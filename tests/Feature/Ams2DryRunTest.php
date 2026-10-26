<?php

namespace Tests\Feature;

use App\Enrolment\V2\Ams2DryRun;
use App\Enrolment\V2\Ams2EnrolArgs;
use App\Enrolment\V2\Ams2Status;
use App\Models\Ams2Enrolment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * `ams2:process --dry-run` exists to be read before anything is enabled, so the
 * things that matter are that it sends nothing, changes nothing, and does not
 * print the API key.
 */
class Ams2DryRunTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET_KEY = 'pk_live_do_not_print_me';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.enrolment_api.base_url', 'https://rtodata.test/api/v2');
        config()->set('services.enrolment_api.public_key', self::SECRET_KEY);
        config()->set('services.enrolment_api.subdomain', 'amstraining');
        config()->set('services.enrolment_api.origin', 'https://ams.test');

        config()->set('services.ams2', [
            'enabled' => false,
            'connect_timeout' => 15,
            'timeout' => 90,
            'enrolment_floor' => 500000,
        ]);
    }

    private function row(array $attributes = []): Ams2Enrolment
    {
        return Ams2Enrolment::create(array_merge([
            'secret_key' => 'key-'.uniqid(),
            'course_id' => 32,
            'plan_id' => 38,
            'real_email' => 'learner@example.test',
            'status' => Ams2Status::RECEIVED,
            'student_data' => [
                'first_name' => 'Test',
                'last_name' => 'Student',
                'date_of_birth' => '1990-01-01',
            ],
        ], $attributes));
    }

    public function test_a_dry_run_sends_nothing_and_changes_nothing(): void
    {
        Http::fake();

        $row = $this->row();

        $this->artisan('ams2:process', ['--dry-run' => true])->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(Ams2Status::RECEIVED, $row->fresh()->status);
    }

    public function test_a_dry_run_works_while_the_kill_switch_is_off(): void
    {
        Http::fake();
        $this->row();

        // Inspecting the payload before enabling the processor is the point.
        $this->artisan('ams2:process', ['--dry-run' => true])
            ->expectsOutputToContain('nothing is sent')
            ->assertSuccessful();
    }

    public function test_the_public_key_is_never_printed(): void
    {
        $row = $this->row();
        $json = app(Ams2DryRun::class)->toJson($row);

        $this->assertStringNotContainsString(self::SECRET_KEY, $json);
        $this->assertStringContainsString(Ams2DryRun::MASK, $json);
    }

    public function test_the_payload_shows_the_placeholder_and_not_the_real_email(): void
    {
        $row = $this->row();
        $json = app(Ams2DryRun::class)->toJson($row);

        $this->assertStringContainsString(Ams2EnrolArgs::placeholderEmail($row->id), $json);
        $this->assertStringNotContainsString('learner@example.test', $json);
        $this->assertStringNotContainsString('contact_email', $json);
    }

    public function test_the_dry_run_flags_a_row_that_would_be_rejected(): void
    {
        $row = $this->row(['student_data' => ['first_name' => 'Test', 'last_name' => 'Student']]);

        // Surfacing this before the live run is cheaper than parking the row.
        $this->assertSame(
            ['date_of_birth'],
            app(Ams2DryRun::class)->describe($row)['missing_required'],
        );
    }

    public function test_only_rows_waiting_to_be_enrolled_are_listed(): void
    {
        $this->row();
        $this->row(['status' => Ams2Status::ENROLLED]);
        $this->row(['status' => Ams2Status::NEEDS_REVIEW]);

        $this->assertCount(1, app(Ams2DryRun::class)->eligibleRows());
    }

    public function test_a_dry_run_with_no_waiting_rows_says_so(): void
    {
        $this->artisan('ams2:process', ['--dry-run' => true])
            ->expectsOutputToContain('no rows are waiting')
            ->assertSuccessful();
    }
}
