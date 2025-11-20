<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users')) return; // safety
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users','email_verification_token')) {
                $table->string('email_verification_token', 100)->nullable()->index();
            }
            if (!Schema::hasColumn('users','email_verification_token_expires_at')) {
                $table->timestamp('email_verification_token_expires_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) return;
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users','email_verification_token_expires_at')) {
                $table->dropColumn('email_verification_token_expires_at');
            }
            if (Schema::hasColumn('users','email_verification_token')) {
                $table->dropColumn('email_verification_token');
            }
        });
    }
};
