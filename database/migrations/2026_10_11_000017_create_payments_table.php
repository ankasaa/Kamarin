<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Payments recorded manually by staff (ERD v2.1, PAYMENTS). payer_name
     * can differ from the guest (someone else transfers), reference_number
     * and proof_path are optional, and money is whole rupiah
     * decimal(12,0). No cascade: a booking with payments must never be
     * deletable by accident.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users');
            $table->decimal('amount', 12, 0);
            $table->string('type');
            $table->string('method');
            $table->string('status');
            $table->string('payer_name')->nullable();
            $table->date('payment_date');
            $table->string('reference_number')->nullable();
            $table->string('proof_path')->nullable();
            // Paid totals per booking (arsitektur_sistem.md section 7).
            $table->index('booking_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
