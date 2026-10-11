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
     * Master-ish transaction data: manual room blocks (ERD v2.1,
     * ROOM_BLOCKS). BR-01 point 6: blocks clash with active nights in the
     * service layer; the database only guarantees a sane date range here.
     */
    public function up(): void
    {
        Schema::create('room_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason');
            // Availability check index (arsitektur_sistem.md section 7).
            $table->index(['room_id', 'start_date', 'end_date']);
            $table->timestamps();
        });

        // ERD: end_date "CHECK end_date > start_date". Written with an
        // explicit constraint name so the test can prove this exact CHECK
        // rejects the insert.
        DB::statement('
            ALTER TABLE room_blocks ADD CONSTRAINT room_blocks_end_after_start
            CHECK (end_date > start_date)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_blocks');
    }
};
