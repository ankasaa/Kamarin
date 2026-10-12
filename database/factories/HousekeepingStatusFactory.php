<?php

namespace Database\Factories;

use App\Models\HousekeepingStatus;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HousekeepingStatus>
 */
class HousekeepingStatusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'updated_by_user_id' => null,
            'status' => 'dirty',
            'notes' => null,
        ];
    }
}
