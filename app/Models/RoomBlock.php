<?php

namespace App\Models;

use Database\Factories\RoomBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $room_id
 * @property int|null $created_by_user_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['room_id', 'created_by_user_id', 'start_date', 'end_date', 'reason'])]
class RoomBlock extends Model
{
    /** @use HasFactory<RoomBlockFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * end_date > start_date is enforced by the database CHECK
     * room_blocks_end_after_start.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<Room> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsTo<User> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
