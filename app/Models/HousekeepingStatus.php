<?php

namespace App\Models;

use App\Enums\HousekeepingStatus as HousekeepingStatusEnum;
use Database\Factories\HousekeepingStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per room (unique room_id): the room's current cleanliness
 * status. There is no history table for cleanliness in the MVP.
 *
 * @property int $id
 * @property int $room_id
 * @property int|null $updated_by_user_id
 * @property HousekeepingStatusEnum $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['room_id', 'updated_by_user_id', 'status', 'notes'])]
class HousekeepingStatus extends Model
{
    /** @use HasFactory<HousekeepingStatusFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => HousekeepingStatusEnum::class,
        ];
    }

    /** @return BelongsTo<Room> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsTo<User> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
