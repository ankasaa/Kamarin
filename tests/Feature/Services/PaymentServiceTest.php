<?php

use App\Enums\BookingStatus;
use App\Enums\DpDecision;
use App\Enums\PaymentBalanceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RoleName;
use App\Enums\RoomStatus;
use App\Exceptions\PaymentException;
use App\Models\Booking;
use App\Models\BookingSource;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PaymentService;

// Test cases PY-01 .. PY-08 (section 4.6) of docs/rencana_pengujian.md,
// for BR-03, BR-04 and BR-12 of docs/business_rules_kamarin.md v1.3.
//
// Everything here reads through PaymentService, which holds the only
// definition of "terbayar", "sisa tagihan", "status bayar" and "DP
// wajib" (T-012). Money is whole rupiah everywhere, so every expectation
// is an int.
//
// The clock is pinned (section 2, "fixed now"): PY-06 and PY-08 compare
// today against check_in_date, and a refund row is dated with today.

function payments(): PaymentService
{
    return app(PaymentService::class);
}

/** One user per canonical role, keyed by the RoleName value. */
function pyActors(): array
{
    $actors = [];

    foreach (RoleName::cases() as $role) {
        $actors[$role->value] = User::factory()->create([
            'role_id' => Role::factory()->create(['name' => $role->value])->id,
        ]);
    }

    return $actors;
}

/** Property + room type for one test, so policy columns can be changed. */
function pyRoomType(): RoomType
{
    return RoomType::factory()->create([
        'property_id' => Property::factory()->create()->id,
    ]);
}

/**
 * A booking in $status with one room, its nights and a consistent
 * total (2 nights x 300000 by default). BookingSourceFactory only has
 * five unique names, so bookings share one source (booking_sources.name
 * is unique).
 */
function pyBooking(
    RoomType $roomType,
    BookingStatus $status = BookingStatus::Confirmed,
    array $attributes = [],
    int $pricePerNight = 300000,
): Booking {
    $room = Room::factory()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'status' => RoomStatus::Active,
    ]);

    $source = BookingSource::query()->value('id') ?? BookingSource::factory()->create()->id;

    return Booking::factory()->{match ($status) {
        BookingStatus::Pending => 'pending',
        BookingStatus::Confirmed => 'confirmed',
        BookingStatus::CheckedIn => 'checkedIn',
        BookingStatus::CheckedOut => 'checkedOut',
        BookingStatus::Cancelled => 'cancelled',
        BookingStatus::Expired => 'expired',
        BookingStatus::NoShow => 'noShow',
    }}()->createWithNights($room, $pricePerNight, array_merge([
        'property_id' => $roomType->property_id,
        'booking_source_id' => $source,
        'created_by_user_id' => null,
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ], $attributes));
}

/** A payment row placed directly, for the states staff cannot reach yet. */
function pySeedPayment(
    Booking $booking,
    int $amount,
    PaymentType $type = PaymentType::Deposit,
    PaymentStatus $status = PaymentStatus::Verified,
    PaymentMethod $method = PaymentMethod::BankTransfer,
): Payment {
    return Payment::factory()->create([
        'booking_id' => $booking->id,
        'amount' => $amount,
        'type' => $type,
        'method' => $method,
        'status' => $status,
        'payer_name' => null,
        'reference_number' => null,
    ]);
}

/**
 * Record a payment through the service and verify it, as staff would.
 * A bank transfer carries the reference the platform gives, which
 * record() now requires (BR-04.3, decision M5), so the helper has to
 * have one to hand over.
 */
function pyPaid(
    Booking $booking,
    User $actor,
    int $amount,
    PaymentType $type = PaymentType::Deposit,
    string $referenceNumber = 'TRF-2026-0615',
): Payment {
    $payment = payments()->record(
        $booking,
        $actor,
        $amount,
        $type,
        PaymentMethod::BankTransfer,
        '2026-06-15',
        null,
        $referenceNumber,
    );

    return payments()->verify($payment, $actor);
}

