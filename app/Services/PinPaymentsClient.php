<?php

namespace App\Services;

use App\Services\Pin\PinPaymentResult;
use App\Services\Pin\UnsafeChargePayloadException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Stateless wrapper around the Pin Payments REST API.
 *
 * Responsibilities are intentionally narrow:
 *  - build authenticated requests from config('services.pin') only,
 *  - send them,
 *  - classify every reply into a PinPaymentResult.
 *
 * It never reads env() directly, never touches the database, never creates
 * Orders or mutates PaymentAttempt, never redirects, and never calculates or
 * trusts an amount (the caller supplies the server-derived payload). No
 * Idempotency-Key header is added (Pin documents none).
 */
class PinPaymentsClient
{
    private const SENSITIVE_KEYS = ['number', 'card_number', 'cvc', 'cvv', 'pan'];

    /**
     * Raw card fields that must never be transmitted in a charge request
     * (compared case-insensitively against every key, at any nesting depth).
     */
    private const FORBIDDEN_OUTBOUND_KEYS = [
        'number',
        'card_number',
        'cardnumber',
        'pan',
        'cvc',
        'cvv',
        'expiry_month',
        'expiry_year',
    ];

    private string $baseUrl;
    private string $secretKey;
    private string $publishableKey;
    private bool $sandbox;
    private int $timeout;

    public function __construct(?array $config = null)
    {
        $config ??= config('services.pin', []);

        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://test-api.pinpayments.com/1'), '/');
        $this->secretKey = (string) ($config['secret_key'] ?? '');
        $this->publishableKey = (string) ($config['publishable_key'] ?? '');
        $this->sandbox = (bool) ($config['sandbox'] ?? true);
        $this->timeout = (int) ($config['timeout'] ?? 30);
    }

    /*
    |--------------------------------------------------------------------------
    | Public API
    |--------------------------------------------------------------------------
    */

    /**
     * Create a charge. The caller supplies the full, server-derived payload
     * (amount in cents, card_token, three_d_secure block, etc.). This method
     * forwards it verbatim and does not inspect or recalculate the amount.
     */
    public function createCharge(array $payload): PinPaymentResult
    {
        // Safety guard: refuse to transmit raw card data before any request.
        $this->assertNoRawCardData($payload);

        try {
            $response = $this->client()->post($this->url('/charges'), $payload);
        } catch (ConnectionException $e) {
            return $this->transportFailure($e);
        }

        return $this->classifyCharge($response, allowThreeDSecure: true);
    }

    /**
     * Retrieve a charge by its token. Use this to resolve an uncertain
     * charge whose creation response was lost.
     */
    public function getCharge(string $chargeToken): PinPaymentResult
    {
        try {
            $response = $this->client()->get($this->url('/charges/' . $chargeToken));
        } catch (ConnectionException $e) {
            return $this->transportFailure($e);
        }

        return $this->classifyCharge($response, allowThreeDSecure: false);
    }

    /**
     * Verify a charge after a 3D Secure challenge, using the session_token
     * returned on the callback URL.
     */
    public function verifyThreeDS(string $sessionToken): PinPaymentResult
    {
        try {
            $response = $this->client()->get($this->url('/charges/verify'), [
                'session_token' => $sessionToken,
            ]);
        } catch (ConnectionException $e) {
            return $this->transportFailure($e);
        }

        return $this->classifyCharge($response, allowThreeDSecure: false);
    }

    /**
     * Search charges. Pin's search matches metadata (among other fields), so
     * this is used to locate a charge by our stored checkout-session UUID /
     * payment-attempt id during reconciliation.
     */
    public function searchChargesByMetadata(string $query): PinPaymentResult
    {
        try {
            $response = $this->client()->get($this->url('/charges/search'), [
                'query' => $query,
            ]);
        } catch (ConnectionException $e) {
            return $this->transportFailure($e);
        }

        return $this->classifySearch($response);
    }

    /*
    |--------------------------------------------------------------------------
    | Request construction
    |--------------------------------------------------------------------------
    */

    private function client(): PendingRequest
    {
        // HTTP Basic: secret key as username, empty password.
        return Http::withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->timeout($this->timeout);
    }

    private function url(string $path): string
    {
        return $this->baseUrl . $path;
    }

    /*
    |--------------------------------------------------------------------------
    | Response classification
    |--------------------------------------------------------------------------
    */

