<?php

namespace App\Models;

use Database\Factories\RoomTypeAmenityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $room_type_id
 * @property int $amenity_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['room_type_id', 'amenity_id'])]
class RoomTypeAmenity extends Model
{
    /** @use HasFactory<RoomTypeAmenityFactory> */
    use HasFactory;

    /** @return BelongsTo<RoomType> */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /** @return BelongsTo<Amenity> */
    public function amenity(): BelongsTo
    {
        return $this->belongsTo(Amenity::class);
    }
}
