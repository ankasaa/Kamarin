<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a payment rule from BR-03, BR-04 or BR-12 refuses a
 * request: a role that may not handle payments, a deposit decision that
 * goes against the cancellation policy, or an amount that cannot exist.
 * The message names the violated rule so callers can explain it.
 */
class PaymentException extends RuntimeException {}
