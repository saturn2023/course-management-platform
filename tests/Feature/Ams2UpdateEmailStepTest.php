<?php

namespace Tests\Feature;

use App\Enrolment\V2\Ams2Failer;
use App\Enrolment\V2\Ams2Status;
use App\Enrolment\V2\Steps\UpdateEmailStep;
use App\Models\Ams2Enrolment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Enrolment v2 stage 4: replacing the placeholder with the learner's real email.
 *
 * The distinction worth holding onto: unlike enrol, a timeout here RETRIES
 * rather than going to the verify path, because writing the same email twice is
 * idempotent while enrolling twice is not.
 */
class Ams2UpdateEmailStepTest extends TestCase
{
    use RefreshDatabase;

    private const ENROLMENT_ID = '42803';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.enrolment_api.base_url', 'https://rtodata.test/api/v2');
        config()->set('services.enrolment_api.public_key', 'pk_test');
        config()->set('services.enrolment_api.subdomain', 'amstraining');
        config()->set('services.enrolment_api.origin', 'https://ams.test');
        config()->set('services.ams2.enabled', true);
    }

    private function row(array $attributes = []): Ams2Enrolment
    {
        return Ams2Enrolment::create(array_merge([
            'secret_key' => 'key-'.uniqid(),
            'course_id' => 32,
            'plan_id' => 38,
            'enrolment_id' => self::ENROLMENT_ID,
            'real_email' => 'learner@example.test',
            'status' => Ams2Status::DOCUMENTS_DONE,
            'student_data' => ['first_name' => 'Jane', 'last_name' => 'Citizen'],
        ], $attributes));
    }

    private function step(): UpdateEmailStep
    {
        return app(UpdateEmailStep::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function sentValues(Request $request): array
    {
        parse_str((string) ($request->data()['values'] ?? ''), $values);

        return $values;
    }

    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    public function test_a_successful_update_advances_the_row(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $row = $this->row(['attempts' => 2]);
        $this->assertTrue($this->step()->handle($row));

        $fresh = $row->fresh();

        $this->assertSame(Ams2Status::UPDATED, $fresh->status);
        $this->assertSame(0, $fresh->attempts);
        $this->assertNull($fresh->error_message);
    }

    public function test_the_real_email_is_sent_in_both_email_fields(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->step()->handle($this->row());

        Http::assertSent(function (Request $request) {
            $values = $this->sentValues($request);

            return $values['email'] === 'learner@example.test'
                && $values['other_email'] === 'learner@example.test';
        });
    }

    public function test_the_enrolment_id_is_sent_as_an_integer(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->step()->handle($this->row());

        // The reference casts, and carries a debug probe written to test this.
        Http::assertSent(fn (Request $request) => $request->data()['enrolment_id'] === 42803);
    }

    public function test_the_call_goes_to_the_slashed_update_endpoint(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->step()->handle($this->row());

        // The API 301s the unslashed form; requesting the final URL avoids it.
        Http::assertSent(fn (Request $request) => $request->url() === 'https://rtodata.test/api/v2/update/');
    }

    /*
    |--------------------------------------------------------------------------
    | Payload contains only what it should
    |--------------------------------------------------------------------------
    */

    public function test_the_payload_carries_only_the_allowed_keys(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->step()->handle($this->row());

        Http::assertSent(function (Request $request) {
            $outer = array_keys($request->data());
            $inner = array_keys($this->sentValues($request));

            sort($outer);
            sort($inner);

            return $outer === ['enrolment_id', 'public_key', 'subdomain', 'type', 'values']
                && $inner === ['email', 'other_email'];
        });
    }

    public function test_contact_email_is_stripped_even_when_nested_in_the_values(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        /*
         * contact_email is the EMERGENCY CONTACT's address; sending it blanks
         * that contact. The reference has a debug shortcut that passes it
         * nested inside the urlencoded values, where a top-level-only scrub
         * would never see it.
         */
        app(\App\Enrolment\V2\Ams2ApiClient::class)->update(
            identifiers: ['type' => 'enrolment', 'enrolment_id' => 42803, 'contact_email' => 'ice@example.test'],
            values: ['email' => 'a@b.test', 'contact_email' => '', 'other_email' => 'a@b.test'],
        );

        Http::assertSent(function (Request $request) {
            $inner = $this->sentValues($request);

            return ! array_key_exists('contact_email', $request->data())
                && ! array_key_exists('contact_email', $inner)
                && ! str_contains((string) $request->body(), 'contact_email');
        });
    }

    public function test_empty_values_are_stripped_so_nothing_is_blanked(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        app(\App\Enrolment\V2\Ams2ApiClient::class)->update(
            identifiers: ['type' => 'enrolment', 'enrolment_id' => 42803],
            values: ['email' => 'a@b.test', 'mobile_phone' => '', 'suburb' => null],
        );

        Http::assertSent(function (Request $request) {
            $inner = $this->sentValues($request);

            return $inner === ['email' => 'a@b.test'];
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Failure and timeout both retry
    |--------------------------------------------------------------------------
    */

    public function test_a_refusal_sends_the_row_back_to_be_retried(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'no'], 200)]);

        $row = $this->row();
        $this->assertFalse($this->step()->handle($row));

        $fresh = $row->fresh();

        $this->assertSame(Ams2Status::DOCUMENTS_DONE, $fresh->status);
        $this->assertSame(1, $fresh->attempts);
    }

    public function test_a_timeout_retries_rather_than_going_to_the_verify_path(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $row = $this->row();
        $this->step()->handle($row);

        $fresh = $row->fresh();

        /*
         * Writing the same email twice is harmless, so unlike enrol there is
         * nothing to verify before retrying.
         */
        $this->assertSame(Ams2Status::DOCUMENTS_DONE, $fresh->status);
        $this->assertNotSame(Ams2Status::VERIFYING, $fresh->status);
        $this->assertSame(1, $fresh->attempts);
    }

    public function test_an_invalid_stored_email_parks_the_row_without_calling_rto_data(): void
    {
        Http::fake();

        $row = $this->row(['real_email' => 'not-an-address']);
        $this->step()->handle($row);

        $this->assertSame(Ams2Status::NEEDS_REVIEW, $row->fresh()->status);
        Http::assertNothingSent();
    }

    /*
    |--------------------------------------------------------------------------
    | The alert has to name the placeholder
    |--------------------------------------------------------------------------
    */

    public function test_parking_alerts_that_rto_data_still_holds_the_placeholder(): void
    {
        Http::fake(['*' => Http::response(['success' => false], 200)]);
        Log::spy();

        // Last attempt, so this failure parks the row.
        $row = $this->row(['attempts' => Ams2Failer::MAX_ATTEMPTS - 1]);
        $this->step()->handle($row);

        $this->assertSame(Ams2Status::NEEDS_REVIEW, $row->fresh()->status);

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context = []) use ($row) {
                $details = implode(' ', $context['details'] ?? []);

                /*
                 * Whoever picks this up needs to know the address in RTO Data
                 * is undeliverable, and what to put back.
                 */
                return str_contains($message, 'still the placeholder')
                    && str_contains($details, $row->placeholderEmail())
                    && str_contains($details, 'learner@example.test')
                    && str_contains($details, self::ENROLMENT_ID);
            })
            ->once();
    }

    public function test_a_retryable_failure_does_not_raise_the_placeholder_alert(): void
    {
        Http::fake(['*' => Http::response(['success' => false], 200)]);
        Log::spy();

        // Still has attempts left, so nothing is stuck yet.
        $this->step()->handle($this->row());

        Log::shouldNotHaveReceived('error', [
            \Mockery::on(fn ($message) => str_contains((string) $message, 'still the placeholder')),
            \Mockery::any(),
        ]);
    }
}
