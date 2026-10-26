<?php

namespace App\Enrolment\V2;

use App\Models\Ams2Enrolment;
use Illuminate\Support\Facades\Log;

/**
 * The verify path. Port of ams2_start_verify(), ams2_step_verify() and
 * ams2_verify_reenrol().
 *
 * Reached when an enrol call gives no clear answer. The enrolment may or may
 * not exist, so the only safe move is to ask RTO Data what is actually there
 * before doing anything else. Never a blind retry.
 */
class Ams2Verifier
{
    /** 5 * MINUTE_IN_SECONDS in the reference. */
    public const VERIFY_DELAY_MINUTES = 5;

    public const MAX_CHECKS = 6;

    public const MAX_REENROLS = 2;

    /** RTO Data status_id values that mean a finished enrolment. */
    private const FINISHED_STATUSES = [2, 3, 4];

    private const ACTIVE_STATUS = 0;

    public function __construct(
        private Ams2Claim $claim,
        private Ams2ApiClient $api,
        private Ams2Failer $failer,
        private Ams2EnrolmentFloor $floor,
    ) {}

    /**
     * Park a row for checking, a few minutes from now.
     */
    public function start(Ams2Enrolment $row, string $reason): void
    {
        $row->update([
            'status' => Ams2Status::VERIFYING,
            'verify_after' => now()->addMinutes(self::VERIFY_DELAY_MINUTES),
            'verify_checks' => 0,
            'error_message' => $reason.' Checking RTO Data before retrying.',
        ]);

        Log::warning('[AMS2] verify_started', [
            'reference' => $row->reference(),
            'reason' => $reason,
        ]);
    }

    /**
     * Ask RTO Data what exists for this learner on this course.
     */
    public function verify(Ams2Enrolment $row): void
    {
        if (! $this->claim->claimRow($row, Ams2Status::VERIFYING, Ams2Status::CHECKING)) {
            return;
        }

        /*
         * Without a configured floor there is no way to tell this row's
         * enrolment from one WordPress created earlier for the same learner,
         * and linking the wrong one is worse than stopping. Park it.
         */
        if (! $this->floor->isConfigured()) {
            $this->failer->fail(
                $row,
                Ams2Status::RECEIVED,
                'Cannot check RTO Data safely: AMS2_ENROLMENT_FLOOR is not set, so an '
                    .'older enrolment for this learner could be linked by mistake. '
                    .'Set it, then resume.',
                retry: false,
            );

            return;
        }

        $data = (array) ($row->student_data ?? []);
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));

        if ($firstName === '' || $lastName === '') {
            $this->failer->fail(
                $row,
                Ams2Status::RECEIVED,
                'Auto-check not possible: no name on the row. Check RTO Data before resuming.',
                retry: false,
            );

            return;
        }

        $result = $this->api->check([
            'type' => 'course',
            'course_id' => (string) (int) $row->course_id,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        // The check itself failed. Never read that as "not found".
        if (! $result->sent || $result->http >= 500 || ! is_array($result->body)) {
            $this->retryCheckLater($row, $result);

            return;
        }

        $found = $result->succeeded() ? (array) ($result->get('data') ?? []) : [];

        if ($found === [] || empty($found['enrolment_id'])) {
            $this->reenrol($row, 'Not found in RTO Data after timeout.');

            return;
        }

        $this->resolve($row, $found);
    }

    private function retryCheckLater(Ams2Enrolment $row, Ams2ApiResult $result): void
    {
        $checks = (int) $row->verify_checks + 1;

        if ($checks >= self::MAX_CHECKS) {
            $this->failer->fail(
                $row,
                Ams2Status::RECEIVED,
                'Could not check RTO Data after '.$checks.' tries ('.$result->describeFailure()
                    .'). The enrolment may exist. Check RTO Data before resuming.',
                retry: false,
            );

            return;
        }

        $row->update([
            'status' => Ams2Status::VERIFYING,
            'verify_checks' => $checks,
            'verify_after' => now()->addMinutes(self::VERIFY_DELAY_MINUTES),
        ]);

        Log::warning('[AMS2] verify_check_failed', [
            'reference' => $row->reference(),
            'check' => $checks.' of '.self::MAX_CHECKS,
        ]);
    }

    /**
     * @param  array<string, mixed>  $found
     */
    private function resolve(Ams2Enrolment $row, array $found): void
    {
        $enrolmentId = (int) $found['enrolment_id'];
        $status = isset($found['status_id']) ? (int) $found['status_id'] : -1;
        $floor = $this->floor->for($row);

        $description = $enrolmentId.' (status '.$status.', created '.($found['created'] ?? '?').')';

        // Active and newer than anything that existed before: this row's own.
        if ($status === self::ACTIVE_STATUS && $enrolmentId > $floor) {
            $row->update([
                'status' => Ams2Status::ENROLLED,
                'resume_status' => '',
                'attempts' => 0,
                'error_message' => null,
                'enrolment_id' => (string) $enrolmentId,
                'learner_id' => (string) (int) ($found['learner_id'] ?? 0),
            ]);

            Log::info('[AMS2] verify_linked', [
                'reference' => $row->reference(),
                'enrolment_id' => $enrolmentId,
                'floor' => $floor,
            ]);

            return;
        }

        // Only an older finished enrolment: the new one was never created.
        if (in_array($status, self::FINISHED_STATUSES, true) && $enrolmentId <= $floor) {
            $this->reenrol($row, 'Only an older finished enrolment in RTO Data: '.$description.'.');

            return;
        }

        $why = $status === self::ACTIVE_STATUS
            ? 'The student already has an older open enrolment on this course: '.$description
                .'. Decide whether to use it or enrol again.'
            : 'The RTO Data check returned an enrolment that can\'t be matched to this one: '
                .$description.' (floor '.$floor.').';

        $this->failer->fail(
            $row,
            Ams2Status::RECEIVED,
            'Timed out. '.$why.' Check RTO Data before resuming.',
            retry: false,
        );
    }

    /**
     * Send the row back to be enrolled again, with a hard cap.
     */
    private function reenrol(Ams2Enrolment $row, string $reason): void
    {
        if ((int) $row->reenrol_count >= self::MAX_REENROLS) {
            $this->failer->fail(
                $row,
                Ams2Status::RECEIVED,
                $reason.' Already re-enrolled '.(int) $row->reenrol_count
                    .' times. Check RTO Data before resuming.',
                retry: false,
            );

            return;
        }

        $row->update([
            'status' => Ams2Status::RECEIVED,
            'attempts' => 0,
            'reenrol_count' => (int) $row->reenrol_count + 1,
            'error_message' => $reason.' Enrolling again.',
        ]);

        Log::info('[AMS2] verify_reenrol', [
            'reference' => $row->reference(),
            'reason' => $reason,
            'reenrol' => (int) $row->reenrol_count,
        ]);
    }
}
