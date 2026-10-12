<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a booking cannot be created for a rule that is neither
 * availability (BR-01) nor pricing (BR-11/BR-12): for example a missing
 * booking source (BR-09). The message names the violated rule so callers
 * can show a clear explanation to the user.
 */
class BookingException extends RuntimeException {}
