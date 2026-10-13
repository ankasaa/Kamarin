<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\RoleName;
use App\Exceptions\AvailabilityException;
use App\Exceptions\BookingException;
use App\Exceptions\PricingException;
use App\Models\Booking;
use App\Models\BookingChangeLog;
use App\Models\BookingItem;
use App\Models\BookingNight;
use App\Models\Room;
use App\Models\User;
use App\Support\BookingChangeResult;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Editing a booking that already exists: its dates, the room it sits in
 * and how many guests it holds (BR-06).
 *
 * Every write here follows the same three steps as
 * BookingService::createBooking, because a change has the same power to
 * double sell a room as a new booking does. The booking is re-read under
 * a row lock, then the room rows are locked in a stable id order, then
 * the availability rules are re-run under that lock (BR-01 layer 2). The
 * partial unique index on booking_nights is still the last line of
 * defence and its refusal is translated below.
 *
 * Two rules shape the prices. K3 keeps every night that survives the
 * change at the price it was booked at, so a rate change in the meantime
 * only touches nights that are genuinely new - and a room move inside
 * the same room type changes no price at all. BR-12.2 then recomputes
 * bookings.total_amount and booking_items.subtotal from the nights and
 * charges, never the other way round.
 *
 * This is also the only writer of the columns BR-06 owns
 * (check_in_date, check_out_date, room_id, guest_count); a status
 * transition belongs to BookingService alone (AGENTS.md rule 3).
 */
class BookingChangeService
{
    /**
     * BR-06 "Siapa": the owner, the manager and the receptionist. Written
     * as a RoleName allow-list so a role outside the canonical four -
     * housekeeping, or anything a future seeder invents - is refused
     * instead of slipping through a deny-list (AGENTS.md rule 8).
     */
    private const CHANGE_ROLES = [RoleName::Owner, RoleName::Manager, RoleName::Receptionist];

    /** BR-06 "Status yang boleh diubah": pending and confirmed carry every change type. */
    private const OPEN_STATUSES = [BookingStatus::Pending, BookingStatus::Confirmed];

    /** ERD v2.1 BOOKING_CHANGE_LOGS.change_type. */
    private const DATES_CHANGED = 'dates_changed';

    private const ROOM_CHANGED = 'room_changed';

    private const GUEST_COUNT_CHANGED = 'guest_count_changed';

    /**
     * The reason a preview passes down to the shared checks. A reason is
     * mandatory for every real change (K4), so by the time a change is
     * saved one always exists; preview runs before staff have typed it.
     * Substituting a non-empty value makes preview evaluate the role
     * rules - which is what matters - instead of failing on a reason the
     * staff member is about to give anyway.
     */
    private const PREVIEW_REASON = 'preview';

    /**
     * Direction hints for extend() and shorten(). The check-out must
     * really move in the promised direction, and it is compared against
     * the booking as it sits behind the row lock - never against the
     * model the caller happened to hold.
     */
    private const MOVES_LATER = 'later';

    private const MOVES_EARLIER = 'earlier';

    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Change the stay dates: extend it, shorten it, or move it to other
     * dates entirely. Nights that stay in the keep their price (K3),
     * nights that leave are released and nights that arrive are priced
     * at the rate in force (BR-11.7).
     *
     * @throws BookingException when the role, the reason, the status or
     *                          the minimum nights refuse the change
     * @throws AvailabilityException when a new night is already taken
     *                               (BR-01 layer 2 or 3)
     */
    public function changeDates(
        Booking $booking,
        CarbonInterface|string $checkIn,
        CarbonInterface|string $checkOut,
        User $actor,
        string $reason,
    ): BookingChangeResult {
        $this->assertMayChange($actor);
        $this->assertReasonGiven($reason);

        $from = $this->toDay($checkIn);
        $to = $this->toDay($checkOut);

        return $this->runDateChange($booking, $from, $to, $actor, $reason, direction: null);
    }

    /**
     * The single transaction behind changeDates(), extend() and
     * shorten().
     *
     * A null $from means "keep whatever check-in the booking already
     * has"; it is resolved from the row once it is locked, never from
     * the caller's model, so two staff working on the same booking
     * cannot act on a date that has just moved (review finding N2).
     */
    private function runDateChange(
        Booking $booking,
        ?CarbonImmutable $from,
        CarbonImmutable $to,
        User $actor,
        string $reason,
        ?string $direction,
    ): BookingChangeResult {
        try {
            return DB::transaction(fn (): BookingChangeResult => $this->performDateChange(
                $booking,
                $from,
                $to,
                $actor,
                $reason,
                $direction,
            ));
        } catch (QueryException $e) {
            throw $this->translateDuplicateNight($e);
        }
    }