// PY-01 / BR-03.1: the required deposit is total x deposit_percent / 100,
// rounded to the nearest rupiah and never a float. The odd total and the
// odd percent are there on purpose: 333333 x 50% is 166666.5 and must
// come back as 166667, and any float arithmetic in the middle would be
// visible at these magnitudes.
it('PY-01: computes the required deposit and rounds it to whole rupiah (BR-03.1)', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = pyRoomType();

    // Default policy: 50% of 600000 (2 nights x 300000).
    $booking = pyBooking($roomType);
    expect(payments()->requiredDeposit($booking))->toBe(300000)->toBeInt();

    // 333333 x 50% = 166666.5 -> 166667, still an int.
    $odd = pyBooking($roomType, BookingStatus::Confirmed, [
        'check_in_date' => '2026-08-01',
        'check_out_date' => '2026-08-02',
    ], 333333);
    expect(payments()->requiredDeposit($odd))->toBe(166667)->toBeInt();

    // 600000 x 33% = 198000 exactly.
    Property::query()->findOrFail($roomType->property_id)->update(['deposit_percent' => 33]);
    expect(payments()->requiredDeposit($booking->refresh()))->toBe(198000);

    // The percent is a policy column (AGENTS.md rule 6): 0 needs nothing,
    // 100 needs the whole total.
    foreach ([0 => 0, 100 => 600000] as $percent => $expected) {
        Property::query()->findOrFail($roomType->property_id)->update(['deposit_percent' => $percent]);
        expect(payments()->requiredDeposit($booking->refresh()))->toBe($expected);
    }
});

// PY-02 / BR-12: unpaid at zero, partial in between, paid once the
// total is reached. Status bayar is computed, never stored
// (arsitektur_sistem.md section 2).
it('PY-02: reports unpaid, partial and paid from the running total (BR-12)', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = pyRoomType();
    $actor = pyActors()[RoleName::Receptionist->value];
    $booking = pyBooking($roomType); // total 600000

    expect(payments()->netPaid($booking))->toBe(0)
        ->and(payments()->balance($booking))->toBe(600000)
        ->and(payments()->paymentStatus($booking))->toBe(PaymentBalanceStatus::Unpaid)
        ->and(payments()->summary($booking)->overpaid)->toBeFalse();

    pyPaid($booking, $actor, 200000);

    expect(payments()->netPaid($booking))->toBe(200000)
        ->and(payments()->balance($booking))->toBe(400000)
        ->and(payments()->paymentStatus($booking))->toBe(PaymentBalanceStatus::Partial);

    pyPaid($booking, $actor, 400000, PaymentType::Settlement);

    expect(payments()->netPaid($booking))->toBe(600000)
        ->and(payments()->balance($booking))->toBe(0)
        ->and(payments()->paymentStatus($booking))->toBe(PaymentBalanceStatus::Paid);
});

// PY-03 / BR-04.3, BR-03.3: only verified money counts. A payment
// sitting in pending, and one that failed, are both invisible to the
// total - which is exactly what lets a bank transfer sit unconfirmed
// without pretending the guest has paid.
it('PY-03: ignores payments that are pending or failed (BR-04.3)', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = pyRoomType();
    $actor = pyActors()[RoleName::Receptionist->value];
    $booking = pyBooking($roomType); // total 600000

    // Recorded but not yet verified: still unpaid.
    $pending = payments()->record($booking, $actor, 300000, PaymentType::Deposit, PaymentMethod::BankTransfer, '2026-06-15', null, 'TRF-2026-0615-02');

    expect($pending->status)->toBe(PaymentStatus::Pending)
        ->and(payments()->netPaid($booking))->toBe(0)
        ->and(payments()->paymentStatus($booking))->toBe(PaymentBalanceStatus::Unpaid);

    // A failed payment is counted by nobody either.
    pySeedPayment($booking, 100000, PaymentType::Deposit, PaymentStatus::Failed);

    expect(payments()->netPaid($booking))->toBe(0)
        ->and(payments()->balance($booking))->toBe(600000);
});

