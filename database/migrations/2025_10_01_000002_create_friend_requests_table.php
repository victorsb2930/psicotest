<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('friend_requests', function(Blueprint $t){
            $t->id();
            $t->unsignedBigInteger('from_id');
            $t->unsignedBigInteger('to_id');
            $t->enum('status',['pending','accepted','rejected'])->default('pending');
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->timestamps();
            $t->unique(['from_id','to_id']);
            $t->foreign('from_id')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('to_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('friend_requests'); }
};