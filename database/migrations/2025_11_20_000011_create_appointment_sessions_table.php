<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade');
            $table->string('room_id', 64)->nullable(); // redundancy for faster lookup
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('professional_joined_at')->nullable();
            $table->timestamp('patient_joined_at')->nullable();
            $table->timestamp('professional_left_at')->nullable();
            $table->timestamp('patient_left_at')->nullable();
            $table->unsignedInteger('professional_presence_seconds')->default(0);
            $table->unsignedInteger('patient_presence_seconds')->default(0);
            $table->timestamps();
            $table->unique('appointment_id');
            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_sessions');
    }
};
