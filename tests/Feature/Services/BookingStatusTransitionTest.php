<?php

use App\Enums\BookingStatus;
use App\Enums\HousekeepingStatus as Cleanliness;
use App\Enums\PaymentOption;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RoleName;
use App\Enums\RoomStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingSource;
use App\Models\Guest;
use App\Models\HousekeepingStatus;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Rate;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Support\Carbon;

// Test cases ST-01 .. ST-09 (section 4.4) and EX-01 .. EX-04, EX-06
// (section 4.7) of docs/rencana_pengujian.md, for the transition matrix
// of docs/business_rules_kamarin.md section 5 together with BR-03,
// BR-04, BR-05, BR-07, BR-08 and BR-13. EX-05 is deliberately absent:
// it exercises the expiry scheduler, which arrives with T-014.
//
// Every test pins the clock (docs/rencana_pengujian.md section 2,
// "fixed now"), so "today", the no-show cutoff and the expiry deadline
// are all reproducible.

function transitions(): BookingService
{
    return app(BookingService::class);
}

/** One user per canonical role, keyed by the RoleName value. */
function stActors(): array
{
    $actors = [];

    foreach (RoleName::cases() as $role) {
        $actors[$role->value] = User::factory()->create([
            'role_id' => Role::factory()->create(['name' => $role->value])->id,
        ]);
    }

    return $actors;
}

/** A user whose role is outside the four canonical names. */
function stActorWithUnknownRole(): User
{
    return User::factory()->create([
        'role_id' => Role::factory()->create(['name' => 'night_audit'])->id,
    ]);
}

/** The BookingFactory state method for a status (AGENTS.md rule 2). */
function stState(BookingStatus $status): string
{
    return match ($status) {
        BookingStatus::Pending => 'pending',
        BookingStatus::Confirmed => 'confirmed',
        BookingStatus::CheckedIn => 'checkedIn',
        BookingStatus::CheckedOut => 'checkedOut',
        BookingStatus::Cancelled => 'cancelled',
        BookingStatus::Expired => 'expired',
        BookingStatus::NoShow => 'noShow',
    };
}

/**
 * A booking already sitting in $status, with its room and nights, so a
 * transition test starts from a real row instead of a stub. Nights are
 * built by BookingFactory::createWithNights(), which derives is_active
 * from BookingStatus::holdsNights() (BR-01).
 *
 * @return array{0: Room, 1: Booking}
 */
function stBooking(RoomType $roomType, BookingStatus $status, array $attributes = []): array
{
    $room = Room::factory()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'status' => RoomStatus::Active,
    ]);

    // BookingSourceFactory draws from five unique names only, so a test
    // that builds many bookings shares one source instead of exhausting
    // that pool (booking_sources.name is unique).
    $source = BookingSource::query()->value('id') ?? BookingSource::factory()->create()->id;

    $booking = Booking::factory()->{stState($status)}()->createWithNights($room, 300000, array_merge([
        'property_id' => $roomType->property_id,
        'booking_source_id' => $source,
        'created_by_user_id' => null,
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ], $attributes));

    return [$room, $booking];
}

/** Property + room type shared by the cases inside one test. */
function stRoomType(): RoomType
{
    return RoomType::factory()->create([
        'property_id' => Property::factory()->create()->id,
    ]);
}

/**
 * Run the transition whose target status is $to. Expire is the one
 * system row of the matrix (BR-13.2), so it deliberately takes no actor
 * at all: no caller can impersonate the system on that path.
 */
function stRun(Booking $booking, BookingStatus $to, ?User $actor = null, ?string $reason = null)
{
    $service = transitions();

    return match ($to) {
        BookingStatus::Confirmed => $service->confirm($booking, $actor, $reason),
        BookingStatus::Cancelled => $service->cancel($booking, $actor, $reason),
        BookingStatus::CheckedIn => $service->checkIn($booking, $actor),
        BookingStatus::CheckedOut => $service->checkOut($booking, $actor),
        BookingStatus::NoShow => $service->markNoShow($booking, $actor),
        BookingStatus::Expired => $service->expire($booking),
        BookingStatus::Pending => throw new BookingException('pending is not a transition target.'),
    };
}

