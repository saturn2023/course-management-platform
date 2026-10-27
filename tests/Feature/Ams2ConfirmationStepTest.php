<?php

namespace Tests\Feature;

use App\Enrolment\V2\Ams2Status;
use App\Enrolment\V2\Steps\SendConfirmationStep;
use App\Mail\Ams2ConfirmationMail;
use App\Mail\EnrolmentLinkMail;
use App\Mail\PurchaserConfirmationMail;
use App\Models\Ams2Enrolment;
use App\Models\Course;
use App\Models\EnrolmentEmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Enrolment v2 stage 5: the confirmation email.
 *
 * The reference sends to the learner only - no CC, no BCC, no copy to info@ or
 * sales@ - from a hardcoded AMS address, and has no duplicate guard beyond the
 * status claim. The guard here is a deliberate improvement on that.
 */
class Ams2ConfirmationStepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');

        config()->set('services.ams2.mail_from', 'info@amstraining.com.au');
        config()->set('services.ams2.mail_from_name', 'AMS Training');
        config()->set('app.url', 'https://ams.test');
    }

    private function template(array $attributes = []): EnrolmentEmailTemplate
    {
        return EnrolmentEmailTemplate::create(array_merge([
            'name' => 'Confirmation',
            'course_id' => 32,
            'plan_id' => 38,
            'is_published' => true,
            'email_body' => '<p>Hello {first_name} {last_name}, welcome to {course_title}.</p>'
                .'<a href="{site_url}/courses">Courses</a>',
        ], $attributes));
    }

    private function row(array $attributes = []): Ams2Enrolment
    {
        return Ams2Enrolment::create(array_merge([
            'secret_key' => 'key-'.uniqid(),
            'course_id' => 32,
            'plan_id' => 38,
            'enrolment_id' => '42803',
            'course_title' => 'Work Safely At Heights',
            'real_email' => 'learner@example.test',
            'status' => Ams2Status::UPDATED,
            'student_data' => ['first_name' => 'Jane', 'last_name' => 'Citizen'],
        ], $attributes));
    }

    private function step(): SendConfirmationStep
    {
        return app(SendConfirmationStep::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Sending
    |--------------------------------------------------------------------------
    */

    public function test_the_confirmation_goes_to_the_learner_and_completes_the_row(): void
    {
        $this->template();
        $row = $this->row();

        $this->assertTrue($this->step()->handle($row));

        $fresh = $row->fresh();
        $this->assertSame(Ams2Status::COMPLETE, $fresh->status);
        $this->assertNotNull($fresh->email_sent_at);

        Mail::assertSent(
            Ams2ConfirmationMail::class,
            fn (Ams2ConfirmationMail $mail) => $mail->hasTo('learner@example.test'),
        );
    }

    public function test_nobody_else_is_copied_in(): void
    {
        $this->template();
        $this->step()->handle($this->row());

        // The reference sets no CC, no BCC, and no copy to info@ or sales@.
        Mail::assertSent(Ams2ConfirmationMail::class, function (Ams2ConfirmationMail $mail) {
            return ! $mail->hasCc('info@amstraining.com.au')
                && ! $mail->hasBcc('info@amstraining.com.au')
                && ! $mail->hasCc('sales@amstraining.com.au')
                && ! $mail->hasBcc('sales@amstraining.com.au');
        });
    }

    public function test_it_comes_from_the_ams_address_not_the_environment_mailer(): void
    {
        config()->set('mail.from.address', 'dev@twomoonsconsulting.com.au');

        $this->template();
        $this->step()->handle($this->row());

        // A learner's confirmation must come from AMS whatever this
        // environment sends its own mail as.
        Mail::assertSent(
            Ams2ConfirmationMail::class,
            fn (Ams2ConfirmationMail $mail) => $mail->hasFrom('info@amstraining.com.au', 'AMS Training'),
        );
    }

    public function test_the_subject_and_body_come_from_the_template(): void
    {
        $this->template();
        $this->step()->handle($this->row());

        Mail::assertSent(Ams2ConfirmationMail::class, function (Ams2ConfirmationMail $mail) {
            return $mail->subjectLine === 'Enrolment confirmation Work Safely At Heights'
                // Names are uppercased by the template, as the reference does.
                && str_contains($mail->body, 'Hello JANE CITIZEN')
                && str_contains($mail->body, 'welcome to Work Safely At Heights')
                && str_contains($mail->body, 'https://ams.test/courses');
        });
    }

    public function test_existing_attachments_are_included_and_missing_ones_skipped(): void
    {
        $template = $this->template();

        $present = $template->attachmentDirectory().'/Handbook.pdf';
        Storage::disk('local')->put($present, '%PDF-1.4');

        $template->update([
            'attachment_1_path' => $present,
            'attachment_2_path' => $template->attachmentDirectory().'/gone.pdf',
        ]);

        $this->step()->handle($this->row());

        // A missing file must not stop the learner being told they are enrolled.
        Mail::assertSent(
            Ams2ConfirmationMail::class,
            fn (Ams2ConfirmationMail $mail) => count($mail->attachmentPaths) === 1,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Never twice
    |--------------------------------------------------------------------------
    */

    public function test_a_second_run_does_not_resend(): void
    {
        $this->template();
        $row = $this->row();

        $this->step()->handle($row);
        $this->step()->handle($row->fresh());

        Mail::assertSentCount(1);
    }

    public function test_a_row_stuck_at_emailing_with_a_sent_timestamp_completes_without_resending(): void
    {
        $this->template();

        /*
         * The process died between the send and the status write. The
         * reference would park this after 30 minutes and resend on Resume.
         */
        $row = $this->row([
            'status' => Ams2Status::EMAILING,
            'email_sent_at' => now()->subMinutes(5),
        ]);

        $this->assertTrue($this->step()->handle($row));

        $this->assertSame(Ams2Status::COMPLETE, $row->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_a_row_at_emailing_with_no_timestamp_is_left_for_the_stuck_sweep(): void
    {
        $this->template();

        // No timestamp means we cannot tell whether it sent, so it must not be
        // claimed or resent here.
        $row = $this->row(['status' => Ams2Status::EMAILING]);

        $this->assertFalse($this->step()->handle($row));

        $this->assertSame(Ams2Status::EMAILING, $row->fresh()->status);
        Mail::assertNothingSent();
    }

    /*
    |--------------------------------------------------------------------------
    | No template
    |--------------------------------------------------------------------------
    */

    public function test_a_missing_template_parks_the_row_with_an_alert(): void
    {
        Log::spy();

        // No template at all for this pair.
        $row = $this->row();
        $this->assertFalse($this->step()->handle($row));

        $this->assertSame(Ams2Status::NEEDS_REVIEW, $row->fresh()->status);
        Mail::assertNothingSent();

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'no confirmation email to send')
                && str_contains(implode(' ', $context['details'] ?? []), 'course 32 / plan 38'))
            ->once();
    }

    public function test_an_unpublished_template_counts_as_missing(): void
    {
        $this->template(['is_published' => false]);

        $row = $this->row();
        $this->step()->handle($row);

        $this->assertSame(Ams2Status::NEEDS_REVIEW, $row->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_a_template_for_a_different_plan_is_not_used(): void
    {
        $this->template(['plan_id' => 99]);

        $row = $this->row();
        $this->step()->handle($row);

        $this->assertSame(Ams2Status::NEEDS_REVIEW, $row->fresh()->status);
        Mail::assertNothingSent();
    }

    /*
    |--------------------------------------------------------------------------
    | Subject fallback
    |--------------------------------------------------------------------------
    */

    public function test_an_empty_course_title_falls_back_to_the_local_course(): void
    {
        Course::create([
            'title' => 'Work Safely At Heights',
            'code' => 'RIIWHS204E',
            'status' => 'active',
            'price' => 110,
            'ams_enrolment_code' => '32',
            'ams_plan_id' => '38',
        ]);

        $this->template();
        $this->step()->handle($this->row(['course_title' => '']));

        // RTO Data does not always return a title; a learner-visible subject
        // with no course name in it is worse than diverging from the reference.
        Mail::assertSent(
            Ams2ConfirmationMail::class,
            fn (Ams2ConfirmationMail $mail) => $mail->subjectLine === 'Enrolment confirmation Work Safely At Heights',
        );
    }

    public function test_with_no_title_anywhere_the_subject_is_bare(): void
    {
        $this->template();
        $this->step()->handle($this->row(['course_title' => '']));

        Mail::assertSent(
            Ams2ConfirmationMail::class,
            fn (Ams2ConfirmationMail $mail) => $mail->subjectLine === 'Enrolment confirmation',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | The old flow must stay out of this
    |--------------------------------------------------------------------------
    */

    public function test_the_old_flow_confirmation_never_fires_for_a_v2_row(): void
    {
        $this->template();
        $this->step()->handle($this->row());

        /*
         * v2 rows live in their own tables and create no Enrolment record, so
         * nothing here can reach the old flow's mail.
         */
        Mail::assertNotSent(EnrolmentLinkMail::class);
        Mail::assertNotSent(PurchaserConfirmationMail::class);
        Mail::assertSentCount(1);
    }
}
