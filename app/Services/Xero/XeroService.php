<?php

namespace App\Services\Xero;

use App\Models\XeroConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Centralised Xero authentication and API access.
 *
 * This is the single place that knows how to obtain a valid access token
 * (refreshing it when expired) and how to talk to the Xero API. Jobs and
 * actions should depend on this service rather than re-implementing token
 * refresh or HTTP calls. Tokens never leave the server.
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
            throw new RuntimeException('No active Xero connection found.');
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
        $response = Http::asForm()
            ->withBasicAuth(
                config('services.xero.client_id'),
                config('services.xero.client_secret')
            )
            ->post(self::TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $connection->refresh_token,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Failed to refresh Xero token: ' . $response->body()
            );
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
     */
    public function createInvoice(array $payload): array
    {
        $response = $this->client()
            ->post(self::API_BASE . '/Invoices', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Xero invoice creation failed: ' . $response->body()
            );
        }

        return $response->json();
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
        $response = $this->client('application/pdf')
            ->get(self::API_BASE . '/Invoices/' . $invoiceId);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Failed to retrieve Xero invoice PDF (HTTP ' . $response->status() . ').'
            );
        }

        return $response->body();
    }

    /**
     * An authenticated HTTP client bound to the active tenant, with a
     * guaranteed-valid token.
     */
    private function client(string $accept = 'application/json'): PendingRequest
    {
        $connection = $this->validConnection();

        return Http::withToken($connection->access_token)
            ->withHeaders([
                'xero-tenant-id' => $connection->tenant_id,
                'Accept' => $accept,
            ]);
    }
}