/**
 * ST-01 / ST-07 dataset: every row of the matrix, by every actor it
 * names. The six staff rows run for each of owner, manager and
 * receptionist; the single system row (pending -> expired) runs with no
 * actor. Housekeeping is absent from every row (section 5 preamble) and
 * is therefore covered by ST-02 instead.
 *
 * @return array<string, array{0: BookingStatus, 1: BookingStatus, 2: RoleName|null, 3: string, 4: PaymentOption}>
 */
function stValidTransitions(): array
{
    // from, to, the check-in date the row's condition needs, payment option.
    // Cancel rows deliberately use bank_transfer: cancellation must not be
    // gated on a deposit (BR-05 says nothing about one).
    $rows = [
        [BookingStatus::Pending, BookingStatus::Confirmed, '2026-07-10', PaymentOption::PayOnArrival],
        [BookingStatus::Pending, BookingStatus::Cancelled, '2026-07-10', PaymentOption::BankTransfer],
        [BookingStatus::Confirmed, BookingStatus::Cancelled, '2026-07-10', PaymentOption::BankTransfer],
        [BookingStatus::Confirmed, BookingStatus::CheckedIn, '2026-06-15', PaymentOption::PayOnArrival],
        [BookingStatus::Confirmed, BookingStatus::NoShow, '2026-06-14', PaymentOption::PayOnArrival],
        [BookingStatus::CheckedIn, BookingStatus::CheckedOut, '2026-06-15', PaymentOption::PayOnArrival],
        [BookingStatus::Pending, BookingStatus::Expired, '2026-07-10', PaymentOption::PayOnArrival],
    ];

    $cases = [];

    foreach ($rows as [$from, $to, $checkIn, $option]) {
        $actors = $to === BookingStatus::Expired
            ? [null]
            : [RoleName::Owner, RoleName::Manager, RoleName::Receptionist];

        foreach ($actors as $role) {
            $who = $role?->value ?? 'system';

            $cases["{$from->value} -> {$to->value} by {$who}"] = [$from, $to, $role, $checkIn, $option];
        }
    }

    return $cases;
}

// ST-01: every valid transition, by every role the matrix allows on it,
// succeeds and leaves the booking in the target status.
it('ST-01: runs every transition the matrix allows for the role it allows', function (
    BookingStatus $from,
    BookingStatus $to,
    ?RoleName $role,
    string $checkIn,
    PaymentOption $option,
) {
    freezeNow('2026-06-15 10:00:00');

    $roomType = stRoomType();
    $actors = stActors();

    $attributes = [
        'payment_option' => $option,
        'check_in_date' => $checkIn,
        'check_out_date' => Carbon::parse($checkIn)->addDays(2)->toDateString(),
    ];

    if ($to === BookingStatus::Expired) {
        $attributes['expires_at'] = now()->subMinute();
    }

    [, $booking] = stBooking($roomType, $from, $attributes);

    $result = stRun($booking, $to, $role === null ? null : $actors[$role->value],
        $to === BookingStatus::Cancelled ? 'Guest changed plans.' : null);

    expect($result->booking->refresh()->status)->toBe($to)
        ->and($booking->bookingStatusLogs()->count())->toBe(1);

    if ($to === BookingStatus::Cancelled) {
        expect($result->booking->cancelled_at)->not->toBeNull()
            ->and($result->booking->cancellation_reason)->toBe('Guest changed plans.');
    }
})->with(stValidTransitions());

