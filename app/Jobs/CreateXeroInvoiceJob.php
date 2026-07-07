<?php

namespace App\Jobs;

use App\Models\IntegrationLog;
use App\Models\Order;
use App\Services\Xero\XeroService;
use App\Support\OrderCompletion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CreateXeroInvoiceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $orderId
    ) {
    }

    public function handle(): void
    {
      $order = Order::with([
    'student',
    'students',
    'items.course',
])->findOrFail($this->orderId);

        /*
        |--------------------------------------------------------------------------
        | Duplicate protection
        |--------------------------------------------------------------------------
        */

        if ($order->xero_invoice_id) {
            $order->update([
                'xero_status' => 'success',
                'xero_error_message' => null,
            ]);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'xero',
                'action' => 'create_invoice',
                'status' => 'skipped',
                'request_payload' => json_encode([
                    'order_id' => $order->id,
                    'existing_xero_invoice_id' => $order->xero_invoice_id,
                    'existing_xero_invoice_number' => $order->xero_invoice_number,
                ]),
                'response_payload' => json_encode([
                    'message' => 'Skipped Xero invoice creation because this order already has a Xero invoice.',
                ]),
            ]);

            /*
             * The invoice already exists, so this may be the final condition
             * required to mark the order as processed.
             */
            OrderCompletion::attemptMarkProcessed($order->id);

            return;
        }

        $xero = app(XeroService::class);

        // Fail fast (before marking the order as processing) if Xero is not
        // connected, preserving the previous behaviour.
        $xero->activeConnection();

        $order->update([
            'xero_status' => 'processing',
            'xero_error_message' => null,
        ]);

        $payload = $this->buildInvoicePayload($order);

        IntegrationLog::create([
            'order_id' => $order->id,
            'service' => 'xero',
            'action' => 'create_invoice',
            'status' => 'processing',
            'request_payload' => json_encode($payload),
        ]);

        try {
            /*
            |--------------------------------------------------------------------------
            | Create the Xero invoice
            |--------------------------------------------------------------------------
            |
            | Token refresh is handled inside XeroService::createInvoice().
            */

            $responseData = $xero->createInvoice($payload);
            $invoice = $responseData['Invoices'][0] ?? null;

            if (! $invoice) {
                throw new \Exception(
                    'Xero response did not contain invoice data.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Save successful Xero result
            |--------------------------------------------------------------------------
            */

            $order->update([
                'xero_status' => 'success',
                'xero_invoice_id' => $invoice['InvoiceID'] ?? null,
                'xero_invoice_number' => $invoice['InvoiceNumber'] ?? null,
                'xero_sent_at' => now(),
                'xero_error_message' => null,
            ]);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'xero',
                'action' => 'create_invoice',
                'status' => 'success',
                'request_payload' => json_encode($payload),
                'response_payload' => json_encode($responseData),
            ]);

            /*
             * Automatic billing-email delivery is gated behind a config flag
             * and is intentionally OFF during the DRAFT testing phase — we must
             * never email a draft invoice to a customer. The invoice ID/number
             * are still saved above so admins can review it in Xero, and the
             * order is NOT marked as sent.
             *
             * In production (XERO_AUTO_EMAIL_INVOICE=true, alongside
             * XERO_INVOICE_STATUS=AUTHORISED) the official PDF is emailed
             * automatically. The job is idempotent via invoice_sent_at.
             */
            if (config('services.xero.auto_email_invoice', false)) {
                SendXeroInvoiceEmailJob::dispatch($order->id);
            }

            /*
             * Xero has succeeded. If enrolment links have also been sent,
             * this changes the overall order status to processed.
             */
            OrderCompletion::attemptMarkProcessed($order->id);
        } catch (Throwable $exception) {
            /*
            |--------------------------------------------------------------------------
            | Save Xero failure
            |--------------------------------------------------------------------------
            */

            $order->update([
                'xero_status' => 'failed',
                'xero_error_message' => $exception->getMessage(),
            ]);

            IntegrationLog::create([
                'order_id' => $order->id,
                'service' => 'xero',
                'action' => 'create_invoice',
                'status' => 'failed',
                'request_payload' => json_encode($payload),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function buildInvoicePayload(Order $order): array
    {
        $isPurchaseOrder =
            $order->payment_method === 'purchase_order'
            && filled($order->purchase_order_number);

        $contactName = trim(
            ($order->billing_first_name ?? '')
            . ' '
            . ($order->billing_last_name ?? '')
        );

        if (filled($order->billing_company)) {
            $contactName = $order->billing_company;
        }

        if ($contactName === '') {
            $contactName = $order->student?->full_name
                ?? 'AMS Training Customer';
        }

        $reference = $isPurchaseOrder
            ? $order->purchase_order_number
            : 'Laravel Order #' . $order->id;
        $studentDetails = $order->students
    ->values()
    ->map(function ($student, int $index): string {
        $name = trim(
            ($student->first_name ?? '')
            . ' '
            . ($student->last_name ?? '')
        );

        $details = 'Student ' . ($index + 1) . ': ' . ($name ?: 'Not provided');

        if (filled($student->email)) {
            $details .= ' | Email: ' . $student->email;
        }

        if (filled($student->phone)) {
            $details .= ' | Phone: ' . $student->phone;
        }

        return $details;
    })
    ->implode(PHP_EOL); 
        return [
            'Invoices' => [
                [
                    'Type' => 'ACCREC',

                    'Contact' => [
                        'Name' => $contactName,
                        'EmailAddress' => $order->billing_email
                            ?: $order->student?->email,
                    ],

                    'Date' => now()->toDateString(),
                    'DueDate' => now()->addDays(7)->toDateString(),
                    'Reference' => $reference,

                    /*
                     * Invoice status is config-driven (see config/services.php
                     * services.xero.invoice_status).
                     *
                     * Testing phase: DRAFT — the invoice stays unfinalised and
                     * unsent in Xero; it does NOT post to accounts receivable
                     * and is not a valid tax invoice for the customer.
                     *
                     * Production: set XERO_INVOICE_STATUS=AUTHORISED to finalise
                     * it as the official tax invoice (and enable auto-email).
                     */
                    'Status' => config('services.xero.invoice_status', 'DRAFT'),

                    'LineAmountTypes' => 'Inclusive',

                     'LineItems' => $order->items
    ->map(function ($item) use (
        $isPurchaseOrder,
        $order,
        $studentDetails
    ) {
        $description = $item->name;

        if ($isPurchaseOrder) {
            $description .= PHP_EOL
                . 'Purchase Order: '
                . $order->purchase_order_number;
        }

        if ($studentDetails !== '') {
            $description .= PHP_EOL
                . PHP_EOL
                . 'Student details:'
                . PHP_EOL
                . $studentDetails;
        }

        return [
            'Description' => $description,
            'Quantity' => $item->quantity,
            'UnitAmount' => (float) $item->unit_price,
            'AccountCode' => '200',
            'TaxType' => 'OUTPUT',
        ];
    })
    ->values()
    ->toArray(),
                ],
            ],
        ];
    }
}