<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| T-005 - Transaction table constraints
|--------------------------------------------------------------------------
|
| Layer 3 of BR-01 (three-layer double booking prevention) lives in the
| database itself. These tests prove the database, not the application,
| rejects double booking nights and impossible date ranges, keeps every
| foreign key restrictive, and carries the performance indexes listed in
| docs/arsitektur_sistem.md section 7.
|
| BK-05 in docs/rencana_pengujian.md: inserting two active nights with the
| same (room_id, stay_date) must be rejected by the database.
|
*/

/**
 * Insert the minimal master rows a booking needs and return their ids.
 */
function txSetupFixture(): array
{
    $propertyId = DB::table('properties')->insertGetId([
        'name' => 'Kamarin Constraint Test',
        'address' => 'Jl. Test No. 1',
        'city' => 'Makassar',
    ]);

    $roomTypeId = DB::table('room_types')->insertGetId([
        'property_id' => $propertyId,
        'name' => 'Standard',
        'capacity' => 2,
        'is_active' => true,
    ]);

    $roomId = DB::table('rooms')->insertGetId([
        'property_id' => $propertyId,
        'room_type_id' => $roomTypeId,
        'room_number' => 'A1',
        'status' => 'active',
    ]);

    $guestId = DB::table('guests')->insertGetId(['name' => 'Test Guest']);

    $sourceId = DB::table('booking_sources')->insertGetId(['name' => 'Website '.Str::random(6)]);

    $userId = User::factory()->create()->id;

    return [
        'property_id' => $propertyId,
        'room_id' => $roomId,
        'guest_id' => $guestId,
        'source_id' => $sourceId,
        'user_id' => $userId,
    ];
}

/**
 * Insert a booking and return its id. Defaults to a valid 2-night stay.
 */
function txInsertBooking(array $fixture, string $checkIn = '2026-03-01', string $checkOut = '2026-03-03'): int
{
    return DB::table('bookings')->insertGetId([
        'property_id' => $fixture['property_id'],
        'guest_id' => $fixture['guest_id'],
        'created_by_user_id' => $fixture['user_id'],
        'booking_source_id' => $fixture['source_id'],
        'booking_code' => 'BK'.Str::random(7),
        'status' => 'confirmed',
        'payment_option' => 'bank_transfer',
        'guest_count' => 2,
        'check_in_date' => $checkIn,
        'check_out_date' => $checkOut,
        'total_amount' => 500000,
    ]);
}

/**
 * Insert a booking item (one room per booking) and return its id.
 */
function txInsertBookingItem(array $fixture, int $bookingId): int
{
    return DB::table('booking_items')->insertGetId([
        'booking_id' => $bookingId,
        'room_id' => $fixture['room_id'],
        'subtotal' => 500000,
    ]);
}

/**
 * Insert one booking night and return its id.
 */
function txInsertNight(array $fixture, int $itemId, string $stayDate, bool $active = true, ?int $roomId = null): int
{
    return DB::table('booking_nights')->insertGetId([
        'booking_item_id' => $itemId,
        'room_id' => $roomId ?? $fixture['room_id'],
        'rate_id' => null,
        'stay_date' => $stayDate,
        'price' => 250000,
        'is_active' => $active,
    ]);
}

it('BK-05: rejects a second active night for the same room and date', function () {
    $fixture = txSetupFixture();
    $bookingId = txInsertBooking($fixture);
    $itemId = txInsertBookingItem($fixture, $bookingId);

    txInsertNight($fixture, $itemId, '2026-03-01', true);

    // Same room, same stay date, another booking item: the partial unique
    // index must make PostgreSQL itself refuse the insert.
    $otherBookingId = txInsertBooking($fixture, '2026-03-01', '2026-03-02');
    $otherItemId = txInsertBookingItem($fixture, $otherBookingId);

    // The message assertion proves the partial unique index rejected the
    // insert, not some unrelated database error.
    expect(fn () => txInsertNight($fixture, $otherItemId, '2026-03-01', true))
        ->toThrow(QueryException::class, 'booking_nights_room_stay_active_unique');
});

it('allows an inactive night next to an active night for the same room and date', function () {
    $fixture = txSetupFixture();
    $bookingId = txInsertBooking($fixture);
    $itemId = txInsertBookingItem($fixture, $bookingId);

    txInsertNight($fixture, $itemId, '2026-03-01', true);

    // A cancelled or expired night (is_active = false) must not collide with
    // the active one: the partial index only indexes active rows, so a
    // cancelled booking never blocks a new one on the same date.
    txInsertNight($fixture, $itemId, '2026-03-01', false);

    $nights = DB::table('booking_nights')->where('stay_date', '2026-03-01')->count();

    expect($nights)->toBe(2);
});

