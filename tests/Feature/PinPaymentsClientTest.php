<?php

namespace Tests\Feature;

use App\Services\Pin\PinPaymentResult;
use App\Services\Pin\UnsafeChargePayloadException;
use App\Services\PinPaymentsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PinPaymentsClientTest extends TestCase
{
    private const BASE = 'https://test-api.pinpayments.com/1';
    private const SECRET = 'sk_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.pin', [
            'publishable_key' => 'pk_test_pub',
            'secret_key' => self::SECRET,
            'base_url' => self::BASE,
            'sandbox' => true,
        ]);
    }

    private function client(): PinPaymentsClient
    {
        return new PinPaymentsClient();
    }

    private function expectedBasicAuthHeader(): string
    {
        // secret key as username, empty password.
        return 'Basic ' . base64_encode(self::SECRET . ':');
    }

    /*
    |--------------------------------------------------------------------------
    | createCharge
    |--------------------------------------------------------------------------
    */

    public function test_create_charge_success_201(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => [
                    'token' => 'ch_success',
                    'success' => true,
                    'amount' => 10000,
                    'currency' => 'AUD',
                    'status_message' => 'Success',
                    'card' => [
                        'display_number' => 'XXXX-XXXX-XXXX-0000',
                        'scheme' => 'visa',
                    ],
                ],
            ], 201),
        ]);

        $result = $this->client()->createCharge(['amount' => 10000, 'card_token' => 'card_x']);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(201, $result->httpStatus);
        $this->assertTrue($result->success);
        $this->assertSame('ch_success', $result->chargeToken);
        $this->assertSame('Success', $result->statusMessage);
        $this->assertNull($result->errorCode);
    }

    public function test_create_charge_uses_basic_auth_and_correct_endpoint(): void
    {
        Http::fake(['*' => Http::response(['response' => ['token' => 'ch_x', 'success' => true]], 201)]);

        $this->client()->createCharge(['amount' => 500]);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === self::BASE . '/charges'
                && $request->hasHeader('Authorization', $this->expectedBasicAuthHeader());
        });
    }

    public function test_create_charge_3ds_required_202(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => [
                    'token' => 'ch_pending',
                    'status_message' => 'Pending',
                    'redirect_url' => 'https://sandbox.checkout.com/api2/v2/3ds/acs/sid_123',
                ],
            ], 202),
        ]);

        $result = $this->client()->createCharge(['amount' => 10000]);

        $this->assertTrue($result->requiresThreeDSecure());
        $this->assertSame(202, $result->httpStatus);
        $this->assertSame('ch_pending', $result->chargeToken);
        $this->assertSame('https://sandbox.checkout.com/api2/v2/3ds/acs/sid_123', $result->redirectUrl);
        $this->assertSame('Pending', $result->statusMessage);
    }

    public function test_create_charge_202_without_redirect_url_is_malformed(): void
    {
        Http::fake(['*' => Http::response(['response' => ['token' => 'ch_x', 'status_message' => 'Pending']], 202)]);

        $result = $this->client()->createCharge(['amount' => 10000]);

        $this->assertTrue($result->isMalformed());
    }

    public function test_create_charge_declined(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => 'card_declined',
                'error_description' => 'The card was declined',
                'charge_token' => 'ch_declined',
            ], 400),
        ]);

        $result = $this->client()->createCharge(['amount' => 10000]);

        $this->assertTrue($result->isDeclined());
        $this->assertSame(400, $result->httpStatus);
        $this->assertSame('card_declined', $result->errorCode);
        $this->assertSame('The card was declined', $result->errorMessage);
        $this->assertSame('ch_declined', $result->chargeToken);
    }

    public function test_create_charge_validation_failure_422(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => 'invalid_resource',
                'error_description' => 'One or more parameters were missing or invalid',
                'messages' => [
                    ['code' => 'amount_invalid', 'message' => "Amount can't be blank", 'param' => 'amount'],
                ],
            ], 422),
        ]);

        $result = $this->client()->createCharge([]);

        $this->assertTrue($result->isValidationFailure());
        $this->assertSame(422, $result->httpStatus);
        $this->assertSame('invalid_resource', $result->errorCode);
        $this->assertSame('amount_invalid', $result->rawResponse['messages'][0]['code']);
    }

    public function test_create_charge_timeout_is_transport_failure(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $result = $this->client()->createCharge(['amount' => 10000]);

        $this->assertTrue($result->isTransportFailure());
        $this->assertTrue($result->isUncertain());
        $this->assertNull($result->httpStatus);
        $this->assertStringContainsString('timed out', (string) $result->errorMessage);
    }

    public function test_create_charge_invalid_json_is_malformed(): void
    {
        Http::fake(['*' => Http::response('<<< not json >>>', 200)]);

        $result = $this->client()->createCharge(['amount' => 10000]);

        $this->assertTrue($result->isMalformed());
        $this->assertSame(200, $result->httpStatus);
    }

    public function test_create_charge_missing_expected_fields_is_malformed(): void
    {
        // 200 but neither response.success nor a top-level error is present.
        Http::fake(['*' => Http::response(['response' => ['foo' => 'bar']], 200)]);

        $result = $this->client()->createCharge(['amount' => 10000]);

        $this->assertTrue($result->isMalformed());
    }

    public function test_sensitive_keys_are_redacted_from_raw_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => [
                    'token' => 'ch_x',
                    'success' => true,
                    'number' => '5520000000000000',
                    'cvc' => '123',
                ],
            ], 201),
        ]);

        $result = $this->client()->createCharge(['amount' => 10000]);

        $this->assertSame('[redacted]', $result->rawResponse['response']['number']);
        $this->assertSame('[redacted]', $result->rawResponse['response']['cvc']);
    }

    /*
    |--------------------------------------------------------------------------
    | getCharge
    |--------------------------------------------------------------------------
    */

    public function test_get_charge_retrieval(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => ['token' => 'ch_abc', 'success' => true, 'status_message' => 'Success'],
            ], 200),
        ]);

        $result = $this->client()->getCharge('ch_abc');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('ch_abc', $result->chargeToken);

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && $request->url() === self::BASE . '/charges/ch_abc'
                && $request->hasHeader('Authorization', $this->expectedBasicAuthHeader());
        });
    }

    public function test_get_charge_reports_unsuccessful_charge_as_declined(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => ['token' => 'ch_abc', 'success' => false, 'status_message' => 'Declined'],
            ], 200),
        ]);

        $result = $this->client()->getCharge('ch_abc');

        $this->assertTrue($result->isDeclined());
        $this->assertFalse($result->success);
    }

    /*
    |--------------------------------------------------------------------------
    | verifyThreeDS
    |--------------------------------------------------------------------------
    */

    public function test_verify_three_ds_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => ['token' => 'ch_verified', 'success' => true, 'status_message' => 'Success'],
            ], 200),
        ]);

        $result = $this->client()->verifyThreeDS('se_session123');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('ch_verified', $result->chargeToken);

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_starts_with($request->url(), self::BASE . '/charges/verify')
                && str_contains($request->url(), 'session_token=se_session123')
                && $request->hasHeader('Authorization', $this->expectedBasicAuthHeader());
        });
    }

    public function test_verify_three_ds_failure_is_declined(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => ['token' => 'ch_v', 'success' => false],
            ], 200),
        ]);

        $result = $this->client()->verifyThreeDS('se_session123');

        $this->assertTrue($result->isDeclined());
    }

    /*
    |--------------------------------------------------------------------------
    | searchChargesByMetadata
    |--------------------------------------------------------------------------
    */

    public function test_search_charges_by_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => [
                    ['token' => 'ch_found', 'success' => true],
                ],
                'pagination' => ['count' => 1, 'page' => 1],
            ], 200),
        ]);

        $result = $this->client()->searchChargesByMetadata('checkout-uuid-123');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('ch_found', $result->rawResponse['response'][0]['token']);

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_starts_with($request->url(), self::BASE . '/charges/search')
                && str_contains($request->url(), 'query=checkout-uuid-123')
                && $request->hasHeader('Authorization', $this->expectedBasicAuthHeader());
        });
    }

    public function test_search_timeout_is_transport_failure(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $result = $this->client()->searchChargesByMetadata('anything');

        $this->assertTrue($result->isTransportFailure());
    }

    public function test_no_idempotency_key_header_is_sent(): void
    {
        Http::fake(['*' => Http::response(['response' => ['token' => 'ch_x', 'success' => true]], 201)]);

        $this->client()->createCharge(['amount' => 10000]);

        Http::assertSent(fn ($request) => ! $request->hasHeader('Idempotency-Key'));
    }

    /*
    |--------------------------------------------------------------------------
    | Outbound raw-card-data safety guard
    |--------------------------------------------------------------------------
    */

    public function test_create_charge_accepts_card_token_payload(): void
    {
        Http::fake(['*' => Http::response(['response' => ['token' => 'ch_ok', 'success' => true]], 201)]);

        $result = $this->client()->createCharge([
            'amount' => 10000,
            'currency' => 'AUD',
            'email' => 'buyer@example.test',
            'description' => 'Course',
            'card_token' => 'card_opaque_token',
        ]);

        $this->assertTrue($result->isSuccessful());
        Http::assertSentCount(1);
    }

    public function test_create_charge_rejects_top_level_raw_card_fields(): void
    {
        Http::fake();

        foreach (['number', 'card_number', 'cardNumber', 'pan', 'cvc', 'cvv', 'expiry_month', 'expiry_year'] as $field) {
            try {
                $this->client()->createCharge(['amount' => 10000, $field => 'x']);
                $this->fail("Expected rejection for raw card field [{$field}].");
            } catch (UnsafeChargePayloadException $e) {
                $this->assertSame($field, $e->forbiddenKey);
            }
        }

        Http::assertNothingSent();
    }

    public function test_create_charge_rejects_nested_raw_card_fields(): void
    {
        Http::fake();

        $this->expectException(UnsafeChargePayloadException::class);

        $this->client()->createCharge([
            'amount' => 10000,
            'card' => [
                'name' => 'Roland Robot',
                'number' => '5520000000000000',
                'cvc' => '123',
            ],
        ]);
    }

    public function test_no_http_request_is_sent_when_a_forbidden_field_is_detected(): void
    {
        Http::fake();

        try {
            $this->client()->createCharge([
                'amount' => 10000,
                'metadata' => ['card' => ['cvc' => '999']], // deeply nested
            ]);
            $this->fail('Expected UnsafeChargePayloadException.');
        } catch (UnsafeChargePayloadException $e) {
            $this->assertSame('cvc', $e->forbiddenKey);
        }

        Http::assertNothingSent();
    }
}
