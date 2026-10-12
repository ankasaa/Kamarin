<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The room type is created under the same property as the room
     * (the closure receives the already-resolved property_id), so a
     * factory-built room never points at a room type of another
     * property.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'room_type_id' => fn (array $attributes) => RoomType::factory()->create([
                'property_id' => $attributes['property_id'],
            ])->id,
            'room_number' => (string) fake()->unique()->numberBetween(1, 999),
            'floor' => null,
            'status' => 'active',
        ];
    }
}
