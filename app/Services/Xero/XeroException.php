<?php

namespace App\Services\Xero;

use RuntimeException;

/**
 * Base class for every failure raised by XeroService.
 *
 * Callers should not catch this directly. Catch the two subclasses instead:
 * the distinction between them is what tells a queued job whether retrying
 * could ever succeed.
 *
 * @see XeroTransientException  retry — the same request may succeed later
 * @see XeroPermanentException  do not retry — the same request will always fail
 */
abstract class XeroException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $responseBody = null
    ) {
        parent::__construct($message, $status ?? 0);
    }
}
