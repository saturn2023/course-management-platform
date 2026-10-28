<?php

namespace App\Enrolment\V2;

/**
 * The Enrolment v2 status names, taken verbatim from reference/wp-ams2.php.
 *
 * Resting statuses are ones a processor run may pick a row up from. The `-ing`
 * statuses are in-flight claims: a row sitting in one means a step started and
 * has not reported back, which is why a stuck sweep parks them rather than
 * retrying — a repeated enrol call could double-enrol a learner.
 */
final class Ams2Status
{
    // Resting.
    public const RECEIVED = 'received';

    public const ENROLLED = 'enrolled';

    public const DOCUMENTS_DONE = 'documents_done';

    public const UPDATED = 'updated';

    // In-flight claims.
    public const ENROLLING = 'enrolling';

    public const SENDING_DOCUMENTS = 'sending_documents';

    public const UPDATING = 'updating';

    public const EMAILING = 'emailing';

    // Verify path, entered when an enrol call gives no clear answer.
    public const VERIFYING = 'verifying';

    public const CHECKING = 'checking';

    // Terminal.
    public const COMPLETE = 'complete';

    public const NEEDS_REVIEW = 'needs_review';

    /**
     * Statuses a processor run picks up, in the order the machine advances.
     *
     * @return array<int, string>
     */
    public static function resting(): array
    {
        return [
            self::RECEIVED,
            self::ENROLLED,
            self::DOCUMENTS_DONE,
            self::UPDATED,
        ];
    }

    /**
     * In-flight claims, mapped back to the resting status they came from.
     *
     * This is the `$back` map in ams2_cron(): a step that started but never
     * finished is returned to its resting status, then parked.
     *
     * @return array<string, string>
     */
    public static function inFlightFallbacks(): array
    {
        return [
            self::ENROLLING => self::RECEIVED,
            self::SENDING_DOCUMENTS => self::ENROLLED,
            self::UPDATING => self::DOCUMENTS_DONE,
            self::EMAILING => self::UPDATED,
        ];
    }

    /**
     * Human wording for "stopped while ...", from ams2_fail().
     *
     * @return array<string, string>
     */
    public static function stepDescriptions(): array
    {
        return [
            self::RECEIVED => 'creating the enrolment in RTO Data',
            self::ENROLLED => 'sending documents to RTO Data',
            self::DOCUMENTS_DONE => 'updating the student email in RTO Data',
            self::UPDATED => 'sending the confirmation email',
        ];
    }
}
