<?php

namespace App\Jobs;

use App\Models\IntegrationLog;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessOrderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $orderId
    ) {}

    public function handle(): void
    {
        $order = Order::with(['student', 'items'])->findOrFail($this->orderId);

        IntegrationLog::create([
            'order_id' => $order->id,
            'service' => 'order_processor',
            'action' => 'process_order',
            'status' => 'processing',
            'request_payload' => json_encode([
                'order_id' => $order->id,
                'student_id' => $order->student_id,
                'total' => $order->total,
                'status' => $order->status,
            ]),
        ]);

        try {
            // For now we dispatch these two jobs from the parent job.
            // Later, we can make this smarter with job chaining.
            CreateXeroInvoiceJob::dispatch($order->id);
            CreateEnrolmentJob::dispatch($order->id);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'order_processor',
                'action' => 'process_order',
                'status' => 'success',
                'response_payload' => json_encode([
                    'message' => 'Order processing jobs dispatched successfully.',
                    'jobs' => [
                        'CreateXeroInvoiceJob',
                        'CreateEnrolmentJob',
                    ],
                ]),
            ]);
        } catch (Throwable $exception) {
            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'order_processor',
                'action' => 'process_order',
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}