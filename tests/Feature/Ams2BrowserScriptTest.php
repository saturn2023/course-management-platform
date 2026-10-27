<?php

namespace Tests\Feature;

use App\Models\Enrolment;
use App\Models\EnrolmentEmailTemplate;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enrolment v2 stage 6: the browser script.
 *
 * PHPUnit cannot run the script, so this covers the two things that can still
 * break silently without it: the page delivering the script and the config it
 * needs, and the learner-facing wording, which is ported from the reference
 * word for word and is part of the behaviour rather than decoration. Several of
 * these sentences are the only thing telling a learner their enrolment is safe,
 * or where to email a document that would not upload.
 *
 * The script's own logic is exercised by hand in a browser; see the stage 6
 * checkpoint.
 */
class Ams2BrowserScriptTest extends TestCase
{
    use RefreshDatabase;

    private const COURSE_ID = 32;

    private const PLAN_ID = 38;

    private const SCRIPT = 'public/js/ams2-enrolment.js';

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

    private function openForm(): \Illuminate\Testing\TestResponse
    {
        $student = Student::create([
            'first_name' => 'Jane',
            'last_name' => 'Citizen',
            'email' => 'jane'.uniqid().'@example.test',
        ]);

        $order = Order::create(['student_id' => $student->id]);

        $enrolment = Enrolment::create([
            'order_id' => $order->id,
            'student_id' => $student->id,
            'enrolment_token' => 'tok'.bin2hex(random_bytes(8)),
            'enrolment_token_expires_at' => now()->addDays(30),
            'secret_key' => bin2hex(random_bytes(16)),
            'secret_base_url' => 'https://ams.test/registration-form/?code='.self::COURSE_ID.'&plan='.self::PLAN_ID,
            'status' => 'link_created',
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        return $this->get(route('enrol.v2.show', ['secretKey' => $enrolment->secret_key]));
    }

    private function script(): string
    {
        return (string) file_get_contents(base_path(self::SCRIPT));
    }

    /**
     * The script with JavaScript's own escaping undone, so a message is
     * compared as a learner reads it rather than as the file spells it — the
     * file holds "can\'t" inside a single-quoted literal.
     */
    private function scriptText(): string
    {
        return str_replace(["\\'", '\\"'], ["'", '"'], $this->script());
    }

    /*
    |--------------------------------------------------------------------------
    | The page delivers it
    |--------------------------------------------------------------------------
    */

    public function test_the_script_file_exists(): void
    {
        $this->assertFileExists(base_path(self::SCRIPT));
    }

    public function test_the_page_loads_the_script(): void
    {
        $this->openForm()->assertSee('js/ams2-enrolment.js', false);
    }

    public function test_the_script_comes_after_the_config_it_reads(): void
    {
        $html = $this->openForm()->getContent();

        $configAt = strpos($html, 'window.AMS2 =');
        $scriptAt = strpos($html, 'js/ams2-enrolment.js');

        $this->assertNotFalse($configAt);
        $this->assertNotFalse($scriptAt);
        $this->assertLessThan($scriptAt, $configAt, 'the script would read an undefined window.AMS2');
    }

    public function test_the_config_carries_every_url_the_script_uses(): void
    {
        $html = $this->openForm()->getContent();

        preg_match('/window\.AMS2 = (\{.*?\});/s', $html, $matches);
        $config = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);

        foreach (['submitUrl', 'uploadUrl', 'finaliseUrl', 'tokenUrl', 'successUrl'] as $key) {
            $this->assertNotEmpty($config[$key] ?? null, $key);
        }

        $this->assertSame(route('enrol.v2.submit'), $config['submitUrl']);
        $this->assertSame(route('enrol.v2.documents'), $config['uploadUrl']);
        $this->assertSame(route('enrol.v2.finalise'), $config['finaliseUrl']);
        $this->assertSame(route('enrol.v2.token'), $config['tokenUrl']);
    }

    public function test_the_size_message_matches_the_label_beside_the_field(): void
    {
        $html = $this->openForm()->getContent();

        preg_match('/window\.AMS2 = (\{.*?\});/s', $html, $matches);
        $config = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);

        // The script says "The maximum size is X"; the field text says X. They
        // must be the same string or the page contradicts itself.
        $this->assertSame(app(\App\Enrolment\V2\Ams2DocumentLimit::class)->label(), $config['maxBytesLabel']);
    }

