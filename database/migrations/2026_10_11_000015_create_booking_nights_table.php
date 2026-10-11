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
     * One row per room per night (ERD v2.1, BOOKING_NIGHTS). room_id is a
     * copy of booking_items.room_id purely so the double booking index can
     * span it (ERD note: "salinan, untuk constraint"). is_active is false
     * for cancelled, expired, and no-show bookings, so those nights never
     * block a new booking (BR-01, "Malam aktif").
     */
    public function up(): void
    {
        Schema::create('booking_nights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_item_id')->constrained('booking_items');
            $table->foreignId('room_id')->constrained('rooms');
            $table->foreignId('rate_id')->nullable()->constrained('rates');
            $table->date('stay_date');
            $table->decimal('price', 12, 0);
            $table->boolean('is_active');
            // Occupancy and revenue queries (arsitektur_sistem.md section 7).
            $table->index('stay_date');
            $table->timestamps();
        });

        // BR-01 layer 3: the partial unique index. The schema builder cannot
        // express a partial index, so it is written with DB::statement as
        // required by the task and AGENTS.md rule 4. It only covers active
        // nights, which is what makes same-day turnover (BR-01 point 3) and
        // re-booking after a cancellation possible.
        DB::statement('
            CREATE UNIQUE INDEX booking_nights_room_stay_active_unique
            ON booking_nights (room_id, stay_date)
            WHERE is_active
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_nights');
    }
};
