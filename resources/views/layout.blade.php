<!DOCTYPE html>
<html lang="{{str_replace('_', '-', app()->getLocale())}}">

<head>
	@yield('head')
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	 <meta name="csrf-token" content="{{ csrf_token() }}">
	<title>@yield('title', 'layout')</title>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
	@vite(['resources/css/app.css','resources/js/app.js'])
</head>

<body data-page="@yield('page','default')" class="d-flex flex-column min-vh-100">
	@php($showHeader = $showHeader ?? true)
	@if($showHeader)
	<header class="site-header py-3 mb-0 border-bottom w-100">
		<div class="container d-flex align-items-center justify-content-between">
			<a href="{{ auth()->check() ? ( auth()->user()->hasRole('admin') ? '/adminarea' : (auth()->user()->hasRole('professional') ? '/professionalarea' : '/userarea') ) : '/' }}" class="brand d-flex align-items-center mb-0 text-white text-decoration-none">
				<img src="{{ Vite::asset('resources/images/p.png') }}" alt="Logo" width="40" height="32" class="me-2">
				<span class="fs-4">PsicoGuia</span>
			</a>
			<ul class="nav nav-pills align-items-center">
			@auth
				@php($user = Auth::user())
				@if(method_exists($user, 'hasRole') && $user->hasRole('admin'))
					<li class="nav-item"><a href="/admin/users" class="nav-link">Panel Admin</a></li>
				@endif
				<li class="nav-item"><a href="/perfil" class="nav-link">Perfil</a></li>
				<li class="nav-item"><span class="nav-link disabled text-white-75">Hola, {{ Auth::user()->name }}</span></li>
				<form action="{{ route('logout') }}" method="POST" class="d-inline">
					@csrf
					<li class="nav-item"><button type="submit" class="nav-link btn btn-link text-white" style="padding: 0; border: none; background: none; cursor: pointer;">Cerrar sesión</button></li>
				</form>
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
		@yield('content')
	</main>


	@php($showFooter = $showFooter ?? true)
	@if($showFooter)
	<footer class="text-white site-header">
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
</body>

</html>