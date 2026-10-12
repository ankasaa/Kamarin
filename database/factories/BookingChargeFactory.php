<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingCharge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingCharge>
 */
class BookingChargeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'description' => ucfirst(fake()->words(3, true)),
            'amount' => 50000,
        ];
    }
}
