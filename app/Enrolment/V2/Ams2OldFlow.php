<?php

namespace App\Enrolment\V2;

use App\Models\Enrolment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Whether a secret key has already been used on the OLD enrolment form.
 *
 * Port of ams2_in_old_flow(), which asks WordPress's `enrolment_requests`
 * table — written when the old form is submitted — whether it holds the key.
 *
 * In WordPress "this link has been used" and "this link is in the old flow" are
 * the SAME fact: one row records both. Laravel splits them across two columns,
 * an `enrolment_submissions` row and `enrolments.enrolment_completed_at`, so
 * either one means the link is spent:
 *
 *  - A submission row means the learner's answers were saved, whatever happened
 *    to the API call afterwards. A pending or failed `api_status` still counts:
 *    the data is here and a retry can still push it to RTO Data, so routing the
 *    learner to v2 could enrol them twice.
 *  - `enrolment_completed_at` means the link was marked used. Both v1 routes
 *    refuse such a link, and nothing in the v2 path checks for a spent link —
 *    the reference never needed that check, because there the used marker and
 *    the old-flow marker are one row. Reading only the submission row would let
 *    a used link come back to life through v2.
 *
 * Neither marker is ever written by the v2 flow, which keeps its state entirely
 * in `ams2_enrolments`, so this can never lock v2 out of its own rows.
 *
 * An attempt that died before writing anything — the form_data encoding bug,
 * for instance — leaves neither marker, and correctly does not count: nothing
 * was submitted and the link is still unused.
 *
 * Checked at all three entry points, with a different outcome at each: the
 * route hands the request back to the old flow silently, the form page says the
 * enrolment is already submitted, and the submit endpoint refuses with 409.
 * Three checks rather than one because a learner can sit on a form page for an
 * hour — the race is real, and only the check at submit protects the data.
 */
class Ams2OldFlow
{
    public function has(?string $secretKey): bool
    {
        $secretKey = trim((string) $secretKey);

        if ($secretKey === '') {
            return false;
        }

        return Enrolment::query()
            ->where('secret_key', $secretKey)
            ->where(fn (Builder $query) => $query
                ->whereHas('submission')
                ->orWhereNotNull('enrolment_completed_at'))
            ->exists();
    }
}
