<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentOption;
use App\Enums\RoomStatus;
use App\Exceptions\AvailabilityException;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingNight;
use App\Models\BookingSource;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Rate;
use App\Models\Room;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

// Test cases BK-01 .. BK-08 from docs/rencana_pengujian.md section 4.3,
// covering BR-01 layer 2 (row lock + recheck in a transaction), BR-13
// (pending expiry), BR-14 (booking code) and BR-09 (booking source).

function bookingService(): BookingService
{
    return app(BookingService::class);
}

/**
 * An active room whose room type is active and owns a default rate, so
 * it is sellable: availability and pricing both pass for it.
 */
function sellableRoom(Property $property, int $pricePerNight = 300000): Room
{
    $room = Room::factory()->create([
        'property_id' => $property->id,
        'status' => RoomStatus::Active,
    ]);

    Rate::factory()->defaultRate()->create([
        'property_id' => $property->id,
        'room_type_id' => $room->room_type_id,
        'price' => $pricePerNight,
    ]);

    return $room;
}

// BK-01: a valid booking becomes pending with a unique 8-character
// code, the right number of active nights, and a first status log
// (BR-14, US-12).
it('BK-01: creates a pending booking with a code, active nights and a status log', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();
    $room = sellableRoom($property);
    $guest = Guest::factory()->create();
    $source = BookingSource::factory()->create(['name' => 'Walk-in']);
    $staff = User::factory()->create();

    $booking = bookingService()->createBooking(
        $property,
        $guest,
        $source,
        PaymentOption::PayOnArrival,
        2,
        '2026-07-10',
        '2026-07-12',
        [$room],
        $staff,
    );

    $nights = $booking->bookingNights()->where('is_active', true)->count();

    expect($booking->status)->toBe(BookingStatus::Pending)
        ->and($booking->booking_code)->toMatch('/^[2-9A-HJ-NP-Z]{8}$/')
        ->and($booking->bookingItems)->toHaveCount(1)
        ->and($nights)->toBe(2) // 10 and 11 July; the check-out day is not a night
        ->and($booking->total_amount)->toBe(600000) // 2 nights x 300000 (BR-12)
        ->and($booking->bookingStatusLogs)->toHaveCount(1)
        ->and($booking->bookingStatusLogs->first()->new_status)->toBe(BookingStatus::Pending)
        ->and($booking->bookingStatusLogs->first()->old_status)->toBeNull();
});

// BK-02: a website booking gets expires_at = created_at + property's
// pending_expiry_hours; a staff booking has no expires_at (BR-13, K5).
it('BK-02: sets expires_at for website bookings but not for staff bookings', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create(['pending_expiry_hours' => 3]);
    $room = sellableRoom($property);
    $guest = Guest::factory()->create();
    $source = BookingSource::factory()->create(['name' => 'Website']);

    $website = bookingService()->createBooking(
        $property,
        $guest,
        $source,
        PaymentOption::BankTransfer,
        2,
        '2026-07-10',
        '2026-07-12',
        [$room],
        null, // no staff actor: created by a guest on the website
    );

    expect($website->created_by_user_id)->toBeNull()
        ->and($website->expires_at->toDateTimeString())
        ->toBe(now()->addHours(3)->toDateTimeString());

    $staff = User::factory()->create();
    $roomB = sellableRoom($property);

    $staffBooking = bookingService()->createBooking(
        $property,
        $guest,
        $source,
        PaymentOption::PayOnArrival,
        2,
        '2026-07-20',
        '2026-07-22',
        [$roomB],
        $staff,
    );

    expect($staffBooking->expires_at)->toBeNull()
        ->and($staffBooking->created_by_user_id)->toBe($staff->id);
});

// BK-03: a multi-room booking where one room already has active nights
// is rejected and nothing at all is stored (BR-01, atomicity).
it('BK-03: rolls back the whole multi-room booking when one room conflicts', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();
    $roomA = sellableRoom($property);
    $roomB = sellableRoom($property);
    $guest = Guest::factory()->create();

    // roomB is already taken for the requested nights.
    Booking::factory()->confirmed()->createWithNights($roomB, 300000, [
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ]);

    $source = BookingSource::factory()->create();
    $staff = User::factory()->create();

    expect(fn () => bookingService()->createBooking(
        $property,
        $guest,
        $source,
        PaymentOption::PayOnArrival,
        3, // fits the two rooms' combined capacity, but roomB is taken
        '2026-07-10',
        '2026-07-12',
        [$roomA, $roomB],
        $staff,
    ))->toThrow(AvailabilityException::class);

    // Only the pre-existing booking survives; nothing was half-created.
    expect(Booking::count())->toBe(1)
        ->and(BookingItem::count())->toBe(1)
        ->and(BookingNight::where('is_active', true)->count())->toBe(2);
});

