<?php

namespace Tests\Feature;

use App\Jobs\SubmitEnrolmentToApiJob;
use App\Models\Course;
use App\Models\Enrolment;
use App\Models\EnrolmentSubmission;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the existing (v1) enrolment form at /enrol/{token}.
 *
 * Written before the registration form markup is extracted into a partial
 * shared with the v2 form, so that refactor has something holding it in place.
 * Nothing here is new behaviour: it pins down what routes/web.php already does.
 */
class EnrolmentV1FormTest extends TestCase
{
    use RefreshDatabase;

    private function enrolment(array $attributes = []): Enrolment
    {
        $student = Student::create([
            'first_name' => 'Jane',
            'last_name' => 'Citizen',
            'email' => 'jane@example.test',
        ]);

        $order = Order::create(['student_id' => $student->id]);

        $course = Course::create([
            'title' => 'Work Safely At Heights',
            'code' => 'RIIWHS204E',
            'status' => 'active',
            'price' => 110,
            'ams_enrolment_code' => '32',
            'ams_plan_id' => '38',
        ]);

        return Enrolment::create(array_merge([
            'order_id' => $order->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'enrolment_token' => 'tok-'.uniqid(),
            'enrolment_token_expires_at' => now()->addDays(30),
            'secret_key' => hash('sha256', uniqid('', true)),
            'secret_base_url' => 'https://ams.test/registration-form/?code=32&plan=38',
            'status' => 'link_created',
        ], $attributes));
    }

    /**
     * The minimum the v1 route accepts. Every field is nullable server-side,
     * so this is deliberately sparse.
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => '32',
            'plan' => '38',
            'date_of_birth' => '1990-01-01',
            'usi' => 'ABC123XYZ9',
            'state' => 'NSW',
            'postcode' => '2000',
            'mobile_phone' => '0400000000',
        ], $overrides);
    }

    /*
    |--------------------------------------------------------------------------
    | Rendering the form
    |--------------------------------------------------------------------------
    */

    public function test_a_valid_link_renders_the_registration_form(): void
    {
        $enrolment = $this->enrolment();

        $response = $this->get('/enrol/'.$enrolment->enrolment_token);

        $response->assertOk();
        $response->assertViewIs('enrolments.registration-form');

        // Each section of the form, so an extraction that drops one is caught.
        foreach (['Personal', 'USI', 'Address', 'Contact', 'Education', 'Demography', 'Needs', 'Uploads', 'Declarations'] as $section) {
            $response->assertSee($section);
        }
    }

    public function test_the_form_carries_the_fields_the_api_payload_is_built_from(): void
    {
        $enrolment = $this->enrolment();

        $response = $this->get('/enrol/'.$enrolment->enrolment_token);

        foreach ([
            'first_name', 'last_name', 'date_of_birth', 'gender', 'usi',
            'city_of_birth', 'country_of_birth', 'main_language_home',
            'street_number', 'street_name', 'suburb', 'state', 'postcode',
            'school_level', 'currently_at_school', 'study_reason',
            'labour_force', 'disability', 'atsi_status',
            'id_document', 'vet_transcript',
        ] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }
    }

    public function test_the_course_code_and_plan_come_from_the_secret_base_url(): void
    {
        $enrolment = $this->enrolment();

        $response = $this->get('/enrol/'.$enrolment->enrolment_token);

        $response->assertViewHas('code', '32');
        $response->assertViewHas('plan', '38');
    }

    /*
    |--------------------------------------------------------------------------
    | Links the route refuses
    |--------------------------------------------------------------------------
    */

    public function test_an_unknown_token_is_refused(): void
    {
        $this->get('/enrol/does-not-exist')
            ->assertRedirect('/enrolment-not-successful');
    }

    public function test_an_already_completed_enrolment_is_refused(): void
    {
        $enrolment = $this->enrolment(['enrolment_completed_at' => now()->subDay()]);

        $this->get('/enrol/'.$enrolment->enrolment_token)
            ->assertRedirect('/enrolment-not-successful');
    }

