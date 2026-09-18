<?php

namespace App\Jobs;

use App\Mail\XeroInvoiceMail;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Services\Xero\XeroPermanentException;
use App\Services\Xero\XeroService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
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

    /**
     * Wait a minute before the first retry, then five. Covers a brief Xero or
     * SMTP outage without hammering either during it.
     */
    public array $backoff = [60, 300];

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
        |
        | Cheap pre-check. It is not the real guard — two workers can both pass
        | it — but it keeps the common "already sent, dispatched again" case
        | out of the claim below and produces the skipped log entry.
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

        /*
        |--------------------------------------------------------------------------
        | Claim the send
        |--------------------------------------------------------------------------
        |
        | The real duplicate guard. invoice_sent_at is moved forward in a single
        | conditional UPDATE, so of two workers racing on the same order exactly
        | one gets a matching row and the other gets zero and stops. Claiming
        | before sending rather than stamping after means a crash mid-send can
        | leave an unsent order looking sent — invoice_email_status = 'sending'
        | is what makes that visible in the admin — and that is the deliberate
        | trade: never send the customer two invoices.
        |
        | A forced resend claims against the timestamp it read, so two admins
        | double-clicking "Resend invoice" still produce one email.
        */

        $previousSentAt = $order->invoice_sent_at;

        $claimed = Order::whereKey($order->id)
            ->when(
                $previousSentAt === null,
                fn ($query) => $query->whereNull('invoice_sent_at'),
                fn ($query) => $query->where('invoice_sent_at', $previousSentAt)
            )
            ->update([
                'invoice_sent_at' => now(),
                'invoice_email_status' => 'sending',
                'invoice_email_error' => null,
            ]);

        if ($claimed === 0) {
            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'email',
                'action' => 'send_invoice_email',
                'status' => 'skipped',
                'request_payload' => json_encode([
                    'order_id' => $order->id,
                    'force_resend' => $this->forceResend,
                ]),
                'response_payload' => json_encode([
                    'message' => 'Skipped invoice email because another worker already claimed this send.',
                ]),
            ]);

            return;
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

            /*
             * Written through the query builder, not $order->update(): the
             * claim above changed the row without touching this in-memory
             * model, so Eloquent's dirty check no longer reflects the stored
             * values and would silently drop columns that look unchanged.
             */
            Order::whereKey($order->id)->update([
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
        } catch (XeroPermanentException $exception) {
            /*
             * Xero will reject the PDF fetch the same way every time — a dead
             * connection, or an invoice ID it does not recognise. Release the
             * claim so an admin can retry once the cause is fixed, then fail
             * without burning the remaining attempts.
             */
            $this->releaseClaim($order, $previousSentAt, $recipient, $exception);

            $this->fail($exception);
        } catch (Throwable $exception) {
            /*
             * Transient: a Xero 5xx/429, or SMTP being unavailable. Release the
             * claim and rethrow so the worker retries on $backoff and can claim
             * again cleanly.
             */
            $this->releaseClaim($order, $previousSentAt, $recipient, $exception);

            throw $exception;
        }
    }

    /**
     * Put invoice_sent_at back where it was so a retry can re-claim the send,
     * and record why this attempt failed.
     */
    private function releaseClaim(
        Order $order,
        ?Carbon $previousSentAt,
        string $recipient,
        Throwable $exception
    ): void {
        /*
         * Must go through the query builder. $order->invoice_sent_at is still
         * the pre-claim value in memory, so $order->update() would see the
         * restore as "no change" and leave the claim standing in the database
         * — the retry would then skip, and the invoice would never arrive.
         */
        Order::whereKey($order->id)->update([
            'invoice_sent_at' => $previousSentAt,
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
    }
}
