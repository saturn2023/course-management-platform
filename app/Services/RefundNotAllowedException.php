<?php

namespace App\Services;

use RuntimeException;

/**
 * Thrown when a refund would breach the order's refundable balance rules.
 */
class RefundNotAllowedException extends RuntimeException
{
}
