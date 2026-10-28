<?php

namespace Tests\Feature;

use App\Enrolment\V2\Ams2Status;
use App\Models\Ams2Enrolment;
use App\Models\Enrolment;
use App\Models\EnrolmentEmailTemplate;
use App\Models\EnrolmentSubmission;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enrolment v2 stage 6: saving the enrolment.
 *
 * The form posts the fields with no files, and the documents follow in their
 * own requests. So the row is created here in `received` with `docs_expected`
 * describing what the BROWSER says it is holding — not what has arrived — and
 * `docs_final` false until the browser says it has finished.
 */
class Ams2SubmitTest extends TestCase
{
    use RefreshDatabase;

    private const COURSE_ID = 32;

    private const PLAN_ID = 38;

    protected function setUp(): void
    {
        parent::setUp();

        EnrolmentEmailTemplate::flushGateCache();
        config()->set('services.ams2.admin_only', true);

        EnrolmentEmailTemplate::create([
            'name' => 'Confirmation',
            'course_id' => self::COURSE_ID,
            'plan_id' => self::PLAN_ID,
            'is_published' => true,
            'email_body' => '<p>Hello {first_name}.</p>',
        ]);
    }

    private function enrolment(array $attributes = []): Enrolment
    {
        $student = Student::create([
            'first_name' => 'Jane',
            'last_name' => 'Citizen',
            'email' => 'jane'.uniqid().'@example.test',
        ]);

        $order = Order::create(['student_id' => $student->id]);

        return Enrolment::create(array_merge([
            'order_id' => $order->id,
            'student_id' => $student->id,
            'enrolment_token' => 'tok'.bin2hex(random_bytes(8)),
            'enrolment_token_expires_at' => now()->addDays(30),
            'secret_key' => bin2hex(random_bytes(16)),
            'secret_base_url' => 'https://ams.test/registration-form/?code='.self::COURSE_ID
                .'&plan='.self::PLAN_ID.'&company=ACME',
            'status' => 'link_created',
        ], $attributes));
    }

    /**
     * Only the four fields the reference insists on, plus whatever a test adds.
     *
     * @return array<string, mixed>
     */
    private function payload(Enrolment $enrolment, array $overrides = []): array
    {
        return array_merge([
            'secretKey' => $enrolment->secret_key,
            'first_name' => 'Jane',
            'last_name' => 'Citizen',
            'date_of_birth' => '1990-01-01',
            'email' => 'jane.citizen@example.test',
        ], $overrides);
    }

