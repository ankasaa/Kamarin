<?php

namespace App\Support;

use App\Enums\PaymentBalanceStatus;

/**
 * Everything the payment screen needs about one booking, computed in a
 * single pass (BR-12, US-14).
 *
 * Nothing here is stored: arsitektur_sistem.md section 2 decides that
 * "status bayar dihitung, tidak disimpan", so total, paid, balance,
 * status and the overpayment flag are all derived from
 * booking_nights/booking_charges and payments every time they are read.
 * overpaid is BR-12.3: paying more than the total is reported rather
 * than clamped, until staff record a refund or a settlement note.
 */
final class PaymentSummary
{
    public function __construct(
        public readonly int $totalAmount,
        public readonly int $requiredDeposit,
        public readonly int $paid,
        public readonly int $balance,
        public readonly PaymentBalanceStatus $status,
        public readonly bool $overpaid,
    ) {}
}
