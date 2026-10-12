<?php

use App\Enums\RoomStatus;
use App\Exceptions\AvailabilityException;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomType;
use App\Models\User;
use App\Services\AvailabilityService;

// Test cases AV-01 .. AV-14 from docs/rencana_pengujian.md section 4.1,
// covering BR-01 (availability and double booking) and BR-07 (room
// status and housekeeping). Every test freezes the clock so "today"
// is deterministic (helper from T-003).

function availability(): AvailabilityService
{
    return app(AvailabilityService::class);
}

function expectAvailabilityError(callable $action, string $messageNeedle): void
{
    $thrown = null;

    try {
        $action();
    } catch (AvailabilityException $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(AvailabilityException::class)
        ->and($thrown?->getMessage())->toContain($messageNeedle);
}

// AV-01: active room without bookings or blocks is available.
it('AV-01: offers an active room with no bookings and no blocks', function () {
    freezeNow('2026-06-15 10:00:00');

    $room = Room::factory()->create(['status' => RoomStatus::Active]);

    $available = availability()->availableRooms(
        $room->property,
        '2026-07-10',
        '2026-07-12',
    );

    expect($available->pluck('id')->all())->toBe([$room->id]);

    availability()->assertValidStay($room->property, '2026-07-10', '2026-07-12', 2, [$room]);
});

// AV-02: an active booking for 10-12 (nights 10 and 11) rejects a
// request for 11-13 for the same room (partial overlap).
it('AV-02: rejects a request that partially overlaps active nights', function () {
    freezeNow('2026-06-15 10:00:00');

    $room = Room::factory()->create(['status' => RoomStatus::Active]);

    Booking::factory()->confirmed()->createWithNights($room, 300000, [
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ]);

    $available = availability()->availableRooms($room->property, '2026-07-11', '2026-07-13');

    expect($available->pluck('id')->all())->not->toContain($room->id);

    expectAvailabilityError(
        fn () => availability()->assertValidStay($room->property, '2026-07-11', '2026-07-13', 2, [$room]),
        'active nights',
    );
});

// AV-03: same-day turnover is allowed - a booking for 10-12 does not
// block a new stay starting on 12 (BR-01.3).
it('AV-03: allows same-day turnover between two stays', function () {
    freezeNow('2026-06-15 10:00:00');

    $room = Room::factory()->create(['status' => RoomStatus::Active]);

    Booking::factory()->checkedIn()->createWithNights($room, 300000, [
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ]);

    $available = availability()->availableRooms($room->property, '2026-07-12', '2026-07-14');

    expect($available->pluck('id')->all())->toBe([$room->id]);

    availability()->assertValidStay($room->property, '2026-07-12', '2026-07-14', 2, [$room]);
});

// AV-04: a room block covering one night of the range makes the room
// unavailable. Blocks follow the check-out convention: the block
// [start, end) covers nights start .. end-1 (enforced by the database
// CHECK end_date > start_date, so a one-night block is start=X,
// end=X+1).
it('AV-04: hides a room when a block covers one night of the range', function () {
    freezeNow('2026-06-15 10:00:00');

    $room = Room::factory()->create(['status' => RoomStatus::Active]);

    RoomBlock::factory()->create([
        'room_id' => $room->id,
        'start_date' => '2026-07-11',
        'end_date' => '2026-07-12', // one night: 11 July
    ]);

    $available = availability()->availableRooms($room->property, '2026-07-10', '2026-07-13');

    expect($available->pluck('id')->all())->not->toContain($room->id);

    expectAvailabilityError(
        fn () => availability()->assertValidStay($room->property, '2026-07-10', '2026-07-13', 2, [$room]),
        'blocked',
    );

    // A stay that starts after the block still fits (turnover with a
    // block: block night 11, stay from 12).
    availability()->assertValidStay($room->property, '2026-07-12', '2026-07-14', 2, [$room]);
});

// AV-05: maintenance and inactive rooms are not sellable (BR-07.1).
it('AV-05: excludes maintenance and inactive rooms', function () {
    freezeNow('2026-06-15 10:00:00');

    $active = Room::factory()->create(['status' => RoomStatus::Active]);
    $maintenance = Room::factory()->create([
        'property_id' => $active->property_id,
        'status' => RoomStatus::Maintenance,
    ]);
    $inactive = Room::factory()->create([
        'property_id' => $active->property_id,
        'status' => RoomStatus::Inactive,
    ]);

    $available = availability()->availableRooms($active->property, '2026-07-10', '2026-07-12');

    expect($available->pluck('id')->all())->toBe([$active->id]);

    expectAvailabilityError(
        fn () => availability()->assertValidStay($active->property, '2026-07-10', '2026-07-12', 2, [$maintenance]),
        'not active',
    );
    expectAvailabilityError(
        fn () => availability()->assertValidStay($active->property, '2026-07-10', '2026-07-12', 2, [$inactive]),
        'not active',
    );
});

// AV-06: cancelled, expired and no-show bookings have inactive nights
// and must not block the room (BR-01).
it('AV-06: cancelled, expired and no-show bookings do not block', function (string $state) {
    freezeNow('2026-06-15 10:00:00');

    $room = Room::factory()->create(['status' => RoomStatus::Active]);

    Booking::factory()->{$state}()->createWithNights($room, 300000, [
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ]);

    $available = availability()->availableRooms($room->property, '2026-07-10', '2026-07-12');

    expect($available->pluck('id')->all())->toBe([$room->id]);

    availability()->assertValidStay($room->property, '2026-07-10', '2026-07-12', 2, [$room]);
})->with([
    'cancelled' => ['cancelled'],
    'expired' => ['expired'],
    'no_show' => ['noShow'],
]);

// AV-07: a pending booking holds its nights and does block.
it('AV-07: a pending booking blocks the room', function () {
    freezeNow('2026-06-15 10:00:00');

    $room = Room::factory()->create(['status' => RoomStatus::Active]);

    Booking::factory()->pending()->createWithNights($room, 300000, [
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ]);

    $available = availability()->availableRooms($room->property, '2026-07-10', '2026-07-12');

    expect($available->pluck('id')->all())->not->toContain($room->id);

    expectAvailabilityError(
        fn () => availability()->assertValidStay($room->property, '2026-07-10', '2026-07-12', 2, [$room]),
        'active nights',
    );
});

// AV-08: housekeeping status never affects future availability
// (BR-07.5).
it('AV-08: a dirty room stays available for future dates', function () {
    freezeNow('2026-06-15 10:00:00');

    $room = Room::factory()->create(['status' => RoomStatus::Active]);
    $room->housekeepingStatus()->create(['status' => 'dirty']);

    $available = availability()->availableRooms($room->property, '2026-07-10', '2026-07-12');

    expect($available->pluck('id')->all())->toBe([$room->id]);

    availability()->assertValidStay($room->property, '2026-07-10', '2026-07-12', 2, [$room]);
});

// AV-09: guest_count above the total capacity of the selected rooms is
// rejected (BR-01.2; capacity is the maximum per room type).
it('AV-09: rejects a guest count above the total capacity of the rooms', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomA = Room::factory()->create(['status' => RoomStatus::Active]);
    $roomB = Room::factory()->create([
        'property_id' => $roomA->property_id,
        'room_type_id' => $roomA->room_type_id,
        'status' => RoomStatus::Active,
    ]);

    // Both rooms share a room type with capacity 2: total capacity 4.
    availability()->assertValidStay($roomA->property, '2026-07-10', '2026-07-12', 4, [$roomA, $roomB]);

    expectAvailabilityError(
        fn () => availability()->assertValidStay($roomA->property, '2026-07-10', '2026-07-12', 5, [$roomA, $roomB]),
        'capacity',
    );

    // availableRooms also filters by capacity for public searches.
    $forTwo = availability()->availableRooms($roomA->property, '2026-07-10', '2026-07-12', 2);
    $forFive = availability()->availableRooms($roomA->property, '2026-07-10', '2026-07-12', 5);

    expect($forTwo->pluck('id')->all())->toBe([$roomA->id, $roomB->id])
        ->and($forFive->pluck('id')->all())->toBe([]);
});

