<?php

namespace App\Enrolment\V2\Steps;

use App\Enrolment\V2\Ams2Alert;
use App\Enrolment\V2\Ams2Claim;
use App\Enrolment\V2\Ams2Failer;
use App\Enrolment\V2\Ams2Status;
use App\Mail\Ams2ConfirmationMail;
use App\Models\Ams2Enrolment;
use App\Models\EnrolmentEmailTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the per-course confirmation from the published Enrolment Email
 * template. Port of ams2_step_email().
 *
 * To the learner only — the reference sets no CC, no BCC and no copy to info@
 * or sales@, and no Reply-To.
 *
 * This step does NOT send the old flow's confirmation. v2 rows live in their
 * own tables and create no Enrolment record, so nothing here can reach
 * SendEnrolmentEmailJob.
 */
class SendConfirmationStep implements Ams2Step
{
    public function __construct(
        private Ams2Claim $claim,
        private Ams2Failer $failer,
        private Ams2Alert $alert,
    ) {}

    public function from(): string
    {
        return Ams2Status::UPDATED;
    }

    public function claims(): string
    {
        return Ams2Status::EMAILING;
    }

    public function to(): string
    {
        return Ams2Status::COMPLETE;
    }

    public function handle(Ams2Enrolment $row): bool
    {
        if ($this->alreadySent($row)) {
            return true;
        }

        if (! $this->claim->claimRow($row, $this->from(), $this->claims())) {
            return false;
        }

        $template = EnrolmentEmailTemplate::findPublishedFor(
            (int) $row->course_id,
            (int) $row->plan_id,
        );

        if (! $template) {
            $this->failMissingTemplate($row);

            return false;
        }

        $rendered = $template->render(
            (array) ($row->student_data ?? []),
            $row->resolvedCourseTitle(),
        );

        try {
            Mail::to($row->real_email)->send(new Ams2ConfirmationMail(
                subjectLine: $rendered['subject'],
                body: $rendered['body'],
                attachmentPaths: $rendered['attachments'],
            ));
        } catch (Throwable $exception) {
            $this->failer->fail(
                $row,
                $this->from(),
                'Confirmation email failed: '.$exception->getMessage(),
            );

            return false;
        }

        /*
         * One update, immediately after the send returns, so the window in
         * which a crash could leave the row looking unsent is as small as it
         * can be made in process.
         */
        $row->update([
            'status' => $this->to(),
            'attempts' => 0,
            'error_message' => null,
            'email_sent_at' => now(),
        ]);

        Log::info('[AMS2] complete', [
            'reference' => $row->displayRef(),
            'attachments' => count($rendered['attachments']),
        ]);

        return true;
    }

    /**
     * Rescue a row whose email went out but whose status never caught up.
     *
     * Checked BEFORE the claim, because the row will be sitting at `emailing`
     * rather than `updated` and so could never be claimed from here.
     *
     * The reference has no equivalent: it relies on the 30-minute sweep parking
     * such a row, and Resume then sends the confirmation a second time. This is
     * a deliberate improvement.
     */
    private function alreadySent(Ams2Enrolment $row): bool
    {
        if ($row->status !== Ams2Status::EMAILING || ! $row->email_sent_at) {
            return false;
        }

        if (! $this->claim->claimRow($row, Ams2Status::EMAILING, $this->to())) {
            return false;
        }

        Log::warning('[AMS2] confirmation already sent, completing without resending', [
            'reference' => $row->displayRef(),
            'email_sent_at' => $row->email_sent_at?->toDateTimeString(),
        ]);

        return true;
    }

    /**
     * No published template means there is nothing to send and retrying cannot
     * conjure one — a human has to publish it, then Resume.
     */
    private function failMissingTemplate(Ams2Enrolment $row): void
    {
        $message = 'No published Enrolment Email for course '.$row->course_id
            .' / plan '.$row->plan_id.'.';

        $this->failer->fail($row, $this->from(), $message, retry: false);

        $this->alert->send($row->fresh(), 'This enrolment has no confirmation email to send.', [
            $message,
            '',
            'The learner is enrolled in RTO Data and their documents are attached,',
            'but they have not been told anything.',
            '',
            'Publish an Enrolment Email for this course and plan, then use Resume.',
        ]);
    }
}
