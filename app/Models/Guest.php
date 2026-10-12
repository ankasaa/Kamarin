<?php

namespace App\Models;

use Database\Factories\GuestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $country
 * @property string|null $id_type
 * @property string|null $id_number
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'country', 'id_type', 'id_number'])]
class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory, SoftDeletes;

    /** @return HasMany<Booking> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
