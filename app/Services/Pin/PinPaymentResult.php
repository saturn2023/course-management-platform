<?php

namespace App\Services\Pin;

/**
 * Immutable outcome of a single Pin Payments API call.
 *
 * This object only describes what Pin returned. It never touches the
 * database, never decides what to do next, and preserves the durable
 * references a caller needs for confirmation and reconciliation.
 */
class PinPaymentResult
{
    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_THREE_D_SECURE_REQUIRED = 'three_d_secure_required';
    public const OUTCOME_DECLINED = 'declined';
    public const OUTCOME_VALIDATION_FAILED = 'validation_failed';
    public const OUTCOME_TRANSPORT_FAILURE = 'transport_failure';
    public const OUTCOME_MALFORMED = 'malformed';

    public function __construct(
        public readonly string $outcome,
        public readonly ?int $httpStatus = null,
        public readonly ?bool $success = null,
        public readonly ?string $chargeToken = null,
        public readonly ?string $statusMessage = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $redirectUrl = null,
        public readonly array $rawResponse = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->outcome === self::OUTCOME_SUCCESS;
    }

    public function requiresThreeDSecure(): bool
    {
        return $this->outcome === self::OUTCOME_THREE_D_SECURE_REQUIRED;
    }

    public function isDeclined(): bool
    {
        return $this->outcome === self::OUTCOME_DECLINED;
    }

    public function isValidationFailure(): bool
    {
        return $this->outcome === self::OUTCOME_VALIDATION_FAILED;
    }

    public function isTransportFailure(): bool
    {
        return $this->outcome === self::OUTCOME_TRANSPORT_FAILURE;
    }

    public function isMalformed(): bool
    {
        return $this->outcome === self::OUTCOME_MALFORMED;
    }

    /**
     * True when the outcome is not a conclusive, trustworthy result and the
     * caller should resolve the real charge status before acting.
     */
    public function isUncertain(): bool
    {
        return in_array($this->outcome, [
            self::OUTCOME_TRANSPORT_FAILURE,
            self::OUTCOME_MALFORMED,
        ], true);
    }
}
