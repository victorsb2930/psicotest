<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_session_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('appointment_session_id')->nullable();
            $table->string('event_type', 100);
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['appointment_id','event_type']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('appointment_session_logs');
    }
};
