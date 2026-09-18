<?php

namespace App\Services\Xero;

/**
 * A Xero failure that may resolve on its own: a 5xx, a 429 rate limit, or a
 * network error. Jobs should let this propagate so the queue worker retries
 * it on the configured backoff.
 */
class XeroTransientException extends XeroException
{
}
