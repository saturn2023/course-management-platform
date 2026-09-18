<?php

namespace Tests\Feature;

use App\Jobs\SendXeroInvoiceEmailJob;
use App\Mail\XeroInvoiceMail;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Student;
use App\Models\XeroConnection;
use App\Services\Xero\XeroService;
use App\Services\Xero\XeroTransientException;
use Closure;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Delivery of the official Xero invoice PDF.
 *
 * Mail::fake() and Http::fake() mean no customer is ever emailed and Xero is
 * never called; Http::preventStrayRequests() makes an un-faked URL fail the
 * test rather than escape to the real API.
 *
 * The important cases are the ones where the customer could receive two
 * invoices, or where a failure part-way through could leave an order looking
 * delivered when it was not.
 */
class XeroInvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    private const INVOICE_ID = 'xero-invoice-uuid-1';

    private const PDF_URL = 'https://api.xero.com/api.xro/2.0/Invoices/' . self::INVOICE_ID;

    private const PDF_BYTES = '%PDF-1.4 official xero invoice bytes';

    private int $studentSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Mail::fake();

        config([
            'services.xero.client_id' => 'test-client-id',
            'services.xero.client_secret' => 'test-client-secret',
        ]);

        XeroConnection::create([
            'tenant_id' => 'tenant-abc',
            'access_token' => 'access-token-1',
            'refresh_token' => 'refresh-token-1',
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Fixtures
    |--------------------------------------------------------------------------
    */

    private function order(array $overrides = []): Order
    {
        $this->studentSequence++;

        $student = Student::create([
            'first_name' => 'Stu',
            'last_name' => 'Dent',
            'email' => 'stu' . $this->studentSequence . '@example.test',
        ]);

        $order = Order::create(array_merge([
            'student_id' => $student->id,
            'billing_first_name' => 'Bill',
            'billing_last_name' => 'Payer',
            'billing_email' => 'bill@example.test',
            'subtotal' => 100.00,
            'total' => 100.00,
            'status' => 'processing',
            'xero_status' => 'success',
            'xero_invoice_id' => self::INVOICE_ID,
            'xero_invoice_number' => 'INV-0042',
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'name' => 'Working Safely at Heights',
            'quantity' => 1,
            'unit_price' => 100.00,
            'total' => 100.00,
        ]);

        return $order->fresh();
    }

    private function fakePdf(): void
    {
        Http::fake([
            self::PDF_URL => Http::response(self::PDF_BYTES, 200),
        ]);
    }

    private function sendInvoice(Order $order, bool $forceResend = false): void
    {
        (new SendXeroInvoiceEmailJob($order->id, $forceResend))
            ->handle(app(XeroService::class));
    }

    /**
     * Make a competing worker claim the send in the window between this job
     * reading the order and running its own conditional claim.
     *
     * Hooking 'retrieved' is the only way to land a write in that gap from a
     * single-threaded test. The write goes through the query builder so it
     * does not re-enter this listener.
     */
    private function claimFromAnotherWorkerAfterRead(Order $order): void
    {
        $interfered = false;

        Order::retrieved(function (Order $retrieved) use ($order, &$interfered): void {
            if ($interfered || $retrieved->getKey() !== $order->getKey()) {
                return;
            }

            $interfered = true;

            Order::whereKey($order->getKey())->update([
                'invoice_sent_at' => now(),
                'invoice_email_status' => 'sending',
            ]);
        });
    }

    protected function tearDown(): void
    {
        // Model event listeners are static, so a leaked one would interfere
        // with every later test in the process.
        Order::flushEventListeners();

        parent::tearDown();
    }

    /**
     * Pull the raw bytes back out of a mailable's attachment.
     */
    private function attachedBytes(XeroInvoiceMail $mail): string
    {
        return $mail->attachments()[0]->attachWith(
            fn () => null,
            fn (Closure $data) => $data()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | The happy path
    |--------------------------------------------------------------------------
    */

    public function test_the_official_pdf_is_fetched_from_xero_and_attached(): void
    {
        $order = $this->order();

        $this->fakePdf();

        $this->sendInvoice($order);

        // The PDF must come from Xero, requested as a PDF — never regenerated
        // locally, or the customer gets a document Xero has no record of.
        Http::assertSent(fn (Request $request) => $request->url() === self::PDF_URL
            && $request->method() === 'GET'
            && $request->hasHeader('Accept', 'application/pdf')
            && $request->hasHeader('xero-tenant-id', 'tenant-abc'));

        Mail::assertSent(XeroInvoiceMail::class, function (XeroInvoiceMail $mail) use ($order) {
            return $mail->hasTo('bill@example.test')
                && $mail->order->is($order)
                && $mail->pdfContents === self::PDF_BYTES
                && $this->attachedBytes($mail) === self::PDF_BYTES;
        });
    }

    public function test_the_attachment_is_named_after_the_invoice(): void
    {
        $order = $this->order();

        $this->fakePdf();

        $this->sendInvoice($order);

        Mail::assertSent(XeroInvoiceMail::class, function (XeroInvoiceMail $mail) {
            $attachment = $mail->attachments()[0];

            return $attachment->as === 'invoice-INV-0042.pdf'
                && $attachment->mime === 'application/pdf'
                && $mail->envelope()->subject === 'AMS Training Invoice INV-0042';
        });
    }

    public function test_the_send_is_recorded_against_the_order(): void
    {
        $order = $this->order();

        $this->fakePdf();

        $this->sendInvoice($order);

        $order->refresh();

        $this->assertNotNull($order->invoice_sent_at);
        $this->assertSame('sent', $order->invoice_email_status);
        $this->assertNull($order->invoice_email_error);

        $this->assertTrue(IntegrationLog::where('order_id', $order->id)
            ->where('action', 'send_invoice_email')
            ->where('status', 'success')
            ->exists());
    }

    /*
    |--------------------------------------------------------------------------
    | Recipient
    |--------------------------------------------------------------------------
    */

    public function test_it_falls_back_to_the_student_email_when_there_is_no_billing_email(): void
    {
        $order = $this->order(['billing_email' => null]);

        $this->fakePdf();

        $this->sendInvoice($order);

        Mail::assertSent(
            XeroInvoiceMail::class,
            fn (XeroInvoiceMail $mail) => $mail->hasTo($order->student->email)
        );
    }

    public function test_an_order_with_no_usable_address_fails_and_sends_nothing(): void
    {
        $student = Student::create([
            'first_name' => 'No',
            'last_name' => 'Email',
            'email' => '',
        ]);

        $order = $this->order(['billing_email' => null, 'student_id' => $student->id]);

        Http::fake();

        $this->expectException(Exception::class);

        try {
            $this->sendInvoice($order);
        } finally {
            Mail::assertNothingSent();
            Http::assertNothingSent();

            $this->assertNull($order->fresh()->invoice_sent_at);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate protection
    |--------------------------------------------------------------------------
    */

    public function test_running_the_job_twice_sends_only_one_email(): void
    {
        $order = $this->order();

        $this->fakePdf();

        $this->sendInvoice($order);
        $this->sendInvoice($order);

        Mail::assertSentTimes(XeroInvoiceMail::class, 1);

        // The second run must not even re-fetch the PDF.
        Http::assertSentCount(1);

        $this->assertTrue(IntegrationLog::where('order_id', $order->id)
            ->where('action', 'send_invoice_email')
            ->where('status', 'skipped')
            ->exists());
    }

    public function test_an_already_sent_order_is_never_re_sent(): void
    {
        $order = $this->order(['invoice_sent_at' => now()->subDay()]);

        Http::fake();

        $this->sendInvoice($order);

        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_a_concurrent_worker_that_loses_the_claim_stops(): void
    {
        $order = $this->order();

        $this->fakePdf();

        /*
         * The race the claim exists to close. Simply stamping the row before
         * calling the job would only exercise the cheap pre-check, because the
         * job re-reads the order itself. Interfering on 'retrieved' instead
         * lands the competing write in the real gap: after this job has read
         * invoice_sent_at as null, before it reaches the claim.
         */
        $this->claimFromAnotherWorkerAfterRead($order);

        $this->sendInvoice($order);

        Mail::assertNothingSent();

        $this->assertTrue(IntegrationLog::where('order_id', $order->id)
            ->where('action', 'send_invoice_email')
            ->where('status', 'skipped')
            ->exists());
    }

    public function test_a_forced_resend_sends_again(): void
    {
        $order = $this->order(['invoice_sent_at' => now()->subDay()]);

        $this->fakePdf();

        $this->sendInvoice($order, forceResend: true);

        Mail::assertSentTimes(XeroInvoiceMail::class, 1);

        $this->assertSame('sent', $order->fresh()->invoice_email_status);
    }

    public function test_a_forced_resend_that_loses_its_claim_stops(): void
    {
        $order = $this->order(['invoice_sent_at' => now()->subDay()]);

        $this->fakePdf();

        // Two admins click "Resend invoice" together: the other one's claim
        // lands between this job's read and its own claim.
        $this->claimFromAnotherWorkerAfterRead($order);

        $this->sendInvoice($order, forceResend: true);

        Mail::assertNothingSent();
    }

    /*
    |--------------------------------------------------------------------------
    | Failure handling
    |--------------------------------------------------------------------------
    */

    public function test_a_missing_xero_invoice_id_fails_without_sending(): void
    {
        $order = $this->order(['xero_invoice_id' => null, 'xero_invoice_number' => null]);

        Http::fake();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Order has no Xero invoice to send.');

        try {
            $this->sendInvoice($order);
        } finally {
            Mail::assertNothingSent();

            $this->assertNull($order->fresh()->invoice_sent_at);
        }
    }

    public function test_a_mail_failure_releases_the_claim_and_retries(): void
    {
        $order = $this->order();

        $this->fakePdf();

        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new RuntimeException('SMTP server unavailable.'));

        try {
            $this->sendInvoice($order);

            $this->fail('A mail failure must propagate so the worker retries it.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMTP server unavailable.', $exception->getMessage());
        }

        $order->refresh();

        /*
         * The claim must be released, not left stamped: otherwise the retry
         * would see invoice_sent_at set and skip, and the customer would never
         * receive the invoice.
         */
        $this->assertNull($order->invoice_sent_at);
        $this->assertSame('failed', $order->invoice_email_status);
        $this->assertStringContainsString('SMTP server unavailable', $order->invoice_email_error);
    }

    public function test_a_transient_xero_failure_releases_the_claim_and_retries(): void
    {
        $order = $this->order();

        Http::fake([
            self::PDF_URL => Http::response('Service Unavailable', 503),
        ]);

        $this->expectException(XeroTransientException::class);

        try {
            $this->sendInvoice($order);
        } finally {
            $order->refresh();

            Mail::assertNothingSent();

            $this->assertNull($order->invoice_sent_at);
            $this->assertSame('failed', $order->invoice_email_status);
        }
    }

    public function test_a_permanent_xero_failure_is_not_retried(): void
    {
        $order = $this->order();

        Http::fake([
            self::PDF_URL => Http::response('Invoice not found', 404),
        ]);

        // Must not throw: the job fails itself rather than asking the worker
        // to re-request an invoice Xero does not have.
        $this->sendInvoice($order);

        $order->refresh();

        Mail::assertNothingSent();

        $this->assertNull($order->invoice_sent_at);
        $this->assertSame('failed', $order->invoice_email_status);
        $this->assertStringContainsString('404', $order->invoice_email_error);
    }

    public function test_a_previous_send_timestamp_is_restored_on_failure(): void
    {
        $sentAt = now()->subDay()->startOfSecond();
        $order = $this->order(['invoice_sent_at' => $sentAt]);

        Http::fake([
            self::PDF_URL => Http::response('Service Unavailable', 503),
        ]);

        try {
            $this->sendInvoice($order, forceResend: true);
        } catch (XeroTransientException) {
            // expected
        }

        // A failed resend must not erase the record of the original delivery.
        $this->assertTrue($sentAt->equalTo($order->fresh()->invoice_sent_at));
    }

    public function test_the_job_backs_off_before_retrying(): void
    {
        $job = new SendXeroInvoiceEmailJob(1);

        $this->assertSame([60, 300], $job->backoff);
        $this->assertSame(3, $job->tries);
    }
}
