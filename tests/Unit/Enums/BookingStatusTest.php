<?php

use App\Enums\BookingStatus;
use App\Enums\HousekeepingStatus as HousekeepingStatusEnum;
use App\Enums\PaymentMethod;
use App\Enums\PaymentOption;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RoomStatus;

// Values must stay identical to AGENTS.md rule 2 (which mirrors the
// ERD value lists). Any drift fails here before it reaches the
// database or a service.

it('exposes exactly the booking status values from rule 2', function () {
    $values = array_map(fn (BookingStatus $case) => $case->value, BookingStatus::cases());

    expect($values)->toBe([
        'pending',
        'confirmed',
        'checked_in',
        'checked_out',
        'cancelled',
        'expired',
        'no_show',
    ]);
});

it('exposes exactly the payment option, type, status and method values from rule 2', function () {
    $options = array_map(fn (PaymentOption $case) => $case->value, PaymentOption::cases());
    $types = array_map(fn (PaymentType $case) => $case->value, PaymentType::cases());
    $statuses = array_map(fn (PaymentStatus $case) => $case->value, PaymentStatus::cases());
    $methods = array_map(fn (PaymentMethod $case) => $case->value, PaymentMethod::cases());

    expect($options)->toBe(['bank_transfer', 'pay_on_arrival', 'via_ota'])
        ->and($types)->toBe(['deposit', 'settlement', 'refund'])
        ->and($statuses)->toBe(['pending', 'verified', 'failed', 'refunded'])
        ->and($methods)->toBe(['bank_transfer', 'cash', 'card_or_qris', 'ota']);
});

it('exposes exactly the room status and housekeeping status values from rule 2', function () {
    $rooms = array_map(fn (RoomStatus $case) => $case->value, RoomStatus::cases());
    $housekeeping = array_map(fn (HousekeepingStatusEnum $case) => $case->value, HousekeepingStatusEnum::cases());

    expect($rooms)->toBe(['active', 'maintenance', 'inactive'])
        ->and($housekeeping)->toBe(['dirty', 'cleaning', 'ready']);
});

// holdsNights(): the status keeps booking_nights marked active, so the
// partial unique index blocks other bookings for the same room/date
// (BR-01). countsAsSold(): the status counts as sold room-nights for
// occupancy and revenue (BR-10: pending excluded).

it('holds nights for pending, confirmed, checked_in and checked_out only', function (BookingStatus $status, bool $expected) {
    expect($status->holdsNights())->toBe($expected);
})->with([
    'pending' => [BookingStatus::Pending, true],
    'confirmed' => [BookingStatus::Confirmed, true],
    'checked_in' => [BookingStatus::CheckedIn, true],
    'checked_out' => [BookingStatus::CheckedOut, true],
    'cancelled' => [BookingStatus::Cancelled, false],
    'expired' => [BookingStatus::Expired, false],
    'no_show' => [BookingStatus::NoShow, false],
]);

it('counts as sold for confirmed, checked_in and checked_out only', function (BookingStatus $status, bool $expected) {
    expect($status->countsAsSold())->toBe($expected);
})->with([
    'pending' => [BookingStatus::Pending, false],
    'confirmed' => [BookingStatus::Confirmed, true],
    'checked_in' => [BookingStatus::CheckedIn, true],
    'checked_out' => [BookingStatus::CheckedOut, true],
    'cancelled' => [BookingStatus::Cancelled, false],
    'expired' => [BookingStatus::Expired, false],
    'no_show' => [BookingStatus::NoShow, false],
]);
