<?php

namespace Tests\Feature;

use App\Models\Ams2Enrolment;
use App\Models\Enrolment;
use App\Models\EnrolmentEmailTemplate;
use App\Models\EnrolmentSubmission;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Enrolment v2 stage 6: which form an enrolment link opens.
 *
 * Every decision is either "go to v2" or "say nothing and let the old flow have
 * it". Nothing here ever refuses a learner outright, so a test that expects a
 * 200 on the old form is asserting the fall-through, not an error.
 */
class Ams2RoutingTest extends TestCase
{
    use RefreshDatabase;

    private const COURSE_ID = 32;

    private const PLAN_ID = 38;

    protected function setUp(): void
    {
        parent::setUp();

        EnrolmentEmailTemplate::flushGateCache();

        // The switch is on by default; most tests here are about the routing
        // decision rather than the switch, so they run as an admin.
        config()->set('services.ams2.admin_only', true);
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

    private function enrolment(array $attributes = [], array $orderAttributes = []): Enrolment
    {
        $student = Student::create([
            'first_name' => 'Jane',
            'last_name' => 'Citizen',
            'email' => 'jane'.uniqid().'@example.test',
        ]);

        $order = Order::create(array_merge(['student_id' => $student->id], $orderAttributes));

        if (isset($orderAttributes['created_at'])) {
            $order->created_at = $orderAttributes['created_at'];
            $order->save();
        }

        return Enrolment::create(array_merge([
            'order_id' => $order->id,
            'student_id' => $student->id,
            'enrolment_token' => 'tok'.bin2hex(random_bytes(8)),
            'enrolment_token_expires_at' => now()->addDays(30),
            'secret_key' => bin2hex(random_bytes(16)),
            'secret_base_url' => 'https://ams.test/registration-form/?code='.self::COURSE_ID.'&plan='.self::PLAN_ID,
            'status' => 'link_created',
        ], $attributes));
    }

    private function asAdmin(): self
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        return $this;
    }

    private function v2Url(Enrolment $enrolment): string
    {
        return route('enrol.v2.show', ['secretKey' => $enrolment->secret_key]);
    }

    /*
    |--------------------------------------------------------------------------
    | Routed to v2
    |--------------------------------------------------------------------------
    */

    public function test_a_course_with_a_published_template_goes_to_v2(): void
    {
        $this->template();
        $enrolment = $this->enrolment();

        $this->asAdmin()
            ->get('/enrol/'.$enrolment->enrolment_token)
            ->assertRedirect($this->v2Url($enrolment));
    }

