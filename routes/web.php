<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\LoginRegisterController;
use App\Http\Controllers\ContactController;

#region index
Route::get('/', function () {
	return view('index');
});

// Professional applications routes with flexible access (no prefix wrapper)
// allow users who have 'professional_applications' permission
Route::middleware(['auth','perm:professional_applications'])->group(function(){
	Route::get('/admin/professional-applications', [\App\Http\Controllers\ProfessionalApplicationController::class, 'index'])->name('admin.profapps.index');
	Route::post('/admin/professional-applications/{application}/approve', [\App\Http\Controllers\ProfessionalApplicationController::class, 'approve'])->name('admin.profapps.approve');
	Route::post('/admin/professional-applications/{application}/reject', [\App\Http\Controllers\ProfessionalApplicationController::class, 'reject'])->name('admin.profapps.reject');
	Route::get('/admin/professional-applications/{application}/file/{field}', [\App\Http\Controllers\ProfessionalApplicationController::class, 'file'])->name('admin.profapps.file');
	Route::post('/admin/professional-applications/{application}/doc-view', [\App\Http\Controllers\ProfessionalApplicationController::class, 'markDocViewed'])->name('admin.profapps.docview');
});
#endregion

#region login, register y logout usan el mismo controlador y view
Route::get('/welcome', function () {
	// Si ya está autenticado, redirigir a su área (evita ver formulario login/register con #registro)
	if (auth()->check()) {
		$user = auth()->user();
		if ($user->hasRole('admin')) return redirect()->route('adminarea');
		if ($user->hasRole('professional')) return redirect()->route('professionalarea');
		return redirect()->route('userarea');
	}
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
})->name('welcome');

// Public route: show the "under review" page even for guests. When a user
// with a professional account isn't active yet they are redirected here from
// the login flow; the route must be reachable without authentication.
Route::get('/underreview', function () {
    return view('under_review');
})->name('underreview');

// Email verification notice and actions (no auth required)
Route::get('/email/verification-notice', function(){
	return view('auth.verify_notice');
})->name('verification.notice');

// Resend verification email (adds debug logging for SMTP failures) - now uses one-time token stored on user
Route::post('/email/verification-notification', function(\Illuminate\Http\Request $r){
	$email = (string) $r->input('email');
	if ($email === '') {
		return back()->with('error','Ingresa tu email para reenviar el enlace.');
	}
	$user = \App\Models\User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
	if (!$user) {
		// Do not leak account existence
		return back()->with('info','Si existe una cuenta, se enviará el enlace.');
	}
	if (!empty($user->email_verified_at)) {
		return back()->with('success','Tu email ya está verificado.');
	}
	// Generar nuevo token único (invalida cualquier anterior)
	$user->email_verification_token = Str::random(40);
	$user->email_verification_token_expires_at = now()->addDay(1);
	try { $user->save(); } catch (\Throwable $_) {}
	$url = url('/email/verify/'.$user->id.'/'.$user->email_verification_token);
	$fromAddr = config('mail.from.address');
	$fromName = config('mail.from.name');
	try {
		\Log::info('verification.email.attempt', [
			'to'=>$user->email,
			'from'=>$fromAddr,
			'mailer'=>config('mail.default'),
			'host'=>config('mail.mailers.'.config('mail.default').'.host'),
			'port'=>config('mail.mailers.'.config('mail.default').'.port'),
			'encryption'=>config('mail.mailers.'.config('mail.default').'.encryption'),
		]);
		\Mail::raw('Verifica tu email con este enlace: '.$url, function($m) use ($user,$fromAddr,$fromName){
			if ($fromAddr) { $m->from($fromAddr, $fromName ?: null); }
			$m->to($user->email)->subject('Verifica tu email');
		});
		// Symfony Mailer in Laravel doesn't populate Mail::failures(); mimic legacy check by catching exceptions.
		\Log::info('verification.email.sent', ['to'=>$user->email]);
	} catch (\Throwable $e) {
		\Log::error('verification.email.error', ['to'=>$user->email,'error'=>$e->getMessage()]);
		return back()->with('error','No se pudo enviar el email de verificación. Intenta más tarde.');
	}
	return back()->with('success','Hemos reenviado el enlace de verificación.');
})->name('verification.send');

// Local debug endpoint to test plain email sending (only enabled in local env)
if (app()->environment('local')) {
	Route::get('/_debug/mail/test', function(){
		$to = request('to', config('mail.from.address'));
		$ok = false; $err = null;
		try {
			\Mail::raw('Test SMTP debug '.now().' (env='.app()->environment().')', function($m) use ($to){ $m->to($to)->subject('Test SMTP'); });
			$ok = true;
		} catch (\Throwable $e) { $err = $e->getMessage(); }
		return response()->json([
			'ok'=>$ok,
			'to'=>$to,
			'error'=>$err,
			'mailer'=>config('mail.default'),
			'host'=>config('mail.mailers.'.config('mail.default').'.host'),
			'port'=>config('mail.mailers.'.config('mail.default').'.port'),
			'encryption'=>config('mail.mailers.'.config('mail.default').'.encryption'),
		]);
	})->name('_debug.mail.test');
}

// Verify one-time token link (token stored in users table)
Route::get('/email/verify/{id}/{token}', function(\Illuminate\Http\Request $r, int $id, string $token){
	$user = \App\Models\User::find($id);
	if (!$user) { abort(404); }
	$stored = (string) ($user->email_verification_token ?? '');
	$expiresAt = $user->email_verification_token_expires_at ?? null;
	$expired = false;
	if ($expiresAt instanceof \DateTimeInterface) {
		$expired = now()->greaterThan($expiresAt);
	} elseif ($expiresAt) {
		try { $expired = now()->greaterThan(\Carbon\Carbon::parse($expiresAt)); } catch (\Throwable $_) { $expired = true; }
	} else { $expired = true; }
	$alreadyVerified = !empty($user->email_verified_at);
	$invalidToken = empty($stored) || !hash_equals($stored, $token);
	// Any of these states means the link should not work anymore
	if ($alreadyVerified || $expired || $invalidToken) {
		// UX amable: mensaje único sin revelar cuál condición falló
		// Si aún NO estaba verificado pero token venció o inválido, ofrecer reenviar
		$msg = $alreadyVerified
			? 'Tu email ya está verificado.'
			: 'El enlace de verificación ya no es válido (usado o expirado). Solicita uno nuevo.';
		// Prefill email if not verified so la vista notice pueda mostrarlo
		if (!$alreadyVerified) { try { session(['pending_verification_email' => $user->email]); } catch (\Throwable $_) {} }
		return redirect()->route('verification.notice')->with($alreadyVerified ? 'info' : 'error', $msg);
	}
	// Consumir token y verificar
	$user->email_verified_at = now();
	$user->email_verification_token = null;
	$user->email_verification_token_expires_at = null;
	try { $user->save(); } catch (\Throwable $_) {}
	return redirect('/')->with('success','Email verificado. Ahora puedes iniciar sesión.');
})->name('verification.verify');

// 2FA login challenge (NO auth middleware; user not fully authenticated yet)
Route::get('/auth/2fa-challenge', function(\Illuminate\Http\Request $request){
	$uid = $request->session()->get('2fa_login_user_id');
	if (!$uid) { return redirect('/'); }
	$user = \App\Models\User::find($uid);
	if (!$user) { return redirect('/'); }
	$method = $request->session()->get('2fa_delivery_method','email');
	$hasPhone = false; try { if (\Illuminate\Support\Facades\Schema::hasColumn('users','phone')) { $hasPhone = (bool)$user->phone; } } catch (\Throwable $_) {}
	return view('auth.twofactor_challenge', ['email' => $user->email, 'has_phone' => $hasPhone, 'delivery_method' => $method]);
})->name('auth.2fa.challenge');

