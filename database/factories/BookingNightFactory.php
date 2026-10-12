<?php

namespace Database\Factories;

use App\Models\BookingItem;
use App\Models\BookingNight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingNight>
 */
class BookingNightFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * room_id is resolved from the parent booking item (the closure
     * receives the already-resolved booking_item_id), because the
     * composite foreign key booking_nights(booking_item_id, room_id)
     * requires both to match and the database rejects any deviation.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_item_id' => BookingItem::factory(),
            'room_id' => fn (array $attributes) => BookingItem::query()
                ->find($attributes['booking_item_id'])->room_id,
            'rate_id' => null,
            'stay_date' => '2026-11-10',
            'price' => 300000,
            'is_active' => true,
        ];
    }
}
