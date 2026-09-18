<?php

namespace App\Services\Xero;

use App\Models\XeroConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Centralised Xero authentication and API access.
 *
 * This is the single place that knows how to obtain a valid access token
 * (refreshing it when expired) and how to talk to the Xero API. Jobs and
 * actions should depend on this service rather than re-implementing token
 * refresh or HTTP calls. Tokens never leave the server.
 *
 * Every failure is classified before it leaves this class, so callers never
 * have to read status codes: XeroTransientException means "retry",
 * XeroPermanentException means "stop".
 */
class XeroService
{
    private const TOKEN_URL = 'https://identity.xero.com/connect/token';

    private const API_BASE = 'https://api.xero.com/api.xro/2.0';

    /**
     * The active Xero connection, or throw if none is configured.
     */
    public function activeConnection(): XeroConnection
    {
        $connection = XeroConnection::where('is_active', true)->first();

        if (! $connection) {
            /*
             * Nobody has connected Xero, or the connection was deactivated.
             * No amount of retrying fixes that — an admin has to visit
             * route('xero.connect').
             */
            throw new XeroPermanentException('No active Xero connection found.');
        }

        return $connection;
    }

    /**
     * The active connection with a guaranteed-valid (refreshed if needed)
     * access token.
     */
    public function validConnection(): XeroConnection
    {
        $connection = $this->activeConnection();

        if ($connection->expires_at && $connection->expires_at->isPast()) {
            $connection = $this->refreshToken($connection);
        }

        return $connection;
    }

    /**
     * Exchange the stored refresh token for a fresh access token and persist
     * the rotated credentials.
     */
    public function refreshToken(XeroConnection $connection): XeroConnection
    {
        try {
            $response = Http::asForm()
                ->withBasicAuth(
                    config('services.xero.client_id'),
                    config('services.xero.client_secret')
                )
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $connection->refresh_token,
                ]);
        } catch (ConnectionException $exception) {
            throw new XeroTransientException(
                'Could not reach Xero to refresh the access token: ' . $exception->getMessage()
            );
        }

        if (! $response->successful()) {
            $message = 'Failed to refresh Xero token: ' . $response->body();

            /*
             * A 5xx at the identity endpoint is worth retrying. Anything else
             * is overwhelmingly invalid_grant — the refresh token has already
             * been used or has passed its 60-day idle expiry — which needs an
             * admin to reconnect, so retrying only delays that discovery.
             */
            if ($response->serverError()) {
                throw new XeroTransientException($message, $response->status(), $response->body());
            }

            throw new XeroPermanentException($message, $response->status(), $response->body());
        }

        $tokenData = $response->json();

        $connection->update([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'expires_at' => now()->addSeconds($tokenData['expires_in']),
        ]);

        return $connection->fresh();
    }

    /**
     * Create an invoice in Xero and return the decoded response body.
     *
     * $idempotencyKey must be derived deterministically from the order (see
     * CreateXeroInvoiceJob) so a retried attempt replays the original request
     * instead of creating a second invoice. Xero honours the key for 24
     * hours, which comfortably covers the queue's retry window.
     */
    public function createInvoice(array $payload, ?string $idempotencyKey = null): array
    {
        $headers = $idempotencyKey === null
            ? []
            : ['Idempotency-Key' => $idempotencyKey];

        $response = $this->call(
            fn (PendingRequest $client): Response => $client->post(self::API_BASE . '/Invoices', $payload),
            headers: $headers
        );

        $this->guard($response, 'Xero invoice creation failed');

        return $response->json();
    }

    /**
     * Retrieve a single invoice from Xero as decoded JSON.
     *
     * Reads back the authoritative state of an invoice we have already
     * created — its status, amount due and payment history — without
     * modifying it. Returns the invoice element itself, not the wrapping
     * envelope.
     */
    public function getInvoice(string $invoiceId): array
    {
        $response = $this->call(
            fn (PendingRequest $client): Response => $client->get(self::API_BASE . '/Invoices/' . $invoiceId)
        );

        $this->guard($response, 'Failed to retrieve Xero invoice');

        $invoice = $response->json('Invoices.0');

        if (! is_array($invoice)) {
            throw new XeroPermanentException(
                'Xero returned no invoice for ID ' . $invoiceId . '.',
                $response->status(),
                $response->body()
            );
        }

        return $invoice;
    }

    /**
     * Retrieve the official invoice PDF bytes for the given Xero invoice ID.
     *
     * Uses the same Invoices/{id} endpoint as the JSON fetch, but requests
     * the PDF representation via the Accept header. Returns the raw PDF
     * contents — callers attach this directly and must never write it to a
     * publicly accessible path.
     */
    public function getInvoicePdf(string $invoiceId): string
    {
        $response = $this->call(
            fn (PendingRequest $client): Response => $client->get(self::API_BASE . '/Invoices/' . $invoiceId),
            accept: 'application/pdf'
        );

        $this->guard($response, 'Failed to retrieve Xero invoice PDF');

        return $response->body();
    }

    /**
     * Perform an authenticated request, transparently recovering from a token
     * Xero rejects earlier than its recorded expiry.
     *
     * validConnection() only refreshes on expires_at, so a token revoked or
     * invalidated server-side still looks usable to us. Rather than burn the
     * job's retries on the same dead token, a 401 triggers exactly one
     * refresh-and-replay here. If the replay is also rejected, guard() turns
     * it into a permanent failure.
     *
     * @param  callable(PendingRequest): Response  $perform
     */
    private function call(callable $perform, string $accept = 'application/json', array $headers = []): Response
    {
        $connection = $this->validConnection();

        try {
            $response = $perform($this->client($connection, $accept, $headers));

            if ($response->status() === 401) {
                $connection = $this->refreshToken($connection);

                $response = $perform($this->client($connection, $accept, $headers));
            }
        } catch (ConnectionException $exception) {
            /*
             * DNS failure, timeout, TLS error: Xero was never reached, so the
             * request is safe to repeat.
             */
            throw new XeroTransientException(
                'Could not reach Xero: ' . $exception->getMessage()
            );
        }

        return $response;
    }

    /**
     * Turn a non-2xx Xero response into the right kind of exception.
     */
    private function guard(Response $response, string $context): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $message = $context . ' (HTTP ' . $status . '): ' . $response->body();

        /*
         * 429 is Xero's per-minute / daily rate limit. The first backoff step
         * is a full minute, which clears the per-minute limit without us
         * having to honour Retry-After explicitly.
         */
        if ($status === 429 || $status >= 500) {
            throw new XeroTransientException($message, $status, $response->body());
        }

        /*
         * Everything else is the request itself being wrong — a 400 validation
         * error (bad AccountCode, bad TaxType, malformed payload), a 404, or a
         * 401/403 that already survived the refresh-and-replay above. Replaying
         * it sends Xero the identical request and earns the identical
         * rejection, so fail now and surface it to an admin.
         */
        throw new XeroPermanentException($message, $status, $response->body());
    }

    /**
     * An authenticated HTTP client bound to the active tenant.
     */
    private function client(XeroConnection $connection, string $accept, array $headers = []): PendingRequest
    {
        return Http::withToken($connection->access_token)
            ->withHeaders(array_merge([
                'xero-tenant-id' => $connection->tenant_id,
                'Accept' => $accept,
            ], $headers));
    }
}
