<?php

use App\Enums\PaymentOption;
use App\Enums\RoomStatus;
use App\Exceptions\AvailabilityException;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingSource;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Rate;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use App\Services\BookingService;
use App\Services\RoomBlockService;
use Illuminate\Support\Facades\DB;

// Test cases AV-12 and US-08 (docs/rencana_pengujian.md section 4.1,
// docs/user_stories.md US-08), specifically for RoomBlockService (T-016).
//
// BR-01.5 requires every writer of room placement to lock the room rows
// with SELECT ... FOR UPDATE in id order and re-check underneath; BR-01.6
// states the partial unique index only guards booking against booking, so
// the clash between a booking and a room block lives in the service.

function blockService(): RoomBlockService
{
    return app(RoomBlockService::class);
}

/** A room that is sellable: active, of an active room type, with a rate. */
function blockRoom(Property $property): Room
{
    $room = Room::factory()->create([
        'property_id' => $property->id,
        'status' => RoomStatus::Active,
    ]);

    Rate::factory()->defaultRate()->create([
        'property_id' => $property->id,
        'room_type_id' => $room->room_type_id,
        'price' => 300000,
    ]);

    return $room;
}

function blockUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->first()
        ?? Role::factory()->create(['name' => $roleName]);

    return User::factory()->create(['role_id' => $role->id]);
}

// AV-12 / BR-01.6: a block may not be created on dates that already hold
// active nights; the booking must be moved or cancelled first.
it('AV-12: refuses to block a room that has active nights in the range', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();
    $room = blockRoom($property);
    $manager = blockUser('manager');

    // Holds the room for the night of 2026-07-10 only.
    Booking::factory()->confirmed()->createWithNights($room, 300000, [
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-11',
    ]);

    expect(fn () => blockService()->block($room, '2026-07-10', '2026-07-12', 'Repainting', $manager))
        ->toThrow(AvailabilityException::class);

    expect(RoomBlock::count())->toBe(0);
});

// US-08: blocking a free room records who did it and why, and the range
// is half-open [start_date, end_date) - the night of end_date is free.
it('US-08: blocks a free room, recording the reason and the actor on a half-open range', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();
    $room = blockRoom($property);
    $manager = blockUser('manager');

    $block = blockService()->block($room, '2026-07-10', '2026-07-12', 'Air conditioner service', $manager);

    expect($block->room_id)->toBe($room->id)
        ->and($block->start_date->toDateString())->toBe('2026-07-10')
        ->and($block->end_date->toDateString())->toBe('2026-07-12')
        ->and($block->reason)->toBe('Air conditioner service')
        ->and($block->created_by_user_id)->toBe($manager->id)
        ->and($manager->id)->not->toBeNull();
});

// BR-01.6 + the half-open convention: blocks may sit back to back, because
// the night of one block's end_date is the next block's first night.
it('allows adjacent blocks but refuses an overlapping one', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();
    $room = blockRoom($property);
    $manager = blockUser('manager');

    blockService()->block($room, '2026-07-10', '2026-07-12', 'First service', $manager);

    // Touching, not overlapping: 07-12 is the first night of the new block.
    blockService()->block($room, '2026-07-12', '2026-07-14', 'Second service', $manager);

    expect(RoomBlock::count())->toBe(2);

    // Overlaps the tail of the first block by one night.
    expect(fn () => blockService()->block($room, '2026-07-11', '2026-07-13', 'Third service', $manager))
        ->toThrow(AvailabilityException::class);

    expect(RoomBlock::count())->toBe(2);
});

// US-08 "Alasan blokir tercatat": a blank reason is refused, not silently
// stored, because the block has to be explainable later.
it('requires a non-blank reason', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();
    $room = blockRoom($property);
    $manager = blockUser('manager');

    expect(fn () => blockService()->block($room, '2026-07-10', '2026-07-12', '   ', $manager))
        ->toThrow(BookingException::class);

    expect(RoomBlock::count())->toBe(0);
});

// AGENTS.md rules 3 and 8: the allow-list names the roles that may block,
// so an unrecognised role is refused rather than silently missed.
it('only lets the owner and the manager block a room', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();

    foreach (['receptionist', 'housekeeping', 'accountant'] as $roleName) {
        $room = blockRoom($property);
        $actor = blockUser($roleName);

        expect(fn () => blockService()->block($room, '2026-07-10', '2026-07-12', 'Blocked', $actor))
            ->toThrow(BookingException::class, "Role \"{$roleName}\" may not block a room");

        // A refused role must not leave a block behind.
        expect(RoomBlock::count())->toBe(0);
    }

    // The owner and the manager both pass.
    blockService()->block(blockRoom($property), '2026-07-10', '2026-07-12', 'Owner block', blockUser('owner'));
    blockService()->block(blockRoom($property), '2026-07-10', '2026-07-12', 'Manager block', blockUser('manager'));

    expect(RoomBlock::count())->toBe(2);
});

