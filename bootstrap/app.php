<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
	->withRouting(
		web: __DIR__ . '/../routes/web.php',
		commands: __DIR__ . '/../routes/console.php',
		channels: __DIR__ . '/../routes/channels.php',
		health: '/up',
	)
	->withMiddleware(function (Middleware $middleware): void {
		// Trust proxy headers (Render) so URLs/assets use https
		$middleware->trustProxies(at: '*');
		// Route middleware aliases
		$middleware->alias([
			'perm' => \App\Http\Middleware\CheckPermission::class,
			'security.headers' => \App\Http\Middleware\SecurityHeaders::class,
		]);
	})
	->withExceptions(function (Exceptions $exceptions): void {
		$exceptions->render(function (AuthenticationException $e, $request) {
			if ($request->expectsJson()) {
				return response()->json(['message' => 'Unauthenticated.'], 401);
			}
			return redirect()->guest('/');
		});
	})->create();
