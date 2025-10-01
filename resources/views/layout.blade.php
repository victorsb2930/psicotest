<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
	@yield('head')
	<!-- FullCalendar CSS: loaded from local assets (see resources/css/vendor) -->
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	@if(auth()->check())<meta name="auth-user-id" content="{{ auth()->id() }}">@endif
	<title>@yield('title', 'PsicoGuia')</title>
	<!-- Minimal critical CSS -->
	<style>
		:root {
			--brand-600: #e11d6a;
			/* deep warm pink */
			--brand-500: #f02d77;
			/* vivid pink */
			--brand-400: #f472b6;
			/* light pink */
			--brand-100: #fff;
			--header-h: 72px;
			/* approx header height; adjust if needed */
			--footer-h: 68px;
			/* target shorter footer height */
		}

		html,
		body {
			font-family: 'Poppins', Arial, sans-serif;
			background: #fbfbfd;
			color: #111217
		}

		/* Header: pink gradient, subtle shadow and comfortable padding */
		.site-header {
			background: linear-gradient(90deg, var(--brand-600) 0%, var(--brand-500) 60%, var(--brand-400) 100%);
			background-color: var(--brand-600);
			box-shadow: 0 6px 30px rgba(16, 24, 40, 0.08);
			padding: 0.9rem 0;
		}

		.site-header .brand,
		.site-header .brand span {
			color: var(--brand-100);
			letter-spacing: 0.2px;
			font-weight: 600
		}

		/* Nav links: lighter, more breathing room and rounded backgrounds on hover */
		.site-header .nav-link {
			color: rgba(255, 255, 255, 0.95);
			padding: 8px 12px;
			margin: 0 6px;
			border-radius: 10px;
		}

		.site-header .nav-link:hover,
		.site-header .nav-link:focus {
			color: #fff;
			background: rgba(255, 255, 255, 0.08);
		}

		.site-header .nav-pills {
			gap: 0.6rem;
			align-items: center
		}

		.site-header .dropdown-menu {
			min-width: 260px;
		}

		.site-header .badge {
			font-size: 0.65rem;
		}

		/* Primary CTA: filled pink, white text, subtle shadow and softer pill */
		.btn-cta {
			background: linear-gradient(90deg, var(--brand-500), var(--brand-400));
			color: #fff;
			font-weight: 700;
			padding: 10px 18px;
			border-radius: 14px;
			box-shadow: 0 8px 28px rgba(219, 39, 119, 0.14);
			border: 0;
			display: inline-block;
		}

		/* Navbar-specific CTA: keep pink gradient but use a muted/gray label so the text reads over pink */
		.site-header .btn-cta {
			color: #9CA3AF !important;
			font-weight: 700;
			box-shadow: 0 8px 28px rgba(219, 39, 119, 0.12);
		}

		.btn-cta:hover {
			transform: translateY(-2px);
			box-shadow: 0 16px 40px rgba(240, 110, 186, 0.16) !important;
			opacity: 0.98
		}

		/* Subtle variant for secondary actions */
		.btn-ghost {
			background: transparent;
			color: var(--brand-100);
			border-radius: 10px;
			padding: 8px 12px;
			border: 1px solid rgba(255, 255, 255, 0.08);
		}

		/* Footer uses pink tones but inverted vertical gradient (light -> dark) */
		footer.site-header {
			background: linear-gradient(180deg, var(--brand-400) 0%, var(--brand-500) 60%, var(--brand-600) 100%) !important;
			background-color: var(--brand-600);
		}

		footer.site-header a {
			color: rgba(255, 255, 255, 0.95);
		}

		footer.site-header p {
			color: rgba(255, 255, 255, 0.92);
		}

		/* Left menu: visually integrated with header on large screens */
		/* Left menu: use the same horizontal pink gradient and fallback color as header/footer
		to keep colors consistent across browsers (Brave/Edge color-management differences). */
		#left-menu .card {
			background: linear-gradient(90deg, var(--brand-600) 0%, var(--brand-500) 60%, var(--brand-400) 100%);
			background-color: var(--brand-600);
			color: var(--brand-100);
			border: 0;
			box-shadow: none;
			border-radius: 0;
		}

		#left-menu .card .card-body {
			padding: 1rem;
		}

		#left-menu .nav-link {
			color: rgba(255, 255, 255, 0.95);
		}

		#left-menu .nav-link i {
			color: rgba(255, 255, 255, 0.9);
		}

		#left-menu .nav-link.active {
			background: rgba(255, 255, 255, 0.06);
			border-radius: 8px;
		}

		/* On large screens keep left menu as a normal column so it visually connects to header/footer
		The card will stretch to the viewport height minus header/footer so it appears as a single piece
		with the header and footer rather than floating independently. */
		@media (min-width: 992px) {
			#left-menu {
				position: relative;
				width: 280px; /* balanced width, flush to left */
				padding-left: 0; /* ensure flush to page edge */
			}

			#left-menu .card {
				height: calc(100vh - var(--header-h) - var(--footer-h));
				display: flex;
				flex-direction: column;
				margin-top: 0; /* align edge with header */
				margin-bottom: 0; /* touch footer */
				padding-left: 0.25rem;
				padding-right: 0.25rem;
			}

			/* left menu card uses normal flow (not sticky) to match previous layout */

			#left-menu .card .card-body {
				overflow: auto;
				/* move list slightly to the right so items align visually with content */
				padding-left: 0.9rem;
				padding-right: 0.9rem;
			}

			/* push main content to the right to make space for the left column */
			#app-content {
				margin-left: 0; /* use grid column spacing; leftmenu occupies its grid column */
				padding-left: 1.25rem; /* moderate breathing room from the left menu */
				padding-right: 4.5rem; /* allow content to use more right-side space */
				max-width: none;
			}

			/* Slightly reduce outer container padding to let content breathe more */
			.container-fluid { padding-left: 0.75rem; padding-right: 0.75rem; }
		}

		/* subtle separator between left menu and content to emphasize separation without breaking the connected look */
		@media (min-width: 992px) {
			#left-menu::after {
				content: '';
				position: absolute;
				top: 0;
				right: -0.5px; /* overlap slightly so menu reads as flush */
				height: 100%;
				width: 1px;
				background: rgba(0,0,0,0.06);
				pointer-events: none;
			}
		}

		/* Improve spacing for tables and forms inside content */
		#app-content table {
			width: 100%;
			border-collapse: separate;
			border-spacing: 0 0.4rem;
		}
		#app-content table thead th, #app-content table tbody td {
			padding: 0.8rem 1rem;
			background: #fff;
			border: 1px solid rgba(0,0,0,0.04);
			vertical-align: middle;
		}

		/* Roles table enhancements */
		.roles-table tbody tr {
			transition: box-shadow 0.12s ease;
			background: transparent;
			position: relative;
			z-index: 0;
		}
		.roles-table tbody tr:hover td {
			box-shadow: 0 6px 18px rgba(16,24,40,0.06);
			z-index: 1;
		}
		/* ensure buttons/inputs inside first cell stay above adjacent cells */
		.roles-table td { position: relative; }
		.roles-table td .btn, .roles-table td .form-control { position: relative; z-index: 2; }
		.roles-table .form-control {
			min-width: 120px;
		}
		.roles-table .input-group .form-control {
			flex: 1 1 auto;
		}
		@media (min-width: 992px) {
			.roles-table thead th { background: transparent; border: none; color: rgba(0,0,0,0.6); }
			.roles-table tbody td { background: #fff; }
		}

		/* Make inputs and button groups inside the table expand to available width */
		.roles-table .input-group, .roles-table .form-control {
			display: block;
			width: 100%;
		}
		.roles-table td > .form-control, .roles-table td .input-group { margin-bottom: 0.4rem; }
		.roles-table td { display: table-cell; vertical-align: middle; }

		/* Make certain cards taller and ensure white backgrounds */
		.card-min-lg {
			min-height: 480px;
			background: #fff;
			border: 1px solid rgba(0,0,0,0.04);
			box-shadow: 0 8px 24px rgba(16,24,40,0.04);
		}

		/* Ensure table responsive container has white background */
		.card .table-responsive {
			background: transparent;
		}

		/* Force table cells to white even if parent card background differs */
		.roles-table thead th, .roles-table tbody td { background: #fff; }

		/* Force cards (except left menu) to white to avoid browser color inconsistencies */
		.card {
			background: #fff;
		}
		#left-menu .card { background: linear-gradient(90deg, var(--brand-600) 0%, var(--brand-500) 60%, var(--brand-400) 100%); }

		/* Table header styling - stronger contrast and no bottom rule */
		.roles-table thead th {
			font-weight: 700;
			padding: 0.9rem 1rem;
			background: transparent !important;
			border-bottom: none !important;
			color: #000 !important;
		}

		/* General table header rules to keep all admin tables consistent */
		.table thead th { color: #000 !important; font-weight: 700; }
		.table thead th a { color: inherit !important; text-decoration: none !important; }
		.table thead th a span { color: inherit !important; }

		/* Card header polish: ensure full contrast and remove dividing line */
		.card .card-header {
			background: #fff !important;
			font-weight: 700;
			color: #000 !important;
			border-bottom: none !important;
			padding: 0.9rem 1rem;
		}

		/* Improve input-group visuals inside roles table */
		.roles-table .input-group { display: flex; gap: 0.5rem; align-items: center; }
		.roles-table .input-group .btn { flex: 0 0 auto; }
		.roles-table .input-group .form-control { flex: 1 1 auto; }

		/* Ensure interactive controls inside table cells stay above siblings and are fully visible */
		.roles-table td .btn, .roles-table td .form-check, .roles-table td .form-control {
			position: relative;
			z-index: 5;
		}

		/* If a control gets too close to an adjacent column, allow it to overflow visibly */
		.roles-table td { overflow: visible; }

		/* Table column widths for roles view on desktop */
		@media (min-width: 992px) {
			.roles-table thead th:nth-child(1), .roles-table tbody td:nth-child(1) { width: 6%; }
			/* Give more horizontal room to the 'Datos' column so controls/buttons don't get clipped */
			.roles-table thead th:nth-child(2), .roles-table tbody td:nth-child(2) { width: 60%; }
			/* Reduce 'Nombre' column to avoid overlapping the right edge */
			.roles-table thead th:nth-child(3), .roles-table tbody td:nth-child(3) { width: 18%; }
			.roles-table thead th:nth-child(4), .roles-table tbody td:nth-child(4) { width: 16%; }
			.roles-table td i { vertical-align: middle; margin-top: 0; }
			.roles-table .badge { vertical-align: middle; }
		}

		/* modern row cards */
		.roles-table tbody tr td {
			background: #fff;
			border: 0;
			box-shadow: 0 2px 10px rgba(16,24,40,0.04);
			border-radius: 8px;
			margin-bottom: 0.6rem;
		}
		.roles-table tbody tr + tr td { margin-top: 0.6rem; }

		/* badges like adminArea */
		.roles-table .badge { font-weight: 600; }
		.roles-table i { color: rgba(0,0,0,0.6); }

		/* Align brand image with leftmenu icon baseline */
		.nav-brand-img { margin-top: -2px; }

		/* Nombre cell: keep icon + badge centered and prevent wrapping that may overlap adjacent cells */
		.nombre-cell { display: inline-flex; align-items: center; gap: 0.5rem; white-space: nowrap; }
		/* Make header, leftmenu and footer appear as a single connected piece on wide screens */
		@media (min-width: 992px) {
			/* remove strong shadow on header so edges sit flush */
			.site-header {
				box-shadow: none;
				border-bottom: 1px solid rgba(0,0,0,0.04);
			}

			/* ensure left menu card has no radius and touches header/footer */
			#left-menu .card {
				border-radius: 0;
				margin-top: 0;
				margin-bottom: 0;
				border: 0;
			}

			/* footer should align visually with the left menu (no rounding) */
			footer.site-header {
				border-radius: 0;
				margin-bottom: 0;
			}

			/* main content: remove left-side rounding so it looks joined to the left area
			but keep small inner card rounding for content blocks */
			#app-content {
				padding-left: 1rem;
			}
			#app-content > .card, #app-content .card {
				border-top-left-radius: 0.25rem;
				border-bottom-left-radius: 0.25rem;
			}
		}

		/* Prevent browser color-inversion or forced-colors from changing admin card headers */
		@supports (forced-color-adjust: none) {
			.card .card-header, .roles-table thead th, .roles-table tbody td {
				forced-color-adjust: none;
			}
		}

		/* Ensure active leftmenu link appears darker and visible across browsers */
		#left-menu .nav-link.active {
			background: rgba(0,0,0,0.12) !important;
			color: #fff !important;
		}

		/* Force table header colors (stronger) to avoid browser-specific rendering */
		.roles-table thead th { color: #000 !important; background: #fff !important; }

		/* Stronger guard against forced dark mode / color inversion in some browsers (Brave) */
		html, body {
			color-scheme: light !important;
			background-color: #fbfbfd !important;
		}

		.card .card-header, .roles-table thead th, .roles-table tbody td {
			background-color: #fff !important;
			color: #000 !important;
			-webkit-text-fill-color: #000 !important;
			mix-blend-mode: normal !important;
			filter: none !important;
			-webkit-filter: none !important;
		}

		/* Extra: ensure table elements themselves also force light backgrounds */
		.roles-table, .roles-table thead, .roles-table tbody, .roles-table tr, .roles-table td, .roles-table th {
			background: #fff !important;
			color: #000 !important;
		}

		/* Small screens: tighten header padding and scale down brand */
		@media (max-width: 767px) {
			.site-header {
				padding-top: 0.55rem;
				padding-bottom: 0.55rem;
			}

			.btn-cta {
				padding: 8px 14px;
				font-size: 0.95rem;
				border-radius: 12px
			}

			.site-header .brand img {
				width: 36px;
				height: 28px
			}
		}
	</style>
	<style>
		/* Use Display-P3 colors on wide-gamut displays for more consistent saturation where supported */
		@supports (color: color(display-p3 1 0 0)) {
			:root {
				--brand-600: color(display-p3 0.882 0.114 0.416);
				/* approx #e11d6a */
				--brand-500: color(display-p3 0.941 0.176 0.482);
				/* approx #f02d77 */
				--brand-400: color(display-p3 0.957 0.447 0.712);
				/* approx #f472b6 */
			}
		}
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
	<header class="site-header py-3 mb-0 border-bottom w-100"
		style="background: linear-gradient(90deg, var(--brand-600) 0%, var(--brand-500) 60%, var(--brand-400) 100%); background-color: var(--brand-600);">
	<div class="container-fluid d-flex align-items-center justify-content-between">
			<a href="{{ auth()->check() ? ( auth()->user()->hasRole('admin') ? '/adminarea' : (auth()->user()->hasRole('professional') ? '/professionalarea' : '/userarea') ) : '/' }}"
				class="brand d-flex align-items-center mb-0 text-white text-decoration-none">
				<img src="{{ Vite::asset('resources/images/p.png') }}" alt="Logo" width="40" height="32" class="me-2 align-self-center nav-brand-img">
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
					<a class="nav-link position-relative dropdown-toggle text-white" href="#" id="globalNotifDropdown"
						role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notificaciones">
						<i class="bi bi-bell"></i>
						<span id="notif-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger" style="{{ $notifCount > 0 ? '' : 'display:none;' }}">{{ $notifCount > 0 ? $notifCount : '' }}</span>
					</a>
					<ul id="notif-dropdown-menu" class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="globalNotifDropdown">
						<li class="dropdown-header">Notificaciones</li>
						{{-- Notifications are dynamically populated by JS into this dropdown when the user is authenticated. --}}
						@forelse($notifications as $n)
						@php
						$data = is_array($n->data) ? $n->data : (array) $n->data;
						$title = $data['title'] ?? $data['message'] ?? ($data['body'] ?? null);
						$title = $title ? $title : \Illuminate\Support\Str::limit(json_encode($data,
						JSON_UNESCAPED_UNICODE), 80);
						$href = $data['link'] ?? ($data['url'] ?? '#');
						$icon = $data['icon'] ?? 'bell';
						@endphp
						<li>
							<a class="dropdown-item d-flex align-items-start notif-item" href="{{ $href }}" data-notif-id="{{ $n->id }}">
								<div class="me-2"><i class="bi bi-{{ $icon }} fs-5"></i></div>
								<div class="flex-grow-1">
									<div class="small text-muted">{{ $n->created_at->diffForHumans() }}</div>
									<div class="lh-1">{!! e($title) !!}</div>
								</div>
							</a>
						</li>
						@empty
						<li class="dropdown-item text-muted notif-empty">No hay notificaciones</li>
						@endforelse
						<li>
							<hr class="dropdown-divider">
						</li>
						<li><a class="dropdown-item text-center small" href="/notifications">Ver todas</a></li>
					</ul>
				</li>

				<li class="nav-item dropdown" data-bs-auto-close="outside">
						@php
						$user = Auth::user();
						$avatar = ($user?->photo) ? base64_encode(file_get_contents(public_path($user->photo))) : Vite::asset('resources/images/p.png');
						// prefer profile_photo_data_url (from user_photos.foto) then user->photo path
						if ($user) {
							if (!empty($user->profile_photo_data_url)) {
								$avatar = $user->profile_photo_data_url;
							} elseif (!empty($user->photo)) {
								$avatar = '/storage/' . ltrim($user->photo, '/');
							}
						}
						// map status to simple presence for UI
						$status = $user?->status ?? ($user?->is_active ? 'online' : 'offline');
						$presence = $status;
					@endphp
					<a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="layoutUserDropdown"
						role="button" data-bs-toggle="dropdown" aria-expanded="false">
						<span style="position:relative; display:inline-block; width:32px; height:32px; margin-right:0.5rem;">
							<img id="nav-avatar-img" src="{{ $avatar }}" width="32" height="32" class="rounded-circle" alt="avatar">
							@php
							$presenceColor = match($presence) {
								'online' => '#28a745',
								'busy' => '#fd7e14',
								'dnd' => '#dc3545',
								'away' => '#ffc107',
								'offline' => '#6c757d',
								default => '#6c757d'
							};
							@endphp
							<span class="presence-dot" style="position:absolute; right:-2px; bottom:-2px; width:10px; height:10px; border-radius:50%; border:2px solid #fff; background: {{ $presenceColor }};"></span>
						</span>
						<span class="d-none d-lg-inline text-white">{{ $user->name ?? 'Usuario' }}</span>
					</a>
					<ul class="dropdown-menu dropdown-menu-end shadow" id="userDropdownMenu">
						{{-- Perfil primero --}}
						<li><a class="dropdown-item" href="/perfil"><strong>Perfil</strong></a></li>

						{{-- Estado: sublist colapsable dentro del dropdown para mantenerlo limpio --}}
						<li>
							{{-- toggle como button para evitar el comportamiento por defecto del anchor --}}
							<button class="dropdown-item d-flex justify-content-between align-items-center presence-toggle" type="button" aria-expanded="false">Estado <i class="bi bi-chevron-down small"></i></button>
							<div class="presence-submenu" id="presenceCollapse" aria-hidden="true" style="display:none;">
								<ul class="list-unstyled ps-3 mb-2">
									@php
										$states = ['online'=>'Online','busy'=>'Ocupado','dnd'=>'No molestar','away'=>'Ausente','offline'=>'No disponible'];
										$stateColors = ['online'=>'#28a745','busy'=>'#fd7e14','dnd'=>'#dc3545','away'=>'#ffc107','offline'=>'#6c757d'];
									@endphp
									@foreach($states as $key => $label)
									@php $color = $stateColors[$key] ?? '#6c757d'; @endphp
									<li>
										<button class="dropdown-item presence-select d-flex align-items-center" data-status="{{ $key }}" type="button">
											<i class="bi bi-circle-fill me-2" style="color: {{ $color }}"></i>
											<span>{{ $label }}</span>
										</button>
									</li>
									@endforeach
								</ul>
							</div>
						</li>

						{{-- separación y acción de logout al final --}}
						<li><hr class="dropdown-divider my-1"></li>
						<li>
							<form method="POST" action="{{ route('logout') }}" class="m-0">
								@csrf
								<button class="dropdown-item text-danger" type="submit">Cerrar sesión</button>
							</form>
						</li>
					</ul>
				</li>
				@else
				<li class="nav-item"><a href="/services" class="nav-link">Servicios</a></li>
				<li class="nav-item"><a href="/about" class="nav-link">Sobre nosotros</a></li>
				<li class="nav-item"><a href="/contact" class="nav-link">Contacto</a></li>
				@unless(request()->is('welcome') || request()->is('welcome*'))
				<li class="nav-item ms-2">
					<a href="/welcome" role="button" class="nav-link btn-cta">Iniciar sesión</a>
				</li>
				@endunless
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
				<div id="app-content" class="col-12 col-lg-9" data-page="@yield('page','default')">
					@yield('content')
				</div>
			</div>
		</div>
		@else
		<div class="container py-4" id="app-content" data-page="@yield('page','default')">
			@yield('content')
		</div>
		@endif
	</main>

	@if($showFooter)
	<footer class="text-white site-header mt-auto"
		style="background: linear-gradient(180deg, var(--brand-400) 0%, var(--brand-500) 60%, var(--brand-600) 100%); background-color: var(--brand-600);">
		<div class="container py-4">
			<div class="row justify-content-center text-center">
				<div class="col-12 col-md-10 col-lg-8">
					<p class="mb-2">&copy; 2025 PsicoGuía. Todos los derechos reservados.</p>
					<ul class="list-inline mb-0">
						<li class="list-inline-item"><a class="link-light text-decoration-underline" href="#">Política
								de privacidad</a></li>
						<li class="list-inline-item text-white-50">|</li>
						<li class="list-inline-item"><a class="link-light text-decoration-underline" href="#">Términos
								de uso</a></li>
					</ul>
				</div>
			</div>
		</div>
	</footer>
	@endif

	@stack('scripts')
	<script>window.__authUserId = (document.querySelector('meta[name="auth-user-id"]')||{}).content || null;</script>
	@vite(['resources/js/realtime.js'])
	<script>
		// Presence dropdown handler: send POST to /profile/presence and update dot color
		document.addEventListener('DOMContentLoaded', function(){
			const map = { online: '#28a745', busy: '#fd7e14', dnd: '#dc3545', away: '#ffc107', offline: '#6c757d' };
			// Inyecta el estado actual del usuario desde backend
			@php
				$currentPresence = 'offline';
				if (auth()->check()) {
					$u = auth()->user();
					$currentPresence = $u->status ?? ($u->is_active ? 'online' : 'offline');
				}
			@endphp
			window.__userPresence = '{{ $currentPresence }}';

			// función para aplicar el estado en la UI (dot en header, profile, botones)
			function applyPresenceToUI(status){
				const stateColors = { online:'#28a745', busy:'#fd7e14', dnd:'#dc3545', away:'#ffc107', offline:'#6c757d' };
				const color = stateColors[status] || stateColors['offline'];
				const dot = document.querySelector('.presence-dot');
				if (dot) dot.style.background = color;
				const profileDot = document.getElementById('profile-presence');
				if (profileDot) profileDot.style.background = color;

				// marcar botón activo en perfil
				document.querySelectorAll('.presence-btn').forEach(function(b){
					if (b.getAttribute('data-status') === status) b.classList.add('active');
					else b.classList.remove('active');
				});

				// marcar (visualmente) opción activa en dropdown si existe
				document.querySelectorAll('.presence-select').forEach(function(b){
					if (b.getAttribute('data-status') === status) b.classList.add('active');
					else b.classList.remove('active');
				});
			}

			// Exponer la función globalmente para que módulos cargados por PJAX la usen
			try { window.applyPresenceToUI = applyPresenceToUI; } catch (e) {}

			// Inicializar UI con el estado del servidor
			document.addEventListener('DOMContentLoaded', function(){
				if (window.__userPresence) applyPresenceToUI(window.__userPresence);
			});

			// sincronización entre pestañas usando localStorage
			window.addEventListener('storage', function(e){
				if (e.key !== 'psicoguia_presence') return;
				try {
					const payload = JSON.parse(e.newValue || '{}');
					if (payload && payload.status) {
						window.__userPresence = payload.status;
						applyPresenceToUI(payload.status);
					}
				} catch(err){ /* ignore parse errors */ }
			});

			// centraliza la lógica de actualización de presencia (POST + UI + localStorage)
			window.updatePresence = async function(status){
				try {
					await window.axios.post('{{ route('profile.presence') }}', { status: status });
				} catch(e){
					console.error('updatePresence error', e);
					throw e;
				}
				window.__userPresence = status;
				applyPresenceToUI(status);
				try {
					localStorage.setItem('psicoguia_presence', JSON.stringify({ status: status, ts: Date.now() }));
				} catch(e) { /* ignore localStorage errors */ }
				return true;
			};

			// handler para botones dentro del dropdown
			document.querySelectorAll('.presence-select').forEach(function(btn){
				btn.addEventListener('click', async function(evt){
					evt.preventDefault();
					evt.stopPropagation();
					const s = btn.getAttribute('data-status');
					try { await window.updatePresence(s); } catch(e){}
				});
			});

			// handler para botones en la página de perfil (presence-btn)
			document.querySelectorAll('.presence-btn').forEach(function(btn){
				btn.addEventListener('click', async function(evt){
					evt.preventDefault();
					const s = btn.getAttribute('data-status');
					try { await window.updatePresence(s); } catch(e){}
					// marcar visualmente el botón seleccionado
					document.querySelectorAll('.presence-btn').forEach(function(b){ b.classList.remove('active'); });
					btn.classList.add('active');
				});
			});
		});
	</script>
	<script>
	// Global counters dynamic update
	document.addEventListener('DOMContentLoaded', function(){
		async function fetchCounters(){
			try { const r = await fetch('/api/counters'); const j = await r.json(); if(!j.ok) return; apply(j); } catch(_){ }
		}
		function apply(j){
			// Messages
			try {
				const link = document.querySelector('#left-menu a.nav-link i.bi-chat-dots')?.closest('a');
				if (link) {
					let badge = link.querySelector('.badge');
					if (j.messages_unread>0){ if(!badge){ badge=document.createElement('span'); badge.className='badge text-bg-light text-dark ms-2'; link.appendChild(badge);} badge.textContent=j.messages_unread; }
					else if(badge) badge.remove();
				}
			} catch(_){ }
			// Friends pending
			try {
				const fl = document.querySelector('#left-menu a.nav-link i.bi-people')?.closest('a');
				if (fl){ let fbadge = fl.querySelector('.badge'); if(j.friend_requests_pending>0){ if(!fbadge){ fbadge=document.createElement('span'); fbadge.className='badge text-bg-danger'; fl.appendChild(fbadge);} fbadge.textContent=j.friend_requests_pending; } else if(fbadge) fbadge.remove(); }
			} catch(_){ }
		}
		document.addEventListener('counters:update', ev => { if(ev.detail) apply(ev.detail); });
		// Poll fallback every 30s in case realtime down
		setInterval(fetchCounters, 30000);
		fetchCounters();
	});
	</script>
	<script>
		// UI sync: when authenticated, ensure public nav links don't appear and move them to user dropdown
		(function(){
			try {
				// Expose authentication state to frontend scripts. Many frontend
				// modules check window.__isAuth to enable/disable behaviors
				// (heartbeat, hiding public CTAs, etc.). Keep a local `isAuth`
				// variable for backward compatibility.
				window.__isAuth = {{ auth()->check() ? 'true' : 'false' }};
				const isAuth = window.__isAuth;
				// Hide public nav links if authenticated (defensive, in case server-side rendering missed one)
				if (isAuth) {
					document.querySelectorAll('.nav .nav-link').forEach(function(el){
						const text = (el.textContent || '').trim().toLowerCase();
						if (['servicios','sobre nosotros','contacto','iniciar sesión'].includes(text)) {
							el.style.display = 'none';
						}
					});

					// Try to append public links to user dropdown menu
					const userDropdown = document.querySelector('#layoutUserDropdown');
					const dropdownMenu = document.querySelector('#layoutUserDropdown')?.nextElementSibling;
					if (dropdownMenu) {
						const extra = [];
						extra.push('<li><a class="dropdown-item" href="/services">Servicios</a></li>');
						extra.push('<li><a class="dropdown-item" href="/about">Sobre nosotros</a></li>');
						extra.push('<li><a class="dropdown-item" href="/contact">Contacto</a></li>');
						const html = extra.join('');
						// Prefer inserting the public links before the visual separator so
						// the Logout button remains the last item in the dropdown.
						// Prefer inserting the public links before our dropdown divider so
						// the Logout button remains the last item. Look for the divider we
						// render: 'hr.dropdown-divider.my-1'. Fall back to end if not found.
						const sep = dropdownMenu.querySelector('hr.dropdown-divider.my-1');
						if (sep) {
							sep.insertAdjacentHTML('beforebegin', html);
						} else {
							dropdownMenu.insertAdjacentHTML('beforeend', html);
						}
					}
				}

				// Hide left menu when on welcome path
				try {
					const isWelcome = window.location.pathname.indexOf('/welcome') === 0 || window.location.pathname === '/';
					if (isWelcome) {
						const left = document.getElementById('left-menu');
						if (left) left.style.display = 'none';
					}
				} catch(e){}
			} catch(e){}
		})();
	</script>
	<script>
		// Presence submenu toggle: simple JS (no Bootstrap collapse) to avoid
		// closing the parent dropdown accidentally.
		document.addEventListener('DOMContentLoaded', function(){
			const toggles = document.querySelectorAll('.presence-toggle');
			toggles.forEach(function(btn){
				btn.addEventListener('click', function(evt){
					evt.preventDefault();
					evt.stopPropagation();
					const submenu = btn.parentElement.querySelector('.presence-submenu');
					if (!submenu) return;
					const isHidden = submenu.style.display === 'none' || submenu.getAttribute('aria-hidden') === 'true';
					submenu.style.display = isHidden ? 'block' : 'none';
					submenu.setAttribute('aria-hidden', isHidden ? 'false' : 'true');
					btn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
				});
			});

			// Clicking inside submenu should not close the dropdown; stop propagation
			document.querySelectorAll('.presence-submenu, .presence-submenu .presence-select').forEach(function(el){
				el.addEventListener('click', function(evt){ evt.stopPropagation(); });
			});
		});
	</script>
</body>

</html>