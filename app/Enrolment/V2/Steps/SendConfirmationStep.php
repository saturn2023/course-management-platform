<?php

namespace App\Enrolment\V2\Steps;

use App\Enrolment\V2\Ams2Status;

/**
 * Sends the per-course confirmation from the published Enrolment Email template.
 *
 * NOT IMPLEMENTED: stops the row at this status rather than advancing it. SendConfirmationStep's real work is still to come.
 */
class SendConfirmationStep extends StubbedStep
{
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
}
