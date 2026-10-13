<?php

namespace App\Enums;

/**
 * The payment status of a booking (BR-12): unpaid at zero, partial in
 * between, paid once the running total reaches total_amount.
 *
 * This is computed, never stored - arsitektur_sistem.md section 2 says
 * "Status bayar dihitung, tidak disimpan" - and bookings carry no such
 * column (ERD v2.1 BOOKINGS). PaymentService::paymentStatus() is the
 * only place that decides it.
 */
enum PaymentBalanceStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
}
