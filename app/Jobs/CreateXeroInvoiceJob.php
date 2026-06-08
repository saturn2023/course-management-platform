<?php

namespace App\Jobs;

use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\XeroConnection;
use App\Support\OrderCompletion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
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

        $connection = XeroConnection::where('is_active', true)->first();

        if (! $connection) {
            throw new \Exception('No active Xero connection found.');
        }

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
            | Refresh the Xero token when required
            |--------------------------------------------------------------------------
            */

            if ($connection->expires_at && $connection->expires_at->isPast()) {
                $connection = $this->refreshToken($connection);
            }

            /*
            |--------------------------------------------------------------------------
            | Create the Xero invoice
            |--------------------------------------------------------------------------
            */

            $response = Http::withToken($connection->access_token)
                ->withHeaders([
                    'xero-tenant-id' => $connection->tenant_id,
                    'Accept' => 'application/json',
                ])
                ->post(
                    'https://api.xero.com/api.xro/2.0/Invoices',
                    $payload
                );

            if (! $response->successful()) {
                throw new \Exception(
                    'Xero invoice creation failed: ' . $response->body()
                );
            }

            $responseData = $response->json();
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
                     * A DRAFT invoice remains unpaid in Xero.
                     */
                    'Status' => 'DRAFT',

                    'LineAmountTypes' => 'Inclusive',

                    'LineItems' => $order->items
                        ->map(function ($item) use (
                            $isPurchaseOrder,
                            $order
                        ) {
                            $description = $item->name;

                            if ($isPurchaseOrder) {
                                $description .= PHP_EOL
                                    . 'Purchase Order: '
                                    . $order->purchase_order_number;
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

    private function refreshToken(
        XeroConnection $connection
    ): XeroConnection {
        $response = Http::asForm()
            ->withBasicAuth(
                config('services.xero.client_id'),
                config('services.xero.client_secret')
            )
            ->post('https://identity.xero.com/connect/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $connection->refresh_token,
            ]);

        if (! $response->successful()) {
            throw new \Exception(
                'Failed to refresh Xero token: ' . $response->body()
            );
        }

        $tokenData = $response->json();

        $connection->update([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'expires_at' => now()->addSeconds($tokenData['expires_in']),
        ]);

        return $connection->fresh();
    }
}