    /*
    |--------------------------------------------------------------------------
    | The wording a learner depends on
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, array{0: string}>
     */
    public static function learnerMessages(): array
    {
        return array_map(fn (string $message) => [$message], [
            // Where to send a document that would not upload. Without this
            // sentence a learner has no way to finish their enrolment.
            'This is usually a brief connection problem. Press Try again. If it still does not work, press Continue and email the document to ',
            'sales@amstraining.com.au',

            // The one that stops a learner submitting twice out of fear.
            'Press Register to try again. You will not be enrolled twice.',

            // "We could not confirm" and "has not been submitted" describe
            // genuinely different situations and must stay distinct.
            '<strong>We could not confirm your enrolment.</strong><br>',
            '<strong>Your enrolment has not been submitted.</strong><br>',

            'Submitting your enrolment… This can take a minute. Please keep this page open.',
            'Uploading your documents… Please keep this page open.',
            '<strong>&#10003; Enrolment successfully submitted.</strong> Taking you to the confirmation page…',

            'This file type can\'t be uploaded. Please choose a PDF, JPG, PNG or WEBP file.',
            'We couldn\'t read this file. Save it to your Desktop or Downloads folder first, then choose it again.',
            'This file is empty. Please choose the file again or save a new copy first.',
            'Please choose a different file where shown before submitting.',

            'We could not reach the server. Check your internet connection.',
            'The server took too long to respond.',

            'Try again',
            'Continue',
            'Register',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('learnerMessages')]
    public function test_the_learner_wording_is_unchanged(string $message): void
    {
        $this->assertStringContainsString($message, $this->scriptText());
    }

    /*
    |--------------------------------------------------------------------------
    | Behaviour the script must not lose
    |--------------------------------------------------------------------------
    */

    public function test_it_keeps_the_reference_timings(): void
    {
        $script = $this->script();

        $this->assertStringContainsString('SUBMIT_TIMEOUT = 60000', $script);
        $this->assertStringContainsString('UPLOAD_TIMEOUT = 180000', $script);
        $this->assertStringContainsString('UPLOAD_ATTEMPTS = 4', $script);
        $this->assertStringContainsString('WATCHDOG_MS = 10 * 60 * 1000', $script);
        $this->assertStringContainsString('3000 * attempt', $script);
    }

    public function test_it_retries_only_the_statuses_the_reference_retries(): void
    {
        $this->assertStringContainsString('[429, 500, 502, 503, 504]', $this->script());
    }

    public function test_it_checks_file_contents_not_just_the_extension(): void
    {
        $script = $this->script();

        // The PDF and JPEG magic bytes, so a renamed .docx is caught before
        // anything is sent.
        $this->assertStringContainsString('0x25', $script);
        $this->assertStringContainsString('0xFF && b[1] === 0xD8', $script);
    }

    public function test_it_warns_before_leaving_mid_upload(): void
    {
        // A learner who closes the tab loses documents nothing will ask for
        // again.
        $this->assertStringContainsString("addEventListener('beforeunload'", $this->script());
    }

    public function test_it_strips_the_file_inputs_from_the_submit(): void
    {
        $script = $this->script();

        /*
         * The submit carries fields only. Leaving the file inputs in would send
         * every document twice and risk the whole request exceeding
         * post_max_size.
         */
        $this->assertStringContainsString("fd.delete('id_document')", $script);
        $this->assertStringContainsString("fd.delete('vet_transcript')", $script);
    }

    public function test_it_finalises_with_keepalive_so_it_survives_unload(): void
    {
        $this->assertStringContainsString('keepalive: true', $this->script());
    }
}