// ST-02: the full dataset. Every pair of statuses the matrix does not
// list is refused for all four roles; on the rows it does list, the
// actors it excludes (housekeeping, and a role outside the canonical
// four) are refused too. Final statuses have no outgoing row at all, so
// they can never be reopened (section 5, last row).
it('ST-02: refuses every transition outside the matrix for every role', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();
    $guest = Guest::factory()->create();
    $source = BookingSource::factory()->create();
    $actors = stActors();
    $unknown = stActorWithUnknownRole();

    $allowedStaffRows = [
        [BookingStatus::Pending, BookingStatus::Confirmed],
        [BookingStatus::Pending, BookingStatus::Cancelled],
        [BookingStatus::Confirmed, BookingStatus::Cancelled],
        [BookingStatus::Confirmed, BookingStatus::CheckedIn],
        [BookingStatus::Confirmed, BookingStatus::NoShow],
        [BookingStatus::CheckedIn, BookingStatus::CheckedOut],
    ];
    $systemRow = [BookingStatus::Pending, BookingStatus::Expired];

    $refused = 0;

    foreach (BookingStatus::cases() as $from) {
        foreach (BookingStatus::cases() as $to) {
            // pending is never a target: it is the state createBooking()
            // writes, and the service exposes no method that lands there,
            // so "reopening to pending" cannot even be attempted.
            if ($to === BookingStatus::Pending || [$from, $to] === $systemRow) {
                continue;
            }

            $allowedRow = in_array([$from, $to], $allowedStaffRows, true);

            // Refusal reason must be the one that actually applies, so a
            // regression cannot hide behind a different error.
            $needle = $allowedRow ? 'may not move a booking' : 'not in the transition matrix';
            $denied = $allowedRow
                ? [$actors[RoleName::Housekeeping->value], $unknown]
                : array_values($actors);

            foreach ($denied as $actor) {
                $booking = Booking::factory()->{stState($from)}()->create([
                    'property_id' => $property->id,
                    'guest_id' => $guest->id,
                    'booking_source_id' => $source->id,
                    'created_by_user_id' => null,
                    'check_in_date' => '2026-07-10',
                    'check_out_date' => '2026-07-12',
                ]);

                expect(fn () => stRun($booking, $to, $actor, 'A reason was given.'))
                    ->toThrow(BookingException::class, $needle);

                // Nothing moved and nothing was logged.
                expect($booking->refresh()->status)->toBe($from)
                    ->and($booking->bookingStatusLogs()->count())->toBe(0);

                $refused++;
            }
        }
    }

    // 7 starting statuses x 6 transition targets, minus the 6 staff rows
    // and the 1 system row, for all 4 roles, plus the 2 actors the matrix
    // excludes from each of those 6 staff rows.
    expect($refused)->toBe((7 * 6 - 6 - 1) * 4 + 6 * 2);
});

// ST-03: BR-05.1 makes the reason mandatory, for every role, not just
// for the receptionist named in the matrix.
it('ST-03: refuses a cancellation without a reason (BR-05)', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = stRoomType();
    $actors = stActors();

    foreach ([RoleName::Receptionist, RoleName::Owner, RoleName::Manager] as $role) {
        [, $booking] = stBooking($roomType, BookingStatus::Pending);

        expect(fn () => transitions()->cancel($booking, $actors[$role->value], ''))
            ->toThrow(BookingException::class, 'reason');

        expect(fn () => transitions()->cancel($booking, $actors[$role->value], null))
            ->toThrow(BookingException::class, 'reason');

        expect($booking->refresh()->status)->toBe(BookingStatus::Pending)
            ->and($booking->cancelled_at)->toBeNull()
            ->and($booking->bookingStatusLogs()->count())->toBe(0);
    }
});

