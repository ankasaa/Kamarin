<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\RoomStatus;
use App\Exceptions\AvailabilityException;
use App\Exceptions\BookingException;
use App\Exceptions\PricingException;
use App\Models\Booking;
use App\Models\BookingNight;
use App\Models\BookingSource;
use App\Models\Property;
use App\Models\Rate;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingChangeService;
use App\Services\PaymentService;
use App\Support\BookingChangeResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Test cases CH-01 .. CH-11 (docs/rencana_pengujian.md section 4.5),
// covering BR-06 (who may change what, recheck under the row lock),
// K3 (unchanged nights keep their old price), K4 (reason for every
// role), BR-11.8 (minimum nights rechecked on the new check-in), BR-12
// (totals recalculated, overpayment flagged) and the single
// booking_change_logs row per change (ERD v2.1).
//
// Every test pins the clock (docs/rencana_pengujian.md section 2,
// "fixed now"), so "today", the rates in force and the expiry deadline
// are all reproducible.

function changes(): BookingChangeService
{
    return app(BookingChangeService::class);
}

/**
 * Property + room type + one sellable room of that type, together with
 * the mandatory default rate (BR-11.4), so availability and pricing
 * both pass for it.
 *
 * @return array{property: Property, roomType: RoomType, room: Room, rate: Rate}
 */
function chSetup(int $pricePerNight = 300000, int $minimumNights = 1, int $capacity = 4): array
{
    $property = Property::factory()->create();

    $roomType = RoomType::factory()->create([
        'property_id' => $property->id,
        'capacity' => $capacity,
    ]);

    $room = Room::factory()->create([
        'property_id' => $property->id,
        'room_type_id' => $roomType->id,
        'status' => RoomStatus::Active,
    ]);

    $rate = Rate::factory()->defaultRate()->create([
        'property_id' => $property->id,
        'room_type_id' => $roomType->id,
        'price' => $pricePerNight,
        'minimum_nights' => $minimumNights,
    ]);

    return compact('property', 'roomType', 'room', 'rate');
}

/** A second room sharing the same room type (CH-08). */
function chRoom(RoomType $roomType): Room
{
    return Room::factory()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'status' => RoomStatus::Active,
    ]);
}

/** A user holding exactly one canonical role, or a custom one. */
function chUser(string $roleName): User
{
    $role = Role::query()->firstOrCreate(['name' => $roleName]);

    return User::factory()->create(['role_id' => $role->id]);
}

/**
 * A booking with one item and its nights, exactly as BookingFactory
 * builds them. Booking sources share a single row because
 * booking_sources.name is unique and the factory draws from five names.
 */
function chBooking(Room $room, string $state = 'pending', array $attributes = [], int $pricePerNight = 300000): Booking
{
    $source = BookingSource::query()->value('id') ?? BookingSource::factory()->create()->id;

    return Booking::factory()->{$state}()->createWithNights($room, $pricePerNight, array_merge([
        'property_id' => $room->property_id,
        'booking_source_id' => $source,
        'created_by_user_id' => null,
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ], $attributes));
}

/** The active nights of the booking, keyed by stay date, with price. */
function chActiveNights(Booking $booking): array
{
    return $booking->refresh()->bookingNights()
        ->get()
        ->filter(fn (BookingNight $night) => $night->is_active)
        ->sortBy('stay_date')
        ->mapWithKeys(fn (BookingNight $night) => [$night->stay_date->toDateString() => $night->price])
        ->all();
}

/** The stay dates whose nights exist but are no longer active. */
function chInactiveNights(Booking $booking): array
{
    return $booking->refresh()->bookingNights()
        ->get()
        ->filter(fn (BookingNight $night) => ! $night->is_active)
        ->sortBy('stay_date')
        ->map(fn (BookingNight $night) => $night->stay_date->toDateString())
        ->values()
        ->all();
}

// CH-01: extending by one night keeps every existing night at its old
// price and prices only the new night at the rate in force (K3,
// BR-11.7). The rate is deliberately raised after the booking was made,
// so a service that repriced everything would fail here.
it('CH-01: extends a stay by one night while the old nightly prices stay locked', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup(pricePerNight: 300000);
    $booking = chBooking($setup['room']);
    $receptionist = chUser('receptionist');

    $setup['rate']->update(['price' => 400000]);

    $result = changes()->changeDates(
        $booking,
        '2026-07-10',
        '2026-07-13',
        $receptionist,
        'Guest asked for one more night',
    );

    expect(chActiveNights($booking))->toBe([
        '2026-07-10' => 300000,
        '2026-07-11' => 300000,
        '2026-07-12' => 400000,
    ])
        ->and($booking->refresh()->check_out_date->toDateString())->toBe('2026-07-13')
        ->and($booking->total_amount)->toBe(1000000)
        ->and($result->oldTotal)->toBe(600000)
        ->and($result->newTotal)->toBe(1000000);
});

