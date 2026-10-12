<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case NoShow = 'no_show';

    /**
     * Whether bookings in this status keep their booking_nights marked
     * active, holding room inventory. Active nights block other bookings
     * for the same room and date (BR-01, enforced by the partial unique
     * index booking_nights_room_stay_active_unique).
     */
    public function holdsNights(): bool
    {
        return in_array($this, [
            self::Pending,
            self::Confirmed,
            self::CheckedIn,
            self::CheckedOut,
        ], true);
    }

    /**
     * Whether bookings in this status count as sold room-nights for
     * occupancy and revenue metrics (BR-10: confirmed, checked_in and
     * checked_out only; pending is excluded).
     */
    public function countsAsSold(): bool
    {
        return in_array($this, [
            self::Confirmed,
            self::CheckedIn,
            self::CheckedOut,
        ], true);
    }
}
