<?php

namespace App\Services;

use App\Enums\DpDecision;
use App\Enums\PaymentBalanceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RoleName;
use App\Exceptions\PaymentException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentSummary;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The one definition of "how much was paid" for a booking (T-012).
 *
 * Before this class existed, BookingService carried two private queries
 * (T-011 notes marked them as temporary). Having a single definition
 * matters because three different rules read the same numbers: BR-03
 * gates a bank-transfer confirmation on the deposit, BR-04 warns about a
 * balance still due at check-in and check-out, and BR-12 turns the same
 * figures into the payment status shown to staff. Where those disagree
 * the guest is charged twice or let in for free, so every one of them
 * goes through here.
 *
 * Only verified money counts (BR-03.3, BR-04.3, "Hanya pembayaran
 * verified yang dihitung" in the T-012 acceptance criteria): a payment
 * still pending, or one that failed, is invisible to all of these
 * figures. That is what lets a bank transfer sit unconfirmed for a day
 * without the books claiming the guest has paid.
 *
 * Refunds follow v1.3 change 2: a refund is a NEW payments row of type
 * refund, written verified. The original deposit row keeps its own
 * status, so the money is subtracted exactly once. PaymentStatus
 * Refunded is deliberately never written.
 *
 * Money is whole rupiah (decimal(12,0), AGENTS.md rule 5) and is never
 * computed through a float: the deposit percentage is applied with
 * integer division plus an explicit remainder test.
 */
class PaymentService
{
    /**
     * BR-03.1: DP wajib = total_amount x deposit_percent / 100, rounded
     * to the nearest rupiah. The percent is a policy column on
     * properties (AGENTS.md rule 6), never a constant.
     *
     * Integer arithmetic only. The product is divided by 100 with
     * intdiv() and the remainder decides the rounding, so no float is
     * ever involved (AGENTS.md rule 5) and no rounding mode of a float
     * function can drift on large totals.
     */
    public function requiredDeposit(Booking $booking): int
    {
        $product = (int) $booking->total_amount * (int) $booking->property->deposit_percent;

        $deposit = intdiv($product, 100);

        // Round half up: a remainder of 50 or more belongs to the next
        // rupiah (333333 x 50% = 16666650 -> 166667, not 166666).
        return ($product % 100) * 2 >= 100 ? $deposit + 1 : $deposit;
    }

    /**
     * Verified deposit and settlement payments, before refunds
     * (BR-03.3). This is the "DP terverifikasi" the confirmation gate
     * asks for: what actually arrived, ignoring pending and failed rows.
     */
    public function verifiedDeposit(Booking $booking): int
    {
        return (int) Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', PaymentStatus::Verified->value)
            ->whereIn('type', [PaymentType::Deposit->value, PaymentType::Settlement->value])
            ->sum('amount');
    }

    /**
     * BR-12 "Terbayar": verified deposit and settlement payments minus
     * verified refunds. A refund that is still pending has not left the
     * building yet, so it does not reduce what was paid (PY-04).
     */
    public function netPaid(Booking $booking): int
    {
        $refunded = (int) Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', PaymentStatus::Verified->value)
            ->where('type', PaymentType::Refund->value)
            ->sum('amount');

        return $this->verifiedDeposit($booking) - $refunded;
    }

    /** BR-12: total_amount - terbayar. Negative means overpaid (BR-12.3). */
    public function balance(Booking $booking): int
    {
        return (int) $booking->total_amount - $this->netPaid($booking);
    }

    /**
     * BR-12 "Status bayar", computed and never stored
     * (arsitektur_sistem.md section 2). A booking whose total is still
     * zero counts as unpaid, because nothing has been paid yet.
     */
    public function paymentStatus(Booking $booking): PaymentBalanceStatus
    {
        $paid = $this->netPaid($booking);
        $total = (int) $booking->total_amount;

        if ($paid <= 0) {
            return PaymentBalanceStatus::Unpaid;
        }

        return $paid < $total
            ? PaymentBalanceStatus::Partial
            : PaymentBalanceStatus::Paid;
    }

    /** Everything one booking screen needs, in a single read (BR-12, US-14). */
    public function summary(Booking $booking): PaymentSummary
    {
        $total = (int) $booking->total_amount;
        $paid = $this->netPaid($booking);

        return new PaymentSummary(
            totalAmount: $total,
            requiredDeposit: $this->requiredDeposit($booking),
            paid: $paid,
            balance: $total - $paid,
            status: $this->paymentStatus($booking),
            overpaid: $paid > $total,
        );
    }

    /**
     * BR-04.3: staff record a payment by hand - amount, type, method,
     * payer name, date and reference. It is stored as pending, because
     * the rule defines an explicit flow pending -> verified or failed:
     * recording is not the same act as confirming the money arrived.
     *
     * @throws PaymentException when the role may not handle payments or
     *                          the amount cannot exist
     */
    public function record(
        Booking $booking,
        User $actor,
        int $amount,
        PaymentType $type,
        PaymentMethod $method,
        CarbonInterface|string $paymentDate,
        ?string $payerName = null,
        ?string $referenceNumber = null,
    ): Payment {
        $this->assertMayHandlePayments($actor);

        if ($amount <= 0) {
            throw new PaymentException('A payment amount must be a positive number of rupiah.');
        }

        return Payment::query()->create([
            'booking_id' => $booking->id,
            'recorded_by_user_id' => $actor->id,
            'amount' => $amount,
            'type' => $type,
            'method' => $method,
            'status' => PaymentStatus::Pending,
            'payer_name' => $payerName,
            'payment_date' => $paymentDate,
            'reference_number' => $referenceNumber,
            'proof_path' => null,
        ]);
    }

    /**
     * BR-04.3: pending -> verified, run by staff. The payment keeps its
     * recorded_by_user_id from the person who entered it; verifying is
     * the moment the amount starts counting towards terbayar.
     *
     * @throws PaymentException when the role may not handle payments, or
     *                          when the payment already left pending
     */
    public function verify(Payment $payment, User $actor): Payment
    {
        $this->assertMayHandlePayments($actor);

        if ($payment->status !== PaymentStatus::Pending) {
            throw new PaymentException(sprintf(
                'Only a pending payment can be verified; this one is %s (BR-04.3).',
                $payment->status->value,
            ));
        }

        $payment->update(['status' => PaymentStatus::Verified]);

        return $payment;
    }

    /**
     * BR-05.2: what the system advises for this cancellation. Whole days
     * between today and check_in_date, compared against the property's
     * free_cancel_days (AGENTS.md rule 6). Exactly on the boundary the
     * deposit is still returned; one day closer it is forfeit (PY-06).
     */
    public function cancellationAdvice(Booking $booking): DpDecision
    {
        $today = CarbonImmutable::parse(now()->toDateString());
        $checkIn = CarbonImmutable::parse($booking->check_in_date->toDateString());

        $daysUntilCheckIn = (int) $today->diffInDays($checkIn, false);

        return $daysUntilCheckIn >= (int) $booking->property->free_cancel_days
            ? DpDecision::Refund
            : DpDecision::Forfeit;
    }

    /**
     * BR-05.2 and Keterangan 2 of the status matrix: the deposit
     * decision taken on a cancellation, plus the refund row when the
     * decision is to return the money.
     *
     * Called by BookingService inside the cancellation transaction, so a
     * refusal rolls the cancellation back with it and a refund can never
     * exist for a booking that stayed confirmed (PY-08).
     *
     * No decision given means "follow the advice", which is the only
     * choice a receptionist has anyway. Deviating from the advice is
     * reserved for the owner and the manager.
     *
     * A booking with no verified money has no deposit to treat, so there
     * is nothing to decide and no refund to write (PY-07).
     *
     * @return Payment|null the refund row, when one was due
     *
     * @throws PaymentException when a receptionist deviates from the advice
     */
    public function settleCancellation(Booking $booking, ?User $actor, ?DpDecision $decision): ?Payment
    {
        $netDeposit = $this->netPaid($booking);

        if ($netDeposit <= 0) {
            return null;
        }

        $advice = $this->cancellationAdvice($booking);
        $decision ??= $advice;

        if ($decision !== $advice && ! $this->mayDeviateFromAdvice($actor)) {
            throw new PaymentException(sprintf(
                'Cancelling with a deposit decision of "%s" goes against the advice "%s"; only the owner or the manager may deviate (BR-05.2, Keterangan 2).',
                $decision->value,
                $advice->value,
            ));
        }

        if ($decision === DpDecision::Forfeit) {
            // BR-05.3: a forfeited deposit is marked by the absence of a
            // refund. The deposit row stays exactly as it was recorded.
            return null;
        }

        return $this->recordRefund($booking, $actor, $netDeposit);
    }

    /**
     * BR-04.3, BR-05.3: the refund row itself. Written verified, because
     * a refund that is still pending has not been paid out yet and must
     * not reduce terbayar.
     *
     * The money goes back the way it came: the method is copied from the
     * newest verified payment of this booking, so a cash deposit is not
     * refunded as a bank transfer. There is no payer name on a refund,
     * the booking pays out rather than takes in.
     */
    private function recordRefund(Booking $booking, ?User $actor, int $amount): Payment
    {
        $incoming = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', PaymentStatus::Verified->value)
            ->whereIn('type', [PaymentType::Deposit->value, PaymentType::Settlement->value])
            ->orderByDesc('id')
            ->first();

        return Payment::query()->create([
            'booking_id' => $booking->id,
            'recorded_by_user_id' => $actor?->id,
            'amount' => $amount,
            'type' => PaymentType::Refund,
            'method' => $incoming?->method ?? PaymentMethod::BankTransfer,
            'status' => PaymentStatus::Verified,
            'payer_name' => null,
            'payment_date' => now()->toDateString(),
            'reference_number' => null,
            'proof_path' => null,
        ]);
    }

    /**
     * BR-04.3 and section 5 of the matrix: payments are recorded and
     * verified by owner, manager and receptionist. Housekeeping has no
     * booking or money duties at all, and a role outside the canonical
     * four is refused the same way (users.role_id is not nullable, so
     * "unknown" has to be a real row).
     *
     * @throws PaymentException
     */
    private function assertMayHandlePayments(User $actor): void
    {
        $role = RoleName::tryFrom((string) $actor->role?->name);

        if ($role === null || in_array($role, [
            RoleName::Owner,
            RoleName::Manager,
            RoleName::Receptionist,
        ], true)) {
            return;
        }

        throw new PaymentException(sprintf(
            'Role "%s" may not record or verify payments (BR-04.3).',
            $actor->role?->name ?? 'nobody',
        ));
    }

    /** Keterangan 2: only the owner and the manager may deviate. */
    private function mayDeviateFromAdvice(?User $actor): bool
    {
        $role = $actor === null ? null : RoleName::tryFrom((string) $actor->role?->name);

        return $role === RoleName::Owner || $role === RoleName::Manager;
    }
}
