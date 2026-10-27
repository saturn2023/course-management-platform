<?php

namespace App\Enrolment\V2\Steps;

use App\Enrolment\V2\Ams2ApiClient;
use App\Enrolment\V2\Ams2Claim;
use App\Enrolment\V2\Ams2EnrolArgs;
use App\Enrolment\V2\Ams2Failer;
use App\Enrolment\V2\Ams2Status;
use App\Enrolment\V2\Ams2Verifier;
use App\Models\Ams2Enrolment;
use Illuminate\Support\Facades\Log;

/**
 * Creates the enrolment in RTO Data. Port of ams2_step_enrol().
 *
 * Three outcomes, and the difference between the last two is the whole point:
 *
 *  - created        -> store the enrolment ID and advance
 *  - refused        -> a real "no" from RTO Data, so retry is safe
 *  - no clear answer -> the enrolment MAY exist, so never retry. Park in
 *                       verifying and ask RTO Data what actually happened.
 */
class EnrolStep implements Ams2Step
{
    public function __construct(
        private Ams2Claim $claim,
        private Ams2ApiClient $api,
        private Ams2EnrolArgs $args,
        private Ams2Failer $failer,
        private Ams2Verifier $verifier,
    ) {}

    public function from(): string
    {
        return Ams2Status::RECEIVED;
    }

    public function claims(): string
    {
        return Ams2Status::ENROLLING;
    }

    public function to(): string
    {
        return Ams2Status::ENROLLED;
    }

    public function handle(Ams2Enrolment $row): bool
    {
        if (! $this->claim->claimRow($row, $this->from(), $this->claims())) {
            return false;
        }

        $args = $this->args->build($row);
        $missing = $this->args->missingRequired($args);

        if ($missing !== []) {
            /*
             * retry: false — retrying cannot conjure a missing date of birth,
             * and nothing has been sent, so a human needs to fix the row.
             */
            $this->failer->fail(
                $row,
                $this->from(),
                'Missing required field: '.$missing[0],
                retry: false,
            );

            return false;
        }

        $result = $this->api->enrol($args);

        $enrolmentId = $result->get('enrolment_id');

        if ($result->succeeded() && ! empty($enrolmentId)) {
            /*
             * The enrolment ID is written in the same update that advances the
             * status, so it cannot be lost between the two.
             */
            $row->update([
                'status' => $this->to(),
                'attempts' => 0,
                'error_message' => null,
                'enrolment_id' => (string) $enrolmentId,
                'learner_id' => (string) ($result->get('enrolment_data.learner_id') ?? ''),
                'course_title' => (string) ($result->get('enrolment_data.course_title') ?? $row->course_title),
            ]);

            Log::info('[AMS2] enrolled', [
                'reference' => $row->displayRef(),
                'enrolment_id' => $enrolmentId,
                'learner_id' => $result->get('enrolment_data.learner_id'),
            ]);

            return true;
        }

        if ($result->unclear()) {
            $this->verifier->start(
                $row,
                'No clear answer from RTO Data ('.$result->describeFailure().').',
            );

            return false;
        }

        // A definite refusal: safe to try again.
        $this->failer->fail(
            $row,
            $this->from(),
            'RTO Data refused the enrolment: '.$result->raw,
        );

        return false;
    }
}
