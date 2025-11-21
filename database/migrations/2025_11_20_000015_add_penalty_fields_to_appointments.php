<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function(Blueprint $table){
            $table->timestamp('penalty_applied_at')->nullable()->after('room_id');
            $table->string('penalty_type',40)->nullable()->after('penalty_applied_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function(Blueprint $table){
            $table->dropColumn(['penalty_applied_at','penalty_type']);
        });
    }
};
