<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\LoginRegisterController;
use App\Http\Controllers\ContactController;

#region index
Route::get('/', function () {
	return view('index');
});
#endregion

#region login, register y logout usan el mismo controlador y view
Route::get('/welcome', function () {
	$signupRoles = \App\Models\Role::query()
		->where('show_in_signup', true)
		->orderBy('name')
		->get(['id','name','signup_label','requires_docs'])
		->map(fn($r) => [
			'value' => (string) $r->id,
			'text' => $r->signup_label ?: $r->name,
			'slug' => $r->name,
			'requires_docs' => (bool) $r->requires_docs,
		])->values()->all();
	return view('loginRegister', compact('signupRoles'));
});

Route::get('/professionalarea', function () {
	return view('professionalArea');
})->middleware(['auth','perm:professionalarea'])->name('professionalarea');

// Professional calendar and related endpoints
Route::prefix('professional')->middleware(['auth','perm:professionalarea'])->group(function(){
	Route::get('/calendar', [\App\Http\Controllers\ProfessionalCalendarController::class, 'index'])->name('professional.calendar');
	// API endpoints for calendar events (initially returns empty list)
	Route::get('/calendar/events', [\App\Http\Controllers\ProfessionalCalendarController::class, 'events'])->name('professional.calendar.events');
	Route::post('/calendar/events', [\App\Http\Controllers\ProfessionalCalendarController::class, 'store'])->name('professional.calendar.events.store');
	Route::get('/calendar/patients', [\App\Http\Controllers\ProfessionalCalendarController::class, 'searchPatients'])->name('professional.calendar.patients');
	// endpoints for patients to accept/reject invitations
	Route::post('/calendar/events/{appointment}/accept', [\App\Http\Controllers\AppointmentController::class, 'accept'])->name('appointments.accept');
	Route::post('/calendar/events/{appointment}/reject', [\App\Http\Controllers\AppointmentController::class, 'reject'])->name('appointments.reject');
});

Route::get('/underreview', function () {
	return view('under_review');
})->name('underreview');

Route::get('/userarea', function () {
	return view('userArea');
})->middleware(['auth','perm:userarea'])->name('userarea');

Route::get('/adminarea', function () {
	$user = auth()->user();
	$totals = [
		'users' => User::count(),
		'active' => User::where('is_active', true)->count(),
		'inactive' => User::where('is_active', false)->count(),
		'deleted' => method_exists(User::class, 'onlyTrashed') ? User::onlyTrashed()->count() : 0,
		'roles' => DB::table('roles')->count(),
		'permissions' => DB::table('permissions')->count(),
		'prof_pending' => \Illuminate\Support\Facades\Schema::hasTable('professional_applications')
			? DB::table('professional_applications')->where('status','pending')->count()
			: 0,
		'byRole' => (function(){
			$sel = [DB::raw('roles.name as slug'), 'roles.name', DB::raw('COUNT(model_has_roles.model_id) as users')];
			$grp = ['roles.id','roles.name'];
			if (Schema::hasColumn('roles','signup_label')) { $sel[] = 'roles.signup_label'; $grp[] = 'roles.signup_label'; } else { $sel[] = DB::raw('NULL as signup_label'); }
			if (Schema::hasColumn('roles','icon_class')) { $sel[] = 'roles.icon_class'; $grp[] = 'roles.icon_class'; } else { $sel[] = DB::raw('NULL as icon_class'); }
			if (Schema::hasColumn('roles','badge_color')) { $sel[] = 'roles.badge_color'; $grp[] = 'roles.badge_color'; } else { $sel[] = DB::raw('NULL as badge_color'); }
			return DB::table('roles')
				->leftJoin('model_has_roles', function($join){
					$join->on('roles.id','=','model_has_roles.role_id')
						->where('model_has_roles.model_type', \App\Models\User::class);
				})
				->select($sel)
				->groupBy($grp)
				->orderBy('roles.name')
				->get();
		})(),
	];
	// Basic health diagnostics
	$health = ['ok' => true, 'messages' => []];
	if (!Schema::hasTable('professional_applications')) {
		$health['ok'] = false;
		$health['messages'][] = 'Falta la tabla professional_applications. Ejecuta: php artisan migrate';
	}
	if (!Schema::hasTable('roles')) {
		$health['ok'] = false;
		$health['messages'][] = 'Falta la tabla roles (Spatie). Publica migraciones y migra: php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider" --tag="permission-migrations" && php artisan migrate';
	} else {
		$needsCols = [];
		foreach (['show_in_signup','requires_docs'] as $col) {
			if (!Schema::hasColumn('roles', $col)) { $needsCols[] = $col; }
		}
		if (!empty($needsCols)) {
			$health['ok'] = false;
			$health['messages'][] = 'Faltan columnas en roles: '.implode(', ', $needsCols).'. Ejecuta migraciones.';
		} else {
			$pro = DB::table('roles')->where('name','professional')->first();
			if (!$pro) {
				$health['ok'] = false;
				$health['messages'][] = 'No existe el rol "professional". Ejecuta: php artisan migrate --seed';
			} elseif (!(bool)($pro->requires_docs ?? false)) {
				$health['ok'] = false;
				$health['messages'][] = 'El rol "professional" no tiene requires_docs=1. Ejecuta: php artisan migrate --seed';
			}
		}
	}
	foreach (['permissions','role_has_permissions','model_has_roles','model_has_permissions'] as $tbl) {
		if (!Schema::hasTable($tbl)) {
			$health['ok'] = false;
			$health['messages'][] = "Falta la tabla {$tbl}. Ejecuta: php artisan migrate";
		}
	}
	// Dynamic quick-access areas: any permission whose name is a route name without dots
	$areas = [];
	$perms = \Spatie\Permission\Models\Permission::orderBy('name')->get(['name']);
	foreach ($perms as $perm) {
		$name = $perm->name;
		if (strpos($name, '.') !== false) continue; // skip nested admin.* routes
		if (!\Illuminate\Support\Facades\Route::has($name)) continue;
		if (!$user || !$user->can($name)) continue;
		$label = match($name) {
			'adminarea' => 'Admin',
			'professionalarea' => 'Professional',
			'userarea' => 'Usuario',
			default => ucfirst(str_replace(['_', '-'], ' ', $name)),
		};
		$btn = match($name) {
			'adminarea' => 'btn-outline-dark',
			'professionalarea' => 'btn-outline-success',
			'userarea' => 'btn-outline-primary',
			default => 'btn-outline-secondary',
		};
		$areas[] = ['name' => $name, 'label' => $label, 'btn' => $btn];
	}
	return view('adminArea', compact('totals','areas','health'));
})->middleware(['auth','perm:adminarea'])->name('adminarea');

