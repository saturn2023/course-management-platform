<?php

namespace App\Enrolment\V2;

use App\Models\Ams2Enrolment;
use App\Models\Enrolment;

/**
 * Decides whether an enrolment link belongs to v2.
 *
 * Port of ams2_route_enrol_link(), which hooks template_redirect at priority 5
 * so it runs ahead of the old /enrol/ handler. Every outcome is either "go to
 * v2" or "say nothing and let the old flow have it" — there is no third answer,
 * and nothing here ever refuses a learner outright.
 *
 * The ORDER of the checks is the whole design, and two places in it are easy to
 * get wrong:
 *
 *  1. A learner who already has a v2 row is sent back to v2 BEFORE the rollout
 *     switch and the old-flow check are consulted. The reference says why:
 *     "Already submitted through v2: always send them back there, even after a
 *     rollback." Handing them to the old form would enrol them a second time.
 *
 *  2. `expired` is not `invalid`, so an expired link is still routed to v2,
 *     where the form shows the expiry message itself. Routing never swallows an
 *     expiry.
 */
class Ams2EnrolmentRouter
{
    public function __construct(
        private Ams2LinkResolver $links,
        private Ams2OldFlow $oldFlow,
        private Ams2Gate $gate,
    ) {}

    /**
     * The v2 form URL for this enrolment token, or null to leave the request
     * with the old flow.
     */
    public function v2UrlFor(?string $token): ?string
    {
        $token = trim((string) $token);

        if ($token === '') {
            return null;
        }

        $enrolment = Enrolment::query()
            ->with('student')
            ->where('enrolment_token', $token)
            ->first();

        // An unknown token is the old route's to refuse, not ours.
        if (! $enrolment || blank($enrolment->secret_key)) {
            return null;
        }

        $key = (string) $enrolment->secret_key;

        if ($this->hasV2Row($key)) {
            return $this->formUrl($key);
        }

        if (! $this->gate->allowsCurrentRequest()) {
            return null;
        }

        // Started on the old form: leave it with the old flow.
        if ($this->oldFlow->has($key)) {
            return null;
        }

        $info = $this->links->forEnrolment($enrolment);

        if ($info->isInvalid()) {
            return null;
        }

        if (! $this->gate->usesV2($info->courseId, $info->planId)) {
            return null;
        }

        return $this->formUrl($key);
    }

    private function hasV2Row(string $secretKey): bool
    {
        return Ams2Enrolment::query()->where('secret_key', $secretKey)->exists();
    }

    private function formUrl(string $secretKey): string
    {
        return route('enrol.v2.show', ['secretKey' => $secretKey]);
    }
}
