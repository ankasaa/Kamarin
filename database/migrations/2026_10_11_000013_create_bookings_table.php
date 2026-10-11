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
     * The booking header (ERD v2.1, BOOKINGS). BR-01: booking_source_id is
     * mandatory (BK-06), money is whole rupiah decimal(12,0), and bookings
     * are soft deleted so analytics keep their history. created_at is also
     * the basis for lead time reporting (ERD note).
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties');
            $table->foreignId('guest_id')->constrained('guests');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users');
            $table->foreignId('booking_source_id')->constrained('booking_sources');
            $table->string('booking_code')->unique();
            $table->string('status');
            $table->string('payment_option');
            $table->integer('guest_count');
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->decimal('total_amount', 12, 0);
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            // Calendar, list, and today's check-in queries
            // (arsitektur_sistem.md section 7).
            $table->index(['property_id', 'check_in_date', 'check_out_date']);
            $table->index('status');
            $table->timestamps();
        });

        // ERD: check_out_date "CHECK check_out_date > check_in_date"
        // (BR-01 point 2). Explicit name so the test can prove this CHECK.
        DB::statement('
            ALTER TABLE bookings ADD CONSTRAINT bookings_check_out_after_checkin
            CHECK (check_out_date > check_in_date)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
