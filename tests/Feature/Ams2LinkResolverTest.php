<?php

namespace Tests\Feature;

use App\Enrolment\V2\Ams2LinkInfo;
use App\Enrolment\V2\Ams2LinkResolver;
use App\Enrolment\V2\Ams2OldFlow;
use App\Models\Course;
use App\Models\Enrolment;
use App\Models\EnrolmentSubmission;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Enrolment v2 stage 6: resolving an enrolment link.
 *
 * The distinction this file exists to protect: `expired` is not `invalid`. An
 * expired link is genuine, so the reference still routes it to v2 and lets the
 * form say so. Collapsing the two would hand an expired link back to the old
 * flow, where the student would read a different, wronger message.
 */
class Ams2LinkResolverTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private function resolver(): Ams2LinkResolver
    {
        return app(Ams2LinkResolver::class);
    }

    private function oldFlow(): Ams2OldFlow
    {
        return app(Ams2OldFlow::class);
    }

    private function enrolment(array $attributes = [], array $orderAttributes = [], string $email = 'jane@example.test'): Enrolment
    {
        $student = Student::create([
            'first_name' => 'Jane',
            'last_name' => 'Citizen',
            'email' => $email,
        ]);

        $order = Order::create(array_merge(['student_id' => $student->id], $orderAttributes));

        if (isset($orderAttributes['created_at'])) {
            $order->created_at = $orderAttributes['created_at'];
            $order->save();
        }

        return Enrolment::create(array_merge([
            'order_id' => $order->id,
            'student_id' => $student->id,
            'enrolment_token' => 'tok-'.uniqid(),
            'enrolment_token_expires_at' => now()->addDays(30),
            'secret_key' => self::KEY,
            'secret_base_url' => 'https://ams.test/registration-form/?code=32&plan=38',
            'status' => 'link_created',
        ], $attributes));
    }

    /*
    |--------------------------------------------------------------------------
    | A usable link
    |--------------------------------------------------------------------------
    */

    public function test_a_valid_link_resolves(): void
    {
        $enrolment = $this->enrolment();

        $info = $this->resolver()->forSecretKey(self::KEY);

        $this->assertTrue($info->isOk());
        $this->assertSame($enrolment->order_id, $info->orderId);
        $this->assertSame('32', $info->courseCode);
        $this->assertSame(32, $info->courseId);
        $this->assertSame(38, $info->planId);
    }

    public function test_the_prefill_comes_from_the_student_record(): void
    {
        $this->enrolment();

        $info = $this->resolver()->forSecretKey(self::KEY);

        $this->assertSame('Jane', $info->firstName);
        $this->assertSame('Citizen', $info->lastName);
        $this->assertSame('jane@example.test', $info->email);
    }

    public function test_the_course_and_plan_come_from_the_link_not_the_browser(): void
    {
        // Whatever the form posts, these are the values that count.
        $this->enrolment([
            'secret_base_url' => 'https://ams.test/registration-form/?code=77&plan=88&company=ACME',
        ]);

        $info = $this->resolver()->forSecretKey(self::KEY);

        $this->assertSame(77, $info->courseId);
        $this->assertSame(88, $info->planId);
        $this->assertSame('ACME', $info->clientId);
    }

    public function test_client_id_is_empty_when_the_link_carries_no_company(): void
    {
        // CreateEnrolmentJob builds secret_base_url with code and plan only, so
        // this is every link today.
        $this->enrolment();

        $this->assertSame('', $this->resolver()->forSecretKey(self::KEY)->clientId);
    }

    public function test_a_link_with_no_query_string_resolves_with_no_course(): void
    {
        $this->enrolment(['secret_base_url' => 'https://ams.test/registration-form/']);

        $info = $this->resolver()->forSecretKey(self::KEY);

        // Still a genuine link; it simply cannot be matched to a course, which
        // the v2 gate then refuses.
        $this->assertTrue($info->isOk());
        $this->assertSame(0, $info->courseId);
        $this->assertSame(0, $info->planId);
    }

    /*
    |--------------------------------------------------------------------------
    | Invalid links
    |--------------------------------------------------------------------------
    */

    public function test_a_malformed_key_is_invalid_without_touching_the_database(): void
    {
        $this->enrolment();

        DB::enableQueryLog();

        foreach (['', 'short', 'has-dashes-in-it-and-is-long-enough', str_repeat('a', 129)] as $key) {
            $this->assertTrue($this->resolver()->forSecretKey($key)->isInvalid(), 'key: '.$key);
        }

        $this->assertSame([], DB::getRawQueryLog());
        DB::disableQueryLog();
    }

    public function test_an_unknown_key_is_invalid(): void
    {
        $this->enrolment();

        $this->assertTrue(
            $this->resolver()->forSecretKey(str_repeat('b', 32))->isInvalid()
        );
    }

    public function test_a_link_with_no_order_is_invalid(): void
    {
        $enrolment = $this->enrolment();
        $enrolment->order_id = 0;

        // A non-zero order id is not enough on its own, and nor is a key that
        // is present and well formed.
        $this->assertTrue($this->resolver()->forEnrolment($enrolment)->isInvalid());
    }

    public function test_a_link_whose_order_no_longer_resolves_is_invalid(): void
    {
        $enrolment = $this->enrolment();
        $enrolment->order_id = 999999;

        $this->assertTrue($this->resolver()->forEnrolment($enrolment)->isInvalid());
    }

    public function test_no_enrolment_at_all_is_invalid(): void
    {
        $this->assertTrue($this->resolver()->forEnrolment(null)->isInvalid());
    }

    public function test_an_invalid_result_carries_nothing(): void
    {
        $info = Ams2LinkInfo::invalid();

        // So a caller that skipped the state check gets a course id of 0,
        // which the gate refuses, rather than a plausible-looking one.
        $this->assertSame(0, $info->orderId);
        $this->assertSame(0, $info->courseId);
        $this->assertSame(0, $info->planId);
        $this->assertSame('', $info->email);
    }

    /*
    |--------------------------------------------------------------------------
    | Expiry: both rules apply
    |--------------------------------------------------------------------------
    */

    public function test_an_expired_token_expires_the_link(): void
    {
        $this->enrolment(['enrolment_token_expires_at' => now()->subDay()]);

        $info = $this->resolver()->forSecretKey(self::KEY);

        $this->assertTrue($info->isExpired());
        $this->assertFalse($info->isInvalid());
    }

    public function test_an_order_older_than_125_days_expires_the_link(): void
    {
        // Token still valid, so only the reference's rule can catch this.
        $this->enrolment(
            ['enrolment_token_expires_at' => now()->addDays(30)],
            ['created_at' => now()->subDays(200)],
        );

        $this->assertTrue($this->resolver()->forSecretKey(self::KEY)->isExpired());
    }

    public function test_an_order_inside_125_days_with_a_live_token_is_ok(): void
    {
        $this->enrolment(
            ['enrolment_token_expires_at' => now()->addDays(30)],
            ['created_at' => now()->subDays(100)],
        );

        $this->assertTrue($this->resolver()->forSecretKey(self::KEY)->isOk());
    }

    public function test_a_link_with_no_token_expiry_falls_back_to_the_order_age(): void
    {
        $this->enrolment(
            ['enrolment_token_expires_at' => null],
            ['created_at' => now()->subDays(200)],
        );

        $this->assertTrue($this->resolver()->forSecretKey(self::KEY)->isExpired());
    }

    public function test_an_expired_link_still_reports_its_course_so_the_form_can_be_reached(): void
    {
        $this->enrolment(['enrolment_token_expires_at' => now()->subDay()]);

        $info = $this->resolver()->forSecretKey(self::KEY);

        // Routing sends an expired link to v2, which shows the expiry message
        // itself, so the course details have to survive expiry.
        $this->assertSame(32, $info->courseId);
        $this->assertSame(38, $info->planId);
    }

    /*
    |--------------------------------------------------------------------------
    | Course code to RTO Data course id
    |--------------------------------------------------------------------------
    */

    public function test_a_numeric_code_is_the_course_id(): void
    {
        $this->assertSame(32, $this->resolver()->courseIdFromCode('32'));
    }

    public function test_an_alphanumeric_code_is_looked_up_on_the_course(): void
    {
        Course::create([
            'title' => 'Work Safely At Heights',
            'code' => 'RIIWHS204E',
            'status' => 'active',
            'price' => 110,
            'ams_enrolment_code' => '32',
            'ams_plan_id' => '38',
        ]);

        $this->assertSame(32, $this->resolver()->courseIdFromCode('RIIWHS204E'));
        // The reference uppercases before mapping.
        $this->assertSame(32, $this->resolver()->courseIdFromCode('riiwhs204e'));
    }

    public function test_an_unmapped_code_is_zero(): void
    {
        $this->assertSame(0, $this->resolver()->courseIdFromCode('NOPE123'));
        $this->assertSame(0, $this->resolver()->courseIdFromCode(''));
        $this->assertSame(0, $this->resolver()->courseIdFromCode(null));
    }

    /*
    |--------------------------------------------------------------------------
    | Already on the old flow
    |--------------------------------------------------------------------------
    */

    private function submit(Enrolment $enrolment): void
    {
        EnrolmentSubmission::create([
            'enrolment_id' => $enrolment->id,
            'order_id' => $enrolment->order_id,
            'student_id' => $enrolment->student_id,
            'form_data' => ['first_name' => 'Jane'],
            'submitted_at' => now(),
            'api_status' => 'pending',
        ]);
    }

    public function test_a_key_submitted_on_the_old_form_is_detected(): void
    {
        $enrolment = $this->enrolment();
        $this->submit($enrolment);

        $this->assertTrue($this->oldFlow()->has(self::KEY));
    }

    public function test_a_key_never_submitted_is_not_in_the_old_flow(): void
    {
        $this->enrolment();

        $this->assertFalse($this->oldFlow()->has(self::KEY));
    }

    public function test_another_links_submission_does_not_count(): void
    {
        $other = $this->enrolment(['secret_key' => str_repeat('c', 32)], [], 'other@example.test');
        $this->submit($other);

        // The check is per key, not per student or per order.
        $this->enrolment();

        $this->assertFalse($this->oldFlow()->has(self::KEY));
        $this->assertTrue($this->oldFlow()->has(str_repeat('c', 32)));
    }

    public function test_an_empty_key_is_never_in_the_old_flow(): void
    {
        $enrolment = $this->enrolment(['secret_key' => null]);
        $this->submit($enrolment);

        // A row with no key must not be matched by a blank lookup.
        $this->assertFalse($this->oldFlow()->has(''));
        $this->assertFalse($this->oldFlow()->has(null));
    }
}
