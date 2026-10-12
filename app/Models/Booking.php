<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentOption;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $property_id
 * @property int $guest_id
 * @property int|null $created_by_user_id
 * @property int $booking_source_id
 * @property string $booking_code
 * @property BookingStatus $status
 * @property PaymentOption $payment_option
 * @property int $guest_count
 * @property Carbon $check_in_date
 * @property Carbon $check_out_date
 * @property Carbon|null $expires_at
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $checked_out_at
 * @property int $total_amount
 * @property string|null $cancellation_reason
 * @property Carbon|null $cancelled_at
 * @property string|null $notes
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'property_id',
    'guest_id',
    'created_by_user_id',
    'booking_source_id',
    'booking_code',
    'status',
    'payment_option',
    'guest_count',
    'check_in_date',
    'check_out_date',
    'expires_at',
    'checked_in_at',
    'checked_out_at',
    'total_amount',
    'cancellation_reason',
    'cancelled_at',
    'notes',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * total_amount is numeric(12,0) (whole rupiah, never float). The
     * explicit date format keeps serialized dates as "Y-m-d" so a stay
     * date can never shift a day when the JSON is read back.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'payment_option' => PaymentOption::class,
            'guest_count' => 'integer',
            'check_in_date' => 'date:Y-m-d',
            'check_out_date' => 'date:Y-m-d',
            'total_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<Property> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<Guest> */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /** @return BelongsTo<User> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<BookingSource> */
    public function bookingSource(): BelongsTo
    {
        return $this->belongsTo(BookingSource::class);
    }

    /** @return HasMany<BookingItem> */
    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /**
     * Every night across all items of this booking (ERD path
     * BOOKINGS → BOOKING_ITEMS → BOOKING_NIGHTS). BR-12 reads this to
     * sum the locked nightly prices; is_active is filtered there.
     *
     * @return HasManyThrough<BookingNight, BookingItem>
     */
    public function bookingNights(): HasManyThrough
    {
        return $this->hasManyThrough(BookingNight::class, BookingItem::class);
    }

    /** @return HasMany<BookingCharge> */
    public function bookingCharges(): HasMany
    {
        return $this->hasMany(BookingCharge::class);
    }

    /** @return HasMany<Payment> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<BookingStatusLog> */
    public function bookingStatusLogs(): HasMany
    {
        return $this->hasMany(BookingStatusLog::class);
    }

    /** @return HasMany<BookingChangeLog> */
    public function bookingChangeLogs(): HasMany
    {
        return $this->hasMany(BookingChangeLog::class);
    }
}
