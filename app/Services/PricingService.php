<?php

namespace App\Services;

use App\Exceptions\PricingException;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\Rate;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Per-night rate selection (BR-11) and booking totals (BR-12).
 *
 * A stay is priced one night at a time. For each night the winning rate
 * of the room type is selected (highest priority, ties broken by the
 * largest id per K2) and its price is the price of that night; booking
 * creation later copies that price into booking_nights.price so later
 * rate changes never touch existing bookings (BR-11.7, PR-08).
 */
class PricingService
{
    /**
     * Roles that may override the minimum nights of a rate (BR-11.8,
     * K1). Same bridge convention as AvailabilityService: the canonical
     * role names are fixed by the T-007 seeder.
     */
    private const MINIMUM_NIGHTS_OVERRIDE_ROLES = ['owner', 'manager'];

    /** Roles that may record a discount, i.e. a negative charge (BR-12.1, US-19). */
    private const DISCOUNT_ROLES = ['owner', 'manager'];

    /**
     * The rate that wins for the given room type on the given night
     * (BR-11.2/3): a candidate matches when it is the default rate or
     * its date range covers the night, and its days_of_week is empty or
     * contains the night's ISO weekday (1 = Monday .. 7 = Sunday).
     * Returns null when no rate matches; callers decide whether that is
     * an error (a room type without a default rate is not sellable).
     */
    public function nightlyRateFor(RoomType $roomType, CarbonInterface|string $night): ?Rate
    {
        $at = $this->toDay($night);
        $nightDate = $at->toDateString();
        $isoDay = (int) $at->format('N'); // ISO-8601 weekday, per BR-11.5

        $candidates = $roomType->rates()
            ->where(function ($query) use ($nightDate) {
                $query->where('is_default', true)
                    ->orWhere(function ($range) use ($nightDate) {
                        $range->where('start_date', '<=', $nightDate)
                            ->where('end_date', '>=', $nightDate);
                    });
            })
            ->get()
            ->filter(function (Rate $rate) use ($isoDay) {
                $days = $rate->days_of_week ?? [];

                return $days === [] || in_array($isoDay, array_map('intval', $days), true);
            });

        if ($candidates->isEmpty()) {
            return null;
        }

        // Highest priority wins; on a tie the largest id wins (K2).
        return $candidates
            ->sort(fn (Rate $a, Rate $b) => [$b->priority, $b->id] <=> [$a->priority, $a->id])
            ->first();
    }

    /**
     * The locked nightly price (whole rupiah, never float, PR-09).
     *
     * @throws PricingException when no rate matches the night, for
     *                          example an active room type without a default rate
     *                          outside every seasonal range (BR-11.4, PR-06).
     */
    public function pricePerNight(RoomType $roomType, CarbonInterface|string $night): int
    {
        $rate = $this->nightlyRateFor($roomType, $night);

        if ($rate === null) {
            $at = $this->toDay($night);

            throw new PricingException(
                "Room type {$roomType->name} has no rate covering {$at->toDateString()}; "
                .'every active room type needs a default rate (BR-11.4).',
            );
        }

        return (int) $rate->price;
    }

    /**
     * Price every night of the stay, one row per night with the date,
     * the whole-rupiah price and the winning rate (BR-11.1, BR-11.7).
     * The check-out day is not a night. Booking creation locks these
     * values into booking_nights.
     *
     * @return array<int, array{stay_date: string, price: int, rate_id: int}>
     *
     * @throws PricingException when any night has no matching rate.
     */
    public function quoteStay(RoomType $roomType, CarbonInterface|string $checkIn, CarbonInterface|string $checkOut): array
    {
        $from = $this->toDay($checkIn);
        $to = $this->toDay($checkOut);

        $quote = [];

        for ($night = $from; $night < $to; $night = $night->addDay()) {
            $rate = $this->nightlyRateFor($roomType, $night);

            if ($rate === null) {
                throw new PricingException(
                    "Room type {$roomType->name} has no rate covering {$night->toDateString()}; "
                    .'every active room type needs a default rate (BR-11.4).',
                );
            }

            $quote[] = [
                'stay_date' => $night->toDateString(),
                'price' => (int) $rate->price,
                'rate_id' => $rate->id,
            ];
        }

        return $quote;
    }

    /**
     * The minimum nights required by the rate that wins on the check-in
     * night (BR-11.8, K1).
     *
     * @throws PricingException when no rate matches the check-in night.
     */
    public function minimumNightsForCheckIn(RoomType $roomType, CarbonInterface|string $checkIn): int
    {
        $rate = $this->nightlyRateFor($roomType, $checkIn);

        if ($rate === null) {
            $at = $this->toDay($checkIn);

            throw new PricingException(
                "Room type {$roomType->name} has no rate covering {$at->toDateString()}; "
                .'cannot read the minimum nights (BR-11.4).',
            );
        }

        return (int) $rate->minimum_nights;
    }

    /**
     * A stay shorter than the minimum nights of the check-in night rate
     * is rejected; only the owner or manager may override it, and only
     * with a reason (BR-11.8, K1, PR-07).
     *
     * @throws PricingException when the stay is too short and no valid
     *                          override applies.
     */
    public function assertMinimumNightsSatisfied(
        RoomType $roomType,
        CarbonInterface|string $checkIn,
        int $nights,
        ?User $actor = null,
        ?string $reason = null,
    ): void {
        $required = $this->minimumNightsForCheckIn($roomType, $checkIn);

        if ($nights >= $required) {
            return;
        }

        $roleName = $actor?->role?->name;

        if (! in_array($roleName, self::MINIMUM_NIGHTS_OVERRIDE_ROLES, true)) {
            throw new PricingException(
                "Stay of {$nights} night(s) is below the minimum of {$required} night(s) "
                .'required by the rate on the check-in night (BR-11.8).',
            );
        }

        if (trim((string) $reason) === '') {
            throw new PricingException(
                'A reason is required to override the minimum nights rule (BR-11.8).',
            );
        }
    }

