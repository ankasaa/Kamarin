<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master data: the guesthouse itself plus its policy settings
     * (ERD v2.1, PROPERTIES; no policy is hardcoded, see AGENTS.md rule 6).
     */
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address');
            $table->string('city');
            $table->string('phone');
            $table->time('check_in_time')->default('14:00');
            $table->time('check_out_time')->default('12:00');
            $table->smallInteger('deposit_percent')->default(50);
            $table->smallInteger('pending_expiry_hours')->default(24);
            $table->smallInteger('free_cancel_days')->default(2);
            $table->time('no_show_cutoff_time')->default('22:00');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
