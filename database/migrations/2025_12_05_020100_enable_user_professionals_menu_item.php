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

        $now = now();
        $row = DB::table('menu_items')->where('route_name', 'user.professionals')->first();
        if ($row) {
            DB::table('menu_items')->where('id', $row->id)->update([
                'label' => 'Mis profesionales',
                'icon_class' => 'bi bi-people',
                'section' => 'user',
                'sort_order' => 35,
                'permission' => 'userarea',
                'enabled' => true,
                'updated_at' => $now,
            ]);
            $menuId = $row->id;
        } else {
            $menuId = DB::table('menu_items')->insertGetId([
                'label' => 'Mis profesionales',
                'route_name' => 'user.professionals',
                'icon_class' => 'bi bi-people',
                'section' => 'user',
                'sort_order' => 35,
                'enabled' => true,
                'permission' => 'userarea',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($menuId && Schema::hasTable('roles') && Schema::hasTable('menu_item_role')) {
            $roleId = DB::table('roles')->where('name', 'user')->value('id');
            if ($roleId) {
                DB::table('menu_item_role')->updateOrInsert([
                    'menu_item_id' => $menuId,
                    'role_id' => $roleId,
                ], []);
            }
        }

        try {
            if (class_exists(\App\Services\MenuService::class)) {
                \App\Services\MenuService::bump();
            }
        } catch (\Throwable $__) {
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('menu_items')) {
            return;
        }

        DB::table('menu_items')
            ->where('route_name', 'user.professionals')
            ->update(['enabled' => false, 'updated_at' => now()]);

        try {
            if (class_exists(\App\Services\MenuService::class)) {
                \App\Services\MenuService::bump();
            }
        } catch (\Throwable $__) {
        }
    }
};
