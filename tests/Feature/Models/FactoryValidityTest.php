<?php

use App\Enums\BookingStatus;
use App\Enums\HousekeepingStatus as HousekeepingStatusEnum;
use App\Enums\PaymentMethod;
use App\Enums\PaymentOption;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\BookingItem;
use App\Models\BookingNight;
use App\Models\Guest;
use App\Models\Rate;
use App\Models\Room;
use Database\Factories\AmenityFactory;
use Database\Factories\BookingChangeLogFactory;
use Database\Factories\BookingChargeFactory;
use Database\Factories\BookingFactory;
use Database\Factories\BookingItemFactory;
use Database\Factories\BookingNightFactory;
use Database\Factories\BookingSourceFactory;
use Database\Factories\BookingStatusLogFactory;
use Database\Factories\GuestFactory;
use Database\Factories\HousekeepingStatusFactory;
use Database\Factories\PaymentFactory;
use Database\Factories\PropertyFactory;
use Database\Factories\RateFactory;
use Database\Factories\RoleFactory;
use Database\Factories\RoomBlockFactory;
use Database\Factories\RoomFactory;
use Database\Factories\RoomTypeAmenityFactory;
use Database\Factories\RoomTypeFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;

// Every factory must produce a row PostgreSQL accepts: all foreign
// keys resolve and every CHECK holds. A rejected insert fails the
// test, so a drifted factory cannot silently poison later tests.

it('creates a valid row from every factory (passes all FKs and CHECKs)', function (string $factory) {
    $model = $factory::new()->create();

    expect($model->exists)->toBeTrue();
})->with([
    'roles' => RoleFactory::class,
    'users' => UserFactory::class,
    'properties' => PropertyFactory::class,
    'room_types' => RoomTypeFactory::class,
    'rooms' => RoomFactory::class,
    'amenities' => AmenityFactory::class,
    'room_type_amenities' => RoomTypeAmenityFactory::class,
    'housekeeping_statuses' => HousekeepingStatusFactory::class,
    'guests' => GuestFactory::class,
    'booking_sources' => BookingSourceFactory::class,
    'rates' => RateFactory::class,
    'room_blocks' => RoomBlockFactory::class,
    'bookings' => BookingFactory::class,
    'booking_items' => BookingItemFactory::class,
    'booking_nights' => BookingNightFactory::class,
    'booking_charges' => BookingChargeFactory::class,
    'payments' => PaymentFactory::class,
    'booking_status_logs' => BookingStatusLogFactory::class,
    'booking_change_logs' => BookingChangeLogFactory::class,
]);

it('provides one factory state per booking status', function (string $state, BookingStatus $expected) {
    $booking = Booking::factory()->{$state}()->create();

    expect($booking->status)->toBe($expected);
})->with([
    'pending' => ['pending', BookingStatus::Pending],
    'confirmed' => ['confirmed', BookingStatus::Confirmed],
    'checkedIn' => ['checkedIn', BookingStatus::CheckedIn],
    'checkedOut' => ['checkedOut', BookingStatus::CheckedOut],
    'cancelled' => ['cancelled', BookingStatus::Cancelled],
    'expired' => ['expired', BookingStatus::Expired],
    'noShow' => ['noShow', BookingStatus::NoShow],
]);

it('creates consistent items and nights via createWithNights (pending holds nights)', function () {
    $room = Room::factory()->create();

    $booking = Booking::factory()->pending()->createWithNights($room, 250000, [
        'check_in_date' => '2026-11-10',
        'check_out_date' => '2026-11-13',
    ]);

    $items = $booking->bookingItems;

    expect($items)->toHaveCount(1);

    $item = $items->first();
    $nights = $item->bookingNights;

    expect($item->room_id)->toBe($room->id)
        ->and($item->subtotal)->toBe(750000)
        ->and($booking->total_amount)->toBe(750000)
        ->and($nights)->toHaveCount(3)
        ->and($nights->pluck('room_id')->unique()->all())->toBe([$room->id])
        ->and($nights->pluck('price')->unique()->all())->toBe([250000])
        ->and($nights->every(fn (BookingNight $night) => $night->is_active === true))->toBeTrue()
        ->and($nights->sortBy('stay_date')->pluck('stay_date')->map->format('Y-m-d')->all())
        ->toBe(['2026-11-10', '2026-11-11', '2026-11-12']);
});

