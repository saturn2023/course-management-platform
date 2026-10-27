<?php

namespace App\Http\Controllers;

use App\Enrolment\V2\Ams2Gate;
use App\Enrolment\V2\Ams2LinkResolver;
use App\Enrolment\V2\Ams2OldFlow;
use App\Models\Ams2Enrolment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The v2 enrolment form page. Port of the ams2_enrolment_form() shortcode.
 *
 * The checks run in the reference's order, and the first one is first for a
 * reason: a learner who has already submitted through v2 is told so "whatever
 * the admin-only or course settings are". Were the rollout switch consulted
 * first, switching it on would start telling learners with a live v2 enrolment
 * that the page does not exist.
 *
 * Expiry is reported before validity, because an expired link is genuine and
 * deserves its own wording — "contact us for a new link" rather than "this link
 * is not valid".
 */
class Ams2EnrolmentController extends Controller
{
    public function __construct(
        private Ams2LinkResolver $links,
        private Ams2OldFlow $oldFlow,
        private Ams2Gate $gate,
    ) {}

    public function show(Request $request): View|Response|\Illuminate\Http\RedirectResponse
    {
        $key = trim((string) $request->query('secretKey', ''));

        // 1. Already submitted through v2. Before every other check.
        $existing = $key === ''
            ? null
            : Ams2Enrolment::query()->where('secret_key', $key)->first();

        if ($existing) {
            return $this->message(
                kind: 'ok',
                heading: 'Enrolment already submitted',
                message: 'Your enrolment has already been submitted.',
                reference: $existing->displayRef(),
                documentHelp: true,
            );
        }

        // 2. The rollout switch.
        if (! $this->gate->allowsCurrentRequest()) {
            return $this->message(
                kind: 'bad',
                heading: 'Page not available',
                message: 'This page is not available.',
            );
        }

        $info = $this->links->forSecretKey($key);

        // 3. Expired, which is not the same as invalid.
        if ($info->isExpired()) {
            return redirect()
                ->route('enrolment.failed')
                ->with('enrolment_failure_reason', 'expired');
        }

        // 4. Not usable at all.
        if (! $info->isOk()) {
            return redirect()->route('enrolment.failed');
        }

        // 5. Started on the old form.
        if ($this->oldFlow->has($key)) {
            return $this->message(
                kind: 'bad',
                heading: 'Enrolment already submitted',
                message: 'Your enrolment has already been submitted. Contact AMS Training if you need to change anything.',
            );
        }

        // 6. Not a course this form serves.
        if (! $this->gate->usesV2($info->courseId, $info->planId)) {
            return $this->message(
                kind: 'bad',
                heading: 'Link not set up for this form',
                message: 'This enrolment link is not set up for this form. Contact AMS Training.',
            );
        }

        /*
         * 7. The form itself, which is the next stage. Only an admin can reach
         * this today: the rollout switch above defaults to on.
         */
        return $this->message(
            kind: 'ok',
            heading: 'Enrolment v2',
            message: 'This link belongs to the v2 enrolment flow. The form itself is not built yet.',
        );
    }

    private function message(
        string $kind,
        string $heading,
        string $message,
        ?string $reference = null,
        bool $documentHelp = false,
    ): View {
        return view('enrolments.v2.message', [
            'kind' => $kind,
            'heading' => $heading,
            'message' => $message,
            'reference' => $reference,
            'documentHelp' => $documentHelp,
        ]);
    }
}