    private function asAdmin(): self
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        return $this;
    }

    private function submit(Enrolment $enrolment, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('enrol.v2.submit'), $this->payload($enrolment, $overrides));
    }

    /*
    |--------------------------------------------------------------------------
    | A successful submit
    |--------------------------------------------------------------------------
    */

    public function test_a_submit_creates_a_received_row(): void
    {
        $enrolment = $this->enrolment();

        $response = $this->asAdmin()->submit($enrolment);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.already_submitted', false);

        $row = Ams2Enrolment::query()->where('secret_key', $enrolment->secret_key)->firstOrFail();

        $this->assertSame(Ams2Status::RECEIVED, $row->status);
        $this->assertSame(0, $row->attempts);
        $this->assertSame('jane.citizen@example.test', $row->real_email);
        $this->assertSame($response->json('data.id'), $row->id);
    }

    public function test_the_course_and_order_come_from_the_link(): void
    {
        $enrolment = $this->enrolment();

        // Forged in the POST; the link is what counts.
        $this->asAdmin()->submit($enrolment, [
            'course_id' => 999,
            'plan_id' => 999,
            'client_id' => 'FORGED',
        ]);

        $row = Ams2Enrolment::query()->where('secret_key', $enrolment->secret_key)->firstOrFail();

        $this->assertSame(self::COURSE_ID, $row->course_id);
        $this->assertSame(self::PLAN_ID, $row->plan_id);
        $this->assertSame($enrolment->order_id, (int) $row->order_id);
        $this->assertSame('ACME', $row->student_data['client_id']);
    }

    public function test_the_form_plumbing_is_kept_out_of_student_data(): void
    {
        $enrolment = $this->enrolment();

        $this->asAdmin()->submit($enrolment, [
            'course_id' => 999,
            'plan_id' => 999,
            'doc_expected' => 'id_document',
            'ams_action' => 'process_enrolment',
            'ams2_noop' => '1',
        ]);

        $data = Ams2Enrolment::query()->where('secret_key', $enrolment->secret_key)->firstOrFail()->student_data;

        foreach (['secretKey', 'doc_expected', 'ams_action', 'ams2_noop', '_token', 'course_id', 'plan_id'] as $key) {
            $this->assertArrayNotHasKey($key, $data, $key.' must not reach student_data');
        }
    }

    public function test_the_state_is_stored_as_its_avetmiss_code(): void
    {
        $enrolment = $this->enrolment();

        $this->asAdmin()->submit($enrolment, ['state' => 'NSW']);

        $data = Ams2Enrolment::query()->where('secret_key', $enrolment->secret_key)->firstOrFail()->student_data;

        $this->assertSame('01', $data['state']);
    }

    public function test_an_unrecognised_state_is_blanked(): void
    {
        $enrolment = $this->enrolment();

        // An invalid code would be refused on enrol, so the reference sends ''.
        $this->asAdmin()->submit($enrolment, ['state' => 'Narnia']);

        $data = Ams2Enrolment::query()->where('secret_key', $enrolment->secret_key)->firstOrFail()->student_data;

        $this->assertSame('', $data['state']);
    }

    public function test_the_language_field_is_renamed_for_the_enrol_payload(): void
    {
        $enrolment = $this->enrolment();

        // The old form posts main_language_home; Ams2EnrolArgs reads
        // main_language. Same AVETMISS field, same ASCL codes.
        $this->asAdmin()->submit($enrolment, ['main_language_home' => '1201']);

        $data = Ams2Enrolment::query()->where('secret_key', $enrolment->secret_key)->firstOrFail()->student_data;

        $this->assertSame('1201', $data['main_language']);
    }

    public function test_markup_is_stripped_from_submitted_values(): void
    {
        $enrolment = $this->enrolment();

        $this->asAdmin()->submit($enrolment, ['city_of_birth' => '<b>Perth</b><script>alert(1)</script>']);

        $data = Ams2Enrolment::query()->where('secret_key', $enrolment->secret_key)->firstOrFail()->student_data;

        /*
         * The script's CONTENTS go too. wp_strip_all_tags() removes script and
         * style blocks before stripping tags; strip_tags() alone would keep
         * "alert(1)" as text.
         */
        $this->assertSame('Perth', $data['city_of_birth']);
    }

    /*
    |--------------------------------------------------------------------------
    | Documents expected
    |--------------------------------------------------------------------------
    */

    public function test_expected_documents_leave_the_row_waiting(): void
    {
        $enrolment = $this->enrolment();

        $this->asAdmin()->submit($enrolment, ['doc_expected' => 'id_document,vet_transcript']);

        $row = Ams2Enrolment::query()->where('secret_key', $enrolment->secret_key)->firstOrFail();

        $this->assertSame('id_document,vet_transcript', $row->docs_expected);
        $this->assertFalse((bool) $row->docs_final);
    }

    public function test_no_documents_means_the_row_is_final_immediately(): void
    {
        $enrolment = $this->enrolment();

        $this->asAdmin()->submit($enrolment);

        $row = Ams2Enrolment::query()->where('secret_key', $enrolment->secret_key)->firstOrFail();

        // Nothing to wait for, so the documents step need not sit out the
        // 15-minute grace period.
        $this->assertSame('', $row->docs_expected);
        $this->assertTrue((bool) $row->docs_final);
    }

    public function test_unknown_document_types_are_ignored(): void
    {
        $enrolment = $this->enrolment();

        $this->asAdmin()->submit($enrolment, ['doc_expected' => 'id_document,passport,nonsense']);

        $row = Ams2Enrolment::query()->where('secret_key', $enrolment->secret_key)->firstOrFail();

        $this->assertSame('id_document', $row->docs_expected);
    }

    /*
    |--------------------------------------------------------------------------
    | Never twice
    |--------------------------------------------------------------------------
    */

    public function test_a_resubmit_returns_the_same_row(): void
    {
        $enrolment = $this->enrolment();

        $first = $this->asAdmin()->submit($enrolment);
        $second = $this->submit($enrolment);

        /*
         * A dropped connection means the browser retries. It must get the same
         * row back as a success, so the documents still attach and
         * "You will not be enrolled twice" is true.
         */
        $second->assertOk();
        $second->assertJsonPath('data.already_submitted', true);
        $second->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(1, Ams2Enrolment::query()->count());
    }

    public function test_a_resubmit_with_nothing_filled_in_still_returns_the_row(): void
    {
        $enrolment = $this->enrolment();
        $first = $this->asAdmin()->submit($enrolment);

        // The existing-row check runs before the field checks, as in the
        // reference, so a bare retry does not read as a validation failure.
        $response = $this->postJson(route('enrol.v2.submit'), [
            'secretKey' => $enrolment->secret_key,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.id', $first->json('data.id'));
    }

    /*
    |--------------------------------------------------------------------------
    | Required fields and the email
    |--------------------------------------------------------------------------
    */

    public function test_each_required_field_is_required(): void
    {
        foreach (['first_name', 'last_name', 'date_of_birth', 'email'] as $field) {
            $enrolment = $this->enrolment();

            $response = $this->asAdmin()->submit($enrolment, [$field => '  ']);

            $response->assertStatus(400);
            $response->assertJsonPath('data.code', 'missing_field');
            $response->assertJsonPath('success', false);

            $this->assertSame(
                0,
                Ams2Enrolment::query()->where('secret_key', $enrolment->secret_key)->count(),
                $field,
            );
        }
    }

    public function test_an_invalid_email_is_refused(): void
    {
        $enrolment = $this->enrolment();

        $response = $this->asAdmin()->submit($enrolment, ['email' => 'not-an-address']);

        $response->assertStatus(400);
        $response->assertJsonPath('data.code', 'bad_email');
        $this->assertSame(0, Ams2Enrolment::query()->count());
    }

    public function test_the_placeholder_is_never_accepted_as_an_email(): void
    {
        $enrolment = $this->enrolment();

        /*
         * The placeholder has no @ and is derived from the row id much later.
         * It must never be something a form can put in real_email.
         */
        $response = $this->asAdmin()->submit($enrolment, ['email' => 'processing100001hold.invalid']);

        $response->assertStatus(400);
        $response->assertJsonPath('data.code', 'bad_email');
    }

    /*
    |--------------------------------------------------------------------------
    | Links the endpoint refuses
    |--------------------------------------------------------------------------
    */

    public function test_an_invalid_key_is_refused(): void
    {
        $response = $this->asAdmin()->postJson(route('enrol.v2.submit'), [
            'secretKey' => 'not-a-key',
            'first_name' => 'Jane',
            'last_name' => 'Citizen',
            'date_of_birth' => '1990-01-01',
            'email' => 'jane@example.test',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('data.code', 'invalid_key');
    }

    public function test_an_expired_link_is_refused_with_its_own_code(): void
    {
        $enrolment = $this->enrolment(['enrolment_token_expires_at' => now()->subDay()]);

        $response = $this->asAdmin()->submit($enrolment);

        $response->assertStatus(403);
        $response->assertJsonPath('data.code', 'expired');
        $response->assertJsonPath('data.message', 'This enrolment link has expired. Your enrolment has not been submitted. Contact AMS Training for a new link.');
    }

    public function test_a_course_without_a_template_is_refused(): void
    {
        EnrolmentEmailTemplate::query()->delete();
        EnrolmentEmailTemplate::flushGateCache();

        $response = $this->asAdmin()->submit($this->enrolment());

        $response->assertStatus(403);
        $response->assertJsonPath('data.code', 'not_test_course');
    }

    public function test_a_link_already_used_on_the_old_form_is_refused(): void
    {
        $enrolment = $this->enrolment();

        EnrolmentSubmission::create([
            'enrolment_id' => $enrolment->id,
            'order_id' => $enrolment->order_id,
            'student_id' => $enrolment->student_id,
            'form_data' => ['first_name' => 'Jane'],
            'submitted_at' => now(),
            'api_status' => 'pending',
        ]);

        $response = $this->asAdmin()->submit($enrolment);

        $response->assertStatus(409);
        $response->assertJsonPath('data.code', 'already_submitted_old');
        $this->assertSame(0, Ams2Enrolment::query()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | The rollout switch covers this endpoint too
    |--------------------------------------------------------------------------
    */

    public function test_a_student_cannot_submit_while_the_switch_is_on(): void
    {
        $enrolment = $this->enrolment();

        /*
         * The reference checks AMS2_ADMIN_ONLY in the route and the form page
         * but in none of these endpoints, so a valid secret key was enough to
         * POST a v2 enrolment while the switch was on.
         */
        $response = $this->submit($enrolment);

        $response->assertStatus(403);
        $response->assertJsonPath('data.code', 'not_available');
        $this->assertSame(0, Ams2Enrolment::query()->count());
    }

    public function test_with_the_switch_off_a_student_can_submit(): void
    {
        config()->set('services.ams2.admin_only', false);

        $response = $this->submit($this->enrolment());

        $response->assertOk();
        $this->assertSame(1, Ams2Enrolment::query()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | A stale session
    |--------------------------------------------------------------------------
    */

    /**
     * Laravel's CSRF middleware short-circuits while running tests, so a stale
     * token cannot be produced through a test request. The mapping itself is
     * what matters, so it is exercised directly on the exception handler.
     */
    private function renderTokenMismatch(string $url): \Symfony\Component\HttpFoundation\Response
    {
        $request = \Illuminate\Http\Request::create($url, 'POST');
        $request->headers->set('Accept', 'application/json');

        return app(\Illuminate\Contracts\Debug\ExceptionHandler::class)->render(
            $request,
            new \Illuminate\Session\TokenMismatchException('CSRF token mismatch.'),
        );
    }

    public function test_a_stale_csrf_token_reports_the_nonce_code(): void
    {
        /*
         * The script retries once on this code after fetching a fresh token,
         * so a learner who left the form open past the session lifetime does
         * not lose what they typed. Laravel's own 419 page would be unreadable
         * to it.
         */
        $response = $this->renderTokenMismatch(route('enrol.v2.submit'));

        $this->assertSame(403, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true);

        $this->assertFalse($body['success']);
        $this->assertSame('nonce', $body['data']['code']);
        $this->assertSame('Your session has expired. Refresh the page and try again.', $body['data']['message']);
    }

    public function test_the_nonce_mapping_is_confined_to_the_v2_endpoints(): void
    {
        // Everything else keeps Laravel's own handling; this envelope is only
        // meaningful to the v2 form's script.
        $response = $this->renderTokenMismatch(url('/checkout'));

        $body = json_decode((string) $response->getContent(), true);

        $this->assertNotSame('nonce', $body['data']['code'] ?? null);
    }

    public function test_the_token_endpoint_returns_a_fresh_token(): void
    {
        $response = $this->asAdmin()->getJson(route('enrol.v2.token'));

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_the_token_endpoint_is_behind_the_switch(): void
    {
        $this->getJson(route('enrol.v2.token'))
            ->assertStatus(403)
            ->assertJsonPath('data.code', 'not_available');
    }
}
