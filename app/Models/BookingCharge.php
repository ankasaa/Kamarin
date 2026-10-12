<?php

namespace App\Models;

use Database\Factories\BookingChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property string $description
 * @property int $amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['booking_id', 'description', 'amount'])]
class BookingCharge extends Model
{
    /** @use HasFactory<BookingChargeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * amount is numeric(12,0) and intentionally has NO database CHECK:
     * discounts are recorded as negative amounts (locked decision in
     * docs/STATUS.md).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    /** @return BelongsTo<Booking> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