// BK-04: a second request for the same room and nights is rejected, so
// exactly one booking wins (BR-01, US-13). True parallelism cannot be
// driven from a single-threaded test; this proves the guard serially,
// and BK-05 proves the database layer that closes the race window.
it('BK-04: only one booking wins the same room and nights', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();
    $room = sellableRoom($property);
    $guest = Guest::factory()->create();
    $source = BookingSource::factory()->create();
    $staff = User::factory()->create();

    $first = bookingService()->createBooking(
        $property,
        $guest,
        $source,
        PaymentOption::PayOnArrival,
        2,
        '2026-07-10',
        '2026-07-12',
        [$room],
        $staff,
    );

    expect(fn () => bookingService()->createBooking(
        $property,
        $guest,
        $source,
        PaymentOption::PayOnArrival,
        2,
        '2026-07-10',
        '2026-07-12',
        [$room],
        $staff,
    ))->toThrow(AvailabilityException::class);

    expect(Booking::count())->toBe(1)
        ->and(Booking::first()->id)->toBe($first->id);
});

// BK-05: a direct insert of a second ACTIVE night with the same
// (room_id, stay_date) is rejected by the partial unique index in the
// database (BR-01 layer 3).
it('BK-05: the database rejects a second active night for the same room and date', function () {
    $property = Property::factory()->create();
    $room = sellableRoom($property);

    $booking = Booking::factory()->confirmed()->createWithNights($room, 300000, [
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-11',
    ]);
    $item = $booking->bookingItems()->first();

    expect(fn () => BookingNight::factory()->create([
        'booking_item_id' => $item->id,
        'room_id' => $room->id,
        'stay_date' => '2026-07-10',
        'price' => 300000,
        'is_active' => true,
    ]))->toThrow(QueryException::class);
});

// BK-06: a booking without a source is rejected (BR-09).
it('BK-06: rejects a booking without a source', function () {
    $property = Property::factory()->create();
    $room = sellableRoom($property);
    $guest = Guest::factory()->create();
    $staff = User::factory()->create();

    expect(fn () => bookingService()->createBooking(
        $property,
        $guest,
        null, // no booking source
        PaymentOption::PayOnArrival,
        2,
        '2026-07-10',
        '2026-07-12',
        [$room],
        $staff,
    ))->toThrow(BookingException::class);

    expect(Booking::count())->toBe(0);
});

// BK-07: a returning guest with the same email is found and reused, not
// duplicated and not merged/overwritten automatically (US-21).
it('BK-07: finds a returning guest by email without merging automatically', function () {
    $existing = Guest::factory()->create([
        'email' => 'return@example.test',
        'name' => 'Alice',
    ]);

    $found = bookingService()->findOrCreateGuest('Alice Other Name', 'return@example.test', '0812');

    expect($found->id)->toBe($existing->id)
        ->and(Guest::where('email', 'return@example.test')->count())->toBe(1)
        ->and($found->name)->toBe('Alice'); // existing data is not overwritten

    // A guest without a matching email is created new.
    $new = bookingService()->findOrCreateGuest('Bob', 'new@example.test');

    expect($new->id)->not->toBe($existing->id)
        ->and(Guest::count())->toBe(2);
});