it('allows two inactive nights for the same room and date', function () {
    $fixture = txSetupFixture();
    $bookingId = txInsertBooking($fixture);
    $itemId = txInsertBookingItem($fixture, $bookingId);

    txInsertNight($fixture, $itemId, '2026-03-01', false);
    txInsertNight($fixture, $itemId, '2026-03-01', false);

    expect(DB::table('booking_nights')->where('stay_date', '2026-03-01')->count())->toBe(2);
});

it('rejects a booking whose check_out_date is not after check_in_date', function () {
    $fixture = txSetupFixture();

    // BR-01 point 2: check_out_date must be greater than check_in_date.
    // Each violation runs inside a savepoint (DB::transaction): after a
    // failed statement the surrounding test transaction is aborted, so
    // without a savepoint the second violation would fail with 25P02
    // instead of reaching the CHECK. The message assertion proves the
    // constraint fired.
    expect(fn () => DB::transaction(fn () => txInsertBooking($fixture, '2026-03-01', '2026-03-01')))
        ->toThrow(QueryException::class, 'bookings_check_out_after_checkin');

    expect(fn () => DB::transaction(fn () => txInsertBooking($fixture, '2026-03-03', '2026-03-01')))
        ->toThrow(QueryException::class, 'bookings_check_out_after_checkin');
});

it('rejects a room block whose end_date is not after start_date', function () {
    $fixture = txSetupFixture();

    $block = fn (string $start, string $end) => DB::table('room_blocks')->insert([
        'room_id' => $fixture['room_id'],
        'created_by_user_id' => $fixture['user_id'],
        'start_date' => $start,
        'end_date' => $end,
        'reason' => 'Maintenance',
    ]);

    // Savepoints (DB::transaction) keep the test transaction usable after
    // each violation, same as in the bookings CHECK test above.
    expect(fn () => DB::transaction(fn () => $block('2026-03-01', '2026-03-01')))
        ->toThrow(QueryException::class, 'room_blocks_end_after_start');
    expect(fn () => DB::transaction(fn () => $block('2026-03-05', '2026-03-01')))
        ->toThrow(QueryException::class, 'room_blocks_end_after_start');

    $block('2026-03-01', '2026-03-02');

    expect(DB::table('room_blocks')->count())->toBe(1);
});

it('keeps every foreign key restrictive (no cascade)', function () {
    // AGENTS.md rule: foreign keys use restrict, not cascade, unless a
    // reason is documented. PostgreSQL's default NO ACTION behaves like
    // restrict here (both forbid deleting a referenced row).
    $rules = DB::select("
        SELECT delete_rule, count(*) AS total
        FROM information_schema.referential_constraints
        WHERE constraint_schema = 'public'
        GROUP BY delete_rule
    ");

    foreach ($rules as $rule) {
        expect($rule->delete_rule)->toBe('NO ACTION', 'Foreign keys must not cascade');
    }

    $total = array_sum(array_map(fn ($rule) => (int) $rule->total, $rules));

    // 11 foreign keys from the master tables (T-004) + 18 from the
    // transaction tables (T-005).
    expect($total)->toBeGreaterThanOrEqual(29);
});

it('creates the performance indexes from arsitektur_sistem.md section 7', function () {
    $defs = [];

    foreach (['booking_nights', 'bookings', 'payments', 'guests', 'room_blocks'] as $table) {
        $rows = DB::select(
            "SELECT indexdef FROM pg_indexes WHERE schemaname = 'public' AND tablename = ?",
            [$table]
        );

        $defs[$table] = array_map(fn ($row) => $row->indexdef, $rows);
    }

    $has = fn (string $table, string $needle): bool => collect($defs[$table])->contains(
        fn (string $def): bool => str_contains($def, $needle)
    );

    expect($has('booking_nights', 'booking_nights_room_stay_active_unique'))->toBeTrue('Partial unique index missing')
        ->and($has('booking_nights', 'WHERE is_active'))->toBeTrue('Partial index predicate must be is_active')
        ->and($has('booking_nights', '(stay_date)'))->toBeTrue('booking_nights.stay_date index missing')
        ->and($has('bookings', '(booking_code)'))->toBeTrue('bookings.booking_code index missing')
        ->and($has('bookings', '(property_id, check_in_date, check_out_date)'))->toBeTrue('bookings calendar index missing')
        ->and($has('bookings', '(status)'))->toBeTrue('bookings.status index missing')
        ->and($has('payments', '(booking_id)'))->toBeTrue('payments.booking_id index missing')
        ->and($has('guests', '(email)'))->toBeTrue('guests.email index missing')
        ->and($has('guests', '(phone)'))->toBeTrue('guests.phone index missing')
        ->and($has('room_blocks', '(room_id, start_date, end_date)'))->toBeTrue('room_blocks availability index missing');
});
