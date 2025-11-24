<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('chat_call_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('type', 32)->nullable(false); // 'video' or 'voice'
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_call_logs');
    }
};
