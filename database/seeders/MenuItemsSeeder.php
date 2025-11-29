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

        // Set default role landing paths if missing
        try {
            foreach ([
                'admin' => '/adminarea',
                'professional' => '/professionalarea',
                'user' => '/userarea',
            ] as $roleName => $home) {
                if (isset($roles[$roleName])) {
                    $existingHome = DB::table('roles')->where('name', $roleName)->value('home_path');
                    if (empty($existingHome)) {
                        DB::table('roles')->where('name', $roleName)->update(['home_path' => $home]);
                    }
                }
            }
        } catch (\Throwable $e) { /* noop */ }

        $items = [
            // Admin section
            ['label' => 'Dashboard', 'route_name' => 'adminarea', 'icon_class' => 'bi bi-speedometer2', 'section' => 'admin', 'sort_order' => 10, 'permission' => 'adminarea'],
            ['label' => 'Usuarios', 'route_name' => 'admin.users', 'icon_class' => 'bi bi-people', 'section' => 'admin', 'sort_order' => 20, 'permission' => 'adminarea'],
            ['label' => 'Solicitudes', 'route_name' => 'admin.profapps.index', 'icon_class' => 'bi bi-file-earmark-medical', 'section' => 'admin', 'sort_order' => 30, 'permission' => 'professional_applications'],
            ['label' => 'Roles', 'route_name' => 'admin.roles.index', 'icon_class' => 'bi bi-shield-lock', 'section' => 'admin', 'sort_order' => 40, 'permission' => 'adminarea'],
            ['label' => 'Permisos', 'route_name' => 'admin.permissions.index', 'icon_class' => 'bi bi-key', 'section' => 'admin', 'sort_order' => 50, 'permission' => 'adminarea'],
            ['label' => 'Gestion del menú', 'route_name' => 'admin.menuitems.index', 'icon_class' => 'bi bi-list-task', 'section' => 'admin', 'sort_order' => 55, 'permission' => 'adminarea'],
            ['label' => 'Dispositivos', 'route_name' => 'admin.devices', 'icon_class' => 'bi bi-phone', 'section' => 'admin', 'sort_order' => 60, 'permission' => 'adminarea'],
            ['label' => 'Pagos', 'route_name' => 'admin.payments.index', 'icon_class' => 'bi bi-credit-card', 'section' => 'admin', 'sort_order' => 61, 'permission' => null],
        ];

        // Add appointment settings item if route exists
        try {
            if (\Route::has('admin.appointment.settings')) {
                $items[] = ['label' => 'Ajustes de Citas', 'route_name' => 'admin.appointment.settings', 'icon_class' => 'bi bi-sliders', 'section' => 'admin', 'sort_order' => 65, 'permission' => 'adminarea'];
            }
            // Métricas de citas (dashboard) si existe la ruta
            if (\Route::has('admin.appointment.metrics')) {
                $items[] = ['label' => 'Métricas de Citas', 'route_name' => 'admin.appointment.metrics', 'icon_class' => 'bi bi-graph-up', 'section' => 'admin', 'sort_order' => 70, 'permission' => 'adminarea'];
            }
        } catch (\Throwable $e) { /* ignore */ }

        // Professional section items (usar etiqueta única para evitar colisión con Calendario usuario)
        $items = array_merge($items, [
            ['label' => 'Mi panel', 'route_name' => 'professionalarea', 'icon_class' => 'bi bi-person-badge', 'section' => 'professional', 'sort_order' => 10, 'permission' => 'professionalarea'],
            ['label' => 'Calendario Profesional', 'route_name' => 'professional.calendar', 'icon_class' => 'bi bi-calendar3', 'section' => 'professional', 'sort_order' => 20, 'permission' => 'professionalarea'],
            ['label' => 'Disponibilidad', 'route_name' => 'professional.availability', 'icon_class' => 'bi bi-clock', 'section' => 'professional', 'sort_order' => 25, 'permission' => 'professionalarea'],
            ['label' => 'Mis Calificaciones', 'route_name' => 'professional.ratings.index', 'icon_class' => 'bi bi-stars', 'section' => 'professional', 'sort_order' => 35, 'permission' => 'professionalarea'],
            ['label' => 'Pacientes', 'route_name' => 'professional.patients', 'icon_class' => 'bi bi-people', 'section' => 'professional', 'sort_order' => 40, 'permission' => 'professionalarea'],
            ['label' => 'Servicios', 'route_name' => 'professional.services', 'icon_class' => 'bi bi-briefcase', 'section' => 'professional', 'sort_order' => 50, 'permission' => 'professionalarea'],
            ['label' => 'Historial de Citas', 'route_name' => 'professional.appointments.history', 'icon_class' => 'bi bi-journal-text', 'section' => 'professional', 'sort_order' => 55, 'permission' => 'professionalarea'],
            ['label' => 'Historial de Pagos', 'route_name' => 'professional.payments.history', 'icon_class' => 'bi bi-credit-card', 'section' => 'professional', 'sort_order' => 60, 'permission' => 'professionalarea'],
            ['label' => 'Configuración', 'route_name' => 'professional.settings', 'icon_class' => 'bi bi-gear', 'section' => 'professional', 'sort_order' => 70, 'permission' => 'professionalarea'],
        ]);

        // User section items (Calendario sin permiso específico)
        $items = array_merge($items, [
            ['label' => 'Mi cuenta', 'route_name' => 'userarea', 'icon_class' => 'bi bi-house', 'section' => 'user', 'sort_order' => 10, 'permission' => 'userarea'],
            ['label' => 'Calendario', 'route_name' => 'appointments.index', 'icon_class' => 'bi bi-calendar3', 'section' => 'user', 'sort_order' => 20, 'permission' => null],
            ['label' => 'Buscar profesionales', 'route_name' => 'professionals.index', 'icon_class' => 'bi bi-search', 'section' => 'user', 'sort_order' => 30, 'permission' => 'userarea'],
            ['label' => 'Favoritos', 'route_name' => 'favorites', 'icon_class' => 'bi bi-star', 'section' => 'user', 'sort_order' => 40, 'permission' => 'userarea'],
            // Chat es compartido entre roles; sin permiso específico para no depender de userarea/professionalarea
            ['label' => 'Chat', 'route_name' => 'chat.index', 'icon_class' => 'bi bi-chat-dots', 'section' => 'common', 'sort_order' => 50, 'permission' => null],
            ['label' => 'Planes', 'route_name' => 'plans.index', 'icon_class' => 'bi bi-card-list', 'section' => 'user', 'sort_order' => 60, 'permission' => 'userarea'],
        ]);

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
                'updated_at' => now(),
            ];
            $existing = DB::table('menu_items')->where('label', $i['label'])->first();
            // Si la ruta declarada no existe, marcar (o actualizar) como disabled y continuar
            if (!empty($row['route_name']) && !\Route::has($row['route_name'])) {
                if ($existing) {
                    DB::table('menu_items')->where('id', $existing->id)->update(['enabled' => false, 'updated_at' => now()]);
                    $idByLabel[$i['label']] = $existing->id;
                }
                continue;
            }
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
        foreach (['Dashboard','Usuarios','Solicitudes','Roles','Permisos','Gestion del menú','Dispositivos','Pagos','Ajustes de Citas','Métricas de Citas'] as $lbl) { $attach($lbl, ['admin']); }
        // Professional items -> role professional (Chat y Calendario usuario se anexan aparte)
        foreach (['Mi panel','Calendario Profesional','Disponibilidad','Mis Calificaciones','Pacientes','Servicios','Historial de Citas','Historial de Pagos','Configuración'] as $lbl) { $attach($lbl, ['professional']); }
        // User items -> role user (Chat común más abajo)
        foreach (['Mi cuenta','Calendario','Buscar profesionales','Favoritos','Planes'] as $lbl) { $attach($lbl, ['user']); }
        // Chat compartido para ambos roles
        $attach('Chat', ['professional','user']);
        // Common: no pivot to show for all roles
    }
}
