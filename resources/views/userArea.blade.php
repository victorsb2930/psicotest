@extends('layout')
@section('title', 'Área de Usuario')
@section('page','userArea')
@section('content')
	<?php
		$user = Auth::user();
	?>
	{{-- @auth
	<div class="container mt-5">
		<h1>Bienvenido, {{ Auth::user()->name }}!</h1>
		<p>Este es tu perfil de usuario.</p>
		<!-- Aquí puedes agregar más detalles del perfil del usuario -->
	</div>
	@else
	<div class="container mt-5">
		<h1>No estás autenticado</h1>
		<p>Por favor, <a href="/">inicia sesión</a> para acceder a tu área de usuario.</p>
	</div>
	@endauth --}}
@endsection
@section('content')
	@php $user = Auth::user(); @endphp

	@auth
	<div class="container mt-5">
		<div class="row">
			<div class="col-md-4">
				<div class="card">
					<div class="card-body text-center">
						{{-- Placeholder avatar --}}
						<img src="/images/2784445.png" alt="avatar" class="rounded-circle mb-3" style="width:96px;height:96px;object-fit:cover">
						<h4 class="card-title">{{ $user->name }}</h4>
						<p class="text-muted mb-1">{{ $user->email }}</p>
						<p class="small text-muted">Registrado: {{ optional($user->created_at)->format('Y-m-d') }}</p>
					</div>
				</div>
			</div>
			<div class="col-md-8">
				<div class="card">
					<div class="card-body">
						<h5 class="card-title">Perfil</h5>
						<dl class="row">
							<dt class="col-sm-3">Nombre</dt>
							<dd class="col-sm-9">{{ $user->name }}</dd>

							<dt class="col-sm-3">Email</dt>
							<dd class="col-sm-9">{{ $user->email }}</dd>

							<dt class="col-sm-3">Roles</dt>
							<dd class="col-sm-9">{{ $user->roles->pluck('name')->join(', ') }}</dd>

							<dt class="col-sm-3">Estado</dt>
							<dd class="col-sm-9">{{ $user->is_active ? 'Activo' : 'En revisión / Desactivado' }}</dd>
						</dl>

						<div class="mt-3">
							{{-- Edit profile route may not exist; link to /profile as a convention --}}
							<a href="/profile" class="btn btn-primary me-2">Editar perfil</a>
							<form method="POST" action="/logout" style="display:inline">@csrf<button class="btn btn-outline-secondary">Cerrar sesión</button></form>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	@else
	<div class="container mt-5">
		<h1>No estás autenticado</h1>
		<p>Por favor, <a href="/">inicia sesión</a> para acceder a tu área de usuario.</p>
	</div>
	@endauth

@endsection