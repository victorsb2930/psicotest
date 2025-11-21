<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
	/**
	 * Seed the application's database.
	 */
	public function run(): void
	{
		// RBAC seed: roles, permissions, and pivot associations
		$roles = [
			['name' => 'admin', 'signup_label' => 'Administrador', 'requires_docs' => false, 'icon_class' => 'bi bi-shield-lock', 'badge_color' => 'bg-dark'],
			['name' => 'professional', 'signup_label' => 'Profesional', 'requires_docs' => true, 'icon_class' => 'bi bi-briefcase', 'badge_color' => 'bg-success'],
			['name' => 'user', 'signup_label' => 'Usuario', 'requires_docs' => false, 'icon_class' => 'bi bi-person', 'badge_color' => 'bg-primary'],
		];
		foreach ($roles as $r) {
			$update = [
				'name' => $r['name'],
				'guard_name' => 'web',
				'updated_at' => now(),
				'created_at' => now(),
			];
			if (Schema::hasColumn('roles', 'signup_label')) {
				$update['signup_label'] = $r['signup_label'] ?? null;
			}
			if (Schema::hasColumn('roles', 'show_in_signup')) {
				$update['show_in_signup'] = in_array($r['name'], ['user', 'professional']);
			}
			if (Schema::hasColumn('roles', 'requires_docs')) {
				$update['requires_docs'] = (bool)($r['requires_docs'] ?? false);
			}
			if (Schema::hasColumn('roles', 'icon_class')) {
				$update['icon_class'] = $r['icon_class'] ?? null;
			}
			if (Schema::hasColumn('roles', 'badge_color')) {
				$update['badge_color'] = $r['badge_color'] ?? null;
			}
			DB::table('roles')->updateOrInsert(['name' => $r['name']], $update);
		}

		$perms = [
			['name' => 'adminarea'],
			['name' => 'professionalarea'],
			['name' => 'userarea'],
			['name' => 'professional_applications'],
		];
		foreach ($perms as $p) {
			DB::table('permissions')->updateOrInsert(
				['name' => $p['name']],
				[
					'name' => $p['name'],
					'guard_name' => 'web',
					'updated_at' => now(),
					'created_at' => now(),
				]
			);
		}

		// Role -> Permission mapping
		$roleIds = DB::table('roles')->pluck('id', 'name');
		$permIds = DB::table('permissions')->pluck('id', 'name');
		$attach = function (string $role, array $permSlugs) use ($roleIds, $permIds) {
			$rid = $roleIds[$role] ?? null;
			if (!$rid) return;
			foreach ($permSlugs as $ps) {
				$pid = $permIds[$ps] ?? null;
				if (!$pid) continue;
				DB::table('role_has_permissions')->updateOrInsert(['role_id' => $rid, 'permission_id' => $pid], []);
			}
		};
		// admin: todos
		$attach('admin', array_keys($permIds->toArray()));
		$attach('professional', ['professionalarea']);
		$attach('user', ['userarea']);
		$attach('professional_applications', ['professional_applications']);

		// Asignar roles por defecto (idempotente)
		$users = DB::table('users')->select('id', 'email')->get();
		foreach ($users as $u) {
			$existing = DB::table('model_has_roles')->where(['model_type' => User::class, 'model_id' => $u->id])->pluck('role_id')->all();
			if (empty($existing)) {
				$ridUser = $roleIds['user'] ?? null;
				if ($ridUser) DB::table('model_has_roles')->updateOrInsert(['model_type' => User::class, 'model_id' => $u->id, 'role_id' => $ridUser], []);
			} elseif (count($existing) > 1) {
				// Enforce single-role: default to 'user' unless admin is present
				$target = $roleIds['admin'] ?? null;
				if (!$target) {
					$target = $roleIds['user'] ?? null;
				}
				if ($target) {
					DB::table('model_has_roles')->where(['model_type' => User::class, 'model_id' => $u->id])->delete();
					DB::table('model_has_roles')->updateOrInsert(['model_type' => User::class, 'model_id' => $u->id, 'role_id' => $target], []);
				}
			}
		}
		// Asignar admin por ENV (CSV) y activarlos si existen
		$adminEmails = (array) config('app.admin_emails', []);
		if (!empty($adminEmails)) {
			$ridAdmin = $roleIds['admin'] ?? null;
			$emailsLower = array_map('strtolower', $adminEmails);

			// Traer usuarios existentes por email (indexados por email lowercase)
			$existing = DB::table('users')
				->whereIn(DB::raw('LOWER(email)'), $emailsLower)
				->get()
				->keyBy(function ($row) {
					return strtolower($row->email);
				});

			foreach ($emailsLower as $email) {
				if (isset($existing[$email])) {
					$u = $existing[$email];
					// Asegurar activo y quitar soft-delete si aplica
					DB::table('users')->where('id', $u->id)->update(['is_active' => true, 'deleted_at' => null, 'updated_at' => now()]);
					$userId = $u->id;
				} else {
					$name = ucfirst(explode('@', $email)[0] ?: 'admin');
					$defaultPassword = (string) config('app.admin_password', 'admin123');
					$userId = DB::table('users')->insertGetId([
						'name' => $name,
						'lastname' => 'Admin',
						'birthdate' => now()->subYears(30)->toDateString(),
						'gender' => 'Hombre',
						'email' => $email,
						'speciality' => 'Especialista',
						'appointment_types' => 'Virtual',
						'location' => 'En algun lugar',
						'password' => \Illuminate\Support\Facades\Hash::make($defaultPassword),
						'email_verified_at' => now(),
						'is_active' => true,
						'created_at' => now(),
						'updated_at' => now(),
					]);
					if ($this->command) {
						$this->command->info("Created admin user: {$email}");
						$this->command->info("  password: {$defaultPassword} (change after deploy)");
					}
				}

				// Asignar rol admin de forma idempotente (y exclusiva)
				if ($ridAdmin) {
					DB::table('model_has_roles')->where(['model_type' => User::class, 'model_id' => $userId])->delete();
					DB::table('model_has_roles')->updateOrInsert(
						['model_type' => User::class, 'model_id' => $userId, 'role_id' => $ridAdmin],
						[]
					);
				}
			}
		}

		// Seed billing plans
		\Database\Seeders\PlansTableSeeder::class && $this->call(\Database\Seeders\PlansTableSeeder::class);

		// Seed menu items (DB-driven left menu)
		if (\Illuminate\Support\Facades\Schema::hasTable('menu_items')) {
			$this->call(\Database\Seeders\MenuItemsSeeder::class);
		}

		// Seed test users (one 'user' and one 'professional') for faster QA flows
		$this->call(\Database\Seeders\TestUsersSeeder::class);
	}
}
