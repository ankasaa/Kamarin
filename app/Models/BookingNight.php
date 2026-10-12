<?php

namespace App\Models;

use Database\Factories\BookingNightFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per room per night. The partial unique index
 * booking_nights_room_stay_active_unique guarantees that at most one
 * active night exists per (room_id, stay_date) - the database-level
 * anti double booking guarantee (BR-01).
 *
 * @property int $id
 * @property int $booking_item_id
 * @property int $room_id
 * @property int|null $rate_id
 * @property Carbon $stay_date
 * @property int $price
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['booking_item_id', 'room_id', 'rate_id', 'stay_date', 'price', 'is_active'])]
class BookingNight extends Model
{
    /** @use HasFactory<BookingNightFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stay_date' => 'date:Y-m-d',
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<BookingItem> */
    public function bookingItem(): BelongsTo
    {
        return $this->belongsTo(BookingItem::class);
    }

    /** @return BelongsTo<Room> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsTo<Rate> */
    public function rate(): BelongsTo
    {
        return $this->belongsTo(Rate::class);
    }
}
