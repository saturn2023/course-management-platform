<?php

namespace App\Support;

use App\Models\IntegrationLog;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderCompletion
{
    /**
     * Mark an order as processed only when both downstream
     * processes have fully succeeded. Safe to call from any job,
     * in any order, multiple times.
     */
    public static function attemptMarkProcessed(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            // Lock the row so concurrent jobs can't both transition it.
            $order = Order::whereKey($orderId)->lockForUpdate()->first();

            if (! $order) {
                return;
            }

            // Only progress orders currently in the processing state.
            if ($order->status !== 'processing') {
                return;
            }

            if ($order->xero_status !== 'success') {
                return;
            }

            if ($order->enrolment_status !== 'link_sent') {
                return;
            }

            $order->update(['status' => 'processed']);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'order',
                'action' => 'mark_processed',
                'status' => 'success',
                'response_payload' => json_encode([
                    'message' => 'Order marked as processed because Xero invoice and enrolment links both completed.',
                    'xero_status' => $order->xero_status,
                    'enrolment_status' => $order->enrolment_status,
                ]),
            ]);
        });
    }
}