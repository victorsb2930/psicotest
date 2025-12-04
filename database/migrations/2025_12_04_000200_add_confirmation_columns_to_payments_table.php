<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'confirmed_by_user_id')) {
                $table->foreignId('confirmed_by_user_id')
                    ->nullable()
                    ->after('recipient_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('payments', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'confirmed_by_user_id')) {
                $table->dropConstrainedForeignId('confirmed_by_user_id');
            }
            if (Schema::hasColumn('payments', 'confirmed_at')) {
                $table->dropColumn('confirmed_at');
            }
        });
    }
};
