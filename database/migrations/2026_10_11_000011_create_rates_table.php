<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master data: rates per room type (ERD v2.1, RATES; BR-11). Money is
     * stored as decimal(12,0): whole Indonesian rupiah, never a float
     * (AGENTS.md rule 5, BR bagian 1 "Mata uang"). Null start/end date
     * means the default rate. days_of_week holds ISO weekday numbers 1-7.
     */
    public function up(): void
    {
        Schema::create('rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties');
            $table->foreignId('room_type_id')->constrained('room_types');
            $table->string('name');
            $table->decimal('price', 12, 0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->jsonb('days_of_week')->nullable();
            $table->integer('priority');
            $table->boolean('is_default');
            $table->integer('minimum_nights');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rates');
    }
};