// PY-04 / BR-12, BR-04.3: a verified refund reduces what was paid. A
// refund still sitting in pending does not, for the same reason as
// PY-03: only verified money moves the total.
it('PY-04: subtracts verified refunds from what was paid (BR-12)', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = pyRoomType();
    $actor = pyActors()[RoleName::Receptionist->value];
    $booking = pyBooking($roomType); // total 600000

    pyPaid($booking, $actor, 600000);

    expect(payments()->paymentStatus($booking))->toBe(PaymentBalanceStatus::Paid);

    pySeedPayment($booking, 200000, PaymentType::Refund, PaymentStatus::Verified);

    expect(payments()->netPaid($booking))->toBe(400000)
        ->and(payments()->balance($booking))->toBe(200000)
        ->and(payments()->paymentStatus($booking))->toBe(PaymentBalanceStatus::Partial);

    // An unverified refund changes nothing.
    pySeedPayment($booking, 100000, PaymentType::Refund, PaymentStatus::Pending);

    expect(payments()->netPaid($booking))->toBe(400000);
});

// PY-05 / BR-04.3: the payer is whoever transferred, which is often not
// the guest. payer_name is recorded as given and never matched against
// the guest row.
it('PY-05: accepts a payer name that differs from the guest (BR-04.3)', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = pyRoomType();
    $actor = pyActors()[RoleName::Receptionist->value];
    $booking = pyBooking($roomType);

    $payment = payments()->record(
        $booking,
        $actor,
        300000,
        PaymentType::Deposit,
        PaymentMethod::BankTransfer,
        '2026-06-15',
        'Siti Rahayu (sister of the guest)',
        'TRF-2026-0615-01',
    );
    payments()->verify($payment, $actor);

    $stored = $booking->payments()->sole();

    expect($stored->payer_name)->toBe('Siti Rahayu (sister of the guest)')
        ->and($stored->reference_number)->toBe('TRF-2026-0615-01')
        ->and((string) $stored->payment_date)->toStartWith('2026-06-15')
        ->and($stored->recorded_by_user_id)->toBe($actor->id)
        ->and($stored->status)->toBe(PaymentStatus::Verified)
        ->and(payments()->netPaid($booking))->toBe(300000);
});

// PY-06 / BR-05.2: the advice is decided by whole days between today and
// check_in_date against the property's free_cancel_days. Exactly on the
// boundary the deposit is still returned; one day closer it is forfeit.
// free_cancel_days is a policy column (AGENTS.md rule 6), so the second
// half moves it to 5 and the boundary moves with it.
it('PY-06: advises refund on the free-cancel boundary and forfeit one day closer (BR-05.2)', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = pyRoomType(); // free_cancel_days defaults to 2

    $onBoundary = pyBooking($roomType, BookingStatus::Confirmed, [
        'check_in_date' => '2026-06-17',
        'check_out_date' => '2026-06-19',
    ]);
    $closer = pyBooking($roomType, BookingStatus::Confirmed, [
        'check_in_date' => '2026-06-16',
        'check_out_date' => '2026-06-18',
    ]);
    $sameDay = pyBooking($roomType, BookingStatus::Confirmed, [
        'check_in_date' => '2026-06-15',
        'check_out_date' => '2026-06-17',
    ]);

    expect(payments()->cancellationAdvice($onBoundary))->toBe(DpDecision::Refund)
        ->and(payments()->cancellationAdvice($closer))->toBe(DpDecision::Forfeit)
        ->and(payments()->cancellationAdvice($sameDay))->toBe(DpDecision::Forfeit);

    Property::query()->findOrFail($roomType->property_id)->update(['free_cancel_days' => 5]);

    $stillInside = pyBooking($roomType, BookingStatus::Confirmed, [
        'check_in_date' => '2026-06-20',
        'check_out_date' => '2026-06-22',
    ]);
    $nowOutside = pyBooking($roomType, BookingStatus::Confirmed, [
        'check_in_date' => '2026-06-19',
        'check_out_date' => '2026-06-21',
    ]);

    expect(payments()->cancellationAdvice($stillInside))->toBe(DpDecision::Refund)
        ->and(payments()->cancellationAdvice($nowOutside))->toBe(DpDecision::Forfeit);
});

