<?php

namespace App\Models;

use Database\Factories\BookingChangeLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property int|null $changed_by_user_id
 * @property string $change_type
 * @property string $old_value
 * @property string $new_value
 * @property string $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'booking_id',
    'changed_by_user_id',
    'change_type',
    'old_value',
    'new_value',
    'reason',
])]
class BookingChangeLog extends Model
{
    /** @use HasFactory<BookingChangeLogFactory> */
    use HasFactory;

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
