<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @yield('head')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PsicoGuia')</title>
    <!-- Minimal critical CSS -->
    <style>
        html, body { font-family: 'Poppins', Arial, sans-serif; background: #fff0f2; color: #1b1b18 }
        .site-header{ background: #f58198 }
        .site-header .brand, .site-header .brand span { color: #fff }
        .btn-cta { background: #fff; color: #f58198; font-weight:700; padding:6px 16px; border-radius:9999px }
    </style>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>

<body data-page="@yield('page','default')" class="d-flex flex-column min-vh-100">
    @php
        $showHeader = $showHeader ?? true;
        $showLeftMenu = $showLeftMenu ?? true;
        $showFooter = $showFooter ?? true;
    @endphp

    @if($showHeader)
    <header class="site-header py-3 mb-0 border-bottom w-100">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="{{ auth()->check() ? ( auth()->user()->hasRole('admin') ? '/adminarea' : (auth()->user()->hasRole('professional') ? '/professionalarea' : '/userarea') ) : '/' }}" class="brand d-flex align-items-center mb-0 text-white text-decoration-none">
                <img src="{{ Vite::asset('resources/images/p.png') }}" alt="Logo" width="40" height="32" class="me-2">
                <span class="fs-4">PsicoGuia</span>
            </a>

            <ul class="nav nav-pills align-items-center mb-0">
                @auth
                    @php
                        // Prepare notifications for the dropdown. Use the notifications table if present.
                        $notifications = collect();
                        $notifCount = 0;
                        if (auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('notifications')) {
                            try {
                                $notifications = auth()->user()->notifications()->whereNull('read_at')->latest()->limit(6)->get();
                                $notifCount = auth()->user()->notifications()->whereNull('read_at')->count();
                            } catch (\Throwable $e) {
                                $notifications = collect();
                                $notifCount = 0;
                            }
                        }
                    @endphp

                    <li class="nav-item dropdown mx-2">
                        <a class="nav-link position-relative dropdown-toggle text-white" href="#" id="globalNotifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notificaciones">
                            <i class="bi bi-bell"></i>
                            @if($notifCount > 0)
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{ $notifCount }}</span>
                            @endif
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="globalNotifDropdown">
                            <li class="dropdown-header">Notificaciones</li>
                            @forelse($notifications as $n)
                                @php
                                    $data = is_array($n->data) ? $n->data : (array) $n->data;
                                    $title = $data['title'] ?? $data['message'] ?? ($data['body'] ?? null);
                                    $title = $title ? $title : \Illuminate\Support\Str::limit(json_encode($data, JSON_UNESCAPED_UNICODE), 80);
                                    $href = $data['link'] ?? ($data['url'] ?? '#');
                                    $icon = $data['icon'] ?? 'bell';
                                @endphp
                                <li>
                                    <a class="dropdown-item d-flex align-items-start" href="{{ $href }}">
                                        <div class="me-2"><i class="bi bi-{{ $icon }} fs-5"></i></div>
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">{{ $n->created_at->diffForHumans() }}</div>
                                            <div class="lh-1">{!! e($title) !!}</div>
                                        </div>
                                    </a>
                                </li>
                            @empty
                                <li class="dropdown-item text-muted">No hay notificaciones</li>
                            @endforelse
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center small" href="/notifications">Ver todas</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="layoutUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="{{ Vite::asset('resources/images/p.png') }}" width="32" height="32" class="rounded-circle me-2" alt="avatar">
                            <span class="d-none d-lg-inline text-white">{{ Auth::user()->name ?? 'Usuario' }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><a class="dropdown-item" href="/perfil">Perfil</a></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}" class="m-0">
                                    @csrf
                                    <button class="dropdown-item" type="submit">Cerrar sesión</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item"><a href="/services" class="nav-link">Servicios</a></li>
                    <li class="nav-item"><a href="/about" class="nav-link">Sobre nosotros</a></li>
                    <li class="nav-item"><a href="/contact" class="nav-link">Contacto</a></li>
                    <li class="nav-item ms-2"><a href="/welcome" class="nav-link btn-cta">Iniciar sesión</a></li>
                @endauth
            </ul>
        </div>
    </header>
    @endif

    <main class="flex-grow-1 bg-brand-soft">
        @if($showLeftMenu && auth()->check())
            <div class="container-fluid py-4">
                <div class="row gx-4">
                    @include('components.leftmenu')
                    <div id="app-content" class="col-12 col-lg-9">
                        @yield('content')
                    </div>
                </div>
            </div>
        @else
            <div class="container py-4">
                @yield('content')
            </div>
        @endif
    </main>

    @if($showFooter)
    <footer class="text-white site-header mt-auto">
        <div class="container py-4">
            <div class="row justify-content-center text-center">
                <div class="col-12 col-md-10 col-lg-8">
                    <p class="mb-2">&copy; 2025 PsicoGuía. Todos los derechos reservados.</p>
                    <ul class="list-inline mb-0">
                        <li class="list-inline-item"><a class="link-light text-decoration-underline" href="#">Política de privacidad</a></li>
                        <li class="list-inline-item text-white-50">|</li>
                        <li class="list-inline-item"><a class="link-light text-decoration-underline" href="#">Términos de uso</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>
    @endif

    @stack('scripts')
</body>
</html>