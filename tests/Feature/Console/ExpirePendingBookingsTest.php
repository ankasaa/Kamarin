<?php

use App\Console\Commands\ExpirePendingBookings;
use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingSource;
use App\Models\Property;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

// Test cases EX-01 .. EX-06 (section 4.7 of docs/rencana_pengujian.md),
// specifically for the artisan command ExpirePendingBookings and its
// schedule registration (T-014, BR-13).

/** Property + room type fixture. */
function consoleRoomType(): RoomType
{
    return RoomType::factory()->create([
        'property_id' => Property::factory()->create()->id,
    ]);
}

/**
 * Helper to build a pending booking with room and nights.
 *
 * @return array{0: Room, 1: Booking}
 */
function consolePendingBooking(RoomType $roomType, array $attributes = []): array
{
    $room = Room::factory()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'status' => RoomStatus::Active,
    ]);

    $source = BookingSource::query()->value('id') ?? BookingSource::factory()->create()->id;

    $booking = Booking::factory()->pending()->createWithNights($room, 300000, array_merge([
        'property_id' => $roomType->property_id,
        'booking_source_id' => $source,
        'created_by_user_id' => null,
        'check_in_date' => '2026-08-01',
        'check_out_date' => '2026-08-03',
        'expires_at' => now()->subMinute(),
    ], $attributes));

    return [$room, $booking];
}

// EX-01 / BR-13.2: the command expires website pending bookings whose
// deadline has passed, releases active nights, and ignores pending bookings
// that have not reached their deadline yet.
it('EX-01: expires past-due website bookings and releases nights while leaving unexpired bookings pending', function () {
    freezeNow('2026-06-15 12:00:00');

    $roomType = consoleRoomType();

    // Past due: expires_at is 12:00:00 or earlier
    [, $pastDue] = consolePendingBooking($roomType, [
        'expires_at' => '2026-06-15 11:59:00',
    ]);

    // Still valid: expires_at is in the future
    [, $futureBooking] = consolePendingBooking($roomType, [
        'expires_at' => '2026-06-15 13:00:00',
    ]);

    $this->artisan('bookings:expire-pending')
        ->expectsOutputToContain('Expired 1 pending booking(s)')
        ->assertSuccessful();

    expect($pastDue->refresh()->status)->toBe(BookingStatus::Expired)
        ->and($pastDue->bookingNights()->where('is_active', true)->count())->toBe(0)
        ->and($futureBooking->refresh()->status)->toBe(BookingStatus::Pending)
        ->and($futureBooking->bookingNights()->where('is_active', true)->count())->toBe(2);

    // EX-06: log status records the system as the actor (changed_by_user_id is null)
    $log = $pastDue->bookingStatusLogs()->sole();
    expect($log->changed_by_user_id)->toBeNull()
        ->and($log->old_status)->toBe(BookingStatus::Pending)
        ->and($log->new_status)->toBe(BookingStatus::Expired);
});

// EX-04 / K5: a booking created by staff has no deadline (expires_at is null),
// so it must never be expired by the scheduler command.
it('EX-04: never expires staff bookings which have no deadline (K5)', function () {
    freezeNow('2026-06-15 12:00:00');

    $roomType = consoleRoomType();
    $staff = User::factory()->create([
        'role_id' => Role::factory()->create(['name' => 'receptionist'])->id,
    ]);

    [, $staffBooking] = consolePendingBooking($roomType, [
        'created_by_user_id' => $staff->id,
        'expires_at' => null,
    ]);

    $this->artisan('bookings:expire-pending')
        ->expectsOutputToContain('Expired 0 pending booking(s)')
        ->assertSuccessful();

    expect($staffBooking->refresh()->status)->toBe(BookingStatus::Pending)
        ->and($staffBooking->bookingNights()->where('is_active', true)->count())->toBe(2)
        ->and($staffBooking->bookingStatusLogs()->count())->toBe(0);
});

// EX-05 / US-29: running the command twice in succession is idempotent;
// the second run does not fail and does not produce duplicate status logs.
it('EX-05: is idempotent when run multiple times in succession', function () {
    freezeNow('2026-06-15 12:00:00');

    $roomType = consoleRoomType();

    [, $booking] = consolePendingBooking($roomType, [
        'expires_at' => '2026-06-15 11:30:00',
    ]);

    // First execution expires the booking
    $this->artisan('bookings:expire-pending')
        ->expectsOutputToContain('Expired 1 pending booking(s)')
        ->assertSuccessful();

    expect($booking->refresh()->status)->toBe(BookingStatus::Expired)
        ->and($booking->bookingStatusLogs()->count())->toBe(1);

    // Second execution finds nothing pending to expire; leaves row unchanged
    $this->artisan('bookings:expire-pending')
        ->expectsOutputToContain('Expired 0 pending booking(s)')
        ->assertSuccessful();

    expect($booking->refresh()->status)->toBe(BookingStatus::Expired)
        ->and($booking->bookingStatusLogs()->count())->toBe(1);
});

// Partial failure scenario: if processing one booking throws an exception,
// the loop must not abort; subsequent bookings must still be processed.
// The run must report both counts and exit non-zero so that cron monitoring
// can see the failure (T-014b).
it('EX-05: reports both counts and exits non-zero on partial failure while still processing the rest (T-014b)', function () {
    freezeNow('2026-06-15 12:00:00');

    $roomType = consoleRoomType();

    // Two bookings that both reached expiry deadline
    [, $firstBooking] = consolePendingBooking($roomType, [
        'expires_at' => '2026-06-15 11:00:00',
    ]);
    [, $secondBooking] = consolePendingBooking($roomType, [
        'expires_at' => '2026-06-15 11:05:00',
    ]);

    // Mock BookingService to throw on the first booking, but call original expire on the second
    $realService = app(BookingService::class);
    $mockService = Mockery::mock($realService)->makePartial();

    $mockService->shouldReceive('expire')
        ->andReturnUsing(function (Booking $booking) use ($realService, $firstBooking) {
            if ($booking->id === $firstBooking->id) {
                throw new BookingException('Simulated database lock failure for first booking.');
            }

            return $realService->expire($booking);
        });

    app()->instance(BookingService::class, $mockService);

    // The command catches the exception, logs it, reports both counts and fails.
    $this->artisan('bookings:expire-pending')
        ->expectsOutputToContain('Expired 1 pending booking(s), 1 failed.')
        ->assertFailed();

    // First booking remains pending because its transaction failed
    expect($firstBooking->refresh()->status)->toBe(BookingStatus::Pending)
        // Second booking successfully expired
        ->and($secondBooking->refresh()->status)->toBe(BookingStatus::Expired)
        ->and($secondBooking->bookingNights()->where('is_active', true)->count())->toBe(0);
});

// Schedule assertion: bookings:expire-pending is scheduled every 5 minutes
// with withoutOverlapping enabled (T-014, T-014b).
//
// The overlap lock must expire after 10 minutes rather than the framework
// default of 1440 minutes (24 hours). A run that dies mid-way would otherwise
// block expiry for a whole day, while the command only runs every 5 minutes.
it('schedules bookings:expire-pending every five minutes with a ten minute overlap lock (T-014b)', function () {
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())->first(function (Event $event) {
        return str_contains($event->command, 'bookings:expire-pending')
            || str_contains($event->command, ExpirePendingBookings::class);
    });

    expect($event)->not->toBeNull('bookings:expire-pending is not registered in the console schedule.')
        ->and($event->expression)->toBe('*/5 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(10);
});
