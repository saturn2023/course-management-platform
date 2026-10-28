<?php

namespace App\Enrolment\V2;

use App\Models\Ams2Enrolment;
use Illuminate\Support\Facades\DB;

/**
 * The highest RTO Data enrolment ID that already existed before this row.
 *
 * Verify uses it to tell "the enrolment we just created" from "an older
 * enrolment this learner already had". Anything at or below the floor predates
 * this row, so linking it would attach the wrong enrolment.
 *
 * Port of ams2_enrolment_floor(), with one addition that matters here: this
 * database has no record of the enrolments WordPress already created in RTO
 * Data. A floor computed only from local rows would therefore sit far too low,
 * and verify could link an old WordPress enrolment for the same learner.
 * AMS2_ENROLMENT_FLOOR carries that history, and without it verify refuses to
 * run at all rather than guessing.
 */
class Ams2EnrolmentFloor
{
    /**
     * The configured floor, or null when it has not been set.
     */
    public function configured(): ?int
    {
        $value = config('services.ams2.enrolment_floor');

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (int) $value;
    }

    public function isConfigured(): bool
    {
        return $this->configured() !== null;
    }

    /**
     * The floor to compare a found enrolment against.
     *
     * max(configured, computed): the configured value covers the WordPress era,
     * the computed value covers everything this system has done since.
     */
    public function for(Ams2Enrolment $row): int
    {
        return max((int) $this->configured(), $this->computed($row));
    }

    /**
     * Highest enrolment ID across both flows that predates this row.
     */
    public function computed(Ams2Enrolment $row): int
    {
        return max($this->fromV2Rows($row), $this->fromOldFlow($row));
    }

    private function fromV2Rows(Ams2Enrolment $row): int
    {
        $ids = Ams2Enrolment::query()
            ->where('id', '<', $row->id)
            ->whereNotNull('enrolment_id')
            ->where('enrolment_id', '<>', '')
            ->pluck('enrolment_id');

        return $this->maxNumeric($ids);
    }

    /**
     * The old flow's RTO Data IDs live in enrolment_submissions.external_reference,
     * which is a free-text column — non-numeric values are ignored rather than
     * being cast to 0 and silently lowering the floor.
     */
    private function fromOldFlow(Ams2Enrolment $row): int
    {
        if (! $row->created_at) {
            return 0;
        }

        $ids = DB::table('enrolment_submissions')
            ->where('created_at', '<', $row->created_at)
            ->whereNotNull('external_reference')
            ->pluck('external_reference');

        return $this->maxNumeric($ids);
    }

    /**
     * @param  iterable<mixed>  $values
     */
    private function maxNumeric(iterable $values): int
    {
        $max = 0;

        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value === '' || ! ctype_digit($value)) {
                continue;
            }

            $max = max($max, (int) $value);
        }

        return $max;
    }
}
