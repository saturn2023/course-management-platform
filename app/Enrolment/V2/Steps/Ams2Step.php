<?php

namespace App\Enrolment\V2\Steps;

use App\Models\Ams2Enrolment;

/**
 * One transition in the Enrolment v2 machine.
 *
 * Every step claims its row atomically before doing anything, and returns true
 * only when the row actually advanced — the processor uses that to decide
 * whether to keep going or hand the row back.
 */
interface Ams2Step
{
    /**
     * The resting status this step picks a row up from.
     */
    public function from(): string;

    /**
     * The in-flight status it claims while working.
     */
    public function claims(): string;

    /**
     * The resting status it leaves the row in on success.
     */
    public function to(): string;

    /**
     * @return bool True when the row advanced.
     */
    public function handle(Ams2Enrolment $row): bool;
}
