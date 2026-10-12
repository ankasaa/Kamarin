<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a pricing rule from BR-11 or BR-12 rejects a request:
 * no matching rate, a missing default rate, a stay below the minimum
 * nights, an overlapping rate with the same priority, or a discount
 * booked by someone who is not the owner or manager. The message names
 * the rule that refused, so callers can show a clear explanation.
 */
class PricingException extends RuntimeException {}