    private function classifyCharge(Response $response, bool $allowThreeDSecure): PinPaymentResult
    {
        $body = $this->decode($response);
        $status = $response->status();

        if ($body === null) {
            return $this->malformed($status, $response->body());
        }

        $inner = $this->innerResponse($body);

        // 3D Secure required: HTTP 202 carrying response.redirect_url.
        if ($allowThreeDSecure && $status === 202) {
            if (! empty($inner['redirect_url'])) {
                return $this->fromBody(PinPaymentResult::OUTCOME_THREE_D_SECURE_REQUIRED, $status, $body);
            }

            return $this->malformed($status, $response->body());
        }

        // Validation failure.
        if ($status === 422) {
            return $this->fromBody(PinPaymentResult::OUTCOME_VALIDATION_FAILED, $status, $body);
        }

        // Conclusive charge result carrying response.success.
        if ($response->successful() && array_key_exists('success', $inner)) {
            return $this->fromBody(
                $inner['success'] === true
                    ? PinPaymentResult::OUTCOME_SUCCESS
                    : PinPaymentResult::OUTCOME_DECLINED,
                $status,
                $body
            );
        }

        // Error body (e.g. card_declined, processing_error).
        if (array_key_exists('error', $body)) {
            return $this->fromBody(PinPaymentResult::OUTCOME_DECLINED, $status, $body);
        }

        return $this->malformed($status, $response->body());
    }

    private function classifySearch(Response $response): PinPaymentResult
    {
        $body = $this->decode($response);
        $status = $response->status();

        if ($body === null) {
            return $this->malformed($status, $response->body());
        }

        if ($status === 422) {
            return $this->fromBody(PinPaymentResult::OUTCOME_VALIDATION_FAILED, $status, $body);
        }

        // A successful search returns response as a (possibly empty) list.
        if ($response->successful() && array_key_exists('response', $body) && is_array($body['response'])) {
            return new PinPaymentResult(
                outcome: PinPaymentResult::OUTCOME_SUCCESS,
                httpStatus: $status,
                rawResponse: $this->sanitize($body),
            );
        }

        if (array_key_exists('error', $body)) {
            return $this->fromBody(PinPaymentResult::OUTCOME_DECLINED, $status, $body);
        }

        return $this->malformed($status, $response->body());
    }

    /**
     * Build a result, extracting the durable references Pin places in
     * different positions for success vs. error responses.
     */
    private function fromBody(string $outcome, int $status, array $body): PinPaymentResult
    {
        $inner = $this->innerResponse($body);

        return new PinPaymentResult(
            outcome: $outcome,
            httpStatus: $status,
            success: array_key_exists('success', $inner) ? (bool) $inner['success'] : null,
            chargeToken: $inner['token'] ?? $body['charge_token'] ?? $body['token'] ?? null,
            statusMessage: $inner['status_message'] ?? $body['status_message'] ?? null,
            errorCode: $body['error'] ?? null,
            errorMessage: $body['error_description'] ?? null,
            redirectUrl: $inner['redirect_url'] ?? null,
            rawResponse: $this->sanitize($body),
        );
    }

    private function transportFailure(ConnectionException $e): PinPaymentResult
    {
        return new PinPaymentResult(
            outcome: PinPaymentResult::OUTCOME_TRANSPORT_FAILURE,
            errorMessage: $e->getMessage(),
        );
    }

    private function malformed(int $status, string $rawBody): PinPaymentResult
    {
        return new PinPaymentResult(
            outcome: PinPaymentResult::OUTCOME_MALFORMED,
            httpStatus: $status,
            rawResponse: ['unparsable_body' => mb_substr($rawBody, 0, 1000)],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Recursively reject any known raw-card field, at any nesting depth,
     * before a charge request is sent. This is a safety net only; it does
     * not validate the rest of the payload or touch the amount.
     *
     * @param  array<mixed>  $payload
     *
     * @throws UnsafeChargePayloadException
     */
    private function assertNoRawCardData(array $payload): void
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_OUTBOUND_KEYS, true)) {
                throw new UnsafeChargePayloadException($key);
            }

            if (is_array($value)) {
                $this->assertNoRawCardData($value);
            }
        }
    }

    private function decode(Response $response): ?array
    {
        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function innerResponse(array $body): array
    {
        return isset($body['response']) && is_array($body['response'])
            ? $body['response']
            : [];
    }

    /**
     * Defensively redact any card-sensitive keys before the response is
     * preserved or handed back. Pin does not return PAN/CVC, but this
     * guarantees they can never be stored if a payload ever echoes them.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function sanitize(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $clean[$key] = '[redacted]';

                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $clean;
    }
}
