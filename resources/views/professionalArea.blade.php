@extends('layout')
@section('title', 'Área de Profesional')
@section('page','professionalArea')
@section('content')
@auth
<div class="container-fluid">
	<div class="row vh-100">
		<main class="col p-4">
			<header class="d-flex justify-content-between align-items-center mb-3">
				<h2 class="m-0">Mi panel</h2>
			</header>

			<section class="row g-3">
				<div class="col-lg-4">
					<div class="card card-compact">
						<div class="d-flex justify-content-between align-items-start">
							<div>
								<small class="text-muted">PRÓXIMA CITA</small>
								<div class="fw-bold">--</div>
								<div>Paciente: <strong>--</strong></div>
								<div class="mt-2"><button class="btn btn-sm btn-success">Ir a sala</button> <button class="btn btn-sm btn-outline-secondary">Reprogramar</button></div>
							</div>
							<div class="text-end">
								<small class="text-muted">Modalidad</small>
								<div class="badge bg-info text-dark">Telemedicina</div>
							</div>
						</div>
					</div>

					<div class="card card-compact mt-3">
						<small class="text-muted">Mini calendario</small>
						<div class="mini-cal mt-2 p-2">[Bloques horarios — arrastrar para reprogramar]</div>
					</div>

					<div class="card card-compact mt-3">
						<small class="text-muted">Onboarding</small>
						<ul class="mb-0 mt-2">
							<li>Verificar credenciales <small class="text-success">@if(auth()->user()->hasVerifiedEmail()) ✔ @endif</small></li>
							<li>Configurar precios</li>
							<li>Completar bio</li>
						</ul>
					</div>
				</div>

				<div class="col-lg-5">
					<div class="card p-3">
						<div class="d-flex justify-content-between align-items-center mb-2">
							<h5 class="mb-0">Mensajes recientes</h5>
							<small class="text-muted">-- sin leer</small>
						</div>
						<div class="list-group list-group-flush">
							<div class="list-group-item">
								<div class="d-flex justify-content-between">
									<div>
										<div class="fw-bold">--</div>
										<div class="text-muted small">--</div>
									</div>
									<div><button class="btn btn-sm btn-outline-primary">Abrir</button></div>
								</div>
							</div>
						</div>
					</div>

					<div class="card p-3 mt-3">
						<h5 class="mb-2">Próximas 7 citas</h5>
						<div class="table-responsive">
							<table class="table table-borderless">
								<thead class="small text-muted"><tr><th>Fecha</th><th>Paciente</th><th>Modalidad</th><th></th></tr></thead>
								<tbody>
									<tr><td>--</td><td>--</td><td>--</td><td><button class="btn btn-sm btn-outline-secondary">Detalles</button></td></tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<aside class="col-lg-3">
					<div class="card p-3">
						<h6 class="small text-muted">KPIs</h6>
						<div class="d-flex flex-column gap-2">
							<div class="d-flex justify-content-between"><div>Sesiones hoy</div><div class="fw-bold">0</div></div>
							<div class="d-flex justify-content-between"><div>Ingresos (30d)</div><div class="fw-bold">$0</div></div>
							<div class="d-flex justify-content-between"><div>Rating</div><div class="fw-bold">-</div></div>
						</div>
					</div>

					<div class="card p-3 mt-3">
						<small class="text-muted">Accesos rápidos</small>
						<div class="d-grid gap-2 mt-2">
							<a href="#" class="btn btn-outline-primary btn-sm">Nuevo cupón</a>
							<a href="#" class="btn btn-outline-secondary btn-sm">Exportar facturas</a>
						</div>
					</div>
				</aside>
			</section>
		</main>
	</div>
</div>
@else
<div class="container mt-5">
	<h1>No estás autenticado</h1>
	<p>Por favor, <a href="/welcome">inicia sesión</a> para acceder a tu área de profesional.</p>
</div>
@endauth

@endsection