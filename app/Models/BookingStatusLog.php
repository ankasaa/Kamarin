<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\BookingStatusLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property int|null $changed_by_user_id
 * @property BookingStatus|null $old_status
 * @property BookingStatus $new_status
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'booking_id',
    'changed_by_user_id',
    'old_status',
    'new_status',
    'note',
])]
class BookingStatusLog extends Model
{
    /** @use HasFactory<BookingStatusLogFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * old_status is null on the first log entry for a booking.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_status' => BookingStatus::class,
            'new_status' => BookingStatus::class,
        ];
    }

    /** @return BelongsTo<Booking> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
