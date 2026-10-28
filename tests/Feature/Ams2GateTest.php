<?php

namespace Tests\Feature;

use App\Enrolment\V2\Ams2Gate;
use App\Models\EnrolmentEmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enrolment v2 stage 6: who reaches v2, and for which courses.
 *
 * The two questions are independent on purpose. The rollout switch decides who
 * sees v2; the published template decides which courses it exists for. Neither
 * says anything about whether a link is valid.
 */
class Ams2GateTest extends TestCase
{
    use RefreshDatabase;

    private const COURSE_ID = 32;

    private const PLAN_ID = 38;

    protected function setUp(): void
    {
        parent::setUp();

        // Memoised for the life of a request, so it leaks between tests.
        EnrolmentEmailTemplate::flushGateCache();
    }

    private function gate(): Ams2Gate
    {
        return app(Ams2Gate::class);
    }

    private function template(array $attributes = []): EnrolmentEmailTemplate
    {
        return EnrolmentEmailTemplate::create(array_merge([
            'name' => 'Confirmation',
            'course_id' => self::COURSE_ID,
            'plan_id' => self::PLAN_ID,
            'is_published' => true,
            'email_body' => '<p>Hello {first_name}.</p>',
        ], $attributes));
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function student(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    /*
    |--------------------------------------------------------------------------
    | The rollout switch
    |--------------------------------------------------------------------------
    */

    public function test_the_switch_is_on_by_default(): void
    {
        // Unlike the reference, where AMS2_ADMIN_ONLY is false and real
        // students are already on v2.
        $this->assertTrue($this->gate()->adminOnly());
    }

    public function test_with_the_switch_on_a_guest_is_refused(): void
    {
        config()->set('services.ams2.admin_only', true);

        $this->assertFalse($this->gate()->allowsCurrentRequest());
    }

    public function test_with_the_switch_on_a_logged_in_non_admin_is_refused(): void
    {
        config()->set('services.ams2.admin_only', true);
        $this->actingAs($this->student());

        $this->assertFalse($this->gate()->allowsCurrentRequest());
    }

    public function test_with_the_switch_on_an_admin_is_allowed(): void
    {
        config()->set('services.ams2.admin_only', true);
        $this->actingAs($this->admin());

        $this->assertTrue($this->gate()->allowsCurrentRequest());
    }

    public function test_with_the_switch_off_everyone_is_allowed(): void
    {
        config()->set('services.ams2.admin_only', false);

        // A guest is the case that matters: a student following an enrolment
        // link is not logged in.
        $this->assertTrue($this->gate()->allowsCurrentRequest());

        $this->actingAs($this->student());
        $this->assertTrue($this->gate()->allowsCurrentRequest());
    }

    /*
    |--------------------------------------------------------------------------
    | Which courses are on v2
    |--------------------------------------------------------------------------
    */

    public function test_a_published_template_puts_the_pair_on_v2(): void
    {
        $this->template();

        $this->assertTrue($this->gate()->usesV2(self::COURSE_ID, self::PLAN_ID));
    }

    public function test_no_template_leaves_the_pair_on_the_old_flow(): void
    {
        $this->assertFalse($this->gate()->usesV2(self::COURSE_ID, self::PLAN_ID));
    }

    public function test_an_unpublished_template_does_not_count(): void
    {
        $this->template(['is_published' => false]);

        $this->assertFalse($this->gate()->usesV2(self::COURSE_ID, self::PLAN_ID));
    }

    public function test_the_plan_must_match_not_just_the_course(): void
    {
        $this->template();

        // Same course, different plan: a different product, and its learners
        // would have no confirmation email to receive.
        $this->assertFalse($this->gate()->usesV2(self::COURSE_ID, 99));
    }

    public function test_the_course_must_match_not_just_the_plan(): void
    {
        $this->template();

        $this->assertFalse($this->gate()->usesV2(99, self::PLAN_ID));
    }

    public function test_a_missing_course_or_plan_is_never_on_v2(): void
    {
        $this->template(['course_id' => 0, 'plan_id' => 0]);

        /*
         * A link whose query string carried no code or plan parses to zero.
         * The reference refuses those before looking for a template at all,
         * so a stray template keyed to 0/0 cannot open the flow.
         */
        $this->assertFalse($this->gate()->usesV2(0, 0));
        $this->assertFalse($this->gate()->usesV2(0, self::PLAN_ID));
        $this->assertFalse($this->gate()->usesV2(self::COURSE_ID, 0));
    }

    /*
    |--------------------------------------------------------------------------
    | The two questions are independent
    |--------------------------------------------------------------------------
    */

    public function test_the_switch_does_not_change_which_courses_use_v2(): void
    {
        $this->template();

        config()->set('services.ams2.admin_only', true);
        $this->assertTrue($this->gate()->usesV2(self::COURSE_ID, self::PLAN_ID));

        config()->set('services.ams2.admin_only', false);
        $this->assertTrue($this->gate()->usesV2(self::COURSE_ID, self::PLAN_ID));
    }

    public function test_an_admin_still_cannot_use_v2_for_a_course_without_a_template(): void
    {
        config()->set('services.ams2.admin_only', true);
        $this->actingAs($this->admin());

        $this->assertTrue($this->gate()->allowsCurrentRequest());
        $this->assertFalse($this->gate()->usesV2(self::COURSE_ID, self::PLAN_ID));
    }

    /*
    |--------------------------------------------------------------------------
    | The memo cache must not outlive a change to the templates
    |--------------------------------------------------------------------------
    */

    public function test_publishing_a_template_takes_effect_immediately(): void
    {
        $this->assertFalse($this->gate()->usesV2(self::COURSE_ID, self::PLAN_ID));

        // Whoever just published it must not be told the course is still off v2.
        $this->template();

        $this->assertTrue($this->gate()->usesV2(self::COURSE_ID, self::PLAN_ID));
    }

    public function test_unpublishing_a_template_takes_effect_immediately(): void
    {
        $template = $this->template();
        $this->assertTrue($this->gate()->usesV2(self::COURSE_ID, self::PLAN_ID));

        $template->update(['is_published' => false]);

        $this->assertFalse($this->gate()->usesV2(self::COURSE_ID, self::PLAN_ID));
    }

    public function test_deleting_a_template_takes_effect_immediately(): void
    {
        $template = $this->template();
        $this->assertTrue($this->gate()->usesV2(self::COURSE_ID, self::PLAN_ID));

        $template->delete();

        $this->assertFalse($this->gate()->usesV2(self::COURSE_ID, self::PLAN_ID));
    }
}
