@extends('layout')
@section('title', 'Área de Usuario')
@section('page', 'user-area')
@section('content')
@php $user = Auth::user(); @endphp

@auth
<div class="container py-4">
	<header class="d-flex justify-content-between align-items-center mb-4">
		<h3>Hola, {{ $user->name }}</h3>
		<div class="d-flex gap-2">
			<input class="form-control form-control-sm" placeholder="Buscar por especialidad o nombre">
			<button class="btn btn-primary btn-sm">Buscar</button>
		</div>
	</header>

	<div class="row g-3">
		<div class="col-lg-8">
			<div class="card card-compact p-3 mb-3">
				<div class="d-flex justify-content-between align-items-center">
					<div>
						<small class="text-muted">PRÓXIMA CITA</small>
						<div class="fw-bold">--</div>
						<div>Con: <strong>--</strong></div>
					</div>
					<div>
						<a href="#" class="btn btn-outline-secondary btn-sm">Ver detalles</a>
						<a href="#" class="btn btn-success btn-sm">Ir a sala</a>
					</div>
				</div>
			</div>

			<div class="card p-3 mb-3">
				<h5>Profesionales recomendados</h5>
				<div class="row">
					<div class="col-md-6 col-lg-4 mb-2">
						<div class="card p-2">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<div class="fw-bold">Dra. Ana Ruiz</div>
									<div class="small text-muted">Cognitiva • $25</div>
								</div>
								<div><a href="#" class="btn btn-sm btn-primary">Reservar</a></div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="card p-3 mb-3">
				<h5>Mensajes</h5>
				<div class="list-group list-group-flush">
					<div class="list-group-item d-flex justify-content-between align-items-center">
						<div>
							<div class="fw-bold">Dra. Ana R.</div>
							<div class="small text-muted">¿Confirmamos la sesión? — Ayer</div>
						</div>
						<div><a href="#" class="btn btn-sm btn-outline-primary">Abrir</a></div>
					</div>
				</div>
			</div>
		</div>

		<aside class="col-lg-4">
			<div class="card p-3 mb-3">
				<h6>Filtros rápidos</h6>
				<div class="mb-2">
					<label class="form-label small">Modalidad</label>
					<select class="form-select form-select-sm"><option>Online</option><option>Presencial</option></select>
				</div>
				<div class="mb-2">
					<label class="form-label small">Precio</label>
					<select class="form-select form-select-sm"><option>Todos</option><option>$</option><option>$$</option></select>
				</div>
				<a href="#" class="btn btn-outline-secondary btn-sm">Aplicar</a>
			</div>

			<div class="card p-3 mb-3">
				<h6>Ayuda y emergencia</h6>
				<p class="small text-muted mb-0">Si estás en riesgo, llama a emergencias. Esta plataforma no reemplaza un servicio de urgencia.</p>
			</div>
		</aside>
	</div>
</div>
@else
<div class="container mt-5">
	<h1>No estás autenticado</h1>
	<p>Por favor, <a href="/welcome">inicia sesión</a> para acceder a tu área de usuario.</p>
</div>
@endauth

@endsection