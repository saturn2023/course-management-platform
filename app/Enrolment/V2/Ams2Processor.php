<?php

namespace App\Enrolment\V2;

use App\Enrolment\V2\Steps\Ams2Step;
use App\Enrolment\V2\Steps\EnrolStep;
use App\Enrolment\V2\Steps\SendConfirmationStep;
use App\Enrolment\V2\Steps\SendDocumentsStep;
use App\Enrolment\V2\Steps\UpdateEmailStep;
use App\Models\Ams2Enrolment;
use App\Models\Ams2EnrolmentDocument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The Enrolment v2 processor — port of ams2_cron() and ams2_process_row().
 *
 * One run does, in this order:
 *   1. park steps that started but never finished (30 minutes)
 *   2. return timed-out document sends to retry
 *   3. advance up to 10 rows through the machine
 *   4. release timed-out verify checks and run due ones
 *
 * Order matters: the stuck sweep runs first so a row parked this minute is not
 * also picked up by the batch below it.
 */
class Ams2Processor
{
    /**
     * A step that has not reported back within this long is assumed dead.
     * 30 * MINUTE_IN_SECONDS in ams2_cron().
     */
    public const STUCK_AFTER_MINUTES = 30;

    /**
     * A verify check only reads, so repeating one is safe after 10 minutes.
     */
    public const CHECKING_RESET_AFTER_MINUTES = 10;

    public const BATCH_LIMIT = 10;

    public const VERIFY_BATCH_LIMIT = 5;

    /**
     * ams2_process_row() loops at most five times per row, so one run can carry
     * a row from received all the way to complete.
     */
    public const MAX_STEPS_PER_ROW = 5;

    /**
     * Only one run at a time.
     *
     * NOTE: ams2_cron() has no explicit lock — it leans on WP-Cron not
     * overlapping. This is an addition, not a port.
     */
    public const LOCK_KEY = 'ams2-processor';

    public const LOCK_SECONDS = 300;

    /** @var array<int, Ams2Step> */
    private array $steps;

    public function __construct(
        private Ams2Failer $failer,
        private Ams2Verifier $verifier,
        EnrolStep $enrol,
        SendDocumentsStep $documents,
        UpdateEmailStep $update,
        SendConfirmationStep $confirmation,
    ) {
        $this->steps = [$enrol, $documents, $update, $confirmation];
    }

    public static function enabled(): bool
    {
        return (bool) config('services.ams2.enabled', false);
    }

    /**
     * Settings the processor cannot run without, named as they appear in .env.
     *
     * @return array<int, string>
     */
    public static function missingConfiguration(): array
    {
        return Ams2ApiClient::missingConfiguration();
    }

    /**
     * @return bool False when disabled, or when another run holds the lock.
     */
    public function run(): bool
    {
        /*
         * Kill switch. Checked before the lock so a disabled processor is a
         * complete no-op: nothing claimed, nothing locked, nothing sent.
         */
        if (! self::enabled()) {
            return false;
        }

        /*
         * Stop before the lock and before any row is claimed.
         *
         * A half-configured client is refused by RTO Data, and the enrol step
         * would read that refusal as "no clear answer" and park the row in
         * verifying — a missing .env value dressed up as an RTO Data outage.
         * Exiting here leaves every row exactly as it was.
         */
        if ($missing = self::missingConfiguration()) {
            Log::error('[AMS2] processor_not_configured', [
                'missing' => $missing,
            ]);

            return false;
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return false;
        }

        try {
            $this->parkStuckRows();
            $this->retryStuckDocuments();
            $this->advanceBatch();
            $this->releaseStuckVerifyChecks();
            $this->runDueVerifyChecks();
        } finally {
            $lock->release();
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Steps that started but never finished
    |--------------------------------------------------------------------------
    */

    private function parkStuckRows(): void
    {
        $cutoff = now()->subMinutes(self::STUCK_AFTER_MINUTES);
        $fallbacks = Ams2Status::inFlightFallbacks();

        $stuck = Ams2Enrolment::query()
            ->whereIn('status', array_keys($fallbacks))
            ->where('updated_at', '<', $cutoff)
            ->get();

        foreach ($stuck as $row) {
            /*
             * retry: false. The step may already have reached RTO Data, so
             * repeating it could enrol the learner twice. Park it for a human.
             */
            $this->failer->fail(
                $row,
                $fallbacks[$row->status],
                'The step "'.$row->status.'" started but did not finish within '
                    .self::STUCK_AFTER_MINUTES.' minutes.',
                retry: false,
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Document sends that never finished
    |--------------------------------------------------------------------------
    */

    private function retryStuckDocuments(): void
    {
        // Resending a document is safe, unlike repeating an enrol.
        Ams2EnrolmentDocument::query()
            ->where('status', Ams2EnrolmentDocument::STATUS_SENDING)
            ->where('updated_at', '<', now()->subMinutes(self::STUCK_AFTER_MINUTES))
            ->update([
                'status' => Ams2EnrolmentDocument::STATUS_RETRY,
                'updated_at' => now(),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Advance rows
    |--------------------------------------------------------------------------
    */

    private function advanceBatch(): void
    {
        $ids = DB::table('ams2_enrolments')
            ->whereIn('status', Ams2Status::resting())
            ->orderBy('id')
            ->limit(self::BATCH_LIMIT)
            ->pluck('id');

        foreach ($ids as $id) {
            $this->processRow((int) $id);
        }
    }

    /**
     * Port of ams2_process_row(): keep stepping while the row keeps moving.
     */
    public function processRow(int $id): void
    {
        for ($i = 0; $i < self::MAX_STEPS_PER_ROW; $i++) {
            $row = Ams2Enrolment::find($id);

            if (! $row) {
                return;
            }

            $step = $this->stepFor($row->status);

            // Nothing handles this status — complete, needs_review, verifying.
            if (! $step) {
                return;
            }

            if (! $step->handle($row)) {
                return;
            }
        }
    }

    private function stepFor(string $status): ?Ams2Step
    {
        foreach ($this->steps as $step) {
            if ($step->from() === $status) {
                return $step;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Verify path
    |--------------------------------------------------------------------------
    |
    | Dormant in stage 1: nothing can reach `verifying` until a real enrol call
    | can come back without a clear answer. The sweep is here so stage 2 only
    | has to supply the check itself.
    */

    private function releaseStuckVerifyChecks(): void
    {
        Ams2Enrolment::query()
            ->where('status', Ams2Status::CHECKING)
            ->where('updated_at', '<', now()->subMinutes(self::CHECKING_RESET_AFTER_MINUTES))
            ->update([
                'status' => Ams2Status::VERIFYING,
                'updated_at' => now(),
            ]);
    }

    private function runDueVerifyChecks(): void
    {
        foreach ($this->dueVerifyRows() as $row) {
            $this->verifier->verify($row);
        }
    }

    /**
     * Rows whose verify check is due.
     *
     * @return \Illuminate\Support\Collection<int, Ams2Enrolment>
     */
    public function dueVerifyRows()
    {
        return Ams2Enrolment::query()
            ->where('status', Ams2Status::VERIFYING)
            ->where('verify_after', '<=', now())
            ->orderBy('id')
            ->limit(self::VERIFY_BATCH_LIMIT)
            ->get();
    }
}