// ST-04 / K6: confirming a bank transfer needs a verified deposit of at
// least the required DP; only owner and manager may waive it, and only
// with a reason. A receptionist can never waive it.
it('ST-04: gates a bank-transfer confirmation on a verified deposit (K6)', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = stRoomType();
    $actors = stActors();

    // 2 nights x 300000 = 600000 total, deposit_percent 50 -> DP 300000 (BR-03.1).
    $bankTransfer = [
        'payment_option' => PaymentOption::BankTransfer,
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ];

    // (a) receptionist, no deposit at all.
    [, $noDeposit] = stBooking($roomType, BookingStatus::Pending, $bankTransfer);
    expect(fn () => transitions()->confirm($noDeposit, $actors['receptionist']))
        ->toThrow(BookingException::class, 'deposit')
        ->and($noDeposit->refresh()->status)->toBe(BookingStatus::Pending);

    // (b) receptionist offers an override reason: still refused.
    [, $receptionistOverride] = stBooking($roomType, BookingStatus::Pending, $bankTransfer);
    expect(fn () => transitions()->confirm($receptionistOverride, $actors['receptionist'], 'Guest asked.'))
        ->toThrow(BookingException::class, 'deposit');

    // (c) owner offers no reason: the override itself needs one.
    [, $ownerNoReason] = stBooking($roomType, BookingStatus::Pending, $bankTransfer);
    expect(fn () => transitions()->confirm($ownerNoReason, $actors['owner']))
        ->toThrow(BookingException::class, 'reason');

    // (d) owner and manager override with a reason.
    foreach ([RoleName::Owner, RoleName::Manager] as $role) {
        [, $override] = stBooking($roomType, BookingStatus::Pending, $bankTransfer);

        expect(transitions()->confirm($override, $actors[$role->value], 'Guest pays the rest on arrival.')
            ->booking->refresh()->status)->toBe(BookingStatus::Confirmed);
    }

    // (e) a verified deposit covers the DP, so no override is needed.
    [, $verified] = stBooking($roomType, BookingStatus::Pending, $bankTransfer);
    Payment::factory()->create([
        'booking_id' => $verified->id,
        'amount' => 300000,
        'type' => PaymentType::Deposit,
        'status' => PaymentStatus::Verified,
    ]);
    expect(transitions()->confirm($verified, $actors['receptionist'])
        ->booking->refresh()->status)->toBe(BookingStatus::Confirmed);

    // (f) an unverified deposit is not a deposit (BR-03.3).
    [, $pendingPayment] = stBooking($roomType, BookingStatus::Pending, $bankTransfer);
    Payment::factory()->create([
        'booking_id' => $pendingPayment->id,
        'amount' => 300000,
        'type' => PaymentType::Deposit,
        'status' => PaymentStatus::Pending,
    ]);
    expect(fn () => transitions()->confirm($pendingPayment, $actors['receptionist']))
        ->toThrow(BookingException::class, 'deposit');

    // (g) a payment method that needs no DP is confirmed as it stands (BR-03.2).
    [, $arrival] = stBooking($roomType, BookingStatus::Pending, [
        'payment_option' => PaymentOption::PayOnArrival,
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ]);
    expect(transitions()->confirm($arrival, $actors['receptionist'])
        ->booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

// ST-04 continues: the required DP is total x deposit_percent / 100 and
// the percent is a policy column on properties (AGENTS.md rule 6), so it
// has to move when the property does. With 30% the DP of a 600000 stay
// is 180000: 200000 must be enough, 100000 must not, and the refusal has
// to name 180000. A hard-coded 50% would fail in both directions.
it('ST-04: reads the deposit percent from the property row (BR-03.1)', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = stRoomType();
    Property::query()->findOrFail($roomType->property_id)->update(['deposit_percent' => 30]);
    $actors = stActors();

    $bankTransfer = [
        'payment_option' => PaymentOption::BankTransfer,
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ];

    [, $enough] = stBooking($roomType, BookingStatus::Pending, $bankTransfer);
    Payment::factory()->create([
        'booking_id' => $enough->id,
        'amount' => 200000,
        'type' => PaymentType::Deposit,
        'status' => PaymentStatus::Verified,
    ]);
    expect(transitions()->confirm($enough, $actors['receptionist'])
        ->booking->refresh()->status)->toBe(BookingStatus::Confirmed);

    [, $short] = stBooking($roomType, BookingStatus::Pending, $bankTransfer);
    Payment::factory()->create([
        'booking_id' => $short->id,
        'amount' => 100000,
        'type' => PaymentType::Deposit,
        'status' => PaymentStatus::Verified,
    ]);
    expect(fn () => transitions()->confirm($short, $actors['receptionist']))
        ->toThrow(BookingException::class, 'needs a verified deposit of 180000');
});

// ST-05 / BR-08: no-show is refused before the property's cutoff and
// accepted from it on, and then every night is released.
it('ST-05: records a no-show only after the cutoff and frees every night (BR-08)', function () {
    $roomType = stRoomType();
    $actors = stActors();

    freezeNow('2026-06-15 10:00:00');

    [, $booking] = stBooking($roomType, BookingStatus::Confirmed, [
        'check_in_date' => '2026-06-15',
        'check_out_date' => '2026-06-17',
    ]);

    // One hour before the default 22:00 cutoff (K7).
    freezeNow('2026-06-15 21:00:00');
    expect(fn () => transitions()->markNoShow($booking, $actors['receptionist']))
        ->toThrow(BookingException::class, 'cutoff')
        ->and($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->bookingNights()->where('is_active', true)->count())->toBe(2);

    // Exactly at the cutoff the matrix allows it ("Sekarang >= ...").
    freezeNow('2026-06-15 22:00:00');
    $result = transitions()->markNoShow($booking, $actors['receptionist']);

    expect($result->booking->status)->toBe(BookingStatus::NoShow)
        ->and($booking->bookingNights()->where('is_active', true)->count())->toBe(0);
});

// ST-05 continues: no_show_cutoff_time is a policy column on properties
// (AGENTS.md rule 6, K7), so the refusal message has to carry whatever
// the row says. Hard-coding 22:00 would fail on both of these.
it('ST-05: reads the no-show cutoff from the property row (K7)', function () {
    $roomType = stRoomType();
    Property::query()->findOrFail($roomType->property_id)
        ->update(['no_show_cutoff_time' => '20:00:00']);
    $actors = stActors();

    freezeNow('2026-06-15 10:00:00');

    [, $booking] = stBooking($roomType, BookingStatus::Confirmed, [
        'check_in_date' => '2026-06-15',
        'check_out_date' => '2026-06-17',
    ]);

    freezeNow('2026-06-15 19:59:00');
    expect(fn () => transitions()->markNoShow($booking, $actors['receptionist']))
        ->toThrow(BookingException::class, '2026-06-15 20:00:00');

    freezeNow('2026-06-15 20:00:00');
    expect(transitions()->markNoShow($booking, $actors['receptionist'])
        ->booking->status)->toBe(BookingStatus::NoShow);
});

// ST-06 / BR-05: cancelling releases the nights, so the very same room
// and dates can be sold again.
it('ST-06: lets the same room and dates be sold again after a cancellation', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();
    $roomType = RoomType::factory()->create(['property_id' => $property->id]);
    Rate::factory()->defaultRate()->create([
        'property_id' => $property->id,
        'room_type_id' => $roomType->id,
        'price' => 300000,
    ]);
    $actors = stActors();

    [$room, $first] = stBooking($roomType, BookingStatus::Pending, [
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ]);

    transitions()->cancel($first, $actors['receptionist'], 'Guest cancelled by phone.');

    expect($first->bookingNights()->where('is_active', true)->count())->toBe(0);

    $second = transitions()->createBooking(
        $property,
        Guest::factory()->create(),
        BookingSource::query()->findOrFail($first->booking_source_id),
        PaymentOption::PayOnArrival,
        2,
        '2026-07-10',
        '2026-07-12',
        [$room],
        $actors['receptionist'],
    );

    expect($second->status)->toBe(BookingStatus::Pending)
        ->and($first->refresh()->status)->toBe(BookingStatus::Cancelled);
});