// CH-02: the new night is already taken, so the whole attempt is
// refused. The assertion checks the surviving rows - nights, total and
// change log - because "nothing changed" is the real requirement, not
// the exception itself.
it('CH-02: refuses an extension whose new night is taken and leaves no trace behind', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup();
    $booking = chBooking($setup['room']);

    // A second booking already holds 12 and 13 July in the same room.
    chBooking($setup['room'], 'pending', [
        'check_in_date' => '2026-07-12',
        'check_out_date' => '2026-07-14',
    ]);

    $receptionist = chUser('receptionist');

    expect(fn () => changes()->changeDates(
        $booking,
        '2026-07-10',
        '2026-07-14',
        $receptionist,
        'Guest wants four nights',
    ))->toThrow(AvailabilityException::class);

    $booking->refresh();

    expect($booking->check_in_date->toDateString())->toBe('2026-07-10')
        ->and($booking->check_out_date->toDateString())->toBe('2026-07-12')
        ->and(chActiveNights($booking))->toBe([
            '2026-07-10' => 300000,
            '2026-07-11' => 300000,
        ])
        ->and(chInactiveNights($booking))->toBe([])
        ->and($booking->total_amount)->toBe(600000)
        ->and($booking->bookingChangeLogs()->count())->toBe(0);
});

// CH-03: shortening deactivates the nights after the new check-out and
// the total follows them down (BR-12.2, which sums active nights only).
it('CH-03: shortens the stay, releasing the leftover nights and lowering the total', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup();
    $booking = chBooking($setup['room']);
    $receptionist = chUser('receptionist');

    changes()->shorten($booking, '2026-07-11', $receptionist, 'Guest leaves a day early');

    expect(chActiveNights($booking))->toBe(['2026-07-10' => 300000])
        ->and(chInactiveNights($booking))->toBe(['2026-07-11'])
        ->and($booking->refresh()->check_out_date->toDateString())->toBe('2026-07-11')
        ->and($booking->total_amount)->toBe(300000);
});

// CH-04: moving the whole stay to other dates. The booking already owns
// 11 July, so a service that counted its own nights as a conflict would
// refuse this - which is exactly what BR-06 forbids.
it('CH-04: moves a stay to other dates without treating its own nights as a conflict', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup();
    $booking = chBooking($setup['room']);
    $receptionist = chUser('receptionist');

    expect(chActiveNights($booking))->toBe([
        '2026-07-10' => 300000,
        '2026-07-11' => 300000,
    ]);

    $result = changes()->changeDates(
        $booking,
        '2026-07-11',
        '2026-07-13',
        $receptionist,
        'Guest shifted by one day',
    );

    expect(chActiveNights($booking))->toBe([
        '2026-07-11' => 300000,
        '2026-07-12' => 300000,
    ])
        ->and(chInactiveNights($booking))->toBe(['2026-07-10'])
        ->and($booking->refresh()->check_in_date->toDateString())->toBe('2026-07-11')
        ->and($booking->check_out_date->toDateString())->toBe('2026-07-13')
        ->and($booking->total_amount)->toBe(600000)
        ->and($result->log)->not->toBeNull();
});

// CH-04 / K4: the same shift, but onto a night another booking holds,
// must still be refused.
it('CH-04: still refuses dates that another booking holds', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup();
    $booking = chBooking($setup['room']);
    $receptionist = chUser('receptionist');

    chBooking($setup['room'], 'pending', [
        'check_in_date' => '2026-07-13',
        'check_out_date' => '2026-07-15',
    ]);

    expect(fn () => changes()->changeDates(
        $booking,
        '2026-07-11',
        '2026-07-14',
        $receptionist,
        'Guest shifted by one day',
    ))->toThrow(AvailabilityException::class);

    expect(chActiveNights($booking))->toBe([
        '2026-07-10' => 300000,
        '2026-07-11' => 300000,
    ])
        ->and($booking->bookingChangeLogs()->count())->toBe(0);
});

