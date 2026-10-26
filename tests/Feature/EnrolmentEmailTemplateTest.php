<?php

namespace Tests\Feature;

use App\Models\EnrolmentEmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Behaviour is ported from reference/wp-ams2.php — ams2_has_enrolment_email()
 * for the gate and ams2_step_email() for rendering. Where WordPress and the
 * brief disagreed, WordPress wins, so these assert uppercase names, a composed
 * subject with no subject field, and silently-skipped missing attachments.
 */
class EnrolmentEmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        EnrolmentEmailTemplate::flushGateCache();
    }

    private function template(array $attributes = []): EnrolmentEmailTemplate
    {
        return EnrolmentEmailTemplate::create(array_merge([
            'name' => 'Work Safely At Heights confirmation',
            'course_id' => 32,
            'plan_id' => 38,
            'is_published' => true,
            'email_body' => '<p>Hello {first_name} {last_name}, welcome to {course_title}.</p>',
        ], $attributes));
    }

    /*
    |--------------------------------------------------------------------------
    | The v2 gate
    |--------------------------------------------------------------------------
    */

    public function test_a_published_template_opens_the_gate_for_its_course_and_plan(): void
    {
        $this->template();

        $this->assertTrue(EnrolmentEmailTemplate::hasPublishedFor(32, 38));
    }

    public function test_an_unpublished_template_leaves_the_gate_shut(): void
    {
        $this->template(['is_published' => false]);

        $this->assertFalse(EnrolmentEmailTemplate::hasPublishedFor(32, 38));
    }

    public function test_the_gate_is_shut_for_a_pair_with_no_template(): void
    {
        $this->assertFalse(EnrolmentEmailTemplate::hasPublishedFor(99, 99));
    }

    public function test_the_gate_matches_on_both_course_and_plan(): void
    {
        $this->template();

        // Right course, wrong plan: these are different products.
        $this->assertFalse(EnrolmentEmailTemplate::hasPublishedFor(32, 99));
        $this->assertFalse(EnrolmentEmailTemplate::hasPublishedFor(99, 38));
    }

    /*
    |--------------------------------------------------------------------------
    | One published template per pair
    |--------------------------------------------------------------------------
    */

    public function test_a_second_published_template_for_the_same_pair_is_rejected(): void
    {
        $this->template();

        $this->expectException(ValidationException::class);

        $this->template(['name' => 'Duplicate']);
    }

    public function test_unpublished_duplicates_are_allowed(): void
    {
        $this->template();

        $this->template(['name' => 'Draft A', 'is_published' => false]);
        $this->template(['name' => 'Draft B', 'is_published' => false]);

        $this->assertSame(3, EnrolmentEmailTemplate::count());
    }

    public function test_publishing_a_draft_while_another_is_published_is_rejected(): void
    {
        $this->template();
        $draft = $this->template(['name' => 'Draft', 'is_published' => false]);

        $this->expectException(ValidationException::class);

        $draft->update(['is_published' => true]);
    }

    public function test_resaving_the_published_template_does_not_clash_with_itself(): void
    {
        $template = $this->template();

        $template->update(['name' => 'Renamed']);

        $this->assertSame('Renamed', $template->fresh()->name);
    }

    public function test_the_same_pair_can_be_republished_after_the_first_is_unpublished(): void
    {
        $first = $this->template();
        $second = $this->template(['name' => 'Replacement', 'is_published' => false]);

        $first->update(['is_published' => false]);
        $second->update(['is_published' => true]);

        $this->assertTrue($second->fresh()->is_published);
    }

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    */

    public function test_names_are_uppercased_and_the_course_title_substituted(): void
    {
        $rendered = $this->template()->render(
            ['first_name' => 'john', 'last_name' => 'Smith'],
            'Work Safely At Heights',
        );

        $this->assertSame(
            '<p>Hello JOHN SMITH, welcome to Work Safely At Heights.</p>',
            $rendered['body'],
        );
    }

    public function test_the_site_url_placeholder_is_replaced(): void
    {
        config()->set('app.url', 'https://ams.test');

        $template = $this->template([
            'email_body' => '<a href="{site_url}/courses">Courses</a>',
        ]);

        $this->assertSame(
            '<a href="https://ams.test/courses">Courses</a>',
            $template->render([], 'Course')['body'],
        );
    }

    public function test_a_trailing_slash_on_the_app_url_is_removed(): void
    {
        config()->set('app.url', 'https://ams.test/');

        $template = $this->template([
            'email_body' => '<a href="{site_url}/courses">Courses</a>',
        ]);

        // Otherwise every link in every template comes out with a double slash.
        $this->assertStringContainsString('https://ams.test/courses', $template->render([], 'Course')['body']);
        $this->assertStringNotContainsString('//courses', $template->render([], 'Course')['body']);
    }

    public function test_the_site_url_is_not_substituted_into_the_subject(): void
    {
        config()->set('app.url', 'https://ams.test');

        // Body only: the subject is composed from the course title alone.
        $rendered = $this->template()->render([], '{site_url}');

        $this->assertSame('Enrolment confirmation {site_url}', $rendered['subject']);
    }

    public function test_substituted_values_are_escaped(): void
    {
        $rendered = $this->template()->render(
            ['first_name' => '<script>', 'last_name' => 'x'],
            'Course & Plan',
        );

        $this->assertStringNotContainsString('<script>', $rendered['body']);
        $this->assertStringContainsString('&amp;', $rendered['body']);
    }

    public function test_the_subject_is_composed_rather_than_stored(): void
    {
        // WordPress has no subject field; it builds this literal at send time.
        $rendered = $this->template()->render([], 'Work Safely At Heights');

        $this->assertSame('Enrolment confirmation Work Safely At Heights', $rendered['subject']);
    }

    public function test_a_missing_course_title_does_not_leave_a_trailing_space_in_the_subject(): void
    {
        $rendered = $this->template()->render([], null);

        $this->assertSame('Enrolment confirmation', $rendered['subject']);
    }

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    public function test_a_template_with_no_attachments_still_renders(): void
    {
        $rendered = $this->template()->render([], 'Course');

        $this->assertSame([], $rendered['attachments']);
    }

    public function test_all_three_attachments_are_returned_in_slot_order(): void
    {
        Storage::fake('local');

        $template = $this->template();
        $paths = [];

        foreach ([1, 2, 3] as $slot) {
            $path = $template->attachmentDirectory()."/doc{$slot}.pdf";
            Storage::disk('local')->put($path, 'x');
            $paths["attachment_{$slot}_path"] = $path;
        }

        $template->update($paths);

        $this->assertCount(3, $template->fresh()->attachmentPaths());
    }

    public function test_a_deleted_attachment_is_skipped_and_logged(): void
    {
        Storage::fake('local');
        Log::spy();

        $template = $this->template();

        $present = $template->attachmentDirectory().'/handbook.pdf';
        Storage::disk('local')->put($present, 'x');

        $template->update([
            'attachment_1_path' => $present,
            'attachment_2_path' => $template->attachmentDirectory().'/deleted.pdf',
        ]);

        // The learner still gets their confirmation; the gap is not silent.
        $this->assertCount(1, $template->fresh()->attachmentPaths());

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => str_contains($message, 'attachment is missing'))
            ->once();
    }

    /*
    |--------------------------------------------------------------------------
    | Storage isolation
    |--------------------------------------------------------------------------
    */

    public function test_each_template_gets_its_own_attachment_directory(): void
    {
        $first = $this->template();
        $second = $this->template(['name' => 'Other', 'course_id' => 40, 'plan_id' => 41]);

        $this->assertNotSame($first->attachmentDirectory(), $second->attachmentDirectory());
        $this->assertStringStartsWith('enrolment-templates/', $first->attachmentDirectory());
    }

    public function test_a_storage_key_is_assigned_on_create(): void
    {
        $this->assertNotEmpty($this->template()->storage_key);
    }
}
