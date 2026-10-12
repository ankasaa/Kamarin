<?php

use App\Exceptions\PricingException;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingNight;
use App\Models\Rate;
use App\Models\Role;
use App\Models\RoomType;
use App\Models\User;
use App\Services\PricingService;

// Test cases PR-01 .. PR-11 from docs/rencana_pengujian.md section 4.2,
// covering BR-11 (rate selection per night) and BR-12 (totals).
// Confirmed weekday mapping used below (ISO-8601, format('N')):
//   2026-07-09 = Thursday (4), 07-10 = Friday (5), 07-11 = Saturday (6),
//   07-12 = Sunday (7).

function pricing(): PricingService
{
    return app(PricingService::class);
}

function expectPricingError(callable $action, string $messageNeedle): void
{
    $thrown = null;

    try {
        $action();
    } catch (PricingException $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(PricingException::class)
        ->and($thrown?->getMessage())->toContain($messageNeedle);
}

function userWithRole(string $roleName): User
{
    return User::factory()->create([
        'role_id' => Role::factory()->create(['name' => $roleName])->id,
    ]);
}

// PR-01: with only a default rate, every night of the stay uses the
// default price.
it('PR-01: prices every night from the default rate', function () {
    $roomType = RoomType::factory()->create();
    Rate::factory()->defaultRate()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'price' => 300000,
    ]);

    $quote = pricing()->quoteStay($roomType, '2026-07-09', '2026-07-12');

    expect($quote)->toHaveCount(3)
        ->and(array_column($quote, 'price'))->toBe([300000, 300000, 300000])
        ->and(array_column($quote, 'stay_date'))->toBe(['2026-07-09', '2026-07-10', '2026-07-11']);
});

// PR-02: a weekend rate with days_of_week [5,6] prices Friday and
// Saturday nights (ISO), leaving other nights on the default rate.
it('PR-02: applies the weekend rate to ISO Friday and Saturday nights', function () {
    $roomType = RoomType::factory()->create();

    Rate::factory()->defaultRate()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'price' => 300000,
    ]);
    Rate::factory()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'name' => 'Weekend',
        'price' => 400000,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'days_of_week' => [5, 6],
        'priority' => 10,
        'is_default' => false,
    ]);

    // Stay Thu 09 -> Sun 12: nights Thu(4), Fri(5), Sat(6).
    $quote = pricing()->quoteStay($roomType, '2026-07-09', '2026-07-12');

    expect(array_column($quote, 'price'))->toBe([300000, 400000, 400000]);
});

// PR-03: on a weekend night that falls inside a high-season range, the
// highest-priority rate wins (high season 20 > weekend 10).
it('PR-03: highest priority wins when weekend overlaps high season', function () {
    $roomType = RoomType::factory()->create();

    Rate::factory()->defaultRate()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'price' => 300000,
    ]);
    Rate::factory()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'name' => 'Weekend',
        'price' => 400000,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'days_of_week' => [5, 6],
        'priority' => 10,
        'is_default' => false,
    ]);
    Rate::factory()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'name' => 'High Season',
        'price' => 500000,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'days_of_week' => null,
        'priority' => 20,
        'is_default' => false,
    ]);

    // Friday 10 July is both a weekend night and inside high season.
    $price = pricing()->pricePerNight($roomType, '2026-07-10');

    expect($price)->toBe(500000);
});

// PR-04: a stay crossing a season boundary gets a mixed per-night price
// whose total equals the sum of the nights (BR-11.1, BR-12).
it('PR-04: mixes prices per night across a season boundary', function () {
    $roomType = RoomType::factory()->create();

    Rate::factory()->defaultRate()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'price' => 300000,
    ]);
    Rate::factory()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'name' => 'Season A',
        'price' => 400000,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-10',
        'priority' => 20,
        'is_default' => false,
    ]);
    Rate::factory()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'name' => 'Season B',
        'price' => 500000,
        'start_date' => '2026-07-11',
        'end_date' => '2026-07-31',
        'priority' => 20,
        'is_default' => false,
    ]);

    // Stay Fri 10 -> Mon 13: nights 10 (Season A), 11 and 12 (Season B).
    $quote = pricing()->quoteStay($roomType, '2026-07-10', '2026-07-13');

    expect(array_column($quote, 'price'))->toBe([400000, 500000, 500000])
        ->and(array_sum(array_column($quote, 'price')))->toBe(1400000);
});

