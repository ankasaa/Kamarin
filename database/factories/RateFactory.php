<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Rate;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rate>
 */
class RateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The rate is created under the same property as its room type
     * (the closure receives the already-resolved property_id).
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
            'name' => 'Standard Rate',
            'price' => 350000,
            'start_date' => null,
            'end_date' => null,
            'days_of_week' => null,
            'priority' => 10,
            'is_default' => false,
            'minimum_nights' => 1,
        ];
    }
}
