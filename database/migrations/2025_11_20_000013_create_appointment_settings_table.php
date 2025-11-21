<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_settings', function (Blueprint $table) {
            $table->id();
            // Use explicit defaults to avoid relying on config() during migrations
            $table->unsignedTinyInteger('presence_threshold_pct')->default(97);
            $table->unsignedTinyInteger('early_access_minutes')->default(5);
            $table->unsignedSmallInteger('reschedule_deadline_hours')->default(24);
            $table->unsignedSmallInteger('unanswered_reprogram_hours')->default(5);
            $table->unsignedSmallInteger('ping_interval_seconds')->default(45);
            $table->timestamps();
        });

        // Seed a single row so UI can edit later. Use the same explicit defaults.
        DB::table('appointment_settings')->insert([
            'presence_threshold_pct' => 97,
            'early_access_minutes' => 5,
            'reschedule_deadline_hours' => 24,
            'unanswered_reprogram_hours' => 5,
            'ping_interval_seconds' => 45,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_settings');
    }
};
