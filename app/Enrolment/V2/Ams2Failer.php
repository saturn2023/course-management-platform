<?php

namespace App\Enrolment\V2;

use App\Models\Ams2Enrolment;
use Illuminate\Support\Facades\Log;

/**
 * Port of ams2_fail().
 *
 * A step that failed either goes back to its resting status to be retried, or —
 * once it has used up its attempts, or when the caller says the failure must
 * not be retried — parks in needs_review and raises an alert.
 *
 * `$retry = false` is how a timed-out step is handled: the step may already
 * have reached RTO Data, so repeating it could double-enrol a learner. Parking
 * it for a human is the safe outcome, not retrying.
 */
class Ams2Failer
{
    /**
     * From AMS2_MAX_ATTEMPTS in reference/wp-ams2.php.
     */
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private Ams2Alert $alert,
    ) {}

    public function fail(Ams2Enrolment $row, string $backTo, string $message, bool $retry = true): void
    {
        $attempts = (int) $row->attempts + 1;

        if ($retry && $attempts < self::MAX_ATTEMPTS) {
            $row->update([
                'status' => $backTo,
                'attempts' => $attempts,
                'error_message' => $message,
            ]);

            Log::warning('[AMS2] step_failed', [
                'reference' => $row->displayRef(),
                'attempt' => $attempts,
                'back_to' => $backTo,
                'message' => $message,
            ]);

            return;
        }

        $row->update([
            'status' => Ams2Status::NEEDS_REVIEW,
            'resume_status' => $backTo,
            'attempts' => $attempts,
            'error_message' => $message,
        ]);

        Log::error('[AMS2] needs_review', [
            'reference' => $row->displayRef(),
            'resume_status' => $backTo,
            'message' => $message,
        ]);

        $description = Ams2Status::stepDescriptions()[$backTo] ?? $backTo;

        $this->alert->send($row->fresh(), 'This enrolment stopped and needs checking.', [
            'Stopped while: '.$description,
            'Reason: '.$message,
            '',
            'Fix the cause, then use Resume.',
        ]);
    }
}
