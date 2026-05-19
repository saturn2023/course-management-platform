<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\IntegrationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class CreateXeroInvoiceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $orderId
    ) {}

    public function handle(): void
    {
        $order = Order::with(['student', 'items'])->findOrFail($this->orderId);

        $order->update([
            'xero_status' => 'processing',
            'xero_error_message' => null,
        ]);

        IntegrationLog::create([
            'order_id' => $order->id,
            'service' => 'xero',
            'action' => 'create_invoice',
            'status' => 'processing',
            'request_payload' => json_encode($this->buildFakeXeroPayload($order)),
        ]);

        try {
            // Fake Xero API response for now.
            // Later we will replace this with the real Xero API call.
            $fakeInvoiceId = 'XERO-' . Str::upper(Str::random(10));
            $fakeInvoiceNumber = 'INV-' . str_pad((string) $order->id, 5, '0', STR_PAD_LEFT);

            $order->update([
                'xero_status' => 'success',
                'xero_invoice_id' => $fakeInvoiceId,
                'xero_invoice_number' => $fakeInvoiceNumber,
                'xero_error_message' => null,
            ]);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'xero',
                'action' => 'create_invoice',
                'status' => 'success',
                'request_payload' => json_encode($this->buildFakeXeroPayload($order)),
                'response_payload' => json_encode([
                    'invoice_id' => $fakeInvoiceId,
                    'invoice_number' => $fakeInvoiceNumber,
                    'message' => 'Fake Xero invoice created successfully.',
                ]),
            ]);
        } catch (Throwable $exception) {
            $order->update([
                'xero_status' => 'failed',
                'xero_error_message' => $exception->getMessage(),
            ]);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'xero',
                'action' => 'create_invoice',
                'status' => 'failed',
                'request_payload' => json_encode($this->buildFakeXeroPayload($order)),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function buildFakeXeroPayload(Order $order): array
    {
        return [
            'type' => 'ACCREC',
            'contact' => [
                'name' => $order->student?->full_name ?? 'Unknown Student',
                'email' => $order->student?->email,
            ],
            'line_items' => $order->items->map(fn ($item) => [
                'description' => $item->name,
                'quantity' => $item->quantity,
                'unit_amount' => (float) $item->unit_price,
                'line_amount' => (float) $item->total,
            ])->toArray(),
            'total' => (float) $order->total,
            'status' => 'AUTHORISED',
        ];
    }
}