    /**
     * The transaction body: everything runs with the booking and the
     * room rows already locked.
     */
    private function performDateChange(
        Booking $booking,
        ?CarbonImmutable $from,
        CarbonImmutable $to,
        User $actor,
        string $reason,
        ?string $direction,
    ): BookingChangeResult {
        $booking = $this->lockBooking($booking);

        $from ??= $this->toDay($booking->check_in_date);

        if ($direction !== null) {
            $this->assertCheckOutMoves($booking, $to, $direction);
        }

        $this->assertStatusAllows($booking, self::DATES_CHANGED, $from);

        $rooms = $this->lockBookingRooms($booking);

        $this->assertStayStillValid($booking, $from, $to, $rooms, $actor, $reason);

        $oldTotal = (int) $booking->total_amount;
        $oldValue = $this->datesOf($booking);

        foreach ($booking->bookingItems()->get() as $item) {
            $this->applyNightDiff($item, $from, $to);
        }

        $booking->fill([
            'check_in_date' => $from->toDateString(),
            'check_out_date' => $to->toDateString(),
        ])->save();

        $this->recalculate($booking);

        $log = $this->writeLog(
            $booking,
            self::DATES_CHANGED,
            $oldValue,
            $this->datesOf($booking),
            $actor,
            $reason,
        );

        return $this->result($booking, $oldTotal, (int) $booking->total_amount, $log);
    }

    /**
     * Keep the check-in date and move the check-out later (BR-06
     * "perpanjang"), which is the shape a guest asking for more nights
     * actually needs.
     *
     * Both the base dates and the direction check are read from the row
     * once it is locked, so a colleague's change made a moment earlier
     * is never measured against a stale model (review finding N2).
     *
     * @throws BookingException when the new check-out is not later, or
     *                          when the change itself is refused
     */
    public function extend(
        Booking $booking,
        CarbonInterface|string $newCheckOut,
        User $actor,
        string $reason,
    ): BookingChangeResult {
        $this->assertMayChange($actor);
        $this->assertReasonGiven($reason);

        return $this->runDateChange(
            $booking,
            from: null,
            to: $this->toDay($newCheckOut),
            actor: $actor,
            reason: $reason,
            direction: self::MOVES_LATER,
        );
    }

    /**
     * Keep the check-in date and move the check-out earlier (BR-06
     * "percepat check-out"). The nights after the new check-out are
     * released and the total follows them down; the refund, if one is
     * due, is never automatic - staff record it (BR-06).
     *
     * @throws BookingException when the new check-out is not earlier, when
     *                          it would leave no nights, or when the
     *                          change itself is refused
     */
    public function shorten(
        Booking $booking,
        CarbonInterface|string $newCheckOut,
        User $actor,
        string $reason,
    ): BookingChangeResult {
        $this->assertMayChange($actor);
        $this->assertReasonGiven($reason);

        return $this->runDateChange(
            $booking,
            from: null,
            to: $this->toDay($newCheckOut),
            actor: $actor,
            reason: $reason,
            direction: self::MOVES_EARLIER,
        );
    }

    /**
     * Move the booking to another room of the same room type (CH-08).
     *
     * No night is repriced: the rows keep their locked price and only
     * booking_items.room_id and booking_nights.room_id change. Moving to
     * a different room type would have to reprice every moved night and
     * show the difference before saving, which BR-06 defines but this
     * release deliberately leaves out (docs/tasks backlog T-013).
     *
     * @throws BookingException when the role, the reason or the status
     *                          refuses the change, when the booking no
     *                          longer holds a single room, or when the
     *                          target room belongs to another room type
     * @throws AvailabilityException when the target room is already
     *                               taken for part of the stay (BR-01)
     */
    public function moveRoom(Booking $booking, Room $newRoom, User $actor, string $reason): BookingChangeResult
    {
        $this->assertMayChange($actor);
        $this->assertReasonGiven($reason);

        try {
            return DB::transaction(
                fn (): BookingChangeResult => $this->performRoomMove($booking, $newRoom, $actor, $reason),
            );
        } catch (QueryException $e) {
            throw $this->translateDuplicateNight($e);
        }
    }