// BR-01.5: creating a block is a writer of room placement, so it must take
// SELECT ... FOR UPDATE on the room row, ordered by id, and the availability
// re-check has to come AFTER that lock - otherwise the lock protects nothing.
it('AV-12: locks the room row for update before rechecking availability', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();
    $room = blockRoom($property);
    $manager = blockUser('manager');

    $sql = [];
    DB::listen(function ($query) use (&$sql) {
        $sql[] = strtolower($query->sql);
    });

    blockService()->block($room, '2026-07-10', '2026-07-12', 'Air conditioner service', $manager);

    $lockQueries = array_values(array_filter(
        $sql,
        fn (string $statement) => str_contains($statement, 'for update')
            && str_contains($statement, 'from "rooms"'),
    ));

    expect($lockQueries)->not->toBeEmpty()
        ->and(str_contains($lockQueries[0], 'order by "id" asc'))->toBeTrue();

    // The lock must be taken before the clash is re-checked, not after.
    $lockAt = array_search($lockQueries[0], $sql, true);
    $recheckAt = array_search(true, array_map(
        fn (string $statement) => str_contains($statement, 'from "booking_nights"'),
        $sql,
    ), true);

    expect($lockAt)->not->toBeFalse()
        ->and($recheckAt)->not->toBeFalse()
        ->and($lockAt)->toBeLessThan($recheckAt);
});

// BR-01.6: the partial index only guards booking against booking, so a
// block has no database safety net. A single-threaded test cannot race
// lockForUpdate directly, so the race window is simulated honestly, in the
// same way as BK-05 for BookingService: the availability check runs for
// real and passes, because nothing is taken yet at that point, and a model
// hook then lands a conflicting block in the gap between that recheck and
// the write. The service must still refuse, and nothing may survive.
it('refuses a block whose conflict lands in the gap between the recheck and the write', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();
    $room = blockRoom($property);
    $manager = blockUser('manager');

    // Fires after the availability re-check has already passed and before
    // the block row is written. withoutEvents keeps the racing block from
    // re-entering this same hook.
    RoomBlock::creating(function () use ($room, $manager): void {
        RoomBlock::withoutEvents(function () use ($room, $manager): void {
            RoomBlock::factory()->create([
                'room_id' => $room->id,
                'created_by_user_id' => $manager->id,
                'start_date' => '2026-07-11',
                'end_date' => '2026-07-13',
                'reason' => 'Racing block',
            ]);
        });
    });

    expect(fn () => blockService()->block($room, '2026-07-10', '2026-07-12', 'Air conditioner service', $manager))
        ->toThrow(AvailabilityException::class);

    // Everything in that transaction - including the racing block the hook
    // inserted - was rolled back, so not a single row survives.
    expect(RoomBlock::count())->toBe(0);
});

// US-08 "Kamar yang diblokir tidak tersedia untuk tanggal itu": the block
// has to close the door for later bookings, through AvailabilityService.
it('makes the blocked dates unavailable to a later booking', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();
    $room = blockRoom($property);
    $manager = blockUser('manager');

    blockService()->block($room, '2026-07-10', '2026-07-12', 'Air conditioner service', $manager);

    $guest = Guest::factory()->create();
    $source = BookingSource::query()->first() ?? BookingSource::factory()->create();

    expect(fn () => app(BookingService::class)->createBooking(
        $property,
        $guest,
        $source,
        PaymentOption::PayOnArrival,
        2,
        '2026-07-10',
        '2026-07-12',
        [$room],
        blockUser('receptionist'),
    ))->toThrow(AvailabilityException::class);

    // The half-open range means the night right after the block is free.
    app(BookingService::class)->createBooking(
        $property,
        $guest,
        $source,
        PaymentOption::PayOnArrival,
        2,
        '2026-07-12',
        '2026-07-13',
        [$room],
        blockUser('receptionist'),
    );

    expect(Booking::count())->toBe(1);
});

// Removing a block re-opens the room, and is the same restricted action
// as creating one.
it('unblocks a room and restricts who may do it', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();
    $room = blockRoom($property);
    $manager = blockUser('manager');

    $block = blockService()->block($room, '2026-07-10', '2026-07-12', 'Air conditioner service', $manager);

    expect(fn () => blockService()->unblock($block, blockUser('receptionist')))
        ->toThrow(BookingException::class);

    expect(RoomBlock::count())->toBe(1);

    blockService()->unblock($block, blockUser('owner'));

    expect(RoomBlock::count())->toBe(0)
        ->and(RoomBlock::query()->find($block->id))->toBeNull();
});