// PR-05: saving two overlapping rates with the same priority is
// rejected (BR-11.3, K2).
it('PR-05: rejects overlapping rates that share a priority', function () {
    $roomType = RoomType::factory()->create();

    Rate::factory()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'name' => 'First',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'priority' => 10,
        'is_default' => false,
    ]);

    $overlapping = Rate::factory()->make([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'name' => 'Second',
        'start_date' => '2026-07-15',
        'end_date' => '2026-08-15',
        'priority' => 10,
        'is_default' => false,
    ]);

    expectPricingError(
        fn () => pricing()->assertNoConflictingRate($overlapping),
        'priority',
    );

    // A non-overlapping or different-priority rate is accepted.
    $fine = Rate::factory()->make([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'name' => 'Later Season',
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
        'priority' => 20,
        'is_default' => false,
    ]);

    pricing()->assertNoConflictingRate($fine);

    expect(true)->toBeTrue();
});

// PR-06: an active room type without a default rate is not sellable
// (BR-11.4).
it('PR-06: rejects an active room type that has no default rate', function () {
    $roomType = RoomType::factory()->create(['is_active' => true]);

    // Only a seasonal rate, no default.
    Rate::factory()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'name' => 'Season Only',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'priority' => 10,
        'is_default' => false,
    ]);

    expectPricingError(
        fn () => pricing()->assertHasDefaultRate($roomType),
        'default',
    );

    // Adding the default makes it sellable.
    Rate::factory()->defaultRate()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
    ]);

    pricing()->assertHasDefaultRate($roomType);

    expect(true)->toBeTrue();
});

// BR-07.6 / AV-14: PR-06 constrains an *active* room type only. An
// inactive type is not sellable at all - AvailabilityService refuses its
// rooms - so it does not have to own a default rate yet.
it('PR-06 (inactive): skips the default rate check for an inactive room type', function () {
    $inactive = RoomType::factory()->create(['is_active' => false]);

    pricing()->assertHasDefaultRate($inactive);

    expect(true)->toBeTrue();

    // The rule still bites for an active type with no default rate.
    $active = RoomType::factory()->create(['is_active' => true]);

    expectPricingError(
        fn () => pricing()->assertHasDefaultRate($active),
        'default',
    );
});

// PR-07: a stay shorter than the minimum_nights of the rate on the
// check-in night is rejected; owner/manager may override with a reason
// (BR-11.8, K1).
it('PR-07: enforces minimum nights from the check-in night rate with override', function () {
    $roomType = RoomType::factory()->create();

    Rate::factory()->defaultRate()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'minimum_nights' => 2,
    ]);

    expect(pricing()->minimumNightsForCheckIn($roomType, '2026-07-10'))->toBe(2);

    // One-night stay is below the minimum.
    expectPricingError(
        fn () => pricing()->assertMinimumNightsSatisfied($roomType, '2026-07-10', 1),
        'minimum',
    );

    // Receptionist cannot override, even with a reason.
    expectPricingError(
        fn () => pricing()->assertMinimumNightsSatisfied($roomType, '2026-07-10', 1, userWithRole('receptionist'), 'Walk-in'),
        'minimum',
    );

    // Manager and owner may override with a reason.
    pricing()->assertMinimumNightsSatisfied($roomType, '2026-07-10', 1, userWithRole('manager'), 'Late arrival, single night');
    pricing()->assertMinimumNightsSatisfied($roomType, '2026-07-10', 1, userWithRole('owner'), 'Correction');

    // A stay meeting the minimum needs no override.
    pricing()->assertMinimumNightsSatisfied($roomType, '2026-07-10', 2);

    expect(true)->toBeTrue();
});

