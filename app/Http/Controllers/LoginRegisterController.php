<?php

namespace App\Http\Controllers;

use App\Models\LoginRegisterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;
use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role as SpatieRole;
use App\Models\ProfessionalApplication;
use Illuminate\Support\Facades\File;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Payment;
use Illuminate\Support\Facades\Schema;

class LoginRegisterController extends Controller
{
	public function register(Request $request)
	{
		// If client sent encrypted password fields, decrypt them into plain fields
		$this->ensureLoginKeyExists();
		$this->decryptIfPresent($request, 'reg_password_enc', 'reg_password');
		$this->decryptIfPresent($request, 'reg_password_confirm_enc', 'reg_password_confirmation');

		$rules = [
			'reg_type' => ['required', 'integer', Rule::exists('roles', 'id')->where('show_in_signup', true)],
			'reg_name' => ['required', 'string', 'max:255'],
			'reg_lastname' => ['required', 'string', 'max:255'],
			'reg_email' => ['required', 'email', 'max:255', 'unique:users,email'],
			'reg_password' => ['required', 'string', 'min:6', 'confirmed'],
			'reg_titulo' => ['nullable','file','mimes:pdf,jpg,jpeg,png','max:8192'],
			'reg_cedula' => ['nullable','file','mimes:pdf,jpg,jpeg,png','max:8192'],
			'reg_cv' => ['nullable','file','mimes:pdf,jpg,jpeg,png','max:8192'],
			'reg_exequatur' => ['nullable','file','mimes:pdf,jpg,jpeg,png','max:8192'],
			'reg_speciality' => ['nullable','string','max:255'],
			'reg_location' => ['required','string','max:255'],
			'reg_birthdate' => ['required','date'],
			'reg_gender' => ['required','string','max:40']
		];

		$messages = [
			'reg_name.required' => 'El nombre es obligatorio.',
			'reg_lastname.required' => 'El apellido es obligatorio.',
			'reg_birthdate.required' => 'La fecha de nacimiento es obligatoria.',
			'reg_gender.required' => 'El género es obligatorio.',
			'reg_email.unique' => 'El email ya está en uso.',
			'reg_email.email' => 'Ingresa un email válido.',
			'reg_password.required' => 'La contraseña es obligatoria.',
			'reg_password.confirmed' => 'La confirmación de la contraseña no coincide.',
			'reg_password.min' => 'La contraseña debe tener al menos 6 caracteres.',
		];

		$attributes = [
			'reg_type' => 'tipo de usuario',
			'reg_name' => 'nombre',
			'reg_lastname' => 'apellido',
			'reg_birthdate' => 'fecha de nacimiento',
			'reg_gender' => 'género',
			'reg_speciality' => 'especialidad',
			'reg_location' => 'ubicación',
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
				'reg_speciality' => ['required','string','max:255'],
				'reg_titulo' => ['required','file','mimes:pdf,jpg,jpeg,png','max:8192'],
				'reg_cedula' => ['required','file','mimes:pdf,jpg,jpeg,png','max:8192'],
				'reg_cv' => ['required','file','mimes:pdf,jpg,jpeg,png','max:8192'],
				'reg_exequatur' => ['required','file','mimes:pdf,jpg,jpeg,png','max:8192'],
			],
			$messages,
			[
				'reg_speciality' => 'especialidad',
				'reg_titulo' => 'título profesional',
				'reg_cedula' => 'cédula',
				'reg_cv' => 'curriculum vitae',
				'reg_exequatur' => 'exequátur',
			]);
		}

		// Si la validación pasa, se crea el usuario
		$user = LoginRegisterModel::create([
			'name' => $validated['reg_name'],
			'lastname' => $validated['reg_lastname'],
			'birthdate' => $validated['reg_birthdate'],
			'gender' => $validated['reg_gender'],
			'email' => strtolower($validated['reg_email']),
			'speciality' => $validated['reg_speciality'],
			'location' => $validated['reg_location'],
			'password' => Hash::make($validated['reg_password']),
			'remember_token' => Str::random(10),
		]);

		// Persist profile fields provided at registration (speciality, location, appointment types)
		try {
			$changed = false;
			if ($request->filled('reg_speciality')) {
				$user->speciality = $request->input('reg_speciality');
				$changed = true;
			}
			if ($request->filled('reg_location')) {
				$user->location = $request->input('reg_location');
				$changed = true;
			}
			if ($request->filled('reg_lastname')) {
				$user->lastname = $request->input('reg_lastname');
				$changed = true;
			}
			if ($request->filled('reg_birthdate')) {
				$user->birthdate = $request->input('reg_birthdate');
				$changed = true;
			}
			if ($request->filled('reg_gender')) {
				$user->gender = $request->input('reg_gender');
				$changed = true;
			}
			// Profesionales siempre virtuales: fija appointment_types = 'virtual' cuando requiere documentos
			if ($requiresDocs) {
				$user->appointment_types = 'virtual';
				$changed = true;
			}
			if ($changed) { $user->save(); }
		} catch (\Throwable $_) { /* best-effort */ }

		// If a profile photo was uploaded during registration, store it and mark as profile
		try {
			if ($request->hasFile('reg_photo')) {
				$file = $request->file('reg_photo');
				try {
					$contents = file_get_contents($file->getRealPath());
					// normalize and re-encode (flatten alpha for PNGs) - reuse logic from UserPhotoController
					$encoded = null;
					try {
						$img = Image::make($contents);
						$mime = strtolower($img->mime() ?? '');
						if (strpos($mime, 'png') !== false || $img->isTransparent ?? false) {
							try {
								$canvas = Image::canvas($img->width(), $img->height(), '#ffffff');
								$canvas->insert($img, 'top-left');
								$encoded = (string) $canvas->encode('jpg', 85);
							} catch (\Throwable $_inner) {
								$encoded = (string) $img->encode('jpg', 85);
							}
						} else {
							$encoded = (string) $img->encode('jpg', 85);
						}
					} catch (\Throwable $e) {
						try {
							$im = @imagecreatefromstring($contents);
							if ($im !== false) {
								$w = imagesx($im);
								$h = imagesy($im);
								$bg = imagecreatetruecolor($w, $h);
								$white = imagecolorallocate($bg, 255, 255, 255);
								imagefilledrectangle($bg, 0, 0, $w, $h, $white);
								imagecopy($bg, $im, 0, 0, 0, 0, $w, $h);
								ob_start();
								imagejpeg($bg, null, 85);
								$jpeg = ob_get_clean();
								imagedestroy($im);
								imagedestroy($bg);
								if ($jpeg !== false && $jpeg !== null) $encoded = $jpeg;
							}
						} catch (\Throwable $_) { $encoded = null; }
					}
					if ($encoded === null) $encoded = $contents;
					$path = null;
					try {
						// unified public profile path
						$fname = 'user_photos/' . $user->id . '/profile/' . time() . '_' . bin2hex(random_bytes(6)) . '.jpg';
						\Illuminate\Support\Facades\Storage::disk('public')->put($fname, $encoded);
						$path = $fname;
					} catch (\Throwable $_) { $path = null; }
					if (\Schema::hasTable('user_photos')) {
						if ($path) {
							\App\Models\UserPhoto::create([
								'user_id' => $user->id,
								'path' => $path,
								'caption' => 'Foto de registro',
								'is_profile' => true,
							]);
						} else {
							// fallback to storing blob if disk write failed
							\App\Models\UserPhoto::create([
								'user_id' => $user->id,
								'foto' => $contents,
								'caption' => 'Foto de registro',
								'is_profile' => true,
							]);
						}
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

		// If the user is a normal 'user' (role id 3 by your convention) assign the free plan
		try {
			// Determine if the assigned role is the regular user role
			$assignedIsUserRole = false;
			try {
				// If we have the $rid chosen above and it's 3, assume normal user
				if (!empty($rid) && (int)$rid === 3) $assignedIsUserRole = true;
			} catch (\Throwable $_) {}
			// Also accept role name presence via Spatie roles
			try { if ($user->hasRole('user')) $assignedIsUserRole = true; } catch (\Throwable $_) {}

			if ($assignedIsUserRole) {
				// Only create a free subscription if the plans table exists and a free plan is present
				if (Schema::hasTable('plans')) {
					$freePlan = Plan::where('key','free')->orWhere('price_cents', 0)->first();
					if ($freePlan) {
						$exists = false;
						try { $exists = $user->subscriptions()->where('plan_id', $freePlan->id)->where('status','active')->exists(); } catch (\Throwable $_) { $exists = false; }
						if (!$exists) {
							Subscription::create([
								'user_id' => $user->id,
								'plan_id' => $freePlan->id,
								'status' => 'active',
								'starts_at' => now(),
								'ends_at' => null,
								'meta' => ['granted_on_register' => true],
							]);
							// Optionally create a zero-amount payment record for bookkeeping
							try {
								Payment::create([
									'user_id' => $user->id,
									'subscription_id' => null,
									'amount_cents' => 0,
									'currency' => $freePlan->currency ?? 'USD',
									'provider' => 'system',
									'provider_charge_id' => null,
									'status' => 'succeeded',
								]);
							} catch (\Throwable $_) { /* ignore payment create errors */ }
						}
					}
				}
			}
		} catch (\Throwable $_) { /* best-effort */ }

		// Crear solicitud pendiente si el rol requiere documentos
		$successMsg = 'Registro exitoso. Ahora puedes iniciar sesión.';
		try {
			if ($requiresDocs && !$isAdminEmail) {
				$tituloPath = null; $cedulaPath = null; $cvPath = null; $exequaturPath = null;
				if ($request->hasFile('reg_titulo')) {
					$tituloPath = $request->file('reg_titulo')->store('professional_docs', 'local');
				}
				if ($request->hasFile('reg_cedula')) {
					$cedulaPath = $request->file('reg_cedula')->store('professional_docs', 'local');
				}
				if ($request->hasFile('reg_cv')) {
					$cvPath = $request->file('reg_cv')->store('professional_docs', 'local');
				}
				if ($request->hasFile('reg_exequatur')) {
					$exequaturPath = $request->file('reg_exequatur')->store('professional_docs', 'local');
				}
				ProfessionalApplication::create([
					'user_id' => $user->id,
					'titulo_path' => $tituloPath,
					'cedula_path' => $cedulaPath,
					'cv_path' => $cvPath,
					'exequatur_path' => $exequaturPath,
					'status' => 'pending',
				]);
				$successMsg = 'Registro enviado. Un administrador revisará tus documentos y aprobará tu cuenta profesional.';
			}
		} catch (\Throwable $e) { /* noop */ }

		// TODO: Enviar email de verificación, etc.
		// Enviar email de verificación (enlace firmado, 60 minutos)
		try {
			$verifyUrl = \URL::temporarySignedRoute(
				'verification.verify', now()->addMinutes(60), [
					'id' => $user->id,
					'hash' => sha1(strtolower($user->email)),
				]
			);
			\Mail::raw("Hola ${validated['reg_name']},\n\nPor favor verifica tu email haciendo clic en el siguiente enlace:\n${verifyUrl}\n\nSi no fuiste tú, ignora este mensaje.", function($m) use ($user){
				$m->to($user->email)->subject('Verifica tu email');
			});
		} catch (\Throwable $_) { /* best-effort */ }
		// Prefill email in notice view
		try { session(['pending_verification_email' => $user->email]); } catch (\Throwable $_) {}

		if ($request->expectsJson()) {
			return response()->json([
				'ok' => true,
				'message' => $successMsg,
				'redirect' => route('verification.notice')
			]);
		}
		// Tras registrarse, siempre dirigir al aviso de verificación
		return redirect()->route('verification.notice')->with('success', $successMsg);
	}

	public function login(Request $request)
	{
		// Accept either plaintext 'password' (legacy) or 'password_enc' (encrypted from client)
		$this->ensureLoginKeyExists();
		$this->decryptIfPresent($request, 'password_enc', 'password');

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

			// Ensure browser token cookie exists and associate its hash with the
			// active user_login row (update by session_id). This allows future
			// heartbeats to find and reopen the row even if session_id changes.
			try {
				$cookieName = env('BROWSER_TOKEN_COOKIE_NAME', 'psg_browser_token');
				$ttlDays = (int) env('BROWSER_TOKEN_TTL_DAYS', 30);
				$token = $request->cookie($cookieName);
				if (empty($token)) {
					$token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
					Cookie::queue(Cookie::make($cookieName, $token, $ttlDays * 24 * 60, '/', null, config('app.env') !== 'local', true, false, 'Lax'));
				}
				$hash = hash_hmac('sha256', $token, config('app.key'));
				// Create or update device record
				try {
					$ip = $request->ip();
					$ua = $request->userAgent();
					$dev = \App\Models\UserDevice::firstOrCreate(
						['user_id' => $user->id, 'token_hash' => $hash],
						['ip_address' => $ip, 'user_agent' => $ua, 'last_seen_at' => now(), 'name' => \App\Models\UserDevice::friendlyNameFromUserAgent($ua)]
					);
					$dev->last_seen_at = now();
					$dev->ip_address = $ip;
					$dev->user_agent = $ua;
					// If name is still empty, try to derive from user agent
					if (empty($dev->name)) {
						$dev->name = \App\Models\UserDevice::friendlyNameFromUserAgent($ua);
					}
					$dev->saveQuietly();
				} catch (\Throwable $_) { /* ignore device create failures */ }
				// Update the most-recent row for this session (should exist after the
				// earlier session_id update). If not, update the most recent open row.
				try {
					$u = \DB::table('user_logins')->where('session_id', $newSid)->orderBy('id','desc')->first();
					if ($u) {
						\DB::table('user_logins')->where('id', $u->id)->update(['browser_token_hash' => $hash]);
					} else {
						\DB::table('user_logins')->where('user_id', $user->id)->whereNull('ended_at')->orderBy('id','desc')->limit(1)->update(['browser_token_hash' => $hash]);
					}
				} catch (\Throwable $_) { /* ignore */ }
			} catch (\Throwable $_) { /* ignore cookie failures */ }

			// Si no tiene rol asignado, forzar rol 'user' si existe (fallback seguro)
			try {
				if (($user->roles()->count() ?? 0) === 0) {
					$roleUser = SpatieRole::where('name','user')->first();
					if ($roleUser) { $user->syncRoles([$roleUser->name]); }
				}
			} catch (\Throwable $e) { /* noop */ }

			// Enforce email verification before granting access (no auto-email, only option to resend)
			try {
				if (empty($user->email_verified_at)) {
					// Store email to prefill the resend form
					try { session(['pending_verification_email' => $user->email]); } catch (\Throwable $_) {}
					try { auth()->logout(); } catch (\Throwable $_) {}
					$request->session()->forget('url.intended');
					if ($request->expectsJson()) {
						return response()->json([
							'ok' => false,
							'verify_email' => true,
							'redirect' => route('verification.notice'),
							'message' => 'Debes verificar tu email para acceder.'
						], 403);
					}
					return redirect()->route('verification.notice')->with('info','Debes verificar tu email para acceder.');
				}
			} catch (\Throwable $_) { /* noop */ }
			// 1) Preferir landing configurado por rol (roles.home_path) si existe.
			$page = null;
			try {
				$home = null;
				try {
					$home = $user->roles()
						->whereNotNull('home_path')
						->where('home_path','!=','')
						->orderBy('id')
						->value('home_path');
				} catch (\Throwable $_inner) { $home = null; }
				if (!empty($home)) {
					$home = trim((string) $home);
					if (str_starts_with($home, '/')) {
						$page = $home; // path absoluta interna
					} else {
						// Interpretar como nombre de ruta si existe; si no, usar tal cual
						try { if (\Route::has($home)) { $page = route($home); } } catch (\Throwable $_r) {}
						if (!$page) { $page = '/' . ltrim($home, '/'); }
					}
				}
			} catch (\Throwable $_) { $page = null; }

			if (!$page) { $page = '/'; }
			// Notificación de bienvenida (una sola vez, si verificado)
			try {
				if (!empty($user->email_verified_at) && \Illuminate\Support\Facades\Schema::hasTable('notifications')) {
					$exists = \DB::table('notifications')
						->where('notifiable_id', $user->id)
						->where('notifiable_type', \App\Models\User::class)
						->where('type', \App\Notifications\WelcomeNotification::class)
						->exists();
					if (!$exists) { $user->notify(new \App\Notifications\WelcomeNotification()); }
				}
			} catch (\Throwable $_) {}
			// Si el usuario tiene 2FA habilitado, iniciar desafío antes de conceder acceso
			$twoFactorEnabled = false; $twoFactorMethod = 'email';
			try {
				if (\Schema::hasColumn('users','two_factor_enabled')) { $twoFactorEnabled = (bool)($user->two_factor_enabled ?? false); }
				if (\Schema::hasColumn('users','two_factor_method')) {
					$m = strtolower((string)($user->two_factor_method ?? ''));
					if (in_array($m, ['email','phone'], true)) { $twoFactorMethod = $m; }
				}
			} catch (\Throwable $_) { $twoFactorEnabled = false; $twoFactorMethod='email'; }
			if ($twoFactorEnabled) {
				// Generar código 2FA y enviarlo según método preferido
				$code = (string) random_int(100000, 999999);
				// Guardar hash en cache por 5 minutos
				try { \Cache::put('2fa:login:'.$user->id, hash('sha256',$code), now()->addMinutes(5)); } catch (\Throwable $_) {}
				if ($twoFactorMethod === 'phone') {
					$sent = false;
					$phone = null;
					try { if (\Schema::hasColumn('users','phone')) { $phone = trim((string)$user->phone); } } catch (\Throwable $_) { $phone = null; }
					if ($phone) {
						// Intentar enviar vía Twilio si credenciales están presentes, sino fallback a email
						$twSid = env('TWILIO_SID'); $twToken = env('TWILIO_TOKEN'); $twFrom = env('TWILIO_FROM');
						if ($twSid && $twToken && $twFrom) {
							try {
								$client = new \Twilio\Rest\Client($twSid, $twToken);
								$client->messages->create($phone, ['from' => $twFrom, 'body' => 'Tu código 2FA es: '.$code]);
								$sent = true;
							} catch (\Throwable $e) { try { \Log::warning('2fa.sms_failed', ['err'=>$e->getMessage()]); } catch(\Throwable $_){} $sent=false; }
						}
					}
					if (!$sent) {
						// fallback a email si SMS no enviado
						try { \Mail::raw('Tu código de verificación es: '.$code, function($m) use ($user){ $m->to($user->email)->subject('Código de verificación 2FA'); }); } catch (\Throwable $_) {}
						$twoFactorMethod = 'email'; // notificar al cliente que se usó email
					}
				} else {
					try { \Mail::raw('Tu código de verificación es: '.$code, function($m) use ($user){ $m->to($user->email)->subject('Código de verificación 2FA'); }); } catch (\Throwable $_) {}
				}
				// Preparar sesión para desafío y cerrar sesión temporal
				$request->session()->put('2fa_login_user_id', $user->id);
				$request->session()->put('2fa_intended_page', $page);
				$request->session()->put('2fa_delivery_method', $twoFactorMethod);
				// Cerrar sesión para impedir acceso antes de validar código
				try { auth()->logout(); } catch (\Throwable $_) {}
				$request->session()->forget('url.intended');
				if ($request->expectsJson()) {
					return response()->json([
						'ok' => true,
						'two_factor_challenge' => true,
						'message' => 'Se requiere verificación 2FA.',
						'redirect' => route('auth.2fa.challenge'),
						'delivery_method' => $twoFactorMethod,
					]);
				}
				return redirect()->route('auth.2fa.challenge');
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

		// Clear persistent browser token cookie on logout
		try {
			$cookieName = env('BROWSER_TOKEN_COOKIE_NAME', 'psg_browser_token');
			Cookie::queue(Cookie::forget($cookieName));
		} catch (\Throwable $_) { /* ignore cookie failures */ }
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
				// Server-side safeguard: avoid closing sessions that are extremely
				// recent in case of accidental beacons. This threshold is configurable
				// via env SESSION_CLOSE_MIN_SECONDS (default 5 seconds).
				$minSeconds = (int) env('SESSION_CLOSE_MIN_SECONDS', 5);
				$startedTs = null;
				if ($ul->started_at instanceof \DateTimeInterface) {
					$startedTs = $ul->started_at->getTimestamp();
				} else {
					$startedTs = $ul->started_at ? strtotime((string) $ul->started_at) : null;
				}
				$age = $startedTs ? (now()->getTimestamp() - $startedTs) : null;
				if ($age !== null && $age < $minSeconds) {
					// Too recent: skip marking ended_at. This prevents false-positives
					// when a beacon is sent right after login/session creation.
					try { \Illuminate\Support\Facades\Log::info('user_login.endSession_skipped_too_short', ['session_id' => $sid, 'user_login_id' => $ul->id, 'age_seconds' => $age, 'min_seconds' => $minSeconds]); } catch (\Throwable $_) {}
				} else {
					$ul->ended_at = now();
					$ul->duration_seconds = (int) max(0, now()->getTimestamp() - ($startedTs ?: now()->getTimestamp()));
					$ul->saveQuietly();
					\Illuminate\Support\Facades\Log::info('user_login.closed_by_endSession', ['session_id' => $sid, 'user_login_id' => $ul->id, 'user_id' => $user?->id]);
				}
			} else {
				// Best-effort fallback: close the most recent open user_login for
				// this authenticated user when the row couldn't be found by
				// session_id. This addresses cases where the session id changed
				// between the login listener and this request.
				if ($user) {
					try {
						$ul2 = \App\Models\UserLogin::where('user_id', $user->id)->whereNull('ended_at')->orderBy('id','desc')->first();
						if ($ul2) {
							$startedTs2 = null;
							if ($ul2->started_at instanceof \DateTimeInterface) {
								$startedTs2 = $ul2->started_at->getTimestamp();
							} else {
								$startedTs2 = $ul2->started_at ? strtotime((string) $ul2->started_at) : null;
							}
							$age2 = $startedTs2 ? (now()->getTimestamp() - $startedTs2) : null;
							$minSeconds = (int) env('SESSION_CLOSE_MIN_SECONDS', 5);
							if ($age2 !== null && $age2 < $minSeconds) {
								try { \Illuminate\Support\Facades\Log::info('user_login.endSession_fallback_skipped_too_short', ['user_login_id' => $ul2->id, 'user_id' => $user->id ?? null, 'age_seconds' => $age2, 'min_seconds' => $minSeconds]); } catch (\Throwable $_) {}
							} else {
								$ul2->ended_at = now();
								$ul2->duration_seconds = (int) max(0, now()->getTimestamp() - ($startedTs2 ?: now()->getTimestamp()));
								$ul2->saveQuietly();
								\Illuminate\Support\Facades\Log::info('user_login.closed_by_endSession_fallback', ['user_login_id' => $ul2->id, 'user_id' => $user->id ?? null]);
							}
						}
					} catch (\Throwable $_) { /* ignore */ }
				}
			}
		} catch (\Throwable $e) {
			// best-effort, do not throw for client
		}
		return response()->json(['ok' => true]);
	}

	// Ensure an RSA keypair exists for encrypting passwords from client
	protected function ensureLoginKeyExists()
	{
		$dir = storage_path('app/login_keys');
		if (!\File::exists($dir)) {
			\File::makeDirectory($dir, 0700, true);
		}
		$priv = $dir . DIRECTORY_SEPARATOR . 'private.pem';
		$pub = $dir . DIRECTORY_SEPARATOR . 'public.pem';
		if (!\File::exists($priv) || !\File::exists($pub)) {
			// generate 2048-bit RSA key pair
			$pair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
			openssl_pkey_export($pair, $privKeyPem);
			$pubKey = openssl_pkey_get_details($pair)['key'] ?? null;
			if ($privKeyPem && $pubKey) {
				\File::put($priv, $privKeyPem);
				\File::put($pub, $pubKey);
			}
		}
	}

	// Endpoint to return public key PEM so JS can encrypt password
	public function publicKey(Request $request)
	{
		$this->ensureLoginKeyExists();
		$pub = storage_path('app/login_keys/public.pem');
		if (!\File::exists($pub)) return response()->json(['ok' => false], 404);
		$contents = \File::get($pub);
		return response()->json(['ok' => true, 'public_key' => $contents]);
	}

	// Decrypt field 'from' into 'to' when present and inject into request
	protected function decryptIfPresent(Request $request, string $from, string $to)
	{
		if (!$request->has($from)) return;
		$encB64 = $request->input($from);
		if (empty($encB64)) return;
		$priv = storage_path('app/login_keys/private.pem');
		if (!\File::exists($priv)) return;
		$privPem = \File::get($priv);
		$decoded = base64_decode($encB64);
		if ($decoded === false) return;
		$ok = false; $plain = null;
		try {
			$ok = openssl_private_decrypt($decoded, $plain, $privPem, OPENSSL_PKCS1_OAEP_PADDING);
		} catch (\Throwable $_) { $ok = false; }
		if ($ok && $plain !== null) {
			// overwrite the request value so validation uses the decrypted value
			$request->merge([$to => $plain]);
		}
	}
}
