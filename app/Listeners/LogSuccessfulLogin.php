<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Models\UserLogin;

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
            UserLogin::create([
                'user_id' => $user->id,
                'session_id' => $sid,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'started_at' => now(),
            ]);
            // mark user presence as online (do not change is_active which means account enabled)
            try { $user->status = 'online'; $user->save(); } catch (\Throwable$e) {}
        } catch (\Throwable $e) {
            // noop
        }
    }
}
