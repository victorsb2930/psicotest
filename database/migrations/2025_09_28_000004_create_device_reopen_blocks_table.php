<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('device_reopen_blocks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('token_hash', 64)->nullable()->index();
            $table->timestamp('blocked_until')->nullable()->index();
            $table->boolean('permanent')->default(false);
            $table->unsignedBigInteger('admin_unlocked_by')->nullable();
            $table->timestamp('admin_unlocked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('device_reopen_blocks');
    }
};
