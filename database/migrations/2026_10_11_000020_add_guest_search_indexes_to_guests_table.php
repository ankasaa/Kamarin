<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Guest search indexes from docs/arsitektur_sistem.md section 7. The
     * guests table itself was migrated in T-004 (stage 1); section 7 lists
     * its email and phone indexes here in stage 2 so the complete index
     * set ships together. Guests may share an email (US-07: found, not
     * merged automatically), so these are plain indexes, not unique.
     */
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->index('email');
            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['phone']);
        });
    }
};
