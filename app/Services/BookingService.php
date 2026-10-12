<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\HousekeepingStatus as Cleanliness;
use App\Enums\PaymentOption;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RoleName;
use App\Exceptions\AvailabilityException;
use App\Exceptions\BookingException;
use App\Exceptions\PricingException;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingNight;
use App\Models\BookingSource;
use App\Models\BookingStatusLog;
use App\Models\Guest;
use App\Models\HousekeepingStatus;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Support\BookingCode;
use App\Support\BookingTransitionResult;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Booking writes: creating a booking atomically (T-010) and moving one
 * through its status transitions (T-011).
 *
 * Creation is the one write that must never leave a room half-sold, so
 * every row is written inside a single DB::transaction that first takes
 * a row lock on each room (ordered by id to avoid deadlocks) and then
 * re-runs the availability rules. That recheck under the lock is layer 2
 * of the anti double booking defence (BR-01.5): layer 1 is
 * AvailabilityService's plain queries, layer 3 is the partial unique
 * index on booking_nights. The booking code (BR-14), the pending expiry
 * (BR-13) and the first status log are decided here as well.
 *
 * This class is also the only writer of bookings.status (AGENTS.md rule
 * 3). Every transition goes through transition(), which looks the row up
 * in TRANSITIONS below, so the matrix of Business Rules section 5 is the
 * single place that decides who may do what. tests/Feature/Architecture/
 * BookingStatusOwnershipTest.php keeps that ownership from drifting.
 */
