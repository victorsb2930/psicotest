<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('device_reopen_attempts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('token_hash', 64)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('success')->nullable();
            $table->string('action')->default('confirm'); // 'confirm' or 'resend'
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('device_reopen_attempts');
    }
};
