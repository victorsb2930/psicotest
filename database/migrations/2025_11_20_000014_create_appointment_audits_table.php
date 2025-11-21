<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action', 50); // started, completed, reschedule_requested, reschedule_accepted, reschedule_rejected, skipped, no_show, expired
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['appointment_id','action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_audits');
    }
};
