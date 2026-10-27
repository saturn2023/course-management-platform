<?php

namespace App\Enrolment\V2\Steps;

use App\Models\Ams2Enrolment;
use Illuminate\Support\Facades\Log;

/**
 * A step that has not been built yet.
 *
 * It deliberately STOPS the row rather than advancing it. An unimplemented step
 * that moved a row on would mark work as done that never happened — a row would
 * reach `complete` with no documents sent, no email corrected and no
 * confirmation delivered, and nothing would ever go back for it.
 *
 * Stopping leaves the row parked at its current resting status, visible and
 * ready to continue the moment the real step lands.
 *
 * Nothing here claims the row, calls RTO Data, sends email, or sends SMS.
 */
abstract class StubbedStep implements Ams2Step
{
    public function handle(Ams2Enrolment $row): bool
    {
        Log::info('[AMS2] next step not implemented', [
            'reference' => $row->displayRef(),
            'status' => $row->status,
            'awaiting_step' => static::class,
            'would_advance_to' => $this->to(),
        ]);

        return false;
    }
}
