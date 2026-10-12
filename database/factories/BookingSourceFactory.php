<?php

namespace Database\Factories;

use App\Models\BookingSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingSource>
 */
class BookingSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Names come from the ERD examples (name is unique).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Website',
                'WhatsApp',
                'OTA',
                'Walk-in',
                'Telepon',
            ]),
        ];
    }
}
