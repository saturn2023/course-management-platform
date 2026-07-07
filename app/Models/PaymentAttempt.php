<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentAttempt extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | States
    |--------------------------------------------------------------------------
    */
    public const STATE_CHARGING = 'charging';
    public const STATE_THREE_D_SECURE_PENDING = 'three_d_secure_pending';
    public const STATE_PAID = 'paid';
    public const STATE_PAID_ORDER_FAILED = 'paid_order_failed';
    public const STATE_DECLINED = 'declined';
    public const STATE_FAILED = 'failed';

    /** Non-terminal states that hold the inflight lock. */
    public const INFLIGHT_STATES = [
        self::STATE_CHARGING,
        self::STATE_THREE_D_SECURE_PENDING,
    ];

    /** States in which the customer's card has been conclusively charged. */
    public const PAID_STATES = [
        self::STATE_PAID,
        self::STATE_PAID_ORDER_FAILED,
    ];

    protected $fillable = [
        'checkout_session_id',
        'order_id',
        'user_id',
        'inflight_session_id',
        'paid_session_id',
        'pin_charge_token',
        'pin_session_token',
        'state',
        'amount_cents',
        'currency',
        'success',
        'three_d_secure_required',
        'redirect_url',
        'verified_at',
        'error_code',
        'status_message',
        'raw_response',
        'last_checked_at',
        'billing_email',
        'checkout_session_uuid',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'success' => 'boolean',
        'three_d_secure_required' => 'boolean',
        'raw_response' => 'array',
        'verified_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function checkoutSession(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class);
    }

    public function inflightSession(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'inflight_session_id');
    }

    public function paidSession(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'paid_session_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | State helpers
    |--------------------------------------------------------------------------
    */
    public function isPaid(): bool
    {
        return in_array($this->state, self::PAID_STATES, true);
    }

    public function isInflight(): bool
    {
        return in_array($this->state, self::INFLIGHT_STATES, true);
    }

    /*
    |--------------------------------------------------------------------------
    | Guarded transitions
    |--------------------------------------------------------------------------
    |
    | These mutate the in-memory model and return $this; the caller persists
    | within the appropriate transaction. They enforce the invariants:
    |
    |  - paid_session_id is set on payment and never cleared (kept for both
    |    paid and paid_order_failed).
    |  - inflight_session_id is cleared on every terminal transition.
    |  - pin_charge_token is preserved across paid -> paid_order_failed.
    |  - a paid attempt can never return to a chargeable / unsuccessful state.
    |
    */

    /**
     * Confirmed successful payment. Sets the permanent paid lock and clears
     * the inflight lock. pin_charge_token is left untouched (preserved).
     */
    public function markPaid(): static
    {
        $this->forceFill([
            'state' => self::STATE_PAID,
            'success' => true,
            'paid_session_id' => $this->checkout_session_id,
            'inflight_session_id' => null,
        ]);

        return $this;
    }

    /**
     * Payment succeeded but Order creation failed. paid_session_id and
     * pin_charge_token are preserved; order_id stays null.
     */
    public function markPaidOrderFailed(): static
    {
        if (! $this->isPaid()) {
            throw new LogicException(
                'Only a paid attempt can transition to paid_order_failed.'
            );
        }

        $this->forceFill([
            'state' => self::STATE_PAID_ORDER_FAILED,
            'inflight_session_id' => null,
            'order_id' => null,
        ]);

        return $this;
    }

    public function markDeclined(): static
    {
        return $this->markUnsuccessful(self::STATE_DECLINED);
    }

    public function markFailed(): static
    {
        return $this->markUnsuccessful(self::STATE_FAILED);
    }

    /**
     * Terminal unsuccessful transition. A paid attempt may never be moved
     * back to a chargeable / unsuccessful state.
     */
    protected function markUnsuccessful(string $state): static
    {
        if ($this->isPaid()) {
            throw new LogicException(
                'A paid attempt cannot return to a chargeable or unsuccessful state.'
            );
        }

        $this->forceFill([
            'state' => $state,
            'inflight_session_id' => null,
        ]);

        return $this;
    }
}
