<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentOption;
use App\Exceptions\AvailabilityException;
use App\Exceptions\BookingException;
use App\Exceptions\PricingException;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingNight;
use App\Models\BookingSource;
use App\Models\BookingStatusLog;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Creating a booking atomically (T-010).
 *
 * A booking is the one write that must never leave a room half-sold, so
 * every row is written inside a single DB::transaction that first takes
 * a row lock on each room (ordered by id to avoid deadlocks) and then
 * re-runs the availability rules. That recheck under the lock is layer 2
 * of the anti double booking defence (BR-01.5): layer 1 is
 * AvailabilityService's plain queries, layer 3 is the partial unique
 * index on booking_nights. The booking code (BR-14), the pending expiry
 * (BR-13) and the first status log are decided here as well.
 */
class BookingService
{
    /**
     * Booking codes are 8 characters from an alphabet without the
     * confusable 0, O, 1 and I, so a code read aloud over the phone
     * cannot be misheard (BR-14).
     */
    private const CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const CODE_LENGTH = 8;

    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
    ) {}

    /**
     * Create a pending booking for the given rooms, locking the price of
     * every night into booking_nights (BR-11.7) and writing the first
     * booking_status_logs entry (BK-01).
     *
     * Website bookings (no staff actor) carry created_by_user_id = null
     * and an expires_at of now + property's pending_expiry_hours (BR-13,
     * K5); staff bookings never expire.
     *
     * @param  iterable<int, Room>  $rooms
     *
     * @throws BookingException when no booking source is given (BR-09).
     * @throws AvailabilityException when the rooms are not free or the
     *                               stay is invalid (BR-01, layer 2 or 3).
     * @throws PricingException when a night has no rate
     *                          or the stay is below the
     *                          minimum nights (BR-11).
     */
    public function createBooking(
        Property $property,
        Guest $guest,
        ?BookingSource $source,
        PaymentOption $paymentOption,
        int $guestCount,
        CarbonInterface|string $checkIn,
        CarbonInterface|string $checkOut,
        iterable $rooms,
        ?User $actor = null,
        ?string $reason = null,
    ): Booking {
        if ($source === null) {
            throw new BookingException('A booking must have a booking source (BR-09).');
        }

        $rooms = collect($rooms)->unique('id')->values();

        try {
            return DB::transaction(function () use (
                $property,
                $guest,
                $source,
                $paymentOption,
                $guestCount,
                $checkIn,
                $checkOut,
                $rooms,
                $actor,
                $reason,
            ): Booking {
                // Layer 2 (BR-01.5): lock every room row in a stable id
                // order, then re-check availability under that lock. A
                // concurrent transaction for the same rooms waits here
                // and sees the committed nights when it resumes.
                $lockedRooms = Room::query()
                    ->whereIn('id', $rooms->pluck('id'))
                    ->orderBy('id')
                    ->with('roomType')
                    ->lockForUpdate()
                    ->get();

                $this->availability->assertValidStay(
                    $property,
                    $checkIn,
                    $checkOut,
                    $guestCount,
                    $lockedRooms,
                    $actor,
                    $reason,
                );

                $nights = $this->countNights($checkIn, $checkOut);

                $booking = Booking::create([
                    'property_id' => $property->id,
                    'guest_id' => $guest->id,
                    'booking_source_id' => $source->id,
                    'booking_code' => $this->generateBookingCode(),
                    'status' => BookingStatus::Pending,
                    'payment_option' => $paymentOption,
                    'guest_count' => $guestCount,
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                    'created_by_user_id' => $actor?->id,
                    'expires_at' => $actor === null
                        ? now()->addHours((int) $property->pending_expiry_hours)
                        : null,
                    'total_amount' => 0,
                ]);

                foreach ($lockedRooms as $room) {
                    $roomType = $room->roomType;

                    $this->pricing->assertMinimumNightsSatisfied(
                        $roomType,
                        $checkIn,
                        $nights,
                        $actor,
                        $reason,
                    );

                    // BR-11.7: quote the stay once and copy price + rate_id
                    // into booking_nights, so later rate changes never touch
                    // this booking (PR-08 end-to-end).
                    $quote = $this->pricing->quoteStay($roomType, $checkIn, $checkOut);

                    $item = BookingItem::create([
                        'booking_id' => $booking->id,
                        'room_id' => $room->id,
                        'subtotal' => array_sum(array_column($quote, 'price')),
                    ]);

                    foreach ($quote as $night) {
                        BookingNight::create([
                            'booking_item_id' => $item->id,
                            'room_id' => $room->id,
                            'rate_id' => $night['rate_id'],
                            'stay_date' => $night['stay_date'],
                            'price' => $night['price'],
                            'is_active' => $booking->status->holdsNights(),
                        ]);
                    }
                }

                $this->pricing->recalculateTotal($booking);

                BookingStatusLog::create([
                    'booking_id' => $booking->id,
                    'changed_by_user_id' => $actor?->id,
                    'old_status' => null,
                    'new_status' => BookingStatus::Pending,
                    'note' => 'Booking created.',
                ]);

                return $booking;
            });
        } catch (QueryException $e) {
            // Layer 3 (BR-01.5): if a race slips past the row lock, the
            // partial unique index on (room_id, stay_date) WHERE is_active
            // rejects the insert. Translate that 23505 into the same clear
            // availability message instead of leaking a driver error.
            //
            // Verified on this PostgreSQL driver: errorInfo is
            // [SQLSTATE, severity, message] and only the message carries
            // the violated constraint name. Matching the index name
            // matters because bookings.booking_code is unique too
            // (BR-14): a collision there is not a room being taken and
            // must not be reported as one.
            if ($this->isDuplicateActiveNight($e)) {
                throw new AvailabilityException(
                    'One of the rooms was just booked for those dates; please re-check availability.',
                    previous: $e,
                );
            }

            throw $e;
        }
    }

    /**
     * Find a guest by email, or create one (US-21, BK-07).
     *
     * A returning guest with a matching email is reused as-is: the
     * existing row is neither duplicated nor overwritten, so two records
     * that happen to share an email are never merged automatically.
     */
    public function findOrCreateGuest(string $name, ?string $email = null, ?string $phone = null): Guest
    {
        if ($email !== null && trim($email) !== '') {
            $existing = Guest::where('email', $email)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return Guest::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ]);
    }

    /**
     * Number of nights in [checkIn, checkOut): the check-out day is not
     * a night (BR-01 convention, shared with AvailabilityService).
     */
    private function countNights(CarbonInterface|string $checkIn, CarbonInterface|string $checkOut): int
    {
        $from = $this->toDay($checkIn);
        $to = $this->toDay($checkOut);

        return (int) $from->diffInDays($to, false);
    }

    private function toDay(CarbonInterface|string $day): CarbonImmutable
    {
        $date = $day instanceof CarbonInterface
            ? CarbonImmutable::instance($day)
            : CarbonImmutable::parse($day);

        return $date->startOfDay();
    }

    /**
     * Whether the failure is the partial unique index refusing a second
     * active night for the same room and date (BR-01 layer 3), rather
     * than any other unique constraint on the database.
     */
    private function isDuplicateActiveNight(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $message = (string) ($e->errorInfo[2] ?? $e->getMessage());

        return $sqlState === '23505'
            && str_contains($message, 'booking_nights_room_stay_active_unique');
    }

    /**
     * A fresh, unique BR-14 booking code: 8 characters from the safe
     * alphabet, random (never a sequence) and not already used by any
     * row, including soft-deleted ones the unique index still covers.
     */
    private function generateBookingCode(): string
    {
        do {
            $code = '';

            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (Booking::withTrashed()->where('booking_code', $code)->exists());

        return $code;
    }
}