// PR-08: changing a rate after a booking is created does not change the
// locked price on that booking's nights (BR-11.7).
it('PR-08: keeps the locked night price after the rate changes', function () {
    $roomType = RoomType::factory()->create();
    $rate = Rate::factory()->defaultRate()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'price' => 300000,
    ]);

    $booking = Booking::factory()->create([
        'property_id' => $roomType->property_id,
        'total_amount' => 0,
    ]);
    $item = BookingItem::factory()->create([
        'booking_id' => $booking->id,
        'subtotal' => 300000,
    ]);

    // Lock the quoted price into a night, as booking creation will do.
    $quote = pricing()->quoteStay($roomType, '2026-07-10', '2026-07-11');
    $night = BookingNight::factory()->create([
        'booking_item_id' => $item->id,
        'rate_id' => $rate->id,
        'stay_date' => $quote[0]['stay_date'],
        'price' => $quote[0]['price'],
        'is_active' => true,
    ]);

    // The rate changes after the fact.
    $rate->update(['price' => 550000]);

    expect($night->refresh()->price)->toBe(300000)
        ->and($night->price)->not->toBe(550000);
});

// PR-09: every money value is a whole-integer rupiah amount, never a
// float.
it('PR-09: returns whole-integer rupiah for prices and totals', function () {
    $roomType = RoomType::factory()->create();
    Rate::factory()->defaultRate()->create([
        'property_id' => $roomType->property_id,
        'room_type_id' => $roomType->id,
        'price' => 350000,
    ]);

    $price = pricing()->pricePerNight($roomType, '2026-07-10');
    $quote = pricing()->quoteStay($roomType, '2026-07-10', '2026-07-12');

    $booking = Booking::factory()->create(['property_id' => $roomType->property_id]);
    $totals = pricing()->calculateTotals($booking);

    expect($price)->toBeInt()
        ->and(array_column($quote, 'price'))->each->toBeInt()
        ->and($totals['subtotal'])->toBeInt()
        ->and($totals['total'])->toBeInt();
});

// PR-10: subtotal equals the sum of active night prices, and
// total_amount equals subtotal plus the sum of booking charges (BR-12).
it('PR-10: computes subtotal from active nights and total with charges', function () {
    $roomType = RoomType::factory()->create();
    $booking = Booking::factory()->create([
        'property_id' => $roomType->property_id,
        'total_amount' => 0,
    ]);
    $item = BookingItem::factory()->create([
        'booking_id' => $booking->id,
        'subtotal' => 600000,
    ]);

    BookingNight::factory()->create([
        'booking_item_id' => $item->id,
        'stay_date' => '2026-07-10',
        'price' => 300000,
        'is_active' => true,
    ]);
    BookingNight::factory()->create([
        'booking_item_id' => $item->id,
        'stay_date' => '2026-07-11',
        'price' => 300000,
        'is_active' => true,
    ]);
    // An inactive night must not count toward the subtotal.
    BookingNight::factory()->create([
        'booking_item_id' => $item->id,
        'stay_date' => '2026-07-12',
        'price' => 999999,
        'is_active' => false,
    ]);

    $booking->bookingCharges()->create(['description' => 'Airport pickup', 'amount' => 50000]);

    $totals = pricing()->calculateTotals($booking);
    $total = pricing()->recalculateTotal($booking);

    expect($totals['subtotal'])->toBe(600000)
        ->and($totals['charges'])->toBe(50000)
        ->and($totals['total'])->toBe(650000)
        ->and($total)->toBe(650000)
        ->and($booking->refresh()->total_amount)->toBe(650000);
});

// PR-11: extra charges are positive and discounts negative; the total
// follows; only owner/manager may record a discount (BR-12.1, US-19).
it('PR-11: allows positive charges and owner/manager discounts only', function () {
    $roomType = RoomType::factory()->create();
    $booking = Booking::factory()->create([
        'property_id' => $roomType->property_id,
        'total_amount' => 0,
    ]);
    $item = BookingItem::factory()->create([
        'booking_id' => $booking->id,
        'subtotal' => 600000,
    ]);
    BookingNight::factory()->create([
        'booking_item_id' => $item->id,
        'stay_date' => '2026-07-10',
        'price' => 600000,
        'is_active' => true,
    ]);

    $receptionist = userWithRole('receptionist');

    // A receptionist may add a positive extra charge.
    pricing()->addCharge($booking, 'Airport pickup', 50000, $receptionist);
    expect($booking->refresh()->total_amount)->toBe(650000);

    // A receptionist may not record a discount.
    expectPricingError(
        fn () => pricing()->addCharge($booking, 'Diskon', -50000, $receptionist),
        'discount',
    );

    // A manager may, and the total follows the negative amount.
    pricing()->addCharge($booking, 'Diskon', -50000, userWithRole('manager'));
    expect($booking->refresh()->total_amount)->toBe(600000);
});
