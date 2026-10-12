<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\PaymentOption;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingNight;
use App\Models\BookingSource;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Support\BookingCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The default is a pending direct booking for a two-night stay;
     * check_out_date is always after check_in_date to satisfy the
     * database CHECK bookings_check_out_after_checkin. The booking code
     * comes from App\Support\BookingCode, so test data obeys BR-14 the
     * same way real bookings do.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'guest_id' => Guest::factory(),
            'created_by_user_id' => User::factory(),
            'booking_source_id' => BookingSource::factory(),
            'booking_code' => BookingCode::generate(),
            'status' => BookingStatus::Pending,
            'payment_option' => PaymentOption::BankTransfer,
            'guest_count' => 2,
            'check_in_date' => '2026-11-10',
            'check_out_date' => '2026-11-12',
            'total_amount' => 700000,
        ];
    }

    /** One state per BookingStatus value (AGENTS.md rule 2). */
    public function pending(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::Pending]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::Confirmed]);
    }

    public function checkedIn(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::CheckedIn]);
    }

    public function checkedOut(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::CheckedOut]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::Cancelled]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::Expired]);
    }

    public function noShow(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::NoShow]);
    }

    /**
     * Create a booking together with one booking_item and its
     * booking_nights for the given room, kept consistent by
     * construction:
     *
     * - every night's room_id equals the item's room_id (matches the
     *   composite foreign key),
     * - the number of nights equals check_in_date to check_out_date
     *   (the check-out day is not a night),
     * - is_active follows BookingStatus::holdsNights(),
     * - item subtotal and booking total_amount equal nights x price.
     */
    public function createWithNights(Room $room, int $pricePerNight = 300000, array $attributes = []): Booking
    {
        /** @var Booking $booking */
        $booking = $this->create($attributes);

        $nightCount = (int) $booking->check_in_date->diffInDays($booking->check_out_date);

        /** @var BookingItem $item */
        $item = BookingItem::factory()->create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'subtotal' => $nightCount * $pricePerNight,
        ]);

        $isActive = $booking->status->holdsNights();

        for ($i = 0; $i < $nightCount; $i++) {
            BookingNight::factory()->create([
                'booking_item_id' => $item->id,
                'room_id' => $room->id,
                'stay_date' => $booking->check_in_date->copy()->addDays($i),
                'price' => $pricePerNight,
                'is_active' => $isActive,
            ]);
        }

        $booking->update(['total_amount' => $item->subtotal]);

        return $booking->refresh();
    }
}