    public function test_an_expired_token_is_refused(): void
    {
        $enrolment = $this->enrolment(['enrolment_token_expires_at' => now()->subDay()]);

        $this->get('/enrol/'.$enrolment->enrolment_token)
            ->assertRedirect('/enrolment-not-successful');
    }

    /*
    |--------------------------------------------------------------------------
    | Submitting
    |--------------------------------------------------------------------------
    */

    public function test_a_submission_is_saved_and_queued_and_the_link_is_closed(): void
    {
        Bus::fake();

        $enrolment = $this->enrolment();

        $this->post('/enrol/'.$enrolment->enrolment_token, $this->payload())
            ->assertRedirect('/enrolment-completed');

        $submission = EnrolmentSubmission::query()->where('enrolment_id', $enrolment->id)->first();

        $this->assertNotNull($submission);
        $this->assertSame('pending', $submission->api_status);
        $this->assertSame('32', $submission->code);
        $this->assertSame('38', $submission->plan);
        $this->assertNotNull($submission->submitted_at);

        Bus::assertDispatched(SubmitEnrolmentToApiJob::class);

        $fresh = $enrolment->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->enrolment_completed_at);
    }

    public function test_identity_fields_come_from_the_student_record_not_the_post(): void
    {
        Bus::fake();

        $enrolment = $this->enrolment();

        // A readonly field in the browser is still editable with dev tools.
        $this->post('/enrol/'.$enrolment->enrolment_token, $this->payload([
            'first_name' => 'Forged',
            'last_name' => 'Name',
            'email' => 'attacker@example.test',
        ]))->assertRedirect('/enrolment-completed');

        $submission = EnrolmentSubmission::query()->where('enrolment_id', $enrolment->id)->firstOrFail();
        $data = (array) $submission->form_data;

        $this->assertSame('Jane', $data['first_name']);
        $this->assertSame('Citizen', $data['last_name']);
        $this->assertSame('jane@example.test', $data['email']);
    }

    public function test_uploaded_documents_are_stored_on_the_private_disk(): void
    {
        Bus::fake();
        Storage::fake('local');

        $enrolment = $this->enrolment();

        $this->post('/enrol/'.$enrolment->enrolment_token, $this->payload([
            'id_document' => UploadedFile::fake()->image('licence.jpg'),
            'vet_transcript' => UploadedFile::fake()->create('transcript.pdf', 20, 'application/pdf'),
        ]))->assertRedirect('/enrolment-completed');

        $submission = EnrolmentSubmission::query()->where('enrolment_id', $enrolment->id)->firstOrFail();

        $this->assertNotNull($submission->id_document_path);
        $this->assertNotNull($submission->vet_transcript_path);

        Storage::disk('local')->assertExists($submission->id_document_path);
        Storage::disk('local')->assertExists($submission->vet_transcript_path);
    }

    public function test_a_used_link_cannot_be_submitted_twice(): void
    {
        Bus::fake();

        $enrolment = $this->enrolment();

        $this->post('/enrol/'.$enrolment->enrolment_token, $this->payload())
            ->assertRedirect('/enrolment-completed');

        $this->post('/enrol/'.$enrolment->enrolment_token, $this->payload())
            ->assertRedirect('/enrolment-not-successful');

        $this->assertSame(1, EnrolmentSubmission::query()->where('enrolment_id', $enrolment->id)->count());
    }

    public function test_an_unknown_token_cannot_be_submitted(): void
    {
        Bus::fake();

        $this->post('/enrol/does-not-exist', $this->payload())
            ->assertRedirect('/enrolment-not-successful');

        $this->assertSame(0, EnrolmentSubmission::query()->count());
        Bus::assertNotDispatched(SubmitEnrolmentToApiJob::class);
    }
}
