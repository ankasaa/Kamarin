<?php

use App\Models\Amenity;
use App\Models\Booking;
use App\Models\BookingChangeLog;
use App\Models\BookingCharge;
use App\Models\BookingNight;
use App\Models\BookingStatusLog;
use App\Models\HousekeepingStatus;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Rate;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomType;
use App\Models\RoomTypeAmenity;
use App\Models\User;

// Every relation declared on the 19 ERD models is exercised with real
// rows; each assertion checks the loaded model actually is the one
// created for that foreign key.

it('loads the access relations: user with role and property', function () {
    $user = User::factory()->create([
        'property_id' => Property::factory(),
    ]);

    $loaded = User::with(['role', 'property'])->find($user->id);

    expect($loaded->role->id)->toBe($user->role_id)
        ->and($loaded->property->id)->toBe($user->property_id)
        ->and($loaded->role->users->pluck('id')->all())->toContain($user->id)
        ->and($loaded->property->users->pluck('id')->all())->toContain($user->id);
});

// Regression guard: role_id, property_id and is_active must be
// mass-assignable so User::create() in seeders and services never
// silently drops them (review finding on User #[Fillable]).
it('mass-assigns user foreign keys and is_active', function () {
    $role = Role::factory()->create(['name' => 'receptionist']);
    $property = Property::factory()->create();

    $user = User::create([
        'role_id' => $role->id,
        'property_id' => $property->id,
        'name' => 'Test User',
        'email' => 'mass-assign@example.test',
        'password' => 'secret-password',
        'is_active' => false,
    ]);

    expect($user->role_id)->toBe($role->id)
        ->and($user->property_id)->toBe($property->id)
        ->and($user->is_active)->toBeFalse()
        ->and(User::find($user->id)->role_id)->toBe($role->id);
});

it('loads the property catalog relations (room types, rooms, rates)', function () {
    $property = Property::factory()->create();
    $roomType = RoomType::factory()->create(['property_id' => $property->id]);
    $room = Room::factory()->create([
        'property_id' => $property->id,
        'room_type_id' => $roomType->id,
    ]);
    $rate = Rate::factory()->create([
        'property_id' => $property->id,
        'room_type_id' => $roomType->id,
    ]);

    $loaded = Property::with(['roomTypes', 'rooms', 'rates', 'bookings'])->find($property->id);

    expect($loaded->roomTypes->pluck('id')->all())->toBe([$roomType->id])
        ->and($loaded->rooms->pluck('id')->all())->toBe([$room->id])
        ->and($loaded->rates->pluck('id')->all())->toBe([$rate->id])
        ->and($loaded->bookings->pluck('id')->all())->toBe([]); // no bookings yet

    // A booking created under the property shows up in the relation.
    $booking = Booking::factory()->create(['property_id' => $property->id]);
    $loaded = Property::with('bookings')->find($property->id);

    expect($loaded->bookings->pluck('id')->all())->toBe([$booking->id]);

    $type = RoomType::with(['property', 'rooms', 'rates'])->find($roomType->id);

    expect($type->property->id)->toBe($property->id)
        ->and($type->rooms->pluck('id')->all())->toBe([$room->id])
        ->and($type->rates->pluck('id')->all())->toBe([$rate->id]);

    $loadedRoom = Room::with(['property', 'roomType'])->find($room->id);

    expect($loadedRoom->property->id)->toBe($property->id)
        ->and($loadedRoom->roomType->id)->toBe($roomType->id);
});

it('loads the amenity relations through the pivot', function () {
    $pivot = RoomTypeAmenity::factory()->create();

    $type = RoomType::with('amenities')->find($pivot->room_type_id);
    $amenity = Amenity::with('roomTypes')->find($pivot->amenity_id);

    expect($type->amenities->pluck('id')->all())->toBe([$pivot->amenity_id])
        ->and($amenity->roomTypes->pluck('id')->all())->toBe([$pivot->room_type_id])
        ->and($pivot->roomType->id)->toBe($pivot->room_type_id)
        ->and($pivot->amenity->id)->toBe($pivot->amenity_id);
});

