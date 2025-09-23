<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CheckPermission {
	public function handle(Request $request, Closure $next, string $permissionSlug = null) {
		$user = $request->user();
		if (!$user) {
			return redirect()->guest('/welcome');
		}

		$slug = $permissionSlug ?: optional($request->route())->getName();
		if (!$slug) {
			return $next($request);
		}

		if ($user->can($slug) || $user->hasPermissionTo($slug)) {
			return $next($request);
		}

		if ($request->expectsJson()) {
			return response()->json(['ok' => false, 'message' => 'No tienes permiso para acceder a esta página.'], 403);
		}
		return redirect('/welcome')->with('error', 'No tienes permiso para acceder a esta página.');
	}
}
