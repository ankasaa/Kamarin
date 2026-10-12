<?php

namespace App\Models;

use Database\Factories\BookingItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property int $room_id
 * @property int $subtotal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['booking_id', 'room_id', 'subtotal'])]
class BookingItem extends Model
{
    /** @use HasFactory<BookingItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * subtotal is numeric(12,0): the sum of the prices of every
     * booking_night belonging to this item.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
        ];
    }

    /** @return BelongsTo<Booking> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Room> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return HasMany<BookingNight> */
    public function bookingNights(): HasMany
    {
        return $this->hasMany(BookingNight::class);
    }
}