// Review finding: the same room passed twice must not double its
// capacity. One room of capacity 2 cannot host 3 guests just because
// it was listed twice.
it('deduplicates the same room so its capacity is not counted twice', function () {
    freezeNow('2026-06-15 10:00:00');

    $room = Room::factory()->create(['status' => RoomStatus::Active]); // capacity 2

    // Listed twice, still only capacity 2.
    expectAvailabilityError(
        fn () => availability()->assertValidStay($room->property, '2026-07-10', '2026-07-12', 3, [$room, $room]),
        'capacity',
    );

    availability()->assertValidStay($room->property, '2026-07-10', '2026-07-12', 2, [$room, $room]);
});

// Review finding: excludeBookingId lets an edited booking re-check its
// own dates without blocking itself; other bookings and blocks still
// block (this is the hook T-013 change-booking will rely on).
it('excludes one booking from blocking itself via excludeBookingId', function () {
    freezeNow('2026-06-15 10:00:00');

    $roomA = Room::factory()->create(['status' => RoomStatus::Active]);
    $roomB = Room::factory()->create([
        'property_id' => $roomA->property_id,
        'status' => RoomStatus::Active,
    ]);

    // My booking currently holds roomA for 10-12.
    $mine = Booking::factory()->confirmed()->createWithNights($roomA, 300000, [
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ]);

    // Re-checking the same room without the exclusion: my own nights
    // block me.
    expectAvailabilityError(
        fn () => availability()->assertValidStay($roomA->property, '2026-07-10', '2026-07-12', 2, [$roomA]),
        'active nights',
    );

    // With the exclusion I no longer block myself, and roomB is free.
    availability()->assertValidStay(
        $roomA->property,
        '2026-07-10',
        '2026-07-12',
        4,
        [$roomA, $roomB],
        null,
        null,
        $mine->id,
    );

    // A different booking on roomB still blocks, even while my own
    // nights on roomA are excluded (a consistent state: no two active
    // bookings share a room and night).
    Booking::factory()->confirmed()->createWithNights($roomB, 300000, [
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ]);

    expectAvailabilityError(
        fn () => availability()->assertValidStay(
            $roomA->property,
            '2026-07-10',
            '2026-07-12',
            4,
            [$roomA, $roomB],
            null,
            null,
            $mine->id,
        ),
        'active nights',
    );
});