it('loads the housekeeping and room block relations', function () {
    $room = Room::factory()->create();
    $user = User::factory()->create();

    $housekeeping = HousekeepingStatus::factory()->create([
        'room_id' => $room->id,
        'updated_by_user_id' => $user->id,
    ]);
    $block = RoomBlock::factory()->create([
        'room_id' => $room->id,
        'created_by_user_id' => $user->id,
    ]);

    $loadedRoom = Room::with(['housekeepingStatus', 'roomBlocks'])->find($room->id);

    expect($loadedRoom->housekeepingStatus->id)->toBe($housekeeping->id)
        ->and($loadedRoom->roomBlocks->pluck('id')->all())->toBe([$block->id])
        ->and($housekeeping->room->id)->toBe($room->id)
        ->and($housekeeping->updatedBy->id)->toBe($user->id)
        ->and($block->room->id)->toBe($room->id)
        ->and($block->createdBy->id)->toBe($user->id);
});

it('loads the booking header relations (guest, source, creator, property)', function () {
    $booking = Booking::factory()->create();

    $loaded = Booking::with(['property', 'guest', 'createdBy', 'bookingSource'])->find($booking->id);

    expect($loaded->property->id)->toBe($booking->property_id)
        ->and($loaded->guest->id)->toBe($booking->guest_id)
        ->and($loaded->createdBy->id)->toBe($booking->created_by_user_id)
        ->and($loaded->bookingSource->id)->toBe($booking->booking_source_id)
        ->and($loaded->guest->bookings->pluck('id')->all())->toContain($booking->id)
        ->and($loaded->bookingSource->bookings->pluck('id')->all())->toContain($booking->id);
});

it('loads the booking detail relations (items, nights, charges, payments, logs)', function () {
    $room = Room::factory()->create();
    $rate = Rate::factory()->create();
    $user = User::factory()->create();

    $booking = Booking::factory()->confirmed()->createWithNights($room);

    $item = $booking->bookingItems()->sole();
    BookingNight::query()->where('booking_item_id', $item->id)->update(['rate_id' => $rate->id]);

    $charge = BookingCharge::factory()->create(['booking_id' => $booking->id]);
    $payment = Payment::factory()->create([
        'booking_id' => $booking->id,
        'recorded_by_user_id' => $user->id,
    ]);
    $statusLog = BookingStatusLog::factory()->create([
        'booking_id' => $booking->id,
        'changed_by_user_id' => $user->id,
    ]);
    $changeLog = BookingChangeLog::factory()->create([
        'booking_id' => $booking->id,
        'changed_by_user_id' => $user->id,
    ]);

    $loaded = Booking::with([
        'bookingItems.bookingNights',
        'bookingCharges',
        'payments.recordedBy',
        'bookingStatusLogs.changedBy',
        'bookingChangeLogs.changedBy',
    ])->find($booking->id);

    expect($loaded->bookingItems->pluck('id')->all())->toBe([$item->id])
        ->and($loaded->bookingCharges->pluck('id')->all())->toBe([$charge->id])
        ->and($loaded->payments->pluck('id')->all())->toBe([$payment->id])
        ->and($loaded->bookingStatusLogs->pluck('id')->all())->toBe([$statusLog->id])
        ->and($loaded->bookingChangeLogs->pluck('id')->all())->toBe([$changeLog->id]);

    $nights = $loaded->bookingItems->first()->bookingNights;

    expect($nights)->toHaveCount(2)
        ->and($nights->pluck('rate_id')->unique()->all())->toBe([$rate->id]);

    $firstNight = $nights->first();

    expect($firstNight->bookingItem->id)->toBe($item->id)
        ->and($firstNight->room->id)->toBe($room->id)
        ->and($firstNight->rate->id)->toBe($rate->id)
        ->and($item->booking->id)->toBe($booking->id)
        ->and($item->room->id)->toBe($room->id)
        ->and($charge->booking->id)->toBe($booking->id)
        ->and($payment->booking->id)->toBe($booking->id)
        ->and($payment->recordedBy->id)->toBe($user->id)
        ->and($statusLog->booking->id)->toBe($booking->id)
        ->and($statusLog->changedBy->id)->toBe($user->id)
        ->and($changeLog->booking->id)->toBe($booking->id)
        ->and($changeLog->changedBy->id)->toBe($user->id);
});

it('loads the rate to booking nights relation', function () {
    $rate = Rate::factory()->create();
    $room = Room::factory()->create();

    $booking = Booking::factory()->confirmed()->createWithNights($room);
    $item = $booking->bookingItems()->sole();
    BookingNight::query()->where('booking_item_id', $item->id)->update(['rate_id' => $rate->id]);

    $loaded = Rate::with('bookingNights')->find($rate->id);

    expect($loaded->bookingNights)->toHaveCount(2)
        ->and($loaded->bookingNights->pluck('booking_item_id')->unique()->all())->toBe([$item->id]);
});
