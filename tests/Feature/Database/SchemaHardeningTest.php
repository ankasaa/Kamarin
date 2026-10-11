<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| T-005b - Booking schema hardening
|--------------------------------------------------------------------------
|
| (1) Composite foreign key: a booking night can never point at a room
| different from its parent booking item, and moving a booking item to
| another room (BR-06 "pindah kamar") moves its nights through ON UPDATE
| CASCADE - while the partial unique index still refuses to land active
| nights on an occupied (room, date).
|
| (2) Reasonableness CHECK constraints with explicit names: money stays
| non-negative (BR-12), guest count and capacity stay positive, policy
| settings stay inside their bounds, and a rate's end date never precedes
| its start date.
|
| Every rejection test asserts the PostgreSQL error message carries the
| exact constraint name, so a green test can never be caused by an
| unrelated database error.
|
| backlog: docs/tasks/BACKLOG_FASE4_5.md row T-005b.
|
*/

/**
 * Insert the minimal master rows the hardening tests need. Two rooms so a
 * booking item can be moved between them.
 */
function shSetupFixture(): array
{
    $propertyId = DB::table('properties')->insertGetId([
        'name' => 'Kamarin Hardening Test',
        'address' => 'Jl. Test No. 2',
        'city' => 'Makassar',
    ]);

    $roomTypeId = DB::table('room_types')->insertGetId([
        'property_id' => $propertyId,
        'name' => 'Standard',
        'capacity' => 2,
        'is_active' => true,
    ]);

    $roomA = DB::table('rooms')->insertGetId([
        'property_id' => $propertyId,
        'room_type_id' => $roomTypeId,
        'room_number' => 'H1',
        'status' => 'active',
    ]);

    $roomB = DB::table('rooms')->insertGetId([
        'property_id' => $propertyId,
        'room_type_id' => $roomTypeId,
        'room_number' => 'H2',
        'status' => 'active',
    ]);

    return [
        'property_id' => $propertyId,
        'room_type_id' => $roomTypeId,
        'room_a' => $roomA,
        'room_b' => $roomB,
        'guest_id' => DB::table('guests')->insertGetId(['name' => 'Hardening Guest']),
        'source_id' => DB::table('booking_sources')->insertGetId(['name' => 'Website '.Str::random(6)]),
        'user_id' => User::factory()->create()->id,
    ];
}

/**
 * Insert a booking and return its id. Defaults to a valid 2-night stay.
 */
function shInsertBooking(array $f, string $checkIn = '2026-03-01', string $checkOut = '2026-03-03'): int
{
    return DB::table('bookings')->insertGetId([
        'property_id' => $f['property_id'],
        'guest_id' => $f['guest_id'],
        'booking_source_id' => $f['source_id'],
        'booking_code' => 'SH'.Str::random(7),
        'status' => 'confirmed',
        'payment_option' => 'bank_transfer',
        'guest_count' => 2,
        'check_in_date' => $checkIn,
        'check_out_date' => $checkOut,
        'total_amount' => 500000,
    ]);
}

/**
 * Insert a booking item for the given room and return its id.
 */
function shInsertItem(array $f, int $bookingId, int $roomId): int
{
    return DB::table('booking_items')->insertGetId([
        'booking_id' => $bookingId,
        'room_id' => $roomId,
        'subtotal' => 500000,
    ]);
}

/**
 * Insert one booking night and return its id.
 */
function shInsertNight(int $itemId, int $roomId, string $stayDate, bool $active = true): int
{
    return DB::table('booking_nights')->insertGetId([
        'booking_item_id' => $itemId,
        'room_id' => $roomId,
        'stay_date' => $stayDate,
        'price' => 250000,
        'is_active' => $active,
    ]);
}

/**
 * Valid columns for one row of every table that carries a T-005b CHECK.
 * Parents (booking, booking item) are created here when the table needs one.
 */
function shValidRow(string $table, array $f): array
{
    return match ($table) {
        'properties' => [
            'name' => 'Hardening Property',
            'address' => 'Jl. Hardening 1',
            'city' => 'Makassar',
        ],
        'room_types' => [
            'property_id' => $f['property_id'],
            'name' => 'Hardening Type',
            'capacity' => 2,
            'is_active' => true,
        ],
        'rates' => [
            'property_id' => $f['property_id'],
            'room_type_id' => $f['room_type_id'],
            'name' => 'Hardening Rate',
            'price' => 300000,
            'start_date' => null,
            'end_date' => null,
            'priority' => 0,
            'is_default' => true,
            'minimum_nights' => 1,
        ],
        'bookings' => [
            'property_id' => $f['property_id'],
            'guest_id' => $f['guest_id'],
            'booking_source_id' => $f['source_id'],
            'booking_code' => 'SH'.Str::random(7),
            'status' => 'confirmed',
            'payment_option' => 'bank_transfer',
            'guest_count' => 2,
            'check_in_date' => '2026-03-01',
            'check_out_date' => '2026-03-03',
            'total_amount' => 500000,
        ],
        'booking_items' => [
            'booking_id' => shInsertBooking($f),
            'room_id' => $f['room_a'],
            'subtotal' => 500000,
        ],
        'booking_nights' => [
            'booking_item_id' => shInsertItem($f, shInsertBooking($f), $f['room_a']),
            'room_id' => $f['room_a'],
            'stay_date' => '2026-06-01',
            'price' => 250000,
            'is_active' => true,
        ],
        'payments' => [
            'booking_id' => shInsertBooking($f),
            'amount' => 100000,
            'type' => 'deposit',
            'method' => 'bank_transfer',
            'status' => 'pending',
            'payment_date' => '2026-03-01',
        ],
        default => throw new InvalidArgumentException("No valid row for table [{$table}]"),
    };
}

