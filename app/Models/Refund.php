<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A refund recorded against an Order.
 *
 * IMPORTANT: recording a Refund is internal bookkeeping only. It does not move
 * any money through Pin Payments, Xero, a bank, or any other provider, and it
 * never modifies the original Order total.
 */
class Refund extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Statuses
    |--------------------------------------------------------------------------
    |
    | recorded  - logged in the admin only; no provider refund was sent.
    | pending   - sent to a provider, awaiting confirmation (future use).
    | processed - the provider genuinely returned the money (future use).
    | failed    - did not complete; releases the refundable balance again.
    |
    */
    public const STATUS_RECORDED = 'recorded';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_RECORDED => 'Recorded',
        self::STATUS_PENDING => 'Pending',
        self::STATUS_PROCESSED => 'Processed',
        self::STATUS_FAILED => 'Failed',
    ];

    /**
     * Statuses that consume an order's refundable balance. A failed refund
     * releases the amount so it can be refunded again.
     *
     * @var array<int, string>
     */
    public const BALANCE_AFFECTING_STATUSES = [
        self::STATUS_RECORDED,
        self::STATUS_PENDING,
        self::STATUS_PROCESSED,
    ];

    protected $fillable = [
        'order_id',
        'created_by',
        'amount',
        'reason',
        'notes',
        'status',
    ];

    protected $casts = [
        // Matches orders.subtotal / orders.total money handling.
        'amount' => 'decimal:2',
    ];

    protected $attributes = [
        'status' => self::STATUS_RECORDED,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The admin user who recorded this refund.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Only refunds that count against the order's refundable balance.
     */
    public function scopeAffectsBalance(Builder $query): Builder
    {
        return $query->whereIn('status', self::BALANCE_AFFECTING_STATUSES);
    }
}
