<?php

namespace App\Enrolment\V2\Steps;

use App\Enrolment\V2\Ams2Alert;
use App\Enrolment\V2\Ams2Claim;
use App\Enrolment\V2\Ams2DocumentSender;
use App\Enrolment\V2\Ams2DocumentTypes;
use App\Enrolment\V2\Ams2Status;
use App\Models\Ams2Enrolment;
use App\Models\Ams2EnrolmentDocument;
use Illuminate\Support\Facades\Log;

/**
 * Uploads the learner's documents to RTO Data. Port of ams2_step_documents().
 *
 * The behaviour that surprises people: a document that cannot be sent does NOT
 * stop the enrolment. The learner was never shown an error, the enrolment is
 * already in RTO Data, and blocking here would strand them. The row advances
 * and an alert names what is missing so someone can chase it.
 */
class SendDocumentsStep implements Ams2Step
{
    /**
     * How long to wait for the browser to finish uploading before giving up on
     * it. 15 * MINUTE_IN_SECONDS in the reference.
     */
    public const UPLOAD_GRACE_MINUTES = 15;

    public function __construct(
        private Ams2Claim $claim,
        private Ams2DocumentSender $sender,
        private Ams2Alert $alert,
    ) {}

    public function from(): string
    {
        return Ams2Status::ENROLLED;
    }

    public function claims(): string
    {
        return Ams2Status::SENDING_DOCUMENTS;
    }

    public function to(): string
    {
        return Ams2Status::DOCUMENTS_DONE;
    }

    public function handle(Ams2Enrolment $row): bool
    {
        if (! $this->isReady($row)) {
            return false;
        }

        if (! $this->claim->claimRow($row, $this->from(), $this->claims())) {
            return false;
        }

        $result = $this->sender->send($row);

        /*
         * Something is still outstanding — a retry, or a document another run
         * is holding. Go back to enrolled and let the next minute pick it up,
         * rather than advancing with documents still unsent.
         */
        if ($result['pending'] > 0) {
            $row->update(['status' => $this->from()]);

            return false;
        }

        $this->alertOnGaps($row, $result['failed']);

        $row->update([
            'status' => $this->to(),
            'attempts' => 0,
            'error_message' => null,
        ]);

        return true;
    }

    /**
     * Whether the browser has finished uploading, or has run out of time.
     *
     * NOTE: docs_final is set by the enrolment form when its uploads complete.
     * Until that is wired up (stage 6), every row with documents waits the full
     * grace period before this step will touch it.
     */
    private function isReady(Ams2Enrolment $row): bool
    {
        if ($row->docs_final) {
            return true;
        }

        return $row->created_at
            && $row->created_at->lt(now()->subMinutes(self::UPLOAD_GRACE_MINUTES));
    }

    /**
     * @param  array<string, string>  $failed
     */
    private function alertOnGaps(Ams2Enrolment $row, array $failed): void
    {
        $expected = array_filter(array_map('trim', explode(',', (string) $row->docs_expected)));

        $sent = Ams2EnrolmentDocument::query()
            ->where('enrolment_row', $row->id)
            ->where('status', Ams2EnrolmentDocument::STATUS_SENT)
            ->distinct()
            ->pluck('doc_type')
            ->all();

        $problems = [];

        foreach ($expected as $type) {
            if (in_array($type, $sent, true)) {
                continue;
            }

            /*
             * Expected but neither sent nor failed means the upload never
             * reached us at all — the connection dropped, or the learner closed
             * the page.
             */
            $reason = $failed[$type]
                ?? 'The upload never reached the website (connection dropped or the page was closed).';

            $problems[] = '- '.Ams2DocumentTypes::label($type).': '.$reason;
        }

        // A document that failed without ever being expected still gets named.
        foreach ($failed as $type => $reason) {
            if (! in_array($type, $expected, true)) {
                $problems[] = '- '.Ams2DocumentTypes::label($type).': '.$reason;
            }
        }

        if ($problems === []) {
            return;
        }

        Log::warning('[AMS2] documents_missing', [
            'reference' => $row->reference(),
            'count' => count($problems),
        ]);

        $this->alert->send(
            $row->fresh(),
            'The enrolment is in RTO Data, but these documents are not attached to it:',
            array_merge($problems, [
                '',
                'The student was not shown an error. The enrolment will continue. '
                    .'Please contact them for the missing documents.',
            ]),
        );
    }
}