// ST-07: exactly one booking_status_logs row per transition, carrying
// the previous status, the new one and the actor.
it('ST-07: writes one booking_status_logs row for every transition', function (
    BookingStatus $from,
    BookingStatus $to,
    ?RoleName $role,
    string $checkIn,
    PaymentOption $option,
) {
    freezeNow('2026-06-15 10:00:00');

    $roomType = stRoomType();
    $actors = stActors();

    $attributes = [
        'payment_option' => $option,
        'check_in_date' => $checkIn,
        'check_out_date' => Carbon::parse($checkIn)->addDays(2)->toDateString(),
    ];

    if ($to === BookingStatus::Expired) {
        $attributes['expires_at'] = now()->subMinute();
    }

    [, $booking] = stBooking($roomType, $from, $attributes);
    $actor = $role === null ? null : $actors[$role->value];

    stRun($booking, $to, $actor, $to === BookingStatus::Cancelled ? 'Guest changed plans.' : null);

    $log = $booking->bookingStatusLogs()->sole();

    expect($log->old_status)->toBe($from)
        ->and($log->new_status)->toBe($to)
        ->and($log->changed_by_user_id)->toBe($actor?->id)
        ->and($log->note)->not->toBeNull();
})->with(stValidTransitions());

// ST-08 / BR-07.4: checking out stamps checked_out_at and turns the
// room dirty.
it('ST-08: marks the room dirty and stamps checked_out_at (BR-07)', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = stRoomType();
    $actors = stActors();

    [$room, $booking] = stBooking($roomType, BookingStatus::CheckedIn, [
        'check_in_date' => '2026-06-15',
        'check_out_date' => '2026-06-17',
    ]);
    HousekeepingStatus::factory()->create([
        'room_id' => $room->id,
        'status' => Cleanliness::Ready,
    ]);

    $result = transitions()->checkOut($booking, $actors['receptionist']);

    expect($result->booking->status)->toBe(BookingStatus::CheckedOut)
        ->and((string) $result->booking->checked_out_at)->toStartWith('2026-06-15 10:00:00')
        ->and(HousekeepingStatus::query()->where('room_id', $room->id)->sole()->status)
        ->toBe(Cleanliness::Dirty)
        // Nothing was ever paid, so the whole 600000 is still owed: the
        // matrix row warns about it on check-out too (BR-04.2).
        ->and(implode(' | ', $result->warnings))->toContain('balance');
});

