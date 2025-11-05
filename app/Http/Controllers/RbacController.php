<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\Models\Permission as SpatiePermission;

class RbacController extends Controller {
	// Roles
	public function rolesIndex() {
		$roles = SpatieRole::orderBy('name')->get();
		$permissions = SpatiePermission::orderBy('name')->get();
		$assigned = DB::table('role_has_permissions')->select('role_id','permission_id')->get()
			->groupBy('role_id')->map(fn($c) => $c->pluck('permission_id')->all());
		return view('admin.roles', compact('roles', 'permissions','assigned'));
	}

	public function rolesStore(Request $request) {
		$data = $request->validate([
			'name' => 'required|string|max:100|alpha_dash|unique:roles,name',
			'show_in_signup' => 'nullable|boolean',
			'signup_label' => 'nullable|string|max:100',
			'requires_docs' => 'nullable|boolean',
			'icon_class' => 'nullable|string|max:100',
			'badge_color' => 'nullable|string|max:50',
			'home_path' => 'nullable|string|max:255',
		]);
		$slug = strtolower($data['name']);
		$show = (bool) ($data['show_in_signup'] ?? false);
		$label = $data['signup_label'] ?? null;
		$reqDocs = (bool) ($data['requires_docs'] ?? false);
		$sp = new SpatieRole(['name' => $slug, 'guard_name' => 'web']);
		$sp->show_in_signup = $show;
		$sp->signup_label = $label;
		$sp->requires_docs = $reqDocs;
		$sp->icon_class = $data['icon_class'] ?? null;
		$sp->badge_color = $data['badge_color'] ?? null;
		$sp->home_path = $data['home_path'] ?? null;
		$sp->save();
		return back()->with('success', 'Rol creado.');
	}

	public function rolesUpdate(Request $request, Role $role) {
		$data = $request->validate([
			'name' => 'required|string|max:100|alpha_dash|unique:roles,name,' . $role->id,
			'show_in_signup' => 'nullable|boolean',
			'signup_label' => 'nullable|string|max:100',
			'requires_docs' => 'nullable|boolean',
			'icon_class' => 'nullable|string|max:100',
			'badge_color' => 'nullable|string|max:50',
			'home_path' => 'nullable|string|max:255',
		]);
		$slug = strtolower($data['name']);
		$sp = SpatieRole::find($role->id);
		if ($sp) {
			$sp->name = $slug; // align name=slug for access control
			$sp->guard_name = 'web';
			$sp->show_in_signup = (bool) ($data['show_in_signup'] ?? false);
			$sp->signup_label = $data['signup_label'] ?? null;
			$sp->requires_docs = (bool) ($data['requires_docs'] ?? false);
			$sp->icon_class = $data['icon_class'] ?? null;
			$sp->badge_color = $data['badge_color'] ?? null;
			$sp->home_path = $data['home_path'] ?? null;
			$sp->save();
		}
		return back()->with('success', 'Rol actualizado.');
	}

	public function rolesDestroy(Request $request, Role $role) {
		// Prevent deleting core roles
		if (in_array($role->name, ['admin', 'professional', 'user'])) {
			return back()->with('error', 'No se puede borrar rol base.');
		}
		$role->delete();
		return back()->with('success', 'Rol eliminado.');
	}

	public function rolesSyncPermissions(Request $request, Role $role) {
		$ids = array_map('intval', (array) $request->input('permissions', []));
		$perms = SpatiePermission::whereIn('id', $ids)->get();
		$spRole = SpatieRole::find($role->id);
		if ($spRole) {
			$spRole->syncPermissions($perms);
		}
		return redirect()->route('admin.roles.index')->with('success', 'Permisos actualizados.');
	}

	// Permissions
	public function permsIndex() {
		$permissions = SpatiePermission::orderBy('name')->get();
		return view('admin.permissions', compact('permissions'));
	}

	public function permsStore(Request $request) {
		$data = $request->validate([
			'name' => 'required|string|max:100|alpha_dash|unique:permissions,name',
		]);
		$slug = strtolower($data['name']);
		$sp = SpatiePermission::firstOrNew(['name' => $slug]);
		$sp->name = $slug; // align name=slug for access control
		$sp->guard_name = 'web';
		$sp->save();
		return back()->with('success', 'Permiso creado.');
	}

	public function permsUpdate(Request $request, Permission $permission) {
		$data = $request->validate([
			'name' => 'required|string|max:100|alpha_dash|unique:permissions,name,' . $permission->id,
		]);
		$slug = strtolower($data['name']);
		$sp = SpatiePermission::find($permission->id);
		if ($sp) {
			$sp->name = $slug; // align name=slug
			$sp->guard_name = 'web';
			$sp->save();
		}
		return back()->with('success', 'Permiso actualizado.');
	}

	public function permsDestroy(Request $request, Permission $permission) {
		$sp = SpatiePermission::find($permission->id);
		if ($sp) { $sp->delete(); }
		// Record is the same row; local model will reflect deletion on next query
		return back()->with('success', 'Permiso eliminado.');
	}
}