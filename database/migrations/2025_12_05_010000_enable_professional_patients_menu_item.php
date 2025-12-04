<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('menu_items')) {
            return;
        }

        DB::table('menu_items')
            ->where('route_name', 'professional.patients')
            ->update(['enabled' => true, 'updated_at' => now()]);

        try {
            if (class_exists(\App\Services\MenuService::class)) {
                \App\Services\MenuService::bump();
            }
        } catch (\Throwable $__) {
            // ignore cache failures during migration
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('menu_items')) {
            return;
        }

        DB::table('menu_items')
            ->where('route_name', 'professional.patients')
            ->update(['enabled' => false, 'updated_at' => now()]);

        try {
            if (class_exists(\App\Services\MenuService::class)) {
                \App\Services\MenuService::bump();
            }
        } catch (\Throwable $__) {
        }
    }
};
