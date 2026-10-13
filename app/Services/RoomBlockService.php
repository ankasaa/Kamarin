<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Exceptions\AvailabilityException;
use App\Exceptions\BookingException;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Manual room blocks (ERD v2.1, ROOM_BLOCKS; US-08).
 *
 * BR-01.6: the partial unique index on booking_nights only guards a
 * booking against another booking, so every clash between a booking and a
 * block - and between two blocks - has to be refused here, in the service.
 *
 * BR-01.5: creating a block is a writer of room placement exactly like
 * BookingService::createBooking and BookingChangeService, so it takes the
 * same lock - SELECT ... FOR UPDATE on the room rows in id order - and
 * re-checks underneath that lock.
 *
 * The range is half-open, [start_date, end_date): a block covers the nights
 * start_date .. end_date - 1, the same convention a booking uses, so a
 * check-out day is never blocked. The database CHECK
 * room_blocks_end_after_start makes a reversed range impossible.
 */
class RoomBlockService
{
    /**
     * AGENTS.md rules 3 and 8: the allow-list names the roles that may
     * block a room, written against the canonical RoleName values, so an
     * unrecognised role is refused instead of being missed by a deny-list.
     *
     * @var array<int, RoleName>
     */
    private const BLOCK_ROLES = [RoleName::Owner, RoleName::Manager];

    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * Block [startDate, endDate) on the room, recording who did it and why.
     *
     * @throws BookingException
     * @throws AvailabilityException
     */
    public function block(
        Room $room,
        CarbonInterface|string $startDate,
        CarbonInterface|string $endDate,
        string $reason,
        User $actor,
    ): RoomBlock {
        $this->assertMayBlock($actor);
        $this->assertReasonIsPresent($reason);

        $from = $this->toDay($startDate, 'start_date');
        $to = $this->toDay($endDate, 'end_date');

        if ($to <= $from) {
            throw new BookingException('end_date must be after start_date.');
        }

        return DB::transaction(function () use ($room, $from, $to, $reason, $actor): RoomBlock {
            // Layer 2 (BR-01.5): lock the room row, then re-check the clash
            // underneath. A concurrent transaction for the same room waits
            // here and sees the committed nights and blocks when it resumes.
            $lockedRoom = $this->lockRooms([$room->id])[0];

            $this->availability->assertRoomCanBeBlocked($lockedRoom, $from, $to);

            $block = RoomBlock::create([
                'room_id' => $lockedRoom->id,
                // The column is nullable in the schema, but a block made
                // through this service always records its actor (US-08).
                'created_by_user_id' => $actor->id,
                'start_date' => $from,
                'end_date' => $to,
                'reason' => $reason,
            ]);

            // BR-01.6: there is no database index guarding a block, so the
            // clash is verified once more after the write, still inside the
            // same transaction. If anything landed in the gap, this refuses
            // the block and the whole transaction - including that racing
            // row - is rolled back.
            $this->availability->assertRoomCanBeBlocked($lockedRoom, $from, $to, $block->getKey());

            return $block;
        });
    }

    /**
     * Remove a block and re-open the room for those nights.
     *
     * @throws BookingException
     */
    public function unblock(RoomBlock $block, User $actor): void
    {
        $this->assertMayBlock($actor);

        DB::transaction(function () use ($block): void {
            // Removing a block adds availability back, so it is serialised
            // against concurrent writers by the same room lock.
            $this->lockRooms([$block->room_id]);

            RoomBlock::query()->whereKey($block->getKey())->delete();
        });
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
     * @throws BookingException
     */
    private function assertMayBlock(User $actor): void
    {
        // users.role_id is NOT NULL with a foreign key to roles, so a
        // persisted user always resolves a role; there is no null to
        // guard against here.
        $roleName = $actor->role->name;
        $role = RoleName::tryFrom($roleName);

        if ($role === null || ! in_array($role, self::BLOCK_ROLES, true)) {
            throw new BookingException(sprintf(
                'Role "%s" may not block a room (BR-01 allows only the owner and the manager).',
                $roleName,
            ));
        }
    }

    /**
     * US-08 "Alasan blokir tercatat": a block has to be explainable later,
     * so the reason is required rather than silently stored blank.
     *
     * @throws BookingException
     */
    private function assertReasonIsPresent(string $reason): void
    {
        if (trim($reason) === '') {
            throw new BookingException('A reason is required to block a room.');
        }
    }

    /**
     * @throws BookingException
     */
    private function toDay(CarbonInterface|string $day, string $label): CarbonImmutable
    {
        try {
            $date = $day instanceof CarbonInterface
                ? CarbonImmutable::instance($day)
                : CarbonImmutable::parse($day);
        } catch (\Throwable) {
            throw new BookingException("{$label} is not a valid date.");
        }

        return $date->startOfDay();
    }
}