// 2FA login challenge verification (NO auth middleware)
Route::post('/auth/2fa-challenge', function(\Illuminate\Http\Request $request){
	$uid = $request->session()->get('2fa_login_user_id');
	if (!$uid) {
		if ($request->expectsJson()) return response()->json(['ok'=>false,'message'=>'Sesión de desafío expirada.'], 419);
		return redirect('/')->with('error','Sesión de desafío expirada.');
	}
	$user = \App\Models\User::find($uid);
	if (!$user) {
		if ($request->expectsJson()) return response()->json(['ok'=>false,'message'=>'Usuario no encontrado.'], 404);
		return redirect('/')->with('error','Usuario no encontrado.');
	}
	// Resend flow (supports phone/email based on stored delivery method)
	if ($request->boolean('resend')) {
		$code = (string) random_int(100000, 999999);
		try { \Cache::put('2fa:login:'.$user->id, hash('sha256',$code), now()->addMinutes(5)); } catch (\Throwable $_) {}
		$method = $request->session()->get('2fa_delivery_method','email');
		$sent = false;
		if ($method === 'phone') {
			$twSid = env('TWILIO_SID'); $twToken = env('TWILIO_TOKEN'); $twFrom = env('TWILIO_FROM');
			$phone = null; try { if (\Schema::hasColumn('users','phone')) { $phone = trim((string)$user->phone); } } catch (\Throwable $_) { $phone=null; }
			if ($twSid && $twToken && $twFrom && $phone) {
				try { $client = new \Twilio\Rest\Client($twSid,$twToken); $client->messages->create($phone,['from'=>$twFrom,'body'=>'Tu código 2FA es: '.$code]); $sent=true; } catch (\Throwable $e){ $sent=false; }
			}
		}
		if (!$sent) { // fallback email
			try { \Mail::raw('Tu código de verificación es: '.$code, function($m) use ($user){ $m->to($user->email)->subject('Código de verificación 2FA'); }); } catch (\Throwable $_) {}
		}
		if ($request->expectsJson()) return response()->json(['ok'=>true,'message'=>'Código reenviado.']);
		return back()->with('success','Código reenviado.');
	}
	$validated = $request->validate([
		'code' => ['required','string','regex:/^[0-9]{6}$/']
	], [
		'code.required' => 'Ingresa el código.',
		'code.regex' => 'Código inválido.'
	]);
	$provided = $validated['code'];
	$cacheKey = '2fa:login:'.$user->id;
	$storedHash = \Cache::get($cacheKey);
	if (!$storedHash) {
		if ($request->expectsJson()) return response()->json(['ok'=>false,'message'=>'Código expirado.'], 419);
		return back()->withErrors(['code'=>'Código expirado.']);
	}
	if (hash('sha256',$provided) !== $storedHash) {
		if ($request->expectsJson()) return response()->json(['ok'=>false,'message'=>'Código incorrecto.'], 422);
		return back()->withErrors(['code'=>'Código incorrecto.']);
	}
	// Éxito: eliminar hash y autenticar
	\Cache::forget($cacheKey);
	try { auth()->login($user); } catch (\Throwable $_) { if ($request->expectsJson()) return response()->json(['ok'=>false,'message'=>'No se pudo iniciar sesión.'], 500); return back()->withErrors(['code'=>'No se pudo iniciar sesión.']); }
	$request->session()->forget('2fa_login_user_id');
	$request->session()->regenerate();
	$page = $request->session()->pull('2fa_intended_page', '/');
	$request->session()->forget('url.intended');
	if ($request->expectsJson()) {
		return response()->json(['ok'=>true,'redirect'=>$page]);
	}
	return redirect()->to($page)->with('success','Verificación 2FA completada.');
})->name('auth.2fa.challenge.verify');

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
		$user = auth()->user();
		$nextAppt = null;
		$pendingReschedule = null;
		try {
			$allowedStatuses = ['accepted','pending','in_progress']; // requested may be internal; we target actionable states
			$now = now();
			// Core query: next upcoming OR currently active window (start <= now < end)
			$nextAppt = \App\Models\Appointment::with('patient')
				->where('professional_id', $user->id)
				->whereNull('deleted_at')
				->where(function($q) use ($now){
					$q->where('start','>=',$now)
					  ->orWhere(function($q2) use ($now){ $q2->where('start','<=',$now)->where('end','>=',$now); });
				})
				->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(status))'), array_map('strtolower',$allowedStatuses))
				->orderBy('start','asc')
				->first();
			if (!$nextAppt) {
				// Fallback: earliest future pending/accepted within next 24h regardless of exact status formatting
				$futureLimit = $now->copy()->addHours(24);
				$nextAppt = \App\Models\Appointment::with('patient')
					->where('professional_id',$user->id)
					->whereNull('deleted_at')
					->where('start','>=',$now)
					->where('start','<=',$futureLimit)
					->orderBy('start','asc')
					->first();
			}
			// Load pending reschedule if exists for this appointment
			if ($nextAppt && \Illuminate\Support\Facades\Schema::hasTable('appointment_reschedules')) {
				try {
					$pendingReschedule = \App\Models\AppointmentReschedule::where('appointment_id', $nextAppt->id)
						->where('status','pending')
						->latest('id')
						->first();
				} catch (\Throwable $_) { $pendingReschedule = null; }
			}
		} catch (\Throwable $_) { $nextAppt = null; }
		return view('professionalArea', ['nextAppt' => $nextAppt, 'pendingReschedule' => $pendingReschedule]);
	})->middleware(['perm:professionalarea'])->name('professionalarea');

	// Professional calendar and related endpoints
	Route::middleware(['auth'])->group(function(){
		// Availability management (professional)
		Route::get('/professional/availability', [\App\Http\Controllers\ProfessionalAvailabilityController::class, 'index'])->name('professional.availability');
		Route::post('/professional/availability', [\App\Http\Controllers\ProfessionalAvailabilityController::class, 'store'])->name('professional.availability.store');
		Route::patch('/professional/availability/{availability}', [\App\Http\Controllers\ProfessionalAvailabilityController::class, 'update'])->name('professional.availability.update');
		Route::delete('/professional/availability/{availability}', [\App\Http\Controllers\ProfessionalAvailabilityController::class, 'destroy'])->name('professional.availability.destroy');
		Route::post('/professional/availability/exceptions', [\App\Http\Controllers\ProfessionalAvailabilityController::class, 'storeException'])->name('professional.availability.exceptions.store');
		Route::patch('/professional/availability/exceptions/{exception}', [\App\Http\Controllers\ProfessionalAvailabilityController::class, 'updateException'])->name('professional.availability.exceptions.update');
		Route::delete('/professional/availability/exceptions/{exception}', [\App\Http\Controllers\ProfessionalAvailabilityController::class, 'destroyException'])->name('professional.availability.exceptions.destroy');
	});

	// Availability APIs (JSON)
	Route::get('/professionals/{id}/availability/check', [\App\Http\Controllers\ProfessionalAvailabilityApiController::class, 'check'])->name('professional.availability.check');
	Route::get('/professionals/{id}/availability/weekly', [\App\Http\Controllers\ProfessionalAvailabilityApiController::class, 'weekly'])->name('professional.availability.weekly');
	// Professional calendar: remove permission middleware so basic calendar loads regardless of perm, keep auth
	Route::prefix('professional')->middleware(['auth'])->group(function(){
		Route::get('/calendar', [\App\Http\Controllers\ProfessionalCalendarController::class, 'index'])->name('professional.calendar');
		Route::get('/calendar/events', [\App\Http\Controllers\ProfessionalCalendarController::class, 'events'])->name('professional.calendar.events');
		Route::post('/calendar/events', [\App\Http\Controllers\ProfessionalCalendarController::class, 'store'])->name('professional.calendar.events.store');
		Route::get('/calendar/patients', [\App\Http\Controllers\ProfessionalCalendarController::class, 'searchPatients'])->name('professional.calendar.patients');
		Route::post('/calendar/events/{appointment}/accept', [\App\Http\Controllers\AppointmentController::class, 'accept'])->name('appointments.accept');
		Route::post('/calendar/events/{appointment}/reject', [\App\Http\Controllers\AppointmentController::class, 'reject'])->name('appointments.reject');
			// Appointment history (past & finalized appointments)
			Route::get('/appointments/history', [\App\Http\Controllers\ProfessionalAppointmentHistoryController::class, 'index'])->name('professional.appointments.history');
			Route::get('/appointments/history/export', [\App\Http\Controllers\ProfessionalAppointmentHistoryController::class, 'export'])->name('professional.appointments.history.export');

			// Payments history for professionals
			Route::get('/payments', [\App\Http\Controllers\ProfessionalPaymentsController::class, 'index'])->name('professional.payments.history');
			Route::get('/payments/export', [\App\Http\Controllers\ProfessionalPaymentsController::class, 'export'])->name('professional.payments.history.export');
	});

});

	// Professionals search - require authentication and permission
	Route::middleware(['perm:userarea'])->group(function(){
		Route::get('/professionals', [\App\Http\Controllers\ProfessionalSearchController::class, 'index'])->name('professionals.index');
		Route::get('/professionals/search', [\App\Http\Controllers\ProfessionalSearchController::class, 'search'])->name('professionals.search');
        // Show public profile page for a professional
        Route::get('/professional/profile/{id}', [\App\Http\Controllers\ProfessionalSearchController::class, 'show'])->name('professionals.show');
		Route::get('/professionals/{id}/ratings/public', [\App\Http\Controllers\ProfessionalSearchController::class, 'publicRatings'])->name('professionals.ratings.public');
	});

	Route::get('/userarea', function () {
		$user = auth()->user();
		$pendingRatings = collect();
		$nextAppt = null;
		$pendingReschedule = null;
		try {
			if (\Illuminate\Support\Facades\Schema::hasTable('appointments') && \Illuminate\Support\Facades\Schema::hasTable('appointment_ratings')) {
				$windowDays = (int) config('appointments.rating_window_days');
				$cutoff = now()->subDays($windowDays);
				$pendingRatings = \App\Models\Appointment::query()
					->where('patient_id', $user->id)
					->where('status','completed')
					->where('end','>=',$cutoff)
					->whereDoesntHave('rating')
					->orderBy('end','desc')
					->limit(10)
					->get();
			}
		} catch (\Throwable $e) { $pendingRatings = collect(); }

		// Próxima cita del paciente (misma lógica que profesional, adaptada a patient_id)
		try {
			$allowedStatuses = ['accepted','pending','in_progress'];
			$now = now();
			$nextAppt = \App\Models\Appointment::with('professional')
				->where('patient_id', $user->id)
				->whereNull('deleted_at')
				->where(function($q) use ($now){
					$q->where('start','>=',$now)
					  ->orWhere(function($q2) use ($now){ $q2->where('start','<=',$now)->where('end','>=',$now); });
				})
				->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(status))'), array_map('strtolower',$allowedStatuses))
				->orderBy('start','asc')
				->first();
			if (!$nextAppt) {
				$futureLimit = $now->copy()->addHours(24);
				$nextAppt = \App\Models\Appointment::with('professional')
					->where('patient_id',$user->id)
					->whereNull('deleted_at')
					->where('start','>=',$now)
					->where('start','<=',$futureLimit)
					->orderBy('start','asc')
					->first();
			}
			if ($nextAppt && \Illuminate\Support\Facades\Schema::hasTable('appointment_reschedules')) {
				try {
					$pendingReschedule = \App\Models\AppointmentReschedule::where('appointment_id', $nextAppt->id)
						->where('status','pending')
						->latest('id')
						->first();
				} catch (\Throwable $_) { $pendingReschedule = null; }
			}
		} catch (\Throwable $_) { $nextAppt = null; }

		return view('userArea', [ 'pendingRatings' => $pendingRatings, 'nextAppt' => $nextAppt, 'pendingReschedule' => $pendingReschedule ]);
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
			'prof_pending' => Schema::hasTable('professional_applications')
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
		Route::post('/users', [\App\Http\Controllers\AdminController::class, 'storeUser'])->name('admin.users.store');
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

		// Menu Items CRUD
		Route::get('/menu-items', [\App\Http\Controllers\Admin\MenuItemController::class, 'index'])->name('admin.menuitems.index');
		Route::get('/menu-items/create', [\App\Http\Controllers\Admin\MenuItemController::class, 'create'])->name('admin.menuitems.create');
		Route::post('/menu-items', [\App\Http\Controllers\Admin\MenuItemController::class, 'store'])->name('admin.menuitems.store');
		Route::get('/menu-items/{menuItem}/edit', [\App\Http\Controllers\Admin\MenuItemController::class, 'edit'])->name('admin.menuitems.edit');
		Route::put('/menu-items/{menuItem}', [\App\Http\Controllers\Admin\MenuItemController::class, 'update'])->name('admin.menuitems.update');
		Route::delete('/menu-items/{menuItem}', [\App\Http\Controllers\Admin\MenuItemController::class, 'destroy'])->name('admin.menuitems.destroy');
		Route::post('/menu-items/{menuItem}/toggle', [\App\Http\Controllers\Admin\MenuItemController::class, 'toggle'])->name('admin.menuitems.toggle');

		// Appointment lifecycle global settings UI
		Route::get('/appointment-settings', [\App\Http\Controllers\Admin\AppointmentSettingsController::class, 'index'])->name('admin.appointment.settings');
		Route::post('/appointment-settings', [\App\Http\Controllers\Admin\AppointmentSettingsController::class, 'update'])->name('admin.appointment.settings.update');
		// Appointment metrics dashboard
		Route::get('/appointment-metrics', [\App\Http\Controllers\Admin\AppointmentMetricsController::class, 'index'])->name('admin.appointment.metrics');
		Route::get('/appointment-metrics/json', [\App\Http\Controllers\Admin\AppointmentMetricsController::class, 'json'])->name('admin.appointment.metrics.json');

		// Professional applications review (moved below with flexible permission)
	});

		// Admin: Device management (global) - listar y revocar dispositivos de cualquier usuario
		Route::get('/devices', function(){
			$devices = \App\Models\UserDevice::with('user')->orderBy('last_seen_at','desc')->paginate(50);
			return view('admin.devices.index', compact('devices'));
		})->name('admin.devices');

		// Admin payments management
		Route::get('/admin/payments', [\App\Http\Controllers\AdminPaymentController::class, 'index'])
			->middleware(['auth','perm:adminarea'])
			->name('admin.payments.index');

		Route::post('/admin/payments/payout', [\App\Http\Controllers\AdminPaymentController::class, 'payout'])
			->middleware(['auth','perm:adminarea'])
			->name('admin.payments.payout');

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
// Lightweight CSRF token refresh endpoint for AJAX modals (returns new token)
Route::get('/auth/csrf-refresh', function(\Illuminate\Http\Request $r){
	try { $r->session()->regenerateToken(); } catch (\Throwable $_) {}
	return response()->json(['token' => csrf_token()]);
})->name('auth.csrf.refresh');
// Safe GET fallbacks to avoid 405 on GET /login or GET /register
Route::get('/login', fn () => redirect('/welcome'));
Route::get('/register', fn () => redirect('/welcome'));

