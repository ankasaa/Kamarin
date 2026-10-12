<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a room availability rule from BR-01 or BR-07 rejects a
 * request. The message always names the room and the rule that
 * refused, so callers can show a clear explanation to the user.
 */
class AvailabilityException extends RuntimeException {}
