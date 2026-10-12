<?php

namespace App\Services;

use App\Enums\RoomStatus;
use App\Exceptions\AvailabilityException;
use App\Models\BookingNight;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Layer 1 of the three-layer anti double booking defence (BR-01.5):
 * every availability question is answered here, in plain queries,
 * before any booking row is written. The database layers (row lock in
 * the booking transaction, partial unique index on active nights)
 * belong to BookingService (T-009) and the schema (T-005).
 *
 * Rules implemented (BR-01, BR-07):
 * - A room is sellable only when rooms.status = active (BR-07.1).
 * - Active booking_nights block a room for their night; cancelled,
 *   expired and no-show bookings have inactive nights and never block
 *   (BR-01).
 * - Room blocks follow the check-out convention: the block
 *   [start_date, end_date) covers nights start_date .. end_date - 1.
 *   The database CHECK (end_date > start_date) makes any other
 *   reading impossible for a one-night block.
 * - Housekeeping status never affects availability for future dates
 *   (BR-07.5): it is not part of any query here.
 * - Turnover on the same day is allowed: a stay may start on the day
 *   another one ends (BR-01.3).
 */
class AvailabilityService
{
    /**
     * Roles allowed to record a booking with a past check-in date
     * (BR-01.4: "Owner dan Manager boleh memakai tanggal lampau dengan
     * alasan"). Canonical role names are seeded in T-007.
     */
    private const PAST_DATE_ROLES = ['owner', 'manager'];

    /**
     * Rooms of the property that are sellable and free for every night
     * of [checkIn, checkOut). When guestCount is given, only rooms
     * whose room type capacity fits that many guests are returned.
     * Housekeeping status is deliberately ignored (BR-07.5).
     *
     * @return Collection<int, Room>
     */
    public function availableRooms(
        Property $property,
        CarbonInterface|string $checkIn,
        CarbonInterface|string $checkOut,
        ?int $guestCount = null,
        ?int $excludeBookingId = null,
    ): Collection {
        [$from, $to] = $this->stayNights($checkIn, $checkOut);

        $conflictedRoomIds = $this->roomIdsWithActiveNights($property, $from, $to, $excludeBookingId)
            ->merge($this->roomIdsWithBlocks($property, $from, $to))
            ->unique();

        return $property->rooms()
            ->with('roomType')
            ->where('status', RoomStatus::Active->value)
            ->when($guestCount !== null, fn (Builder $query) => $query->whereHas(
                'roomType',
                fn (Builder $query) => $query->where('capacity', '>=', $guestCount),
            ))
            ->when($conflictedRoomIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('id', $conflictedRoomIds))
            ->orderBy('id')
            ->get();
    }

    /**
     * Assert that a stay from checkIn to checkOut for guestCount guests
     * in the given rooms is valid according to BR-01: dates ordered,
     * check-in not in the past (unless owner/manager with a reason),
     * rooms part of the property and active, capacity sufficient, and
     * every night free of active bookings and blocks.
     *
     * @param  iterable<int, Room>  $rooms
     *
     * @throws AvailabilityException
     */
    public function assertValidStay(
        Property $property,
        CarbonInterface|string $checkIn,
        CarbonInterface|string $checkOut,
        int $guestCount,
        iterable $rooms,
        ?User $user = null,
        ?string $reason = null,
        ?int $excludeBookingId = null,
    ): void {
        [$from, $to] = $this->stayNights($checkIn, $checkOut);

        $this->assertCheckInIsNotInPast($from, $user, $reason);

        // Deduplicate by primary key: the same room passed twice must
        // not double its capacity (BR-01.2 counts the capacity of the
        // rooms actually booked, once each).
        $rooms = collect($rooms)->unique('id')->values();

        if ($rooms->isEmpty()) {
            throw new AvailabilityException('A stay requires at least one room.');
        }

        foreach ($rooms as $room) {
            if ($room->property_id !== $property->id) {
                throw new AvailabilityException("Room {$room->room_number} does not belong to this property.");
            }

            if ($room->status !== RoomStatus::Active) {
                throw new AvailabilityException("Room {$room->room_number} is not active and cannot be sold (BR-07).");
            }
        }

        $capacity = (int) $rooms->sum(fn (Room $room) => $room->roomType->capacity);

        if ($guestCount > $capacity) {
            throw new AvailabilityException(
                "guest_count {$guestCount} exceeds the total capacity of the selected rooms ({$capacity}).",
            );
        }

        foreach ($rooms as $room) {
            $this->assertRoomIsFree($room, $from, $to, $excludeBookingId);
        }
    }