// Password reset flow (links generated by Password::sendResetLink require these named routes)
// Request reset link page
Route::get('/password/forgot', function(){
	return view('auth.password_forgot');
})->name('password.request');

// Send reset link (generic response to avoid user enumeration)
Route::post('/password/email', function(\Illuminate\Http\Request $request){
	$data = $request->only('email');
	$validator = \Illuminate\Support\Facades\Validator::make($data, [
		'email' => ['required','email']
	], [
		'email.required' => 'El email es obligatorio.',
		'email.email' => 'Formato de email inválido.'
	]);
	if ($validator->fails()) {
		if ($request->wantsJson()) {
			return response()->json(['ok'=>false,'errors'=>$validator->errors()], 422);
		}
		return back()->withErrors($validator)->withInput();
	}
	$user = null;
	try { $user = \App\Models\User::whereRaw('LOWER(email) = ?', [strtolower($data['email'])])->first(); } catch (\Throwable $_) { $user = null; }
	if ($user && empty($user->email_verified_at)) {
		// Regenerar token de verificación para asegurar que tenga un enlace vigente
		try {
			$user->email_verification_token = Str::random(40);
			$user->email_verification_token_expires_at = now()->addDay(1);
			$user->save();
		} catch (\Throwable $_) {}
		// Enviar nuevamente el enlace de verificación
		try {
			$verifyUrl = url('/email/verify/'.$user->id.'/'.$user->email_verification_token);
			\Mail::raw('Verifica tu email con este enlace: '.$verifyUrl, function($m) use ($user){
				$m->to($user->email)->subject('Verifica tu email');
			});
			\Log::info('password.email.verification_resent', ['email'=>$user->email]);
		} catch (\Throwable $e) {
			\Log::error('password.email.verification_resend_failed', ['email'=>$user->email,'error'=>$e->getMessage()]);
		}
		try { session(['pending_verification_email' => $user->email]); } catch (\Throwable $_) {}
		$verifyMsg = 'Tu cuenta aún no está verificada. Revisa tu email, confirma tu cuenta y luego podrás restablecer la contraseña.';
		if ($request->wantsJson()) {
			return response()->json([
				'ok' => false,
				'requires_verification' => true,
				'message' => $verifyMsg,
			], 409);
		}
		return redirect()->route('verification.notice')->with('info', $verifyMsg);
	}
	try { \Log::info('password.email.request', ['email' => $data['email']]); } catch (\Throwable $_) {}
	$status = \Illuminate\Support\Facades\Password::sendResetLink(['email' => $data['email']]);
	try { \Log::info('password.email.status', ['email' => $data['email'], 'status' => $status]); } catch (\Throwable $_) {}
	// Always return a generic success message to avoid leaking whether the email exists
	$generic = 'Si el correo existe en nuestro sistema, te enviaremos un enlace para restablecer tu contraseña.';
	if ($request->wantsJson()) {
		return response()->json(['ok' => true, 'message' => $generic, 'status' => $status]);
	}
	return back()->with('success', $generic);
})->name('password.email');

Route::get('/password/reset/{token}', function(\Illuminate\Http\Request $request, string $token){
	$email = $request->query('email');
	if (!$email) { abort(404, 'Recurso no disponible'); }
	// Fetch latest token rows for this email and verify provided token hash + expiry
	try {
		$rows = \Illuminate\Support\Facades\DB::table(config('auth.passwords.users.table','password_reset_tokens'))
			->where('email', $email)->orderByDesc('created_at')->limit(5)->get();
		$valid = false; $expiryMinutes = (int) config('auth.passwords.users.expire', 15); $ageSeconds = null;
		foreach ($rows as $r) {
			$match = false; try { $match = password_verify($token, $r->token); } catch (\Throwable $_) { $match = false; }
			if ($match) {
				if ($r->created_at) { $ageSeconds = time() - strtotime($r->created_at); }
				if ($ageSeconds !== null && $ageSeconds > ($expiryMinutes * 60)) {
					// Expired: delete and continue searching (should result in 404)
					try { \Illuminate\Support\Facades\DB::table(config('auth.passwords.users.table','password_reset_tokens'))->where('email',$email)->delete(); } catch (\Throwable $_) {}
					break; // treat as invalid
				}
				$valid = true; break;
			}
		}
		if (!$valid) { abort(404, 'Recurso no disponible'); }
	} catch (\Throwable $_) { abort(404, 'Recurso no disponible'); }
	return view('auth.password_reset', compact('token','email'));
})->name('password.reset');

Route::post('/password/reset', function(\Illuminate\Http\Request $request){
	$data = $request->only(['email','password','password_confirmation','token']);
	try { \Log::info('password.reset.request', ['email'=>$data['email'] ?? null, 'token_len'=>isset($data['token'])?strlen($data['token']):null]); } catch (\Throwable $_) {}
	// Basic validation (avoid FormRequest overhead for this simple closure)
	$rules = [
		'email' => ['required','email'],
		// Align minimum length with registration (6)
		'password' => ['required','confirmed','min:6'],
		'password_confirmation' => ['required'],
		'token' => ['required']
	];
	$messages = [
		'email.required' => 'El email es obligatorio.',
		'email.email' => 'Formato de email inválido.',
		'password.required' => 'La contraseña es obligatoria.',
		'password.confirmed' => 'La confirmación no coincide.',
		'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
		'password_confirmation.required' => 'Confirma la contraseña.',
		'token.required' => 'Token faltante.',
	];
	$validator = \Illuminate\Support\Facades\Validator::make($data, $rules, $messages);
	if ($validator->fails()) {
		try { \Log::warning('password.reset.validation_failed', ['errors'=>$validator->errors()->all()]); } catch (\Throwable $_) {}
		// Render view directly with error bag (avoid redirect). Use view()->withErrors()
		$view = view('auth.password_reset', [
			'token' => $data['token'] ?? $request->route('token'),
			'email' => $data['email'] ?? $request->input('email'),
		]);
		return response($view->withErrors($validator), 422);
	}
	// Log the stored hashed token row if present for diagnostics
	try {
		$row = \Illuminate\Support\Facades\DB::table(config('auth.passwords.users.table','password_reset_tokens'))
			->where('email', $data['email'])->orderByDesc('created_at')->first();
		if ($row) {
			$hashedPrefix = substr($row->token,0,12);
			$providedPrefix = substr($data['token'],0,12);
			$hashMatches = false;
			try { $hashMatches = password_verify($data['token'], $row->token); } catch (\Throwable $_) { $hashMatches = false; }
			// Manual expiry enforcement (Laravel broker sometimes lenient if created_at null/timezone). Use config expire minutes.
			$expiryMinutes = (int) config('auth.passwords.users.expire', 60);
			$ageSeconds = null;
			$expired = false;
			try {
				if ($row->created_at) {
					$ageSeconds = time() - strtotime($row->created_at);
					$expired = $ageSeconds > ($expiryMinutes * 60);
				}
			} catch (\Throwable $_) { $expired = false; }
			if ($expired) {
				// Delete stale token row and surface expiry error
				try { \Illuminate\Support\Facades\DB::table(config('auth.passwords.users.table','password_reset_tokens'))->where('email',$data['email'])->delete(); } catch (\Throwable $_) {}
				\Log::warning('password.reset.token_expired', ['email'=>$data['email'],'age_seconds'=>$ageSeconds,'limit_seconds'=>$expiryMinutes*60]);
				return redirect()->back()->with('error', __('passwords.token'))->withInput();
			}
			\Log::info('password.reset.token_row', [
				'email' => $row->email,
				'hashed_token_prefix' => $hashedPrefix,
				'provided_token_prefix' => $providedPrefix,
				'hash_matches' => $hashMatches,
				'age_seconds' => $ageSeconds,
				'expiry_minutes' => $expiryMinutes,
			]);
			// Early feedback if token clearly does not match (helps user see problem instead of silent broker failure)
			if (!$hashMatches) {
				\Log::warning('password.reset.token_mismatch', ['email'=>$data['email']]);
				return redirect()->back()->with('error', __('passwords.token'))->withInput();
			}
		} else {
			\Log::warning('password.reset.token_row_missing', ['email'=>$data['email']]);
		}
	} catch (\Throwable $_) {}
	$status = Password::reset(
		$data,
		function($user) use ($data) {
			$user->forceFill([
				'password' => Hash::make($data['password']),
			])->save();
			$user->setRememberToken(Str::random(60));
			try { $user->save(); } catch (\Throwable $_) {}
			event(new PasswordReset($user));
			try { \Log::info('password.reset.success', ['user_id' => $user->id]); } catch (\Throwable $_) {}
			// Only auto-login verified accounts; pending emails must still verify before acceder
			$shouldLogin = (!auth()->check() || auth()->id() !== $user->id) && !empty($user->email_verified_at);
			if ($shouldLogin) {
				try { auth()->login($user); } catch (\Throwable $_) {}
			}
			// Optionally invalidate other sessions for this user for security hardening
			try {
				if (Schema::hasTable('sessions')) {
					DB::table('sessions')
						->where('user_id', $user->id)
						->where('id', '!=', request()->session()->getId())
						->delete();
				}
			} catch (\Throwable $_) {}
		}
	);
	try { \Log::info('password.reset.status', ['email'=>$data['email'] ?? null, 'status'=>$status]); } catch (\Throwable $_) {}
	if ($status === Password::PASSWORD_RESET) {
		// Redirigir al perfil con mensaje de éxito
		// Delete any remaining reset tokens for this email so link cannot be reused
		try { \Illuminate\Support\Facades\DB::table(config('auth.passwords.users.table','password_reset_tokens'))->where('email',$data['email'])->delete(); } catch (\Throwable $_) {}
		$user = null;
		try { $user = \App\Models\User::whereRaw('LOWER(email) = ?', [strtolower($data['email'])])->first(); } catch (\Throwable $_) { $user = null; }
		if ($user && empty($user->email_verified_at)) {
			try { session(['pending_verification_email' => $user->email]); } catch (\Throwable $_) {}
			try { auth()->logout(); } catch (\Throwable $_) {}
			return redirect()->route('verification.notice')->with('info', 'Tu contraseña fue actualizada, pero debes verificar tu email antes de acceder.');
		}
		return redirect()->route('profile')->with('success', 'Contraseña cambiada correctamente');
	}
	try { \Log::warning('password.reset.failed', ['email' => $data['email'] ?? null, 'status' => $status]); } catch (\Throwable $_) {}
	// Render view with error message (avoid redirect so user sees message reliably)
	$view = view('auth.password_reset', [
		'token' => $data['token'] ?? $request->route('token'),
		'email' => $data['email'] ?? $request->input('email'),
		'error' => __($status),
	]);
	// Attach a generic error bag entry so blade's $errors still shows something if translation not in field errors
	$bag = \Illuminate\Support\Facades\Validator::make([], []);
	$bag->errors()->add('email', __($status));
	return response($view->withErrors($bag), 422);
})->name('password.update');
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

