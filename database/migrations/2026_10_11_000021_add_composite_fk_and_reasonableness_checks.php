<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * T-005b schema hardening (docs/tasks/BACKLOG_FASE4_5.md row T-005b,
     * user decision "option A"):
     *
     * 1. booking_nights.booking_item_id becomes a composite foreign key
     *    (booking_item_id, room_id) -> booking_items(id, room_id) with
     *    ON UPDATE CASCADE and ON DELETE NO ACTION, backed by a unique
     *    (id, room_id) index on booking_items. A night can no longer point
     *    at a room different from its parent item, and moving a booking
     *    item to another room (BR-06 "pindah kamar") moves its nights -
     *    while the partial unique index still refuses to land an active
     *    night on an occupied (room, date) (BR-01).
     *
     * 2. Twelve reasonableness CHECK constraints with explicit names,
     *    written with DB::statement so SchemaHardeningTest can assert the
     *    exact constraint that fired: money stays non-negative (BR-12),
     *    guest count and capacity stay positive, policy settings stay
     *    inside their bounds, and a rate's end date never precedes its
     *    start date. booking_charges.amount is deliberately unchecked
     *    because discounts are recorded as negative amounts.
     */
    public function up(): void
    {
        // Part 1: composite foreign key (option A).
        Schema::table('booking_items', function (Blueprint $table) {
            $table->unique(['id', 'room_id']);
        });

        Schema::table('booking_nights', function (Blueprint $table) {
            $table->dropForeign(['booking_item_id']);
        });

        DB::statement('
            ALTER TABLE booking_nights
            ADD CONSTRAINT booking_nights_booking_item_id_room_id_foreign
            FOREIGN KEY (booking_item_id, room_id)
            REFERENCES booking_items (id, room_id)
            ON UPDATE CASCADE
            ON DELETE NO ACTION
        ');

        // Part 2: reasonableness checks. The names below are asserted by
        // tests/Feature/Database/SchemaHardeningTest.php - keep in sync.
        DB::statement('
            ALTER TABLE bookings ADD CONSTRAINT bookings_guest_count_positive
            CHECK (guest_count > 0)
        ');
        DB::statement('
            ALTER TABLE bookings ADD CONSTRAINT bookings_total_amount_non_negative
            CHECK (total_amount >= 0)
        ');
        DB::statement('
            ALTER TABLE booking_items ADD CONSTRAINT booking_items_subtotal_non_negative
            CHECK (subtotal >= 0)
        ');
        DB::statement('
            ALTER TABLE booking_nights ADD CONSTRAINT booking_nights_price_non_negative
            CHECK (price >= 0)
        ');
        DB::statement('
            ALTER TABLE payments ADD CONSTRAINT payments_amount_positive
            CHECK (amount > 0)
        ');
        DB::statement('
            ALTER TABLE rates ADD CONSTRAINT rates_price_non_negative
            CHECK (price >= 0)
        ');
        DB::statement('
            ALTER TABLE rates ADD CONSTRAINT rates_minimum_nights_positive
            CHECK (minimum_nights >= 1)
        ');
        DB::statement('
            ALTER TABLE rates ADD CONSTRAINT rates_end_date_not_before_start
            CHECK (start_date IS NULL OR end_date IS NULL OR end_date >= start_date)
        ');
        DB::statement('
            ALTER TABLE room_types ADD CONSTRAINT room_types_capacity_positive
            CHECK (capacity > 0)
        ');
        DB::statement('
            ALTER TABLE properties ADD CONSTRAINT properties_deposit_percent_range
            CHECK (deposit_percent >= 0 AND deposit_percent <= 100)
        ');
        DB::statement('
            ALTER TABLE properties ADD CONSTRAINT properties_pending_expiry_positive
            CHECK (pending_expiry_hours > 0)
        ');
        DB::statement('
            ALTER TABLE properties ADD CONSTRAINT properties_free_cancel_days_non_negative
            CHECK (free_cancel_days >= 0)
        ');
    }

    /**
     * Reverse the migrations.
     *
     * Restores the pre-T-005b schema: the original single-column foreign
     * key with its original name, no unique (id, room_id) on booking_items,
     * and none of the twelve checks.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE properties DROP CONSTRAINT properties_free_cancel_days_non_negative');
        DB::statement('ALTER TABLE properties DROP CONSTRAINT properties_pending_expiry_positive');
        DB::statement('ALTER TABLE properties DROP CONSTRAINT properties_deposit_percent_range');
        DB::statement('ALTER TABLE room_types DROP CONSTRAINT room_types_capacity_positive');
        DB::statement('ALTER TABLE rates DROP CONSTRAINT rates_end_date_not_before_start');
        DB::statement('ALTER TABLE rates DROP CONSTRAINT rates_minimum_nights_positive');
        DB::statement('ALTER TABLE rates DROP CONSTRAINT rates_price_non_negative');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_amount_positive');
        DB::statement('ALTER TABLE booking_nights DROP CONSTRAINT booking_nights_price_non_negative');
        DB::statement('ALTER TABLE booking_items DROP CONSTRAINT booking_items_subtotal_non_negative');
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_total_amount_non_negative');
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_guest_count_positive');

        DB::statement('
            ALTER TABLE booking_nights
            DROP CONSTRAINT booking_nights_booking_item_id_room_id_foreign
        ');

        Schema::table('booking_nights', function (Blueprint $table) {
            $table->foreign('booking_item_id')->references('id')->on('booking_items');
        });

        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropUnique(['id', 'room_id']);
        });
    }
};