// ST-09 / BR-04.2, BR-07.5: a room that is not ready and a balance that
// is still owed produce warnings, never a refusal.
it('ST-09: warns but never blocks a check-in with a room not ready or a balance due', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = stRoomType();
    $actors = stActors();

    [$room, $booking] = stBooking($roomType, BookingStatus::Confirmed, [
        'check_in_date' => '2026-06-15',
        'check_out_date' => '2026-06-17',
    ]);
    HousekeepingStatus::factory()->create([
        'room_id' => $room->id,
        'status' => Cleanliness::Cleaning,
    ]);

    $result = transitions()->checkIn($booking, $actors['receptionist']);
    $text = implode(' | ', $result->warnings);

    expect($result->booking->status)->toBe(BookingStatus::CheckedIn)
        ->and($result->warnings)->toHaveCount(2)
        ->and($text)->toContain('not ready')
        ->and($text)->toContain('balance')
        ->and((string) $result->booking->checked_in_at)->toStartWith('2026-06-15 10:00:00');
});

// BR-02.2 and BR-08.1: the confirmed -> checked_in row reads "the
// check-in date is today or already past, and not yet no_show". Both
// halves are refusals, so both are locked down here.
it('BR-02.2/BR-08.1: refuses check-in before the stay starts and after the cutoff', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = stRoomType();
    $actors = stActors();

    // (a) tomorrow's stay has not started yet.
    [, $tooEarly] = stBooking($roomType, BookingStatus::Confirmed, [
        'check_in_date' => '2026-06-16',
        'check_out_date' => '2026-06-18',
    ]);
    expect(fn () => transitions()->checkIn($tooEarly, $actors['receptionist']))
        ->toThrow(BookingException::class, 'only allowed on or after the check-in date')
        ->and($tooEarly->refresh()->status)->toBe(BookingStatus::Confirmed);

    // (b) today's cutoff has passed, so the booking is a no-show now.
    [, $tooLate] = stBooking($roomType, BookingStatus::Confirmed, [
        'check_in_date' => '2026-06-15',
        'check_out_date' => '2026-06-17',
    ]);
    freezeNow('2026-06-15 22:00:00');
    expect(fn () => transitions()->checkIn($tooLate, $actors['receptionist']))
        ->toThrow(BookingException::class, 'no-show cutoff has passed')
        ->and($tooLate->refresh()->status)->toBe(BookingStatus::Confirmed);
});

