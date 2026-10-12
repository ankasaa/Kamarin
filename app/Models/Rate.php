<?php

namespace App\Models;

use Database\Factories\RateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $property_id
 * @property int $room_type_id
 * @property string $name
 * @property int $price
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property array|null $days_of_week
 * @property int $priority
 * @property bool $is_default
 * @property int $minimum_nights
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'property_id',
    'room_type_id',
    'name',
    'price',
    'start_date',
    'end_date',
    'days_of_week',
    'priority',
    'is_default',
    'minimum_nights',
])]
class Rate extends Model
{
    /** @use HasFactory<RateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * price is numeric(12,0) in PostgreSQL (whole rupiah, never float)
     * and comes back as a string, so it is cast to int. days_of_week is
     * jsonb holding ISO weekdays 1-7 (e.g. [5,6]).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'days_of_week' => 'array',
            'priority' => 'integer',
            'is_default' => 'boolean',
            'minimum_nights' => 'integer',
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

    /** @return HasMany<BookingNight> */
    public function bookingNights(): HasMany
    {
        return $this->hasMany(BookingNight::class);
    }
}