// Admin: gestión de usuarios y roles
Route::prefix('admin')->middleware(['auth','perm:adminarea'])->group(function(){
	Route::get('/users', [\App\Http\Controllers\AdminController::class, 'users'])->name('admin.users');
	Route::post('/users/{user}/toggle', [\App\Http\Controllers\AdminController::class, 'toggleActive'])->name('admin.users.toggle');
	Route::post('/users/{user}/roles', [\App\Http\Controllers\AdminController::class, 'assignRoles'])->name('admin.users.roles');
	Route::post('/users/{user}/ban', [\App\Http\Controllers\AdminController::class, 'toggleBan'])->name('admin.users.ban');
	Route::delete('/users/{user}', [\App\Http\Controllers\AdminController::class, 'destroy'])->name('admin.users.destroy');

	// Roles CRUD
	Route::get('/roles', [\App\Http\Controllers\RbacController::class, 'rolesIndex'])->name('admin.roles.index');
	Route::post('/roles', [\App\Http\Controllers\RbacController::class, 'rolesStore'])->name('admin.roles.store');
	Route::put('/roles/{role}', [\App\Http\Controllers\RbacController::class, 'rolesUpdate'])->name('admin.roles.update');
	Route::delete('/roles/{role}', [\App\Http\Controllers\RbacController::class, 'rolesDestroy'])->name('admin.roles.destroy');
	Route::post('/roles/{role}/permissions', [\App\Http\Controllers\RbacController::class, 'rolesSyncPermissions'])->name('admin.roles.permissions');

	// Permissions CRUD
	Route::get('/permissions', [\App\Http\Controllers\RbacController::class, 'permsIndex'])->name('admin.permissions.index');
	Route::post('/permissions', [\App\Http\Controllers\RbacController::class, 'permsStore'])->name('admin.permissions.store');
	Route::put('/permissions/{permission}', [\App\Http\Controllers\RbacController::class, 'permsUpdate'])->name('admin.permissions.update');
	Route::delete('/permissions/{permission}', [\App\Http\Controllers\RbacController::class, 'permsDestroy'])->name('admin.permissions.destroy');

	// Professional applications review
	Route::get('/professional-applications', [\App\Http\Controllers\ProfessionalApplicationController::class, 'index'])->name('admin.profapps.index');
	Route::post('/professional-applications/{application}/approve', [\App\Http\Controllers\ProfessionalApplicationController::class, 'approve'])->name('admin.profapps.approve');
	Route::post('/professional-applications/{application}/reject', [\App\Http\Controllers\ProfessionalApplicationController::class, 'reject'])->name('admin.profapps.reject');
	Route::get('/professional-applications/{application}/file/{field}', [\App\Http\Controllers\ProfessionalApplicationController::class, 'file'])->name('admin.profapps.file');
});

Route::post('/login', [LoginRegisterController::class, 'login'])->name('login');
Route::post('/register', [LoginRegisterController::class, 'register'])->name('register');
Route::post('/logout', [LoginRegisterController::class, 'logout'])->name('logout');
// Safe GET fallbacks to avoid 405 on GET /login or GET /register
Route::get('/login', fn () => redirect('/welcome'));
Route::get('/register', fn () => redirect('/welcome'));
#endregion

#region contact
Route::get('/contact', function () {
	return view('contact');
})->name('contact');
Route::post('/contact', [ContactController::class, 'send'])->name('contact.send');
#endregion

#region services
Route::get('/services', function () {
	return view('services');
})->name('services');
#endregion

#region services
Route::get('/services', function () {
	return view('services');
})->name('services');
#endregion

#region about
Route::get('/about', function () {
	return view('about');
})->name('about');
#endregion

// User notifications listing (simple)
Route::middleware('auth')->group(function(){
	Route::get('/notifications', function(){
		$user = auth()->user();
		if (!\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
			return view('notifications', ['notifications' => collect()]);
		}
		$notes = $user->notifications()->latest()->limit(50)->get();
		return view('notifications', ['notifications' => $notes]);
	})->name('notifications.index');

	Route::post('/notifications/mark-read', function(){
		$user = auth()->user();
		if (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
			$user->unreadNotifications->markAsRead();
		}
		return redirect()->back();
	})->name('notifications.markread');
});