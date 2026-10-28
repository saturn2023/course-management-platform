<?php

namespace App\Enrolment\V2;

use App\Models\Ams2Enrolment;
use Illuminate\Support\Facades\DB;

/**
 * Atomic status transitions.
 *
 * Port of ams2_claim(): a single conditional UPDATE that succeeds only when it
 * changes exactly one row. Two runs racing for the same row means exactly one
 * wins and the loser does nothing — which is the only reason it is safe to call
 * an enrol endpoint from inside a step.
 *
 * Never select-then-update. The check and the write have to be the same
 * statement or there is a window between them.
 */
class Ams2Claim
{
    /**
     * Move a row from one status to another, if it is still in `$from`.
     *
     * @return bool True only when this caller made the transition.
     */
    public function claim(int $id, string $from, string $to): bool
    {
        $affected = DB::table('ams2_enrolments')
            ->where('id', $id)
            ->where('status', $from)
            ->update([
                'status' => $to,
                'updated_at' => now(),
            ]);

        return $affected === 1;
    }

    /**
     * Convenience wrapper for a model instance, refreshing it on success so the
     * caller never acts on a stale status.
     */
    public function claimRow(Ams2Enrolment $row, string $from, string $to): bool
    {
        if (! $this->claim($row->id, $from, $to)) {
            return false;
        }

        $row->refresh();

        return true;
    }
}
