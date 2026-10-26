<?php

namespace Tests\Feature;

use App\Enrolment\V2\Ams2EnrolArgs;
use App\Enrolment\V2\Ams2Processor;
use App\Enrolment\V2\Ams2Status;
use App\Enrolment\V2\Ams2Verifier;
use App\Models\Ams2Enrolment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Enrolment v2 stage 2: the real enrol call and the verify path.
 *
 * Every test fakes the HTTP client. Nothing here may reach RTO Data.
 */
class Ams2EnrolStepTest extends TestCase
{
    use RefreshDatabase;

    private const FLOOR = 500000;

    protected function setUp(): void
    {
        parent::setUp();

        // Credentials are shared with the old flow; only timeouts, the kill
        // switch and the floor are v2's own.
        config()->set('services.enrolment_api.base_url', 'https://rtodata.test/api/v2');
        config()->set('services.enrolment_api.public_key', 'pk_test');
        config()->set('services.enrolment_api.subdomain', 'amstraining');
        config()->set('services.enrolment_api.origin', 'https://ams.test');

        config()->set('services.ams2', [
            'enabled' => true,
            'connect_timeout' => 15,
            'timeout' => 90,
            'enrolment_floor' => self::FLOOR,
        ]);
    }

    private function row(array $attributes = []): Ams2Enrolment
    {
        return Ams2Enrolment::create(array_merge([
            'secret_key' => 'key-'.uniqid(),
            'course_id' => 32,
            'plan_id' => 38,
            'course_code' => 'RIIWHS204E',
            'course_title' => 'Work Safely At Heights',
            'real_email' => 'learner@example.test',
            'status' => Ams2Status::RECEIVED,
            'student_data' => [
                'first_name' => 'Test',
                'last_name' => 'Student',
                'date_of_birth' => '1990-01-01',
                'mobile_phone' => '0400000000',
                'atsi_status' => 'No',
            ],
        ], $attributes));
    }

    private function processor(): Ams2Processor
    {
        return app(Ams2Processor::class);
    }

    private function enrolSuccess(int $enrolmentId = 600001): array
    {
        return [
            'success' => true,
            'enrolment_id' => $enrolmentId,
            'enrolment_data' => [
                'learner_id' => 90210,
                'course_title' => 'Work Safely At Heights',
            ],
        ];
    }

    /**
     * The enrol payload is form-encoded into a single "values" field.
     *
     * @return array<string, mixed>
     */
    private function decodeEnrolValues(Request $request): array
    {
        parse_str((string) ($request->data()['values'] ?? ''), $values);

        return $values;
    }

    /*
    |--------------------------------------------------------------------------
    | Kill switch
    |--------------------------------------------------------------------------
    */

