<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Content Security Policy - adapt sources as needed
        // Stricter CSP: avoid 'unsafe-eval' and reduce script/style allowances. Add connect-src for XHR/fetch, frame-ancestors to prevent framing,
        // base-uri and form-action to limit where forms and base tags can point.
        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; connect-src 'self' https: wss:; img-src 'self' data:; font-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self';";

        // During local development, allow Vite dev server (http://localhost:5173 and http://[::1]:5173) and HMR (ws://)
        if (app()->environment('local')) {
            // Allow general http/ws schemes during development so IPv6 addresses like [::1] are covered.
            // This is intentionally permissive but only active in local environment.
            $csp = "default-src 'self'; "
                . "script-src 'self' 'unsafe-inline' 'unsafe-eval' http: https: ws: wss:; "
                . "script-src-elem 'self' 'unsafe-inline' 'unsafe-eval' http: https: ws: wss:; "
                . "style-src 'self' 'unsafe-inline' http: https:; "
                . "style-src-elem 'self' 'unsafe-inline' http: https:; "
                . "connect-src 'self' http: https: ws: wss:; "
                . "img-src 'self' data: http: https:; "
                . "font-src 'self' data: http: https:; frame-ancestors 'none'; base-uri 'self'; form-action 'self';";
        }

        $response->headers->set('Content-Security-Policy', $csp);

        // Other useful security headers
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Allow camera/microphone for appointment and chat pages (deny elsewhere)
        try {
            $path = $request->path();
            $allowMedia = false;
            // Base allow list (professional area & RTC related pages)
            if (preg_match('#^appointments#', $path)) { $allowMedia = true; }
            if (preg_match('#^(chat|messages)#', $path)) { $allowMedia = true; }
            if (preg_match('#^rtc/#', $path)) { $allowMedia = true; }
            if (preg_match('#^professional#', $path)) { $allowMedia = true; }
            // Extend for patient/user dashboard style routes (common naming variants)
            if (preg_match('#^(patient|paciente|user|panel|dashboard)#', $path)) { $allowMedia = true; }
            // If authenticated user, allow unless explicitly public auth routes
            if (!$allowMedia && $request->user()) {
                if (!preg_match('#^(login|register|password|public|assets|js|css)#', $path)) {
                    $allowMedia = true;
                }
            }
            // When allowed, grant self-origin access; keep geolocation disabled.
            $policy = $allowMedia
                ? 'geolocation=(), microphone=(self), camera=(self)'
                : 'geolocation=(), microphone=(), camera=()';
            $response->headers->set('Permissions-Policy', $policy);
        } catch (\Throwable $e) {
            // Fail closed except geolocation still denied
            $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        }

        // HSTS when running in production (add Strict-Transport-Security)
        if (!app()->environment('local')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        }

        return $response;
    }
}
