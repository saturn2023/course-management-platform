<?php

namespace App\Enrolment\V2;

use RuntimeException;

/**
 * A document the server will not accept, with the words the learner reads.
 *
 * The message is part of the behaviour, not decoration: several of them tell
 * the learner to email the document to sales@amstraining.com.au instead, which
 * is the only way their enrolment gets completed once online upload has failed
 * or closed. They are ported from the reference verbatim.
 */
class Ams2DocumentRejected extends RuntimeException
{
    /**
     * Named failureCode, not code: Exception already owns $code and PHP will
     * not let a subclass redeclare it as readonly. The JSON field is still
     * "code", which is what the browser script reads.
     */
    public function __construct(
        public readonly string $learnerMessage,
        public readonly string $failureCode,
        public readonly int $status = 400,
    ) {
        parent::__construct($learnerMessage);
    }
}