// CH-05 / K4: the reason is mandatory for every role, not only for the
// receptionist. Blank and whitespace-only reasons are both refused and
// nothing is written for any of them.
it('CH-05: refuses a change without a reason for every role that may change a booking', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup();
    $booking = chBooking($setup['room']);

    foreach (['owner', 'manager', 'receptionist'] as $roleName) {
        $actor = chUser($roleName);

        foreach (['', '   '] as $reason) {
            expect(fn () => changes()->changeDates(
                $booking,
                '2026-07-10',
                '2026-07-13',
                $actor,
                $reason,
            ))->toThrow(BookingException::class);
        }
    }

    expect($booking->refresh()->check_out_date->toDateString())->toBe('2026-07-12')
        ->and(chActiveNights($booking))->toBe([
            '2026-07-10' => 300000,
            '2026-07-11' => 300000,
        ])
        ->and($booking->bookingChangeLogs()->count())->toBe(0);
});

// CH-06: every change writes exactly one row carrying the type, the old
// value, the new value, the reason and the actor (ERD v2.1
// BOOKING_CHANGE_LOGS). The second change proves one row per change
// rather than one row per booking.
it('CH-06: writes one change log per change with type, old value, new value, reason and actor', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup();
    $booking = chBooking($setup['room']);
    $receptionist = chUser('receptionist');

    changes()->changeDates(
        $booking,
        '2026-07-11',
        '2026-07-13',
        $receptionist,
        'Moved at the guest request',
    );

    $datesLog = $booking->refresh()->bookingChangeLogs()->sole();

    expect($datesLog->change_type)->toBe('dates_changed')
        ->and($datesLog->changed_by_user_id)->toBe($receptionist->id)
        ->and($datesLog->reason)->toBe('Moved at the guest request')
        ->and(json_decode($datesLog->old_value, true))->toBe([
            'check_in_date' => '2026-07-10',
            'check_out_date' => '2026-07-12',
        ])
        ->and(json_decode($datesLog->new_value, true))->toBe([
            'check_in_date' => '2026-07-11',
            'check_out_date' => '2026-07-13',
        ]);

    changes()->changeGuestCount($booking, 3, $receptionist, 'A third guest joined');

    $logs = $booking->refresh()->bookingChangeLogs()->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs->last()->change_type)->toBe('guest_count_changed')
        ->and($logs->last()->reason)->toBe('A third guest joined')
        ->and(json_decode($logs->last()->old_value, true))->toBe(['guest_count' => 2])
        ->and(json_decode($logs->last()->new_value, true))->toBe(['guest_count' => 3]);
});

// CH-07 / BR-11.8: the minimum nights of the new check-in night are
// re-checked, and only owner and manager may override it.
it('CH-07: rechecks minimum nights on the new check-in and lets only owner or manager override', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup(minimumNights: 3);
    $first = chBooking($setup['room']);
    $second = chBooking(chRoom($setup['roomType']));

    $receptionist = chUser('receptionist');
    $owner = chUser('owner');
    $manager = chUser('manager');

    expect(fn () => changes()->shorten($first, '2026-07-11', $receptionist, 'One night only'))
        ->toThrow(PricingException::class);

    expect($first->refresh()->check_out_date->toDateString())->toBe('2026-07-12')
        ->and($first->bookingChangeLogs()->count())->toBe(0);

    changes()->shorten($first, '2026-07-11', $owner, 'Owner approved the short stay');
    changes()->shorten($second, '2026-07-11', $manager, 'Manager approved the short stay');

    expect($first->refresh()->check_out_date->toDateString())->toBe('2026-07-11')
        ->and($second->refresh()->check_out_date->toDateString())->toBe('2026-07-11');
});

// CH-08: a room change within the same room type keeps every nightly
// price exactly as it was - no night is repriced, even though the rate
// has moved on in the meantime.
it('CH-08: moves a booking to another room of the same type without changing any price', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup(pricePerNight: 300000);
    $target = chRoom($setup['roomType']);
    $booking = chBooking($setup['room']);
    $receptionist = chUser('receptionist');

    $setup['rate']->update(['price' => 900000]);

    $result = changes()->moveRoom($booking, $target, $receptionist, 'Room 101 is out of order');

    expect(chActiveNights($booking))->toBe([
        '2026-07-10' => 300000,
        '2026-07-11' => 300000,
    ])
        ->and($booking->refresh()->total_amount)->toBe(600000)
        ->and($booking->check_in_date->toDateString())->toBe('2026-07-10')
        ->and($booking->check_out_date->toDateString())->toBe('2026-07-12')
        ->and($booking->bookingItems()->pluck('room_id')->all())->toBe([$target->id])
        ->and($result->log->change_type)->toBe('room_changed')
        ->and(json_decode($result->log->new_value, true))->toBe([
            'room_id' => $target->id,
            'room_number' => $target->room_number,
        ]);
});

