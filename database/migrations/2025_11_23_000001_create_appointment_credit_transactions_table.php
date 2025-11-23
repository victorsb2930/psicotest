<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('appointment_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->integer('amount')->default(0); // positive = purchase, negative = consume
            $table->json('meta')->nullable();
            $table->timestamps();

            // If users table exists, add FK for convenience (best-effort)
            try {
                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                }
            } catch (\Throwable $_) { /* ignore foreign key creation errors in some environments */ }
        });
    }

    public function down()
    {
        Schema::dropIfExists('appointment_credit_transactions');
    }
};
