<?php

namespace Tests\Feature;

use App\Enrolment\V2\Ams2DocumentTypes;
use App\Enrolment\V2\Ams2Status;
use App\Models\Ams2Enrolment;
use App\Models\Ams2EnrolmentDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The operator tooling: row ids, the test-row options, and the fixture
 * documents.
 */
class Ams2TestRowCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_row_starts_at_the_id_floor(): void
    {
        $row = Ams2Enrolment::create([
            'secret_key' => 'key-1',
            'student_data' => ['first_name' => 'Test', 'last_name' => 'Student'],
            'status' => Ams2Status::RECEIVED,
        ]);

        /*
         * The id is the operator-facing "AMS2-{id}" reference and is baked into
         * the RTO Data placeholder, so it must not collide with the small ids
         * every other table hands out.
         */
        $this->assertGreaterThanOrEqual(100000, $row->id);
    }

    public function test_the_learner_identity_comes_from_the_options(): void
    {
        $this->artisan('ams2:make-test-row', [
            '--first' => 'Jane',
            '--last' => 'Citizen',
            '--dob' => '1985-06-15',
            '--gender' => 'F',
        ])->assertSuccessful();

        $row = Ams2Enrolment::sole();

        // RTO Data matches on name, so these have to arrive exactly as typed.
        $this->assertSame('Jane', $row->student_data['first_name']);
        $this->assertSame('Citizen', $row->student_data['last_name']);
        $this->assertSame('1985-06-15', $row->student_data['date_of_birth']);
        $this->assertSame('F', $row->student_data['gender']);
    }

    public function test_an_impossible_date_is_rejected_before_the_row_is_created(): void
    {
        // A bad date is treated as absent by the enrol step, which parks the
        // row — a confusing way to discover a typo.
        $this->artisan('ams2:make-test-row', ['--dob' => '1990-02-31'])->assertFailed();
        $this->artisan('ams2:make-test-row', ['--dob' => '15/06/1985'])->assertFailed();

        $this->assertSame(0, Ams2Enrolment::count());
    }

    public function test_an_empty_name_is_rejected(): void
    {
        $this->artisan('ams2:make-test-row', ['--first' => ' '])->assertFailed();

        $this->assertSame(0, Ams2Enrolment::count());
    }

    public function test_the_command_refuses_to_run_in_production(): void
    {
        app()['env'] = 'production';

        $this->artisan('ams2:make-test-row')->assertFailed();
        $this->assertSame(0, Ams2Enrolment::count());
    }

    /*
    |--------------------------------------------------------------------------
    | Fixture documents
    |--------------------------------------------------------------------------
    */

    public function test_with_docs_attaches_an_id_document_and_a_vet_transcript(): void
    {
        Storage::fake('local');

        $this->artisan('ams2:make-test-row', ['--with-docs' => true])->assertSuccessful();

        $row = Ams2Enrolment::sole();

        $this->assertSame(2, $row->documents()->count());
        $this->assertEqualsCanonicalizing(
            Ams2DocumentTypes::keys(),
            $row->documents()->pluck('doc_type')->all(),
        );
    }

    public function test_the_stored_name_differs_from_the_original_name(): void
    {
        Storage::fake('local');

        $this->artisan('ams2:make-test-row', [
            '--first' => 'Jane',
            '--last' => 'Citizen',
            '--with-docs' => true,
        ])->assertSuccessful();

        $documents = Ams2Enrolment::sole()->documents;

        foreach ($documents as $document) {
            /*
             * RTO Data must receive the ORIGINAL name. A fixture that stored
             * them identically would hide a regression in exactly that.
             */
            $this->assertNotSame(basename($document->file_path), $document->original_name);
            Storage::disk('local')->assertExists($document->file_path);
        }

        $this->assertEqualsCanonicalizing(
            ['Jane Citizen ID.jpg', 'Jane Citizen VET Transcript.pdf'],
            $documents->pluck('original_name')->all(),
        );
    }

    public function test_with_docs_records_what_is_expected_and_that_uploading_finished(): void
    {
        Storage::fake('local');

        $this->artisan('ams2:make-test-row', ['--with-docs' => true])->assertSuccessful();

        $row = Ams2Enrolment::sole();

        $this->assertSame(implode(',', Ams2DocumentTypes::keys()), $row->docs_expected);
        $this->assertTrue($row->docs_final);
    }

    public function test_a_row_without_documents_is_marked_final_immediately(): void
    {
        // Nothing to wait for, so the documents step must not sit blocked.
        $this->artisan('ams2:make-test-row')->assertSuccessful();

        $row = Ams2Enrolment::sole();

        $this->assertSame('', $row->docs_expected);
        $this->assertTrue($row->docs_final);
    }

    public function test_documents_start_pending(): void
    {
        Storage::fake('local');

        $this->artisan('ams2:make-test-row', ['--with-docs' => true])->assertSuccessful();

        $this->assertSame(
            [Ams2EnrolmentDocument::STATUS_PENDING, Ams2EnrolmentDocument::STATUS_PENDING],
            Ams2EnrolmentDocument::pluck('status')->all(),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Attaching to an existing row
    |--------------------------------------------------------------------------
    */

    public function test_documents_can_be_attached_to_an_existing_row(): void
    {
        Storage::fake('local');

        $this->artisan('ams2:make-test-row')->assertSuccessful();
        $row = Ams2Enrolment::sole();

        $this->artisan('ams2:attach-test-docs', ['id' => $row->id])->assertSuccessful();

        $this->assertSame(2, $row->fresh()->documents()->count());
        $this->assertSame(implode(',', Ams2DocumentTypes::keys()), $row->fresh()->docs_expected);
    }

    public function test_attaching_twice_needs_force(): void
    {
        Storage::fake('local');

        $this->artisan('ams2:make-test-row', ['--with-docs' => true])->assertSuccessful();
        $row = Ams2Enrolment::sole();

        // A second copy would send the same file to RTO Data twice.
        $this->artisan('ams2:attach-test-docs', ['id' => $row->id])->assertFailed();
        $this->assertSame(2, $row->fresh()->documents()->count());

        $this->artisan('ams2:attach-test-docs', ['id' => $row->id, '--force' => true])->assertSuccessful();
        $this->assertSame(4, $row->fresh()->documents()->count());
    }

    public function test_attaching_to_a_missing_row_fails(): void
    {
        $this->artisan('ams2:attach-test-docs', ['id' => 999999])->assertFailed();
    }
}
