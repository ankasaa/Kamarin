<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master data: cleanliness status per room, one row per room
     * (ERD v2.1, HOUSEKEEPING_STATUSES 1:1 with ROOMS). ERD marks
     * updated_by_user_id and notes explicitly; both are nullable.
     */
    public function up(): void
    {
        Schema::create('housekeeping_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->unique()->constrained('rooms');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housekeeping_statuses');
    }
};
