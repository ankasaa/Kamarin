<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extra charges on a booking (ERD v2.1, BOOKING_CHARGES), such as extra
     * beds or late check-out. Discounts are recorded as negative amounts
     * (decision locked in docs/STATUS.md). Money is whole rupiah
     * decimal(12,0).
     */
    public function up(): void
    {
        Schema::create('booking_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings');
            $table->string('description');
            $table->decimal('amount', 12, 0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_charges');
    }
};
