<?php

namespace App\Services\Xero;

/**
 * A Xero failure that will never succeed on retry: a 400 validation error
 * (bad AccountCode, bad TaxType, malformed payload), a 404, or a 401/403 that
 * survived a token refresh and so needs a human to reconnect Xero.
 *
 * Jobs must catch this, record it, and fail immediately rather than burning
 * their remaining attempts re-sending a request Xero has already rejected.
 */
class XeroPermanentException extends XeroException
{
}
