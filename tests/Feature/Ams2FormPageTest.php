<?php

namespace Tests\Feature;

use App\Enrolment\V2\Ams2DocumentLimit;
use App\Models\Enrolment;
use App\Models\EnrolmentEmailTemplate;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enrolment v2 stage 6: the form page itself.
 *
 * The fields are the same partial the v1 form renders, so what is worth testing
 * here is everything around them — the prefill, the config the browser reads,
 * and the absence of a form action, since this form is driven entirely by
 * JavaScript and must not be able to fall back to a plain POST.
 */
class Ams2FormPageTest extends TestCase
{
    use RefreshDatabase;

    private const COURSE_ID = 32;

    private const PLAN_ID = 38;

    protected function setUp(): void
    {
        parent::setUp();

        EnrolmentEmailTemplate::flushGateCache();
        config()->set('services.ams2.admin_only', true);
        config()->set('services.ams2.max_document_bytes', 10 * 1024 * 1024);

        EnrolmentEmailTemplate::create([
            'name' => 'Confirmation',
            'course_id' => self::COURSE_ID,
            'plan_id' => self::PLAN_ID,
            'is_published' => true,
            'email_body' => '<p>Hello {first_name}.</p>',
        ]);
    }

    private function enrolment(array $studentAttributes = []): Enrolment
    {
        $student = Student::create(array_merge([
            'first_name' => 'Jane',
            'last_name' => 'Citizen',
            'email' => 'jane'.uniqid().'@example.test',
        ], $studentAttributes));

        $order = Order::create(['student_id' => $student->id]);

        return Enrolment::create([
            'order_id' => $order->id,
            'student_id' => $student->id,
            'enrolment_token' => 'tok'.bin2hex(random_bytes(8)),
            'enrolment_token_expires_at' => now()->addDays(30),
            'secret_key' => bin2hex(random_bytes(16)),
            'secret_base_url' => 'https://ams.test/registration-form/?code='.self::COURSE_ID
                .'&plan='.self::PLAN_ID.'&company=ACME',
            'status' => 'link_created',
        ]);
    }