// PY-07 / BR-04.3, BR-05.3: cancelling with the refund decision writes
// exactly one new refund row, verified, for the net deposit - verified
// deposit and settlement minus refunds already recorded. The original
// rows keep their own status, so the money is subtracted once, not
// twice (v1.3 change 2).
it('PY-07: records one verified refund for the net deposit and leaves the original rows alone (BR-05.3)', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = pyRoomType();
    $actors = pyActors();
    $receptionist = $actors[RoleName::Receptionist->value];

    $booking = pyBooking($roomType, BookingStatus::Confirmed, [
        'check_in_date' => '2026-06-20',
        'check_out_date' => '2026-06-22',
    ]);

    // 400000 came in as a deposit, 100000 has already gone back: the
    // net deposit is 300000.
    pySeedPayment($booking, 400000, PaymentType::Deposit, PaymentStatus::Verified);
    pySeedPayment($booking, 100000, PaymentType::Refund, PaymentStatus::Verified);

    expect(payments()->netPaid($booking))->toBe(300000)
        ->and(payments()->cancellationAdvice($booking))->toBe(DpDecision::Refund);

    app(BookingService::class)->cancel(
        $booking,
        $receptionist,
        'Guest cancelled by phone.',
        DpDecision::Refund,
    );

    $refund = $booking->payments()->where('type', PaymentType::Refund->value)->orderByDesc('id')->first();

    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->payments()->count())->toBe(3)
        ->and($refund->amount)->toBe(300000)
        ->and($refund->status)->toBe(PaymentStatus::Verified)
        ->and($refund->recorded_by_user_id)->toBe($receptionist->id)
        ->and((string) $refund->payment_date)->toStartWith('2026-06-15')
        // BR-04.3 / v1.3: the original rows keep their own status.
        ->and($booking->payments()->where('type', PaymentType::Deposit->value)->sole()->status)
        ->toBe(PaymentStatus::Verified)
        ->and($booking->payments()->where('type', PaymentType::Refund->value)->where('id', '!=', $refund->id)->sole()->status)
        ->toBe(PaymentStatus::Verified)
        // Nothing is owed any more, and the booking is not overpaid.
        ->and(payments()->netPaid($booking))->toBe(0)
        ->and(payments()->balance($booking))->toBe(600000)
        ->and(payments()->paymentStatus($booking))->toBe(PaymentBalanceStatus::Unpaid)
        ->and(payments()->summary($booking)->overpaid)->toBeFalse();
});

// PY-07 continues: a booking with no verified money has no deposit to
// return, so cancelling it creates no refund row and asks no questions.
it('PY-07: cancels a booking that never paid anything without writing a refund', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = pyRoomType();
    $receptionist = pyActors()[RoleName::Receptionist->value];

    $booking = pyBooking($roomType, BookingStatus::Confirmed, [
        'check_in_date' => '2026-06-20',
        'check_out_date' => '2026-06-22',
    ]);

    app(BookingService::class)->cancel($booking, $receptionist, 'Guest cancelled by phone.');

    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->payments()->count())->toBe(0)
        ->and(payments()->netPaid($booking))->toBe(0);
});

