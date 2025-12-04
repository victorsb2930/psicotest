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
							$hasAppt = isset($nextAppt) && $nextAppt && (($nextAppt->status ?? '') !== 'completed');
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
					@php
						$pro = auth()->user();
						$start = \Carbon\Carbon::now()->startOfMonth();
						$end = \Carbon\Carbon::now()->endOfMonth();
						$sessions_today = 0; // could compute if needed
						$completed_month = 0;
						$completed_total = 0;
						$income_30d = 0;
						$income_total = 0;
						$pending_payout = 0;
						$last_payout = null;
						$rating_avg = null;
						try {
							$completed_month = \App\Models\Appointment::where('professional_id', $pro->id)->where('status', 'completed')->whereBetween('start', [$start, $end])->count();
							$completed_total = \App\Models\Appointment::where('professional_id', $pro->id)->where('status', 'completed')->count();
							$income_30d = (int) (\App\Models\Payment::where('recipient_user_id', $pro->id)->where('status', 'succeeded')->where('created_at', '>=', \Carbon\Carbon::now()->subDays(30))->sum('amount_cents')) / 100;
							$income_total = (int) (\App\Models\Payment::where('recipient_user_id', $pro->id)->where('status', 'succeeded')->sum('amount_cents')) / 100;
							$pending_payout = (int) (\App\Models\Payment::where('recipient_user_id', $pro->id)->where('type','payout')->where('status','pending')->sum('amount_cents')) / 100;
							$last_payout = \App\Models\Payment::where('recipient_user_id', $pro->id)->where('type','payout')->latest('created_at')->first();
							$rating_avg = $pro->ratings_avg ?? \App\Models\AppointmentRating::where('professional_id', $pro->id)->avg('rating');
						} catch (\Throwable $_) {
							// silently ignore DB errors in view
						}
					@endphp

					<div class="d-grid gap-3">
						<x-card title="KPIs" :hover="false" :center="false" height="auto" width="100%" >
							<div class="d-flex flex-column gap-2">
								<div class="d-flex justify-content-between"><div>Sesiones (este mes)</div><div class="fw-bold">{{ $completed_month }}</div></div>
								<div class="d-flex justify-content-between"><div>Citas completadas</div><div class="fw-bold">{{ $completed_total }}</div></div>
								<div class="d-flex justify-content-between"><div>Ingresos (30d)</div><div class="fw-bold">${{ number_format($income_30d, 2) }}</div></div>
								<div class="d-flex justify-content-between"><div>Ingresos totales</div><div class="fw-bold">${{ number_format($income_total, 2) }}</div></div>
								<div class="d-flex justify-content-between"><div>Rating</div><div class="fw-bold">{{ $rating_avg ? number_format($rating_avg, 2) : '-' }}</div></div>
							</div>
							@if(false)
							<div class="mt-2"><a href="#" class="btn btn-outline-secondary btn-sm">Ver detalles</a></div>
							@endif
						</x-card>

						<x-card title="Accesos rápidos" :hover="false" :center="false" height="auto" width="100%" >
							<div class="d-grid gap-2">
								{{-- <a href="#" class="btn btn-outline-primary btn-sm">Nuevo cupón</a>
								<a href="#" class="btn btn-outline-secondary btn-sm">Exportar facturas</a> --}}
								<a href="{{ route('professional.appointments.history') }}" class="btn btn-outline-success btn-sm">Historial de citas</a>
								<a href="{{ route('professional.payments.history') }}" class="btn btn-outline-primary btn-sm">Pagos y retiros</a>
							</div>
						</x-card>

						<x-card title="Pagos" :hover="false" :center="false" height="auto" width="100%" >
							<div class="d-flex justify-content-between align-items-center">
								<div>Pendiente por confirmar</div>
								<div class="fw-bold text-warning">${{ number_format($pending_payout, 2) }}</div>
							</div>
							<div class="d-flex justify-content-between align-items-center mt-2 small text-muted">
								<div>Último payout</div>
								<div>{{ $last_payout?->created_at?->format('d/m/Y') ?? '—' }}</div>
							</div>
							<div class="mt-3">
								<a href="{{ route('professional.payments.history') }}" class="btn btn-sm btn-outline-primary w-100">Gestionar pagos</a>
							</div>
						</x-card>
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

@push('scripts')
	@include('partials.appt-join-poller')
@endpush
