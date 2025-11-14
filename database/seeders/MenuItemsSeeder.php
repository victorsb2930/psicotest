<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MenuItemsSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('menu_items') || !Schema::hasTable('roles')) return;

        $roles = DB::table('roles')->pluck('id', 'name');

        $items = [
            // Admin section
            ['label' => 'Dashboard', 'route_name' => 'adminarea', 'icon_class' => 'bi bi-speedometer2', 'section' => 'admin', 'sort_order' => 10, 'permission' => 'adminarea'],
            ['label' => 'Usuarios', 'route_name' => 'admin.users', 'icon_class' => 'bi bi-people', 'section' => 'admin', 'sort_order' => 20, 'permission' => 'adminarea'],
            ['label' => 'Solicitudes', 'route_name' => 'admin.profapps.index', 'icon_class' => 'bi bi-file-earmark-medical', 'section' => 'admin', 'sort_order' => 30, 'permission' => 'professional_applications'],
            ['label' => 'Roles', 'route_name' => 'admin.roles.index', 'icon_class' => 'bi bi-shield-lock', 'section' => 'admin', 'sort_order' => 40, 'permission' => 'adminarea'],
            ['label' => 'Permisos', 'route_name' => 'admin.permissions.index', 'icon_class' => 'bi bi-key', 'section' => 'admin', 'sort_order' => 50, 'permission' => 'adminarea'],
            ['label' => 'Gestion del menú', 'route_name' => 'admin.menuitems.index', 'icon_class' => 'bi bi-list-task', 'section' => 'admin', 'sort_order' => 55, 'permission' => 'adminarea'],
            ['label' => 'Dispositivos', 'route_name' => 'admin.devices', 'icon_class' => 'bi bi-phone', 'section' => 'admin', 'sort_order' => 60, 'permission' => 'adminarea'],

            // Professional section
            ['label' => 'Mi panel', 'route_name' => 'professionalarea', 'icon_class' => 'bi bi-person-badge', 'section' => 'professional', 'sort_order' => 10, 'permission' => 'professionalarea'],
            ['label' => 'Calendario', 'route_name' => 'professional.calendar', 'icon_class' => 'bi bi-calendar3', 'section' => 'professional', 'sort_order' => 20, 'permission' => 'professionalarea'],
            ['label' => 'Chat', 'route_name' => 'chat.index', 'icon_class' => 'bi bi-chat-dots', 'section' => 'professional', 'sort_order' => 30, 'permission' => 'professionalarea'],
            ['label' => 'Pacientes', 'route_name' => 'professional.patients', 'icon_class' => 'bi bi-people', 'section' => 'professional', 'sort_order' => 40, 'permission' => 'professionalarea'],
            ['label' => 'Servicios', 'route_name' => 'professional.services', 'icon_class' => 'bi bi-briefcase', 'section' => 'professional', 'sort_order' => 50, 'permission' => 'professionalarea'],
            ['label' => 'Historial de Pagos', 'route_name' => 'professional.payments', 'icon_class' => 'bi bi-credit-card', 'section' => 'professional', 'sort_order' => 60, 'permission' => 'professionalarea'],
            ['label' => 'Configuración', 'route_name' => 'professional.settings', 'icon_class' => 'bi bi-gear', 'section' => 'professional', 'sort_order' => 70, 'permission' => 'professionalarea'],

            // User section
            ['label' => 'Mi cuenta', 'route_name' => 'userarea', 'icon_class' => 'bi bi-house', 'section' => 'user', 'sort_order' => 10, 'permission' => 'userarea'],
            ['label' => 'Calendario', 'route_name' => 'appointments.index', 'icon_class' => 'bi bi-calendar3', 'section' => 'user', 'sort_order' => 20, 'permission' => 'userarea'],
            ['label' => 'Buscar profesionales', 'route_name' => 'professionals.index', 'icon_class' => 'bi bi-search', 'section' => 'user', 'sort_order' => 30, 'permission' => 'userarea'],
            ['label' => 'Favoritos', 'route_name' => 'favorites', 'icon_class' => 'bi bi-star', 'section' => 'user', 'sort_order' => 40, 'permission' => 'userarea'],
            ['label' => 'Chat', 'route_name' => 'chat.index', 'icon_class' => 'bi bi-chat-dots', 'section' => 'user', 'sort_order' => 50, 'permission' => 'userarea'],
        ];

        $idByLabel = [];
        foreach ($items as $i) {
            $row = [
                'label' => $i['label'],
                'route_name' => $i['route_name'] ?? null,
                'url' => $i['url'] ?? null,
                'icon_class' => $i['icon_class'] ?? null,
                'section' => $i['section'] ?? 'user',
                'sort_order' => $i['sort_order'] ?? 0,
                'enabled' => true,
                'permission' => $i['permission'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $existing = DB::table('menu_items')->where('label', $i['label'])->first();
            if ($existing) {
                DB::table('menu_items')->where('id', $existing->id)->update($row);
                $id = $existing->id;
            } else {
                $id = DB::table('menu_items')->insertGetId($row);
            }
            $idByLabel[$i['label']] = $id;
        }

        // Attach visibility to roles (admin/professional/user for their sections). Common has no role restriction.
        $attach = function(string $label, array $roleNames) use ($idByLabel, $roles) {
            $id = $idByLabel[$label] ?? null; if (!$id) return;
            foreach ($roleNames as $rn) {
                $rid = $roles[$rn] ?? null; if (!$rid) continue;
                DB::table('menu_item_role')->updateOrInsert(['menu_item_id' => $id, 'role_id' => $rid], []);
            }
        };

        // Admin items -> role admin
    foreach (['Dashboard','Usuarios','Solicitudes','Roles','Permisos','Gestion del menú','Dispositivos'] as $lbl) { $attach($lbl, ['admin']); }
        // Professional items -> role professional
        foreach (['Mi panel','Calendario','Chat','Pacientes','Servicios','Historial de Pagos','Configuración'] as $lbl) { $attach($lbl, ['professional']); }
        // User items -> role user
        foreach (['Mi cuenta','Calendario','Buscar profesionales','Favoritos','Chat'] as $lbl) { $attach($lbl, ['user']); }
        // Common: no pivot to show for all roles
    }
}