    /**
     * The transaction behind moveRoom(): the booking and both room rows
     * are locked before anything moves.
     */
    private function performRoomMove(Booking $booking, Room $newRoom, User $actor, string $reason): BookingChangeResult
    {
        $booking = $this->lockBooking($booking);

        $this->assertStatusAllows($booking, self::ROOM_CHANGED);

        $item = $this->singleItem($booking);

        // Both rooms take a row lock, in id order: the one being left
        // and the one being taken, so a concurrent move or booking for
        // either room waits here (Pertanyaan Terbuka 3).
        $rooms = $this->lockRooms([
            ...$booking->bookingItems()->pluck('room_id')->all(),
            $newRoom->id,
        ]);

        $target = collect($rooms)->firstWhere('id', $newRoom->id);

        if ($item->room_id === $target->id) {
            throw new BookingException('The booking already occupies that room.');
        }

        if ($item->room->room_type_id !== $target->room_type_id) {
            throw new BookingException(
                'A booking may only be moved to another room of the same room type; '
                .'moving between room types reprices the nights and is not part of this release (BR-06).',
            );
        }

        // Only the room being taken has to be free: the one being left
        // is released by this very change.
        $this->assertStayStillValid(
            $booking,
            $this->toDay($booking->check_in_date),
            $this->toDay($booking->check_out_date),
            [$target],
            $actor,
            $reason,
        );

        $oldTotal = (int) $booking->total_amount;
        $oldValue = $this->roomOf($item->room);

        $item->update(['room_id' => $target->id]);
        $item->bookingNights()->update(['room_id' => $target->id]);

        $this->recalculate($booking);

        $log = $this->writeLog(
            $booking,
            self::ROOM_CHANGED,
            $oldValue,
            $this->roomOf($target),
            $actor,
            $reason,
        );

        return $this->result($booking, $oldTotal, (int) $booking->total_amount, $log);
    }

    /**
     * Change how many guests the booking holds (BR-06 "ubah jumlah
     * tamu"). The count may never exceed the capacity of the rooms the
     * booking already holds (BR-01.2).
     *
     * @throws BookingException when the count is impossible or the
     *                          change is refused
     */
    public function changeGuestCount(Booking $booking, int $guestCount, User $actor, string $reason): BookingChangeResult
    {
        $this->assertMayChange($actor);
        $this->assertReasonGiven($reason);

        return DB::transaction(function () use ($booking, $guestCount, $actor, $reason): BookingChangeResult {
            $booking = $this->lockBooking($booking);

            $this->assertStatusAllows($booking, self::GUEST_COUNT_CHANGED);

            if ($guestCount < 1) {
                throw new BookingException('guest_count must be at least 1 (BR-01.2).');
            }

            $rooms = $this->lockBookingRooms($booking);
            $capacity = (int) collect($rooms)->sum(fn (Room $room) => $room->roomType->capacity);

            if ($guestCount > $capacity) {
                throw new BookingException(sprintf(
                    'guest_count %d exceeds the capacity of the booked rooms (%d) (BR-01.2).',
                    $guestCount,
                    $capacity,
                ));
            }

            $oldTotal = (int) $booking->total_amount;
            $oldValue = ['guest_count' => $booking->guest_count];

            $booking->update(['guest_count' => $guestCount]);

            $log = $this->writeLog(
                $booking,
                self::GUEST_COUNT_CHANGED,
                $oldValue,
                ['guest_count' => $guestCount],
                $actor,
                $reason,
            );

            return $this->result($booking, $oldTotal, (int) $booking->total_amount, $log);
        });
    }

