<?php

namespace Database\Factories;

use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * id_number stays empty in seeds (AGENTS.md rule 12: no real
     * personal data), so the factory leaves it null as well.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => null,
            'phone' => null,
            'country' => null,
            'id_type' => null,
            'id_number' => null,
        ];
    }
}
