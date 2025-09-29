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
        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; connect-src 'self'; img-src 'self' data:; font-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self';";

        // During local development, allow Vite dev server (http://localhost:5173 and http://[::1]:5173) and HMR (ws://)
        if (app()->environment('local')) {
            // Allow general http/ws schemes during development so IPv6 addresses like [::1] are covered.
            // This is intentionally permissive but only active in local environment.
            $csp = "default-src 'self'; "
                . "script-src 'self' 'unsafe-inline' 'unsafe-eval' http: ws:; "
                . "script-src-elem 'self' 'unsafe-inline' 'unsafe-eval' http: ws:; "
                . "style-src 'self' 'unsafe-inline' http:; "
                . "style-src-elem 'self' 'unsafe-inline' http:; "
                . "connect-src 'self' http: ws:; "
                . "img-src 'self' data: http:; "
                . "font-src 'self' data: http:; frame-ancestors 'none'; base-uri 'self'; form-action 'self';";
        }

        $response->headers->set('Content-Security-Policy', $csp);

        // Other useful security headers
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', "geolocation=(), microphone=(), camera=()");

        // HSTS when running in production (add Strict-Transport-Security)
        if (!app()->environment('local')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        }

        return $response;
    }
}
