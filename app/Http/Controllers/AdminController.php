<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;

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
}