    /**
     * Assert that a block [start, end) may be created for the room
     * (BR-01.6: the partial index only guards bookings against
     * bookings, so booking vs block overlap is enforced here).
     *
     * @throws AvailabilityException
     */
    public function assertRoomCanBeBlocked(
        Room $room,
        CarbonInterface|string $start,
        CarbonInterface|string $end,
    ): void {
        [$from, $to] = $this->stayNights($start, $end, 'end_date', 'start_date');

        $hasActiveNights = $this->activeNightsQuery($from, $to, null)
            ->where('room_id', $room->id)
            ->exists();

        if ($hasActiveNights) {
            throw new AvailabilityException(
                "Room {$room->room_number} has active nights that overlap the block; move the booking first (BR-01).",
            );
        }
    }

    /**
     * Assert that a room may move to the given status (BR-07.2):
     * maintenance and inactive are refused while the room still has
     * active nights from today onwards - the booking must be moved
     * first. Moving to active is always allowed.
     *
     * @throws AvailabilityException
     */
    public function assertRoomStatusChangeAllowed(Room $room, RoomStatus $newStatus): void
    {
        if ($newStatus === RoomStatus::Active) {
            return;
        }

        $hasUpcomingNights = BookingNight::query()
            ->where('room_id', $room->id)
            ->where('is_active', true)
            ->where('stay_date', '>=', today())
            ->exists();

        if ($hasUpcomingNights) {
            throw new AvailabilityException(
                "Room {$room->room_number} still has active nights from today onwards; move the booking first (BR-07).",
            );
        }
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     *
     * @throws AvailabilityException
     */
    private function stayNights(
        CarbonInterface|string $start,
        CarbonInterface|string $end,
        string $endLabel = 'check_out_date',
        string $startLabel = 'check_in_date',
    ): array {
        $from = $this->toDay($start);
        $to = $this->toDay($end);

        if ($to <= $from) {
            throw new AvailabilityException("{$endLabel} must be after {$startLabel}.");
        }

        return [$from, $to];
    }

    private function toDay(CarbonInterface|string $day): CarbonImmutable
    {
        $date = $day instanceof CarbonInterface
            ? CarbonImmutable::instance($day)
            : CarbonImmutable::parse($day);

        return $date->startOfDay();
    }

    /**
     * BR-01.4: a check-in date before today is only allowed for the
     * owner or manager, and only with a reason.
     *
     * @throws AvailabilityException
     */
    private function assertCheckInIsNotInPast(CarbonImmutable $from, ?User $user, ?string $reason): void
    {
        if ($from >= today()) {
            return;
        }

        $roleName = $user?->role?->name;

        if (! in_array($roleName, self::PAST_DATE_ROLES, true)) {
            throw new AvailabilityException(
                'Check-in date is in the past; only the owner or manager may book it.',
            );
        }

        if (trim((string) $reason) === '') {
            throw new AvailabilityException('A reason is required to book a past check-in date.');
        }
    }

    /**
     * @throws AvailabilityException
     */
    private function assertRoomIsFree(Room $room, CarbonImmutable $from, CarbonImmutable $to, ?int $excludeBookingId): void
    {
        $hasActiveNights = $this->activeNightsQuery($from, $to, $excludeBookingId)
            ->where('room_id', $room->id)
            ->exists();

        if ($hasActiveNights) {
            throw new AvailabilityException(
                "Room {$room->room_number} already has active nights that overlap the requested dates.",
            );
        }

        $isBlocked = RoomBlock::query()
            ->where('room_id', $room->id)
            ->where('start_date', '<', $to)
            ->where('end_date', '>', $from)
            ->exists();

        if ($isBlocked) {
            throw new AvailabilityException(
                "Room {$room->room_number} is blocked for part of the requested dates.",
            );
        }
    }

    /**
     * Active nights of the half-open night range [from, to); optionally
     * exclude the nights of one booking (used later when a booking is
     * edited and must not block itself).
     */
    private function activeNightsQuery(CarbonImmutable $from, CarbonImmutable $to, ?int $excludeBookingId): Builder
    {
        return BookingNight::query()
            ->where('is_active', true)
            ->where('stay_date', '>=', $from)
            ->where('stay_date', '<', $to)
            ->when($excludeBookingId !== null, fn (Builder $query) => $query->whereDoesntHave(
                'bookingItem',
                fn (Builder $query) => $query->where('booking_id', $excludeBookingId),
            ));
    }

    /** @return SupportCollection<int, int> */
    private function roomIdsWithActiveNights(
        Property $property,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $excludeBookingId,
    ): SupportCollection {
        return $this->activeNightsQuery($from, $to, $excludeBookingId)
            ->whereIn('room_id', $property->rooms()->select('id'))
            ->pluck('room_id');
    }

    /** @return SupportCollection<int, int> */
    private function roomIdsWithBlocks(Property $property, CarbonImmutable $from, CarbonImmutable $to): SupportCollection
    {
        return RoomBlock::query()
            ->where('start_date', '<', $to)
            ->where('end_date', '>', $from)
            ->whereIn('room_id', $property->rooms()->select('id'))
            ->pluck('room_id');
    }
}
