<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\IntegrationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessTestOrderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $orderId
    ) {}

    public function handle(): void
    {
        $order = Order::with(['student', 'items'])->findOrFail($this->orderId);

        IntegrationLog::create([
            'order_id' => $order->id,
            'service' => 'test',
            'action' => 'process_order',
            'status' => 'success',
            'request_payload' => json_encode([
                'order_id' => $order->id,
                'student' => $order->student?->full_name,
                'total' => $order->total,
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total' => $item->total,
                ])->toArray(),
            ]),
            'response_payload' => json_encode([
                'message' => 'Test order processed successfully.',
            ]),
        ]);
    }
}