    /**
     * Compute what a change would do without writing any of it, so the
     * desk can show the difference in total, the balance still owed and
     * any overpayment warning before staff ask the guest (BR-06,
     * docs/seq_perubahan_booking.mermaid).
     *
     * Pass whichever of checkIn, checkOut, room or guestCount applies;
     * the rest keep their current values. Exactly one of those three
     * kinds must be given - handing over more than one is refused rather
     * than silently answered for just one of them. The rules are
     * evaluated exactly as they will be at save time, so a preview that
     * reports a conflict is a change that would be refused.
     *
     * @throws BookingException when the role or the status refuses the change
     *                          or when the call asks for several changes at once
     * @throws AvailabilityException when a night of the change is taken
     */
    public function preview(
        Booking $booking,
        User $actor,
        CarbonInterface|string|null $checkIn = null,
        CarbonInterface|string|null $checkOut = null,
        ?Room $room = null,
        ?int $guestCount = null,
    ): BookingChangeResult {
        $this->assertMayChange($actor);

        // One preview answers one question. Handing it dates *and* a
        // room would let the match below pick one and quietly drop the
        // other, so the desk would be shown a figure for a change
        // nobody asked for (review finding N5).
        $requested = array_values(array_filter([
            ($checkIn !== null || $checkOut !== null) ? self::DATES_CHANGED : null,
            $room !== null ? self::ROOM_CHANGED : null,
            $guestCount !== null ? self::GUEST_COUNT_CHANGED : null,
        ]));

        $changeType = match (true) {
            $requested === [] => throw new BookingException(
                'A preview needs something to change: dates, a room or the guest count.',
            ),
            count($requested) > 1 => throw new BookingException(
                'A preview takes one change at a time: dates, a room or the guest count; preview the others separately.',
            ),
            default => $requested[0],
        };

        return DB::transaction(function () use (
            $booking,
            $actor,
            $changeType,
            $checkIn,
            $checkOut,
            $room,
            $guestCount,
        ): BookingChangeResult {
            /** @var Booking $booking re-read so the preview sees the committed row */
            $booking = $this->lockBooking($booking);

            $from = $checkIn === null ? $this->toDay($booking->check_in_date) : $this->toDay($checkIn);
            $to = $checkOut === null ? $this->toDay($booking->check_out_date) : $this->toDay($checkOut);

            $this->assertStatusAllows($booking, $changeType, $changeType === self::DATES_CHANGED ? $from : null);

            $oldTotal = (int) $booking->total_amount;
            $newTotal = match ($changeType) {
                self::DATES_CHANGED => $this->previewDates($booking, $from, $to, $actor),
                self::ROOM_CHANGED => $this->previewRoom($booking, $room, $actor),
                self::GUEST_COUNT_CHANGED => $this->previewGuestCount($booking, $guestCount, $actor),
            };

            // Only the in-memory copy is adjusted: this transaction
            // writes nothing, so the stored total is untouched and the
            // payment picture below is computed against the total the
            // change would produce.
            $booking->setAttribute('total_amount', $newTotal);

            return $this->result($booking, $oldTotal, $newTotal, null);
        });
    }

    /**
     * The new total a date change would produce, validated but not
     * written.
     */
    private function previewDates(Booking $booking, CarbonImmutable $from, CarbonImmutable $to, User $actor): int
    {
        $this->assertStayStillValid($booking, $from, $to, $this->lockBookingRooms($booking), $actor, self::PREVIEW_REASON);

        $newTotal = (int) $booking->total_amount;

        foreach ($booking->bookingItems()->get() as $item) {
            $diff = $this->nightDiff($item, $from, $to);

            $newTotal -= $diff['released'];
            $newTotal += $diff['added'];
        }

        return $newTotal;
    }

    /** A move inside the same room type changes no price (CH-08). */
    private function previewRoom(Booking $booking, ?Room $room, User $actor): int
    {
        if ($room === null) {
            throw new BookingException('A room preview needs the room to move to.');
        }

        $item = $this->singleItem($booking);

        $rooms = $this->lockRooms([
            ...$booking->bookingItems()->pluck('room_id')->all(),
            $room->id,
        ]);

        $target = collect($rooms)->firstWhere('id', $room->id);

        if ($item->room_id !== $target->id && $item->room->room_type_id !== $target->room_type_id) {
            throw new BookingException(
                'A booking may only be moved to another room of the same room type; '
                .'moving between room types reprices the nights and is not part of this release (BR-06).',
            );
        }

        $this->assertStayStillValid(
            $booking,
            $this->toDay($booking->check_in_date),
            $this->toDay($booking->check_out_date),
            [$target],
            $actor,
            self::PREVIEW_REASON,
        );

        return (int) $booking->total_amount;
    }

    /** Changing the guest count changes no price, but must fit the rooms. */
    private function previewGuestCount(Booking $booking, ?int $guestCount, User $actor): int
    {
        if ($guestCount === null) {
            throw new BookingException('A guest count preview needs the new count.');
        }

        if ($guestCount < 1) {
            throw new BookingException('guest_count must be at least 1 (BR-01.2).');
        }

        $capacity = (int) collect($this->lockBookingRooms($booking))
            ->sum(fn (Room $room) => $room->roomType->capacity);

        if ($guestCount > $capacity) {
            throw new BookingException(sprintf(
                'guest_count %d exceeds the capacity of the booked rooms (%d) (BR-01.2).',
                $guestCount,
                $capacity,
            ));
        }

        return (int) $booking->total_amount;
    }

