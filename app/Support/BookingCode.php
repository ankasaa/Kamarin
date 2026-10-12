<?php

namespace App\Support;

use App\Models\Booking;

/**
 * Booking codes (BR-14).
 *
 * One generator for the whole application: BookingService uses it for
 * real bookings and BookingFactory uses it for test data, so test data
 * obeys the same rule instead of inventing its own "BK-" pattern.
 *
 * A code is 8 characters drawn with random_int from an alphabet without
 * the confusable 0, O, 1 and I, so a code read aloud over the phone
 * cannot be misheard, and it is never a sequence. Because
 * bookings.booking_code is unique, the loop rejects a collision against
 * any row including soft-deleted ones (the unique index still covers
 * them) and draws again.
 */
class BookingCode
{
    /**
     * Booking codes are 8 characters from this alphabet (BR-14): digits
     * 2-9 and the uppercase letters that are neither ambiguous in
     * speech nor ambiguous on screen.
     */
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LENGTH = 8;

    /**
     * A fresh, unique booking code.
     */
    public static function generate(): string
    {
        do {
            $code = '';

            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (Booking::withTrashed()->where('booking_code', $code)->exists());

        return $code;
    }
}
