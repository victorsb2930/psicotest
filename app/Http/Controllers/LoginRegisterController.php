<?php

namespace App\Http\Controllers;

use App\Models\LoginRegisterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role as SpatieRole;
use App\Models\ProfessionalApplication;

class LoginRegisterController extends Controller
{
	public function register(Request $request)
	{
		$rules = [
			'reg_type' => ['required', 'integer', Rule::exists('roles', 'id')->where('show_in_signup', true)],
			'reg_name' => ['required', 'string', 'max:255'],
			'reg_email' => ['required', 'email', 'max:255', 'unique:users,email'],
			'reg_password' => ['required', 'string', 'min:6', 'confirmed'],
			'reg_titulo' => ['nullable','file','mimes:pdf,jpg,jpeg,png','max:8192'],
			'reg_cedula' => ['nullable','file','mimes:pdf,jpg,jpeg,png','max:8192'],
		];

		$messages = [
			'reg_email.unique' => 'El email ya está en uso.',
			'reg_email.email' => 'Ingresa un email válido.',
			'reg_password.confirmed' => 'La confirmación de la contraseña no coincide.',
			'reg_password.min' => 'La contraseña debe tener al menos 6 caracteres.',
		];

		$attributes = [
			'reg_type' => 'tipo de usuario',
			'reg_name' => 'nombre',
			'reg_email' => 'email',
			'reg_password' => 'contraseña',
			'reg_password_confirmation' => 'confirmar contraseña',
		];

		$validated = $request->validate($rules, $messages, $attributes);
		// normalize email to lowercase for case-insensitive handling
		if (!empty($validated['reg_email'])) {
			$validated['reg_email'] = strtolower($validated['reg_email']);
		}

		// enforce case-insensitive uniqueness (in case DB collation is case-sensitive)
		try {
			$exists = \App\Models\User::whereRaw('LOWER(email) = ?', [$validated['reg_email']])->exists();
			if ($exists) {
				if ($request->expectsJson()) {
					return response()->json(['ok' => false, 'message' => 'El email ya está en uso.'], 422);
				}
				return back()->withErrors(['reg_email' => 'El email ya está en uso.'])->withInput();
			}
		} catch (\Throwable $e) { /* noop: fallback to DB unique rule */ }

		$adminEmails = (array) config('app.admin_emails', []);
		$regEmailLc = strtolower((string)($validated['reg_email'] ?? ''));
		$isAdminEmail = in_array($regEmailLc, $adminEmails, true);

		// Determinar si el tipo requiere documentos
		$roleRow = \Illuminate\Support\Facades\DB::table('roles')->where('id', (int)($validated['reg_type'] ?? 0))->first();
		$requiresDocs = $roleRow && (bool)($roleRow->requires_docs ?? false);
		$roleName = strtolower((string)($roleRow->name ?? ''));
		if (in_array($roleName, ['professional','profesional'], true)) {
			$requiresDocs = true;
		}
		if ($isAdminEmail) { $requiresDocs = false; }
		if ($requiresDocs) {
			$request->validate([
				'reg_titulo' => ['required','file','mimes:pdf,jpg,jpeg,png','max:8192'],
				'reg_cedula' => ['required','file','mimes:pdf,jpg,jpeg,png','max:8192'],
			], $messages, [ 'reg_titulo' => 'título profesional', 'reg_cedula' => 'cédula' ]);
		}

		// Si la validación pasa, se crea el usuario
		$user = LoginRegisterModel::create([
			'name' => $validated['reg_name'],
			'email' => strtolower($validated['reg_email'] ?? ''),
			'password' => Hash::make($validated['reg_password']),
			'remember_token' => Str::random(10),
		]);

		// If a profile photo was uploaded during registration, store it and mark as profile
		try {
			if ($request->hasFile('reg_photo')) {
				$file = $request->file('reg_photo');
				try {
					$contents = file_get_contents($file->getRealPath());
					if (\Schema::hasTable('user_photos')) {
						\App\Models\UserPhoto::create([
							'user_id' => $user->id,
							'foto' => $contents,
							'caption' => 'Foto de registro',
							'is_profile' => true,
						]);
					}
				} catch (\Throwable$e) {
					// noop fallback - ignore if binary save fails
				}
			}
		} catch (\Throwable $e) { /* noop */ }
		if ($requiresDocs) {
			$user->is_active = false; // pendiente de aprobación
			$user->save();
		}
		if ($isAdminEmail) {
			$user->is_active = true; // admin siempre activo
			$user->save();
		}

		// Asignar rol según selección (o admin por email ENV)
		try {
			$rid = $isAdminEmail
				? \Illuminate\Support\Facades\DB::table('roles')->where('name', 'admin')->value('id')
				: (int) $validated['reg_type'];
			if ($rid) {
				if ($isAdminEmail) {
					$adminRole = SpatieRole::where('name','admin')->first();
					if ($adminRole) {
						$user->syncRoles([$adminRole->name]);
					}
				} else {
					if (!$requiresDocs) { // no asignar rol profesional hasta aprobación
						$role = SpatieRole::find($rid);
						if ($role) $user->syncRoles([$role->name]);
					}
				}
			}
		} catch (\Throwable $e) { /* noop */ }

		// Crear solicitud pendiente si el rol requiere documentos
		$successMsg = 'Registro exitoso. Ahora puedes iniciar sesión.';
		try {
			if ($requiresDocs && !$isAdminEmail) {
				$tituloPath = null; $cedulaPath = null;
				if ($request->hasFile('reg_titulo')) {
					$tituloPath = $request->file('reg_titulo')->store('professional_docs', 'local');
				}
				if ($request->hasFile('reg_cedula')) {
					$cedulaPath = $request->file('reg_cedula')->store('professional_docs', 'local');
				}
				ProfessionalApplication::create([
					'user_id' => $user->id,
					'titulo_path' => $tituloPath,
					'cedula_path' => $cedulaPath,
					'status' => 'pending',
				]);
				$successMsg = 'Registro enviado. Un administrador revisará tus documentos y aprobará tu cuenta profesional.';
			}
		} catch (\Throwable $e) { /* noop */ }

		// TODO: Enviar email de verificación, etc.
		if ($request->expectsJson()) {
			return response()->json([
				'ok' => true,
				'message' => $successMsg
			]);
		}
		if ($requiresDocs) {
			return redirect()->route('underreview')->with('success', $successMsg);
		}
		return redirect('/')->with('success', $successMsg);
	}

