<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (!Schema::hasColumn('roles','icon_class')) {
                    $table->string('icon_class')->nullable()->after('signup_label');
                }
                if (!Schema::hasColumn('roles','badge_color')) {
                    $table->string('badge_color')->nullable()->after('icon_class');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (Schema::hasColumn('roles','badge_color')) {
                    $table->dropColumn('badge_color');
                }
                if (Schema::hasColumn('roles','icon_class')) {
                    $table->dropColumn('icon_class');
                }
            });
        }
    }
};
