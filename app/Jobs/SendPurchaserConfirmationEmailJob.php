<?php

namespace App\Jobs;

use App\Mail\PurchaserConfirmationMail;
use App\Models\IntegrationLog;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPurchaserConfirmationEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $orderId,
        public bool $forceResend = false
    ) {}

    public function handle(): void
    {
        $order = Order::with(['students', 'items.course'])->findOrFail($this->orderId);

        if ($order->purchaser_confirmation_sent_at && ! $this->forceResend) {
            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'email',
                'action' => 'send_purchaser_confirmation',
                'status' => 'skipped',
                'request_payload' => json_encode([
                    'order_id' => $order->id,
                    'billing_email' => $order->billing_email,
                    'purchaser_confirmation_sent_at' => $order->purchaser_confirmation_sent_at,
                ]),
                'response_payload' => json_encode([
                    'message' => 'Skipped purchaser confirmation because it was already sent.',
                ]),
            ]);

            return;
        }

        if (! $order->billing_email) {
            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'email',
                'action' => 'send_purchaser_confirmation',
                'status' => 'failed',
                'request_payload' => json_encode([
                    'order_id' => $order->id,
                ]),
                'error_message' => 'Billing email address is missing.',
            ]);

            throw new \Exception('Billing email address is missing.');
        }

        try {
            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'email',
                'action' => 'send_purchaser_confirmation',
                'status' => 'processing',
                'request_payload' => json_encode([
                    'order_id' => $order->id,
                    'billing_email' => $order->billing_email,
                    'student_count' => $order->students->count(),
                    'xero_invoice_number' => $order->xero_invoice_number,
                    'force_resend' => $this->forceResend,
                ]),
            ]);

            Mail::to($order->billing_email)
                ->send(new PurchaserConfirmationMail($order));

            $order->update([
                'purchaser_confirmation_sent_at' => now(),
            ]);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'email',
                'action' => 'send_purchaser_confirmation',
                'status' => 'success',
                'response_payload' => json_encode([
                    'message' => $this->forceResend
                        ? 'Purchaser confirmation email resent successfully.'
                        : 'Purchaser confirmation email sent successfully.',
                    'order_id' => $order->id,
                    'billing_email' => $order->billing_email,
                    'student_count' => $order->students->count(),
                    'force_resend' => $this->forceResend,
                ]),
            ]);
        } catch (Throwable $exception) {
            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'email',
                'action' => 'send_purchaser_confirmation',
                'status' => 'failed',
                'request_payload' => json_encode([
                    'order_id' => $order->id,
                    'billing_email' => $order->billing_email,
                    'force_resend' => $this->forceResend,
                ]),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}