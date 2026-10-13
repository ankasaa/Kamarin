<?php

namespace App\Enums;

/**
 * The lifecycle of a single payments row (ERD v2.1 PAYMENTS, BR-04.3).
 *
 * Refunded is deliberately unused in the MVP: business rules v1.3 change 2
 * models a refund as a NEW row of type refund with status verified, so the
 * original row keeps its own status and the "paid" formula cannot subtract
 * the same money twice. The case stays because the status is part of the
 * ERD and AGENTS.md rule 2 forbids renaming or dropping values. See
 * PaymentService::recordRefund().
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';

    /** Not written anywhere; see the class comment above. */
    case Refunded = 'refunded';
}
