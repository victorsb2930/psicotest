<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use App\Models\UserLogin;

class LogSuccessfulLogout
{
    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        try {
            $user = $event->user;
            $request = request();
            $sid = $request->session()->getId();
            if ($sid) {
                $ul = UserLogin::where('session_id', $sid)->orderBy('id','desc')->first();
                if ($ul && !$ul->ended_at) {
                    $ul->ended_at = now();
                    $ul->duration_seconds = $ul->started_at ? now()->diffInSeconds($ul->started_at) : null;
                    $ul->save();
                }
            }
            // mark user presence as offline (do not change is_active)
            try { $user->status = 'offline'; $user->save(); } catch (\Throwable$e) {}
        } catch (\Throwable $e) {
            // noop
        }
    }
}