    public function test_the_redirect_is_not_cacheable(): void
    {
        $this->template();
        $enrolment = $this->enrolment();

        // A cached redirect would pin the learner to whichever side of the
        // decision they first saw.
        $response = $this->asAdmin()->get('/enrol/'.$enrolment->enrolment_token);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_an_expired_link_is_still_routed_to_v2(): void
    {
        $this->template();
        $enrolment = $this->enrolment(['enrolment_token_expires_at' => now()->subDay()]);

        /*
         * expired is not invalid. The reference routes it to v2 so the v2 page
         * can show the expiry wording; falling through would show the old
         * flow's generic refusal instead.
         */
        $this->asAdmin()
            ->get('/enrol/'.$enrolment->enrolment_token)
            ->assertRedirect($this->v2Url($enrolment));
    }

    /*
    |--------------------------------------------------------------------------
    | Left with the old flow
    |--------------------------------------------------------------------------
    */

    public function test_a_course_with_no_template_stays_on_the_old_flow(): void
    {
        $enrolment = $this->enrolment();

        $this->asAdmin()
            ->get('/enrol/'.$enrolment->enrolment_token)
            ->assertOk()
            ->assertViewIs('enrolments.registration-form');
    }

    public function test_a_template_for_another_plan_stays_on_the_old_flow(): void
    {
        $this->template(['plan_id' => 99]);
        $enrolment = $this->enrolment();

        $this->asAdmin()
            ->get('/enrol/'.$enrolment->enrolment_token)
            ->assertOk()
            ->assertViewIs('enrolments.registration-form');
    }

    public function test_an_unpublished_template_stays_on_the_old_flow(): void
    {
        $this->template(['is_published' => false]);
        $enrolment = $this->enrolment();

        $this->asAdmin()
            ->get('/enrol/'.$enrolment->enrolment_token)
            ->assertOk()
            ->assertViewIs('enrolments.registration-form');
    }

    public function test_a_link_already_used_on_the_old_form_stays_there(): void
    {
        $this->template();
        $enrolment = $this->enrolment();

        EnrolmentSubmission::create([
            'enrolment_id' => $enrolment->id,
            'order_id' => $enrolment->order_id,
            'student_id' => $enrolment->student_id,
            'form_data' => ['first_name' => 'Jane'],
            'submitted_at' => now(),
            'api_status' => 'pending',
        ]);

        /*
         * Started on the old form: leave it with the old flow. What the old
         * route then does with it is its own business — it refuses on
         * enrolment_completed_at, which a submission row alone does not set —
         * so what matters here is only that v2 did not take the request.
         */
        $this->asAdmin()
            ->get('/enrol/'.$enrolment->enrolment_token)
            ->assertOk()
            ->assertViewIs('enrolments.registration-form');
    }

    public function test_a_link_marked_used_on_the_old_form_stays_there(): void
    {
        $this->template();
        $enrolment = $this->enrolment(['enrolment_completed_at' => now()->subDay()]);

        // Falls through, and the old route refuses it as already used.
        $this->asAdmin()
            ->get('/enrol/'.$enrolment->enrolment_token)
            ->assertRedirect('/enrolment-not-successful');
    }

    public function test_a_link_with_no_resolvable_order_stays_on_the_old_flow(): void
    {
        $this->template();
        $enrolment = $this->enrolment();

        // Cannot be trusted, so it is not promoted to v2.
        Order::query()->whereKey($enrolment->order_id)->delete();

        $this->asAdmin()
            ->get('/enrol/'.$enrolment->enrolment_token)
            ->assertRedirect('/enrolment-not-successful');
    }

    public function test_a_link_whose_query_string_names_no_course_stays_on_the_old_flow(): void
    {
        $this->template();
        $enrolment = $this->enrolment(['secret_base_url' => 'https://ams.test/registration-form/']);

        $this->asAdmin()
            ->get('/enrol/'.$enrolment->enrolment_token)
            ->assertOk()
            ->assertViewIs('enrolments.registration-form');
    }

    public function test_an_unknown_token_is_left_to_the_old_route_to_refuse(): void
    {
        $this->template();

        $this->asAdmin()
            ->get('/enrol/nope')
            ->assertRedirect('/enrolment-not-successful');
    }

    /*
    |--------------------------------------------------------------------------
    | The rollout switch
    |--------------------------------------------------------------------------
    */

    public function test_with_the_switch_on_a_student_stays_on_the_old_flow(): void
    {
        $this->template();
        $enrolment = $this->enrolment();

        // Not logged in, which is every real student.
        $this->get('/enrol/'.$enrolment->enrolment_token)
            ->assertOk()
            ->assertViewIs('enrolments.registration-form');
    }

    public function test_with_the_switch_off_a_student_goes_to_v2(): void
    {
        config()->set('services.ams2.admin_only', false);

        $this->template();
        $enrolment = $this->enrolment();

        $this->get('/enrol/'.$enrolment->enrolment_token)
            ->assertRedirect($this->v2Url($enrolment));
    }

    /*
    |--------------------------------------------------------------------------
    | A learner who already has a v2 row
    |--------------------------------------------------------------------------
    */

    private function v2Row(Enrolment $enrolment): Ams2Enrolment
    {
        return Ams2Enrolment::create([
            'secret_key' => $enrolment->secret_key,
            'order_id' => $enrolment->order_id,
            'course_id' => self::COURSE_ID,
            'plan_id' => self::PLAN_ID,
            'real_email' => 'jane@example.test',
            'student_data' => ['first_name' => 'Jane', 'last_name' => 'Citizen'],
        ]);
    }

    public function test_an_existing_v2_row_goes_back_to_v2_even_for_a_student(): void
    {
        config()->set('services.ams2.admin_only', true);

        $this->template();
        $enrolment = $this->enrolment();
        $this->v2Row($enrolment);

        /*
         * "Always send them back there, even after a rollback." Handing a
         * learner with a live v2 enrolment to the old form would enrol them a
         * second time.
         */
        $this->get('/enrol/'.$enrolment->enrolment_token)
            ->assertRedirect($this->v2Url($enrolment));
    }

    public function test_an_existing_v2_row_goes_back_to_v2_without_a_template(): void
    {
        // The template may have been unpublished since they submitted.
        $enrolment = $this->enrolment();
        $this->v2Row($enrolment);

        $this->get('/enrol/'.$enrolment->enrolment_token)
            ->assertRedirect($this->v2Url($enrolment));
    }

    public function test_an_existing_v2_row_goes_back_to_v2_even_when_expired(): void
    {
        $enrolment = $this->enrolment(['enrolment_token_expires_at' => now()->subDay()]);
        $this->v2Row($enrolment);

        $this->get('/enrol/'.$enrolment->enrolment_token)
            ->assertRedirect($this->v2Url($enrolment));
    }

    /*
    |--------------------------------------------------------------------------
    | POST is never redirected
    |--------------------------------------------------------------------------
    */

    public function test_a_post_to_the_old_endpoint_is_not_redirected_to_v2(): void
    {
        // The old route dispatches its API job; this test is about routing.
        Bus::fake();

        $this->template();
        $enrolment = $this->enrolment();

        /*
         * A POST comes from someone who already has the old form filled in.
         * Redirecting would throw their answers away and protect nothing: they
         * are not yet recorded as being in the old flow.
         */
        $this->asAdmin()
            ->post('/enrol/'.$enrolment->enrolment_token, ['date_of_birth' => '1990-01-01'])
            ->assertRedirect('/enrolment-completed');

        $this->assertSame(1, EnrolmentSubmission::query()->count());
        $this->assertSame(0, Ams2Enrolment::query()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | The v2 page re-checks everything
    |--------------------------------------------------------------------------
    */

    public function test_the_v2_page_tells_a_learner_who_already_submitted(): void
    {
        $this->template();
        $enrolment = $this->enrolment();
        $row = $this->v2Row($enrolment);

        $response = $this->get($this->v2Url($enrolment));

        $response->assertOk();
        $response->assertSee('Your enrolment has already been submitted.');
        // The operator-facing reference, offset from the raw id.
        $response->assertSee($row->displayRef());
    }

    public function test_the_v2_page_is_not_available_to_a_student_while_the_switch_is_on(): void
    {
        $this->template();
        $enrolment = $this->enrolment();

        $this->get($this->v2Url($enrolment))
            ->assertOk()
            ->assertSee('This page is not available.');
    }

    public function test_the_v2_page_reports_an_expired_link_as_expired(): void
    {
        $this->template();
        $enrolment = $this->enrolment(['enrolment_token_expires_at' => now()->subDay()]);

        $this->asAdmin()
            ->get($this->v2Url($enrolment))
            ->assertRedirect('/enrolment-not-successful');

        $this->asAdmin()
            ->followingRedirects()
            ->get($this->v2Url($enrolment))
            ->assertSee('This enrolment link has expired.');
    }

    public function test_the_v2_page_sends_an_invalid_link_to_the_generic_refusal(): void
    {
        $this->asAdmin()
            ->followingRedirects()
            ->get(route('enrol.v2.show', ['secretKey' => 'not-a-key']))
            ->assertSee('This enrolment link is invalid, expired, or has already been completed.');
    }

    public function test_the_v2_page_refuses_a_link_already_used_on_the_old_form(): void
    {
        $this->template();
        $enrolment = $this->enrolment(['enrolment_completed_at' => now()->subDay()]);

        $this->asAdmin()
            ->get($this->v2Url($enrolment))
            ->assertOk()
            ->assertSee('Your enrolment has already been submitted.');
    }

    public function test_the_v2_page_refuses_a_course_it_does_not_serve(): void
    {
        $enrolment = $this->enrolment();

        $this->asAdmin()
            ->get($this->v2Url($enrolment))
            ->assertOk()
            ->assertSee('This enrolment link is not set up for this form.');
    }
}
