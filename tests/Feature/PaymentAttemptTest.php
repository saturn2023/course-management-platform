<?php

namespace Tests\Feature;

use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PaymentAttemptTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(): CheckoutSession
    {
        return CheckoutSession::create([
            'course_code' => 'RIIWHS204E',
            'plan_id' => 'PLAN-1',
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
            'expires_at' => now()->addHour(),
        ]);
    }

    private function makeAttempt(CheckoutSession $session, array $overrides = []): PaymentAttempt
    {
        return PaymentAttempt::create(array_merge([
            'checkout_session_id' => $session->id,
            'state' => PaymentAttempt::STATE_CHARGING,
            'inflight_session_id' => $session->id,
            'amount_cents' => 10000,
            'currency' => 'AUD',
            'checkout_session_uuid' => $session->uuid,
        ], $overrides));
    }

    /*
    |--------------------------------------------------------------------------
    | Schema / casts / relationships
    |--------------------------------------------------------------------------
    */

    public function test_it_persists_state_fields_with_correct_casts(): void
    {
        $session = $this->makeSession();

        $attempt = $this->makeAttempt($session, [
            'success' => true,
            'three_d_secure_required' => true,
            'raw_response' => ['success' => true, 'token' => 'ch_abc'],
            'verified_at' => now(),
            'last_checked_at' => now(),
        ]);

        $attempt->refresh();

        $this->assertIsInt($attempt->amount_cents);
        $this->assertIsBool($attempt->success);
        $this->assertIsBool($attempt->three_d_secure_required);
        $this->assertIsArray($attempt->raw_response);
        $this->assertSame('ch_abc', $attempt->raw_response['token']);
        $this->assertNotNull($attempt->verified_at);
        $this->assertNotNull($attempt->last_checked_at);
    }

    public function test_relationships_resolve(): void
    {
        $user = User::create([
            'name' => 'Buyer',
            'email' => 'buyer@example.test',
            'password' => 'secret',
        ]);
        $session = $this->makeSession();
        $order = Order::create(['subtotal' => 100, 'total' => 100]);

        $attempt = $this->makeAttempt($session, [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'paid_session_id' => null,
        ]);

        $this->assertTrue($attempt->checkoutSession->is($session));
        $this->assertTrue($attempt->inflightSession->is($session));
        $this->assertTrue($attempt->user->is($user));
        $this->assertTrue($attempt->order->is($order));

        $this->assertTrue($session->paymentAttempts->first()->is($attempt));
        $this->assertTrue($order->paymentAttempts->first()->is($attempt));
        $this->assertTrue($user->paymentAttempts->first()->is($attempt));
    }

    /*
    |--------------------------------------------------------------------------
    | Uniqueness guarantees (MySQL-compatible UNIQUE-on-nullable)
    |--------------------------------------------------------------------------
    */

    public function test_only_one_inflight_attempt_per_session(): void
    {
        $session = $this->makeSession();
        $this->makeAttempt($session); // holds inflight_session_id = session->id

        $this->expectException(QueryException::class);

        // A second concurrent in-flight claim for the same session must fail.
        $this->makeAttempt($session);
    }

    public function test_only_one_paid_attempt_per_session(): void
    {
        $session = $this->makeSession();

        $this->makeAttempt($session, [
            'state' => PaymentAttempt::STATE_PAID,
            'inflight_session_id' => null,
            'paid_session_id' => $session->id,
            'success' => true,
            'pin_charge_token' => 'ch_first',
        ]);

        $this->expectException(QueryException::class);

        $this->makeAttempt($session, [
            'state' => PaymentAttempt::STATE_PAID,
            'inflight_session_id' => null,
            'paid_session_id' => $session->id,
            'success' => true,
            'pin_charge_token' => 'ch_second',
        ]);
    }

    public function test_pin_charge_token_is_unique(): void
    {
        $sessionA = $this->makeSession();
        $sessionB = $this->makeSession();

        $this->makeAttempt($sessionA, [
            'inflight_session_id' => null,
            'pin_charge_token' => 'ch_shared',
            'state' => PaymentAttempt::STATE_DECLINED,
        ]);

        $this->expectException(QueryException::class);

        $this->makeAttempt($sessionB, [
            'inflight_session_id' => null,
            'pin_charge_token' => 'ch_shared',
            'state' => PaymentAttempt::STATE_DECLINED,
        ]);
    }

    public function test_multiple_terminated_attempts_with_null_locks_are_allowed(): void
    {
        $session = $this->makeSession();

        // Two declined retries for the same session: both clear the locks and
        // carry no charge token, so the UNIQUE indexes permit both.
        $this->makeAttempt($session, [
            'inflight_session_id' => null,
            'state' => PaymentAttempt::STATE_DECLINED,
        ]);
        $this->makeAttempt($session, [
            'inflight_session_id' => null,
            'state' => PaymentAttempt::STATE_DECLINED,
        ]);

        $this->assertSame(2, PaymentAttempt::where('checkout_session_id', $session->id)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | State-machine invariants
    |--------------------------------------------------------------------------
    */

    public function test_mark_paid_sets_permanent_lock_and_clears_inflight(): void
    {
        $session = $this->makeSession();
        $attempt = $this->makeAttempt($session, ['pin_charge_token' => 'ch_keepme']);

        $attempt->markPaid()->save();
        $attempt->refresh();

        $this->assertSame(PaymentAttempt::STATE_PAID, $attempt->state);
        $this->assertTrue($attempt->success);
        $this->assertSame($session->id, $attempt->paid_session_id);
        $this->assertNull($attempt->inflight_session_id);
        $this->assertSame('ch_keepme', $attempt->pin_charge_token);
    }

    public function test_mark_paid_order_failed_preserves_payment_evidence(): void
    {
        $session = $this->makeSession();
        $attempt = $this->makeAttempt($session, ['pin_charge_token' => 'ch_keepme']);
        $attempt->markPaid()->save();

        $attempt->markPaidOrderFailed()->save();
        $attempt->refresh();

        $this->assertSame(PaymentAttempt::STATE_PAID_ORDER_FAILED, $attempt->state);
        $this->assertSame($session->id, $attempt->paid_session_id); // preserved
        $this->assertSame('ch_keepme', $attempt->pin_charge_token);  // preserved
        $this->assertNull($attempt->order_id);
        $this->assertNull($attempt->inflight_session_id);
        $this->assertTrue($attempt->isPaid());
    }

    public function test_paid_attempt_cannot_return_to_chargeable_state(): void
    {
        $session = $this->makeSession();
        $attempt = $this->makeAttempt($session, ['pin_charge_token' => 'ch_keepme']);
        $attempt->markPaid()->save();

        $this->expectException(LogicException::class);
        $attempt->markDeclined();
    }

    public function test_paid_attempt_cannot_be_marked_failed(): void
    {
        $session = $this->makeSession();
        $attempt = $this->makeAttempt($session, ['pin_charge_token' => 'ch_keepme']);
        $attempt->markPaid()->save();

        $this->expectException(LogicException::class);
        $attempt->markFailed();
    }

    public function test_mark_paid_order_failed_requires_paid_state(): void
    {
        $session = $this->makeSession();
        $attempt = $this->makeAttempt($session);

        $this->expectException(LogicException::class);
        $attempt->markPaidOrderFailed();
    }
}
