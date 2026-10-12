<?php

namespace App\Models;

use App\Enums\RoomStatus;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $property_id
 * @property int $room_type_id
 * @property string $room_number
 * @property int|null $floor
 * @property RoomStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'property_id',
    'room_type_id',
    'room_number',
    'floor',
    'status',
])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'floor' => 'integer',
        ];
    }

    /** @return BelongsTo<Property> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<RoomType> */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /** @return HasOne<HousekeepingStatus> */
    public function housekeepingStatus(): HasOne
    {
        return $this->hasOne(HousekeepingStatus::class);
    }

    /** @return HasMany<RoomBlock> */
    public function roomBlocks(): HasMany
    {
        return $this->hasMany(RoomBlock::class);
    }

    /** @return HasMany<BookingItem> */
    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /** @return HasMany<BookingNight> */
    public function bookingNights(): HasMany
    {
        return $this->hasMany(BookingNight::class);
    }
}