// AV-10: check_out_date equal to or before check_in_date is rejected
// by validation (the database CHECK is the second layer, BR-01.2).
it('AV-10: rejects a check-out date on or before the check-in date', function (string $checkIn, string $checkOut) {
    freezeNow('2026-06-15 10:00:00');

    $room = Room::factory()->create(['status' => RoomStatus::Active]);

    expectAvailabilityError(
        fn () => availability()->assertValidStay($room->property, $checkIn, $checkOut, 2, [$room]),
        'after check_in_date',
    );
    expectAvailabilityError(
        fn () => availability()->availableRooms($room->property, $checkIn, $checkOut),
        'after check_in_date',
    );
})->with([
    'same day' => ['2026-07-10', '2026-07-10'],
    'backwards' => ['2026-07-12', '2026-07-10'],
]);

// AV-11: a past check-in date is rejected for a receptionist, and
// allowed for Manager or Owner only with a reason (BR-01.4).
it('AV-11: guards past check-in dates by role and reason', function () {
    freezeNow('2026-06-15 10:00:00');

    $room = Room::factory()->create(['status' => RoomStatus::Active]);
    $property = $room->property;

    $receptionist = User::factory()->create([
        'role_id' => Role::factory()->create(['name' => 'receptionist'])->id,
    ]);
    $manager = User::factory()->create([
        'role_id' => Role::factory()->create(['name' => 'manager'])->id,
    ]);
    $owner = User::factory()->create([
        'role_id' => Role::factory()->create(['name' => 'owner'])->id,
    ]);

    // Future check-in: any user, no reason needed.
    availability()->assertValidStay($property, '2026-07-10', '2026-07-12', 2, [$room], $receptionist);

    // Past check-in: receptionist rejected even with a reason.
    expectAvailabilityError(
        fn () => availability()->assertValidStay($property, '2026-06-10', '2026-06-12', 2, [$room], $receptionist, 'Walk-in correction'),
        'past',
    );

    // Past check-in: anonymous (public engine) rejected.
    expectAvailabilityError(
        fn () => availability()->assertValidStay($property, '2026-06-10', '2026-06-12', 2, [$room]),
        'past',
    );

    // Past check-in: manager without a reason rejected.
    expectAvailabilityError(
        fn () => availability()->assertValidStay($property, '2026-06-10', '2026-06-12', 2, [$room], $manager),
        'reason',
    );

    // Past check-in: manager and owner with a reason allowed.
    availability()->assertValidStay($property, '2026-06-10', '2026-06-12', 2, [$room], $manager, 'Recording a correction');
    availability()->assertValidStay($property, '2026-06-10', '2026-06-12', 2, [$room], $owner, 'Recording a correction');
});

// AV-12: creating a block on dates that have active nights is
// rejected; inactive nights and non-overlapping blocks are fine
// (BR-01.6, US-08: the index only guards bookings against bookings).
it('AV-12: rejects a block that overlaps active nights', function () {
    freezeNow('2026-06-15 10:00:00');

    $room = Room::factory()->create(['status' => RoomStatus::Active]);

    Booking::factory()->confirmed()->createWithNights($room, 300000, [
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ]);

    expectAvailabilityError(
        fn () => availability()->assertRoomCanBeBlocked($room, '2026-07-11', '2026-07-12'),
        'active nights',
    );

    // A block starting right after the last night is allowed.
    availability()->assertRoomCanBeBlocked($room, '2026-07-12', '2026-07-14');
});

