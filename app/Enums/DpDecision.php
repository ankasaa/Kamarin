<?php

namespace App\Enums;

/**
 * What happens to the deposit when a booking is cancelled (BR-05.2,
 * BR-05.3).
 *
 * The two words come straight from the business rules: "DP
 * dikembalikan" and "DP hangus". They are a decision a member of staff
 * makes on a cancellation, not a stored column - bookings carry no
 * deposit decision of their own (ERD v2.1 BOOKINGS). A refund, when
 * chosen, is written as a new payments row of type refund (BR-04.3).
 */
enum DpDecision: string
{
    /** DP dikembalikan: a verified refund row is written (BR-05.3). */
    case Refund = 'refund';

    /** DP hangus: no refund row, the deposit stays recorded (BR-05.3). */
    case Forfeit = 'forfeit';
}
