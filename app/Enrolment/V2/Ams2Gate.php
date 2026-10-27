<?php

namespace App\Enrolment\V2;

use App\Models\EnrolmentEmailTemplate;
use Illuminate\Support\Facades\Auth;

/**
 * Who reaches the v2 enrolment flow, and for which courses.
 *
 * Two independent questions, both consulted at every entry point — the route,
 * the form page and each of the POST endpoints. They live here together so the
 * answers cannot drift between those call sites, which is exactly what happened
 * in WordPress: `AMS2_ADMIN_ONLY` is checked in `ams2_route_enrol_link()` and
 * `ams2_enrolment_form()` but in none of `ams2_submit()`, `ams2_upload()` or
 * `ams2_finalise()`, so anyone holding a valid secret key could POST a v2
 * enrolment while the switch was on. Closing that is a deliberate divergence.
 *
 * Neither question decides whether a link is VALID — that is
 * Ams2LinkResolver's job — and neither ever blocks a learner who already has a
 * v2 row. Such a learner is sent back to v2 before either question is asked,
 * because handing them to the old form would enrol them a second time.
 */
class Ams2Gate
{
    /**
     * Whether the staged-rollout switch is on.
     */
    public function adminOnly(): bool
    {
        return (bool) config('services.ams2.admin_only', true);
    }

    /**
     * Whether whoever is making this request may reach v2 at all.
     *
     * With the switch off this is true for everyone, students included. With it
     * on, only a logged-in admin passes and every student falls through to the
     * old flow, which stays the live path.
     */
    public function allowsCurrentRequest(): bool
    {
        if (! $this->adminOnly()) {
            return true;
        }

        return Auth::user()?->isAdmin() === true;
    }

    /**
     * Whether this course/plan pair runs on v2.
     *
     * Port of ams2_is_test_course() with AMS2_ALL_COURSES true, which is its
     * live value: the pair is on v2 precisely when it has a published Enrolment
     * Email template. The hardcoded AMS2_TEST_COURSES list is dead in the
     * reference and is not ported.
     *
     * A pair with no template stays on the old flow, so there is no such thing
     * as a v2 enrolment that cannot be confirmed by email.
     */
    public function usesV2(int $courseId, int $planId): bool
    {
        if ($courseId <= 0 || $planId <= 0) {
            return false;
        }

        return EnrolmentEmailTemplate::hasPublishedFor($courseId, $planId);
    }
}
