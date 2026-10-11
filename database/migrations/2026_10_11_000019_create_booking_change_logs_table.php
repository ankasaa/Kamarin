<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Append-only history of booking data edits (ERD v2.1,
     * BOOKING_CHANGE_LOGS): dates, room, guest count, with before/after
     * values, so the staff member who changed a booking stays accountable.
     */
    public function up(): void
    {
        Schema::create('booking_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings');
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users');
            $table->string('change_type');
            $table->text('old_value');
            $table->text('new_value');
            $table->text('reason');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_change_logs');
    }
};
