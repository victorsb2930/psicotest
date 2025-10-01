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

// Public route: show the "under review" page even for guests. When a user
// with a professional account isn't active yet they are redirected here from
// the login flow; the route must be reachable without authentication.
Route::get('/underreview', function () {
    return view('under_review');
})->name('underreview');

// Show details for a rejected professional application.
// Access policy:
// - Authenticated owner may view their own application.
// - The login flow will set a one-time session flash key allowing a
//   redirect to this page immediately after an authentication attempt.
// - AJAX/JS flows receive a temporary signed URL to follow directly.
// Direct unauthenticated requests without a valid signature or session
// flash will be rejected (403).
use Illuminate\Http\Request;

// Apply security response headers to all web routes (prevents a class of XSS/iframe/etc issues)
Route::middleware(['security.headers'])->group(function(){
Route::get('/underreview/rejected/{application}', function (Request $request, \App\Models\ProfessionalApplication $application) {
	$user = auth()->user();
	$isOwner = $user && $user->id === $application->user_id;

	// Allow if authenticated owner
	if ($isOwner) {
		return view('under_review_rejected', compact('application', 'isOwner'));
	}

	// Allow if request has a valid signed URL (used by AJAX responses or emails)
	if ($request->hasValidSignature()) {
		return view('under_review_rejected', compact('application', 'isOwner'));
	}

	// Allow if the previous redirect set a one-time flash flag for this application
	$allowedFlash = session('allow_rejected_view') == $application->id;
	if ($allowedFlash) {
		return view('under_review_rejected', compact('application', 'isOwner'));
	}

	abort(403, 'Acceso no autorizado');
})->name('underreview.rejected');


// Protected routes: require authentication (whitelist public pages below)
Route::middleware(['auth'])->group(function(){
		// Device management endpoints: list and revoke
		Route::get('/user/devices', function(){
			$user = auth()->user();
			$devices = \App\Models\UserDevice::where('user_id', $user->id)->orderBy('last_seen_at','desc')->get();
			return view('user.devices', compact('devices'));
		})->name('user.devices');

		Route::post('/user/devices/{device}/revoke', function(\Illuminate\Http\Request $r, \App\Models\UserDevice $device){
			$user = auth()->user();
			if ($device->user_id !== $user->id) abort(403);
			$device->revoked_at = now();
			$device->save();
			// Also revoke any user_logins that reference this token
			try { \App\Models\UserLogin::where('browser_token_hash', $device->token_hash)->update(['ended_at' => now(), 'duration_seconds' => null]); } catch (\Throwable $_) {}
			return redirect()->back()->with('success','Dispositivo revocado');
		})->name('user.devices.revoke');

		// Revoke all devices for the current user
		Route::post('/user/devices/revoke-all', function(\Illuminate\Http\Request $r){
			$user = auth()->user();
			if (!$user) abort(401);
			try {
				\App\Models\UserDevice::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
				// also close any user_logins referencing those tokens
				try { \App\Models\UserLogin::where('user_id', $user->id)->update(['ended_at' => now(), 'duration_seconds' => null]); } catch (\Throwable $_) {}
			} catch (\Throwable $_) {}
			return redirect()->back()->with('success','Todos los dispositivos han sido revocados');
		})->name('user.devices.revoke_all');
	Route::get('/professionalarea', function () {
		return view('professionalArea');
	})->middleware(['perm:professionalarea'])->name('professionalarea');

	// Professional calendar and related endpoints
	Route::prefix('professional')->middleware(['perm:professionalarea'])->group(function(){
		Route::get('/calendar', [\App\Http\Controllers\ProfessionalCalendarController::class, 'index'])->name('professional.calendar');
		// API endpoints for calendar events (initially returns empty list)
		Route::get('/calendar/events', [\App\Http\Controllers\ProfessionalCalendarController::class, 'events'])->name('professional.calendar.events');
		Route::post('/calendar/events', [\App\Http\Controllers\ProfessionalCalendarController::class, 'store'])->name('professional.calendar.events.store');
		Route::get('/calendar/patients', [\App\Http\Controllers\ProfessionalCalendarController::class, 'searchPatients'])->name('professional.calendar.patients');
		// endpoints for patients to accept/reject invitations
		Route::post('/calendar/events/{appointment}/accept', [\App\Http\Controllers\AppointmentController::class, 'accept'])->name('appointments.accept');
		Route::post('/calendar/events/{appointment}/reject', [\App\Http\Controllers\AppointmentController::class, 'reject'])->name('appointments.reject');
	});

});

	// Professionals search - require authentication and permission
	Route::middleware(['perm:userarea'])->group(function(){
		Route::get('/professionals', [\App\Http\Controllers\ProfessionalSearchController::class, 'index'])->name('professionals.index');
		Route::get('/professionals/search', [\App\Http\Controllers\ProfessionalSearchController::class, 'search'])->name('professionals.search');
        // Show public profile page for a professional
        Route::get('/professional/profile/{id}', [\App\Http\Controllers\ProfessionalSearchController::class, 'show'])->name('professionals.show');
	});

	Route::get('/userarea', function () {
		return view('userArea');
	})->middleware(['perm:userarea'])->name('userarea');

	// Perfil del usuario
	Route::get('/perfil', function(){ return view('profile'); })->name('profile')->middleware('auth');

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
		Route::get('/users/{user}/sessions', [\App\Http\Controllers\AdminController::class, 'sessions'])->name('admin.users.sessions');
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

		// Admin: Device management (global) - listar y revocar dispositivos de cualquier usuario
		Route::get('/devices', function(){
			$devices = \App\Models\UserDevice::with('user')->orderBy('last_seen_at','desc')->paginate(50);
			return view('admin.devices.index', compact('devices'));
		})->name('admin.devices');

		Route::post('/devices/{device}/revoke', function(\Illuminate\Http\Request $r, \App\Models\UserDevice $device){
			// mark revoked
			$device->revoked_at = now();
			$device->save();
			// optionally close user logins referencing this token
			try { \App\Models\UserLogin::where('browser_token_hash', $device->token_hash)->update(['ended_at' => now(), 'duration_seconds' => null]); } catch (\Throwable $_) {}
			return redirect()->back()->with('success','Dispositivo revocado');
		})->name('admin.devices.revoke');

		// Revoke all devices for a given user (admin action)
		Route::post('/devices/user/{user}/revoke-all', function(\Illuminate\Http\Request $r, \App\Models\User $user){
			try { \App\Models\UserDevice::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]); } catch (\Throwable $_) {}
			try { \App\Models\UserLogin::where('user_id', $user->id)->update(['ended_at' => now(), 'duration_seconds' => null]); } catch (\Throwable $_) {}
			return redirect()->back()->with('success','Todos los dispositivos del usuario han sido revocados');
		})->name('admin.devices.revoke_user_all');

});

