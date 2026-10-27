<?php

namespace App\Enrolment\V2;

use App\Models\Ams2Enrolment;
use Illuminate\Support\Facades\Log;

/**
 * Operator alert for an enrolment that stopped.
 *
 * STAGE 1 STUB — writes a log line instead of sending mail. The real one goes
 * out through the Two Moons SMTP2GO account (TM_SMTP2GO_API_KEY), deliberately
 * not the client's mailer, so an alert still arrives when their mail setup is
 * the thing that broke.
 *
 * The fields are already the ones ams2_alert() quotes, because the point of the
 * alert is that someone can repair the enrolment by hand from it — in
 * particular `real_email`, since RTO Data may still be holding the
 * 'processing{n}hold.invalid' placeholder for this learner.
 */
class Ams2Alert
{
    /**
     * @param  array<int, string>  $details
     */
    public function send(Ams2Enrolment $row, string $heading, array $details = []): void
    {
        $data = (array) ($row->student_data ?? []);

        Log::error('[AMS2] '.$heading, [
            'reference' => $row->displayRef(),
            'enrolment_row_id' => $row->id,
            'student' => $row->studentName(),
            'real_email' => $row->real_email,
            'mobile' => $data['mobile_phone'] ?? '',
            'course_id' => $row->course_id,
            'plan_id' => $row->plan_id,
            // "not created" / "not known" mirror the wording in ams2_alert().
            'rto_enrolment_id' => $row->enrolment_id ?: 'not created',
            'rto_learner_id' => $row->learner_id ?: 'not known',
            'status' => $row->status,
            'resume_status' => $row->resume_status,
            'submitted_at' => $row->created_at?->toDateTimeString(),
            'details' => $details,
        ]);
    }
}
