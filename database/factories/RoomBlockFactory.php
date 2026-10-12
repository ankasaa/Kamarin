<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomBlock>
 */
class RoomBlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * end_date is always after start_date to satisfy the database CHECK
     * room_blocks_end_after_start.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'created_by_user_id' => User::factory(),
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-03',
            'reason' => ucfirst(fake()->words(3, true)),
        ];
    }
}