    /**
     * BR-11.4: every active room type must own exactly one default rate
     * with the lowest priority, so a matching rate always exists. An
     * active room type without one cannot be sold (PR-06).
     *
     * Only active room types are constrained: a deactivated room type
     * is not sellable at all - AvailabilityService already refuses its
     * rooms (BR-07.6, AV-14) - so it does not have to own a default
     * rate yet.
     *
     * @throws PricingException when the room type has no default rate.
     */
    public function assertHasDefaultRate(RoomType $roomType): void
    {
        if (! $roomType->is_active) {
            return;
        }

        $hasDefault = $roomType->rates()->where('is_default', true)->exists();

        if (! $hasDefault) {
            throw new PricingException(
                "Room type {$roomType->name} has no default rate; it cannot be sold (BR-11.4).",
            );
        }
    }

    /**
     * K2 / PR-05: saving a rate is rejected when another rate of the
     * same room type with the same priority overlaps it in dates and
     * days. Rates are rejected at save time, so a stored price always
     * has one unambiguous winner.
     *
     * @throws PricingException when an overlapping rate shares the priority.
     */
    public function assertNoConflictingRate(Rate $rate): void
    {
        $conflicts = Rate::query()
            ->where('room_type_id', $rate->room_type_id)
            ->where('priority', $rate->priority)
            ->when($rate->exists, fn ($query) => $query->where('id', '!=', $rate->id))
            ->get()
            ->contains(fn (Rate $other) => $this->ratesOverlap($rate, $other));

        if ($conflicts) {
            throw new PricingException(
                "Another rate of the same room type overlaps this one with priority {$rate->priority}; "
                .'overlapping rates must use different priorities (BR-11.3).',
            );
        }
    }

    /**
     * BR-12 totals for one booking: the subtotal of the active nights,
     * the sum of the extra charges (discounts are negative) and their
     * total. Inactive nights (cancelled, expired, no-show) never count.
     *
     * @return array{subtotal: int, charges: int, total: int}
     */
    public function calculateTotals(Booking $booking): array
    {
        $subtotal = (int) $booking->bookingNights()
            ->where('is_active', true)
            ->sum('price');

        $charges = (int) $booking->bookingCharges()->sum('amount');

        return [
            'subtotal' => $subtotal,
            'charges' => $charges,
            'total' => $subtotal + $charges,
        ];
    }

    /**
     * Recompute and persist bookings.total_amount from the source of
     * truth (booking_nights and booking_charges, BR-12.2). Call this
     * after any change to nights or charges.
     */
    public function recalculateTotal(Booking $booking): int
    {
        $total = $this->calculateTotals($booking)['total'];

        $booking->update(['total_amount' => $total]);

        return $total;
    }

    /**
     * Record an extra charge on a booking (BR-12.1, US-19). A positive
     * amount is an extra bed or similar; a negative amount is a
     * discount, which only the owner or manager may record. The booking
     * total is recalculated afterwards (PR-11).
     *
     * @throws PricingException when a receptionist or housekeeping tries
     *                          to record a discount.
     */
    public function addCharge(Booking $booking, string $description, int $amount, User $actor): BookingCharge
    {
        $roleName = $actor->role?->name;

        if ($amount < 0 && ! in_array($roleName, self::DISCOUNT_ROLES, true)) {
            throw new PricingException(
                'A discount may only be recorded by the owner or manager (BR-12.1).',
            );
        }

        $charge = $booking->bookingCharges()->create([
            'description' => $description,
            'amount' => $amount,
        ]);

        $this->recalculateTotal($booking);

        return $charge;
    }

    /**
     * Two rates overlap when they cover at least one shared night: their
     * days of week intersect (an empty list means every day) and their
     * date ranges intersect (a default rate covers every date).
     */
    private function ratesOverlap(Rate $a, Rate $b): bool
    {
        return $this->daysOverlap($a, $b) && $this->datesOverlap($a, $b);
    }

    private function daysOverlap(Rate $a, Rate $b): bool
    {
        $aDays = array_map('intval', $a->days_of_week ?? []);
        $bDays = array_map('intval', $b->days_of_week ?? []);

        if ($aDays === [] || $bDays === []) {
            return true;
        }

        return array_intersect($aDays, $bDays) !== [];
    }

    private function datesOverlap(Rate $a, Rate $b): bool
    {
        // A default rate covers every date.
        if ($a->is_default || $b->is_default) {
            return true;
        }

        // A non-default rate without a range is never active.
        if ($a->start_date === null || $a->end_date === null
            || $b->start_date === null || $b->end_date === null) {
            return false;
        }

        return $a->start_date <= $b->end_date && $b->start_date <= $a->end_date;
    }

    private function toDay(CarbonInterface|string $day): CarbonImmutable
    {
        $date = $day instanceof CarbonInterface
            ? CarbonImmutable::instance($day)
            : CarbonImmutable::parse($day);

        return $date->startOfDay();
    }
}