    public function test_nothing_is_sent_while_the_kill_switch_is_off(): void
    {
        config()->set('services.ams2.enabled', false);
        Http::fake();

        $row = $this->row();

        $this->assertFalse($this->processor()->run());

        Http::assertNothingSent();
        $this->assertSame(Ams2Status::RECEIVED, $row->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    public function test_a_successful_enrol_stores_the_rto_data_ids(): void
    {
        Http::fake(['*' => Http::response($this->enrolSuccess(), 200)]);

        $row = $this->row();
        $this->processor()->run();

        $fresh = $row->fresh();

        // The documents step is not built, so the row stops at enrolled.
        $this->assertSame(Ams2Status::ENROLLED, $fresh->status);
        $this->assertSame('600001', $fresh->enrolment_id);
        $this->assertSame('90210', $fresh->learner_id);
        $this->assertSame(0, $fresh->attempts);
    }

    public function test_the_payload_never_carries_a_real_email_or_contact_email(): void
    {
        Http::fake(['*' => Http::response($this->enrolSuccess(), 200)]);

        $row = $this->row();
        $this->processor()->run();

        Http::assertSent(function (Request $request) use ($row) {
            $values = $this->decodeEnrolValues($request);
            $placeholder = Ams2EnrolArgs::placeholderEmail($row->id);

            return $values['email'] === $placeholder
                && $values['other_email'] === $placeholder
                && ! array_key_exists('contact_email', $values)
                // The learner's real address must not appear anywhere.
                && ! str_contains((string) $request->body(), 'learner@example.test');
        });
    }

    public function test_the_placeholder_has_the_exact_wordpress_format(): void
    {
        $row = $this->row();

        // Deliberately not a valid address: no '@'. Reproduced exactly.
        $this->assertSame('processing'.$row->id.'hold.invalid', Ams2EnrolArgs::placeholderEmail($row->id));
    }

    public function test_the_payload_carries_the_real_name_uppercased(): void
    {
        Http::fake(['*' => Http::response($this->enrolSuccess(), 200)]);

        $this->row();
        $this->processor()->run();

        Http::assertSent(function (Request $request) {
            $values = $this->decodeEnrolValues($request);

            // Real names, AVETMISS-uppercased — not anonymised.
            return $values['first_name'] === 'TEST' && $values['last_name'] === 'STUDENT';
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Shared credentials, and refusing to call half-configured
    |--------------------------------------------------------------------------
    */

    public function test_the_call_uses_the_shared_enrolment_api_credentials(): void
    {
        Http::fake(['*' => Http::response($this->enrolSuccess(), 200)]);

        $this->row();
        $this->processor()->run();

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return str_starts_with($request->url(), 'https://rtodata.test/api/v2/enrol/')
                && $data['public_key'] === 'pk_test'
                && $data['subdomain'] === 'amstraining'
                && $request->header('Origin') === ['https://ams.test'];
        });
    }

    public function test_a_misconfigured_run_leaves_a_received_row_untouched(): void
    {
        config()->set('services.enrolment_api.public_key', '');
        Http::fake();

        $row = $this->row();
        $before = $row->updated_at;

        $this->assertFalse($this->processor()->run());

        $fresh = $row->fresh();

        /*
         * The processor stops before the lock and before claiming anything. If
         * it ran on, RTO Data would refuse the call, the enrol step would read
         * that as "no clear answer", and the row would be parked in verifying —
         * a missing .env value dressed up as an RTO Data outage.
         */
        $this->assertSame(Ams2Status::RECEIVED, $fresh->status);
        $this->assertSame(0, $fresh->attempts);
        $this->assertNull($fresh->verify_after);
        $this->assertEquals($before, $fresh->updated_at);
        Http::assertNothingSent();
    }

    public function test_a_missing_public_key_stops_the_processor_and_is_logged(): void
    {
        config()->set('services.enrolment_api.public_key', '');
        Http::fake();
        Log::spy();

        $this->row();
        $this->assertFalse($this->processor()->run());

        Http::assertNothingSent();

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'processor_not_configured')
                && in_array('ENROLMENT_API_PUBLIC_KEY', $context['missing'] ?? [], true))
            ->once();
    }

    public function test_a_missing_origin_stops_the_processor_and_is_logged(): void
    {
        config()->set('services.enrolment_api.origin', '');
        Http::fake();
        Log::spy();

        $this->row();
        $this->assertFalse($this->processor()->run());

        Http::assertNothingSent();

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'processor_not_configured')
                && in_array('ENROLMENT_API_ORIGIN', $context['missing'] ?? [], true))
            ->once();
    }

    public function test_the_command_names_the_missing_setting(): void
    {
        config()->set('services.enrolment_api.origin', '');

        $this->artisan('ams2:process')
            ->expectsOutputToContain('ENROLMENT_API_ORIGIN')
            ->assertFailed();
    }

    public function test_the_client_still_refuses_on_its_own(): void
    {
        // Backstop: anything calling the client directly must not get through.
        config()->set('services.enrolment_api.public_key', '');
        Http::fake();

        $result = app(\App\Enrolment\V2\Ams2ApiClient::class)->enrol(['course_id' => 32]);

        $this->assertFalse($result->sent);
        $this->assertStringContainsString('ENROLMENT_API_PUBLIC_KEY', $result->error);
        Http::assertNothingSent();
    }

    /*
    |--------------------------------------------------------------------------
    | Unclear answers go to verifying, never a retry
    |--------------------------------------------------------------------------
    */

    public function test_a_timeout_parks_the_row_for_verification(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));

        $row = $this->row();
        $this->processor()->run();

        $fresh = $row->fresh();

        $this->assertSame(Ams2Status::VERIFYING, $fresh->status);
        $this->assertNotNull($fresh->verify_after);
        $this->assertSame(0, $fresh->verify_checks);
    }

    public function test_a_server_error_parks_the_row_for_verification(): void
    {
        Http::fake(['*' => Http::response('gateway down', 502)]);

        $row = $this->row();
        $this->processor()->run();

        $this->assertSame(Ams2Status::VERIFYING, $row->fresh()->status);
    }

    public function test_a_refusal_is_retried_rather_than_verified(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'bad plan'], 200)]);

        $row = $this->row();
        $this->processor()->run();

        $fresh = $row->fresh();

        // A definite "no" means nothing was created, so retry is safe.
        $this->assertSame(Ams2Status::RECEIVED, $fresh->status);
        $this->assertSame(1, $fresh->attempts);
    }

    public function test_a_missing_required_field_parks_without_calling_rto_data(): void
    {
        Http::fake();

        $row = $this->row(['student_data' => ['first_name' => 'Test', 'last_name' => 'Student']]);
        $this->processor()->run();

        $this->assertSame(Ams2Status::NEEDS_REVIEW, $row->fresh()->status);
        $this->assertStringContainsString('date_of_birth', (string) $row->fresh()->error_message);
        Http::assertNothingSent();
    }

    /*
    |--------------------------------------------------------------------------
    | Verify
    |--------------------------------------------------------------------------
    */

    private function verifyingRow(array $attributes = []): Ams2Enrolment
    {
        return $this->row(array_merge([
            'status' => Ams2Status::VERIFYING,
            'verify_after' => now()->subMinute(),
        ], $attributes));
    }

    public function test_verify_links_an_active_enrolment_above_the_floor(): void
    {
        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => ['enrolment_id' => self::FLOOR + 1, 'status_id' => 0, 'learner_id' => 77],
        ], 200)]);

        $row = $this->verifyingRow();
        $this->processor()->run();

        $fresh = $row->fresh();

        $this->assertSame((string) (self::FLOOR + 1), $fresh->enrolment_id);
        $this->assertSame('77', $fresh->learner_id);
    }

    public function test_verify_finding_nothing_sends_the_row_back_to_be_enrolled(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        $row = $this->verifyingRow();
        app(Ams2Verifier::class)->verify($row);

        $fresh = $row->fresh();

        $this->assertSame(Ams2Status::RECEIVED, $fresh->status);
        $this->assertSame(1, $fresh->reenrol_count);
    }

    public function test_verify_will_not_link_an_enrolment_at_or_below_the_configured_floor(): void
    {
        /*
         * The computed floor is 0 here — this database has no prior rows. Only
         * AMS2_ENROLMENT_FLOOR knows about the enrolments WordPress already
         * created, so an active enrolment below it must be treated as the
         * learner's OLD enrolment, never linked to this row.
         */
        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => ['enrolment_id' => self::FLOOR - 1, 'status_id' => 0, 'learner_id' => 77],
        ], 200)]);

        $row = $this->verifyingRow();
        app(Ams2Verifier::class)->verify($row);

        $fresh = $row->fresh();

        $this->assertSame(Ams2Status::NEEDS_REVIEW, $fresh->status);
        $this->assertNull($fresh->enrolment_id);
        $this->assertStringContainsString('older open enrolment', (string) $fresh->error_message);
    }

    public function test_verify_refuses_to_run_without_a_configured_floor(): void
    {
        config()->set('services.ams2.enrolment_floor', null);
        Http::fake();

        $row = $this->verifyingRow();
        app(Ams2Verifier::class)->verify($row);

        $fresh = $row->fresh();

        $this->assertSame(Ams2Status::NEEDS_REVIEW, $fresh->status);
        $this->assertStringContainsString('AMS2_ENROLMENT_FLOOR', (string) $fresh->error_message);
        Http::assertNothingSent();
    }

    public function test_an_older_finished_enrolment_triggers_a_re_enrol(): void
    {
        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => ['enrolment_id' => self::FLOOR - 10, 'status_id' => 3, 'learner_id' => 77],
        ], 200)]);

        $row = $this->verifyingRow();
        app(Ams2Verifier::class)->verify($row);

        $this->assertSame(Ams2Status::RECEIVED, $row->fresh()->status);
        $this->assertSame(1, $row->fresh()->reenrol_count);
    }

    public function test_re_enrolling_is_capped(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        $row = $this->verifyingRow(['reenrol_count' => Ams2Verifier::MAX_REENROLS]);
        app(Ams2Verifier::class)->verify($row);

        $this->assertSame(Ams2Status::NEEDS_REVIEW, $row->fresh()->status);
    }

    public function test_a_failed_check_is_rearmed_rather_than_treated_as_not_found(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);

        $row = $this->verifyingRow();
        app(Ams2Verifier::class)->verify($row);

        $fresh = $row->fresh();

        // A failed check says nothing about whether the enrolment exists.
        $this->assertSame(Ams2Status::VERIFYING, $fresh->status);
        $this->assertSame(1, $fresh->verify_checks);
        $this->assertSame(0, $fresh->reenrol_count);
    }

    public function test_checks_give_up_after_the_maximum(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);

        $row = $this->verifyingRow(['verify_checks' => Ams2Verifier::MAX_CHECKS - 1]);
        app(Ams2Verifier::class)->verify($row);

        $this->assertSame(Ams2Status::NEEDS_REVIEW, $row->fresh()->status);
    }
}