// PY-08 / BR-05.2 and Keterangan 2: a receptionist may not cancel
// against the advice, owner and manager may. A refusal happens before
// anything is written, so the booking, its payments and its log stay
// exactly as they were.
it('PY-08: refuses a receptionist who cancels against the advice and allows owner and manager', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = pyRoomType();
    $actors = pyActors();
    $service = app(BookingService::class);

    // Booked far enough out that the advice is "return the deposit".
    $farOut = fn (): Booking => pyBooking($roomType, BookingStatus::Confirmed, [
        'check_in_date' => '2026-06-20',
        'check_out_date' => '2026-06-22',
    ]);

    // (a) Receptionist forfeits against a refund advice: refused, and
    // the whole attempt is rolled back.
    $booking = $farOut();
    pySeedPayment($booking, 300000, PaymentType::Deposit, PaymentStatus::Verified);

    expect(fn () => $service->cancel($booking, $actors[RoleName::Receptionist->value], 'Guest cancelled.', DpDecision::Forfeit))
        ->toThrow(PaymentException::class, 'owner or the manager');

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->payments()->count())->toBe(1)
        ->and($booking->bookingStatusLogs()->count())->toBe(0)
        ->and($booking->bookingNights()->where('is_active', true)->count())->toBe(2);

    // (b) Owner and manager may deviate: cancelled, no refund written.
    foreach ([RoleName::Owner, RoleName::Manager] as $role) {
        $deviated = $farOut();
        pySeedPayment($deviated, 300000, PaymentType::Deposit, PaymentStatus::Verified);

        $service->cancel($deviated, $actors[$role->value], 'Guest cancelled.', DpDecision::Forfeit);

        expect($deviated->refresh()->status)->toBe(BookingStatus::Cancelled)
            ->and($deviated->payments()->where('type', PaymentType::Refund->value)->count())->toBe(0)
            ->and($deviated->payments()->where('type', PaymentType::Deposit->value)->sole()->status)
            ->toBe(PaymentStatus::Verified)
            ->and(payments()->netPaid($deviated))->toBe(300000);
    }

    // (c) The other direction: check-in is tomorrow, so the advice is
    // "forfeit". A receptionist refunding against it is refused; the
    // owner gets the refund row.
    $soon = fn (): Booking => pyBooking($roomType, BookingStatus::Confirmed, [
        'check_in_date' => '2026-06-16',
        'check_out_date' => '2026-06-18',
    ]);

    $refused = $soon();
    pySeedPayment($refused, 300000, PaymentType::Deposit, PaymentStatus::Verified);

    expect(payments()->cancellationAdvice($refused))->toBe(DpDecision::Forfeit)
        ->and(fn () => $service->cancel($refused, $actors[RoleName::Receptionist->value], 'Guest cancelled.', DpDecision::Refund))
        ->toThrow(PaymentException::class, 'owner or the manager');

    expect($refused->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($refused->payments()->count())->toBe(1);

    $byOwner = $soon();
    pySeedPayment($byOwner, 300000, PaymentType::Deposit, PaymentStatus::Verified);

    $service->cancel($byOwner, $actors[RoleName::Owner->value], 'Guest cancelled.', DpDecision::Refund);

    expect($byOwner->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($byOwner->payments()->where('type', PaymentType::Refund->value)->sole()->amount)->toBe(300000)
        ->and(payments()->netPaid($byOwner))->toBe(0);
});

// BR-04.3: recording and verifying are staff actions, and housekeeping
// is not a booking or money role (section 5 preamble). A role outside
// the canonical four is refused the same way, because users.role_id is
// not nullable and "unknown" has to be a real row.
it('BR-04.3: lets owner, manager and receptionist handle payments and refuses everyone else', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = pyRoomType();
    $actors = pyActors();
    $booking = pyBooking($roomType);

    foreach ([RoleName::Owner, RoleName::Manager, RoleName::Receptionist] as $role) {
        $payment = payments()->record($booking, $actors[$role->value], 100000, PaymentType::Deposit, PaymentMethod::Cash, '2026-06-15');

        expect($payment->status)->toBe(PaymentStatus::Pending)
            ->and(payments()->verify($payment, $actors[$role->value])->status)->toBe(PaymentStatus::Verified);
    }

    expect(fn () => payments()->record($booking, $actors[RoleName::Housekeeping->value], 100000, PaymentType::Deposit, PaymentMethod::Cash, '2026-06-15'))
        ->toThrow(PaymentException::class, 'housekeeping');

    $pending = payments()->record($booking, $actors[RoleName::Receptionist->value], 50000, PaymentType::Deposit, PaymentMethod::Cash, '2026-06-15');

    expect(fn () => payments()->verify($pending, $actors[RoleName::Housekeeping->value]))
        ->toThrow(PaymentException::class, 'housekeeping');

    // The refusal left the payment exactly as it was.
    expect($pending->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($booking->payments()->count())->toBe(4);
});

// BR-04.3, second half: a role outside the canonical four is refused by
// the same door as housekeeping. users.role_id is not nullable, so
// "unknown" has to be a real role row whose name is not one of the four
// values of RoleName. Without this case the allow-list could silently
// admit everyone - which is what the review of T-012 caught.
it('BR-04.3: refuses a role that is not one of the canonical four', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = pyRoomType();
    $receptionist = pyActors()[RoleName::Receptionist->value];
    $booking = pyBooking($roomType);

    $stranger = User::factory()->create([
        'role_id' => Role::factory()->create(['name' => 'night_audit'])->id,
    ]);

    // The fixture really is outside RoleName, or the test would prove
    // nothing.
    expect(RoleName::tryFrom((string) $stranger->role?->name))->toBeNull();

    expect(fn () => payments()->record($booking, $stranger, 100000, PaymentType::Deposit, PaymentMethod::Cash, '2026-06-15'))
        ->toThrow(PaymentException::class, 'night_audit');

    $pending = payments()->record($booking, $receptionist, 150000, PaymentType::Deposit, PaymentMethod::Cash, '2026-06-15');

    expect(fn () => payments()->verify($pending, $stranger))
        ->toThrow(PaymentException::class, 'night_audit');

    // Both refusals wrote nothing at all.
    expect($pending->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($booking->payments()->count())->toBe(1);
});

