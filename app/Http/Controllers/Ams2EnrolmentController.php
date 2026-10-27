<?php

namespace App\Http\Controllers;

use App\Enrolment\V2\Ams2DocumentLimit;
use App\Enrolment\V2\Ams2Gate;
use App\Enrolment\V2\Ams2LinkResolver;
use App\Enrolment\V2\Ams2OldFlow;
use App\Models\Ams2Enrolment;
use App\Models\Enrolment;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Normalizer;

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
        private Ams2DocumentLimit $limit,
    ) {}

    public function show(Request $request): View|Response|RedirectResponse
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

        // 7. The form.
        $enrolment = Enrolment::query()->with('student')->where('secret_key', $key)->first();

        if (! $enrolment) {
            // Resolved a moment ago, so this means it went away in between.
            return redirect()->route('enrolment.failed');
        }

        return view('enrolments.v2.form', [
            'student' => $this->prefill($enrolment->student),
            'languages' => config('enrolment_languages', []),
            'countries' => config('enrolment_countries', []),
            'limit' => $this->limit,
            'config' => [
                'secretKey' => $key,
                'csrfToken' => csrf_token(),
                'maxBytes' => $this->limit->bytes(),

                // The same string the form text shows, so the "maximum size
                // is X" message cannot contradict the label beside the field.
                'maxBytesLabel' => $this->limit->label(),

                'successUrl' => route('enrolment.completed'),

                'submitUrl' => route('enrol.v2.submit'),
                'uploadUrl' => route('enrol.v2.documents'),
                'finaliseUrl' => route('enrol.v2.finalise'),
                'tokenUrl' => route('enrol.v2.token'),

                /*
                 * Here for the browser's own use only. The server reads all
                 * three off the link again on submit and never trusts what was
                 * posted — "From the link, not the browser."
                 *
                 * Unlike the reference's shared form markup, this form renders
                 * no course_id, plan_id or client_id inputs at all, so there is
                 * normally nothing to strip. The submit handler still strips
                 * them, because a crafted POST can send anything.
                 */
                'courseId' => $info->courseId,
                'planId' => $info->planId,
                'clientId' => $info->clientId,
            ],
        ]);
    }

    /**
     * The learner as the form should show them.
     *
     * An unsaved copy, so the stored Student is untouched. The reference strips
     * accents from the prefilled name (`clean()`: NFD, then drop the combining
     * marks) and the field is readonly, so the stripped form is what gets
     * submitted and what reaches RTO Data. Ported as-is: USI and AVETMISS
     * matching is done on plain ASCII, and a name that does not match the USI
     * registry is rejected on enrol.
     */
    private function prefill(?Student $student): ?Student
    {
        if (! $student) {
            return null;
        }

        $copy = $student->replicate();
        $copy->first_name = $this->stripAccents($student->first_name);
        $copy->last_name = $this->stripAccents($student->last_name);

        return $copy;
    }

    /**
     * Drop accents, as the reference's `clean()` does: decompose to NFD and
     * remove the combining marks.
     *
     * This does NOT produce ASCII, and the reference does not either. Only
     * decomposable characters are affected — "José" becomes "Jose", but a
     * letter that is atomic in Unicode survives intact: Ł, Đ, ø and ß all pass
     * through, so "Müller-Łódź" becomes "Muller-Łodz" and "Søren" is unchanged.
     * If RTO Data or the USI registry needs plain ASCII, those names still fail
     * on enrol, and transliterating them further here would risk submitting a
     * name that matches neither the learner's document nor the registry.
     */
    private function stripAccents(?string $value): string
    {
        $value = (string) $value;

        /*
         * intl is not in composer.json, so it cannot be assumed in production.
         * Leaving a name alone beats mangling it.
         */
        if ($value === '' || ! class_exists(Normalizer::class)) {
            return $value;
        }

        $decomposed = Normalizer::normalize($value, Normalizer::FORM_D);

        if (! is_string($decomposed)) {
            return $value;
        }

        return preg_replace('/\p{Mn}/u', '', $decomposed) ?? $value;
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
