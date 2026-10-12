<?php

use App\Models\Booking;
use App\Models\BookingNight;

// The classic date bug: a "date" column stored as midnight in the app
// timezone (Asia/Makassar, UTC+8) serializes to the previous day when
// it is rendered as an UTC instant. The explicit 'date:Y-m-d' cast
// keeps the plain calendar date in JSON, proven here against the real
// application timezone.

it('runs in the Asia/Makassar application timezone', function () {
    expect(config('app.timezone'))->toBe('Asia/Makassar')
        ->and(now()->timezoneName)->toBe('Asia/Makassar');
});

it('serializes booking_nights.stay_date 2026-10-12 as "2026-10-12" under Asia/Makassar', function () {
    $night = BookingNight::factory()->create(['stay_date' => '2026-10-12']);

    $json = $night->toJson();

    expect($json)
        ->toContain('"stay_date":"2026-10-12"')
        ->not->toContain('2026-10-11')
        ->and($night->toArray()['stay_date'])->toBe('2026-10-12');
});

it('serializes booking check-in and check-out dates as plain Y-m-d', function () {
    $booking = Booking::factory()->create([
        'check_in_date' => '2026-10-12',
        'check_out_date' => '2026-10-13',
    ]);

    $json = $booking->toJson();

    expect($json)
        ->toContain('"check_in_date":"2026-10-12"')
        ->toContain('"check_out_date":"2026-10-13"')
        ->not->toContain('2026-10-11')
        ->and($booking->toArray()['check_in_date'])->toBe('2026-10-12')
        ->and($booking->toArray()['check_out_date'])->toBe('2026-10-13');
});
