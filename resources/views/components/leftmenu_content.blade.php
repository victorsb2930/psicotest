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
            $is = function($name) { return request()->routeIs($name); };
            // Safe DB checks for badges
            $profPending = 0;
            try {
                if (Schema::hasTable('professional_applications')) {
                    $profPending = DB::table('professional_applications')->where('status','pending')->count();
                }
            } catch (\Throwable $_) { $profPending = 0; }
        @endphp

        <ul class="nav flex-column gap-2">
            @php $firstSection = true; @endphp
            @foreach($dbMenu as $section => $items)
                @if(!$firstSection)
                    <hr class="sidebar-divider my-0">
                @endif
                @php $firstSection = false; @endphp
                @foreach($items as $it)
                    @php
                        $href = $it->resolvedUrl();
                        $isChat = $it->route_name === 'chat.index' || \Illuminate\Support\Str::contains($href, '/chat');
                        $badgeTotal = 0;
                        if ($isChat) {
                            try {
                                $unreadMsgs = 0; $pendingFriends = 0;
                                if (Schema::hasTable('messages')) { $unreadMsgs = \App\Models\Message::where('to_id', auth()->id())->whereNull('read_at')->count(); }
                                if (Schema::hasTable('friend_requests')) { $pendingFriends = \App\Models\FriendRequest::where('to_id', auth()->id())->where('status','pending')->count(); }
                                $badgeTotal = $unreadMsgs + $pendingFriends;
                            } catch (\Throwable $___) { $badgeTotal = 0; }
                        }
                    @endphp
                    <li class="nav-item {{ $it->route_name==='admin.profapps.index' ? 'position-relative' : '' }}">
                        <a href="{{ $href }}" class="nav-link px-0 d-flex align-items-center {{ $it->route_name ? ($is($it->route_name) ? 'active' : '') : '' }} {{ $isChat ? 'justify-content-between' : '' }}">
                            <span>@if(!empty($it->icon_class))<i class="{{ $it->icon_class }} me-2"></i>@endif{{ $it->label }}</span>
                            @if($it->route_name==='admin.profapps.index' && $profPending>0)
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{ $profPending }}</span>
                            @endif
                            @if($isChat && $badgeTotal>0)
                                <span class="badge text-bg-light text-dark ms-2">{{ $badgeTotal }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            @endforeach

            {{-- Nota: 'Planes' eliminado del hardcode. Si se requiere, agregar como item en DB (sección user o common). --}}

            {{-- COMMON LINKS (desde DB) --}}
            @php $commonItems = $dbMenu->get('common', collect()); @endphp
            @if($commonItems->count() > 0)
                <hr class="sidebar-divider my-1">
                @foreach($commonItems as $it)
                    @php $href = $it->resolvedUrl(); @endphp
                    <li class="nav-item">
                        <a href="{{ $href }}" class="nav-link px-0 {{ $it->route_name ? $is($it->route_name) : '' }}">
                            @if(!empty($it->icon_class))<i class="{{ $it->icon_class }} me-2"></i>@endif
                            {{ $it->label }}
                        </a>
                    </li>
                @endforeach
            @endif
            {{-- (Chat ya aparece arriba según área/DB) --}}
        </ul>
    </div>
  </div>