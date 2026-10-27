<?php

namespace Tests\Feature;

use App\Enrolment\V2\Ams2DocumentReceiver;
use App\Models\Ams2Enrolment;
use App\Models\Ams2EnrolmentDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Enrolment v2 stage 6: receiving the documents, and the browser saying it has
 * finished.
 *
 * One file per request. That is what lets a single flaky upload be retried
 * without resubmitting the enrolment, and it keeps each request inside
 * upload_max_filesize rather than needing every document to fit at once.
 */
class Ams2DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Ams2DocumentReceiver::DISK);
        config()->set('services.ams2.admin_only', true);
        config()->set('services.ams2.max_document_bytes', 10 * 1024 * 1024);

        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function row(array $attributes = []): Ams2Enrolment
    {
        return Ams2Enrolment::create(array_merge([
            'secret_key' => bin2hex(random_bytes(16)),
            'course_id' => 32,
            'plan_id' => 38,
            'real_email' => 'learner@example.test',
            'docs_expected' => 'id_document,vet_transcript',
            'docs_final' => false,
            'student_data' => ['first_name' => 'Jane', 'last_name' => 'Citizen'],
        ], $attributes));
    }

    private function upload(
        Ams2Enrolment $row,
        ?UploadedFile $file = null,
        array $overrides = [],
    ): \Illuminate\Testing\TestResponse {
        return $this->postJson(route('enrol.v2.documents'), array_merge([
            'secretKey' => $row->secret_key,
            'id' => $row->id,
            'doc_type' => 'id_document',
            'document' => $file ?? UploadedFile::fake()->image('my licence.jpg', 600, 400),
        ], $overrides));
    }

    /*
    |--------------------------------------------------------------------------
    | A document arrives
    |--------------------------------------------------------------------------
    */

    public function test_a_document_is_stored_and_recorded(): void
    {
        $row = $this->row();

        $response = $this->upload($row);

        $response->assertOk();
        $response->assertJsonPath('data.doc_type', 'id_document');

        $document = Ams2EnrolmentDocument::query()->where('enrolment_row', $row->id)->firstOrFail();

        $this->assertSame('id_document', $document->doc_type);
        $this->assertSame(Ams2EnrolmentDocument::STATUS_PENDING, $document->status);
        $this->assertSame('image/jpeg', $document->mime);
        $this->assertGreaterThan(0, $document->size_bytes);

        Storage::disk(Ams2DocumentReceiver::DISK)->assertExists($document->file_path);
    }

    public function test_the_original_filename_is_kept(): void
    {
        $row = $this->row();

        $this->upload($row, UploadedFile::fake()->image('Jane Citizen Licence.jpg'));

        $document = Ams2EnrolmentDocument::query()->where('enrolment_row', $row->id)->firstOrFail();

        /*
         * RTO Data must receive the name the learner chose, not the randomised
         * storage name — the whole point of the 19/10 fix.
         */
        $this->assertSame('Jane Citizen Licence.jpg', $document->original_name);
        $this->assertStringNotContainsString('Jane', $document->file_path);
    }

    public function test_the_file_is_stored_privately_under_the_row(): void
    {
        $row = $this->row();

        $this->upload($row);

        $document = Ams2EnrolmentDocument::query()->where('enrolment_row', $row->id)->firstOrFail();

        // Same convention Ams2TestDocuments uses, so the sender works unchanged.
        $this->assertStringStartsWith(
            Ams2DocumentReceiver::DIRECTORY.'/'.$row->id.'/',
            $document->file_path,
        );
    }

    public function test_a_path_in_the_filename_cannot_escape(): void
    {
        $row = $this->row();

        $this->upload($row, UploadedFile::fake()->createWithContent(
            '../../evil.jpg',
            file_get_contents(base_path('resources/fixtures/ams2/test-id.jpg')),
        ));

        $document = Ams2EnrolmentDocument::query()->where('enrolment_row', $row->id)->firstOrFail();

        // original_name is sent to RTO Data as a filename, so it must not
        // carry a path.
        $this->assertSame('evil.jpg', $document->original_name);
    }

    public function test_both_document_types_are_accepted(): void
    {
        $row = $this->row();

        $this->upload($row, null, ['doc_type' => 'id_document'])->assertOk();
        $this->upload($row, UploadedFile::fake()->create(
            'transcript.pdf',
            20,
            'application/pdf',
        ), ['doc_type' => 'vet_transcript'])->assertOk();

        $this->assertSame(2, Ams2EnrolmentDocument::query()->where('enrolment_row', $row->id)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | A retry replaces the previous file
    |--------------------------------------------------------------------------
    */

    public function test_re_uploading_replaces_the_previous_unsent_file(): void
    {
        $row = $this->row();

        $this->upload($row);
        $first = Ams2EnrolmentDocument::query()->where('enrolment_row', $row->id)->firstOrFail();

        $this->upload($row, UploadedFile::fake()->image('better.jpg'));

        $this->assertSame(
            Ams2EnrolmentDocument::STATUS_REPLACED,
            $first->fresh()->status,
        );

        // The superseded file goes with it; nothing will ever send it.
        Storage::disk(Ams2DocumentReceiver::DISK)->assertMissing($first->file_path);

        $current = Ams2EnrolmentDocument::query()
            ->where('enrolment_row', $row->id)
            ->where('status', Ams2EnrolmentDocument::STATUS_PENDING)
            ->get();

        $this->assertCount(1, $current);
        $this->assertSame('better.jpg', $current->first()->original_name);
    }

    public function test_a_document_already_sent_is_left_alone(): void
    {
        $row = $this->row();
        $this->upload($row);

        $sent = Ams2EnrolmentDocument::query()->where('enrolment_row', $row->id)->firstOrFail();
        $sent->update(['status' => Ams2EnrolmentDocument::STATUS_SENT, 'sent_at' => now()]);

        $this->upload($row, UploadedFile::fake()->image('again.jpg'));

        // Already at RTO Data, so it is history, not something to supersede.
        $this->assertSame(Ams2EnrolmentDocument::STATUS_SENT, $sent->fresh()->status);
    }

    public function test_the_other_type_is_not_disturbed(): void
    {
        $row = $this->row();

        $this->upload($row, UploadedFile::fake()->create('t.pdf', 20, 'application/pdf'), ['doc_type' => 'vet_transcript']);
        $transcript = Ams2EnrolmentDocument::query()->where('doc_type', 'vet_transcript')->firstOrFail();

        $this->upload($row, UploadedFile::fake()->image('id.jpg'), ['doc_type' => 'id_document']);

        $this->assertSame(Ams2EnrolmentDocument::STATUS_PENDING, $transcript->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Files the server will not take
    |--------------------------------------------------------------------------
    */

    /**
     * A real upload over a temp file.
     *
     * NOT UploadedFile::fake(): Illuminate\Http\Testing\File overrides
     * getMimeType() to report the type of the FILENAME, so a fake can never
     * exercise the content sniffing this flow depends on.
     */
    private function realFile(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ams2test');
        file_put_contents($path, $contents);

        $this->tempFiles[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_a_renamed_file_is_refused_on_its_contents(): void
    {
        $row = $this->row();

        // A zip called .pdf. The type is read from the file itself, never from
        // the name or the browser's Content-Type.
        $response = $this->upload($row, $this->realFile(
            'cv.pdf',
            'PK'.chr(3).chr(4).str_repeat('not really a pdf at all', 20),
        ));

        $response->assertStatus(400);
        $response->assertJsonPath('data.code', 'bad_mime');
        $response->assertJsonPath('data.message', 'This file type is not accepted. Upload a PDF, JPG, PNG or WEBP file.');

        $this->assertSame(0, Ams2EnrolmentDocument::query()->count());
    }

    public function test_a_genuine_image_with_the_wrong_extension_is_accepted(): void
    {
        $row = $this->row();

        // The other half of sniffing: a real JPEG a learner saved as .pdf is
        // still a usable ID, and is stored under the extension its contents
        // say, not the one its name claims.
        $this->upload($row, $this->realFile(
            'licence.pdf',
            (string) file_get_contents(base_path('resources/fixtures/ams2/test-id.jpg')),
        ))->assertOk();

        $document = Ams2EnrolmentDocument::query()->where('enrolment_row', $row->id)->firstOrFail();

        $this->assertSame('image/jpeg', $document->mime);
        $this->assertStringEndsWith('.jpg', $document->file_path);
        // RTO Data still receives the name the learner chose.
        $this->assertSame('licence.pdf', $document->original_name);
    }

    public function test_a_file_over_the_limit_is_refused(): void
    {
        config()->set('services.ams2.max_document_bytes', 1024);

        $response = $this->upload($this->row(), UploadedFile::fake()->image('big.jpg', 900, 900));

        $response->assertStatus(400);
        $response->assertJsonPath('data.code', 'too_large');
        $this->assertSame(0, Ams2EnrolmentDocument::query()->count());
    }

    public function test_an_empty_file_is_refused(): void
    {
        $response = $this->upload($this->row(), UploadedFile::fake()->createWithContent('empty.pdf', ''));

        $response->assertStatus(400);
        $this->assertSame(0, Ams2EnrolmentDocument::query()->count());
    }

    public function test_a_missing_file_is_refused(): void
    {
        $row = $this->row();

        $response = $this->postJson(route('enrol.v2.documents'), [
            'secretKey' => $row->secret_key,
            'id' => $row->id,
            'doc_type' => 'id_document',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('data.code', 'no_file');
    }

    public function test_an_unknown_document_type_is_refused(): void
    {
        $response = $this->upload($this->row(), null, ['doc_type' => 'passport']);

        $response->assertStatus(400);
        $response->assertJsonPath('data.code', 'bad_type');
    }

    /*
    |--------------------------------------------------------------------------
    | Whose enrolment is this
    |--------------------------------------------------------------------------
    */

    public function test_the_wrong_secret_key_cannot_attach_a_document(): void
    {
        $row = $this->row();

        $response = $this->upload($row, null, ['secretKey' => bin2hex(random_bytes(16))]);

        /*
         * Matched on the id AND the key together, so knowing a row id is not
         * enough to attach a document to someone else's enrolment.
         */
        $response->assertStatus(403);
        $response->assertJsonPath('data.code', 'no_match');
        $response->assertJsonPath('data.message', 'We could not match this document to your enrolment. Email it to sales@amstraining.com.au instead.');
    }

    public function test_another_rows_id_cannot_be_used_with_this_key(): void
    {
        $mine = $this->row();
        $theirs = $this->row();

        $response = $this->upload($mine, null, ['id' => $theirs->id]);

        $response->assertStatus(403);
        $response->assertJsonPath('data.code', 'no_match');
    }

    public function test_uploads_close_after_seven_days(): void
    {
        $row = $this->row();
        $row->created_at = now()->subDays(Ams2DocumentReceiver::UPLOAD_WINDOW_DAYS + 1);
        $row->save();

        $response = $this->upload($row);

        $response->assertStatus(403);
        $response->assertJsonPath('data.code', 'closed');
        $response->assertJsonPath('data.message', 'Online uploads for this enrolment have closed. Email the document to sales@amstraining.com.au.');
    }

    public function test_uploads_are_still_open_inside_the_window(): void
    {
        $row = $this->row();
        $row->created_at = now()->subDays(Ams2DocumentReceiver::UPLOAD_WINDOW_DAYS - 1);
        $row->save();

        $this->upload($row)->assertOk();
    }

    public function test_a_student_cannot_upload_while_the_switch_is_on(): void
    {
        $row = $this->row();

        app('auth')->forgetGuards();
        auth()->logout();

        $response = $this->upload($row);

        $response->assertStatus(403);
        $response->assertJsonPath('data.code', 'not_available');
    }

    /*
    |--------------------------------------------------------------------------
    | Finalise
    |--------------------------------------------------------------------------
    */

    private function finalise(Ams2Enrolment $row, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('enrol.v2.finalise'), array_merge([
            'secretKey' => $row->secret_key,
            'id' => $row->id,
        ], $overrides));
    }

    public function test_finalising_marks_the_row_ready_for_the_documents_step(): void
    {
        $row = $this->row();

        $this->finalise($row)->assertOk();

        /*
         * Without this the row waits out SendDocumentsStep's 15-minute grace
         * period before anything is sent — nothing fails, enrolments are just
         * silently slower.
         */
        $this->assertTrue((bool) $row->fresh()->docs_final);
    }

    public function test_finalising_twice_is_harmless(): void
    {
        $row = $this->row();

        $this->finalise($row)->assertOk();
        $this->finalise($row)->assertOk();

        $this->assertTrue((bool) $row->fresh()->docs_final);
    }

    public function test_finalising_the_wrong_row_changes_nothing(): void
    {
        $row = $this->row();

        $response = $this->finalise($row, ['secretKey' => bin2hex(random_bytes(16))]);

        /*
         * Still a success: this is delivered by fetch(keepalive) as the page
         * unloads, where a failure cannot be retried or even seen. The row
         * simply stays unfinalised and waits out the grace period.
         */
        $response->assertOk();
        $this->assertFalse((bool) $row->fresh()->docs_final);
    }

    public function test_finalising_an_unknown_row_is_still_a_success(): void
    {
        $this->postJson(route('enrol.v2.finalise'), [
            'secretKey' => bin2hex(random_bytes(16)),
            'id' => 999999,
        ])->assertOk();
    }
}
