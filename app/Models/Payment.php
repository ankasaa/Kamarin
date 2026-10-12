<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property int|null $recorded_by_user_id
 * @property int $amount
 * @property PaymentType $type
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property string|null $payer_name
 * @property Carbon $payment_date
 * @property string|null $reference_number
 * @property string|null $proof_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'booking_id',
    'recorded_by_user_id',
    'amount',
    'type',
    'method',
    'status',
    'payer_name',
    'payment_date',
    'reference_number',
    'proof_path',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'type' => PaymentType::class,
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'payment_date' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<Booking> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
