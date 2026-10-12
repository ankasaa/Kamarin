<?php

namespace Database\Factories;

use App\Models\Amenity;
use App\Models\RoomType;
use App\Models\RoomTypeAmenity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomTypeAmenity>
 */
class RoomTypeAmenityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_type_id' => RoomType::factory(),
            'amenity_id' => Amenity::factory(),
        ];
    }
}
