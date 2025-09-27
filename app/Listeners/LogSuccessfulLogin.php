<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Models\UserLogin;
use Illuminate\Support\Facades\Log;

class LogSuccessfulLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        try {
            $user = $event->user;
            $request = request();
            $sid = $request->session()->getId();
            try {
                // Prevent duplicate rows for the same user and session when
                // the Login event fires more than once (AJAX double-post,
                // middleware replay, etc.). If an open (not ended) row for
                // this session already exists, skip creating a new one.
                if ($sid && UserLogin::where('user_id', $user->id)->where('session_id', $sid)->whereNull('ended_at')->exists()) {
                    Log::info('user_login.duplicate_skipped', ['user_id' => $user->id, 'session_id' => $sid]);
                } else {
                    $ul = UserLogin::create([
                        'user_id' => $user->id,
                        'session_id' => $sid,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'started_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('user_login.create_failed', ['user_id' => $user->id, 'session_id' => $sid, 'error' => (string) $e]);
            }
            // mark user presence as online (do not change is_active which means account enabled)
            try { $user->status = 'online'; $user->save(); } catch (\Throwable$e) {}
        } catch (\Throwable $e) {
            Log::error('login.listener.failed', ['error' => (string) $e]);
        }
    }
}