// AV-12b: inactive nights (cancelled booking) do not stop a block.
it('AV-12: allows a block when the overlapping nights are inactive', function () {
    freezeNow('2026-06-15 10:00:00');

    $room = Room::factory()->create(['status' => RoomStatus::Active]);

    Booking::factory()->cancelled()->createWithNights($room, 300000, [
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ]);

    expect(fn () => availability()->assertRoomCanBeBlocked($room, '2026-07-10', '2026-07-12'))
        ->not->toThrow(AvailabilityException::class);
});

// AV-13: moving a room to maintenance or inactive is rejected while
// active nights start from today onwards; past nights do not count
// (BR-07.2, US-05).
it('AV-13: rejects maintenance and inactive while future active nights exist', function () {
    freezeNow('2026-06-15 10:00:00');

    $room = Room::factory()->create(['status' => RoomStatus::Active]);

    Booking::factory()->confirmed()->createWithNights($room, 300000, [
        'check_in_date' => '2026-06-16', // starts tomorrow: still upcoming
        'check_out_date' => '2026-06-18',
    ]);

    expectAvailabilityError(
        fn () => availability()->assertRoomStatusChangeAllowed($room, RoomStatus::Maintenance),
        'active nights',
    );
    expectAvailabilityError(
        fn () => availability()->assertRoomStatusChangeAllowed($room, RoomStatus::Inactive),
        'active nights',
    );

    // Setting a room to active is always allowed.
    availability()->assertRoomStatusChangeAllowed($room, RoomStatus::Active);

    // Only nights from today onwards count: a completed stay in the
    // past does not block a status change.
    $past = Room::factory()->create(['status' => RoomStatus::Active]);
    Booking::factory()->checkedOut()->createWithNights($past, 300000, [
        'check_in_date' => '2026-06-05',
        'check_out_date' => '2026-06-07',
    ]);

    availability()->assertRoomStatusChangeAllowed($past, RoomStatus::Maintenance);
});

// Review finding: the exact "today" boundary of BR-07.2. A night dated
// today is still upcoming (the room is occupied tonight), so it blocks
// maintenance; the interpretation is stay_date >= today, not > today.
it('AV-13: a night dated exactly today blocks maintenance and inactive', function () {
    freezeNow('2026-06-15 10:00:00');

    $room = Room::factory()->create(['status' => RoomStatus::Active]);

    // Stay starts today: the night of 15 June is active tonight.
    Booking::factory()->checkedIn()->createWithNights($room, 300000, [
        'check_in_date' => '2026-06-15',
        'check_out_date' => '2026-06-17',
    ]);

    expectAvailabilityError(
        fn () => availability()->assertRoomStatusChangeAllowed($room, RoomStatus::Maintenance),
        'active nights',
    );
    expectAvailabilityError(
        fn () => availability()->assertRoomStatusChangeAllowed($room, RoomStatus::Inactive),
        'active nights',
    );

    // Contrast: a stay that ended yesterday leaves only past nights.
    $ended = Room::factory()->create(['status' => RoomStatus::Active]);
    Booking::factory()->checkedOut()->createWithNights($ended, 300000, [
        'check_in_date' => '2026-06-13',
        'check_out_date' => '2026-06-14', // last night 13 June, before today
    ]);

    availability()->assertRoomStatusChangeAllowed($ended, RoomStatus::Maintenance);
});

// AV-14: a room whose room type is inactive is not sellable even when
// rooms.status is active (BR-01.1 now requires room_types.is_active =
// true, BR-07.6). It is refused the same way a non-active room status
// is, both when listing rooms and when validating a stay.
it('AV-14: excludes rooms whose room type is inactive', function () {
    freezeNow('2026-06-15 10:00:00');

    $active = Room::factory()->create(['status' => RoomStatus::Active]);

    $retiredType = RoomType::factory()->create([
        'property_id' => $active->property_id,
        'is_active' => false,
    ]);
    $retired = Room::factory()->create([
        'property_id' => $active->property_id,
        'room_type_id' => $retiredType->id,
        'status' => RoomStatus::Active,
    ]);

    $available = availability()->availableRooms($active->property, '2026-07-10', '2026-07-12');

    expect($available->pluck('id')->all())->toBe([$active->id]);

    expectAvailabilityError(
        fn () => availability()->assertValidStay($active->property, '2026-07-10', '2026-07-12', 2, [$retired]),
        'inactive room type',
    );
});
