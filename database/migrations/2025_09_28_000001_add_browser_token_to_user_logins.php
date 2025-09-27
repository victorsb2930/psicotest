<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_logins')) {
            Schema::table('user_logins', function (Blueprint $table) {
                if (!Schema::hasColumn('user_logins', 'browser_token_hash')) {
                    $table->string('browser_token_hash', 64)->nullable()->after('user_agent')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('user_logins')) {
            Schema::table('user_logins', function (Blueprint $table) {
                if (Schema::hasColumn('user_logins', 'browser_token_hash')) {
                    $table->dropColumn('browser_token_hash');
                }
            });
        }
    }
};