// BR-04.3: verification is the second act, not a repeat of the first.
// The rule defines the flow pending -> verified or failed, so only a
// pending row can be verified. Without this, clicking "verify" twice on
// the same payment would silently re-approve money that may already have
// failed or been refunded.
it('BR-04.3: only verifies a payment that is still pending', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = pyRoomType();
    $actor = pyActors()[RoleName::Receptionist->value];
    $booking = pyBooking($roomType);

    // Already verified: refused, and the row is untouched.
    $verified = pyPaid($booking, $actor, 100000);

    expect(fn () => payments()->verify($verified->fresh(), $actor))
        ->toThrow(PaymentException::class, 'Only a pending payment can be verified; this one is verified');

    // Failed: refused as well.
    $failed = pySeedPayment($booking, 100000, PaymentType::Deposit, PaymentStatus::Failed);

    expect(fn () => payments()->verify($failed, $actor))
        ->toThrow(PaymentException::class, 'this one is failed');

    // Refused both times without moving a single rupiah.
    expect($verified->refresh()->status)->toBe(PaymentStatus::Verified)
        ->and($failed->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and($booking->payments()->count())->toBe(2)
        ->and(payments()->netPaid($booking))->toBe(100000);
});

// BR-12.3: paying more than the total is flagged as an overpayment
// rather than clamped, so staff can see it and settle it with a refund
// or a note.
it('BR-12.3: flags an overpayment instead of clamping it', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = pyRoomType();
    $actor = pyActors()[RoleName::Receptionist->value];
    $booking = pyBooking($roomType); // total 600000

    pyPaid($booking, $actor, 700000);

    $summary = payments()->summary($booking);

    expect($summary->paid)->toBe(700000)
        ->and($summary->balance)->toBe(-100000)
        ->and($summary->totalAmount)->toBe(600000)
        ->and($summary->requiredDeposit)->toBe(300000)
        ->and($summary->status)->toBe(PaymentBalanceStatus::Paid)
        ->and($summary->overpaid)->toBeTrue();
});

