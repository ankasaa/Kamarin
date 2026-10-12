<?php

namespace App\Models;

use Database\Factories\RoomTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $property_id
 * @property string $name
 * @property string|null $description
 * @property int $capacity
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'property_id',
    'name',
    'description',
    'capacity',
    'is_active',
])]
class RoomType extends Model
{
    /** @use HasFactory<RoomTypeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Property> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return HasMany<Room> */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /** @return HasMany<RoomTypeAmenity> */
    public function roomTypeAmenities(): HasMany
    {
        return $this->hasMany(RoomTypeAmenity::class);
    }

    /** @return BelongsToMany<Amenity> */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'room_type_amenities');
    }

    /** @return HasMany<Rate> */
    public function rates(): HasMany
    {
        return $this->hasMany(Rate::class);
    }
}
