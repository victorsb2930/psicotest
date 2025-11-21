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
            $table->unsignedTinyInteger('presence_threshold_pct')->default(config('appointments.presence_threshold_pct'));
            $table->unsignedTinyInteger('early_access_minutes')->default(config('appointments.early_access_minutes'));
            $table->unsignedSmallInteger('reschedule_deadline_hours')->default(config('appointments.reschedule_deadline_hours'));
            $table->unsignedSmallInteger('unanswered_reprogram_hours')->default(config('appointments.unanswered_reprogram_hours'));
            $table->unsignedSmallInteger('ping_interval_seconds')->default(config('appointments.ping_interval_seconds'));
            $table->timestamps();
        });

        // Seed a single row so UI can edit later.
        DB::table('appointment_settings')->insert([
            'presence_threshold_pct' => config('appointments.presence_threshold_pct'),
            'early_access_minutes' => config('appointments.early_access_minutes'),
            'reschedule_deadline_hours' => config('appointments.reschedule_deadline_hours'),
            'unanswered_reprogram_hours' => config('appointments.unanswered_reprogram_hours'),
            'ping_interval_seconds' => config('appointments.ping_interval_seconds'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_settings');
    }
};
