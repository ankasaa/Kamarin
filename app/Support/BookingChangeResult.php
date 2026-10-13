<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingChangeLog;

/**
 * What one booking change produced: the booking, the total it had
 * before, the total it has now, the payment picture of BR-12 and the
 * single booking_change_logs row written for the change (ERD v2.1).
 *
 * A preview returns the same shape with log left null and the totals
 * computed without writing anything, which is what lets one screen show
 * "selisih total, sisa tagihan dan peringatan kelebihan bayar" before
 * staff commit to the change (docs/seq_perubahan_booking.mermaid).
 */
final class BookingChangeResult
{
    /**
     * BR-12.3: more money has been paid than the new total owes, for
     * example after a stay is shortened. It is reported rather than
     * clamped, until staff record a refund or a settlement note.
     */
    public const WARN_OVERPAID = 'overpaid';

    public function __construct(
        public readonly Booking $booking,
        public readonly int $oldTotal,
        public readonly int $newTotal,
        public readonly PaymentSummary $payments,
        /** @var array<int, string> */
        public readonly array $warnings = [],
        public readonly ?BookingChangeLog $log = null,
    ) {}
}
