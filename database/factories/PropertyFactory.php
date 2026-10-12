<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Policy values mirror the ERD defaults (check-in 14:00, check-out
     * 12:00, DP 50%, pending expiry 3 hours, free cancellation 2 days,
     * no-show cutoff 22:00) so factories never invent policies.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Guesthouse '.fake()->unique()->numberBetween(1, 9999),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'phone' => null,
            'check_in_time' => '14:00:00',
            'check_out_time' => '12:00:00',
            'deposit_percent' => 50,
            'pending_expiry_hours' => 3,
            'free_cancel_days' => 2,
            'no_show_cutoff_time' => '22:00:00',
        ];
    }
}
