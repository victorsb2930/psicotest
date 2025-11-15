<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminController extends Controller {
	public function users(Request $request) {
	$q = trim((string)$request->get('q', ''));
	$roleId = $request->integer('role');
	$status = $request->get('status'); // 'active' | 'inactive' | null
	$size = (int) ($request->get('size', 20));
	$allowedSizes = [10,20,50];
	if (!in_array($size, $allowedSizes, true)) { $size = 20; }
	$sort = (string) $request->get('sort', 'id');
		$allowedSorts = ['id','name','email','active','roles','created_at','updated_at'];
	if (!in_array($sort, $allowedSorts, true)) { $sort = 'id'; }
	$dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

	$query = User::query()->with(['roles'])->withCount('roles');
		if ($q !== '') {
			$query->where(function($w) use ($q) {
				$w->where('name','like', "%$q%")
				->orWhere('email','like', "%$q%")
				->when(ctype_digit($q), fn($ww) => $ww->orWhere('id', (int) $q));
			});
		}
		if ($roleId) {
			$query->whereHas('roles', fn($r) => $r->where('roles.id', $roleId));
		}
		if ($status === 'active') {
			$query->where('is_active', true);
		} elseif ($status === 'inactive') {
			$query->where('is_active', false);
		}

		$sortCol = match($sort) {
			'active' => 'is_active',
			'roles' => 'roles_count',
			'created_at' => 'created_at',
			'updated_at' => 'updated_at',
			default => $sort,
		};
		$users = $query->orderBy($sortCol, $dir)->paginate($size)->withQueryString();
	$roles = Role::orderBy('name')->get();
		return view('admin.users', compact('users','roles','q','roleId','status','size','sort','dir'));
	}

	public function storeUser(Request $request) {
		$rules = [
			'name' => ['required','string','max:255'],
			'lastname' => ['required','string','max:255'],
			'email' => ['required','email','max:255','unique:users,email'],
			'location' => ['required','string','max:255'],
			//'password' => ['required','string','min:6'],
			'birthdate' => ['required','date'],
			'gender' => ['required','string','max:40'],
			'role_id' => ['nullable','integer','exists:roles,id'],
		];
		$messages = [
			'email.unique' => 'El email ya está en uso.',
			//'password.min' => 'La contraseña debe tener al menos 6 caracteres.'
		];
		$validated = $request->validate($rules, $messages);
		// normalizar email a minúsculas
		$validated['email'] = strtolower($validated['email']);
		$user = new User();
		$user->name = $validated['name'];
		$user->lastname = $validated['lastname'];
		$user->email = $validated['email'];
		$user->location = $validated['location'];
		// If no password provided, generate a secure random one and email it to the user
		$plainPassword = $validated['password'] ?? Str::random(12);
		$user->password = \Illuminate\Support\Facades\Hash::make($plainPassword);
		$user->birthdate = $validated['birthdate'];
		$user->gender = $validated['gender'];
		$user->is_active = true; // crear activo por defecto
		$user->save();
		// asignar rol si se proporciona
		if (!empty($validated['role_id'])) {
			try {
				$role = Role::find($validated['role_id']);
				if ($role) { $user->syncRoles([$role->name]); }
			} catch (\Throwable $_) { /* ignore */ }
		}
		// send notification email with temporary password
		$mailOk = true; $mailErr = null;
		try {
			Mail::to($user->email)->send(new \App\Mail\AdminCreatedUser($user, $plainPassword));
		} catch (\Throwable $e) {
			$mailOk = false; $mailErr = $e->getMessage();
			Log::warning('admin.create_user.mail_failed', ['email' => $user->email, 'error' => $mailErr]);
		}
		if ($mailOk) {
			return back()->with('success', 'Usuario agregado correctamente. Se envió el correo con la contraseña temporal.');
		}
		return back()->with('success', 'Usuario agregado correctamente. No se pudo enviar el correo de notificación.');
	}

	public function toggleActive(Request $request, User $user) {
		$user->is_active = !$user->is_active;
		$user->save();
		if ($request->expectsJson()) return response()->json(['ok'=>true,'is_active'=>$user->is_active]);
		return back()->with('success', 'Estado actualizado.');
	}

	public function assignRoles(Request $request, User $user) {
		$roleId = (int) ($request->input('roles')[0] ?? 0);
		if ($roleId <= 0) {
			$user->syncRoles([]);
			return back()->with('success', 'Roles vaciados.');
		}
		$role = Role::find($roleId);
		if (!$role) {
			return back()->with('error', 'Rol inválido.');
		}
		$user->syncRoles([$role->name]);
		if ($request->expectsJson()) return response()->json(['ok'=>true]);
		return back()->with('success', 'Roles actualizados.');
	}

	public function destroy(Request $request, User $user) {
		// Prevent self-delete
		if (auth()->id() === $user->id) {
			return back()->with('error', 'No puedes eliminar tu propia cuenta desde aquí.');
		}
		// Soft delete
		$user->delete();
		if ($request->expectsJson()) return response()->json(['ok'=>true]);
		return back()->with('success', 'Usuario eliminado (soft delete).');
	}

	public function toggleBan(Request $request, User $user) {
		$reason = trim((string) $request->input('reason', ''));
		// Prefer explicit is_banned column if present
		if (\Schema::hasColumn('users','is_banned')) {
			$user->is_banned = !$user->is_banned;
			// clear deactivation metadata if reactivated
			if (!$user->is_banned) {
				if (\Schema::hasColumn('users','deactivated_reason')) $user->deactivated_reason = null;
				if (\Schema::hasColumn('users','deactivated_at')) $user->deactivated_at = null;
			}
			$user->save();
			$msg = $user->is_banned ? 'Usuario baneado.' : 'Usuario desbaneado.';
			if ($request->expectsJson()) return response()->json(['ok'=>true,'is_banned'=>$user->is_banned]);
			return back()->with('success', $msg);
		}
		// fallback: use is_active + deactivated_reason/deactivated_at
		$user->is_active = !$user->is_active;
		if (!$user->is_active) {
			if (\Schema::hasColumn('users','deactivated_reason')) $user->deactivated_reason = $reason ?: null;
			if (\Schema::hasColumn('users','deactivated_at')) $user->deactivated_at = now();
		} else {
			if (\Schema::hasColumn('users','deactivated_reason')) $user->deactivated_reason = null;
			if (\Schema::hasColumn('users','deactivated_at')) $user->deactivated_at = null;
		}
		$user->save();
		if ($request->expectsJson()) return response()->json(['ok'=>true,'is_active'=>$user->is_active]);
		return back()->with('success', 'Estado actualizado.');
	}

	/**
	 * Return session history for a user (JSON) used by admin UI.
	 */
	public function sessions(Request $request, User $user) {
		// Prefer explicit user_logins table which stores start/end/duration
		if (\Schema::hasTable('user_logins')) {
			$rows = \DB::table('user_logins')->where('user_id', $user->id)->orderBy('started_at', 'desc')->get();
			$res = $rows->map(function($r){
				$startedAt = $r->started_at ? strtotime($r->started_at) : null;
				$endedAt = $r->ended_at ? strtotime($r->ended_at) : null;
				// Prefer stored duration_seconds; if missing, compute using ended_at or now
				$duration = null;
				if (!is_null($r->duration_seconds)) {
					$duration = $r->duration_seconds;
				} elseif ($startedAt) {
					$duration = ($endedAt ? $endedAt : time()) - $startedAt;
					// avoid negative values
					if ($duration < 0) $duration = 0;
				}
				return [
					'id' => $r->id,
					'ip' => $r->ip_address,
					'user_agent' => $r->user_agent,
					'started_at' => $r->started_at ? date('Y-m-d H:i:s', $startedAt) : null,
					'ended_at' => $r->ended_at ? date('Y-m-d H:i:s', $endedAt) : null,
					'duration_seconds' => $duration,
				];
			});
			return response()->json(['ok' => true, 'sessions' => $res]);
		}
		// Fallback: use sessions table if present
		if (!\Schema::hasTable('sessions')) {
			return response()->json(['ok' => false, 'message' => 'No session tracking available'], 404);
		}
		$rows = \DB::table('sessions')->where('user_id', $user->id)->orderBy('last_activity', 'desc')->get();
		$result = $rows->map(function($r){
			$last = is_numeric($r->last_activity) ? (int)$r->last_activity : null;
			return [
				'id' => $r->id,
				'ip' => $r->ip_address,
				'user_agent' => $r->user_agent,
				'last_activity' => $last ? date('Y-m-d H:i:s', $last) : null,
				'raw_payload' => $r->payload,
			];
		});
		return response()->json(['ok' => true, 'sessions' => $result]);
	}
}