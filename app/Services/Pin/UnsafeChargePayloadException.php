<?php

namespace App\Services\Pin;

use RuntimeException;

/**
 * Thrown before any HTTP request is sent when a charge payload contains a
 * known raw-card field. This server-side service must only ever transmit an
 * opaque card_token, never raw card data.
 */
class UnsafeChargePayloadException extends RuntimeException
{
    public function __construct(public readonly string $forbiddenKey)
    {
        parent::__construct(
            "Refusing to send charge: raw card field '{$forbiddenKey}' must never reach the Pin charge request."
        );
    }
}
