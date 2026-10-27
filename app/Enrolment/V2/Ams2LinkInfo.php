<?php

namespace App\Enrolment\V2;

/**
 * What an enrolment link resolves to. Port of the array ams2_link_info()
 * returns.
 *
 * There are exactly three states, and the difference between two of them
 * matters at every call site:
 *
 *  - `invalid` — the link cannot be trusted at all. Three separate causes
 *    collapse into this one state in the reference: a malformed key, a key with
 *    no order, and a key whose order no longer resolves. None of them is ever
 *    treated as a pass.
 *  - `expired` — the link is genuine but too old. **Not** the same as invalid:
 *    the reference still routes an expired link to v2, which then shows the
 *    expiry message itself, so routing never silently swallows an expiry.
 *  - `ok` — usable.
 *
 * `courseId`, `planId` and `clientId` are read from the link's own query
 * string, never from the browser. The reference is explicit about this
 * ("From the link, not the browser.") because the learner can edit anything
 * posted from the form.
 */
final readonly class Ams2LinkInfo
{
    public const OK = 'ok';

    public const EXPIRED = 'expired';

    public const INVALID = 'invalid';

    private function __construct(
        public string $state,
        public int $orderId = 0,
        public string $courseCode = '',
        public int $courseId = 0,
        public int $planId = 0,
        public string $clientId = '',
        public string $firstName = '',
        public string $lastName = '',
        public string $email = '',
    ) {}

    /**
     * No usable link. Carries nothing: there is nothing worth reading off it,
     * and a caller that reaches for a course id here has skipped a check.
     */
    public static function invalid(): self
    {
        return new self(self::INVALID);
    }

    public static function resolved(
        bool $expired,
        int $orderId,
        string $courseCode,
        int $courseId,
        int $planId,
        string $clientId,
        string $firstName,
        string $lastName,
        string $email,
    ): self {
        return new self(
            state: $expired ? self::EXPIRED : self::OK,
            orderId: $orderId,
            courseCode: $courseCode,
            courseId: $courseId,
            planId: $planId,
            clientId: $clientId,
            firstName: $firstName,
            lastName: $lastName,
            email: $email,
        );
    }

    public function isOk(): bool
    {
        return $this->state === self::OK;
    }

    public function isExpired(): bool
    {
        return $this->state === self::EXPIRED;
    }

    public function isInvalid(): bool
    {
        return $this->state === self::INVALID;
    }
}
