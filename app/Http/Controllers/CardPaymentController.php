<?php

namespace App\Http\Controllers;

use App\Http\Requests\CardChargeRequest;
use App\Jobs\ProcessOrderJob;
use App\Models\CheckoutSession;
use App\Models\IntegrationLog;
use App\Models\PaymentAttempt;
use App\Services\CheckoutOrderCreator;
use App\Services\Pin\PinPaymentResult;
use App\Services\PinPaymentsClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Throwable;

class CardPaymentController extends Controller
{
    public function __construct(
        private PinPaymentsClient $pin,
        private CheckoutOrderCreator $orderCreator,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | POST: start a card payment
    |--------------------------------------------------------------------------
    */
    public function store(CardChargeRequest $request, CheckoutSession $checkoutSession): RedirectResponse
    {
        $this->guardCardCheckout($checkoutSession);

        $cardToken = (string) $request->validated()['card_token'];

        // Transaction 1: claim. Lock the session, reject duplicates, create the
        // in-flight PaymentAttempt, then commit BEFORE calling Pin.
        $attempt = DB::transaction(function () use ($checkoutSession) {
            $session = CheckoutSession::whereKey($checkoutSession->id)->lockForUpdate()->firstOrFail();

            abort_if($session->isExpired(), 410, 'This checkout session has expired.');
            abort_if($session->isCompleted(), 409, 'This checkout has already been completed.');
            abort_unless($session->hasSavedDetails(), 422, 'Student and billing details must be saved first.');

            $alreadyPaid = PaymentAttempt::query()
                ->where('checkout_session_id', $session->id)
                ->whereIn('state', PaymentAttempt::PAID_STATES)
                ->exists();

            abort_if($alreadyPaid, 409, 'This checkout has already been paid.');

            $inFlight = PaymentAttempt::query()
                ->where('inflight_session_id', $session->id)
                ->exists();

            abort_if($inFlight, 409, 'A payment is already in progress for this checkout.');

            return PaymentAttempt::create([
                'checkout_session_id' => $session->id,
                'user_id' => auth()->id(),
                'inflight_session_id' => $session->id,
                'state' => PaymentAttempt::STATE_CHARGING,
                'amount_cents' => $this->amountCents($session),
                'currency' => 'AUD',
                'success' => false,
                'billing_email' => $session->billing_details['email'] ?? null,
                'checkout_session_uuid' => $session->uuid,
            ]);
        });

        // Pin request — outside all database transactions.
        $result = $this->pin->createCharge(
            $this->buildChargePayload($checkoutSession, $attempt, $cardToken, $request->ip())
        );

        return $this->handleChargeResult($result, $checkoutSession, $attempt, isCallback: false);
    }

    /*
    |--------------------------------------------------------------------------
    | GET: 3D Secure callback
    |--------------------------------------------------------------------------
    */
    public function callback(Request $request, CheckoutSession $checkoutSession): RedirectResponse
    {
        // Pin appends ?session_token=... after we signed the URL, so ignore it
        // when validating the signature.
        abort_unless(
            $request->hasValidSignatureWhileIgnoring(['session_token']),
            403,
            'Invalid or expired payment callback.'
        );

        $attempt = PaymentAttempt::query()
            ->where('id', (int) $request->query('attempt'))
            ->where('checkout_session_id', $checkoutSession->id)
            ->first();

        abort_if($attempt === null, 404);

        // Refresh-safe: if already finalised, do not re-verify or re-charge.
        if ($attempt->isPaid()) {
            return $attempt->order_id
                ? redirect()->route('checkout.thank-you', $checkoutSession)->with('order_id', $attempt->order_id)
                : redirect()->route('checkout.card-payment.received', $checkoutSession);
        }

        if ($attempt->state !== PaymentAttempt::STATE_THREE_D_SECURE_PENDING) {
            return redirect()
                ->route('checkout.card-payment.show', $checkoutSession)
                ->withErrors(['card' => 'This payment could not be completed. Please try again.']);
        }

        $sessionToken = (string) $request->query('session_token');
        abort_if($sessionToken === '', 400, 'Missing payment session token.');

        $result = $this->pin->verifyThreeDS($sessionToken);

        return $this->handleChargeResult($result, $checkoutSession, $attempt, isCallback: true);
    }

    /*
    |--------------------------------------------------------------------------
    | Outcome routing
    |--------------------------------------------------------------------------
    */
    private function handleChargeResult(
        PinPaymentResult $result,
        CheckoutSession $session,
        PaymentAttempt $attempt,
        bool $isCallback,
    ): RedirectResponse {
        if ($result->isSuccessful()) {
            return $this->finalisePaidCharge($session, $attempt, $result);
        }

        if (! $isCallback && $result->requiresThreeDSecure()) {
            return $this->beginThreeDSecure($session, $attempt, $result);
        }

        if ($result->isUncertain()) {
            return $this->handleUncertain($session, $attempt, $result);
        }

        // Declined or validation failure: terminal, clears the in-flight claim.
        return $this->handleFailedCharge($session, $attempt, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | Success path: confirm payment, then create the order (separate commits)
    |--------------------------------------------------------------------------
    */
    private function finalisePaidCharge(
        CheckoutSession $session,
        PaymentAttempt $attempt,
        PinPaymentResult $result,
    ): RedirectResponse {
        $this->commitPayment($session, $attempt, $result);

        try {
            ['orderId' => $orderId, 'created' => $created] = $this->createOrder($session, $attempt);
        } catch (Throwable $e) {
            $this->handleOrderCreationFailure($attempt, $e);

            return redirect()->route('checkout.card-payment.received', $session);
        }

        if ($created && $orderId) {
            ProcessOrderJob::dispatch($orderId);
        }

        return redirect()
            ->route('checkout.thank-you', $session)
            ->with('order_id', $orderId);
    }

    /**
     * Transaction 2: persist the confirmed payment independently of Order
     * creation, so an Order exception can never roll back the recorded charge.
     */
    private function commitPayment(CheckoutSession $session, PaymentAttempt $attempt, PinPaymentResult $result): void
    {
        DB::transaction(function () use ($session, $attempt, $result) {
            CheckoutSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $locked = PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPaid()) {
                return; // idempotent
            }

            $locked->forceFill([
                'pin_charge_token' => $result->chargeToken,
                'status_message' => $result->statusMessage,
                'error_code' => $result->errorCode,
                'raw_response' => $result->rawResponse,
                'verified_at' => now(),
            ]);

            $locked->markPaid()->save();
        });
    }

    /**
     * Transaction 3: create the Order. Idempotent — a refresh or retry that
     * finds an existing order links nothing new and dispatches no job.
     *
     * @return array{orderId: int|null, created: bool}
     */
    private function createOrder(CheckoutSession $session, PaymentAttempt $attempt): array
    {
        $orderId = null;
        $created = false;

        DB::transaction(function () use ($session, $attempt, &$orderId, &$created) {
            $lockedSession = CheckoutSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $lockedAttempt = PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($lockedAttempt->order_id) {
                $orderId = $lockedAttempt->order_id;

                return;
            }

            if ($lockedSession->order_id) {
                $orderId = $lockedSession->order_id;

                return;
            }

            $order = $this->orderCreator->createPaidCardOrder($lockedSession, $lockedAttempt);

            $lockedAttempt->forceFill(['order_id' => $order->id])->save();
            $lockedSession->forceFill(['order_id' => $order->id, 'completed_at' => now()])->save();

            $orderId = $order->id;
            $created = true;
        });

        return ['orderId' => $orderId, 'created' => $created];
    }

    /**
     * Payment succeeded but Order creation failed. Preserve the payment
     * evidence, mark paid_order_failed (no second charge possible), log it.
     */
    private function handleOrderCreationFailure(PaymentAttempt $attempt, Throwable $e): void
    {
        DB::transaction(function () use ($attempt) {
            $locked = PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->state === PaymentAttempt::STATE_PAID) {
                $locked->markPaidOrderFailed()->save();
            }
        });

        $fresh = PaymentAttempt::find($attempt->id);

        IntegrationLog::create([
            'order_id' => null,
            'service' => 'card_order',
            'action' => 'create_order',
            'status' => 'failed',
            'request_payload' => json_encode([
                'payment_attempt_id' => $attempt->id,
                'checkout_session_id' => $attempt->checkout_session_id,
                'pin_charge_token' => $fresh?->pin_charge_token,
            ]),
            'error_message' => $e->getMessage(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 3D Secure pending
    |--------------------------------------------------------------------------
    */
    private function beginThreeDSecure(
        CheckoutSession $session,
        PaymentAttempt $attempt,
        PinPaymentResult $result,
    ): RedirectResponse {
        DB::transaction(function () use ($session, $attempt, $result) {
            CheckoutSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $locked = PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPaid()) {
                return;
            }

            $locked->forceFill([
                'state' => PaymentAttempt::STATE_THREE_D_SECURE_PENDING,
                'three_d_secure_required' => true,
                'pin_charge_token' => $result->chargeToken,
                'redirect_url' => $result->redirectUrl,
                'status_message' => $result->statusMessage,
                'raw_response' => $result->rawResponse,
                // inflight_session_id is intentionally kept.
            ])->save();
        });

        return redirect()->away($result->redirectUrl);
    }

    /*
    |--------------------------------------------------------------------------
    | Uncertain outcome (transport / malformed): keep the in-flight claim
    |--------------------------------------------------------------------------
    */
    private function handleUncertain(
        CheckoutSession $session,
        PaymentAttempt $attempt,
        PinPaymentResult $result,
    ): RedirectResponse {
        DB::transaction(function () use ($attempt, $result) {
            $locked = PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPaid()) {
                return;
            }

            // Do NOT clear inflight and do NOT mark failed: the claim is held
            // until reconciliation resolves the real status.
            $locked->forceFill([
                'status_message' => $result->statusMessage ?? 'uncertain',
                'pin_charge_token' => $result->chargeToken ?? $locked->pin_charge_token,
                'raw_response' => $result->rawResponse,
                'last_checked_at' => now(),
            ])->save();
        });

        return redirect()->route('checkout.card-payment.received', $session);
    }

    /*
    |--------------------------------------------------------------------------
    | Declined / validation failure: terminal, releases the claim for retry
    |--------------------------------------------------------------------------
    */
    private function handleFailedCharge(
        CheckoutSession $session,
        PaymentAttempt $attempt,
        PinPaymentResult $result,
    ): RedirectResponse {
        DB::transaction(function () use ($attempt, $result) {
            $locked = PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPaid()) {
                return;
            }

            $locked->forceFill([
                'pin_charge_token' => $result->chargeToken ?? $locked->pin_charge_token,
                'error_code' => $result->errorCode,
                'status_message' => $result->statusMessage ?? $result->errorMessage,
                'raw_response' => $result->rawResponse,
            ]);

            if ($result->isValidationFailure()) {
                $locked->markFailed()->save();
            } else {
                $locked->markDeclined()->save();
            }
        });

        return redirect()
            ->route('checkout.card-payment.show', $session)
            ->withErrors(['card' => 'Your payment could not be completed. Please check your card details and try again.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    private function guardCardCheckout(CheckoutSession $session): void
    {
        abort_if($session->isExpired(), 410, 'This checkout session has expired.');
        abort_if($session->isCompleted(), 409, 'This checkout has already been completed.');
        abort_unless($session->hasSavedDetails(), 422, 'Student and billing details must be saved first.');

        $user = auth()->user();
        abort_if(
            $user && ! $user->canPayByCard(),
            403,
            'You are not authorised to use card checkout.'
        );
    }

    private function amountCents(CheckoutSession $session): int
    {
        return (int) round(((float) $session->subtotal) * 100);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildChargePayload(
        CheckoutSession $session,
        PaymentAttempt $attempt,
        string $cardToken,
        ?string $ipAddress,
    ): array {
        $callbackUrl = URL::signedRoute('checkout.card-payment.callback', [
            'checkoutSession' => $session->uuid,
            'attempt' => $attempt->id,
        ]);

        return [
            'amount' => $attempt->amount_cents,
            'currency' => 'AUD',
            'email' => $session->billing_details['email'] ?? null,
            'description' => $this->chargeDescription($session),
            'ip_address' => $ipAddress,
            'card_token' => $cardToken,
            'capture' => true,
            'metadata' => [
                'checkout_session_uuid' => $session->uuid,
                'payment_attempt_id' => (string) $attempt->id,
            ],
            'three_d_secure' => [
                'enabled' => true,
                'fallback_ok' => false,
                'callback_url' => $callbackUrl,
            ],
        ];
    }

    private function chargeDescription(CheckoutSession $session): string
    {
        $name = $session->course_title
            ?: ($session->course_code ?: 'Course');

        if (! empty($session->plan_title)) {
            $name .= ' - ' . $session->plan_title;
        }

        return $name;
    }
}