class BookingService
{
    /**
     * The transition matrix of docs/business_rules_kamarin.md section 5,
     * written out as data rather than as nested ifs (T-011).
     *
     * Shape: [from status] => [to status] => rule, where a rule holds
     *
     *   'roles'    the RoleName values allowed to run it (staff rows)
     *   'system'   true for the one row the scheduler runs; it takes no actor
     *   'requires' the condition checked before anything is written
     *   'warns'    conditions reported but never blocking (BR-04.2, BR-07.5)
     *   'effects'  what changes besides bookings.status itself
     *
     * A from-status with no entry at all - checked_out, cancelled,
     * expired and no_show - is final: the lookup misses and the
     * transition is refused, so "status akhir tidak bisa dibuka kembali"
     * is a property of this table instead of a branch somewhere.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    private const TRANSITIONS = [
        'pending' => [
            'confirmed' => [
                'roles' => [RoleName::Owner, RoleName::Manager, RoleName::Receptionist],
                'requires' => 'deposit',
                'warns' => [],
                'effects' => ['clear_expires_at'],
            ],
            'cancelled' => [
                'roles' => [RoleName::Owner, RoleName::Manager, RoleName::Receptionist],
                'requires' => 'reason',
                'warns' => [],
                'effects' => ['release_nights', 'mark_cancelled'],
            ],
            'expired' => [
                'system' => true,
                'requires' => 'expiry_deadline',
                'warns' => [],
                'effects' => ['release_nights'],
            ],
        ],
        'confirmed' => [
            'cancelled' => [
                'roles' => [RoleName::Owner, RoleName::Manager, RoleName::Receptionist],
                'requires' => 'reason',
                'warns' => [],
                'effects' => ['release_nights', 'mark_cancelled'],
            ],
            'checked_in' => [
                'roles' => [RoleName::Owner, RoleName::Manager, RoleName::Receptionist],
                'requires' => 'check_in_window',
                'warns' => ['housekeeping', 'balance'],
                'effects' => ['mark_checked_in'],
            ],
            'no_show' => [
                'roles' => [RoleName::Owner, RoleName::Manager, RoleName::Receptionist],
                'requires' => 'no_show_cutoff',
                'warns' => [],
                'effects' => ['release_nights'],
            ],
        ],
        'checked_in' => [
            'checked_out' => [
                'roles' => [RoleName::Owner, RoleName::Manager, RoleName::Receptionist],
                'requires' => 'none',
                'warns' => ['balance'],
                'effects' => ['mark_checked_out', 'rooms_dirty'],
            ],
        ],
    ];

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
                    'booking_code' => BookingCode::generate(),
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
     *
     * Matching uses the normalised email (BR Data dan privasi, butir 3):
     * edge whitespace trimmed and letters lower-cased, both when it is
     * stored and when it is looked up. The phone number is never a match
     * key here because a number may be shared; searching by phone is a
     * separate staff feature.
     */
    public function findOrCreateGuest(string $name, ?string $email = null, ?string $phone = null): Guest
    {
        $email = $this->normalizeEmail($email);

        if ($email !== null) {
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
     * pending -> confirmed (Business Rules section 5).
     *
     * For a bank transfer the required deposit must be covered by a
     * verified payment (BR-03); only owner and manager may waive that,
     * and only with a reason (K6). Confirmation also drops expires_at,
     * so the booking can never expire afterwards (BR-13.3).
     *
     * @throws BookingException when the row, the actor or the deposit
     *                          requirement is not satisfied
     */
    public function confirm(Booking $booking, ?User $actor = null, ?string $reason = null): BookingTransitionResult
    {
        return $this->transition($booking, BookingStatus::Confirmed, $actor, $reason);
    }

    /**
     * pending|confirmed -> cancelled (Business Rules section 5, BR-05).
     *
     * The reason is mandatory for every role, not only for the
     * receptionist the matrix names. Nights are released so the room can
     * be sold again; a refund, when one is due, is recorded separately
     * as a payment (BR-05.3, T-012).
     *
     * @throws BookingException when the reason is missing
     */
    public function cancel(Booking $booking, ?User $actor = null, ?string $reason = null): BookingTransitionResult
    {
        return $this->transition($booking, BookingStatus::Cancelled, $actor, $reason);
    }

    /**
     * confirmed -> checked_in (Business Rules section 5, BR-02, BR-04).
     *
     * A room that is not ready and a balance still due are warnings that
     * travel back in the result; neither blocks the check-in (BR-04.2,
     * BR-07.5, ST-09).
     *
     * @throws BookingException before the stay starts or once the
     *                          no-show cutoff has passed
     */
    public function checkIn(Booking $booking, ?User $actor = null): BookingTransitionResult
    {
        return $this->transition($booking, BookingStatus::CheckedIn, $actor, null);
    }

    /**
     * checked_in -> checked_out (Business Rules section 5, BR-07.4).
     *
     * Stamps checked_out_at and turns every room of the booking dirty.
     * A balance still due is a warning, never a refusal (BR-04.2).
     */
    public function checkOut(Booking $booking, ?User $actor = null): BookingTransitionResult
    {
        return $this->transition($booking, BookingStatus::CheckedOut, $actor, null);
    }

    /**
     * confirmed -> no_show (BR-08). Only once the property's
     * no_show_cutoff_time on the check-in date has been reached, and it
     * releases every night so the room goes back on sale.
     *
     * @throws BookingException before the cutoff
     */
    public function markNoShow(Booking $booking, ?User $actor = null): BookingTransitionResult
    {
        return $this->transition($booking, BookingStatus::NoShow, $actor, null);
    }

    /**
     * pending -> expired (BR-13). The system row of the matrix: it takes
     * no actor at all, which is why the signature has none, and the log
     * it writes carries an empty changed_by_user_id (EX-06). The expiry
     * scheduler of T-014 is the intended caller.
     *
     * @throws BookingException before the deadline, or for a booking
     *                          that never had one (K5)
     */
    public function expire(Booking $booking): BookingTransitionResult
    {
        return $this->transition($booking, BookingStatus::Expired, null, null);
    }

    /**
     * Run one row of the transition matrix inside a transaction.
     *
     * The booking is re-read under a row lock first, so two members of
     * staff acting at the same moment both work from the committed
     * status and the second one is refused by the matrix instead of
     * overwriting the first. Order matters: the matrix row, then the
     * actor, then the condition, then the effects and exactly one log
     * line. A failure at any point rolls the whole attempt back, which
     * is what keeps a booking from being half-transitioned.
     *
     * @throws BookingException when the matrix, the actor or the
     *                          condition refuses the move
     */
    private function transition(Booking $booking, BookingStatus $to, ?User $actor, ?string $reason): BookingTransitionResult
    {
        return DB::transaction(function () use ($booking, $to, $actor, $reason): BookingTransitionResult {
            $id = $booking->getKey();

            /** @var Booking $booking re-read under the row lock */
            $booking = Booking::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            $from = $booking->status;
            $rule = self::TRANSITIONS[$from->value][$to->value] ?? null;

            if ($rule === null) {
                throw new BookingException(sprintf(
                    'A booking cannot go from %s to %s: that row is not in the transition matrix (Business Rules section 5).',
                    $from->value,
                    $to->value,
                ));
            }

            $this->assertActorMayRun($rule, $from, $to, $actor);
            $this->assertCondition($rule['requires'], $booking, $actor, $reason);

            // Collected before the effects run, because the balance and
            // the cleanliness warnings describe the booking as it was
            // handed to us.
            $warnings = $this->collectWarnings($rule['warns'], $booking);

            $booking->status = $to;

            foreach ($rule['effects'] as $effect) {
                $this->applyEffect($effect, $booking, $actor, $reason);
            }

            $booking->save();

            BookingStatusLog::create([
                'booking_id' => $booking->id,
                'changed_by_user_id' => $actor?->id,
                'old_status' => $from,
                'new_status' => $to,
                'note' => $this->statusLogNote($to, $reason),
            ]);

            return new BookingTransitionResult($booking->refresh(), $warnings);
        });
    }

    /**
     * Whether this actor may run this row of the matrix. Housekeeping
     * appears in no row at all, so it is refused everywhere; a user
     * whose role is outside the canonical four is refused the same way.
     *
     * @param  array<string, mixed>  $rule
     *
     * @throws BookingException
     */
    private function assertActorMayRun(array $rule, BookingStatus $from, BookingStatus $to, ?User $actor): void
    {
        if (($rule['system'] ?? false) === true) {
            if ($actor !== null) {
                throw new BookingException(sprintf(
                    'Only the system may move a booking from %s to %s; a person cannot stand in for it.',
                    $from->value,
                    $to->value,
                ));
            }

            return;
        }

        $role = $actor === null ? null : RoleName::tryFrom((string) $actor->role?->name);

        if ($role === null || ! in_array($role, $rule['roles'], true)) {
            throw new BookingException(sprintf(
                'Role "%s" may not move a booking from %s to %s (transition matrix, Business Rules section 5).',
                $actor?->role?->name ?? 'nobody',
                $from->value,
                $to->value,
            ));
        }
    }

    /**
     * Dispatch the condition named by the matrix row.
     *
     * @throws BookingException
     */
    private function assertCondition(string $requires, Booking $booking, ?User $actor, ?string $reason): void
    {
        match ($requires) {
            'none' => null,
            'reason' => $this->assertReasonGiven($reason),
            'deposit' => $this->assertDepositCovered($booking, $actor, $reason),
            'check_in_window' => $this->assertCheckInWindow($booking),
            'no_show_cutoff' => $this->assertNoShowCutoff($booking),
            'expiry_deadline' => $this->assertExpiryDeadline($booking),
            default => throw new LogicException("Unknown transition condition \"{$requires}\"."),
        };
    }

    /**
     * BR-05.1: the cancellation reason is mandatory.
     *
     * @throws BookingException
     */
    private function assertReasonGiven(?string $reason): void
    {
        if (trim((string) $reason) === '') {
            throw new BookingException('A reason is required for this transition (BR-05.1).');
        }
    }

    /**
     * BR-03 and K6: confirming a bank transfer needs a verified deposit
     * of at least total_amount x deposit_percent / 100. Only owner and
     * manager may waive it, and only with a reason; a receptionist never
     * may. Other payment options need no deposit at all (BR-03.2).
     *
     * @throws BookingException
     */
    private function assertDepositCovered(Booking $booking, ?User $actor, ?string $reason): void
    {
        if ($booking->payment_option !== PaymentOption::BankTransfer) {
            return;
        }

        $required = (int) round(
            $booking->total_amount * (int) $booking->property->deposit_percent / 100,
        );
        $verified = $this->verifiedDeposit($booking);

        if ($verified >= $required) {
            return;
        }

        $role = $actor === null ? null : RoleName::tryFrom((string) $actor->role?->name);

        if ($role !== RoleName::Owner && $role !== RoleName::Manager) {
            throw new BookingException(sprintf(
                'Confirming a bank transfer needs a verified deposit of %d (BR-03); only %d is recorded.',
                $required,
                $verified,
            ));
        }

        if (trim((string) $reason) === '') {
            throw new BookingException('Waiving the deposit requirement needs a reason (K6).');
        }
    }

    /**
     * BR-03.3: a deposit only counts while its payment is verified.
     *
     * T-011 deliberately keeps this as one plain query so the transition
     * guard has no dependency on a payment service. T-012 moves it into
     * PaymentService, which then becomes the single definition of
     * "DP terverifikasi" (noted in docs/STATUS.md).
     */
    private function verifiedDeposit(Booking $booking): int
    {
        return (int) Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', PaymentStatus::Verified->value)
            ->whereIn('type', [PaymentType::Deposit->value, PaymentType::Settlement->value])
            ->sum('amount');
    }

    /**
     * Matrix row confirmed -> checked_in: the stay must already have
     * started (BR-02.2 allows an early check-in on the day itself, not
     * before it) and the no-show cutoff must not have passed yet, or the
     * booking should be a no-show instead.
     *
     * @throws BookingException
     */
    private function assertCheckInWindow(Booking $booking): void
    {
        $checkInDay = CarbonImmutable::parse($booking->check_in_date->toDateString());
        $today = CarbonImmutable::parse(now()->toDateString());

        if ($checkInDay->gt($today)) {
            throw new BookingException(sprintf(
                'Check-in is only allowed on or after the check-in date %s.',
                $checkInDay->toDateString(),
            ));
        }

        if (! now()->lt($this->noShowCutoff($booking))) {
            throw new BookingException(
                'The no-show cutoff has passed; record the booking as a no-show instead (BR-08.1).',
            );
        }
    }

    /**
     * BR-08.1 and K7: the cutoff is check_in_date plus the property's
     * no_show_cutoff_time, not a hard-coded hour.
     *
     * @throws BookingException
     */
    private function assertNoShowCutoff(Booking $booking): void
    {
        $cutoff = $this->noShowCutoff($booking);

        if (now()->lt($cutoff)) {
            throw new BookingException(sprintf(
                'A no-show can only be recorded at or after the no-show cutoff (%s, BR-08.1).',
                $cutoff->toDateTimeString(),
            ));
        }
    }

    /**
     * BR-13.2 and K5: only a booking that has a deadline can pass it, so
     * a booking created by staff (expires_at = null) never expires.
     *
     * @throws BookingException
     */
    private function assertExpiryDeadline(Booking $booking): void
    {
        $deadline = $this->asMoment($booking->expires_at);

        if ($deadline === null) {
            throw new BookingException('Only a booking with an expiry deadline can expire (K5, BR-13.1).');
        }

        if (now()->lt($deadline)) {
            throw new BookingException('The expiry deadline has not been reached yet (BR-13.2).');
        }
    }

    /** The instant on the check-in date at which no-show becomes valid. */
    private function noShowCutoff(Booking $booking): CarbonImmutable
    {
        $clock = explode(':', (string) $booking->property->no_show_cutoff_time);

        return CarbonImmutable::parse(sprintf(
            '%s %02d:%02d',
            $booking->check_in_date->toDateString(),
            (int) ($clock[0] ?? 0),
            (int) ($clock[1] ?? 0),
        ));
    }

    /**
     * The timestamp columns of bookings are handed back from PostgreSQL
     * as plain strings - they are not in the model's cast list - so any
     * comparison goes through here instead of assuming a Carbon.
     */
    private function asMoment(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse((string) $value);
    }

    /**
     * The non-blocking warnings named by the matrix row.
     *
     * @param  array<int, string>  $warns
     * @return array<int, string>
     */
    private function collectWarnings(array $warns, Booking $booking): array
    {
        $warnings = [];

        foreach ($warns as $warn) {
            $collected = match ($warn) {
                'balance' => $this->balanceWarnings($booking),
                'housekeeping' => $this->housekeepingWarnings($booking),
                default => throw new LogicException("Unknown transition warning \"{$warn}\"."),
            };

            foreach ($collected as $warning) {
                $warnings[] = $warning;
            }
        }

        return $warnings;
    }

    /**
     * BR-04.2: sisa tagihan still owed. Read through BR-12's "terbayar"
     * (verified deposit and settlement minus refunds).
     *
     * @return array<int, string>
     */
    private function balanceWarnings(Booking $booking): array
    {
        $balance = $booking->total_amount - $this->netPaid($booking);

        if ($balance <= 0) {
            return [];
        }

        return [sprintf('An outstanding balance of %d rupiah is still due (BR-04.2).', $balance)];
    }

    /**
     * BR-12 "Terbayar": verified deposit and settlement payments, minus
     * what has been refunded.
     *
     * Same T-011 note as verifiedDeposit(): a plain query for now,
     * moved into PaymentService with it in T-012.
     */
    private function netPaid(Booking $booking): int
    {
        $settled = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', PaymentStatus::Verified->value)
            ->whereIn('type', [PaymentType::Deposit->value, PaymentType::Settlement->value])
            ->sum('amount');

        $refunded = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('type', PaymentType::Refund->value)
            ->sum('amount');

        return (int) $settled - (int) $refunded;
    }

    /**
     * BR-07.5: a room that is not ready is announced, never refused.
     * A room with no housekeeping row at all counts as not ready.
     *
     * @return array<int, string>
     */
    private function housekeepingWarnings(Booking $booking): array
    {
        $roomIds = $booking->bookingItems()->pluck('room_id')->unique()->values();

        if ($roomIds->isEmpty()) {
            return [];
        }

        $numbers = Room::query()->whereIn('id', $roomIds)->pluck('room_number', 'id');
        $statuses = HousekeepingStatus::query()->whereIn('room_id', $roomIds)->get()->keyBy('room_id');

        $warnings = [];

        foreach ($roomIds as $roomId) {
            if ($statuses->get($roomId)?->status !== Cleanliness::Ready) {
                $warnings[] = sprintf(
                    'Room %s is not ready yet; housekeeping will be told (BR-07.5).',
                    $numbers->get($roomId) ?? $roomId,
                );
            }
        }

        return $warnings;
    }

    /**
     * Apply one effect named by the matrix row. Booking-column effects
     * only change the in-memory attributes and are persisted by the
     * single save() that follows; the other two touch other tables.
     *
     * @throws LogicException when the table names an unknown effect
     */
    private function applyEffect(string $effect, Booking $booking, ?User $actor, ?string $reason): void
    {
        match ($effect) {
            'clear_expires_at' => $booking->setAttribute('expires_at', null),
            'mark_cancelled' => $booking->fill([
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]),
            'mark_checked_in' => $booking->setAttribute('checked_in_at', now()),
            'mark_checked_out' => $booking->setAttribute('checked_out_at', now()),
            'release_nights' => $this->releaseNights($booking),
            'rooms_dirty' => $this->markRoomsDirty($booking, $actor),
            default => throw new LogicException("Unknown transition effect \"{$effect}\"."),
        };
    }

    /**
     * Deactivate every night of the booking (BR-05, BR-08, BR-13.2),
     * which is what frees the room for sale again: the partial unique
     * index only guards rows whose is_active is true (BR-01 layer 3).
     */
    private function releaseNights(Booking $booking): void
    {
        BookingNight::query()
            ->whereIn('booking_item_id', $booking->bookingItems()->pluck('id'))
            ->update(['is_active' => false]);
    }

    /**
     * BR-07.4: every room of the booking turns dirty on check-out. One
     * row per room, so this creates the row when the room never had one.
     */
    private function markRoomsDirty(Booking $booking, ?User $actor): void
    {
        foreach ($booking->bookingItems()->pluck('room_id')->unique() as $roomId) {
            HousekeepingStatus::query()->updateOrCreate(
                ['room_id' => $roomId],
                ['status' => Cleanliness::Dirty, 'updated_by_user_id' => $actor?->id],
            );
        }
    }

    /**
     * The one line written to booking_status_logs for a transition
     * (ST-07). The reason is carried in the note because the table has
     * no reason column of its own; cancellation_reason on the booking
     * remains the authoritative copy (BR-05).
     */
    private function statusLogNote(BookingStatus $to, ?string $reason): string
    {
        return match ($to) {
            BookingStatus::Confirmed => 'Booking confirmed.',
            BookingStatus::Cancelled => 'Cancelled: '.trim((string) $reason),
            BookingStatus::CheckedIn => 'Checked in.',
            BookingStatus::CheckedOut => 'Checked out.',
            BookingStatus::NoShow => 'Recorded as a no-show (BR-08).',
            BookingStatus::Expired => 'Expired past the deadline (BR-13.2).',
            BookingStatus::Pending => 'Booking created.',
        };
    }

    /**
     * The stored form of an email address: trim the edge whitespace and
     * lower-case it. A blank address becomes null, which is what the
     * nullable guests.email column is for (OTA bookings without one),
     * and null is never used as a match key.
     */
    private function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = trim($email);

        return $email === '' ? null : strtolower($email);
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
}
