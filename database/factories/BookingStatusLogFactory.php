<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingStatusLog>
 */
class BookingStatusLogFactory extends Factory
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
            'changed_by_user_id' => User::factory(),
            'old_status' => null,
            'new_status' => 'pending',
            'note' => null,
        ];
    }
}
