<?php

namespace Tests\Feature;

use App\Jobs\CreateXeroInvoiceJob;
use App\Jobs\SendXeroInvoiceEmailJob;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Student;
use App\Models\XeroConnection;
use App\Services\Xero\XeroTransientException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Invoice creation against a faked Xero. Nothing here reaches the real API:
 * every test installs Http::fake() first, and Http::preventStrayRequests()
 * turns any URL we forgot to fake into a failure rather than a live call.
 *
 * The behaviour under test is mostly about what happens when Xero misbehaves
 * — a duplicate dispatch, a validation rejection, an expired token — because
 * that is where a second real invoice could be created.
 */
class XeroInvoiceCreationTest extends TestCase
{
    use RefreshDatabase;

    private const INVOICES_URL = 'https://api.xero.com/api.xro/2.0/Invoices';

    private const TOKEN_URL = 'https://identity.xero.com/connect/token';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'services.xero.client_id' => 'test-client-id',
            'services.xero.client_secret' => 'test-client-secret',
            'services.xero.invoice_status' => 'DRAFT',
            'services.xero.auto_email_invoice' => false,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Fixtures
    |--------------------------------------------------------------------------
    */

    private function connection(array $overrides = []): XeroConnection
    {
        return XeroConnection::create(array_merge([
            'tenant_id' => 'tenant-abc',
            'tenant_name' => 'AMS Training',
            'access_token' => 'access-token-1',
            'refresh_token' => 'refresh-token-1',
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ], $overrides));
    }

    private int $studentSequence = 0;

    private function order(array $overrides = []): Order
    {
        // students.email is uniquely indexed, so tests that build more than
        // one order need a distinct student each time.
        $this->studentSequence++;

        $student = Student::create([
            'first_name' => 'Stu',
            'last_name' => 'Dent',
            'email' => 'stu' . $this->studentSequence . '@example.test',
            'phone' => '0400000000',
        ]);

        $order = Order::create(array_merge([
            'student_id' => $student->id,
            'billing_first_name' => 'Bill',
            'billing_last_name' => 'Payer',
            'billing_email' => 'bill@example.test',
            'payment_method' => 'card',
            'subtotal' => 100.00,
            'total' => 100.00,
            'status' => 'processing',
        ], $overrides));

        $order->students()->attach($student->id);

        OrderItem::create([
            'order_id' => $order->id,
            'name' => 'Working Safely at Heights',
            'quantity' => 1,
            'unit_price' => 100.00,
            'total' => 100.00,
        ]);

        return $order->fresh();
    }

    /**
     * The shape Xero returns from POST /Invoices.
     */
    private function invoiceCreated(string $id = 'xero-invoice-uuid-1', string $number = 'INV-0042'): array
    {
        return [
            'Invoices' => [[
                'InvoiceID' => $id,
                'InvoiceNumber' => $number,
                'Status' => 'DRAFT',
                'Total' => 100.00,
            ]],
        ];
    }