    private function openForm(Enrolment $enrolment): \Illuminate\Testing\TestResponse
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        return $this->get(route('enrol.v2.show', ['secretKey' => $enrolment->secret_key]));
    }

    /*
    |--------------------------------------------------------------------------
    | The page renders
    |--------------------------------------------------------------------------
    */

    public function test_the_form_renders_for_an_eligible_link(): void
    {
        $response = $this->openForm($this->enrolment());

        $response->assertOk();
        $response->assertViewIs('enrolments.v2.form');
    }

    public function test_it_renders_the_same_fields_as_the_old_form(): void
    {
        $response = $this->openForm($this->enrolment());

        // The shared partial, so these are the names the RTO Data payload is
        // built from. A rename here silently empties a field on enrol.
        foreach ([
            'first_name', 'last_name', 'date_of_birth', 'gender', 'usi',
            'city_of_birth', 'country_of_birth', 'main_language_home',
            'street_number', 'street_name', 'suburb', 'state', 'postcode',
            'school_level', 'currently_at_school', 'study_reason',
            'labour_force', 'disability', 'atsi_status',
            'id_document', 'vet_transcript',
        ] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }
    }

    public function test_every_section_is_present(): void
    {
        $response = $this->openForm($this->enrolment());

        foreach (['Personal', 'USI', 'Address', 'Contact', 'Education', 'Demography', 'Needs', 'Uploads', 'Declarations'] as $section) {
            $response->assertSee($section);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | No plain-POST fallback
    |--------------------------------------------------------------------------
    */

    public function test_the_form_has_no_action_so_it_cannot_post_anywhere(): void
    {
        $response = $this->openForm($this->enrolment());

        /*
         * The reference renames the old handler's ams_action field so a
         * no-JavaScript submit cannot reach it. There is nothing to rename
         * here: the form has no action at all.
         */
        $response->assertSee('<form class="ef-form" id="ams2-form" novalidate>', false);
        $response->assertDontSee('name="ams_action"', false);
        $response->assertDontSee(route('enrol.submit', 'x'), false);
    }

    public function test_it_warns_when_javascript_is_off(): void
    {
        $this->openForm($this->enrolment())
            ->assertSee('This form needs JavaScript.');
    }

    public function test_it_carries_a_status_region_for_the_script(): void
    {
        $this->openForm($this->enrolment())
            ->assertSee('id="ams2-status"', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Prefill
    |--------------------------------------------------------------------------
    */

    public function test_the_learner_is_prefilled_from_their_record(): void
    {
        $enrolment = $this->enrolment(['email' => 'jane.citizen@example.test']);
        $response = $this->openForm($enrolment);

        $response->assertSee('value="Jane"', false);
        $response->assertSee('value="Citizen"', false);
        $response->assertSee('value="jane.citizen@example.test"', false);
    }

    public function test_accents_are_stripped_from_the_prefilled_name(): void
    {
        $enrolment = $this->enrolment([
            'first_name' => 'José',
            'last_name' => 'Müller-Łódź',
        ]);

        $response = $this->openForm($enrolment);

        /*
         * The reference strips combining marks from the prefill, and the field
         * is readonly, so the stripped form is what reaches RTO Data.
         */
        $response->assertSee('value="Jose"', false);
        $response->assertDontSee('value="José"', false);

        // Ł is atomic in Unicode, so it has no combining mark to drop and
        // survives; the ó and ź do not. See the next test.
        $response->assertSee('value="Muller-Łodz"', false);
    }

    public function test_letters_with_no_combining_mark_survive_the_strip(): void
    {
        $enrolment = $this->enrolment([
            'first_name' => 'Søren',
            'last_name' => 'Straße',
        ]);

        $response = $this->openForm($enrolment);

        /*
         * This is the reference's behaviour, not a gap in the port: `clean()`
         * decomposes and drops combining marks, which does nothing to a letter
         * that is a single code point. The prefill is therefore NOT guaranteed
         * to be ASCII, and a learner named Søren still reaches RTO Data with
         * the ø. Transliterating further would risk sending a name that
         * matches neither their ID nor the USI registry.
         */
        $response->assertSee('value="Søren"', false);
        $response->assertSee('value="Straße"', false);
    }

    public function test_stripping_accents_does_not_touch_the_stored_student(): void
    {
        $enrolment = $this->enrolment(['first_name' => 'José']);

        $this->openForm($enrolment);

        $this->assertSame('José', $enrolment->student->fresh()->first_name);
    }

    /*
    |--------------------------------------------------------------------------
    | The config the browser reads
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    private function config(Enrolment $enrolment): array
    {
        $html = $this->openForm($enrolment)->getContent();

        $this->assertMatchesRegularExpression('/window\.AMS2 = (\{.*?\});/s', $html);
        preg_match('/window\.AMS2 = (\{.*?\});/s', $html, $matches);

        return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_the_config_carries_the_secret_key_and_course(): void
    {
        $enrolment = $this->enrolment();
        $config = $this->config($enrolment);

        $this->assertSame($enrolment->secret_key, $config['secretKey']);
        $this->assertSame(self::COURSE_ID, $config['courseId']);
        $this->assertSame(self::PLAN_ID, $config['planId']);
    }

    public function test_the_course_and_client_come_from_the_link(): void
    {
        $enrolment = $this->enrolment();
        $enrolment->update([
            'secret_base_url' => 'https://ams.test/registration-form/?code='
                .self::COURSE_ID.'&plan='.self::PLAN_ID.'&company=WIDGETCO',
        ]);

        $this->assertSame('WIDGETCO', $this->config($enrolment)['clientId']);
    }

    public function test_the_config_carries_a_csrf_token_and_the_success_url(): void
    {
        $config = $this->config($this->enrolment());

        $this->assertNotEmpty($config['csrfToken']);
        $this->assertSame(route('enrolment.completed'), $config['successUrl']);
    }

    public function test_the_config_never_carries_the_placeholder_address(): void
    {
        $config = $this->config($this->enrolment());

        // The placeholder is derived server-side when the row is enrolled; the
        // browser has no business knowing about it.
        $this->assertStringNotContainsString('hold.invalid', json_encode($config));
    }

    /*
    |--------------------------------------------------------------------------
    | The upload limit the form promises
    |--------------------------------------------------------------------------
    */

    public function test_the_config_limit_matches_what_the_server_will_accept(): void
    {
        $config = $this->config($this->enrolment());

        $this->assertSame(app(Ams2DocumentLimit::class)->bytes(), $config['maxBytes']);
    }

    public function test_the_limit_never_exceeds_what_php_allows(): void
    {
        $limit = app(Ams2DocumentLimit::class);

        /*
         * A file above upload_max_filesize is discarded by PHP before any of
         * this code runs, arriving as an empty $_FILES with no useful error.
         * Promising more than that in the form text is worse than offering
         * less.
         */
        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $ini = $this->iniBytes($setting);

            if ($ini > 0) {
                $this->assertLessThanOrEqual($ini, $limit->bytes(), $setting);
            }
        }
    }

    public function test_the_configured_ceiling_caps_the_limit(): void
    {
        config()->set('services.ams2.max_document_bytes', 1024);

        $this->assertSame(1024, app(Ams2DocumentLimit::class)->bytes());
        // Not "0MB", which is the honest rounding and useless to read.
        $this->assertSame('1KB', app(Ams2DocumentLimit::class)->label());
    }

    public function test_the_label_is_rounded_down_so_it_never_overstates(): void
    {
        config()->set('services.ams2.max_document_bytes', (int) (2.59 * 1048576));

        // 2.59MB must not be advertised as 2.6MB.
        $this->assertSame('2.5MB', app(Ams2DocumentLimit::class)->label());
    }

    public function test_a_whole_number_of_megabytes_has_no_decimal(): void
    {
        config()->set('services.ams2.max_document_bytes', 8 * 1024 * 1024);

        $this->assertSame('8MB', app(Ams2DocumentLimit::class)->label());
    }

    public function test_the_form_text_states_the_effective_limit(): void
    {
        $response = $this->openForm($this->enrolment());

        // The script rewrites the shared markup's size text, so the number the
        // learner reads comes from one place.
        $response->assertSee(app(Ams2DocumentLimit::class)->label(), false);
    }

    private function iniBytes(string $setting): int
    {
        $raw = trim((string) ini_get($setting));

        if ($raw === '') {
            return 0;
        }

        $value = (int) $raw;

        return match (strtoupper(substr($raw, -1))) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value,
        };
    }
}
