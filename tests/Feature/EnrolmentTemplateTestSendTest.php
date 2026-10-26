<?php

namespace Tests\Feature;

use App\Mail\EnrolmentTemplateTestMail;
use App\Models\Course;
use App\Models\EnrolmentEmailTemplate;
use App\Models\User;
use App\Services\EnrolmentTemplateTestSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * The "Send test" action. The rule that matters most here is that a preview can
 * only ever reach the signed-in administrator — a template preview escaping to
 * a learner would be worse than no preview at all.
 */
class EnrolmentTemplateTestSendTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email = 'admin@example.test'): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => 'secret',
            'is_admin' => true,
            'can_use_purchase_order' => false,
        ]);
    }

    private function template(array $attributes = []): EnrolmentEmailTemplate
    {
        return EnrolmentEmailTemplate::create(array_merge([
            'name' => 'Confirmation',
            'course_id' => 32,
            'plan_id' => 38,
            'is_published' => true,
            'email_body' => '<p>Hello {first_name} {last_name}, welcome to {course_title}.</p>',
        ], $attributes));
    }

    private function sender(): EnrolmentTemplateTestSender
    {
        return app(EnrolmentTemplateTestSender::class);
    }

    public function test_the_preview_goes_to_the_signed_in_admin(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $this->actingAs($admin);

        $recipient = $this->sender()->sendToCurrentUser($this->template());

        $this->assertSame($admin->email, $recipient);
        Mail::assertSent(
            EnrolmentTemplateTestMail::class,
            fn (EnrolmentTemplateTestMail $mail) => $mail->hasTo($admin->email),
        );
    }

    public function test_the_preview_goes_to_nobody_else(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $other = $this->admin('someone.else@example.test');
        $this->actingAs($admin);

        $this->sender()->sendToCurrentUser($this->template());

        Mail::assertSent(
            EnrolmentTemplateTestMail::class,
            fn (EnrolmentTemplateTestMail $mail) => ! $mail->hasTo($other->email)
                && ! $mail->hasCc($other->email)
                && ! $mail->hasBcc($other->email),
        );
    }

    public function test_a_guest_cannot_send_a_preview(): void
    {
        Mail::fake();

        $this->expectException(RuntimeException::class);

        try {
            $this->sender()->sendToCurrentUser($this->template());
        } finally {
            Mail::assertNothingSent();
        }
    }

    public function test_the_preview_uses_the_test_learner_and_the_real_course_title(): void
    {
        Mail::fake();

        Course::create([
            'title' => 'Work Safely At Heights',
            'code' => 'RIIWHS204E',
            'status' => 'active',
            'price' => 110,
            'ams_enrolment_code' => '32',
            'ams_plan_id' => '38',
        ]);

        $this->actingAs($this->admin());
        $this->sender()->sendToCurrentUser($this->template());

        Mail::assertSent(EnrolmentTemplateTestMail::class, function (EnrolmentTemplateTestMail $mail) {
            // Names are uppercased by the template, exactly as a real send does.
            return str_contains($mail->body, 'TEST STUDENT')
                && str_contains($mail->body, 'Work Safely At Heights')
                && $mail->subjectLine === 'Enrolment confirmation Work Safely At Heights';
        });
    }

    public function test_the_preview_resolves_the_site_url(): void
    {
        Mail::fake();
        config()->set('app.url', 'https://ams.test');

        $this->actingAs($this->admin());
        $this->sender()->sendToCurrentUser($this->template([
            'email_body' => '<a href="{site_url}/courses">Courses</a>',
        ]));

        Mail::assertSent(
            EnrolmentTemplateTestMail::class,
            fn (EnrolmentTemplateTestMail $mail) => str_contains($mail->body, 'https://ams.test/courses')
                && ! str_contains($mail->body, '{site_url}'),
        );
    }

    public function test_a_template_with_no_matching_local_course_still_sends(): void
    {
        Mail::fake();

        $this->actingAs($this->admin());
        $this->sender()->sendToCurrentUser($this->template(['course_id' => 999, 'plan_id' => 999]));

        Mail::assertSent(
            EnrolmentTemplateTestMail::class,
            fn (EnrolmentTemplateTestMail $mail) => $mail->subjectLine === 'Enrolment confirmation',
        );
    }

    public function test_existing_attachments_are_included(): void
    {
        Mail::fake();
        Storage::fake('local');

        $this->actingAs($this->admin());
        $template = $this->template();

        $path = $template->attachmentDirectory().'/Learner Handbook.pdf';
        Storage::disk('local')->put($path, 'x');
        $template->update(['attachment_1_path' => $path]);

        $this->sender()->sendToCurrentUser($template->fresh());

        Mail::assertSent(
            EnrolmentTemplateTestMail::class,
            fn (EnrolmentTemplateTestMail $mail) => count($mail->attachmentPaths) === 1,
        );
    }

    public function test_a_missing_attachment_does_not_stop_the_preview(): void
    {
        Mail::fake();
        Storage::fake('local');

        $this->actingAs($this->admin());
        $template = $this->template();
        $template->update([
            'attachment_1_path' => $template->attachmentDirectory().'/gone.pdf',
        ]);

        $this->sender()->sendToCurrentUser($template->fresh());

        Mail::assertSent(
            EnrolmentTemplateTestMail::class,
            fn (EnrolmentTemplateTestMail $mail) => $mail->attachmentPaths === [],
        );
    }

    public function test_the_preview_is_not_wrapped_in_the_ams_mail_layout(): void
    {
        Mail::fake();

        $this->actingAs($this->admin());
        $this->sender()->sendToCurrentUser($this->template());

        // A real confirmation sends the template's raw HTML, so the preview must
        // not gain the shared markdown header or its embedded logo.
        Mail::assertSent(EnrolmentTemplateTestMail::class, function (EnrolmentTemplateTestMail $mail) {
            $rendered = $mail->render();

            return ! str_contains($rendered, 'cid:ams-logo')
                && str_contains($rendered, 'Hello TEST STUDENT');
        });
    }
}