// BR-04.3, decision M4 on Pertanyaan Terbuka no. 4: recording a refund by
// hand is the one payment type that moves money out of the till, so it
// belongs to the owner and the manager. A receptionist keeps the everyday
// work - deposits and settlements - and gets refunds through the
// cancellation flow instead, where the advice and the deviation gate of
// BR-05.2 already apply (PY-07, PY-08). Without this, one receptionist
// could hand money back for any amount with no reason and no log, which
// is exactly the gap the review of T-012 opened.
it('BR-04.3: reserves a manual refund for the owner and the manager', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = pyRoomType();
    $actors = pyActors();
    $booking = pyBooking($roomType); // total 600000
    $receptionist = $actors[RoleName::Receptionist->value];

    // A receptionist still records the money that comes in, and the
    // everyday two-step is unchanged: record it, then verify it.
    $deposit = payments()->record($booking, $receptionist, 300000, PaymentType::Deposit, PaymentMethod::Cash, '2026-06-15');

    expect($deposit->status)->toBe(PaymentStatus::Pending);

    payments()->verify($deposit, $receptionist);
    payments()->verify(
        payments()->record($booking, $receptionist, 100000, PaymentType::Settlement, PaymentMethod::Cash, '2026-06-15'),
        $receptionist,
    );

    expect(payments()->verifiedDeposit($booking))->toBe(400000)
        ->and(payments()->netPaid($booking))->toBe(400000);

    // The same receptionist is refused a refund, and the refusal writes
    // no row at all.
    expect(fn () => payments()->record(
        $booking,
        $receptionist,
        100000,
        PaymentType::Refund,
        PaymentMethod::BankTransfer,
        '2026-06-15',
        null,
        'TRF-2026-0615-R',
    ))->toThrow(PaymentException::class, 'owner or the manager');

    expect($booking->payments()->where('type', PaymentType::Refund->value)->count())->toBe(0)
        ->and(payments()->netPaid($booking))->toBe(400000);

    // Owner and manager may record one by hand. A refund is the one
    // exception to the two-step: BR-04.3 bullet 3 and v1.3 change 2 say
    // a refund is "verified when recorded", because it represents money
    // that has already left the till - so no second click is waiting and
    // terbayar drops at once.
    $owner = $actors[RoleName::Owner->value];
    $manager = $actors[RoleName::Manager->value];

    $byOwner = payments()->record($booking, $owner, 100000, PaymentType::Refund, PaymentMethod::BankTransfer, '2026-06-15', null, 'TRF-2026-0615-owner');

    expect($byOwner->type)->toBe(PaymentType::Refund)
        ->and($byOwner->status)->toBe(PaymentStatus::Verified)
        ->and($byOwner->recorded_by_user_id)->toBe($owner->id)
        // Already out of the till: terbayar drops without a verify().
        ->and(payments()->netPaid($booking))->toBe(300000);

    // There is nothing left to verify, which is exactly the point.
    expect(fn () => payments()->verify($byOwner, $owner))
        ->toThrow(PaymentException::class, 'Only a pending payment can be verified; this one is verified');

    $byManager = payments()->record($booking, $manager, 50000, PaymentType::Refund, PaymentMethod::BankTransfer, '2026-06-15', null, 'TRF-2026-0615-manager');

    expect($byManager->status)->toBe(PaymentStatus::Verified)
        ->and($byManager->recorded_by_user_id)->toBe($manager->id)
        ->and(payments()->netPaid($booking))->toBe(250000);

    // 400000 came in, 150000 went back out, and the refused attempt by
    // the receptionist left every figure exactly where it was. The
    // deposit and settlement are still the only rows that needed a
    // verify() before counting.
    expect($booking->payments()->count())->toBe(4)
        ->and($booking->payments()->where('type', PaymentType::Refund->value)->count())->toBe(2)
        ->and(payments()->verifiedDeposit($booking))->toBe(400000)
        ->and(payments()->netPaid($booking))->toBe(250000)
        ->and(payments()->balance($booking))->toBe(350000);
});

// BR-04.3, decision M5 on Pertanyaan Terbuka no. 5: a bank transfer and
// an OTA payout cannot be traced back to the platform without the
// reference it gave, so record() refuses to store one that is empty.
// Cash and card/QRIS are handed over at the desk and need no reference,
// which matches the ERD leaving the column nullable.
it('BR-04.3: refuses a bank transfer or OTA payment recorded without a reference number', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = pyRoomType();
    $actor = pyActors()[RoleName::Receptionist->value];
    $booking = pyBooking($roomType);

    // Missing, blank and whitespace-only are all the same refusal, and
    // none of them writes a row.
    foreach ([null, '', '   '] as $reference) {
        foreach ([PaymentMethod::BankTransfer, PaymentMethod::Ota] as $method) {
            expect(fn () => payments()->record(
                $booking,
                $actor,
                300000,
                PaymentType::Deposit,
                $method,
                '2026-06-15',
                null,
                $reference,
            ))->toThrow(PaymentException::class, 'needs a reference number');
        }
    }

    expect($booking->payments()->count())->toBe(0)
        ->and(payments()->netPaid($booking))->toBe(0);

    // The same transfer with its reference is accepted.
    $transfer = payments()->record(
        $booking,
        $actor,
        300000,
        PaymentType::Deposit,
        PaymentMethod::BankTransfer,
        '2026-06-15',
        'Siti Rahayu',
        'TRF-2026-0615-03',
    );

    expect($transfer->reference_number)->toBe('TRF-2026-0615-03');

    // Cash and card/QRIS stay optional: the money is counted at the
    // desk, and an absent reference is stored as null rather than as an
    // empty string.
    foreach ([PaymentMethod::Cash, PaymentMethod::CardOrQris] as $method) {
        expect(payments()->record($booking, $actor, 100000, PaymentType::Settlement, $method, '2026-06-15', null, null)->reference_number)
            ->toBeNull()
            ->and(payments()->record($booking, $actor, 100000, PaymentType::Settlement, $method, '2026-06-15', null, '')->reference_number)
            ->toBeNull();
    }

    expect($booking->payments()->count())->toBe(5);
});
