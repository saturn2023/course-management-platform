<?php

namespace App\Enrolment\V2\Steps;

use App\Enrolment\V2\Ams2Status;

/**
 * Replaces the placeholder with the learner's real email. Email fields only.
 *
 * NOT IMPLEMENTED: stops the row at this status rather than advancing it. UpdateEmailStep's real work is still to come.
 */
class UpdateEmailStep extends StubbedStep
{
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
}
