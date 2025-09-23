<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Evita errores de índices/llaves con utf8mb4 en MySQL antiguos
        Schema::defaultStringLength(191);

        // Registro de comando de diagnóstico para entorno/DB
        if ($this->app->runningInConsole()) {
            \Illuminate\Support\Facades\Artisan::command('app:health', function () {
                $this->info('Health check:');
                $ok = true;
                $messages = [];
                // Tablas críticas
                $tables = ['users','roles','permissions','role_has_permissions','model_has_roles','model_has_permissions','professional_applications'];
                foreach ($tables as $t) {
                    if (!\Illuminate\Support\Facades\Schema::hasTable($t)) {
                        $ok = false; $messages[] = "- Falta tabla: {$t}";
                    }
                }
                // Rol professional y bandera requires_docs
                if (\Illuminate\Support\Facades\Schema::hasTable('roles')) {
                    $pro = \Illuminate\Support\Facades\DB::table('roles')->where('name','professional')->first();
                    if (!$pro) { $ok = false; $messages[] = '- No existe rol "professional"'; }
                    elseif (!(bool)($pro->requires_docs ?? false)) { $ok = false; $messages[] = '- Rol "professional" sin requires_docs=1'; }
                }
                // Pendientes
                if (\Illuminate\Support\Facades\Schema::hasTable('professional_applications')) {
                    $pending = \Illuminate\Support\Facades\DB::table('professional_applications')->where('status','pending')->count();
                    $messages[] = "- Solicitudes pendientes: {$pending}";
                }
                foreach ($messages as $m) { $this->line($m); }
                return $ok ? 0 : 1;
            })->describe('Diagnóstico de tablas/migraciones/roles y solicitudes pendientes');

            \Illuminate\Support\Facades\Artisan::command('app:make-pending {email?}', function () {
                $email = (string) ($this->argument('email') ?? 'pro_test_'.time().'@local.test');
                $this->info("Creando solicitud pendiente para: {$email}");
                // Asegurar tablas básicas
                foreach (['users','roles','professional_applications'] as $t) {
                    if (!\Illuminate\Support\Facades\Schema::hasTable($t)) {
                        $this->error("Falta tabla {$t}. Ejecuta migraciones primero.");
                        return 1;
                    }
                }
                // Asegurar roles base
                $now = now();
                $rolePro = \Illuminate\Support\Facades\DB::table('roles')->where('name','professional')->first();
                if (!$rolePro) {
                    \Illuminate\Support\Facades\DB::table('roles')->insert([
                        'name' => 'professional',
                        'guard_name' => 'web',
                        'show_in_signup' => true,
                        'requires_docs' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $rolePro = \Illuminate\Support\Facades\DB::table('roles')->where('name','professional')->first();
                } else {
                    // Forzar bandera si existe la columna
                    if (\Illuminate\Support\Facades\Schema::hasColumn('roles','requires_docs')) {
                        \Illuminate\Support\Facades\DB::table('roles')->where('id',$rolePro->id)->update(['requires_docs' => true, 'updated_at' => $now]);
                    }
                }
                $roleUser = \Illuminate\Support\Facades\DB::table('roles')->where('name','user')->first();
                if (!$roleUser) {
                    \Illuminate\Support\Facades\DB::table('roles')->insert([
                        'name' => 'user',
                        'guard_name' => 'web',
                        'show_in_signup' => true,
                        'requires_docs' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $roleUser = \Illuminate\Support\Facades\DB::table('roles')->where('name','user')->first();
                }
                // Crear o reutilizar usuario
                $user = \Illuminate\Support\Facades\DB::table('users')->where(\Illuminate\Support\Facades\DB::raw('LOWER(email)'), strtolower($email))->first();
                if (!$user) {
                    $uid = \Illuminate\Support\Facades\DB::table('users')->insertGetId([
                        'name' => 'Pro Test',
                        'email' => $email,
                        'password' => \Illuminate\Support\Facades\Hash::make('secret123'),
                        'is_active' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $user = \Illuminate\Support\Facades\DB::table('users')->where('id',$uid)->first();
                } else {
                    \Illuminate\Support\Facades\DB::table('users')->where('id',$user->id)->update(['is_active' => false, 'updated_at' => $now]);
                }
                // Rol por defecto 'user' (no asignar professional hasta aprobación)
                if ($roleUser) {
                    \Illuminate\Support\Facades\DB::table('model_has_roles')->updateOrInsert([
                        'model_type' => \App\Models\User::class,
                        'model_id' => $user->id,
                        'role_id' => $roleUser->id,
                    ], []);
                }
                // Colocar archivos dummy
                $titulo = 'professional_docs/'.(string) \Illuminate\Support\Str::uuid().'.pdf';
                $cedula = 'professional_docs/'.(string) \Illuminate\Support\Str::uuid().'.pdf';
                \Illuminate\Support\Facades\Storage::disk('local')->put($titulo, '');
                \Illuminate\Support\Facades\Storage::disk('local')->put($cedula, '');
                // Crear solicitud pendiente (idempotente básica: una por user si no existe pendiente)
                $exists = \Illuminate\Support\Facades\DB::table('professional_applications')->where('user_id',$user->id)->where('status','pending')->exists();
                if (!$exists) {
                    \Illuminate\Support\Facades\DB::table('professional_applications')->insert([
                        'user_id' => $user->id,
                        'titulo_path' => $titulo,
                        'cedula_path' => $cedula,
                        'status' => 'pending',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $pending = \Illuminate\Support\Facades\DB::table('professional_applications')->where('status','pending')->count();
                $this->info("Listo. Solicitudes pendientes: {$pending}");
                return 0;
            })->describe('Crea una solicitud profesional pendiente de prueba para el email dado');
        }
    }
}
