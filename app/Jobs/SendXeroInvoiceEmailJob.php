<?php

namespace App\Jobs;

use App\Mail\XeroInvoiceMail;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Services\Xero\XeroService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the official Xero invoice PDF to the order's billing email address.
 *
 * The PDF is fetched from Xero (never regenerated in Laravel) using the
 * stored xero_invoice_id. Delivery is guarded by invoice_sent_at so job
 * retries and re-dispatches never send duplicate invoices. Pass
 * $forceResend = true for the admin "Resend invoice" action.
 */
class SendXeroInvoiceEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $orderId,
        public bool $forceResend = false
    ) {}

    public function handle(XeroService $xero): void
    {
        $order = Order::with(['student'])->findOrFail($this->orderId);

        /*
        |--------------------------------------------------------------------------
        | Duplicate protection
        |--------------------------------------------------------------------------
        */

        if ($order->invoice_sent_at && ! $this->forceResend) {
            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'email',
                'action' => 'send_invoice_email',
                'status' => 'skipped',
                'request_payload' => json_encode([
                    'order_id' => $order->id,
                    'xero_invoice_number' => $order->xero_invoice_number,
                    'invoice_sent_at' => $order->invoice_sent_at,
                ]),
                'response_payload' => json_encode([
                    'message' => 'Skipped invoice email because it was already sent.',
                ]),
            ]);

            return;
        }

        /*
         * We email the official PDF retrieved from Xero, so an invoice must
         * already exist for this order.
         */
        if (! $order->xero_invoice_id) {
            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'email',
                'action' => 'send_invoice_email',
                'status' => 'failed',
                'request_payload' => json_encode([
                    'order_id' => $order->id,
                ]),
                'error_message' => 'Order has no Xero invoice to send.',
            ]);

            throw new \Exception('Order has no Xero invoice to send.');
        }

        /*
         * Recipient rule: send to the billing email. Only fall back to the
         * student email when billing email is missing.
         */
        $recipient = $order->billing_email ?: $order->student?->email;

        if (! $recipient) {
            $order->update([
                'invoice_email_status' => 'failed',
                'invoice_email_error' => 'No billing or student email address available.',
            ]);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'email',
                'action' => 'send_invoice_email',
                'status' => 'failed',
                'request_payload' => json_encode([
                    'order_id' => $order->id,
                ]),
                'error_message' => 'No billing or student email address available.',
            ]);

            throw new \Exception('No billing or student email address available.');
        }

        try {
            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'email',
                'action' => 'send_invoice_email',
                'status' => 'processing',
                'request_payload' => json_encode([
                    'order_id' => $order->id,
                    'recipient' => $recipient,
                    'xero_invoice_id' => $order->xero_invoice_id,
                    'xero_invoice_number' => $order->xero_invoice_number,
                    'force_resend' => $this->forceResend,
                ]),
            ]);

            /*
             * Fetch the official invoice PDF from Xero. The access token is
             * resolved/refreshed inside the service and never exposed here.
             */
            $pdfContents = $xero->getInvoicePdf($order->xero_invoice_id);

            Mail::to($recipient)
                ->send(new XeroInvoiceMail($order, $pdfContents));

            $order->update([
                'invoice_sent_at' => now(),
                'invoice_email_status' => 'sent',
                'invoice_email_error' => null,
            ]);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'email',
                'action' => 'send_invoice_email',
                'status' => 'success',
                'response_payload' => json_encode([
                    'message' => $this->forceResend
                        ? 'Xero invoice email resent successfully.'
                        : 'Xero invoice email sent successfully.',
                    'order_id' => $order->id,
                    'recipient' => $recipient,
                    'xero_invoice_number' => $order->xero_invoice_number,
                    'force_resend' => $this->forceResend,
                ]),
            ]);
        } catch (Throwable $exception) {
            $order->update([
                'invoice_email_status' => 'failed',
                'invoice_email_error' => $exception->getMessage(),
            ]);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'email',
                'action' => 'send_invoice_email',
                'status' => 'failed',
                'request_payload' => json_encode([
                    'order_id' => $order->id,
                    'recipient' => $recipient,
                    'force_resend' => $this->forceResend,
                ]),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
