<?php

namespace Tests\Feature;

use App\Jobs\ProcessOrderJob;
use App\Models\CheckoutSession;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CardPaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://test-api.pinpayments.com/1';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        config()->set('services.pin', [
            'publishable_key' => 'pk_test',
            'secret_key' => 'sk_test',
            'base_url' => self::BASE,
            'sandbox' => true,
        ]);
    }

    private function makeSession(int $quantity = 1, int $students = 1): CheckoutSession
    {
        $studentRows = [];
        for ($i = 0; $i < $students; $i++) {
            $studentRows[] = [
                'first_name' => 'Stu' . $i, 'last_name' => 'Dent',
                'email' => "stu{$i}@example.test", 'phone' => '0400000000',
            ];
        }

        return CheckoutSession::create([
            'course_code' => 'RIIWHS204E',
            'plan_id' => 'PLAN-1',
            'quantity' => $quantity,
            'unit_price' => 100.00,
            'subtotal' => 100.00 * $quantity,
            'expires_at' => now()->addHour(),
            'student_details' => $studentRows,
            'billing_details' => [
                'first_name' => 'Bill', 'last_name' => 'Payer',
                'email' => 'bill@example.test', 'address_1' => '42 Sevenoaks St', 'city' => 'Lathlain',
            ],
            'details_completed_at' => now(),
        ]);
    }

    private function chargeSuccessBody(): array
    {
        return ['response' => [
            'token' => 'ch_success',
            'success' => true,
            'amount' => 10000,
            'currency' => 'AUD',
            'status_message' => 'Success',
            'card' => ['display_number' => 'XXXX-XXXX-XXXX-0000', 'scheme' => 'visa'],
        ]];
    }

    private function signedCallbackUrl(CheckoutSession $session, PaymentAttempt $attempt, string $sessionToken): string
    {
        $url = URL::signedRoute('checkout.card-payment.callback', [
            'checkoutSession' => $session->uuid,
            'attempt' => $attempt->id,
        ]);

        return $url . '&session_token=' . $sessionToken;
    }

    /*
    |--------------------------------------------------------------------------
    | Direct success
    |--------------------------------------------------------------------------
    */
    public function test_direct_success_creates_paid_order_and_dispatches_job(): void
    {
        Http::fake(['*' => Http::response($this->chargeSuccessBody(), 201)]);
        $session = $this->makeSession();

        $response = $this->post(route('checkout.card-payment.store', $session), ['card_token' => 'card_x']);

        $response->assertRedirect(route('checkout.thank-you', $session));

        $attempt = PaymentAttempt::firstOrFail();
        $this->assertSame(PaymentAttempt::STATE_PAID, $attempt->state);
        $this->assertTrue($attempt->success);
        $this->assertSame($session->id, $attempt->paid_session_id);
        $this->assertNull($attempt->inflight_session_id);
        $this->assertSame('ch_success', $attempt->pin_charge_token);
        $this->assertNotNull($attempt->order_id);

        $order = Order::firstOrFail();
        $this->assertSame('card', $order->payment_method);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('ch_success', $order->pin_charge_token);
        $this->assertSame('visa', $order->card_scheme);
        $this->assertCount(1, $order->students);

        $this->assertNotNull($session->fresh()->completed_at);
        Queue::assertPushed(ProcessOrderJob::class);
    }

    public function test_charge_payload_is_server_derived(): void
    {
        Http::fake(['*' => Http::response($this->chargeSuccessBody(), 201)]);
        $session = $this->makeSession();

        $this->post(route('checkout.card-payment.store', $session), [
            'card_token' => 'card_x',
            'amount' => 1, // tampered - must be ignored
            'email' => 'attacker@evil.test',
        ]);

        Http::assertSent(function ($request) use ($session) {
            $body = $request->data();

            return $request->url() === self::BASE . '/charges'
                && $body['amount'] === 10000
                && $body['currency'] === 'AUD'
                && $body['email'] === 'bill@example.test'
                && $body['capture'] === true
                && $body['card_token'] === 'card_x'
                && $body['three_d_secure']['enabled'] === true
                && $body['three_d_secure']['fallback_ok'] === false
                && $body['metadata']['checkout_session_uuid'] === $session->uuid
                && ! empty($body['metadata']['payment_attempt_id']);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | 3D Secure required
    |--------------------------------------------------------------------------
    */
    public function test_3ds_required_redirects_and_keeps_inflight(): void
    {
        $redirect = 'https://sandbox.checkout.com/api2/v2/3ds/acs/sid_123';
        Http::fake(['*' => Http::response([
            'response' => ['token' => 'ch_pending', 'status_message' => 'Pending', 'redirect_url' => $redirect],
        ], 202)]);
        $session = $this->makeSession();

        $response = $this->post(route('checkout.card-payment.store', $session), ['card_token' => 'card_x']);

        $response->assertRedirect($redirect);

        $attempt = PaymentAttempt::firstOrFail();
        $this->assertSame(PaymentAttempt::STATE_THREE_D_SECURE_PENDING, $attempt->state);
        $this->assertSame($session->id, $attempt->inflight_session_id); // kept
        $this->assertSame('ch_pending', $attempt->pin_charge_token);
        $this->assertSame($redirect, $attempt->redirect_url);
        $this->assertNull($attempt->order_id);
        $this->assertNull($session->fresh()->completed_at);
        Queue::assertNothingPushed();
    }

    /*
    |--------------------------------------------------------------------------
    | Decline / validation failure
    |--------------------------------------------------------------------------
    */
    public function test_declined_charge_creates_no_order_and_releases_claim(): void
    {
        Http::fake(['*' => Http::response([
            'error' => 'card_declined', 'error_description' => 'The card was declined', 'charge_token' => 'ch_d',
        ], 400)]);
        $session = $this->makeSession();

        $this->post(route('checkout.card-payment.store', $session), ['card_token' => 'card_x'])
            ->assertRedirect(route('checkout.card-payment.show', $session));

        $attempt = PaymentAttempt::firstOrFail();
        $this->assertSame(PaymentAttempt::STATE_DECLINED, $attempt->state);
        $this->assertNull($attempt->inflight_session_id);
        $this->assertNull($attempt->paid_session_id);
        $this->assertSame('card_declined', $attempt->error_code);
        $this->assertSame(0, Order::count());
        Queue::assertNothingPushed();
    }

    public function test_validation_failure_marks_failed_and_creates_no_order(): void
    {
        Http::fake(['*' => Http::response([
            'error' => 'invalid_resource', 'error_description' => 'Invalid', 'messages' => [],
        ], 422)]);
        $session = $this->makeSession();

        $this->post(route('checkout.card-payment.store', $session), ['card_token' => 'card_x'])
            ->assertRedirect(route('checkout.card-payment.show', $session));

        $attempt = PaymentAttempt::firstOrFail();
        $this->assertSame(PaymentAttempt::STATE_FAILED, $attempt->state);
        $this->assertNull($attempt->inflight_session_id);
        $this->assertSame(0, Order::count());
    }

    /*
    |--------------------------------------------------------------------------
    | Uncertain outcomes
    |--------------------------------------------------------------------------
    */
    public function test_transport_failure_keeps_inflight_claim(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timed out');
        });
        $session = $this->makeSession();

        $this->post(route('checkout.card-payment.store', $session), ['card_token' => 'card_x'])
            ->assertRedirect(route('checkout.card-payment.received', $session));

        $attempt = PaymentAttempt::firstOrFail();
        $this->assertSame(PaymentAttempt::STATE_CHARGING, $attempt->state); // not failed
        $this->assertSame($session->id, $attempt->inflight_session_id);     // claim held
        $this->assertNotNull($attempt->last_checked_at);
        $this->assertSame(0, Order::count());
    }

    public function test_malformed_response_keeps_inflight_claim(): void
    {
        Http::fake(['*' => Http::response('<<not json>>', 200)]);
        $session = $this->makeSession();

        $this->post(route('checkout.card-payment.store', $session), ['card_token' => 'card_x'])
            ->assertRedirect(route('checkout.card-payment.received', $session));

        $attempt = PaymentAttempt::firstOrFail();
        $this->assertSame(PaymentAttempt::STATE_CHARGING, $attempt->state);
        $this->assertSame($session->id, $attempt->inflight_session_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Order-creation failure after a confirmed payment
    |--------------------------------------------------------------------------
    */
    public function test_order_creation_failure_after_payment_is_reconciliation_grade(): void
    {
        Http::fake(['*' => Http::response($this->chargeSuccessBody(), 201)]);
        // quantity 2 but only 1 student -> order creation aborts after payment.
        $session = $this->makeSession(quantity: 2, students: 1);

        $this->post(route('checkout.card-payment.store', $session), ['card_token' => 'card_x'])
            ->assertRedirect(route('checkout.card-payment.received', $session));

        $attempt = PaymentAttempt::firstOrFail();
        $this->assertSame(PaymentAttempt::STATE_PAID_ORDER_FAILED, $attempt->state);
        $this->assertSame($session->id, $attempt->paid_session_id);   // preserved
        $this->assertSame('ch_success', $attempt->pin_charge_token);  // preserved
        $this->assertNull($attempt->order_id);
        $this->assertNull($attempt->inflight_session_id);

        $this->assertSame(0, Order::count());
        $this->assertTrue(
            IntegrationLog::where('service', 'card_order')->where('status', 'failed')->exists()
        );
        Http::assertSentCount(1); // exactly one charge - no second charge
        Queue::assertNothingPushed();
    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate prevention
    |--------------------------------------------------------------------------
    */
    public function test_already_paid_session_rejects_new_charge(): void
    {
        Http::fake();
        $session = $this->makeSession();
        PaymentAttempt::create([
            'checkout_session_id' => $session->id,
            'state' => PaymentAttempt::STATE_PAID,
            'paid_session_id' => $session->id,
            'pin_charge_token' => 'ch_existing',
            'amount_cents' => 10000,
            'success' => true,
        ]);

        $this->post(route('checkout.card-payment.store', $session), ['card_token' => 'card_x'])
            ->assertStatus(409);

        Http::assertNothingSent();
    }

    public function test_in_flight_attempt_rejects_concurrent_charge(): void
    {
        Http::fake();
        $session = $this->makeSession();
        PaymentAttempt::create([
            'checkout_session_id' => $session->id,
            'state' => PaymentAttempt::STATE_CHARGING,
            'inflight_session_id' => $session->id,
            'amount_cents' => 10000,
        ]);

        $this->post(route('checkout.card-payment.store', $session), ['card_token' => 'card_x'])
            ->assertStatus(409);

        Http::assertNothingSent();
    }

    /*
    |--------------------------------------------------------------------------
    | Access control
    |--------------------------------------------------------------------------
    */
    public function test_po_only_client_cannot_post_card_charge(): void
    {
        Http::fake();
        $session = $this->makeSession();
        $poOnly = User::create([
            'name' => 'PO', 'email' => 'po@example.test', 'password' => 'secret',
            'is_admin' => false, 'can_use_purchase_order' => true,
        ]);

        $this->actingAs($poOnly)
            ->post(route('checkout.card-payment.store', $session), ['card_token' => 'card_x'])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_completed_session_rejects_charge(): void
    {
        Http::fake();
        $session = $this->makeSession();
        $session->update(['completed_at' => now()]);

        $this->post(route('checkout.card-payment.store', $session), ['card_token' => 'card_x'])
            ->assertStatus(409);

        Http::assertNothingSent();
    }

    public function test_card_token_is_required(): void
    {
        Http::fake();
        $session = $this->makeSession();

        $this->post(route('checkout.card-payment.store', $session), [])
            ->assertSessionHasErrors('card_token');

        Http::assertNothingSent();
    }

    /*
    |--------------------------------------------------------------------------
    | 3D Secure callback
    |--------------------------------------------------------------------------
    */
    private function pendingAttempt(CheckoutSession $session): PaymentAttempt
    {
        return PaymentAttempt::create([
            'checkout_session_id' => $session->id,
            'state' => PaymentAttempt::STATE_THREE_D_SECURE_PENDING,
            'inflight_session_id' => $session->id,
            'three_d_secure_required' => true,
            'pin_charge_token' => 'ch_pending',
            'redirect_url' => 'https://sandbox.checkout.com/acs',
            'amount_cents' => 10000,
            'checkout_session_uuid' => $session->uuid,
        ]);
    }

    public function test_callback_success_creates_order(): void
    {
        Http::fake(['*' => Http::response([
            'response' => ['token' => 'ch_pending', 'success' => true, 'status_message' => 'Success'],
        ], 200)]);
        $session = $this->makeSession();
        $attempt = $this->pendingAttempt($session);

        $this->get($this->signedCallbackUrl($session, $attempt, 'se_token'))
            ->assertRedirect(route('checkout.thank-you', $session));

        $attempt->refresh();
        $this->assertSame(PaymentAttempt::STATE_PAID, $attempt->state);
        $this->assertSame($session->id, $attempt->paid_session_id);
        $this->assertNull($attempt->inflight_session_id);
        $this->assertNotNull($attempt->order_id);
        $this->assertSame(1, Order::count());
        Queue::assertPushed(ProcessOrderJob::class);
    }

    public function test_callback_failure_creates_no_order(): void
    {
        Http::fake(['*' => Http::response([
            'response' => ['token' => 'ch_pending', 'success' => false, 'status_message' => 'Declined'],
        ], 200)]);
        $session = $this->makeSession();
        $attempt = $this->pendingAttempt($session);

        $this->get($this->signedCallbackUrl($session, $attempt, 'se_token'))
            ->assertRedirect(route('checkout.card-payment.show', $session));

        $attempt->refresh();
        $this->assertSame(PaymentAttempt::STATE_DECLINED, $attempt->state);
        $this->assertNull($attempt->inflight_session_id);
        $this->assertSame(0, Order::count());
    }

    public function test_callback_refresh_does_not_duplicate_payment_or_order(): void
    {
        Http::fake(['*' => Http::response([
            'response' => ['token' => 'ch_pending', 'success' => true],
        ], 200)]);
        $session = $this->makeSession();
        $attempt = $this->pendingAttempt($session);

        $url = $this->signedCallbackUrl($session, $attempt, 'se_token');

        $this->get($url)->assertRedirect(route('checkout.thank-you', $session));
        Http::assertSentCount(1);

        // Second visit (refresh): no new verify call, no duplicate order.
        $this->get($url)->assertRedirect(route('checkout.thank-you', $session));
        Http::assertSentCount(1);
        $this->assertSame(1, Order::count());
        Queue::assertPushed(ProcessOrderJob::class, 1);
    }

    public function test_callback_rejects_invalid_signature(): void
    {
        Http::fake();
        $session = $this->makeSession();
        $attempt = $this->pendingAttempt($session);

        $unsigned = route('checkout.card-payment.callback', [
            'checkoutSession' => $session->uuid,
            'attempt' => $attempt->id,
        ]) . '&session_token=se_token';

        $this->get($unsigned)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_callback_rejects_attempt_from_other_session(): void
    {
        Http::fake();
        $sessionA = $this->makeSession();
        $sessionB = $this->makeSession();
        $attemptB = $this->pendingAttempt($sessionB);

        // Signed for sessionA but referencing sessionB's attempt.
        $url = URL::signedRoute('checkout.card-payment.callback', [
            'checkoutSession' => $sessionA->uuid,
            'attempt' => $attemptB->id,
        ]) . '&session_token=se_token';

        $this->get($url)->assertNotFound();
        Http::assertNothingSent();
    }
}
