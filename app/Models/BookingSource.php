<?php

namespace App\Models;

use Database\Factories\BookingSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class BookingSource extends Model
{
    /** @use HasFactory<BookingSourceFactory> */
    use HasFactory;

    /** @return HasMany<Booking> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
