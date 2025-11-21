<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_reschedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('original_start')->nullable();
            $table->timestamp('original_end')->nullable();
            $table->timestamp('proposed_start')->nullable();
            $table->timestamp('proposed_end')->nullable();
            $table->enum('status', ['pending','accepted','rejected','expired'])->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['appointment_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_reschedules');
    }
};