    /**
     * BR-01 layer 2: the availability rules run again, now that the room
     * rows are locked, and the booking's own nights are excluded because
     * a change may keep, extend or move them (BR-06). The minimum nights
     * of the new check-in night are re-read from the rate in force at
     * the same time (BR-11.8).
     *
     * BR-01.4 (no past check-in) only governs *choosing* check_in_date,
     * so it runs just when the change actually moves that date. A stay
     * in progress has a check-in in the past by definition and BR-06
     * still lets the owner, the manager and the receptionist extend,
     * shorten or move it - applying the gate unconditionally blocked the
     * main use of US-16 (review finding N1).
     *
     * @param  array<int, Room>  $rooms
     *
     * @throws AvailabilityException when a night of the new stay is taken
     * @throws PricingException when the stay is below the minimum nights
     *                          and the actor may not override it
     */
    private function assertStayStillValid(
        Booking $booking,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $rooms,
        User $actor,
        string $reason,
    ): void {
        // The booking is re-read under its row lock and is only written
        // after this returns, so check_in_date here is still the stored
        // one - the right value to compare against.
        $checkInMoved = $from->toDateString() !== $booking->check_in_date->toDateString();

        $this->availability->assertValidStay(
            $booking->property,
            $from,
            $to,
            $booking->guest_count,
            $rooms,
            $actor,
            $reason,
            $booking->id,
            enforcePastCheckInRule: $checkInMoved,
        );

        $nights = (int) $from->diffInDays($to, false);

        foreach ($rooms as $room) {
            $this->pricing->assertMinimumNightsSatisfied($room->roomType, $from, $nights, $actor, $reason);
        }
    }

    /**
     * Release the nights that fall out of the stay and add the ones that
     * come in. The release runs first on purpose: the partial unique
     * index on (room_id, stay_date) WHERE is_active would otherwise still
     * see the old active row while the new one is inserted, which is how
     * a shift that overlaps itself would be refused by the database.
     */
    private function applyNightDiff(BookingItem $item, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $diff = $this->nightDiff($item, $from, $to);

        foreach ($diff['release'] as $night) {
            $night->update(['is_active' => false]);
        }

        foreach ($diff['insert'] as $night) {
            BookingNight::create($night);
        }
    }

    /**
     * Split the stay of one item into the nights that go, the nights
     * that arrive, and what each side is worth. A night that stays in
     * the range is left completely alone, which is what keeps its price
     * locked (K3, BR-11.7).
     *
     * @return array{
     *     release: array<int, BookingNight>,
     *     insert: array<int, array<string, mixed>>,
     *     released: int,
     *     added: int,
     * }
     */
    private function nightDiff(BookingItem $item, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $room = $item->room;
        $wanted = $this->nightsBetween($from, $to);

        $held = [];

        foreach ($item->bookingNights()->where('is_active', true)->get() as $night) {
            $held[$night->stay_date->toDateString()] = $night;
        }

        $release = [];
        $released = 0;

        foreach ($held as $date => $night) {
            if (! in_array($date, $wanted, true)) {
                $release[] = $night;
                $released += (int) $night->price;
            }
        }

        $insert = [];
        $added = 0;

        foreach ($wanted as $date) {
            if (isset($held[$date])) {
                continue;
            }

            $rate = $this->pricing->nightlyRateFor($room->roomType, $date);

            // pricePerNight carries the canonical refusal when no rate
            // matches the night (BR-11.4); calling it keeps one message.
            $price = $rate === null
                ? $this->pricing->pricePerNight($room->roomType, $date)
                : (int) $rate->price;

            $insert[] = [
                'booking_item_id' => $item->id,
                'room_id' => $room->id,
                'rate_id' => $rate?->id,
                'stay_date' => $date,
                'price' => $price,
                'is_active' => true,
            ];

            $added += $price;
        }

        return compact('release', 'insert', 'released', 'added');
    }

