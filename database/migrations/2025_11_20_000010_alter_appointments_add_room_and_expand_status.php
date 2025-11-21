<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change enum to string to support expanded lifecycle statuses
        // and add room_id for deterministic RTC mapping.
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('status', 30)->default('pending')->change();
            $table->string('room_id', 64)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Revert room_id addition
            $table->dropColumn('room_id');
            // Revert status back to original enum
            DB::statement("ALTER TABLE appointments MODIFY status ENUM('pending','accepted','rejected','cancelled') DEFAULT 'pending'");
        });
    }
};