// Plans page (require auth by default)
Route::get('/planes', function () {
	return view('plans.index');
})->middleware('auth')->name('plans.index');

// Admin: update plan pricing (price in cents and discount percent)
Route::post('/admin/plans/{plan}/pricing', [\App\Http\Controllers\PlanAdminController::class, 'updatePricing'])
	->middleware(['auth','perm:adminarea'])
	->name('admin.plans.update_pricing');

// Billing API: subscribe to a plan (simulated provider)
Route::post('/billing/subscribe', [\App\Http\Controllers\BillingController::class, 'subscribe'])
	->middleware('auth')
	->name('billing.subscribe');

// Appointment credits: check remaining included + purchased credits
Route::get('/user/appointment-credits', [\App\Http\Controllers\BillingController::class, 'appointmentCredits'])
	->middleware('auth')
	->name('user.appointment_credits');

// Purchase a single appointment credit (simulated)
Route::post('/billing/purchase-appointment', [\App\Http\Controllers\BillingController::class, 'purchaseAppointment'])
	->middleware('auth')
	->name('billing.purchase_appointment');

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
	Route::post('/logout', function(\Illuminate\Http\Request $request){
		// Before logging out, mark the user as offline and broadcast presence
		$user = auth()->user();
		if ($user) {
			try { $user->status = 'offline'; $user->save(); } catch (\Throwable $_) {}
			try { event(new \App\Events\UserPresenceChanged($user->id, 'offline')); } catch (\Throwable $_) {}
		}
		// Delegate to the controller for the rest of the logout flow
		return app(\App\Http\Controllers\LoginRegisterController::class)->logout($request);
	})->name('logout');

	// Endpoint to record session end (used by client sendBeacon on page unload)
	Route::post('/sessions/end', function(\Illuminate\Http\Request $request){
		$user = auth()->user();
		if ($user) {
			// Mark user as offline when the page is closed and broadcast presence change
			try { $user->status = 'offline'; $user->save(); } catch (\Throwable $_) {}
			try { event(new \App\Events\UserPresenceChanged($user->id, 'offline')); } catch (\Throwable $_) {}
		}
		// Delegate to controller for session end bookkeeping
		return app(\App\Http\Controllers\LoginRegisterController::class)->endSession($request);
	})->withoutMiddleware('auth')->name('sessions.end');

	// User photos management
	Route::get('/profile/photos', [\App\Http\Controllers\UserPhotoController::class, 'index'])->name('profile.photos.index');
	Route::post('/profile/photos', [\App\Http\Controllers\UserPhotoController::class, 'store'])->name('profile.photos.store');
	Route::post('/profile/photos/{photo}/set-profile', [\App\Http\Controllers\UserPhotoController::class, 'setProfile'])->name('profile.photos.set');
	Route::post('/profile/photos/{photo}/unset-profile', [\App\Http\Controllers\UserPhotoController::class, 'unsetProfile'])->name('profile.photos.unset');
	Route::delete('/profile/photos/{photo}', [\App\Http\Controllers\UserPhotoController::class, 'destroy'])->name('profile.photos.destroy');

	// Presence update
	Route::post('/profile/presence', function(\Illuminate\Http\Request $request){
		$user = auth()->user();
		$status = (string) $request->input('status','online');
		$allowed = ['online','busy','dnd','away','offline'];
		if (!in_array($status, $allowed, true)) $status = 'online';
		$user->status = $status;
		try { $user->save(); } catch(\Throwable$e) {}
		try { event(new \App\Events\UserPresenceChanged($user->id, $status)); } catch (\Throwable $_) {}
		return response()->json(['ok'=>true,'status'=>$status]);
	})->name('profile.presence');

	// Send password-reset email to the authenticated user (AJAX)
	Route::post('/profile/password/reset-email', function(\Illuminate\Http\Request $request){
		$user = auth()->user();
		if (!$user) return response()->json(['ok'=>false,'message'=>'unauthenticated'], 401);
		try {
			$status = \Illuminate\Support\Facades\Password::sendResetLink(['email' => $user->email]);
			// Laravel returns translation key strings like 'passwords.sent', 'passwords.throttled', 'passwords.user'
			switch ($status) {
				case \Illuminate\Support\Facades\Password::RESET_LINK_SENT:
					return response()->json(['ok'=>true,'message'=>__($status)], 200);
				case \Illuminate\Support\Facades\Password::RESET_THROTTLED:
					return response()->json(['ok'=>false,'message'=>__($status)], 429);
				case \Illuminate\Support\Facades\Password::INVALID_USER:
					return response()->json(['ok'=>false,'message'=>__($status)], 404);
				default:
					// Treat any other broker status as unprocessable (e.g. invalid token context)
					return response()->json(['ok'=>false,'message'=>__($status)], 422);
			}
		} catch (\Throwable $e) {
			try { \Log::error('profile.reset-email error', ['err'=>$e->getMessage()]); } catch (\Throwable $_) {}
			return response()->json(['ok'=>false,'message'=>'Error interno al enviar correo.'], 500);
		}
	})->name('profile.password.reset_email');

	// Toggle user preference for requiring 2FA on suspicious reopen (UA/IP mismatch)
	Route::post('/profile/2fa/toggle', function(\Illuminate\Http\Request $request){
		$user = auth()->user();
		if (!$user) return response()->json(['ok'=>false,'message'=>'unauthenticated'], 401);
		// If enabling and user has no phone, block and ask for phone first
		$hasPhoneColumn = \Illuminate\Support\Facades\Schema::hasColumn('users','phone');
		$currentlyEnabled = false;
		if (\Illuminate\Support\Facades\Schema::hasColumn('users','two_factor_enabled')) {
			$currentlyEnabled = (bool)($user->two_factor_enabled ?? false);
		}
		$method = strtolower(trim((string) $request->input('method','')));
		if ($method === '') { $method = 'email'; }
		if (!in_array($method, ['email','phone'], true)) { $method = 'email'; }
		if (!$currentlyEnabled) { // attempting to enable
			if ($method === 'phone') {
				$phoneVal = $hasPhoneColumn ? (string)($user->phone ?? '') : '';
				if ($hasPhoneColumn && trim($phoneVal) === '') {
					return response()->json(['ok'=>false,'phone_required'=>true,'message'=>'Debes registrar tu número de teléfono antes de activar 2FA con teléfono.']);
				}
			}
		}
		$new = $currentlyEnabled;
		try {
			if (\Illuminate\Support\Facades\Schema::hasColumn('users','two_factor_enabled')) {
				$user->two_factor_enabled = !$currentlyEnabled;
				if ($user->two_factor_enabled) { // set method when enabling
					if (\Illuminate\Support\Facades\Schema::hasColumn('users','two_factor_method')) {
						$user->two_factor_method = $method;
					}
				} else {
					if (\Illuminate\Support\Facades\Schema::hasColumn('users','two_factor_method')) {
						$user->two_factor_method = null; // clear when disabling
					}
				}
				try { $user->save(); } catch (\Throwable $_) {}
				$new = (bool)$user->two_factor_enabled;
			} else {
				$cacheKey = 'user:'.$user->id.':two_factor_enabled';
				$currentlyEnabled = (bool) \Illuminate\Support\Facades\Cache::get($cacheKey, false);
				$new = !$currentlyEnabled;
				\Illuminate\Support\Facades\Cache::put($cacheKey, $new, now()->addDays(30));
			}
		} catch (\Throwable $_) { /* keep previous state */ }
		return response()->json(['ok'=>true,'enabled'=>$new,'method'=>$method]);
	})->name('profile.2fa.toggle');

	// Update phone number (required before enabling 2FA)
	Route::post('/profile/phone', function(\Illuminate\Http\Request $request){
		$user = auth()->user();
		if (!$user) return response()->json(['ok'=>false,'message'=>'unauthenticated'], 401);
		if (!\Illuminate\Support\Facades\Schema::hasColumn('users','phone')) {
			return response()->json(['ok'=>false,'message'=>'phone column missing'], 500);
		}
		$validated = $request->validate([
			'phone' => ['required','string','min:7','max:25','regex:/^[0-9+\-().\s]{7,25}$/']
		], [
			'phone.required' => 'El teléfono es obligatorio.',
			'phone.min' => 'El teléfono es demasiado corto.',
			'phone.max' => 'El teléfono es demasiado largo.',
			'phone.regex' => 'Formato de teléfono inválido.'
		]);
		$user->phone = $validated['phone'];
		try { $user->save(); } catch (\Throwable $e) { return response()->json(['ok'=>false,'message'=>'No se pudo guardar el teléfono.'], 500); }
		return response()->json(['ok'=>true,'message'=>'Teléfono actualizado.']);
	})->name('profile.phone.update');



	// Return current authenticated user's status (used by polling on /perfil)
	Route::get('/profile/status', function(){
		$user = auth()->user();
		if (!$user) return response()->json(['ok'=>false], 401);
		$lastSeen = null;
		$recent = false;
		if (isset($user->last_seen_at)) {
			if ($user->last_seen_at instanceof \DateTimeInterface) {
				$lastSeen = $user->last_seen_at->format('Y-m-d H:i:s');
				$recent = now()->diffInSeconds($user->last_seen_at) <= 90; // within 90s = online
			} else {
				$lastSeen = is_string($user->last_seen_at) && $user->last_seen_at !== '' ? $user->last_seen_at : null;
				try { $recent = $lastSeen ? (now()->diffInSeconds(\Carbon\Carbon::parse($lastSeen)) <= 90) : false; } catch (\Throwable $_) { $recent = false; }
			}
		}
		$base = (string) ($user->status ?? '');
		// Honor explicit user-set status, including 'offline'
		if (in_array($base, ['online','busy','dnd','away','offline'], true)) {
			$effective = $base;
		} else {
			// Otherwise, infer from recency
			$effective = $recent ? 'online' : 'offline';
		}
		return response()->json(['ok'=>true,'status'=>$effective,'last_seen_at'=>$lastSeen]);
	})->name('profile.status');

	// Heartbeat/keepalive endpoint for presence (called periodically by the client)
	Route::post('/profile/heartbeat', function(\Illuminate\Http\Request $request){
		$user = auth()->user();
		if (!$user) return response()->json(['ok'=>false], 401);
		try {
			$user->last_seen_at = now();
			$user->saveQuietly();

			// Broadcast a presence update so other clients can reflect online state without refresh
			try { event(new \App\Events\UserPresenceChanged($user->id, $user->status ?: 'online')); } catch (\Throwable $_) {}

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
											// Honor user 2FA preference; skip if disabled
											$twoFactorPref = false;
											try {
												if (\Illuminate\Support\Facades\Schema::hasColumn('users','two_factor_enabled')) {
													$twoFactorPref = (bool)($user->two_factor_enabled ?? false);
												} else {
													$twoFactorPref = (bool) \Illuminate\Support\Facades\Cache::get('user:'.$user->id.':two_factor_enabled', false);
												}
											} catch (\Throwable $_) { $twoFactorPref = false; }
											if (!$twoFactorPref) {
												// User opted out: treat as normal reopen (update session & device)
												try { \Illuminate\Support\Facades\Log::info('user_login.reopen_without_2fa_pref_disabled', ['user_id'=>$user->id]); } catch (\Throwable $_) {}
												// reopen directly
												\Illuminate\Support\Facades\DB::table('user_logins')->where('id', $found->id)->update(['ended_at' => null, 'duration_seconds' => null, 'session_id' => $sid]);
												return response()->json(['ok'=>true,'two_factor_required'=>false]);
											}
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

	// Quick meta for a specific appointment (status + start) used by UI polling
	Route::get('/appointments/{appointment}/meta', function(\Illuminate\Http\Request $r, \App\Models\Appointment $appointment){
		$user = auth()->user();
		if (!$user) return response()->json(['ok'=>false,'message'=>'unauthenticated'], 401);
		// Only allow participants (patient or professional) to query
		if (!in_array($user->id, [(int)$appointment->patient_id, (int)$appointment->professional_id], true)) {
			return response()->json(['ok'=>false,'message'=>'forbidden'], 403);
		}
		return response()->json([
			'ok' => true,
			'status' => (string) ($appointment->status ?? ''),
			'start' => $appointment->start ? $appointment->start->toIso8601String() : null,
		]);
	})->name('appointments.meta');
	// Patient accept/reject endpoints (calls to AppointmentController)
	Route::post('/appointments/{appointment}/accept', [\App\Http\Controllers\AppointmentController::class, 'accept'])->name('appointments.patient.accept');
	Route::post('/appointments/{appointment}/reject', [\App\Http\Controllers\AppointmentController::class, 'reject'])->name('appointments.patient.reject');
	// Patient cancel endpoint (cancel a pending request)
	Route::post('/appointments/{appointment}/cancel', [\App\Http\Controllers\AppointmentController::class, 'cancel'])->name('appointments.patient.cancel');

	// Appointment session lifecycle endpoints
	Route::post('/appointments/{appointment}/session/start', [\App\Http\Controllers\AppointmentSessionController::class, 'start'])->name('appointments.session.start');
	Route::post('/appointments/{appointment}/session/ensure-room', [\App\Http\Controllers\AppointmentSessionController::class, 'ensureRoom'])->name('appointments.session.ensure_room');
	Route::post('/appointments/{appointment}/session/request-end', [\App\Http\Controllers\AppointmentSessionController::class, 'requestEnd'])->name('appointments.session.request_end');
	Route::get('/appointments/{appointment}/session/end-request', [\App\Http\Controllers\AppointmentSessionController::class, 'endRequestStatus'])->name('appointments.session.end_request.status');
	Route::post('/appointments/{appointment}/session/cancel-end', [\App\Http\Controllers\AppointmentSessionController::class, 'cancelEnd'])->name('appointments.session.cancel_end');
	Route::post('/appointments/{appointment}/session/heartbeat', [\App\Http\Controllers\AppointmentSessionController::class, 'heartbeat'])->middleware('appointment.session.rate')->name('appointments.session.heartbeat');
	Route::post('/appointments/{appointment}/session/complete', [\App\Http\Controllers\AppointmentSessionController::class, 'complete'])->name('appointments.session.complete');
	Route::get('/appointments/{appointment}/session/status', [\App\Http\Controllers\AppointmentSessionController::class, 'status'])->name('appointments.session.status');
	Route::post('/appointments/{appointment}/session/metrics', [\App\Http\Controllers\AppointmentSessionController::class, 'metrics'])->middleware('appointment.session.rate')->name('appointments.session.metrics');

	// Ratings endpoints (patient -> professional)
	Route::post('/appointments/{appointment}/rating', [\App\Http\Controllers\AppointmentRatingController::class, 'store'])->name('appointments.rating.store');
	Route::put('/appointments/{appointment}/rating', [\App\Http\Controllers\AppointmentRatingController::class, 'update'])->name('appointments.rating.update');
	Route::get('/professionals/{professionalId}/ratings/summary', [\App\Http\Controllers\AppointmentRatingController::class, 'summary'])->name('professionals.ratings.summary');
	// Professional ratings management (summary + moderation/response)
	Route::get('/professional/ratings', [\App\Http\Controllers\AppointmentRatingController::class, 'professionalIndex'])->middleware('perm:professionalarea')->name('professional.ratings.index');
	Route::patch('/professional/ratings/{rating}', [\App\Http\Controllers\AppointmentRatingController::class, 'moderate'])->middleware('perm:professionalarea')->name('professional.ratings.moderate');

	// Reschedule workflow endpoints
	Route::post('/appointments/{appointment}/reschedules', [\App\Http\Controllers\AppointmentRescheduleController::class, 'store'])->name('appointments.reschedules.store');
	Route::post('/reschedules/{reschedule}/accept', [\App\Http\Controllers\AppointmentRescheduleController::class, 'accept'])->name('appointments.reschedules.accept');
	Route::post('/reschedules/{reschedule}/reject', [\App\Http\Controllers\AppointmentRescheduleController::class, 'reject'])->name('appointments.reschedules.reject');

	// Unified chat hub (combines conversations + friends UI)
	Route::get('/chat', function(){
		$userId = auth()->id();
		$lastMessages = \App\Models\Message::with(['from','to'])
			->where(function($q) use ($userId){ $q->where('from_id', $userId)->orWhere('to_id', $userId); })
			->orderBy('created_at','desc')
			->limit(200)
			->get()
			->unique(function($m){ return $m->from_id === auth()->id() ? $m->to_id : $m->from_id; })
			->values();
		return view('chat.index', compact('lastMessages'));
	})->name('chat.index');

	// Messages API used by chat.js
	// Get conversation thread with a specific user (JSON)
	Route::get('/messages/thread/{user}', function(\Illuminate\Http\Request $r, \App\Models\User $user){
		$me = auth()->user();
		// Basic guard: avoid self-thread
		if ($me->id === $user->id) {
			return response()->json(['ok' => true, 'messages' => []]);
		}
		try {
			$messages = \App\Models\Message::query()
				->where(function($q) use ($me, $user){ $q->where('from_id', $me->id)->where('to_id', $user->id); })
				->orWhere(function($q) use ($me, $user){ $q->where('from_id', $user->id)->where('to_id', $me->id); })
				->orderBy('created_at', 'asc')
				->limit(200)
				->get();
			// Mark as read all messages from the other user to me
			try { \App\Models\Message::where('from_id', $user->id)->where('to_id', $me->id)->whereNull('read_at')->update(['read_at' => now()]); } catch (\Throwable $_) {}
			$items = $messages->map(function($m){
				return [
					'id' => $m->id,
					'from_id' => (int) $m->from_id,
					'to_id' => (int) $m->to_id,
					'body' => (string) $m->body,
					'created_at' => $m->created_at ? $m->created_at->toIso8601String() : null,
					'read_at' => $m->read_at ? $m->read_at->toIso8601String() : null,
				];
			});
			return response()->json(['ok' => true, 'messages' => $items]);
		} catch (\Throwable $e) {
			return response()->json(['ok' => false, 'error' => 'thread_error'], 500);
		}
	})->name('messages.thread');

	// Send a new message to a specific user (JSON)
	Route::post('/messages/thread/{user}', function(\Illuminate\Http\Request $r, \App\Models\User $user){
		$me = auth()->user();
		$body = (string) $r->input('body', '');
		$body = trim($body);
		if ($body === '') return response()->json(['ok' => false, 'error' => 'empty'], 422);
		// --- Chat quota enforcement (chats_per_month) ---
		try {
			$activeSub = $me->subscriptions()->with('plan')->where(function($q){
				$q->where('status','active')
				  ->orWhereNull('ends_at')
				  ->orWhere('ends_at','>', now());
			})->orderBy('ends_at','desc')->first();

			$included = null;
			if ($activeSub && $activeSub->plan && is_array($activeSub->plan->features ?? null)) {
				$included = $activeSub->plan->features['chats_per_month'] ?? null;
				if (is_string($included)) $included = (int)$included;
			}

			$start = \Carbon\Carbon::now()->startOfMonth();
			$end = \Carbon\Carbon::now()->endOfMonth();
			$used = 0;
			try {
				if (\Illuminate\Support\Facades\Schema::hasTable('messages')) {
					$used = \App\Models\Message::where('from_id', $me->id)->whereBetween('created_at', [$start, $end])->count();
				}
			} catch (\Throwable $_) { $used = 0; }

			// No purchased chat ledger yet; treat purchased as 0 for now
			$purchased = 0;

			$remainingIncluded = null;
			if ($included !== null) {
				$remainingIncluded = max(0, (int)$included - (int)$used);
			}

			$totalAvailable = ($remainingIncluded === null) ? -1 : ($remainingIncluded + (int)$purchased);
			if ($totalAvailable !== -1 && $totalAvailable <= 0) {
				return response()->json(['ok' => false, 'error' => 'quota_exceeded', 'message' => 'Has alcanzado el límite de chats este mes. Actualiza tu plan.'], 402);
			}
		} catch (\Throwable $e) {
			try { \Illuminate\Support\Facades\Log::error('chat.quota.check.failed', ['err' => $e->getMessage(), 'user_id' => $me->id ?? null]); } catch (\Throwable $_) {}
			return response()->json(['ok' => false, 'error' => 'quota_check_failed', 'message' => 'No se pudo verificar disponibilidad de chat. Intenta más tarde.'], 500);
		}
		try {
			$msg = new \App\Models\Message();
			$msg->from_id = $me->id;
			$msg->to_id = $user->id;
			$msg->body = mb_substr($body, 0, 4000);
			$msg->save();
			// Broadcast event if available (non-fatal if it fails)
			try { event(new \App\Events\MessageSent($msg)); } catch (\Throwable $_) { }
			$payload = [
				'id' => $msg->id,
				'from_id' => (int) $msg->from_id,
				'to_id' => (int) $msg->to_id,
				'body' => (string) $msg->body,
				'created_at' => $msg->created_at ? $msg->created_at->toIso8601String() : null,
				'read_at' => $msg->read_at ? $msg->read_at->toIso8601String() : null,
			];
			return response()->json(['ok' => true, 'message' => $payload]);
		} catch (\Throwable $e) {
			return response()->json(['ok' => false, 'error' => 'send_error'], 500);
		}
	})->name('messages.thread.send');

	// Expose chat credits for the authenticated user (used by frontend to disable UI)
	Route::get('/user/chat-credits', function(\Illuminate\Http\Request $r){
		$user = $r->user();
		if (!$user) return response()->json(['ok' => false, 'message' => 'Unauthenticated'], 401);
		try {
			$activeSub = $user->subscriptions()->with('plan')->where(function($q){
				$q->where('status','active')
				  ->orWhereNull('ends_at')
				  ->orWhere('ends_at','>', now());
			})->orderBy('ends_at','desc')->first();

			$included = null;
			if ($activeSub && $activeSub->plan && is_array($activeSub->plan->features ?? null)) {
				$included = $activeSub->plan->features['chats_per_month'] ?? null;
				if (is_string($included)) $included = (int)$included;
			}

			$start = \Carbon\Carbon::now()->startOfMonth();
			$end = \Carbon\Carbon::now()->endOfMonth();
			$used = 0;
			try {
				if (\Illuminate\Support\Facades\Schema::hasTable('messages')) {
					$used = \App\Models\Message::where('from_id', $user->id)->whereBetween('created_at', [$start, $end])->count();
				}
			} catch (\Throwable $_) { $used = 0; }

			$purchased = 0; // ledger for chat purchases not implemented yet

			$remainingIncluded = null;
			if ($included !== null) { $remainingIncluded = max(0, (int)$included - (int)$used); }
			$total = ($remainingIncluded === null) ? -1 : ($remainingIncluded + $purchased);
			return response()->json(['ok' => true, 'included_remaining' => $remainingIncluded, 'purchased_credits' => $purchased, 'credits' => $total]);
		} catch (\Throwable $e) {
			try { \Illuminate\Support\Facades\Log::error('chat.credits.failed', ['err' => $e->getMessage(), 'user_id' => $user->id ?? null]); } catch (\Throwable $_) {}
			return response()->json(['ok' => false, 'message' => 'error'], 500);
		}
	})->name('user.chat_credits');

	// JSON: accepted friends list with last message snippet for sorting/rendering in Chat hub
	Route::get('/friends/list', function(){
		$me = auth()->user();
		try {
			if (!\Illuminate\Support\Facades\Schema::hasTable('friend_requests')) {
				return response()->json(['ok'=>true,'friends'=>[]]);
			}
			// Collect accepted friend ids (both directions)
			$rows = \App\Models\FriendRequest::query()
				->where(function($q) use ($me){ $q->where('from_id',$me->id)->orWhere('to_id',$me->id); })
				->where('status','accepted')
				->get(['from_id','to_id']);
			$fids = [];
			foreach ($rows as $r) { $fids[] = (int) ($r->from_id == $me->id ? $r->to_id : $r->from_id); }
			$fids = array_values(array_unique(array_filter($fids)));
			if (empty($fids)) return response()->json(['ok'=>true,'friends'=>[]]);

			// Map last message per friend (either direction)
			$lastBy = [];
			if (\Illuminate\Support\Facades\Schema::hasTable('messages')) {
				$messages = \App\Models\Message::query()
					->where(function($q) use ($me, $fids){
						$q->where(function($w) use ($me,$fids){ $w->where('from_id',$me->id)->whereIn('to_id',$fids); })
						  ->orWhere(function($w) use ($me,$fids){ $w->where('to_id',$me->id)->whereIn('from_id',$fids); });
					})
					->orderByDesc('created_at')
					->limit(500)
					->get(['id','from_id','to_id','body','created_at','read_at']);
				foreach ($messages as $m) {
					$pid = (int) ($m->from_id == $me->id ? $m->to_id : $m->from_id);
					if (!array_key_exists($pid, $lastBy)) { $lastBy[$pid] = $m; }
				}
			}

			// Fetch friend users
			$users = \App\Models\User::whereIn('id', $fids)->get();
			$items = [];
			foreach ($users as $u) {
				$lm = $lastBy[$u->id] ?? null;
				$profilePhoto = null;
				try {
					if (!empty($u->profile_photo_data_url)) { $profilePhoto = $u->profile_photo_data_url; }
					elseif (!empty($u->photo)) { $profilePhoto = '/storage/' . ltrim($u->photo, '/'); }
				} catch (\Throwable $_) { $profilePhoto = null; }
				$items[] = [
					'id' => (int)$u->id,
					'name' => $u->name . ' ' . $u->lastname,
					'email' => $u->email,
					'profile_photo' => $profilePhoto,
					'last_body' => $lm ? (string)$lm->body : null,
					'last_at' => $lm ? optional($lm->created_at)->toDateTimeString() : null,
					'unread' => $lm ? ((int)$lm->to_id === (int)$me->id && empty($lm->read_at)) : false,
				];
			}
			// Sort by last_at desc, nulls last; stable by name as tie-breaker
			usort($items, function($a,$b){
				$aa = $a['last_at']; $bb = $b['last_at'];
				if ($aa === $bb) { return strcasecmp($a['name'] ?? '', $b['name'] ?? ''); }
				if ($aa === null) return 1; if ($bb === null) return -1;
				return strcmp($bb, $aa);
			});
			return response()->json(['ok'=>true,'friends'=>$items]);
		} catch (\Throwable $e) {
			return response()->json(['ok'=>false], 500);
		}
	})->name('friends.list');

		// Recent conversations (message partners) for user area widgets
		Route::get('/conversations/recent', function(){
			$me = auth()->user();
			try {
				if (!\Illuminate\Support\Facades\Schema::hasTable('messages')) {
					return response()->json(['ok'=>true,'conversations'=>[]]);
				}
				$messages = \App\Models\Message::query()
					->where(function($q) use ($me){ $q->where('from_id',$me->id)->orWhere('to_id',$me->id); })
					->orderByDesc('created_at')
					->limit(500)
					->get(['id','from_id','to_id','body','created_at','read_at']);

				$lastBy = [];
				foreach ($messages as $m) {
					$pid = (int) ($m->from_id == $me->id ? $m->to_id : $m->from_id);
					if (!array_key_exists($pid, $lastBy)) { $lastBy[$pid] = $m; }
				}

				$partnerIds = array_values(array_keys($lastBy));
				if (empty($partnerIds)) return response()->json(['ok'=>true,'conversations'=>[]]);

				$users = \App\Models\User::whereIn('id', $partnerIds)->get();
				$items = [];
				foreach ($users as $u) {
					$lm = $lastBy[$u->id] ?? null;
					$profilePhoto = null;
					try {
						if (!empty($u->profile_photo_data_url)) { $profilePhoto = $u->profile_photo_data_url; }
						elseif (!empty($u->photo)) { $profilePhoto = '/storage/' . ltrim($u->photo, '/'); }
					} catch (\Throwable $_) { $profilePhoto = null; }
					$items[] = [
						'id' => (int)$u->id,
						'name' => $u->name . ' ' . $u->lastname,
						'email' => $u->email,
						'profile_photo' => $profilePhoto,
						'last_body' => $lm ? (string)$lm->body : null,
						'last_at' => $lm ? optional($lm->created_at)->toDateTimeString() : null,
						'unread' => $lm ? ((int)$lm->to_id === (int)$me->id && empty($lm->read_at)) : false,
					];
				}
				usort($items, function($a,$b){
					$aa = $a['last_at']; $bb = $b['last_at'];
					if ($aa === $bb) { return strcasecmp($a['name'] ?? '', $b['name'] ?? ''); }
					if ($aa === null) return 1; if ($bb === null) return -1;
					return strcmp($bb, $aa);
				});
				return response()->json(['ok'=>true,'conversations'=>$items]);
			} catch (\Throwable $e) {
				return response()->json(['ok'=>false], 500);
			}
		})->name('conversations.recent');

			// Backwards-compatible endpoint for legacy front-end: /messages/recent
			Route::get('/messages/recent', function(){
				$me = auth()->user();
				try {
					if (!\Illuminate\Support\Facades\Schema::hasTable('messages')) {
						return response()->json(['ok'=>true,'conversations'=>[]]);
					}
					$messages = \App\Models\Message::query()
						->where(function($q) use ($me){ $q->where('from_id',$me->id)->orWhere('to_id',$me->id); })
						->orderByDesc('created_at')
						->limit(500)
						->get(['id','from_id','to_id','body','created_at','read_at']);

					$lastBy = [];
					foreach ($messages as $m) {
						$pid = (int) ($m->from_id == $me->id ? $m->to_id : $m->from_id);
						if (!array_key_exists($pid, $lastBy)) { $lastBy[$pid] = $m; }
					}

					$partnerIds = array_values(array_keys($lastBy));
					if (empty($partnerIds)) return response()->json(['ok'=>true,'conversations'=>[]]);

					$users = \App\Models\User::whereIn('id', $partnerIds)->get();
					$items = [];
					foreach ($users as $u) {
						$lm = $lastBy[$u->id] ?? null;
						$profilePhoto = null;
						try {
							if (!empty($u->profile_photo_data_url)) { $profilePhoto = $u->profile_photo_data_url; }
							elseif (!empty($u->photo)) { $profilePhoto = '/storage/' . ltrim($u->photo, '/'); }
						} catch (\Throwable $_) { $profilePhoto = null; }
						$items[] = [
							'id' => (int)$u->id,
							'name' => $u->name,
							'email' => $u->email,
							'profile_photo' => $profilePhoto,
							'last_body' => $lm ? (string)$lm->body : null,
							'last_at' => $lm ? optional($lm->created_at)->toDateTimeString() : null,
							'unread' => $lm ? ((int)$lm->to_id === (int)$me->id && empty($lm->read_at)) : false,
						];
					}
					usort($items, function($a,$b){
						$aa = $a['last_at']; $bb = $b['last_at'];
						if ($aa === $bb) { return strcasecmp($a['name'] ?? '', $b['name'] ?? ''); }
						if ($aa === null) return 1; if ($bb === null) return -1;
						return strcmp($bb, $aa);
					});
					return response()->json(['ok'=>true,'conversations'=>$items]);
				} catch (\Throwable $e) {
					return response()->json(['ok'=>false], 500);
				}
			})->name('messages.recent');

	// RTC/ConnectyCube bootstrap endpoints (authenticated)
	Route::get('/rtc/config', function(){
		$config = [
			'appId' => (int) config('services.connectycube.app_id'),
			'authKey' => config('services.connectycube.auth_key'),
			'authSecret' => config('services.connectycube.auth_secret'),
			'apiEndpoint' => config('services.connectycube.api_endpoint'),
			'chatEndpoint' => config('services.connectycube.chat_endpoint'),
		];
		return response()->json(['ok' => true, 'config' => $config]);
	})->name('rtc.config');

	Route::get('/rtc/user', function(){
		$user = auth()->user();
		// Always use deterministic login based on our app user id
		$deterministicLogin = 'pg'.config('services.connectycube.app_id').'_'.((int)$user->id);
		$password = (string) config('services.connectycube.default_password');
		// Prefer stored cc_user_id if present for convenience; client will discover the real one via createSession
		$ccId = null;
		try {
			if (\Illuminate\Support\Facades\Schema::hasColumn('users','cc_user_id') && !empty($user->cc_user_id)) { $ccId = (int) $user->cc_user_id; }
			elseif (\Illuminate\Support\Facades\Schema::hasColumn('users','connectycube_user_id') && !empty($user->connectycube_user_id)) { $ccId = (int) $user->connectycube_user_id; }
		} catch (\Throwable $_) { $ccId = null; }
		if (!$ccId) { $ccId = (int) $user->id; }
		$payload = ['userId' => (int)$ccId, 'login' => $deterministicLogin, 'password' => $password];
		return response()->json(['ok' => true, 'user' => $payload]);
	})->name('rtc.user');

	Route::get('/rtc/map', function(){
		$auth = auth()->user(); $uid = $auth->id;
		$ids = [];
		try {
			if (\Illuminate\Support\Facades\Schema::hasTable('messages')) {
				$partners = \App\Models\Message::query()
					->select(['from_id','to_id'])
					->where(function($q) use ($uid){ $q->where('from_id',$uid)->orWhere('to_id',$uid); })
					->latest('id')->limit(200)->get();
				foreach ($partners as $m) { $ids[] = (int) ($m->from_id == $uid ? $m->to_id : $m->from_id); }
			}
		} catch (\Throwable $_) {}
		$ids = array_values(array_unique(array_filter($ids)));
		$map = [];
		foreach ($ids as $id) {
			$ccId = null;
			try {
				$u = \App\Models\User::find($id);
				if ($u) {
					if (\Illuminate\Support\Facades\Schema::hasColumn('users','cc_user_id') && !empty($u->cc_user_id)) { $ccId = (int)$u->cc_user_id; }
					elseif (\Illuminate\Support\Facades\Schema::hasColumn('users','connectycube_user_id') && !empty($u->connectycube_user_id)) { $ccId = (int)$u->connectycube_user_id; }
				}
			} catch (\Throwable $_) { $ccId = null; }
			if (!$ccId) $ccId = (int)$id; // fallback same id
			$map[(string)$id] = (int)$ccId;
		}
		return response()->json(['ok'=>true,'map'=>$map]);
	})->name('rtc.map');

	Route::get('/rtc/bootstrap', function(){
		// Compose all pieces into a single payload
		$config = [
			'appId' => (int) config('services.connectycube.app_id'),
			'authKey' => config('services.connectycube.auth_key'),
			'authSecret' => config('services.connectycube.auth_secret'),
			'apiEndpoint' => config('services.connectycube.api_endpoint'),
			'chatEndpoint' => config('services.connectycube.chat_endpoint'),
		];
	$user = auth()->user();
	$password = (string) config('services.connectycube.default_password');
	$deterministicLogin = 'pg'.config('services.connectycube.app_id').'_'.((int)$user->id);
	// Prefer stored cc_user_id if present; client will learn the authoritative user_id from CC
	$ccId = null;
	try { if (\Illuminate\Support\Facades\Schema::hasColumn('users','cc_user_id') && !empty($user->cc_user_id)) { $ccId = (int)$user->cc_user_id; } } catch (\Throwable $_) {}
	try { if (!$ccId && \Illuminate\Support\Facades\Schema::hasColumn('users','connectycube_user_id') && !empty($user->connectycube_user_id)) { $ccId = (int)$user->connectycube_user_id; } } catch (\Throwable $_) {}
	if (!$ccId) $ccId = (int)$user->id;
	$ccUser = ['userId' => (int)$ccId, 'login' => $deterministicLogin, 'password' => $password];
		// Build map like /rtc/map e incluir participantes explícitos de la cita
		$ids = [];
		// Siempre incluir usuario actual para asegurar presencia en userIdMap
		$ids[] = (int)$user->id;
		// Param opcional other (id del otro participante pasado desde frontend)
		$other = (int) request()->query('other', 0);
		if($other > 0 && $other !== (int)$user->id){ $ids[] = $other; }
		// Param opcional appt (id de cita) para agregar professional y patient
		$apptId = (int) request()->query('appt', 0);
		if($apptId > 0){
			try {
				$appt = \App\Models\Appointment::find($apptId);
				if($appt){
					if($appt->professional_id && $appt->professional_id !== $user->id) $ids[] = (int)$appt->professional_id;
					if($appt->patient_id && $appt->patient_id !== $user->id) $ids[] = (int)$appt->patient_id;
				}
			} catch(\Throwable $_) {}
		}
		try {
			if (\Illuminate\Support\Facades\Schema::hasTable('messages')) {
				$partners = \App\Models\Message::query()
					->select(['from_id','to_id'])
					->where(function($q) use ($user){ $q->where('from_id',$user->id)->orWhere('to_id',$user->id); })
					->latest('id')->limit(200)->get();
				foreach ($partners as $m) { $ids[] = (int) ($m->from_id == $user->id ? $m->to_id : $m->from_id); }
			}
		} catch (\Throwable $_) {}
		$ids = array_values(array_unique(array_filter($ids)));
		$map = [];
		foreach ($ids as $id) {
			$cid = null;
			try {
				$u = \App\Models\User::find($id);
				if ($u) {
					if (\Illuminate\Support\Facades\Schema::hasColumn('users','cc_user_id') && !empty($u->cc_user_id)) { $cid = (int)$u->cc_user_id; }
					elseif (\Illuminate\Support\Facades\Schema::hasColumn('users','connectycube_user_id') && !empty($u->connectycube_user_id)) { $cid = (int)$u->connectycube_user_id; }
				}
			} catch (\Throwable $_) { $cid = null; }
			if (!$cid) $cid = (int)$id;
			$map[(string)$id] = (int)$cid;
		}
		return response()->json(['ok'=>true,'ccConfig'=>$config,'ccUser'=>$ccUser,'userIdMap'=>$map]);
	})->name('rtc.bootstrap');

		// Consume a chat credit for initiating a call (voice/video).
		Route::post('/rtc/consume', function(\Illuminate\Http\Request $r){
			$user = $r->user();
			if (!$user) return response()->json(['ok' => false, 'message' => 'unauthenticated'], 401);
			$type = (string) ($r->input('type') ?? 'voice');
			if (!in_array($type, ['voice','video'], true)) $type = 'voice';

			try {
				return \Illuminate\Support\Facades\DB::transaction(function() use($user,$type){
					$activeSub = $user->subscriptions()->with('plan')->where(function($q){
						$q->where('status','active')->orWhereNull('ends_at')->orWhere('ends_at','>', now());
					})->orderBy('ends_at','desc')->first();

					$included = null;
					if ($activeSub && $activeSub->plan && is_array($activeSub->plan->features ?? null)) {
						$included = $activeSub->plan->features['chats_per_month'] ?? null;
						if (is_string($included)) $included = (int)$included;
					}

					$start = \Carbon\Carbon::now()->startOfMonth();
					$end = \Carbon\Carbon::now()->endOfMonth();
					$usedMessages = 0;
					try {
						if (\Illuminate\Support\Facades\Schema::hasTable('messages')) {
							$usedMessages = \App\Models\Message::where('from_id', $user->id)->whereBetween('created_at', [$start, $end])->count();
						}
					} catch (\Throwable $_) { $usedMessages = 0; }

					$usedCalls = 0;
					try {
						if (\Illuminate\Support\Facades\Schema::hasTable('chat_call_logs')) {
							$usedCalls = \App\Models\ChatCallLog::where('user_id', $user->id)->whereBetween('created_at', [$start, $end])->count();
						}
					} catch (\Throwable $_) { $usedCalls = 0; }

					$used = (int)$usedMessages + (int)$usedCalls;
					$purchased = 0; // purchased chat ledger not implemented yet

					$remainingIncluded = null;
					if ($included !== null) { $remainingIncluded = max(0, (int)$included - (int)$used); }
					$totalAvailable = ($remainingIncluded === null) ? -1 : ($remainingIncluded + (int)$purchased);
					if ($totalAvailable !== -1 && $totalAvailable <= 0) {
						return response()->json(['ok' => false, 'error' => 'quota_exceeded', 'message' => 'Has alcanzado el límite de chats este mes. Actualiza tu plan.'], 402);
					}

					// Record the call consumption
					try {
						$log = \App\Models\ChatCallLog::create(['user_id' => $user->id, 'type' => $type]);
					} catch (\Throwable $e) {
						\Illuminate\Support\Facades\Log::error('rtc.consume.log_failed', ['err' => $e->getMessage(), 'user_id' => $user->id]);
					}

					// Return remaining credits after this consumption
					$remaining = ($remainingIncluded === null) ? -1 : max(0, $remainingIncluded - 1 + (int)$purchased);
					return response()->json(['ok' => true, 'consumed' => true, 'credits' => $remaining]);
				});
			} catch (\Throwable $e) {
				try { \Illuminate\Support\Facades\Log::error('rtc.consume.failed', ['err' => $e->getMessage(), 'user_id' => $user->id ?? null]); } catch (\Throwable $_) {}
				return response()->json(['ok' => false, 'message' => 'error'], 500);
			}
		})->name('rtc.consume');

	// Sync CC identifiers back to DB when the client discovers them (no new migrations; use columns if they exist)
	Route::post('/rtc/sync', function(\Illuminate\Http\Request $r){
		$user = auth()->user();
		$ccUserId = (int) ($r->input('cc_user_id') ?? 0);
		$ccLogin = trim((string) ($r->input('cc_login') ?? ''));
		$changed = false;
		try {
			if ($ccUserId > 0) {
				if (\Illuminate\Support\Facades\Schema::hasColumn('users','connectycube_user_id')) { if ((int)($user->connectycube_user_id ?? 0) !== $ccUserId) { $user->connectycube_user_id = $ccUserId; $changed = true; } }
				if (\Illuminate\Support\Facades\Schema::hasColumn('users','cc_user_id')) { if ((int)($user->cc_user_id ?? 0) !== $ccUserId) { $user->cc_user_id = $ccUserId; $changed = true; } }
			}
			if ($ccLogin !== '') {
				if (\Illuminate\Support\Facades\Schema::hasColumn('users','cc_login')) { if ((string)($user->cc_login ?? '') !== $ccLogin) { $user->cc_login = $ccLogin; $changed = true; } }
			}
			if ($changed) { $user->save(); }
		} catch (\Throwable $_) {}
		return response()->json(['ok'=>true,'changed'=>$changed]);
	})->name('rtc.sync');

	// Public-ish endpoint to fetch another user's presence/status and avatar (requires auth)
	Route::get('/users/{user}/status', function(\App\Models\User $user){
		// expose a small set of fields safe for authenticated users
		$lastSeen = null; $recent = false;
		if (isset($user->last_seen_at)) {
			if ($user->last_seen_at instanceof \DateTimeInterface) {
				$lastSeen = $user->last_seen_at->format('Y-m-d H:i:s');
				$recent = now()->diffInSeconds($user->last_seen_at) <= 90; // within 90s = online
			} else {
				$lastSeen = is_string($user->last_seen_at) && $user->last_seen_at !== '' ? $user->last_seen_at : null;
				try { $recent = $lastSeen ? (now()->diffInSeconds(\Carbon\Carbon::parse($lastSeen)) <= 90) : false; } catch (\Throwable $_) { $recent = false; }
			}
		}
		$profilePhoto = null;
		try {
			if (!empty($user->profile_photo_data_url)) $profilePhoto = $user->profile_photo_data_url;
			elseif (!empty($user->photo)) $profilePhoto = '/storage/' . ltrim($user->photo, '/');
		} catch (\Throwable $_) { $profilePhoto = null; }
		$base = (string) ($user->status ?? '');
		// Honor explicit status including 'offline'
		if (in_array($base, ['online','busy','dnd','away','offline'], true)) {
			$status = $base;
		} else {
			$status = $recent ? 'online' : 'offline';
		}
		return response()->json(['ok'=>true,'user_id'=>$user->id,'status'=>$status,'last_seen_at'=>$lastSeen,'profile_photo'=>$profilePhoto]);
	})->name('users.status');

	// Return a user's public gallery (requires auth)
	Route::get('/users/{user}/photos', function(\App\Models\User $user){
		try {
			$photos = \App\Models\UserPhoto::where('user_id', $user->id)->orderBy('id','desc')->get()->map(function($p){
				$publicUrl = null; $secureUrl = null;
				try { if (!empty($p->path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($p->path)) { $publicUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($p->path); if (\Illuminate\Support\Facades\Route::has('secure.storage')) { $enc = rtrim(strtr(base64_encode($p->path), '+/', '-_'), '='); $secureUrl = route('secure.storage', ['encoded' => $enc]); } } } catch (\Throwable $_) {}
				return [ 'id'=>$p->id, 'caption'=>$p->caption, 'is_profile'=>(bool)$p->is_profile, 'url'=>$publicUrl, 'secure_url'=>$secureUrl, 'created_at'=>optional($p->created_at)->toDateTimeString() ];
			});
			return response()->json(['ok'=>true,'photos'=>$photos]);
		} catch (\Throwable $e) { return response()->json(['ok'=>false,'message'=>'error'],500); }
	})->name('users.photos');

	// Friend requests
	Route::post('/friend/{user}/request', [\App\Http\Controllers\FriendRequestController::class, 'send'])->name('friend.request');
	Route::post('/friend/request/{requestModel}/accept', [\App\Http\Controllers\FriendRequestController::class, 'accept'])->name('friend.request.accept');
	Route::post('/friend/request/{requestModel}/reject', [\App\Http\Controllers\FriendRequestController::class, 'reject'])->name('friend.request.reject');
	Route::get('/friend/requests/pending', [\App\Http\Controllers\FriendRequestController::class, 'pending'])->name('friend.requests.pending');
	// Friends list & search
	// Legacy friends page: redirect to unified chat hub
	Route::get('/friends', function(){ return redirect()->route('chat.index'); });
	Route::get('/friends/search', [\App\Http\Controllers\FriendsController::class, 'search'])->name('friends.search');

	// Outgoing friend requests (for unified chat hub)
	Route::get('/friend/requests/outgoing', function(){
		$u = auth()->user();
		try {
			if (!\Illuminate\Support\Facades\Schema::hasTable('friend_requests')) {
				return response()->json(['ok'=>true,'requests'=>[]]);
			}
			$rows = \App\Models\FriendRequest::with('to')
				->where('from_id', $u->id)
				->where('status','pending')
				->latest()
				->limit(50)
				->get();
			$requests = $rows->map(function($r){
				return [
					'id' => (int)$r->id,
					'to' => $r->to ? [ 'id'=>(int)$r->to->id, 'name'=>$r->to->name, 'email'=>$r->to->email ] : null,
				];
			});
			return response()->json(['ok'=>true,'requests'=>$requests]);
		} catch (\Throwable $e) {
			return response()->json(['ok'=>false,'error'=>'server'], 500);
		}
	})->name('friend.requests.outgoing');

	// Create a new friend request
	Route::post('/friend/{user}/request', function(\App\Models\User $user){
		$me = auth()->user();
		if ($me->id === $user->id) { return response()->json(['ok'=>false,'error'=>'self'], 422); }
		try {
			if (!\Illuminate\Support\Facades\Schema::hasTable('friend_requests')) {
				return response()->json(['ok'=>false,'error'=>'unavailable'], 503);
			}
			// Optional role gating: allow only user<->professional (2<->3) if roles table is present
			try {
				$myRoles = method_exists($me, 'roles') ? $me->roles()->pluck('id')->map(fn($i)=>(int)$i)->toArray() : [];
				$targetRoles = method_exists($user, 'roles') ? $user->roles()->pluck('id')->map(fn($i)=>(int)$i)->toArray() : [];
				$is2 = in_array(2, $myRoles, true); $is3 = in_array(3, $myRoles, true);
				$targetIs2 = in_array(2, $targetRoles, true); $targetIs3 = in_array(3, $targetRoles, true);
				if (($is2 && !$targetIs3) || ($is3 && !$targetIs2)) {
					return response()->json(['ok'=>false,'error'=>'role_mismatch'], 403);
				}
			} catch (\Throwable $_) { /* ignore gating if roles absent */ }

			// Prevent duplicates (pending either direction)
			$exists = \App\Models\FriendRequest::where(function($q) use ($me,$user){
				$q->where([['from_id',$me->id],['to_id',$user->id]])
				 ->orWhere([['from_id',$user->id],['to_id',$me->id]]);
			})->where('status','pending')->exists();
			if ($exists) { return response()->json(['ok'=>true,'duplicate'=>true]); }

			\App\Models\FriendRequest::create([
				'from_id' => $me->id,
				'to_id' => $user->id,
				'status' => 'pending',
			]);
			return response()->json(['ok'=>true]);
		} catch (\Throwable $e) {
			return response()->json(['ok'=>false,'error'=>'server'], 500);
		}
	})->name('friend.request.create');
});

	// Simple JSON endpoints for AJAX notifications polling and marking
	Route::middleware('auth')->group(function(){
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
					// flatten common fields for ease on client
					'title' => $data['title'] ?? null,
					'body' => $data['body'] ?? ($data['message'] ?? null),
					'icon' => $data['icon'] ?? 'bell',
					'link' => $data['link'] ?? ($data['url'] ?? '#'),
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

		// Delete a notification
		Route::post('/api/notifications/delete', function(\Illuminate\Http\Request $r){
			$user = auth()->user();
			$id = $r->input('id');
			if ($id) {
				$notif = $user->notifications()->where('id', $id)->first();
				if ($notif) { $notif->delete(); }
			}
			return response()->json(['ok' => true]);
		});
	});

	// Global counters (messages unread, pending friend requests)
	Route::middleware('auth')->get('/api/counters', function(){
	$u = auth()->user();
	$unread = 0; $pending = 0;
	try { if (\Illuminate\Support\Facades\Schema::hasTable('messages')) { $unread = \App\Models\Message::where('to_id',$u->id)->whereNull('read_at')->count(); } } catch(\Throwable $_){}
	try { if (\Illuminate\Support\Facades\Schema::hasTable('friend_requests')) { $pending = \App\Models\FriendRequest::where('to_id',$u->id)->where('status','pending')->count(); } } catch(\Throwable $_){}
	return response()->json(['ok'=>true,'messages_unread'=>$unread,'friend_requests_pending'=>$pending]);
	})->name('api.counters');
