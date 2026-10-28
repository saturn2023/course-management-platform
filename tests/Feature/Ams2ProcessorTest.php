<?php

namespace Tests\Feature;

use App\Enrolment\V2\Ams2Claim;
use App\Enrolment\V2\Ams2Processor;
use App\Enrolment\V2\Ams2Status;
use App\Models\Ams2Enrolment;
use App\Models\Ams2EnrolmentDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use App\Models\EnrolmentEmailTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Enrolment v2 machine, stage 1.
 *
 * Statuses, transitions and timeouts are taken from reference/wp-ams2.php.
 * Steps are stubbed, so these cover the machine itself: the lock, the atomic
 * claim, the full pass, and what happens to a step that never finished.
 */
class Ams2ProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * These cover the machine itself, so the kill switch is on. That it
         * defaults OFF, and that a disabled processor is a complete no-op, is
         * covered in Ams2EnrolStepTest.
         */
        config()->set('services.ams2.enabled', true);

        Mail::fake();

        // The confirmation step needs a published template for this pair.
        EnrolmentEmailTemplate::create([
            'name' => 'Confirmation',
            'course_id' => 32,
            'plan_id' => 38,
            'is_published' => true,
            'email_body' => '<p>Hello {first_name} {last_name}.</p>',
        ]);
        config()->set('services.enrolment_api.public_key', 'pk_test');
        config()->set('services.enrolment_api.origin', 'https://ams.test');
        config()->set('services.ams2.enrolment_floor', 500000);

        /*
         * The enrol step is real from stage 2 on, so the machine tests fake a
         * successful RTO Data response. Nothing here may touch the network.
         */
        Http::fake(['*' => Http::response([
            'success' => true,
            'enrolment_id' => 600001,
            'enrolment_data' => ['learner_id' => 90210, 'course_title' => 'Work Safely At Heights'],
        ], 200)]);
    }

    private function row(array $attributes = []): Ams2Enrolment
    {
        return Ams2Enrolment::create(array_merge([
            'secret_key' => 'key-'.uniqid(),
            'order_id' => 1,
            'course_id' => 32,
            'plan_id' => 38,
            'course_code' => 'RIIWHS204E',
            'course_title' => 'Work Safely At Heights',
            'student_data' => [
                'first_name' => 'Test',
                'last_name' => 'Student',
                // Required by the real enrol step from stage 2 on.
                'date_of_birth' => '1990-01-01',
                'mobile_phone' => '0400000000',
            ],
            'real_email' => 'learner@example.test',
            'status' => Ams2Status::RECEIVED,
        ], $attributes));
    }

    private function processor(): Ams2Processor
    {
        return app(Ams2Processor::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Global lock
    |--------------------------------------------------------------------------
    */

    public function test_a_second_run_exits_while_the_first_holds_the_lock(): void
    {
        $held = Cache::lock(Ams2Processor::LOCK_KEY, Ams2Processor::LOCK_SECONDS);
        $this->assertTrue($held->get());

        try {
            $this->assertFalse($this->processor()->run());
        } finally {
            $held->release();
        }
    }

    public function test_a_run_releases_the_lock_so_the_next_one_proceeds(): void
    {
        $this->assertTrue($this->processor()->run());
        $this->assertTrue($this->processor()->run());
    }

    public function test_the_locked_out_run_leaves_rows_untouched(): void
    {
        $row = $this->row();

        $held = Cache::lock(Ams2Processor::LOCK_KEY, Ams2Processor::LOCK_SECONDS);
        $held->get();

        try {
            $this->processor()->run();
        } finally {
            $held->release();
        }

        $this->assertSame(Ams2Status::RECEIVED, $row->fresh()->status);
    }

    public function test_the_command_reports_a_blocked_run_without_failing(): void
    {
        $held = Cache::lock(Ams2Processor::LOCK_KEY, Ams2Processor::LOCK_SECONDS);
        $held->get();

        try {
            $this->artisan('ams2:process')
                ->expectsOutputToContain('already in progress')
                ->assertSuccessful();
        } finally {
            $held->release();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Atomic claim
    |--------------------------------------------------------------------------
    */

    public function test_only_one_of_two_claims_on_the_same_row_wins(): void
    {
        $row = $this->row();
        $claim = app(Ams2Claim::class);

        $first = $claim->claim($row->id, Ams2Status::RECEIVED, Ams2Status::ENROLLING);
        $second = $claim->claim($row->id, Ams2Status::RECEIVED, Ams2Status::ENROLLING);

        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertSame(Ams2Status::ENROLLING, $row->fresh()->status);
    }

    public function test_a_claim_from_the_wrong_status_does_nothing(): void
    {
        $row = $this->row(['status' => Ams2Status::ENROLLED]);
        $claim = app(Ams2Claim::class);

        $this->assertFalse($claim->claim($row->id, Ams2Status::RECEIVED, Ams2Status::ENROLLING));
        $this->assertSame(Ams2Status::ENROLLED, $row->fresh()->status);
    }

    public function test_a_claim_touches_only_its_own_row(): void
    {
        $target = $this->row();
        $other = $this->row();

        app(Ams2Claim::class)->claim($target->id, Ams2Status::RECEIVED, Ams2Status::ENROLLING);

        $this->assertSame(Ams2Status::RECEIVED, $other->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Full pass
    |--------------------------------------------------------------------------
    */

    public function test_one_run_carries_a_row_from_received_to_complete(): void
    {
        // Nothing to upload and uploading is finished, so one run carries the
        // row the whole way: enrol, documents, email update, confirmation.
        $row = $this->row(['docs_final' => true]);

        $this->processor()->run();

        $this->assertSame(Ams2Status::COMPLETE, $row->fresh()->status);
        $this->assertNotNull($row->fresh()->email_sent_at);
    }




    public function test_a_row_still_waiting_on_uploads_is_left_at_enrolled(): void
    {
        // docs_final is false and the row is new, so the documents step holds
        // off rather than sending an incomplete set.
        $row = $this->row(['status' => Ams2Status::ENROLLED, 'docs_final' => false]);

        $this->processor()->run();

        $this->assertSame(Ams2Status::ENROLLED, $row->fresh()->status);
    }

    public function test_a_completed_row_is_left_alone(): void
    {
        $row = $this->row(['status' => Ams2Status::COMPLETE]);
        $before = $row->updated_at;

        $this->processor()->run();

        $this->assertSame(Ams2Status::COMPLETE, $row->fresh()->status);
        $this->assertEquals($before, $row->fresh()->updated_at);
    }

    public function test_a_needs_review_row_is_not_picked_back_up(): void
    {
        $row = $this->row(['status' => Ams2Status::NEEDS_REVIEW, 'resume_status' => Ams2Status::RECEIVED]);

        $this->processor()->run();

        $this->assertSame(Ams2Status::NEEDS_REVIEW, $row->fresh()->status);
    }

    public function test_the_batch_is_capped(): void
    {
        for ($i = 0; $i < Ams2Processor::BATCH_LIMIT + 3; $i++) {
            $this->row(['docs_final' => true]);
        }

        $this->processor()->run();

        $this->assertSame(
            Ams2Processor::BATCH_LIMIT,
            Ams2Enrolment::where('status', Ams2Status::COMPLETE)->count(),
        );
        $this->assertSame(3, Ams2Enrolment::where('status', Ams2Status::RECEIVED)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | needs_review after 30 minutes
    |--------------------------------------------------------------------------
    */

    public static function inFlightProvider(): array
    {
        return [
            'enrolling' => [Ams2Status::ENROLLING, Ams2Status::RECEIVED],
            'sending_documents' => [Ams2Status::SENDING_DOCUMENTS, Ams2Status::ENROLLED],
            'updating' => [Ams2Status::UPDATING, Ams2Status::DOCUMENTS_DONE],
            'emailing' => [Ams2Status::EMAILING, Ams2Status::UPDATED],
        ];
    }

    #[DataProvider('inFlightProvider')]
    public function test_a_step_stuck_for_thirty_minutes_is_parked_for_review(
        string $inFlight,
        string $expectedResume,
    ): void {
        $row = $this->row(['status' => $inFlight]);
        $row->forceFill(['updated_at' => now()->subMinutes(31)])->saveQuietly();

        $this->processor()->run();

        $fresh = $row->fresh();

        $this->assertSame(Ams2Status::NEEDS_REVIEW, $fresh->status);
        // Resume has to know which step to start again from.
        $this->assertSame($expectedResume, $fresh->resume_status);
        $this->assertStringContainsString($inFlight, (string) $fresh->error_message);
    }

    public function test_a_step_in_flight_for_under_thirty_minutes_is_left_running(): void
    {
        $row = $this->row(['status' => Ams2Status::ENROLLING]);
        $row->forceFill(['updated_at' => now()->subMinutes(29)])->saveQuietly();

        $this->processor()->run();

        $this->assertSame(Ams2Status::ENROLLING, $row->fresh()->status);
    }

    public function test_a_timed_out_step_is_never_retried(): void
    {
        $row = $this->row(['status' => Ams2Status::ENROLLING]);
        $row->forceFill(['updated_at' => now()->subMinutes(31)])->saveQuietly();

        $this->processor()->run();

        /*
         * The enrol may already have reached RTO Data. Parking on the first
         * timeout rather than burning attempts is what stops a double enrolment.
         */
        $this->assertSame(Ams2Status::NEEDS_REVIEW, $row->fresh()->status);
        $this->assertSame(1, $row->fresh()->attempts);
    }

    public function test_parking_a_row_raises_an_alert_carrying_the_real_email(): void
    {
        Log::spy();

        $row = $this->row(['status' => Ams2Status::ENROLLING]);
        $row->forceFill(['updated_at' => now()->subMinutes(31)])->saveQuietly();

        $this->processor()->run();

        /*
         * The alert must quote the real address: RTO Data may still be holding
         * the placeholder for this learner, so whoever repairs it needs to know
         * what to restore.
         */
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'needs checking')
                && ($context['real_email'] ?? null) === 'learner@example.test'
                && ($context['reference'] ?? null) === $row->displayRef())
            ->once();
    }

    public function test_a_parked_row_is_not_advanced_in_the_same_run(): void
    {
        $row = $this->row(['status' => Ams2Status::ENROLLING]);
        $row->forceFill(['updated_at' => now()->subMinutes(31)])->saveQuietly();

        $this->processor()->run();

        // The stuck sweep runs before the batch, so it must not be re-advanced.
        $this->assertSame(Ams2Status::NEEDS_REVIEW, $row->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Documents and the verify path
    |--------------------------------------------------------------------------
    */

    public function test_a_stalled_document_send_is_returned_to_retry(): void
    {
        $row = $this->row();

        $document = Ams2EnrolmentDocument::create([
            'enrolment_row' => $row->id,
            'doc_type' => 'id',
            'file_path' => 'docs/abc123.pdf',
            'original_name' => 'Drivers Licence.pdf',
            'status' => Ams2EnrolmentDocument::STATUS_SENDING,
        ]);
        $document->forceFill(['updated_at' => now()->subMinutes(31)])->saveQuietly();

        $this->processor()->run();

        // Resending a document is safe, unlike repeating an enrol.
        $this->assertSame(Ams2EnrolmentDocument::STATUS_RETRY, $document->fresh()->status);
    }

    public function test_a_stalled_verify_check_is_released_back_to_verifying(): void
    {
        $row = $this->row(['status' => Ams2Status::CHECKING]);
        $row->forceFill(['updated_at' => now()->subMinutes(11)])->saveQuietly();

        $this->processor()->run();

        // A check only reads, so repeating it is safe after 10 minutes.
        $this->assertSame(Ams2Status::VERIFYING, $row->fresh()->status);
    }

}
