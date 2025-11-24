@extends('layout')
@section('title', 'Área de Profesional')
@section('page', 'professional-area')
@section('content')
@auth
<div class="container-fluid">
	<div class="row vh-100">
		<main class="col p-4">
			<header class="d-flex justify-content-between align-items-center mb-3">
				<h2 class="m-0">Mi panel</h2>
			</header>

			<section class="row g-3">
				<style>
					/* make the recent messages card a bit smaller and remove hover lift for this page only */
					.pg-small-card { max-width: 420px; }
					.pg-small-card .card-anim-lift { transition: none !important; transform: none !important; }
				</style>

				<div class="col-lg-4">
					<x-card title="PRÓXIMA CITA" :hover="false" :center="false" class="card-compact" height="auto" width="100%">
						@php
							$hasAppt = isset($nextAppt) && $nextAppt;
						@endphp
						@if($hasAppt)
							@php
								$apptTitle = $nextAppt->title ?: ($nextAppt->patient?->name ?? 'Cita');
								$startStr = $nextAppt->start ? $nextAppt->start->format('d/m/Y H:i') : null;
								$endStr = $nextAppt->end ? $nextAppt->end->format('d/m/Y H:i') : null;
								$notes = trim((string)($nextAppt->notes ?? ''));
							@endphp
							<div class="text-start" id="pg-next-appt"
								data-appt-id="{{ $nextAppt->id }}"
								data-patient-id="{{ $nextAppt->patient_id }}"
								data-title="{{ $apptTitle }}"
								data-start="{{ $nextAppt->start?->toIso8601String() }}"
								data-end="{{ $nextAppt->end?->toIso8601String() }}"
								data-start-human="{{ $startStr }}"
								data-end-human="{{ $endStr }}"
								data-notes="{{ e($notes) }}"
								@if(isset($pendingReschedule) && $pendingReschedule)
									data-reschedule-id="{{ $pendingReschedule->id }}"
									data-reschedule-start="{{ $pendingReschedule->proposed_start?->toIso8601String() }}"
									data-reschedule-end="{{ $pendingReschedule->proposed_end?->toIso8601String() }}"
								@endif
							>
								<div class="fw-bold mb-1">{{ $apptTitle }}</div>
								<div>Paciente: <strong>{{ $nextAppt->patient?->name ?? '—' }}</strong></div>
								@if($startStr)
									<div class="text-muted small mt-1">Horario: {{ $startStr }}@if($endStr) – {{ $endStr }}@endif</div>
								@endif
								@if(isset($pendingReschedule) && $pendingReschedule)
									@php
										$prStart = $pendingReschedule->proposed_start?->format('d/m/Y H:i');
										$prEnd = $pendingReschedule->proposed_end?->format('d/m/Y H:i');
									@endphp
									<div class="alert alert-warning py-2 px-3 mt-2" id="pg-reschedule-banner">
										<div class="small">Reprogramación pendiente: <strong>{{ $prStart }}</strong>@if($prEnd) – <strong>{{ $prEnd }}</strong>@endif</div>
										<div class="mt-2 d-flex gap-2 flex-wrap">
											<button type="button" class="btn btn-sm btn-primary" data-reschedule-action="accept" data-reschedule-id="{{ $pendingReschedule->id }}">Aceptar</button>
											<button type="button" class="btn btn-sm btn-outline-secondary" data-reschedule-action="reject" data-reschedule-id="{{ $pendingReschedule->id }}">Rechazar</button>
										</div>
									</div>
								@endif
								<div class="mt-3 d-flex gap-2 flex-wrap">
									<button type="button" class="btn btn-sm btn-outline-secondary" data-appt-action="details">Ver detalles</button>
									<button type="button" class="btn btn-sm btn-success" data-appt-action="join">Iniciar / Acceder</button>
									<button type="button" class="btn btn-sm btn-outline-primary" data-appt-action="reschedule">Reprogramar cita</button>
								</div>
							</div>
						@else
							<div class="text-start">
								<small class="text-muted">PRÓXIMA CITA</small>
								<div class="mt-1 text-muted">No tienes próximas citas.</div>
								<div class="mt-2"><a href="{{ route('professional.calendar') }}" class="btn btn-sm btn-outline-secondary">Abrir calendario</a></div>
							</div>
						@endif
					</x-card>

					{{-- <div class="card card-compact mt-3">
						<small class="text-muted">Onboarding</small>
						<ul class="mb-0 mt-2">
							<li>Verificar credenciales <small class="text-success">@if(auth()->user()->hasVerifiedEmail()) ✔ @endif</small></li>
							<li>Configurar precios</li>
							<li>Completar bio</li>
						</ul>
					</div> --}}
				</div>

				<div class="col-lg-5">
					<x-card title="Mensajes recientes" titleRight="-- últimos" :hover="false" :center="false" class="p-0" height="auto" width="100%" >
						<div class="list-group list-group-flush" id="pg-prof-messages-list">
							<!-- JS rellenará aquí hasta 3 conversaciones -->
						</div>
					</x-card>
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

					{{-- <div class="card p-3 mt-3">
						<small class="text-muted">Accesos rápidos</small>
						<div class="d-grid gap-2 mt-2">
							<a href="#" class="btn btn-outline-primary btn-sm">Nuevo cupón</a>
							<a href="#" class="btn btn-outline-secondary btn-sm">Exportar facturas</a>
							<a href="{{ route('professional.appointments.history') }}" class="btn btn-outline-success btn-sm">Historial de citas</a>
						</div>
					</div> --}}
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

@push('scripts')
	@include('partials.appt-join-poller')
@endpush