    private function fakeSuccessfulCreate(): void
    {
        Http::fake([
            self::INVOICES_URL => Http::response($this->invoiceCreated(), 200),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | The happy path
    |--------------------------------------------------------------------------
    */

    public function test_the_xero_invoice_is_stored_against_the_correct_order(): void
    {
        $this->connection();
        $other = $this->order(['billing_email' => 'other@example.test']);
        $order = $this->order();

        $this->fakeSuccessfulCreate();

        (new CreateXeroInvoiceJob($order->id))->handle();

        $order->refresh();

        $this->assertSame('xero-invoice-uuid-1', $order->xero_invoice_id);
        $this->assertSame('INV-0042', $order->xero_invoice_number);
        $this->assertSame('success', $order->xero_status);
        $this->assertNotNull($order->xero_sent_at);
        $this->assertNull($order->xero_error_message);

        // The other order must not have been touched.
        $this->assertNull($other->fresh()->xero_invoice_id);
    }

    public function test_the_request_is_authenticated_against_the_active_tenant(): void
    {
        $this->connection();
        $order = $this->order();

        $this->fakeSuccessfulCreate();

        (new CreateXeroInvoiceJob($order->id))->handle();

        Http::assertSent(fn (Request $request) => $request->url() === self::INVOICES_URL
            && $request->hasHeader('Authorization', 'Bearer access-token-1')
            && $request->hasHeader('xero-tenant-id', 'tenant-abc'));
    }

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    */

    public function test_the_create_request_carries_a_deterministic_idempotency_key(): void
    {
        $this->connection();
        $order = $this->order();

        $this->fakeSuccessfulCreate();

        (new CreateXeroInvoiceJob($order->id))->handle();

        $expected = 'ams-order-' . $order->id . '-invoice';

        // Same key for the same order, whichever job instance builds it —
        // that is what makes a retry replay rather than duplicate.
        $this->assertSame($expected, (new CreateXeroInvoiceJob($order->id))->idempotencyKey());

        Http::assertSent(fn (Request $request) => $request->hasHeader('Idempotency-Key', $expected));
    }

    public function test_running_the_job_twice_does_not_create_a_second_invoice(): void
    {
        $this->connection();
        $order = $this->order();

        $this->fakeSuccessfulCreate();

        (new CreateXeroInvoiceJob($order->id))->handle();
        (new CreateXeroInvoiceJob($order->id))->handle();

        // The second run short-circuits on the stored invoice ID, so Xero is
        // never asked a second time.
        Http::assertSentCount(1);

        $this->assertSame(1, IntegrationLog::where('order_id', $order->id)
            ->where('action', 'create_invoice')
            ->where('status', 'skipped')
            ->count());

        $this->assertSame('success', $order->fresh()->xero_status);
    }

    /*
    |--------------------------------------------------------------------------
    | Failure classification
    |--------------------------------------------------------------------------
    */

    public function test_a_validation_error_is_recorded_and_not_retried(): void
    {
        $this->connection();
        $order = $this->order();

        Http::fake([
            self::INVOICES_URL => Http::response([
                'Message' => 'Account code 200 is not a valid code for this organisation.',
            ], 400),
        ]);

        // A permanent failure must not propagate: the job fails itself so the
        // worker does not schedule another attempt at the same bad request.
        (new CreateXeroInvoiceJob($order->id))->handle();

        $order->refresh();

        $this->assertSame('failed', $order->xero_status);
        $this->assertStringContainsString('not a valid code', $order->xero_error_message);
        $this->assertNull($order->xero_invoice_id);

        Http::assertSentCount(1);

        $this->assertTrue(IntegrationLog::where('order_id', $order->id)
            ->where('action', 'create_invoice')
            ->where('status', 'failed')
            ->exists());
    }

    public function test_a_server_error_is_rethrown_so_the_worker_retries(): void
    {
        $this->connection();
        $order = $this->order();

        Http::fake([
            self::INVOICES_URL => Http::response('Service Unavailable', 503),
        ]);

        $this->expectException(XeroTransientException::class);

        try {
            (new CreateXeroInvoiceJob($order->id))->handle();
        } finally {
            $order->refresh();

            $this->assertSame('failed', $order->xero_status);
            $this->assertNull($order->xero_invoice_id);
        }
    }

    public function test_a_rate_limit_is_treated_as_retryable(): void
    {
        $this->connection();
        $order = $this->order();

        Http::fake([
            self::INVOICES_URL => Http::response('Rate limit exceeded', 429),
        ]);

        $this->expectException(XeroTransientException::class);

        (new CreateXeroInvoiceJob($order->id))->handle();
    }

    public function test_the_job_backs_off_before_retrying(): void
    {
        // A Xero outage retried immediately just spends the remaining
        // attempts inside the same outage.
        $this->assertSame([60, 300], (new CreateXeroInvoiceJob(1))->backoff);
        $this->assertSame(3, (new CreateXeroInvoiceJob(1))->tries);
    }

    public function test_a_missing_xero_connection_fails_without_retrying(): void
    {
        // No XeroConnection row at all.
        $order = $this->order();

        Http::fake();

        (new CreateXeroInvoiceJob($order->id))->handle();

        $order->refresh();

        $this->assertSame('failed', $order->xero_status);
        $this->assertStringContainsString('No active Xero connection', $order->xero_error_message);

        Http::assertNothingSent();
    }

    /*
    |--------------------------------------------------------------------------
    | Token handling
    |--------------------------------------------------------------------------
    */

    public function test_an_expired_token_is_refreshed_before_the_request(): void
    {
        $connection = $this->connection(['expires_at' => now()->subMinute()]);
        $order = $this->order();

        Http::fake([
            self::TOKEN_URL => Http::response([
                'access_token' => 'access-token-2',
                'refresh_token' => 'refresh-token-2',
                'expires_in' => 1800,
            ], 200),
            self::INVOICES_URL => Http::response($this->invoiceCreated(), 200),
        ]);

        (new CreateXeroInvoiceJob($order->id))->handle();

        $connection->refresh();

        // The rotated pair must be persisted, or the next refresh fails.
        $this->assertSame('access-token-2', $connection->access_token);
        $this->assertSame('refresh-token-2', $connection->refresh_token);
        $this->assertTrue($connection->expires_at->isFuture());

        Http::assertSent(fn (Request $request) => $request->url() === self::INVOICES_URL
            && $request->hasHeader('Authorization', 'Bearer access-token-2'));

        $this->assertSame('success', $order->fresh()->xero_status);
    }

    public function test_a_401_refreshes_the_token_once_and_replays_the_request(): void
    {
        // Token looks valid to us but Xero has already invalidated it.
        $connection = $this->connection(['expires_at' => now()->addHour()]);
        $order = $this->order();

        Http::fake([
            self::TOKEN_URL => Http::response([
                'access_token' => 'access-token-2',
                'refresh_token' => 'refresh-token-2',
                'expires_in' => 1800,
            ], 200),
            self::INVOICES_URL => Http::sequence()
                ->push('Unauthorized', 401)
                ->push($this->invoiceCreated(), 200),
        ]);

        (new CreateXeroInvoiceJob($order->id))->handle();

        $order->refresh();

        $this->assertSame('success', $order->xero_status);
        $this->assertSame('xero-invoice-uuid-1', $order->xero_invoice_id);
        $this->assertSame('access-token-2', $connection->fresh()->access_token);

        // Rejected call, token refresh, replayed call.
        Http::assertSentCount(3);
    }

    public function test_a_401_that_survives_the_refresh_is_permanent(): void
    {
        $this->connection(['expires_at' => now()->addHour()]);
        $order = $this->order();

        Http::fake([
            self::TOKEN_URL => Http::response([
                'access_token' => 'access-token-2',
                'refresh_token' => 'refresh-token-2',
                'expires_in' => 1800,
            ], 200),
            self::INVOICES_URL => Http::response('Unauthorized', 401),
        ]);

        // Refreshing did not help, so this needs an admin to reconnect Xero,
        // not two more automatic attempts.
        (new CreateXeroInvoiceJob($order->id))->handle();

        $order->refresh();

        $this->assertSame('failed', $order->xero_status);
        $this->assertNull($order->xero_invoice_id);

        // The 401 is retried exactly once, never in a loop.
        Http::assertSentCount(3);
    }

    public function test_a_dead_refresh_token_fails_without_retrying(): void
    {
        $this->connection(['expires_at' => now()->subMinute()]);
        $order = $this->order();

        Http::fake([
            self::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        // invalid_grant means the refresh token is spent or has passed its
        // 60-day idle expiry. Only reconnecting fixes it.
        (new CreateXeroInvoiceJob($order->id))->handle();

        $order->refresh();

        $this->assertSame('failed', $order->xero_status);
        $this->assertStringContainsString('invalid_grant', $order->xero_error_message);
    }

    /*
    |--------------------------------------------------------------------------
    | The email flag stays off
    |--------------------------------------------------------------------------
    */

    public function test_no_invoice_email_is_queued_while_the_flag_is_off(): void
    {
        Queue::fake();

        $this->connection();
        $order = $this->order();

        $this->fakeSuccessfulCreate();

        (new CreateXeroInvoiceJob($order->id))->handle();

        // Invoices are still created as DRAFT; a draft must never be emailed.
        Queue::assertNotPushed(SendXeroInvoiceEmailJob::class);
    }

    public function test_the_invoice_email_is_queued_once_the_flag_is_on(): void
    {
        Queue::fake();

        config(['services.xero.auto_email_invoice' => true]);

        $this->connection();
        $order = $this->order();

        $this->fakeSuccessfulCreate();

        (new CreateXeroInvoiceJob($order->id))->handle();

        Queue::assertPushed(
            SendXeroInvoiceEmailJob::class,
            fn (SendXeroInvoiceEmailJob $job) => $job->orderId === $order->id && $job->forceResend === false
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Reading an invoice back
    |--------------------------------------------------------------------------
    |
    | getInvoice() is what the card-payment reconciliation work will use to ask
    | Xero what it actually holds for an order before recording a payment
    | against it.
    */

    public function test_an_invoice_can_be_read_back_from_xero(): void
    {
        $this->connection();

        Http::fake([
            self::INVOICES_URL . '/xero-invoice-uuid-1' => Http::response([
                'Invoices' => [[
                    'InvoiceID' => 'xero-invoice-uuid-1',
                    'InvoiceNumber' => 'INV-0042',
                    'Status' => 'AUTHORISED',
                    'AmountDue' => 100.00,
                ]],
            ], 200),
        ]);

        $invoice = app(\App\Services\Xero\XeroService::class)->getInvoice('xero-invoice-uuid-1');

        // The invoice element itself, not the wrapping envelope.
        $this->assertSame('INV-0042', $invoice['InvoiceNumber']);
        $this->assertSame('AUTHORISED', $invoice['Status']);
        $this->assertEquals(100.00, $invoice['AmountDue']);
    }

    public function test_reading_an_unknown_invoice_is_a_permanent_failure(): void
    {
        $this->connection();

        Http::fake([
            self::INVOICES_URL . '/nope' => Http::response('Invoice not found', 404),
        ]);

        $this->expectException(\App\Services\Xero\XeroPermanentException::class);

        app(\App\Services\Xero\XeroService::class)->getInvoice('nope');
    }

    public function test_the_invoice_is_created_as_draft_while_testing(): void
    {
        $this->connection();
        $order = $this->order();

        $this->fakeSuccessfulCreate();

        (new CreateXeroInvoiceJob($order->id))->handle();

        Http::assertSent(fn (Request $request) => ($request->data()['Invoices'][0]['Status'] ?? null) === 'DRAFT');
    }
}
