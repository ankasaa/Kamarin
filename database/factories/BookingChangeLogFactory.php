<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingChangeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingChangeLog>
 */
class BookingChangeLogFactory extends Factory
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
            'change_type' => 'dates_changed',
            'old_value' => '2026-11-10',
            'new_value' => '2026-11-11',
            'reason' => ucfirst(fake()->words(4, true)),
        ];
    }
}
