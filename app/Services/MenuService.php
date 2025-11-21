<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MenuService
{
    /**
     * Build and cache the menu for a given user and active area.
     * Returns a grouped collection by section: admin/professional/user/common.
     */
    public function getFor(?User $user, ?string $activeArea = null): Collection
    {
        if (!$user) {
            return collect();
        }

        // If menu tables aren't present, short-circuit
        try {
            if (!Schema::hasTable('menu_items')) {
                return collect();
            }
        } catch (\Throwable $__) {
            return collect();
        }

        // Role IDs influence visibility; include in cache key
        $roleIds = [];
        try {
            $roleIds = $user->roles()->pluck('id')->map(fn($i) => (int)$i)->toArray();
        } catch (\Throwable $__) {
            $roleIds = [];
        }

    $rolesHash = md5(implode(',', $roleIds));
    $version = (int) (Cache::get('menu:version') ?: 1);
    $scope = $activeArea ? $activeArea : 'all';
    $cacheKey = sprintf('menu:v%d:user:%d:%s:%s', $version, (int)$user->id, $scope, $rolesHash);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($activeArea, $roleIds, $user) {
            $q = MenuItem::query()
                ->where('enabled', true)
                ->when(!empty($activeArea), function($qq) use ($activeArea){
                    $qq->whereIn('section', [$activeArea, 'common']);
                })
                ->where(function ($qq) use ($roleIds) {
                    $qq->whereDoesntHave('roles');
                    if (!empty($roleIds)) {
                        $qq->orWhereHas('roles', function ($r) use ($roleIds) {
                            $r->whereIn('roles.id', $roleIds);
                        });
                    }
                })
                ->orderBy('section')
                ->orderBy('sort_order');

            $items = $q->get();

            // Filter by permission when applicable
            $items = $items->filter(function ($it) use ($user) {
                $perm = trim((string)($it->permission ?? ''));
                if ($perm === '' || !$user) return true;
                try {
                    return $user->can($perm);
                } catch (\Throwable $__) {
                    return false;
                }
            });

            return $items->groupBy('section');
        });
    }

    public static function bump(): void
    {
        try {
            if (!Cache::has('menu:version')) {
                Cache::forever('menu:version', 1);
            }
            Cache::increment('menu:version');
        } catch (\Throwable $__) { /* ignore cache failures */ }
    }

    /**
     * Compute per-route/menu badges (e.g. chat unread, professional applications pending)
     * Centralized to keep Blade lean. Returns array keyed by route_name.
     */
    public function computeBadges(?User $user): array
    {
        if (!$user) return [];
        $out = [];
        try {
            // Professional applications pending (admin panel)
            if (Schema::hasTable('professional_applications')) {
                $pending = \DB::table('professional_applications')->where('status','pending')->count();
                if ($pending > 0) { $out['admin.profapps.index'] = $pending; }
            }
        } catch (\Throwable $__) {}
        try {
            // Chat unread + pending friend requests
            $unread = 0; $friendPending = 0;
            if (Schema::hasTable('messages')) {
                $unread = \DB::table('messages')->where('to_id', $user->id)->whereNull('read_at')->count();
            }
            if (Schema::hasTable('friend_requests')) {
                $friendPending = \DB::table('friend_requests')->where('to_id', $user->id)->where('status','pending')->count();
            }
            $chatTotal = $unread + $friendPending;
            if ($chatTotal > 0) { $out['chat.index'] = $chatTotal; }
        } catch (\Throwable $__) {}
        return $out;
    }
}
