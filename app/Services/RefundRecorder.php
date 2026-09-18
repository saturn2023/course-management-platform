<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

/**
 * Records refunds against orders.
 *
 * This is the single server-side authority for refund validation: every path
 * that creates or updates a refund (the Orders row action, the Refunds
 * resource, the Order relation manager) goes through here, so the balance
 * rules cannot be bypassed by the UI.
 *
 * No money is moved. Creating a Refund record does NOT refund the customer
 * through Pin Payments, Xero, or any other provider.
 */
class RefundRecorder
{
    /**
     * Record a refund against an order inside a locked transaction.
     *
     * @param  array{amount: mixed, reason: string, notes?: ?string, status?: ?string}  $data
     *
     * @throws RefundNotAllowedException
     */
    public function record(Order $order, array $data, ?int $createdBy = null): Refund
    {
        return DB::transaction(function () use ($order, $data, $createdBy) {
            // Lock the order row so two concurrent refunds cannot both pass
            // the remaining-balance check.
            $lockedOrder = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $amount = self::normaliseAmount($data['amount'] ?? null);

            self::assertAmountAllowed($lockedOrder, $amount);

            return $lockedOrder->refunds()->create([
                'amount' => $amount,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'] ?? Refund::STATUS_RECORDED,
                'created_by' => $createdBy,
            ]);
        });
    }

    /**
     * Update an existing refund, re-checking the balance while ignoring the
     * refund's own current amount.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws RefundNotAllowedException
     */
    public function update(Refund $refund, array $data): Refund
    {
        return DB::transaction(function () use ($refund, $data) {
            $lockedRefund = Refund::whereKey($refund->getKey())->lockForUpdate()->firstOrFail();

            $orderId = $data['order_id'] ?? $lockedRefund->order_id;

            $lockedOrder = Order::whereKey($orderId)->lockForUpdate()->firstOrFail();

            if (array_key_exists('amount', $data)) {
                $amount = self::normaliseAmount($data['amount']);

                self::assertAmountAllowed(
                    $lockedOrder,
                    $amount,
                    // The refund being edited must not count against itself.
                    $lockedRefund->order_id === $lockedOrder->getKey()
                        ? $lockedRefund->getKey()
                        : null
                );

                $data['amount'] = $amount;
            }

            $lockedRefund->update($data);

            return $lockedRefund;
        });
    }

    /**
     * Server-side balance validation.
     *
     * @throws RefundNotAllowedException
     */
    public static function assertAmountAllowed(
        Order $order,
        float $amount,
        ?int $excludingRefundId = null
    ): void {
        if ($amount <= 0) {
            throw new RefundNotAllowedException(
                'The refund amount must be greater than 0.'
            );
        }

        $remaining = $order->remainingRefundable($excludingRefundId);

        // Half-cent tolerance so a legitimate exact-balance refund is never
        // rejected by decimal representation.
        if ($amount > $remaining + 0.005) {
            throw new RefundNotAllowedException(sprintf(
                'The refund amount cannot exceed the remaining refundable balance of A$%s.',
                number_format($remaining, 2)
            ));
        }
    }

    /**
     * Money is handled to 2 decimal places, matching the orders table.
     */
    public static function normaliseAmount(mixed $amount): float
    {
        return round((float) $amount, 2);
    }
}
