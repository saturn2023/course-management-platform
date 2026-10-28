<?php

namespace App\Http\Controllers;

use App\Enrolment\V2\Ams2DocumentReceiver;
use App\Enrolment\V2\Ams2DocumentRejected;
use App\Enrolment\V2\Ams2Gate;
use App\Enrolment\V2\Ams2LinkResolver;
use App\Enrolment\V2\Ams2OldFlow;
use App\Enrolment\V2\Ams2SubmissionWriter;
use App\Models\Ams2Enrolment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The three requests the v2 form makes, plus a token refresh.
 *
 * Port of ams2_submit(), ams2_upload(), ams2_finalise() and ams2_nonce(). The
 * JSON envelope is the reference's — {success, data:{code, message}} — because
 * the browser script is a port too and reads exactly those fields.
 *
 * The form saves the enrolment first and uploads each document afterwards, in
 * its own request. That is what makes a single flaky upload retryable without
 * resubmitting the enrolment, and it is why `docs_final` exists: the row is not
 * ready for the documents step until the browser says it has finished, or the
 * 15-minute grace period in SendDocumentsStep expires.
 *
 * Unlike the reference, every endpoint here is behind the rollout switch. The
 * reference checks AMS2_ADMIN_ONLY in the route and the form page but in none
 * of these three, so a valid secret key was enough to POST a v2 enrolment while
 * the switch was on.
 */
class Ams2SubmissionController extends Controller
{
    public function __construct(
        private Ams2LinkResolver $links,
        private Ams2OldFlow $oldFlow,
        private Ams2Gate $gate,
        private Ams2SubmissionWriter $writer,
        private Ams2DocumentReceiver $documents,
    ) {}

    /**
     * Stage 1: save the enrolment. No files.
     */
    public function submit(Request $request): JsonResponse
    {
        $key = trim((string) $request->input('secretKey', ''));

        if ($refusal = $this->refuseUnavailable()) {
            return $refusal;
        }

        $info = $this->links->forSecretKey($key);

        if ($info->isExpired()) {
            return $this->fail(
                'This enrolment link has expired. Your enrolment has not been submitted. Contact AMS Training for a new link.',
                'expired',
                403,
            );
        }

        if (! $info->isOk()) {
            return $this->fail(
                'This enrolment link is not valid. Your enrolment has not been submitted. Contact AMS Training for a new link.',
                'invalid_key',
                403,
            );
        }

        if (! $this->gate->usesV2($info->courseId, $info->planId)) {
            return $this->fail(
                'This enrolment link is not set up for this form. Your enrolment has not been submitted. Contact AMS Training.',
                'not_test_course',
                403,
            );
        }

        if ($this->oldFlow->has($key)) {
            return $this->fail(
                'Your enrolment has already been submitted. Contact AMS Training if you need to change anything.',
                'already_submitted_old',
                409,
            );
        }

        /*
         * Before the field checks, as in the reference: a resubmit after a
         * dropped connection has to return the original row so the documents
         * can still attach, even if the retry posts nothing useful. It is also
         * what makes "You will not be enrolled twice" true.
         */
        if ($existing = $this->writer->existing($key)) {
            return $this->ok(['id' => $existing->id, 'already_submitted' => true]);
        }

        $posted = $request->all();

        if ($this->writer->missingRequired($posted) !== []) {
            return $this->fail(
                'Some required details are missing. Check the form and try again. Your enrolment has not been submitted.',
                'missing_field',
            );
        }

        if ($this->writer->realEmail($posted) === null) {
            return $this->fail(
                'The email address is not valid. Check it and try again. Your enrolment has not been submitted.',
                'bad_email',
            );
        }

        $row = $this->writer->write($key, $info, $posted, $request->input('doc_expected'));

        return $this->ok(['id' => $row->id, 'already_submitted' => false]);
    }

    /**
     * Stage 2: one document per request.
     */
    public function documents(Request $request): JsonResponse
    {
        if ($refusal = $this->refuseUnavailable()) {
            return $refusal;
        }

        try {
            $type = $this->documents->assertKnownType($request->input('doc_type'));

            $row = $this->documents->rowFor(
                (int) $request->input('id'),
                trim((string) $request->input('secretKey', '')),
            );

            $this->documents->receive($row, $type, $request->file('document'));
        } catch (Ams2DocumentRejected $rejected) {
            Log::warning('[AMS2] document_rejected', [
                'code' => $rejected->failureCode,
                'doc_type' => $request->input('doc_type'),
                'row' => (int) $request->input('id'),
            ]);

            return $this->fail($rejected->learnerMessage, $rejected->failureCode, $rejected->status);
        }

        return $this->ok(['doc_type' => $type]);
    }

    /**
     * The browser reporting that it has no more documents to send.
     *
     * Always answers success, even when nothing matched. It is delivered by
     * fetch(keepalive) as the page unloads, where a failure cannot be retried
     * or seen — and a row that never gets this still proceeds once
     * SendDocumentsStep's 15-minute grace period expires.
     */
    public function finalise(Request $request): JsonResponse
    {
        if ($refusal = $this->refuseUnavailable()) {
            return $refusal;
        }

        $row = Ams2Enrolment::query()
            ->where('id', (int) $request->input('id'))
            ->where('secret_key', trim((string) $request->input('secretKey', '')))
            ->first();

        if ($row && ! $row->docs_final) {
            $row->update(['docs_final' => true]);

            Log::info('[AMS2] uploads_finalised', ['reference' => $row->displayRef()]);
        }

        return $this->ok([]);
    }

    /**
     * A fresh CSRF token. Port of ams2_nonce().
     *
     * The script asks for one and retries once when a request comes back with
     * the `nonce` code, so a learner who left the form open past the session
     * lifetime does not lose what they typed.
     */
    public function token(Request $request): JsonResponse
    {
        if ($refusal = $this->refuseUnavailable()) {
            return $refusal;
        }

        return $this->ok(['token' => csrf_token()]);
    }

    private function refuseUnavailable(): ?JsonResponse
    {
        if ($this->gate->allowsCurrentRequest()) {
            return null;
        }

        return $this->fail('This page is not available.', 'not_available', 403);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ok(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function fail(string $message, string $code, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