    /**
     * Recompute booking_items.subtotal and bookings.total_amount from the
     * nights and the charges, which stay the single source of truth
     * (BR-12.2, PR-10).
     */
    private function recalculate(Booking $booking): void
    {
        foreach ($booking->bookingItems()->with('bookingNights')->get() as $item) {
            $subtotal = (int) $item->bookingNights
                ->filter(fn (BookingNight $night) => $night->is_active)
                ->sum('price');

            $item->update(['subtotal' => $subtotal]);
        }

        $this->pricing->recalculateTotal($booking);
    }

    /**
     * The one row this change leaves in booking_change_logs (CH-06): the
     * type, the value before, the value after, the reason and the person
     * who did it.
     *
     * @param  array<string, mixed>  $oldValue
     * @param  array<string, mixed>  $newValue
     */
    private function writeLog(
        Booking $booking,
        string $changeType,
        array $oldValue,
        array $newValue,
        User $actor,
        string $reason,
    ): BookingChangeLog {
        return BookingChangeLog::create([
            'booking_id' => $booking->id,
            'changed_by_user_id' => $actor->id,
            'change_type' => $changeType,
            'old_value' => json_encode($oldValue, JSON_UNESCAPED_SLASHES),
            'new_value' => json_encode($newValue, JSON_UNESCAPED_SLASHES),
            'reason' => $reason,
        ]);
    }

    /** Assemble the result, including the BR-12.3 overpayment warning. */
    private function result(Booking $booking, int $oldTotal, int $newTotal, ?BookingChangeLog $log): BookingChangeResult
    {
        $payments = $this->payments->summary($booking);

        $warnings = $payments->overpaid ? [BookingChangeResult::WARN_OVERPAID] : [];

        return new BookingChangeResult(
            booking: $booking,
            oldTotal: $oldTotal,
            newTotal: $newTotal,
            payments: $payments,
            warnings: $warnings,
            log: $log,
        );
    }

    /**
     * Re-read the booking under a row lock, the way a status transition
     * does, so two staff members editing the same booking at the same
     * moment work from the committed row instead of overwriting each
     * other.
     *
     * @throws ModelNotFoundException
     */
    private function lockBooking(Booking $booking): Booking
    {
        /** @var Booking $locked re-read under the row lock */
        $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

        return $locked;
    }

    /**
     * Lock every room the booking holds, ordered by id so two
     * transactions touching the same rooms always wait in the same
     * order and cannot deadlock (BR-01 layer 2).
     *
     * @return array<int, Room>
     */
    private function lockBookingRooms(Booking $booking): array
    {
        return $this->lockRooms($booking->bookingItems()->pluck('room_id')->all());
    }

    /**
     * @param  array<int, int>  $roomIds
     * @return array<int, Room>
     */
    private function lockRooms(array $roomIds): array
    {
        return Room::query()
            ->whereIn('id', array_unique($roomIds))
            ->orderBy('id')
            ->with('roomType')
            ->lockForUpdate()
            ->get()
            ->all();
    }

    /**
     * BR-06 "Status yang boleh diubah": pending and confirmed take every
     * kind of change, checked_in may only be extended, shortened or
     * moved, and a final status is closed (BR-06 "Status akhir tidak
     * boleh diubah").
     *
     * @throws BookingException
     */
    private function assertStatusAllows(Booking $booking, string $changeType, ?CarbonImmutable $newCheckIn = null): void
    {
        $status = $booking->status;

        if (in_array($status, self::OPEN_STATUSES, true)) {
            return;
        }

        if ($status !== BookingStatus::CheckedIn) {
            throw new BookingException(sprintf(
                'A booking in %s status cannot be changed: that status is final (BR-06).',
                $status->value,
            ));
        }

        if ($changeType === self::GUEST_COUNT_CHANGED) {
            throw new BookingException(
                'A checked-in booking may only be extended, shortened or moved to another room (BR-06).',
            );
        }

        if ($changeType === self::DATES_CHANGED
            && $newCheckIn !== null
            && $newCheckIn->toDateString() !== $booking->check_in_date->toDateString()) {
            throw new BookingException(
                'A checked-in booking may not change its check-in date; extend or shorten the stay instead (BR-06).',
            );
        }
    }