	public function login(Request $request)
	{
		$validated = $request->validate([
			'email' => ['required', 'email'],
			'password' => ['required', 'string', 'min:6'],
		], [
			'email.required' => 'Ingresa tu email.',
			'email.email' => 'Ingresa un email válido.',
			'password.required' => 'Ingresa tu contraseña.',
			'password.min' => 'Contraseña incorrecta.',
		], [
			'email' => 'email',
			'password' => 'contraseña',
		]);
		$remember = $request->has('remember');

		// Pre-chequeo: auto-ascenso admin por ENV, cuenta eliminada o bajo revisión
		// lookup user case-insensitively
		$user = null;
		try {
			$user = \App\Models\User::whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])->first();
		} catch (\Throwable $e) {
			// fallback
			$user = \App\Models\User::where('email', $validated['email'])->first();
		}
		if ($user) {
			try {
				$emails = (array) config('app.admin_emails', []);
				if (in_array(strtolower($user->email), $emails, true)) {
					$adminRole = SpatieRole::where('name','admin')->first();
					if ($adminRole && !$user->hasRole('admin')) {
						$user->syncRoles([$adminRole->name]);
					}
					if (!$user->is_active) { $user->is_active = true; $user->save(); }
				}
			} catch (\Throwable $e) { /* noop */ }
		}
		if ($user && $user->trashed()) {
			$errMsg = 'Cuenta desactivada. Contacta al administrador.';
			if ($request->expectsJson()) return response()->json(['ok' => false, 'message' => $errMsg], 403);
			return back()->withErrors(['email' => $errMsg])->withInput();
		}
		if ($user && !$user->is_active) {
			// If the user has an explicit rejected application, send them to
			// the rejected-details page which shows the rejection reason.
			$rejected = ProfessionalApplication::where('user_id', $user->id)->where('status','rejected')->orderByDesc('id')->first();
			if ($rejected) {
				$infoMsg = 'Tu solicitud fue rechazada.';
				// Only reveal the rejection notes to the owner who proves ownership
				// by providing the correct password in the login attempt. This avoids
				// leaking the admin note to arbitrary visitors.
				$notes = null;
				try {
					if (!empty($validated['password']) && \Illuminate\Support\Facades\Hash::check($validated['password'], $user->password)) {
						$notes = (string) $rejected->notes;
					}
				} catch (\Throwable $_) { /* ignore hash errors */ }

				if ($request->expectsJson()) {
					// For AJAX flows, provide a temporary signed URL so the client can
					// navigate directly without exposing the page publicly.
					$signed = \URL::temporarySignedRoute(
						'underreview.rejected', now()->addMinutes(10), ['application' => $rejected->id]
					);
					$payload = ['ok' => false, 'rejected' => true, 'redirect' => $signed, 'message' => $infoMsg];
					if ($notes !== null) $payload['notes'] = $notes;
					// Debug: log that we're returning a rejected JSON payload (do not log notes content)
					try { \Illuminate\Support\Facades\Log::info('login.rejected_json', ['user_id' => $user->id, 'application_id' => $rejected->id, 'includes_notes' => ($notes !== null)]); } catch (\Throwable $_) { }
					return response()->json($payload, 403);
				}

				// For non-AJAX flows, if password matched, set a one-time session
				// flash key so the redirected view can be displayed immediately
				// without making the route globally public.
				if ($notes !== null) {
					// store a one-time flag containing the application id
					session()->flash('allow_rejected_view', $rejected->id);
					// Debug: log that we're redirecting with flash notes
					try { \Illuminate\Support\Facades\Log::info('login.rejected_redirect_flash', ['user_id' => $user->id, 'application_id' => $rejected->id, 'includes_notes' => true]); } catch (\Throwable $_) { }
					return redirect()->route('underreview.rejected', ['application' => $rejected->id])->with(['info' => $infoMsg, 'rejection_notes' => $notes])->withInput();
				}

				// Set the one-time flag even when notes are not present so the page
				// can be reached immediately after login (but not directly via URL).
				session()->flash('allow_rejected_view', $rejected->id);
				try { \Illuminate\Support\Facades\Log::info('login.rejected_redirect_flash', ['user_id' => $user->id, 'application_id' => $rejected->id, 'includes_notes' => false]); } catch (\Throwable $_) { }
				return redirect()->route('underreview.rejected', ['application' => $rejected->id])->with('info', $infoMsg)->withInput();
			}
			$hasPending = ProfessionalApplication::where('user_id', $user->id)->where('status','pending')->exists();
			$infoMsg = $hasPending ? 'Tu cuenta profesional está en revisión.' : 'Tu cuenta está desactivada.';
			if ($request->expectsJson()) {
				return response()->json(['ok' => false, 'under_review' => true, 'redirect' => route('underreview'), 'message' => $infoMsg], 403);
			}
			return redirect()->route('underreview')->with('info', $infoMsg)->withInput();
		}

	// ensure credentials use the actual stored email if we found the user case-insensitively
	$emailForAttempt = $user?->email ?? strtolower($validated['email']);
	$credentials = ['email' => $emailForAttempt, 'password' => $validated['password']];
	if (auth()->attempt($credentials, $remember)) {
			// Session id is usually regenerated to prevent session fixation.
			// The Login event listener runs during auth()->attempt() and may
			// have stored the PRE-REGENERATE session id in user_logins. To
			// ensure the logout handler can find the row by session_id, we
			// capture the old id and after regenerating update the row to
			// use the new session id.
			$oldSid = $request->session()->getId();
			$request->session()->regenerate();
			$user = auth()->user();
			$newSid = $request->session()->getId();
			try {
				if (\Schema::hasTable('user_logins')) {
					$updated = \DB::table('user_logins')
						->where('user_id', $user->id)
						->where('session_id', $oldSid)
						->update(['session_id' => $newSid]);
					// If nothing was updated (row not found by old session id) try a
					// best-effort fallback: update the most recent open row for this
					// user (no ended_at) which is likely the one the login listener
					// just created. This handles cases where session ids were rotated
					// earlier or the listener saw a different id.
					if (empty($updated)) {
						try {
						\DB::table('user_logins')
							->where('user_id', $user->id)
							->whereNull('ended_at')
							->orderBy('id', 'desc')
							->limit(1)
							->update(['session_id' => $newSid]);
						} catch (\Throwable $_) { /* ignore */ }
					}
				}
			} catch (\Throwable $_) { /* noop */ }

			// Si no tiene rol asignado, forzar rol 'user' si existe (fallback seguro)
			try {
				if (($user->roles()->count() ?? 0) === 0) {
					$roleUser = SpatieRole::where('name','user')->first();
					if ($roleUser) { $user->syncRoles([$roleUser->name]); }
				}
			} catch (\Throwable $e) { /* noop */ }
			$slugs = $user->roles()->pluck('name')->map(fn($s) => strtolower((string)$s))->filter()->values()->all();
			$names = $user->roles()->pluck('name')->map(fn($n) => strtolower((string)$n))->filter()->values()->all();
			$isAdmin = in_array('admin', $slugs, true) || in_array('admin', $names, true) || in_array('administrador', $names, true);
			$isPro = in_array('professional', $slugs, true) || in_array('professional', $names, true) || in_array('profesional', $names, true);
			$isUser = in_array('user', $slugs, true) || in_array('user', $names, true) || in_array('usuario', $names, true);
			$page = $isAdmin ? '/adminarea' : ($isPro ? '/professionalarea' : ($isUser ? '/userarea' : null));
			if (!$page) {
				auth()->logout();
				$request->session()->invalidate();
				$request->session()->regenerateToken();
				$errMsg = 'Tu cuenta no tiene un rol asignado. Contacta al administrador.';
				if ($request->expectsJson()) {
					return response()->json(['ok' => false, 'message' => $errMsg], 422);
				}
				return back()->withErrors(['email' => $errMsg])->withInput();
			}
			// Asegura que no persista un 'url.intended' de una sesión anterior
			$request->session()->forget('url.intended');
			if ($request->expectsJson()) {
				return response()->json([
					'ok' => true,
					'message' => 'Inicio de sesión exitoso.',
					'redirect' => $page,
				]);
			}
			return redirect()->to($page);
		}
		$errMsg = 'Credenciales inválidas.';
		if ($request->expectsJson()) {
			return response()->json(['ok' => false, 'message' => $errMsg], 422);
		}
		return back()->withErrors(['email' => $errMsg])->withInput();
	}

	public function logout(Request $request) {
		// Debug: log logout attempt with current session id and user
		try {
			Log::info('logout.attempt', ['user_id' => auth()->id(), 'session_id' => $request->session()->getId()]);
		} catch (\Throwable $_) {}
		// Best-effort: mark the user_login row ended before invalidating session.
		try {
			if (\Schema::hasTable('user_logins')) {
				$sid = $request->session()->getId();
				$ul = \App\Models\UserLogin::where('session_id', $sid)->orderBy('id', 'desc')->first();
				if ($ul && !$ul->ended_at) {
					$ul->ended_at = now();
					$ul->duration_seconds = (int) max(0, now()->getTimestamp() - ($ul->started_at ? $ul->started_at->getTimestamp() : now()->getTimestamp()));
					$ul->saveQuietly();
					try { Log::info('logout.controller.closed', ['user_login_id' => $ul->id, 'session_id' => $sid, 'user_id' => auth()->id()]); } catch (\Throwable $_) {}
				}
			}
		} catch (\Throwable $_) { /* best-effort */ }
		auth()->logout();

		// Limpieza explícita (aunque invalidate() la elimina de todos modos)
		$request->session()->forget('url.intended');
		$request->session()->invalidate();
		$request->session()->regenerateToken();
		if ($request->expectsJson()) {
			return response()->json(['ok' => true, 'message' => 'Sesión cerrada.']);
		}
		return redirect('/');
	}

	/**
	 * Endpoint called by client to record session end (best-effort).
	 * Uses the current authenticated user (if any) and the session id to
	 * find the matching user_logins row and set ended_at/duration_seconds
	 * if it hasn't been set already.
	 */
	public function endSession(Request $request)
	{
		$user = auth()->user();
		try {
			if (!\Schema::hasTable('user_logins')) {
				return response()->json(['ok' => false, 'message' => 'user_logins missing'], 500);
			}
			$sid = $request->getSession()->getId();
			$ul = null;
			if ($user) {
				$ul = \App\Models\UserLogin::where('user_id', $user->id)->where('session_id', $sid)->orderBy('id', 'desc')->first();
			}
			if (!$ul) {
				// fallback: find last row by session id even if user missing
				$ul = \App\Models\UserLogin::where('session_id', $sid)->orderBy('id', 'desc')->first();
			}
			if ($ul && !$ul->ended_at) {
				$ul->ended_at = now();
				$ul->duration_seconds = (int) max(0, now()->getTimestamp() - ($ul->started_at ? $ul->started_at->getTimestamp() : now()->getTimestamp()));
				$ul->saveQuietly();
				\Illuminate\Support\Facades\Log::info('user_login.closed_by_endSession', ['session_id' => $sid, 'user_login_id' => $ul->id, 'user_id' => $user?->id]);
			} else {
				// Best-effort fallback: close the most recent open user_login for
				// this authenticated user when the row couldn't be found by
				// session_id. This addresses cases where the session id changed
				// between the login listener and this request.
				if ($user) {
					try {
						$ul2 = \App\Models\UserLogin::where('user_id', $user->id)->whereNull('ended_at')->orderBy('id','desc')->first();
						if ($ul2) {
							$ul2->ended_at = now();
							$ul2->duration_seconds = (int) max(0, now()->getTimestamp() - ($ul2->started_at ? $ul2->started_at->getTimestamp() : now()->getTimestamp()));
							$ul2->saveQuietly();
							\Illuminate\Support\Facades\Log::info('user_login.closed_by_endSession_fallback', ['user_login_id' => $ul2->id, 'user_id' => $user->id ?? null]);
						}
					} catch (\Throwable $_) { /* ignore */ }
				}
			}
		} catch (\Throwable $e) {
			// best-effort, do not throw for client
		}
		return response()->json(['ok' => true]);
	}
}