// EX-01 / BR-13.2: a website pending booking past its deadline expires
// and its nights are released.
it('EX-01: expires a website booking past its deadline and releases its nights (BR-13)', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = stRoomType();

    [, $booking] = stBooking($roomType, BookingStatus::Pending, [
        'created_by_user_id' => null,
        'expires_at' => now()->subMinute(),
    ]);

    $result = transitions()->expire($booking);

    expect($result->booking->status)->toBe(BookingStatus::Expired)
        ->and($booking->bookingNights()->where('is_active', true)->count())->toBe(0);
});

// EX-02 / BR-13.3: confirming drops the deadline, so the booking can no
// longer expire later.
it('EX-02: clears expires_at when the booking is confirmed (BR-13.3)', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = stRoomType();
    $actors = stActors();

    [, $booking] = stBooking($roomType, BookingStatus::Pending, [
        'created_by_user_id' => null,
        'payment_option' => PaymentOption::PayOnArrival,
        'expires_at' => now()->addHours(2),
    ]);

    $result = transitions()->confirm($booking, $actors['receptionist']);

    expect($result->booking->status)->toBe(BookingStatus::Confirmed)
        ->and($result->booking->refresh()->expires_at)->toBeNull();
});

// EX-03 / BR-13.3: an expired booking cannot be confirmed; the matrix
// simply has no such row.
it('EX-03: refuses to confirm an expired booking (BR-13.3)', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = stRoomType();
    $actors = stActors();

    [, $booking] = stBooking($roomType, BookingStatus::Expired);

    expect(fn () => transitions()->confirm($booking, $actors['owner'], 'Late payment.'))
        ->toThrow(BookingException::class, 'not in the transition matrix');

    expect($booking->refresh()->status)->toBe(BookingStatus::Expired)
        ->and($booking->bookingStatusLogs()->count())->toBe(0);
});

// EX-04 / K5: a booking created by staff has no deadline, so there is
// nothing for the expiry row to fire on.
it('EX-04: never expires a staff booking, which has no deadline (K5)', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = stRoomType();
    $actors = stActors();

    [, $booking] = stBooking($roomType, BookingStatus::Pending, [
        'created_by_user_id' => $actors['owner']->id,
        'expires_at' => null,
    ]);

    expect(fn () => transitions()->expire($booking))
        ->toThrow(BookingException::class, 'expiry deadline')
        ->and($booking->refresh()->status)->toBe(BookingStatus::Pending);
});

// BR-13.2: a deadline that has not been reached yet is not an expiry,
// and the nights must stay sold while the guest still holds the room.
it('BR-13.2: refuses to expire a booking before its deadline', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = stRoomType();

    [, $booking] = stBooking($roomType, BookingStatus::Pending, [
        'created_by_user_id' => null,
        'expires_at' => now()->addHour(),
    ]);

    expect(fn () => transitions()->expire($booking))
        ->toThrow(BookingException::class, 'deadline has not been reached')
        ->and($booking->refresh()->status)->toBe(BookingStatus::Pending)
        ->and($booking->bookingNights()->where('is_active', true)->count())->toBe(2);
});

// EX-06 / BR-13.2: the actor of an expiry is the system, which means an
// empty changed_by_user_id.
it('EX-06: logs the system as the actor when a booking expires (BR-13.2)', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomType = stRoomType();

    [, $booking] = stBooking($roomType, BookingStatus::Pending, [
        'created_by_user_id' => null,
        'expires_at' => now()->subMinute(),
    ]);

    transitions()->expire($booking);

    $log = $booking->bookingStatusLogs()->sole();

    expect($log->changed_by_user_id)->toBeNull()
        ->and($log->old_status)->toBe(BookingStatus::Pending)
        ->and($log->new_status)->toBe(BookingStatus::Expired);
});
