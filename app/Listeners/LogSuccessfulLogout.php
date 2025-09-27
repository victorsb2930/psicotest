<?php

namespace App\Listeners;

use App\Models\UserLogin;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

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
			$closed = false;
			if ($sid) {
				try {
					$ul = UserLogin::where('session_id', $sid)->orderBy('id', 'desc')->first();
					if ($ul && ! $ul->ended_at) {
						try {
							$ul->ended_at = now();
							// ensure we store an integer >= 0 (DB column is unsigned integer)
							// started_at may be a Carbon instance or a string (DB/raw), handle both safely
							$startedTs = null;
							if ($ul->started_at instanceof \DateTimeInterface) {
								$startedTs = $ul->started_at->getTimestamp();
							} else {
								$startedTs = $ul->started_at ? strtotime((string) $ul->started_at) : null;
							}
							$startedTs = $startedTs ?: now()->getTimestamp();
							$ul->duration_seconds = (int) max(0, now()->getTimestamp() - $startedTs);
							$ul->save();
							$closed = true;
						} catch (\Throwable $e) {
							Log::error('logout.listener.save_failed', ['error' => (string) $e, 'user_login_id' => $ul->id ?? null]);
						}
					}
				} catch (\Throwable $e) {
					Log::error('logout.listener.query_failed', ['error' => (string) $e]);
				}
			}
			// Fallback: if we couldn't find by session_id, close the most
			// recent open login row for this user (best-effort). This helps
			// in environments where session ids were regenerated before the
			// listener could run or where the listener saw a different id.
			if (! $closed && $user) {
				try {
					$ul2 = UserLogin::where('user_id', $user->id)->whereNull('ended_at')->orderBy('id', 'desc')->first();
					if ($ul2) {
						try {
							$ul2->ended_at = now();
							// ensure we store an integer >= 0 (DB column is unsigned integer)
							$startedTs2 = null;
							if ($ul2->started_at instanceof \DateTimeInterface) {
								$startedTs2 = $ul2->started_at->getTimestamp();
							} else {
								$startedTs2 = $ul2->started_at ? strtotime((string) $ul2->started_at) : null;
							}
							$startedTs2 = $startedTs2 ?: now()->getTimestamp();
							$ul2->duration_seconds = (int) max(0, now()->getTimestamp() - $startedTs2);
							$ul2->save();
						} catch (\Throwable $e) {
							Log::error('logout.listener.fallback_save_failed', ['error' => (string) $e, 'user_login_id' => $ul2->id ?? null]);
						}
					}
				} catch (\Throwable $e) {
					Log::error('logout.listener.query_failed', ['error' => (string) $e]);
				}
			}
			// mark user presence as offline (do not change is_active)
			try {
				$user->status = 'offline';
				$user->save();
			} catch (\Throwable $e) {
				Log::error('logout.listener.status_save_failed', ['error' => (string) $e, 'user_id' => $user->id ?? null]);
			}
		} catch (\Throwable $e) {
			Log::error('logout.listener.failed', ['error' => (string) $e]);
		}
	}
}
