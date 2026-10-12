<?php

namespace App\Support;

use App\Models\Booking;

/**
 * What a status transition produced (T-011): the booking in its new
 * status plus any non-blocking warnings the caller should surface.
 *
 * The transition matrix warns but never blocks on two conditions
 * (BR-04.2 a balance still due, BR-07.5 a room that is not "ready"),
 * so the warnings have to travel back with the booking instead of being
 * thrown as exceptions. Every transition returns this, which keeps the
 * six public methods of BookingService uniform.
 */
final class BookingTransitionResult
{
    /**
     * @param  array<int, string>  $warnings  human-readable, English, never fatal
     */
    public function __construct(
        public readonly Booking $booking,
        public readonly array $warnings = [],
    ) {}
}
