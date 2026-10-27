<?php

namespace App\Enrolment\V2\Steps;

use App\Enrolment\V2\Ams2Alert;
use App\Enrolment\V2\Ams2ApiClient;
use App\Enrolment\V2\Ams2Claim;
use App\Enrolment\V2\Ams2Failer;
use App\Enrolment\V2\Ams2Status;
use App\Models\Ams2Enrolment;
use Illuminate\Support\Facades\Log;

/**
 * Replaces the placeholder address in RTO Data with the learner's real email.
 * Port of ams2_step_update().
 *
 * This updates the ENROLMENT record only — `type: enrolment` is the only type
 * this API accepts. The learner's profile is not touched, so a returning
 * learner whose profile still holds a placeholder is not repaired here.
 *
 * Unlike the enrol step there is no verify path, and a timeout is treated
 * exactly like a refusal: both retry. That looks inconsistent and is not —
 * writing the same email twice is idempotent, whereas enrolling twice would
 * create a second enrolment.
 */
class UpdateEmailStep implements Ams2Step
{
    public function __construct(
        private Ams2Claim $claim,
        private Ams2ApiClient $api,
        private Ams2Failer $failer,
        private Ams2Alert $alert,
    ) {}

    public function from(): string
    {
        return Ams2Status::DOCUMENTS_DONE;
    }

    public function claims(): string
    {
        return Ams2Status::UPDATING;
    }

    public function to(): string
    {
        return Ams2Status::UPDATED;
    }

    public function handle(Ams2Enrolment $row): bool
    {
        if (! $this->claim->claimRow($row, $this->from(), $this->claims())) {
            return false;
        }

        if (! $this->hasUsableEmail($row)) {
            /*
             * retry: false — retrying cannot repair a malformed address, and
             * nothing has been sent. A human has to correct the row.
             */
            $this->failWithPlaceholderWarning(
                $row,
                'The stored email address is not valid: '.$row->real_email,
                retry: false,
            );

            return false;
        }

        $result = $this->api->update(
            identifiers: [
                'type' => 'enrolment',
                // Cast to int: the reference does, and carries a debug probe
                // written specifically to test int against string here.
                'enrolment_id' => (int) $row->enrolment_id,
            ],
            values: [
                'email' => $row->real_email,
                'other_email' => $row->real_email,
            ],
        );

        if ($result->succeeded()) {
            $row->update([
                'status' => $this->to(),
                'attempts' => 0,
                'error_message' => null,
            ]);

            Log::info('[AMS2] email_updated', [
                'reference' => $row->displayRef(),
            ]);

            return true;
        }

        /*
         * Everything else retries, including a timeout. Repeating an update is
         * safe; the row goes back to documents_done and is picked up again.
         */
        $this->failWithPlaceholderWarning(
            $row,
            'Email update failed (HTTP '.$result->http.'): '
                .($result->error !== '' ? $result->error : $result->raw),
        );

        return false;
    }

    private function hasUsableEmail(Ams2Enrolment $row): bool
    {
        return filter_var((string) $row->real_email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Fail, and make sure the alert says what state RTO Data was left in.
     *
     * This is the one step where stopping leaves the learner's RTO Data record
     * holding an address that is not deliverable. Whoever picks up the alert
     * needs to know that, and what to put back.
     */
    private function failWithPlaceholderWarning(Ams2Enrolment $row, string $message, bool $retry = true): void
    {
        $this->failer->fail($row, $this->from(), $message, $retry);

        $parked = $row->fresh();

        if (! $parked || ! $parked->needsReview()) {
            // Still retrying; Ams2Failer has not alerted yet.
            return;
        }

        $this->alert->send($parked, 'This learner\'s email in RTO Data is still the placeholder.', [
            'RTO Data currently holds: '.$parked->placeholderEmail(),
            'It should be: '.$parked->real_email,
            '',
            'That address is not deliverable, so nothing sent from RTO Data will',
            'reach this learner until it is corrected.',
            '',
            'Set email and other_email on enrolment '.($parked->enrolment_id ?: 'unknown')
                .' to the address above, then use Resume.',
        ]);
    }
}
