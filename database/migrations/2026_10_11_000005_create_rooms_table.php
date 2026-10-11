<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master data: physical rooms (ERD v2.1, ROOMS). A room number is
     * unique within its property (US-05, enforced by a composite unique
     * index, not only by an application check).
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties');
            $table->foreignId('room_type_id')->constrained('room_types');
            $table->string('room_number');
            $table->integer('floor')->nullable();
            $table->string('status');
            $table->unique(['property_id', 'room_number']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
