<?php

namespace App\Enrolment\V2;

use App\Models\Enrolment;

/**
 * Whether a secret key has already been used on the OLD enrolment form.
 *
 * Port of ams2_in_old_flow(), which asks WordPress's `enrolment_requests`
 * table — written when the old form is submitted — whether it holds the key.
 * The Laravel equivalent is an `enrolment_submissions` row for the enrolment
 * that owns the key; it resolves through the enrolment because that table has
 * no secret_key column of its own.
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
            ->whereHas('submission')
            ->exists();
    }
}