Route::post('/login', [LoginRegisterController::class, 'login'])->name('login');
Route::post('/register', [LoginRegisterController::class, 'register'])->name('register');
Route::get('/auth/public-key', [LoginRegisterController::class, 'publicKey'])->name('auth.publickey');
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

	// Logout should require authentication
	Route::post('/logout', [LoginRegisterController::class, 'logout'])->name('logout');

	// Endpoint to record session end (used by client sendBeacon on page unload)
	Route::post('/sessions/end', [LoginRegisterController::class, 'endSession'])->name('sessions.end');

	// User photos management
	Route::get('/profile/photos', [\App\Http\Controllers\UserPhotoController::class, 'index'])->name('profile.photos.index');
	Route::post('/profile/photos', [\App\Http\Controllers\UserPhotoController::class, 'store'])->name('profile.photos.store');
	Route::post('/profile/photos/{photo}/set-profile', [\App\Http\Controllers\UserPhotoController::class, 'setProfile'])->name('profile.photos.set');
	Route::delete('/profile/photos/{photo}', [\App\Http\Controllers\UserPhotoController::class, 'destroy'])->name('profile.photos.destroy');

	// Presence update
	Route::post('/profile/presence', function(\Illuminate\Http\Request $request){
		$user = auth()->user();
		$status = (string) $request->input('status','online');
		$allowed = ['online','busy','dnd','away','offline'];
		if (!in_array($status, $allowed, true)) $status = 'online';
		$user->status = $status;
		try { $user->save(); } catch(\Throwable$e) {}
		return response()->json(['ok'=>true,'status'=>$status]);
	})->name('profile.presence');

	// Return current authenticated user's status (used by polling on /perfil)
	Route::get('/profile/status', function(){
		$user = auth()->user();
		if (!$user) return response()->json(['ok'=>false], 401);
		$lastSeen = null;
		if (isset($user->last_seen_at)) {
			if ($user->last_seen_at instanceof \DateTimeInterface) {
				$lastSeen = $user->last_seen_at->format('Y-m-d H:i:s');
			} else {
				$lastSeen = is_string($user->last_seen_at) && $user->last_seen_at !== '' ? $user->last_seen_at : null;
			}
		}
		return response()->json(['ok'=>true,'status'=>$user->status ?? 'offline','last_seen_at'=>$lastSeen]);
	})->name('profile.status');

	// Heartbeat/keepalive endpoint for presence (called periodically by the client)
	Route::post('/profile/heartbeat', function(\Illuminate\Http\Request $request){
		$user = auth()->user();
		if (!$user) return response()->json(['ok'=>false], 401);
		try {
			$user->last_seen_at = now();
			$user->saveQuietly();

			// Reopen a user_logins row that was closed very recently for this same
			// session id. This handles page reloads (F5) where the client
			// previously sent a sendBeacon() on unload and the server closed the
			// session. If the user reloads, we want to treat it as the same
			// session rather than two separate sessions. The grace window is
			// configurable via SESSION_REOPEN_GRACE_SECONDS (default 30s).
			try {
				if (\Illuminate\Support\Facades\Schema::hasTable('user_logins')) {
					$sid = $request->getSession()->getId();
					// Try to find an open row by session_id first
					$open = \Illuminate\Support\Facades\DB::table('user_logins')->where('session_id', $sid)->whereNull('ended_at')->orderBy('id','desc')->first();
					if ($open) {
						// nothing to do - session already open
					} else {
						// No open row for this session: prefer to reopen by recent ended_at
						$ul = \Illuminate\Support\Facades\DB::table('user_logins')->where('session_id', $sid)->orderBy('id','desc')->first();
						if ($ul && $ul->ended_at) {
							$grace = (int) env('SESSION_REOPEN_GRACE_SECONDS', 30);
							$endedTs = $ul->ended_at ? strtotime((string) $ul->ended_at) : null;
							if ($endedTs !== null && ($endedTs >= (time() - $grace))) {
								// reopen recent
								\Illuminate\Support\Facades\DB::table('user_logins')->where('id', $ul->id)->update(['ended_at' => null, 'duration_seconds' => null]);
								try { \Illuminate\Support\Facades\Log::info('user_login.reopened_by_heartbeat', ['user_login_id' => $ul->id, 'session_id' => $sid, 'user_id' => $user->id ?? null]); } catch (\Throwable $_) {}
							}
						}
						// If still nothing open, try reopen by browser token hash (cookie)
						$cookieName = env('BROWSER_TOKEN_COOKIE_NAME', 'psg_browser_token');
						$token = $request->cookie($cookieName);
						if (!empty($token)) {
							$hash = hash_hmac('sha256', $token, config('app.key'));
							try {
								// find most recent row for this user with matching token hash
								$found = \Illuminate\Support\Facades\DB::table('user_logins')
									->where('user_id', $user->id)
									->where('browser_token_hash', $hash)
									->orderBy('id','desc')
									->first();
								if ($found) {
									// ensure the device record isn't revoked
									$dev = null;
									try { $dev = \App\Models\UserDevice::where('token_hash', $hash)->where('user_id', $user->id)->first(); } catch (\Throwable $_) { $dev = null; }
									if ($dev && $dev->revoked_at) {
										try { \Illuminate\Support\Facades\Log::warning('user_login.reopen_blocked_revoked_device', ['device_id' => $dev->id, 'user_id' => $user->id]); } catch (\Throwable $_) {}
										$found = null;
									}
									// Mitigation: only reopen when the request's User-Agent matches
									// the stored one, unless the environment disables this strict check.
									$strictUa = filter_var(env('BROWSER_TOKEN_STRICT_UA', true), FILTER_VALIDATE_BOOLEAN);
									$strictIp = filter_var(env('BROWSER_TOKEN_STRICT_IP', true), FILTER_VALIDATE_BOOLEAN);
									$reqUa = $request->userAgent() ?? '';
									$storedUa = $found->user_agent ?? '';
									$uaMatches = $storedUa !== '' && $reqUa === $storedUa;
									$reqIp = $request->ip() ?? '';
									$storedIp = $found->ip_address ?? '';
									$ipMatches = $storedIp !== '' && $reqIp === $storedIp;
									$allowUa = !$strictUa || $uaMatches;
									$allowIp = !$strictIp || $ipMatches;
									if ($allowUa && $allowIp) {
										// reopen and update session_id
										\Illuminate\Support\Facades\DB::table('user_logins')->where('id', $found->id)->update(['ended_at' => null, 'duration_seconds' => null, 'session_id' => $sid]);
										try { \Illuminate\Support\Facades\Log::info('user_login.reopened_by_token', ['user_login_id' => $found->id, 'session_id' => $sid, 'user_id' => $user->id ?? null]); } catch (\Throwable $_) {}
										$open = true;
										// update device last seen
										try {
											// derive a friendly name if device record has no name
											$derived = \App\Models\UserDevice::friendlyNameFromUserAgent($reqUa);
											$upd = ['last_seen_at' => now(), 'ip_address' => $reqIp, 'user_agent' => $reqUa];
											if ($derived) $upd['name'] = \DB::raw("COALESCE(name, '" . addslashes($derived) . "')");
											// Use a raw update when setting COALESCE to avoid overwriting existing names
											try {
												if (isset($upd['name'])) {
													\App\Models\UserDevice::where('token_hash', $hash)->where('user_id', $user->id)->update(['last_seen_at' => now(), 'ip_address' => $reqIp, 'user_agent' => $reqUa, 'name' => $derived]);
												} else {
													\App\Models\UserDevice::where('token_hash', $hash)->where('user_id', $user->id)->update($upd);
												}
											} catch (\Throwable $_inner) {
												// fallback to simple update
												try { \App\Models\UserDevice::where('token_hash', $hash)->where('user_id', $user->id)->update(['last_seen_at' => now(), 'ip_address' => $reqIp, 'user_agent' => $reqUa]); } catch (\Throwable $_) {}
											}
										} catch (\Throwable $_) {}
									} else {
										// Token matched but policy failed. If UA matches but IP differs, require a one-time 2FA code
										// to confirm reopening (allows users who changed networks but use the same browser).
										$reqUa = $request->userAgent() ?? '';
										$reqIp = $request->ip() ?? '';
										$uaMatches = $storedUa !== '' && $reqUa === $storedUa;
										$ipMatches = $storedIp !== '' && $reqIp === $storedIp;

										if ($uaMatches && !$ipMatches) {
											// Generate a 6-digit code and cache it tied to user+token hash
											try {
												$code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
												$cacheKey = 'reopen_2fa:' . $user->id . ':' . $hash;
												\Illuminate\Support\Facades\Cache::put($cacheKey, $code, now()->addMinutes(10));
												$u = auth()->user();
												if ($u) {
													$u->notify(new \App\Notifications\DeviceReopen2FA($code, $reqIp, $reqUa));
												}
												try { \Illuminate\Support\Facades\Log::info('user_login.reopen_2fa_sent', ['user_id' => $user->id ?? null, 'req_ip' => $reqIp, 'stored_ip' => $storedIp, 'req_ua' => substr($reqUa,0,200)]); } catch (\Throwable $_) {}
											} catch (\Throwable $_) { /* ignore generation/notify failures */ }
											// Tell the client it must confirm with the 2FA code
											return response()->json(['ok' => true, 'two_factor_required' => true]);
										} else {
											// UA doesn't match or other mismatch: treat as suspicious and notify user
											try { \Illuminate\Support\Facades\Log::warning('user_login.token_reuse_suspicious', ['found_id' => $found->id, 'session_id' => $sid, 'user_id' => $user->id ?? null, 'req_ua' => substr($reqUa,0,200), 'stored_ua' => substr($storedUa,0,200), 'req_ip' => $reqIp, 'stored_ip' => $storedIp]); } catch (\Throwable $_) {}
											try { $u = auth()->user(); if ($u) $u->notify(new \App\Notifications\DeviceSuspiciousAttempt($reqIp, $reqUa)); } catch (\Throwable $_) {}
										}
									}
								}
							} catch (\Throwable $_) { /* ignore */ }
						}
						// If still no open row, create one and persist the token hash if present
						if (empty($open)) {
							try {
								$ip = $request->ip();
								$ua = $request->userAgent();
								$newId = \App\Models\UserLogin::create(['user_id' => $user->id, 'session_id' => $sid, 'ip_address' => $ip, 'user_agent' => $ua, 'started_at' => now(), 'browser_token_hash' => isset($hash) ? $hash : null])->id;
								try { \Illuminate\Support\Facades\Log::info('user_login.created_by_heartbeat', ['user_login_id' => $newId, 'session_id' => $sid, 'user_id' => $user->id ?? null]); } catch (\Throwable $_) {}
							} catch (\Throwable $_) { /* ignore create failures */ }
						}
					}
				}
			} catch (\Throwable $_) { /* ignore reopen failures */ }
		} catch (\Throwable$e) { /* noop */ }
		return response()->json(['ok'=>true,'ts'=>now()->toDateTimeString()]);
	})->name('profile.heartbeat');

	// Endpoint to confirm a reopen using the 2FA code sent by email
	Route::post('/profile/heartbeat/confirm', function(\Illuminate\Http\Request $request){
		$user = auth()->user();
		if (!$user) return response()->json(['ok'=>false], 401);
		$cookieName = env('BROWSER_TOKEN_COOKIE_NAME', 'psg_browser_token');
		$token = $request->cookie($cookieName);
		if (empty($token)) return response()->json(['ok'=>false,'message'=>'no_token'], 400);
		$hash = hash_hmac('sha256', $token, config('app.key'));
		$code = $request->input('code');
		if (!$code) return response()->json(['ok'=>false,'message'=>'no_code'], 400);
		// Check block record
		try {
			$block = \App\Models\DeviceReopenBlock::where('user_id', $user->id)->where(function($q) use ($hash){ $q->whereNull('token_hash')->orWhere('token_hash', $hash); })->orderBy('id','desc')->first();
			if ($block) {
				if ($block->permanent) return response()->json(['ok'=>false,'message'=>'blocked_permanent'], 403);
				if ($block->blocked_until && \Illuminate\Support\Carbon::now()->lessThan($block->blocked_until)) return response()->json(['ok'=>false,'message'=>'blocked_until','until' => $block->blocked_until->toDateTimeString()], 403);
			}
		} catch (\Throwable $_) { $block = null; }

		$cacheKey = 'reopen_2fa:' . $user->id . ':' . $hash;
		$expected = \Illuminate\Support\Facades\Cache::get($cacheKey);
		if (!$expected || !hash_equals((string)$expected, (string)$code)) {
			// log failed attempt
			try { \App\Models\DeviceReopenAttempt::create(['user_id' => $user->id, 'token_hash' => $hash, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'success' => false, 'action' => 'confirm']); } catch (\Throwable $_) {}
			// increment attempt counter and enforce blocks
			try {
				$ctrKey = 'reopen_attempts:' . $user->id . ':' . $hash;
				$cnt = (int) (\Illuminate\Support\Facades\Cache::get($ctrKey, 0) + 1);
				\Illuminate\Support\Facades\Cache::put($ctrKey, $cnt, now()->addHours(2));
				if ($cnt >= 15) {
					\App\Models\DeviceReopenBlock::create(['user_id'=>$user->id,'token_hash'=>$hash,'permanent'=>true,'blocked_until'=>null]);
				} elseif ($cnt >= 10) {
					\App\Models\DeviceReopenBlock::create(['user_id'=>$user->id,'token_hash'=>$hash,'permanent'=>false,'blocked_until'=>now()->addHour()]);
				} elseif ($cnt >= 5) {
					\App\Models\DeviceReopenBlock::create(['user_id'=>$user->id,'token_hash'=>$hash,'permanent'=>false,'blocked_until'=>now()->addMinutes(15)]);
				}
			} catch (\Throwable $_) {}
			return response()->json(['ok'=>false,'message'=>'invalid_code'], 400);
		}
		// valid: reopen most recent user_logins row for this token
		try {
			$found = \Illuminate\Support\Facades\DB::table('user_logins')->where('user_id', $user->id)->where('browser_token_hash', $hash)->orderBy('id','desc')->first();
			if ($found) {
				$sid = $request->getSession()->getId();
				\Illuminate\Support\Facades\DB::table('user_logins')->where('id', $found->id)->update(['ended_at' => null, 'duration_seconds' => null, 'session_id' => $sid]);
				try { \Illuminate\Support\Facades\Log::info('user_login.reopened_by_2fa', ['user_login_id' => $found->id, 'user_id' => $user->id ?? null]); } catch (\Throwable $_) {}
				// update device last seen
				try {
					$derived = \App\Models\UserDevice::friendlyNameFromUserAgent($request->userAgent());
					if ($derived) {
						\App\Models\UserDevice::where('token_hash', $hash)->where('user_id', $user->id)->update(['last_seen_at' => now(), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'name' => $derived]);
					} else {
						\App\Models\UserDevice::where('token_hash', $hash)->where('user_id', $user->id)->update(['last_seen_at' => now(), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
					}
				} catch (\Throwable $_) {}
			}
		} catch (\Throwable $_) { /* ignore */ }
		// consume code
		\Illuminate\Support\Facades\Cache::forget($cacheKey);
		try { \App\Models\DeviceReopenAttempt::create(['user_id' => $user->id, 'token_hash' => $hash, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'success' => true, 'action' => 'confirm']); } catch (\Throwable $_) {}
		return response()->json(['ok'=>true]);
	})->name('profile.heartbeat.confirm');

	// Resend 2FA code endpoint (rate-limited per user+token)
	Route::post('/profile/heartbeat/resend', function(\Illuminate\Http\Request $request){
		$user = auth()->user();
		if (!$user) return response()->json(['ok'=>false], 401);
		$cookieName = env('BROWSER_TOKEN_COOKIE_NAME', 'psg_browser_token');
		$token = $request->cookie($cookieName);
		if (empty($token)) return response()->json(['ok'=>false,'message'=>'no_token'], 400);
		$hash = hash_hmac('sha256', $token, config('app.key'));
		$cacheKeyRate = 'reopen_2fa_rate:' . $user->id . ':' . $hash;
		if (\Illuminate\Support\Facades\Cache::has($cacheKeyRate)) {
			return response()->json(['ok'=>false,'message'=>'rate_limited'], 429);
		}
		// check if blocked
		try {
			$block = \App\Models\DeviceReopenBlock::where('user_id', $user->id)->where(function($q) use ($hash){ $q->whereNull('token_hash')->orWhere('token_hash', $hash); })->orderBy('id','desc')->first();
			if ($block) {
				if ($block->permanent) return response()->json(['ok'=>false,'message'=>'blocked_permanent'], 403);
				if ($block->blocked_until && \Illuminate\Support\Carbon::now()->lessThan($block->blocked_until)) return response()->json(['ok'=>false,'message'=>'blocked_until','until' => $block->blocked_until->toDateTimeString()], 403);
			}
		} catch (\Throwable $_) { /* ignore */ }

		try {
			$code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
			$cacheKey = 'reopen_2fa:' . $user->id . ':' . $hash;
			\Illuminate\Support\Facades\Cache::put($cacheKey, $code, now()->addMinutes(10));
			\Illuminate\Support\Facades\Cache::put($cacheKeyRate, true, now()->addMinutes(2)); // allow resend every 2 minutes
			$method = $request->input('method','email');
			if ($method === 'sms' && !empty($user->phone) && env('TWILIO_SID') && env('TWILIO_TOKEN') && env('TWILIO_FROM')) {
				try {
					$client = new \Twilio\Rest\Client(env('TWILIO_SID'), env('TWILIO_TOKEN'));
					$client->messages->create($user->phone, ['from' => env('TWILIO_FROM'), 'body' => "Código 2FA: {$code}"]); 
				} catch (\Throwable $_) {
					// fallback to email if SMS fails
					$u = auth()->user(); if ($u) $u->notify(new \App\Notifications\DeviceReopen2FA($code, $request->ip(), $request->userAgent()));
				}
			} else {
				$u = auth()->user(); if ($u) $u->notify(new \App\Notifications\DeviceReopen2FA($code, $request->ip(), $request->userAgent()));
			}
			try { \App\Models\DeviceReopenAttempt::create(['user_id' => $user->id, 'token_hash' => $hash, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'success' => null, 'action' => 'resend']); } catch (\Throwable $_) {}
		} catch (\Throwable $_) { /* ignore */ }
		return response()->json(['ok'=>true]);
	})->name('profile.heartbeat.resend');

	// Admin endpoint to unlock a user's blocks (requires admin permission)
	Route::post('/admin/users/{user}/unlock-reopen', function(\Illuminate\Http\Request $r, \App\Models\User $user){
		$actor = auth()->user();
		if (!$actor || !$actor->can('adminarea')) abort(403);
		$tokenHash = $r->input('token_hash');
		try {
			\App\Models\DeviceReopenBlock::where('user_id', $user->id)->where(function($q) use($tokenHash){ if ($tokenHash) $q->where('token_hash', $tokenHash); })->update(['admin_unlocked_by' => $actor->id, 'admin_unlocked_at' => now(), 'permanent' => false, 'blocked_until' => null]);
		} catch (\Throwable $_) {}
		return redirect()->back()->with('success','Bloqueos eliminados (registro marcado para revisión por admin)');
	})->name('admin.users.unlock_reopen');

	// User appointments (calendar view for normal users)
	Route::get('/appointments', [\App\Http\Controllers\UserAppointmentController::class, 'index'])->name('appointments.index');
	Route::get('/appointments/events', [\App\Http\Controllers\UserAppointmentController::class, 'events'])->name('appointments.events');
	// Create appointment (patient requests a new appointment)
	Route::post('/appointments', [\App\Http\Controllers\UserAppointmentController::class, 'store'])->name('appointments.store');
	// Patient accept/reject endpoints (calls to AppointmentController)
	Route::post('/appointments/{appointment}/accept', [\App\Http\Controllers\AppointmentController::class, 'accept'])->name('appointments.patient.accept');
	Route::post('/appointments/{appointment}/reject', [\App\Http\Controllers\AppointmentController::class, 'reject'])->name('appointments.patient.reject');

	// Simple messaging (user to user)
	Route::get('/messages', [\App\Http\Controllers\MessagesController::class, 'index'])->name('messages.index');
	Route::get('/messages/thread/{user}', [\App\Http\Controllers\MessagesController::class, 'thread'])->name('messages.thread');
	Route::post('/messages/thread/{user}', [\App\Http\Controllers\MessagesController::class, 'send'])->name('messages.send');

	// Friend requests
	Route::post('/friend/{user}/request', [\App\Http\Controllers\FriendRequestController::class, 'send'])->name('friend.request');
	Route::post('/friend/request/{requestModel}/accept', [\App\Http\Controllers\FriendRequestController::class, 'accept'])->name('friend.request.accept');
	Route::post('/friend/request/{requestModel}/reject', [\App\Http\Controllers\FriendRequestController::class, 'reject'])->name('friend.request.reject');
	Route::get('/friend/requests/pending', [\App\Http\Controllers\FriendRequestController::class, 'pending'])->name('friend.requests.pending');
});

	// Simple JSON endpoints for AJAX notifications polling and marking
	Route::get('/api/notifications/unread', function(){
		$user = auth()->user();
		if (!\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
			return response()->json(['count' => 0, 'items' => []]);
		}
		$items = $user->unreadNotifications()->latest()->limit(10)->get()->map(function($n){
			$data = is_array($n->data) ? $n->data : (array)$n->data;
			return [
				'id' => $n->id,
				'data' => $data,
				'time' => $n->created_at->diffForHumans(),
			];
		});
		return response()->json(['count' => $user->unreadNotifications()->count(), 'items' => $items]);
	});

	Route::post('/api/notifications/mark-read', function(\Illuminate\Http\Request $r){
		$user = auth()->user();
		$id = $r->input('id');
		if ($id) {
			$notif = $user->unreadNotifications()->where('id', $id)->first();
			if ($notif) { $notif->markAsRead(); }
		}
		return response()->json(['ok' => true]);
	});

	Route::post('/api/notifications/mark-read-all', function(){
		$user = auth()->user();
		$user->unreadNotifications->markAsRead();
		return response()->json(['ok' => true]);
	});