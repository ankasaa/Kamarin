<?php

namespace App\Models;

use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $address
 * @property string $city
 * @property string|null $phone
 * @property string $check_in_time
 * @property string $check_out_time
 * @property int $deposit_percent
 * @property int $pending_expiry_hours
 * @property int $free_cancel_days
 * @property string $no_show_cutoff_time
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'address',
    'city',
    'phone',
    'check_in_time',
    'check_out_time',
    'deposit_percent',
    'pending_expiry_hours',
    'free_cancel_days',
    'no_show_cutoff_time',
])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * Policy times (check-in, check-out, no-show cutoff) come from
     * PostgreSQL as plain "HH:MM:SS" strings and stay strings; policies
     * are never hardcoded in the application (AGENTS.md rule 6).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'check_in_time' => 'string',
            'check_out_time' => 'string',
            'no_show_cutoff_time' => 'string',
            'deposit_percent' => 'integer',
            'pending_expiry_hours' => 'integer',
            'free_cancel_days' => 'integer',
        ];
    }

    /** @return HasMany<User> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<RoomType> */
    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    /** @return HasMany<Room> */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /** @return HasMany<Rate> */
    public function rates(): HasMany
    {
        return $this->hasMany(Rate::class);
    }

    /** @return HasMany<Booking> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
