<div class="card h-100 border-0 shadow-sm">
    <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between mb-3">
            @php
                $user = auth()->user();
                // Cargar todos los ítems permitidos (sin áreas predefinidas)
                $menuService = app(\App\Services\MenuService::class);
                $dbMenu = $menuService->getFor($user, null); // todas las secciones
                // Resolver home: preferir roles.home_path si existe, de lo contrario primer ítem permitido
                $resolvedHome = '/';
                try {
                    if ($user) {
                        $home = $user->roles()
                            ->whereNotNull('home_path')
                            ->where('home_path','!=','')
                            ->orderBy('id')
                            ->value('home_path');
                        if (!empty($home)) {
                            $home = trim((string) $home);
                            if (\Illuminate\Support\Str::startsWith($home, '/')) {
                                $resolvedHome = $home;
                            } else {
                                try { if (\Route::has($home)) { $resolvedHome = route($home); } } catch (\Throwable $__) {}
                                if ($resolvedHome === '/') { $resolvedHome = '/'.ltrim($home,'/'); }
                            }
                        }
                    }
                } catch (\Throwable $__) { /* ignore */ }
                if ($resolvedHome === '/') {
                    try {
                        $first = $dbMenu->flatten(1)->sortBy([['section','asc'],['sort_order','asc']])->first();
                        if ($first) { $resolvedHome = method_exists($first, 'resolvedUrl') ? $first->resolvedUrl() : '#'; }
                    } catch (\Throwable $__) { /* ignore */ }
                }
                $homeUrl = $resolvedHome ?: '/';
            @endphp

            <a class="d-flex align-items-center text-white text-decoration-none" href="{{ $homeUrl }}">
                <div class="me-2"><i class="bi bi-grid-3x3-gap fs-4"></i></div>
                <div class="fw-semibold text-white">Menú</div>
            </a>
        </div>

        @php
            // Precompute badge map once via service for DRY
            $badgeMap = $menuService->computeBadges($user);
        @endphp

        <ul class="nav flex-column gap-2">
            @foreach($dbMenu as $section => $items)
                <x-menu.section :items="$items" :showDivider="!$loop->first" :badgeMap="$badgeMap" />
            @endforeach
        </ul>
    </div>
  </div>