// CH-08: the destination room must belong to the same room type; a
// cross-type move is outside the T-013 MVP (docs/tasks backlog) and
// would have to reprice the nights, which BR-06 defines separately.
it('CH-08: refuses to move a booking into a room of a different type', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup();
    $otherTypeRoom = Room::factory()->create([
        'property_id' => $setup['property']->id,
        'status' => RoomStatus::Active,
    ]);
    Rate::factory()->defaultRate()->create([
        'property_id' => $setup['property']->id,
        'room_type_id' => $otherTypeRoom->room_type_id,
        'price' => 500000,
    ]);

    $booking = chBooking($setup['room']);
    $receptionist = chUser('receptionist');

    expect(fn () => changes()->moveRoom($booking, $otherTypeRoom, $receptionist, 'Guest asked for a suite'))
        ->toThrow(BookingException::class);

    expect(chActiveNights($booking))->toBe([
        '2026-07-10' => 300000,
        '2026-07-11' => 300000,
    ])
        ->and($booking->bookingChangeLogs()->count())->toBe(0);
});

// CH-09 / BR-06: a checked-in guest may be extended or sent home early,
// but the check-in date itself is frozen once the stay has started.
it('CH-09: a checked-in booking refuses a check-in change but accepts an extension', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup();
    $booking = chBooking($setup['room'], 'checkedIn');
    $receptionist = chUser('receptionist');

    expect(fn () => changes()->changeDates(
        $booking,
        '2026-07-11',
        '2026-07-13',
        $receptionist,
        'Guest arrived a day late',
    ))->toThrow(BookingException::class);

    expect($booking->refresh()->check_in_date->toDateString())->toBe('2026-07-10')
        ->and($booking->check_out_date->toDateString())->toBe('2026-07-12')
        ->and($booking->bookingChangeLogs()->count())->toBe(0);

    changes()->extend($booking, '2026-07-14', $receptionist, 'Guest stays two nights longer');

    expect(chActiveNights($booking))->toBe([
        '2026-07-10' => 300000,
        '2026-07-11' => 300000,
        '2026-07-12' => 300000,
        '2026-07-13' => 300000,
    ])
        ->and($booking->refresh()->total_amount)->toBe(1200000);
});

// CH-10 / BR-12.3: shortening a booking that has already been paid in
// full leaves the guest paying more than the new total, and the result
// reports that back as a warning rather than clamping it.
it('CH-10: flags the booking as overpaid when a change drops the total below what was paid', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup();
    $booking = chBooking($setup['room']);
    $owner = chUser('owner');
    $receptionist = chUser('receptionist');

    $payments = app(PaymentService::class);

    $payment = $payments->record(
        $booking,
        $owner,
        600000,
        PaymentType::Deposit,
        PaymentMethod::Cash,
        '2026-06-15',
    );
    $payments->verify($payment, $owner);

    expect($payments->summary($booking)->overpaid)->toBeFalse();

    $result = changes()->shorten($booking, '2026-07-11', $receptionist, 'Guest leaves a day early');

    expect($result->newTotal)->toBe(300000)
        ->and($result->payments->paid)->toBe(600000)
        ->and($result->payments->overpaid)->toBeTrue()
        ->and($result->warnings)->toContain(BookingChangeResult::WARN_OVERPAID);
});

// CH-11 / BR-13: changing a pending website booking must not touch its
// expiry deadline - an edit never buys the guest more time.
it('CH-11: leaves expires_at alone when a pending booking is changed', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup();
    $deadline = now()->addHours(3);
    $booking = chBooking($setup['room'], 'pending', ['expires_at' => $deadline]);
    $receptionist = chUser('receptionist');

    changes()->changeDates($booking, '2026-07-11', '2026-07-13', $receptionist, 'Guest shifted by one day');

    // Booking has no cast for expires_at, so it comes back as a raw
    // timestamp string; parse it instead of calling a date method.
    expect(Carbon::parse($booking->refresh()->expires_at)->toDateTimeString())
        ->toBe($deadline->toDateTimeString())
        ->and($booking->status)->toBe(BookingStatus::Pending);
});

// BR-06 "Status akhir tidak boleh diubah": a closed booking is closed
// for every kind of change, not just for status transitions.
it('BR-06: refuses every change once the booking reached a final status', function () {
    freezeNow('2026-06-15 10:00:00');

    $receptionist = chUser('receptionist');

    foreach (['checkedOut', 'cancelled', 'expired', 'noShow'] as $state) {
        $setup = chSetup();
        $booking = chBooking($setup['room'], $state);

        expect(fn () => changes()->changeDates(
            $booking,
            '2026-07-11',
            '2026-07-13',
            $receptionist,
            'Trying to edit a closed booking',
        ))->toThrow(BookingException::class);

        expect($booking->bookingChangeLogs()->count())->toBe(0);
    }
});

