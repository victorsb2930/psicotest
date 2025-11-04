<div class="card h-100 border-0 shadow-sm">
    <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between mb-3">
            @php
                $homeUrl = auth()->check()
                    ? (auth()->user()->hasRole('admin')
                        ? route('adminarea')
                        : (auth()->user()->hasRole('professional')
                            ? route('professionalarea')
                            : route('userarea')))
                    : '/';
                $isAdmin = auth()->check() && auth()->user()->hasRole('admin');
            @endphp

            <a class="d-flex align-items-center text-white text-decoration-none" href="{{ $homeUrl }}">
                <div class="me-2"><i class="bi bi-grid-3x3-gap fs-4"></i></div>
                <div class="fw-semibold text-white">{{ $isAdmin ? 'Panel' : 'Menú' }}</div>
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
            {{-- ADMIN SECTION --}}
            @can('adminarea')
                <hr class="sidebar-divider my-0">
                <li class="nav-item">
                    <a href="{{ Route::has('adminarea') ? route('adminarea') : '#' }}" class="nav-link px-0 {{ $is('adminarea') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                </li>
                <hr class="sidebar-divider my-0">
                <li class="nav-item">
                    <a href="{{ Route::has('admin.users') ? route('admin.users') : '#' }}" class="nav-link px-0 {{ $is('admin.users') ? 'active' : '' }}">
                        <i class="bi bi-people me-2"></i>Usuarios
                    </a>
                </li>
                <li class="nav-item position-relative">
                    <a href="{{ Route::has('admin.profapps.index') ? route('admin.profapps.index') : '#' }}" class="nav-link px-0 {{ $is('admin.profapps.*') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-medical me-2"></i>Solicitudes
                    </a>
                    @if($profPending > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{ $profPending }}</span>
                    @endif
                </li>
                <li class="nav-item">
                    <a href="{{ Route::has('admin.roles.index') ? route('admin.roles.index') : '#' }}" class="nav-link px-0 {{ $is('admin.roles.*') ? 'active' : '' }}">
                        <i class="bi bi-shield-lock me-2"></i>Roles
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ Route::has('admin.permissions.index') ? route('admin.permissions.index') : '#' }}" class="nav-link px-0 {{ $is('admin.permissions.*') ? 'active' : '' }}">
                        <i class="bi bi-key me-2"></i>Permisos
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ Route::has('admin.devices') ? route('admin.devices') : (Route::has('user.devices') ? route('user.devices') : '#') }}" class="nav-link px-0 {{ $is('admin.devices') ? 'active' : '' }}">
                        <i class="bi bi-phone me-2"></i>Dispositivos
                    </a>
                </li>

            {{-- PROFESSIONAL / USER SECTION --}}
            @else
                @if(auth()->check() && auth()->user()->hasRole('professional'))
                    @if(auth()->user()->hasRole('admin') && $is('professionalarea'))
                        <li class="nav-item">
                            <a href="{{ Route::has('adminarea') ? route('adminarea') : '#' }}" class="nav-link px-0">
                                <i class="bi bi-arrow-left-circle me-2"></i>Volver a admin
                            </a>
                        </li>
                    @endif

                    <li class="nav-item">
                        <a href="{{ Route::has('professionalarea') ? route('professionalarea') : '#' }}" class="nav-link px-0 {{ $is('professionalarea') ? 'active' : '' }}">
                            <i class="bi bi-person-badge me-2"></i>Mi panel
                        </a>
                    </li>
                    <li class="nav-item"><a href="{{ Route::has('professional.calendar') ? route('professional.calendar') : '#' }}" class="nav-link px-0 {{ $is('professional.calendar') ? 'active' : '' }}"><i class="bi bi-calendar3 me-2"></i>Calendario</a></li>
                    <li class="nav-item">
                        @php
                            $chatUrl = \Illuminate\Support\Facades\Route::has('chat.index') ? route('chat.index') : (\Illuminate\Support\Facades\Route::has('messages.index') ? route('messages.index') : '#');
                            $unreadMsgs = 0; $pendingFriends = 0;
                            try { if (Schema::hasTable('messages')) { $unreadMsgs = \App\Models\Message::where('to_id', auth()->id())->whereNull('read_at')->count(); } } catch(\Throwable $e) { $unreadMsgs = 0; }
                            try { if (Schema::hasTable('friend_requests')) { $pendingFriends = \App\Models\FriendRequest::where('to_id', auth()->id())->where('status','pending')->count(); } } catch(\Throwable $_) { $pendingFriends = 0; }
                            $badgeTotal = $unreadMsgs + $pendingFriends;
                            $isChatActive = request()->routeIs('chat.index') || request()->is('chat');
                        @endphp
                        <a href="{{ $chatUrl }}" class="nav-link px-0 d-flex align-items-center justify-content-between {{ $isChatActive ? 'active' : '' }}">
                            <span><i class="bi bi-chat-dots me-2"></i>Chat</span>
                            @if($badgeTotal>0)<span class="badge text-bg-light text-dark ms-2">{{ $badgeTotal }}</span>@endif
                        </a>
                    </li>
                    <li class="nav-item"><a href="{{ Route::has('professional.patients') ? route('professional.patients') : '#' }}" class="nav-link px-0 {{ $is('professional.patients') ? 'active' : '' }}"><i class="bi bi-people me-2"></i>Pacientes</a></li>
                    <li class="nav-item"><a href="{{ Route::has('professional.services') ? route('professional.services') : '#' }}" class="nav-link px-0 {{ $is('professional.services') ? 'active' : '' }}"><i class="bi bi-briefcase me-2"></i>Servicios</a></li>
                    <li class="nav-item"><a href="{{ Route::has('professional.payments') ? route('professional.payments') : '#' }}" class="nav-link px-0 {{ $is('professional.payments') ? 'active' : '' }}"><i class="bi bi-credit-card me-2"></i>Historial de Pagos</a></li>
                    <li class="nav-item"><a href="{{ Route::has('professional.settings') ? route('professional.settings') : '#' }}" class="nav-link px-0 {{ $is('professional.settings') ? 'active' : '' }}"><i class="bi bi-gear me-2"></i>Configuración</a></li>

                {{-- REGULAR USER SECTION --}}
                @else
                    <li class="nav-item"><a href="{{ Route::has('userarea') ? route('userarea') : '#' }}" class="nav-link px-0 {{ $is('userarea') ? 'active' : '' }}"><i class="bi bi-house me-2"></i>Mi cuenta</a></li>
                    <li class="nav-item"><a href="{{ Route::has('appointments.index') ? route('appointments.index') : (Route::has('userarea') ? route('userarea') : '#') }}" class="nav-link px-0"><i class="bi bi-calendar3 me-2"></i>Calendario</a></li>
                    <li class="nav-item"><a href="{{ Route::has('professionals.index') ? route('professionals.index') : '#' }}" class="nav-link px-0 {{ $is('professionals.index') ? 'active' : '' }}"><i class="bi bi-search me-2"></i>Buscar profesionales</a></li>
                    <li class="nav-item"><a href="{{ Route::has('favorites') ? route('favorites') : '#' }}" class="nav-link px-0"><i class="bi bi-star me-2"></i>Favoritos</a></li>
                    <li class="nav-item">
                        @php
                            $chatUrl = \Illuminate\Support\Facades\Route::has('chat.index') ? route('chat.index') : (\Illuminate\Support\Facades\Route::has('messages.index') ? route('messages.index') : '#');
                            $unreadMsgs = 0; $pendingFriends = 0;
                            try { if (Schema::hasTable('messages')) { $unreadMsgs = \App\Models\Message::where('to_id', auth()->id())->whereNull('read_at')->count(); } } catch(\Throwable $e) { $unreadMsgs = 0; }
                            try { if (Schema::hasTable('friend_requests')) { $pendingFriends = \App\Models\FriendRequest::where('to_id', auth()->id())->where('status','pending')->count(); } } catch(\Throwable $_) { $pendingFriends = 0; }
                            $badgeTotal = $unreadMsgs + $pendingFriends;
                            $isChatActive = request()->routeIs('chat.index') || request()->is('chat');
                        @endphp
                        <a href="{{ $chatUrl }}" class="nav-link px-0 d-flex align-items-center justify-content-between {{ $isChatActive ? 'active' : '' }}">
                            <span><i class="bi bi-chat-dots me-2"></i>Chat</span>
                            @if($badgeTotal>0)<span class="badge text-bg-light text-dark ms-2">{{ $badgeTotal }}</span>@endif
                        </a>
                    </li>
                @endif
            @endcan

            {{-- Link to Plans page --}}
            @if(auth()->check() && auth()->user()->hasRole('user'))
                <hr class="sidebar-divider my-1">
                <li class="nav-item">
                    <a href="{{ Route::has('plans.index') ? route('plans.index') : '#' }}" class="nav-link px-0 {{ request()->routeIs('plans.*') ? 'active' : '' }}">
                        <i class="bi bi-card-list me-2"></i>Planes
                    </a>
                </li>
            @endif

            {{-- COMMON LINKS --}}
            <hr class="sidebar-divider my-1">
            <li class="nav-item"><a href="{{ route('contact') }}" class="nav-link px-0"><i class="bi bi-envelope me-2"></i>Contacto</a></li>
            <li class="nav-item"><a href="{{ route('services') }}" class="nav-link px-0"><i class="bi bi-briefcase me-2"></i>Servicios</a></li>
            {{-- (Eliminado duplicado) Chat aparece una sola vez más arriba según rol --}}
        </ul>
    </div>
  </div>