    /**
     * extend() and shorten() promise a direction, and that promise must
     * hold against the booking as it sits behind the row lock. Doing it
     * here rather than in the public wrappers is what makes two staff
     * working on the same booking safe: the second one is measured
     * against the first one's committed dates and told plainly that
     * there is nothing to extend, instead of silently reverting the
     * other's change (review finding N2).
     *
     * @throws BookingException
     */
    private function assertCheckOutMoves(Booking $booking, CarbonImmutable $to, string $direction): void
    {
        $current = $this->toDay($booking->check_out_date);

        if ($direction === self::MOVES_LATER) {
            if ($to <= $current) {
                throw new BookingException(
                    'An extension must move the check-out date later; use shorten() to move it earlier.',
                );
            }

            return;
        }

        if ($to >= $current) {
            throw new BookingException(
                'A shortening must move the check-out date earlier; use extend() to move it later.',
            );
        }

        if ($to <= $this->toDay($booking->check_in_date)) {
            throw new BookingException('A booking must keep at least one night (BR-01.2).');
        }
    }

    /**
     * AGENTS.md rule 3 and 8: the allow-list is written against the
     * canonical RoleName values, so a role that is not one of the three
     * is refused instead of being missed by a deny-list.
     *
     * @throws BookingException
     */
    private function assertMayChange(User $actor): void
    {
        // users.role_id is NOT NULL with a foreign key to roles, so a
        // persisted user always resolves a role; there is no null to
        // guard against here.
        $roleName = $actor->role->name;
        $role = RoleName::tryFrom($roleName);

        if ($role === null || ! in_array($role, self::CHANGE_ROLES, true)) {
            throw new BookingException(sprintf(
                'Role "%s" may not change a booking (BR-06 allows the owner, the manager and the receptionist).',
                $roleName,
            ));
        }
    }

    /**
     * K4: the reason is mandatory for every role, not only for the
     * receptionist.
     *
     * @throws BookingException
     */
    private function assertReasonGiven(string $reason): void
    {
        if (trim($reason) === '') {
            throw new BookingException('A reason is required for every booking change (K4, BR-06).');
        }
    }

    /**
     * A room move in this release applies to a booking that holds one
     * room; changing the rooms of a multi-room booking means adding or
     * removing a room, which the T-013 scope leaves out.
     *
     * @throws BookingException
     */
    private function singleItem(Booking $booking): BookingItem
    {
        $items = $booking->bookingItems()->get();

        if ($items->count() !== 1) {
            throw new BookingException(
                'This release only moves a booking that holds a single room; '
                .'changing the rooms of a multi-room booking is outside the T-013 scope (BR-06).',
            );
        }

        return $items->first();
    }

    /**
     * Whether the failure is the partial unique index refusing a second
     * active night for the same room and date (BR-01 layer 3), rather
     * than any other unique constraint. The row lock makes this
     * practically unreachable, but a refusal that does slip through must
     * still read as an availability problem instead of a driver error,
     * exactly as it does in BookingService.
     *
     * Returns an Exception rather than a QueryException because the
     * translated branch hands back an AvailabilityException, which only
     * shares Exception with the driver's own error - declaring
     * QueryException here would raise a TypeError at the very moment
     * this safety net is needed.
     */
    private function translateDuplicateNight(QueryException $e): Exception
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $message = (string) ($e->errorInfo[2] ?? $e->getMessage());

        if ($sqlState === '23505' && str_contains($message, 'booking_nights_room_stay_active_unique')) {
            return new AvailabilityException(
                'One of the rooms was just taken for those dates; please re-check availability.',
                previous: $e,
            );
        }

        return $e;
    }

    /** @return array{check_in_date: string, check_out_date: string} */
    private function datesOf(Booking $booking): array
    {
        return [
            'check_in_date' => $booking->check_in_date->toDateString(),
            'check_out_date' => $booking->check_out_date->toDateString(),
        ];
    }

    /** @return array{room_id: int, room_number: string} */
    private function roomOf(Room $room): array
    {
        return [
            'room_id' => (int) $room->id,
            'room_number' => (string) $room->room_number,
        ];
    }

    /**
     * The nights of [from, to): the check-out day is not a night, the
     * same convention AvailabilityService and BookingService use.
     *
     * @return array<int, string>
     */
    private function nightsBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dates = [];

        for ($night = $from; $night < $to; $night = $night->addDay()) {
            $dates[] = $night->toDateString();
        }

        return $dates;
    }

    private function toDay(CarbonInterface|string $day): CarbonImmutable
    {
        $date = $day instanceof CarbonInterface
            ? CarbonImmutable::instance($day)
            : CarbonImmutable::parse($day);

        return $date->startOfDay();
    }
}