// AGENTS.md rule 3 + the BR-06 allow-list: housekeeping may not change
// a booking, and neither may a role outside the canonical four.
it('BR-06: only owner, manager and receptionist may change a booking', function () {
    freezeNow('2026-06-15 10:00:00');

    foreach (['housekeeping', 'night_audit'] as $roleName) {
        $setup = chSetup();
        $booking = chBooking($setup['room']);
        $actor = chUser($roleName);

        expect(fn () => changes()->changeDates(
            $booking,
            '2026-07-10',
            '2026-07-13',
            $actor,
            'Trying to edit a booking',
        ))->toThrow(BookingException::class);

        expect($booking->refresh()->check_out_date->toDateString())->toBe('2026-07-12')
            ->and($booking->bookingChangeLogs()->count())->toBe(0);
    }
});

// BR-01.2: the guest count may never exceed the capacity of the rooms
// the booking already holds.
it('BR-01: refuses a guest count above the room capacity and accepts one that fits', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup(capacity: 4);
    $booking = chBooking($setup['room']);
    $receptionist = chUser('receptionist');

    expect(fn () => changes()->changeGuestCount($booking, 5, $receptionist, 'Four guests and a baby'))
        ->toThrow(BookingException::class);

    expect($booking->refresh()->guest_count)->toBe(2)
        ->and($booking->bookingChangeLogs()->count())->toBe(0);

    changes()->changeGuestCount($booking, 4, $receptionist, 'Two more guests joined');

    expect($booking->refresh()->guest_count)->toBe(4)
        ->and($booking->bookingChangeLogs()->count())->toBe(1);
});

// docs/tasks backlog T-013: a preview computes the change without
// writing anything, so the desk can show the difference before asking
// for a reason.
it('T-013: previews a change and writes nothing at all', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup(pricePerNight: 300000);
    $booking = chBooking($setup['room']);
    $receptionist = chUser('receptionist');

    $setup['rate']->update(['price' => 400000]);

    $result = changes()->preview($booking, $receptionist, checkIn: '2026-07-10', checkOut: '2026-07-13');

    expect($result->oldTotal)->toBe(600000)
        ->and($result->newTotal)->toBe(1000000)
        ->and($result->log)->toBeNull()
        ->and($booking->refresh()->check_out_date->toDateString())->toBe('2026-07-12')
        ->and($booking->total_amount)->toBe(600000)
        ->and(chActiveNights($booking))->toBe([
            '2026-07-10' => 300000,
            '2026-07-11' => 300000,
        ])
        ->and($booking->bookingChangeLogs()->count())->toBe(0);
});

// docs/tasks backlog T-013: a preview also reports the conflict instead
// of pretending the change is possible.
it('T-013: reports an unavailable night through the preview without writing', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup();
    $booking = chBooking($setup['room']);
    $receptionist = chUser('receptionist');

    chBooking($setup['room'], 'pending', [
        'check_in_date' => '2026-07-12',
        'check_out_date' => '2026-07-14',
    ]);

    expect(fn () => changes()->preview($booking, $receptionist, checkIn: '2026-07-10', checkOut: '2026-07-14'))
        ->toThrow(AvailabilityException::class);

    expect($booking->refresh()->check_out_date->toDateString())->toBe('2026-07-12')
        ->and($booking->bookingChangeLogs()->count())->toBe(0);
});

// Pertanyaan Terbuka 3 (now decided): every writer that creates or moves
// room placement must take a lockForUpdate on the room rows, ordered by
// id, exactly like BookingService::createBooking. This proves the SQL is
// actually issued rather than merely intended.
it('T-013: locks the room rows for update before rechecking availability', function () {
    freezeNow('2026-06-15 10:00:00');

    $setup = chSetup();
    $booking = chBooking($setup['room']);
    $receptionist = chUser('receptionist');

    $sql = [];
    DB::listen(function ($query) use (&$sql) {
        $sql[] = strtolower($query->sql);
    });

    changes()->changeDates($booking, '2026-07-10', '2026-07-13', $receptionist, 'Guest asked for one more night');

    $lockQueries = array_values(array_filter(
        $sql,
        fn (string $statement) => str_contains($statement, 'for update')
            && str_contains($statement, 'from "rooms"'),
    ));

    expect($lockQueries)->not->toBeEmpty()
        ->and(str_contains($lockQueries[0], 'order by "id" asc'))->toBeTrue();
});