// BK-08: two bookings produce different, non-sequential 8-character
// codes from the safe alphabet (no 0/O/1/I) (BR-14).
it('BK-08: generates distinct, non-sequential booking codes', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();
    $roomA = sellableRoom($property);
    $roomB = sellableRoom($property);
    $guest = Guest::factory()->create();
    $source = BookingSource::factory()->create();
    $staff = User::factory()->create();

    $b1 = bookingService()->createBooking(
        $property, $guest, $source, PaymentOption::PayOnArrival, 2, '2026-07-10', '2026-07-12', [$roomA], $staff,
    );
    $b2 = bookingService()->createBooking(
        $property, $guest, $source, PaymentOption::PayOnArrival, 2, '2026-07-20', '2026-07-22', [$roomB], $staff,
    );

    expect($b1->booking_code)->not->toBe($b2->booking_code)
        ->and(strlen($b1->booking_code))->toBe(8)
        ->and(strlen($b2->booking_code))->toBe(8)
        ->and($b1->booking_code)->toMatch('/^[2-9A-HJ-NP-Z]{8}$/')
        ->and($b2->booking_code)->toMatch('/^[2-9A-HJ-NP-Z]{8}$/')
        // No confusable characters 0, O, 1 or I anywhere in either code.
        ->and(preg_match('/[01OI]/', $b1->booking_code.$b2->booking_code))->toBe(0);
});

// T-010 Definition of Done: when a race slips past the row lock, the
// partial unique index (layer 3) rejects the insert, and the service
// must turn that into a clear availability message instead of leaking a
// driver error.
//
// A single-threaded test can never actually race the row lock, so layer
// 1/2 (assertValidStay) is deliberately stubbed out here to reach layer
// 3 in isolation. Layers 1 and 2 are covered by BK-03, BK-04 and the AV
// suite; this test only proves the 23505 translation and the rollback.
it('BK-05 (service): turns a unique index violation into a clear availability message', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();
    $room = sellableRoom($property);
    $guest = Guest::factory()->create();
    $source = BookingSource::factory()->create();
    $staff = User::factory()->create();

    // An active night already occupies this room on the first night of
    // the stay we are about to request.
    Booking::factory()->confirmed()->createWithNights($room, 300000, [
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-12',
    ]);

    // Stub out the availability recheck so the insert reaches the
    // unique index and raises SQLSTATE 23505.
    $this->app->bind(AvailabilityService::class, fn (): AvailabilityService => new class extends AvailabilityService
    {
        public function assertValidStay(
            Property $property,
            CarbonInterface|string $checkIn,
            CarbonInterface|string $checkOut,
            int $guestCount,
            iterable $rooms,
            ?User $user = null,
            ?string $reason = null,
            ?int $excludeBookingId = null,
        ): void {
            // Intentionally empty: layer 1 is under test elsewhere.
        }
    });

    try {
        bookingService()->createBooking(
            $property,
            $guest,
            $source,
            PaymentOption::PayOnArrival,
            2,
            '2026-07-10',
            '2026-07-12',
            [$room],
            $staff,
        );

        $this->fail('Expected an AvailabilityException for the unique index violation.');
    } catch (AvailabilityException $e) {
        expect($e->getMessage())
            ->toBe('One of the rooms was just booked for those dates; please re-check availability.')
            ->and($e->getPrevious())->toBeInstanceOf(QueryException::class);
    }

    // The rejected booking was rolled back: only the first booking and
    // its two nights survive, nothing was half-created.
    expect(Booking::count())->toBe(1)
        ->and(BookingNight::where('is_active', true)->count())->toBe(2);
});

// PR-08 / BR-11.7: booking creation copies the quoted price and rate_id
// into booking_nights, so changing the tariff afterwards never moves the
// price or the total of a booking that already exists.
it('PR-08: locks the quoted price into booking_nights so a later rate change never touches the booking', function () {
    freezeNow('2026-06-15 10:00:00');

    $property = Property::factory()->create();
    $room = sellableRoom($property, 300000);
    $guest = Guest::factory()->create();
    $source = BookingSource::factory()->create();
    $staff = User::factory()->create();

    $booking = bookingService()->createBooking(
        $property,
        $guest,
        $source,
        PaymentOption::PayOnArrival,
        2,
        '2026-07-10',
        '2026-07-12',
        [$room],
        $staff,
    );

    $rate = $room->roomType->rates()->where('is_default', true)->first();

    // Every night records the rate that won for it (BR-11.7).
    expect($booking->bookingNights()->pluck('rate_id')->unique()->values()->all())
        ->toBe([$rate->id]);

    // The tariff doubles after the booking exists.
    $rate->update(['price' => 600000]);

    // The locked night prices and the booking total do not move.
    expect($booking->bookingNights()->pluck('price')->unique()->values()->all())->toBe([300000])
        ->and((int) $booking->bookingNights()->sum('price'))->toBe(600000)
        ->and($booking->fresh()->total_amount)->toBe(600000);
});