it('rejects a night whose room_id differs from its parent booking item', function () {
    $f = shSetupFixture();
    $bookingId = shInsertBooking($f);
    $itemId = shInsertItem($f, $bookingId, $f['room_a']);

    // The night claims room B while its parent item rents room A: only the
    // composite foreign key can refuse this, and the message assertion
    // proves it is the one that fired.
    expect(fn () => shInsertNight($itemId, $f['room_b'], '2026-06-10'))
        ->toThrow(QueryException::class, 'booking_nights_booking_item_id_room_id_foreign');
});

it('moves the nights with their booking item when room_id changes (BR-06 pindah kamar)', function () {
    $f = shSetupFixture();
    $bookingId = shInsertBooking($f);
    $itemId = shInsertItem($f, $bookingId, $f['room_a']);
    shInsertNight($itemId, $f['room_a'], '2026-06-01');
    shInsertNight($itemId, $f['room_a'], '2026-06-02');

    DB::table('booking_items')->where('id', $itemId)->update(['room_id' => $f['room_b']]);

    $rooms = DB::table('booking_nights')
        ->where('booking_item_id', $itemId)
        ->pluck('room_id')
        ->all();

    // ON UPDATE CASCADE must have rewritten both nights to room B.
    expect($rooms)->toBe([$f['room_b'], $f['room_b']]);
});

it('rejects moving an item to a room already occupied on one of its dates', function () {
    $f = shSetupFixture();

    // Item 1 keeps room A occupied on 10 and 11 June (active nights).
    $booking1 = shInsertBooking($f);
    $item1 = shInsertItem($f, $booking1, $f['room_a']);
    shInsertNight($item1, $f['room_a'], '2026-06-10');
    shInsertNight($item1, $f['room_a'], '2026-06-11');

    // Item 2 sits in room B with one active night on 10 June.
    $booking2 = shInsertBooking($f, '2026-06-10', '2026-06-11');
    $item2 = shInsertItem($f, $booking2, $f['room_b']);
    shInsertNight($item2, $f['room_b'], '2026-06-10');

    // Moving item 2 to room A cascades its night onto (A, 10 June), where
    // an active night already lives: the partial unique index must stop
    // the move mid-cascade.
    expect(fn () => DB::table('booking_items')->where('id', $item2)->update(['room_id' => $f['room_a']]))
        ->toThrow(QueryException::class, 'booking_nights_room_stay_active_unique');
});

it('gives the composite foreign key ON UPDATE CASCADE and ON DELETE NO ACTION', function () {
    $rule = DB::selectOne("
        SELECT update_rule, delete_rule
        FROM information_schema.referential_constraints
        WHERE constraint_schema = 'public'
          AND constraint_name = 'booking_nights_booking_item_id_room_id_foreign'
    ");

    expect($rule)->not->toBeNull('Composite foreign key is missing')
        ->and($rule->update_rule)->toBe('CASCADE')
        ->and($rule->delete_rule)->toBe('NO ACTION');
});

it('rejects bad values with the named reasonableness CHECK constraint', function (string $table, array $bad, string $constraint) {
    $f = shSetupFixture();
    $row = array_merge(shValidRow($table, $f), $bad);

    // The savepoint keeps the surrounding test transaction usable; the
    // message assertion proves the named CHECK refused the value.
    expect(fn () => DB::transaction(fn () => DB::table($table)->insert($row)))
        ->toThrow(QueryException::class, $constraint);
})->with([
    'bookings: guest_count must be positive' => ['bookings', ['guest_count' => 0], 'bookings_guest_count_positive'],
    'bookings: total_amount must not be negative' => ['bookings', ['total_amount' => -1], 'bookings_total_amount_non_negative'],
    'booking_items: subtotal must not be negative' => ['booking_items', ['subtotal' => -1], 'booking_items_subtotal_non_negative'],
    'booking_nights: price must not be negative' => ['booking_nights', ['price' => -1], 'booking_nights_price_non_negative'],
    'payments: amount must be positive' => ['payments', ['amount' => 0], 'payments_amount_positive'],
    'rates: price must not be negative' => ['rates', ['price' => -1], 'rates_price_non_negative'],
    'rates: minimum_nights at least 1' => ['rates', ['minimum_nights' => 0], 'rates_minimum_nights_positive'],
    'rates: end_date not before start_date' => ['rates', ['start_date' => '2026-05-01', 'end_date' => '2026-04-01'], 'rates_end_date_not_before_start'],
    'room_types: capacity must be positive' => ['room_types', ['capacity' => 0], 'room_types_capacity_positive'],
    'properties: deposit_percent above 100' => ['properties', ['deposit_percent' => 101], 'properties_deposit_percent_range'],
    'properties: deposit_percent below 0' => ['properties', ['deposit_percent' => -1], 'properties_deposit_percent_range'],
    'properties: pending_expiry_hours must be positive' => ['properties', ['pending_expiry_hours' => 0], 'properties_pending_expiry_positive'],
    'properties: free_cancel_days must not be negative' => ['properties', ['free_cancel_days' => -1], 'properties_free_cancel_days_non_negative'],
]);

it('keeps booking_charges.amount unrestricted because discounts are negative', function () {
    // T-005b deliberately adds no CHECK here: a discount is recorded as a
    // negative charge (decision locked in docs/STATUS.md).
    $f = shSetupFixture();
    $bookingId = shInsertBooking($f);

    DB::table('booking_charges')->insert([
        'booking_id' => $bookingId,
        'description' => 'Loyalty discount',
        'amount' => -50000,
    ]);

    expect(DB::table('booking_charges')->count())->toBe(1);
});
