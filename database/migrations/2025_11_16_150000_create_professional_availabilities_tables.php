<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Base weekly availability: one row per continuous time range
        Schema::create('professional_availabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // professional user id
            $table->unsignedTinyInteger('day_of_week'); // 0 (domingo) .. 6 (sábado)
            $table->time('start_time'); // stored in app timezone (assumed) or UTC convention
            $table->time('end_time');
            $table->string('timezone', 64)->nullable(); // optional explicit tz if professional sets one
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id','day_of_week']);
        });

        // Exceptions / overrides: can block or add ad-hoc availability on specific date
        Schema::create('professional_availability_exceptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('date'); // specific calendar date
            $table->time('start_time')->nullable(); // null means full-day
            $table->time('end_time')->nullable(); // null means full-day
            $table->enum('status', ['available','blocked'])->default('blocked'); // 'blocked' overrides availability, 'available' adds a special slot
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id','date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_availability_exceptions');
        Schema::dropIfExists('professional_availabilities');
    }
};
