<?php

namespace Tests\Feature;

use App\Enrolment\V2\Ams2DocumentTypes;
use App\Enrolment\V2\Ams2DocumentUploader;
use App\Enrolment\V2\Ams2Processor;
use App\Enrolment\V2\Ams2Status;
use App\Enrolment\V2\Ams2TestDocuments;
use App\Enrolment\V2\Steps\SendDocumentsStep;
use App\Models\Ams2Enrolment;
use App\Models\Ams2EnrolmentDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Enrolment v2 stage 3: the documents step.
 *
 * The behaviour worth holding onto: a document that cannot be sent does not
 * stop the enrolment. The learner was never shown an error and is already
 * enrolled in RTO Data, so the row advances and an alert names the gap.
 */
class Ams2DocumentsStepTest extends TestCase
{
    use RefreshDatabase;

    private const ENROLMENT_ID = '600001';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config()->set('services.enrolment_api.base_url', 'https://rtodata.test/api/v2');
        config()->set('services.enrolment_api.public_key', 'pk_test');
        config()->set('services.enrolment_api.subdomain', 'amstraining');
        config()->set('services.enrolment_api.origin', 'https://ams.test');

        config()->set('services.ams2', [
            'enabled' => true,
            'connect_timeout' => 15,
            'timeout' => 90,
            'enrolment_floor' => 500000,
        ]);
    }

    /**
     * A row already enrolled, with both documents attached and uploading done.
     */
    private function rowWithDocuments(array $attributes = []): Ams2Enrolment
    {
        $row = Ams2Enrolment::create(array_merge([
            'secret_key' => 'key-'.uniqid(),
            'course_id' => 32,
            'plan_id' => 38,
            'enrolment_id' => self::ENROLMENT_ID,
            'real_email' => 'learner@example.test',
            'status' => Ams2Status::ENROLLED,
            'docs_expected' => implode(',', Ams2DocumentTypes::keys()),
            'docs_final' => true,
            'student_data' => [
                'first_name' => 'Jane',
                'last_name' => 'Citizen',
                'date_of_birth' => '1985-06-15',
            ],
        ], $attributes));

        app(Ams2TestDocuments::class)->attach($row);

        return $row->fresh();
    }

    private function step(): SendDocumentsStep
    {
        return app(SendDocumentsStep::class);
    }

    private function accepted(): array
    {
        // The reference decides success by searching for this exact substring.
        return ['*' => Http::response('{"success":1,"message":"ok"}', 200)];
    }

    private function rejected(): array
    {
        return ['*' => Http::response('{"success":0,"message":"rejected"}', 200)];
    }

    /*
    |--------------------------------------------------------------------------
    | All documents sent
    |--------------------------------------------------------------------------
    */

    public function test_all_documents_are_sent_and_the_row_advances(): void
    {
        Http::fake($this->accepted());

        $row = $this->rowWithDocuments();
        $this->step()->handle($row);

        $this->assertSame(Ams2Status::DOCUMENTS_DONE, $row->fresh()->status);
        $this->assertSame(2, Ams2EnrolmentDocument::where('status', Ams2EnrolmentDocument::STATUS_SENT)->count());
        Http::assertSentCount(2);
    }

    public function test_the_original_filename_is_what_rto_data_receives(): void
    {
        Http::fake($this->accepted());

        $row = $this->rowWithDocuments();
        $stored = $row->documents->pluck('file_path')->map(fn ($p) => basename($p))->all();

        $this->step()->handle($row);

        Http::assertSent(function (Request $request) use ($stored) {
            $name = $request->data()['files'][0]['name'] ?? '';

            /*
             * The 19/10 fix. A document arriving in RTO Data as
             * "01kwxetpy1.pdf" is useless to whoever has to read it.
             */
            return str_contains($name, 'Jane Citizen')
                && ! in_array($name, $stored, true);
        });
    }

    public function test_the_file_is_sent_as_a_base64_data_uri_against_the_enrolment_id(): void
    {
        Http::fake($this->accepted());

        $this->step()->handle($this->rowWithDocuments());

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return $data['type'] === 'receive'
                && $data['enrolment_id'] === self::ENROLMENT_ID
                && str_starts_with($data['files'][0]['uri'], 'data:');
        });
    }

    public function test_a_sent_document_has_its_local_file_deleted(): void
    {
        Http::fake($this->accepted());

        $row = $this->rowWithDocuments();
        $paths = $row->documents->pluck('file_path')->all();

        $this->step()->handle($row);

        // RTO Data holds it now; the local copy is not kept.
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | One fails, the rest succeed
    |--------------------------------------------------------------------------
    */

    public function test_a_rejected_document_is_retried_and_the_row_waits(): void
    {
        Http::fake($this->rejected());

        $row = $this->rowWithDocuments();
        $this->step()->handle($row);

        // Still outstanding, so the row goes back to be picked up next minute.
        $this->assertSame(Ams2Status::ENROLLED, $row->fresh()->status);
        $this->assertSame(2, Ams2EnrolmentDocument::where('status', Ams2EnrolmentDocument::STATUS_RETRY)->count());
    }

    public function test_a_rejected_document_keeps_its_file(): void
    {
        Http::fake($this->rejected());

        $row = $this->rowWithDocuments();
        $paths = $row->documents->pluck('file_path')->all();

        $this->step()->handle($row);

        // A failed document must stay recoverable by hand.
        foreach ($paths as $path) {
            Storage::disk('local')->assertExists($path);
        }
    }

    public function test_the_row_advances_once_a_document_gives_up_and_an_alert_names_it(): void
    {
        Http::fake($this->rejected());
        Log::spy();

        $row = $this->rowWithDocuments();

        // One document already at its last attempt; the other will succeed.
        $id = $row->documents()->where('doc_type', Ams2DocumentTypes::ID_DOCUMENT)->sole();
        $id->update(['attempts' => Ams2Processor::MAX_STEPS_PER_ROW - 1]);
        $row->documents()->where('doc_type', Ams2DocumentTypes::VET_TRANSCRIPT)->sole()
            ->update(['status' => Ams2EnrolmentDocument::STATUS_SENT, 'sent_at' => now()]);

        $this->step()->handle($row->fresh());

        /*
         * The learner is already enrolled and was never shown an error, so a
         * document that cannot be sent must not strand them.
         */
        $this->assertSame(Ams2Status::DOCUMENTS_DONE, $row->fresh()->status);
        $this->assertSame(Ams2EnrolmentDocument::STATUS_FAILED, $id->fresh()->status);

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'not attached to it')
                && str_contains(implode(' ', $context['details'] ?? []), 'ID document'))
            ->once();
    }

    public function test_an_expected_document_that_never_arrived_is_alerted(): void
    {
        Http::fake($this->accepted());
        Log::spy();

        // Expected both, but only one was ever uploaded.
        $row = Ams2Enrolment::create([
            'secret_key' => 'key-missing',
            'enrolment_id' => self::ENROLMENT_ID,
            'status' => Ams2Status::ENROLLED,
            'docs_expected' => implode(',', Ams2DocumentTypes::keys()),
            'docs_final' => true,
            'student_data' => ['first_name' => 'Jane', 'last_name' => 'Citizen'],
        ]);

        $this->step()->handle($row);

        $this->assertSame(Ams2Status::DOCUMENTS_DONE, $row->fresh()->status);

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []) => str_contains(
                implode(' ', $context['details'] ?? []),
                'never reached the website',
            ))
            ->once();
    }

    public function test_a_missing_file_is_recorded_without_calling_rto_data(): void
    {
        Http::fake($this->accepted());

        $row = $this->rowWithDocuments();
        $document = $row->documents()->first();
        Storage::disk('local')->delete($document->file_path);

        $this->step()->handle($row);

        $this->assertSame(Ams2EnrolmentDocument::STATUS_FAILED, $document->fresh()->status);
        $this->assertStringContainsString('File missing', (string) $document->fresh()->error_message);
        Http::assertSentCount(1);
    }

    /*
    |--------------------------------------------------------------------------
    | Retries never re-upload
    |--------------------------------------------------------------------------
    */

    public function test_a_retry_does_not_resend_an_already_sent_document(): void
    {
        Http::fake($this->accepted());

        $row = $this->rowWithDocuments();
        $this->step()->handle($row);
        Http::assertSentCount(2);

        // Put the row back as the stalled-document sweep would.
        $row->update(['status' => Ams2Status::ENROLLED]);
        $this->step()->handle($row->fresh());

        /*
         * Only pending and retry documents are ever selected, so a sent one
         * cannot be uploaded twice.
         */
        Http::assertSentCount(2);
        $this->assertSame(Ams2Status::DOCUMENTS_DONE, $row->fresh()->status);
    }

    public function test_a_stalled_send_returns_to_retry_and_is_resent(): void
    {
        Http::fake($this->accepted());

        $row = $this->rowWithDocuments();
        $document = $row->documents()->first();
        $document->update(['status' => Ams2EnrolmentDocument::STATUS_SENDING]);
        $document->forceFill(['updated_at' => now()->subMinutes(31)])->saveQuietly();

        // Resending a document is safe, unlike repeating an enrol.
        $this->processor()->run();

        $this->assertSame(Ams2EnrolmentDocument::STATUS_SENT, $document->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Readiness gate
    |--------------------------------------------------------------------------
    */

    public function test_a_row_whose_uploads_are_unfinished_is_left_alone(): void
    {
        Http::fake($this->accepted());

        $row = $this->rowWithDocuments(['docs_final' => false]);
        $this->step()->handle($row);

        $this->assertSame(Ams2Status::ENROLLED, $row->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_an_unfinished_row_proceeds_once_the_grace_period_passes(): void
    {
        Http::fake($this->accepted());

        $row = $this->rowWithDocuments(['docs_final' => false]);
        $row->forceFill([
            'created_at' => now()->subMinutes(SendDocumentsStep::UPLOAD_GRACE_MINUTES + 1),
        ])->saveQuietly();

        $this->step()->handle($row->fresh());

        $this->assertSame(Ams2Status::DOCUMENTS_DONE, $row->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | The brittle success check
    |--------------------------------------------------------------------------
    */

    public function test_a_spaced_success_key_is_treated_as_failure_but_logged(): void
    {
        // The reference's substring test wants `"success":1` exactly.
        Http::fake(['*' => Http::response('{"success": 1}', 200)]);
        Log::spy();

        $row = $this->rowWithDocuments();
        $this->step()->handle($row);

        $this->assertSame(0, Ams2EnrolmentDocument::where('status', Ams2EnrolmentDocument::STATUS_SENT)->count());

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => str_contains($message, 'ams2_document_success_mismatch'))
            ->twice();
    }

    public function test_a_genuine_rejection_is_not_reported_as_a_mismatch(): void
    {
        Http::fake($this->rejected());
        Log::spy();

        $this->step()->handle($this->rowWithDocuments());

        Log::shouldNotHaveReceived('warning', [
            \Mockery::on(fn ($message) => str_contains((string) $message, 'ams2_document_success_mismatch')),
            \Mockery::any(),
        ]);
    }

    public function test_the_success_marker_matches_the_reference(): void
    {
        $this->assertSame('"success":1', Ams2DocumentUploader::SUCCESS_MARKER);
    }

    private function processor(): Ams2Processor
    {
        return app(Ams2Processor::class);
    }
}