it('marks nights inactive when the booking status does not hold nights', function (string $state) {
    $room = Room::factory()->create();

    $booking = Booking::factory()->{$state}()->createWithNights($room);

    $nights = $booking->bookingItems()->sole()->bookingNights;

    expect($nights)->not->toBeEmpty()
        ->and($nights->every(fn (BookingNight $night) => $night->is_active === false))->toBeTrue();
})->with([
    'cancelled' => ['cancelled'],
    'expired' => ['expired'],
    'no_show' => ['noShow'],
]);

it('casts enum, money, date, time and json columns to the documented PHP types', function () {
    $booking = Booking::factory()->create(['total_amount' => 750000]);

    expect($booking->status)->toBeInstanceOf(BookingStatus::class)
        ->and($booking->payment_option)->toBeInstanceOf(PaymentOption::class)
        ->and($booking->total_amount)->toBeInt()->toBe(750000)
        ->and($booking->check_in_date->format('Y-m-d'))->toBe('2026-11-10')
        ->and($booking->check_out_date->format('Y-m-d'))->toBe('2026-11-12');

    $payment = PaymentFactory::new()->create();

    expect($payment->amount)->toBeInt()
        ->and($payment->type)->toBeInstanceOf(PaymentType::class)
        ->and($payment->method)->toBeInstanceOf(PaymentMethod::class)
        ->and($payment->status)->toBeInstanceOf(PaymentStatus::class)
        ->and($payment->payment_date->format('Y-m-d'))->toBe('2026-11-01');

    $rate = Rate::factory()->create([
        'days_of_week' => [5, 6],
        'price' => 450000,
    ]);

    expect($rate->price)->toBeInt()->toBe(450000)
        ->and($rate->days_of_week)->toBe([5, 6]);

    $room = Room::factory()->create();
    expect($room->status)->toBeInstanceOf(RoomStatus::class);

    $housekeeping = HousekeepingStatusFactory::new()->create();
    expect($housekeeping->status)->toBeInstanceOf(HousekeepingStatusEnum::class);

    $property = PropertyFactory::new()->create();
    expect($property->check_in_time)->toBe('14:00:00')
        ->and($property->check_out_time)->toBe('12:00:00')
        ->and($property->no_show_cutoff_time)->toBe('22:00:00')
        ->and($property->deposit_percent)->toBeInt()->toBe(50);

    $night = BookingNight::factory()->create(['price' => 300000]);
    expect($night->price)->toBeInt();

    $item = BookingItem::factory()->create(['subtotal' => 600000]);
    expect($item->subtotal)->toBeInt();

    // Discounts are negative amounts by design and must stay allowed.
    $charge = BookingCharge::factory()->create(['amount' => -15000]);
    expect($charge->amount)->toBeInt()->toBe(-15000);
});

it('soft deletes bookings and guests only', function () {
    $booking = Booking::factory()->create();
    $guest = Guest::factory()->create();

    $booking->delete();
    $guest->delete();

    expect($booking->refresh()->trashed())->toBeTrue()
        ->and($guest->refresh()->trashed())->toBeTrue()
        ->and(Booking::find($booking->id))->toBeNull()
        ->and(Booking::withTrashed()->find($booking->id))->not->toBeNull()
        ->and(Guest::find($guest->id))->toBeNull()
        ->and(Guest::withTrashed()->find($guest->id))->not->toBeNull();

    // Other models have no SoftDeletes trait: deleting one removes the row.
    $rate = Rate::factory()->create();
    $rate->delete();
    expect(Rate::find($rate->id))->toBeNull();
});

// BR-14: test data must carry a real booking code, so BookingFactory
// goes through the same App\Support\BookingCode generator as
// BookingService instead of its own 'BK-' pattern.
it('generates a BR-14 booking code from the factory', function () {
    $codes = collect([
        Booking::factory()->create()->booking_code,
        Booking::factory()->create()->booking_code,
        Booking::factory()->create()->booking_code,
    ]);

    foreach ($codes as $code) {
        // 8 characters from the safe alphabet: no 0/O/1/I, never a
        // sequence, and no separator such as the old 'BK-' prefix.
        expect($code)->toMatch('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{8}$/');
    }

    expect($codes->unique()->all())->toHaveCount(3)
        ->and(Booking::whereIn('booking_code', $codes)->count())->toBe(3);
});

// Guard: the frozen-clock helper from T-003 stays compatible with the
// date casts introduced here.
it('keeps frozen clock compatible with date casts', function () {
    freezeNow('2026-06-15 10:00:00');

    $night = BookingNight::factory()->create([
        'stay_date' => Carbon::today()->toDateString(),
    ]);

    expect($night->stay_date->format('Y-m-d'))->toBe('2026-06-15');
});
