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
					@if(isset($pendingRatings) && $pendingRatings->count() > 0)
					<div class="card p-3 mb-3" id="pg-rating-pending-wrapper">
						<h5 class="mb-2">Califica tus últimas citas</h5>
						@foreach($pendingRatings as $appt)
						@php
						 $prof = $appt->professional; $profName = ($prof?->name . ' ' . ($prof?->lastname ?? '')) ?? 'Profesional';
						 $endHuman = $appt->end?->format('d/m/Y H:i');
						@endphp
						<div class="border rounded p-2 mb-2 rating-item" data-appt-id="{{ $appt->id }}">
							<div class="d-flex justify-content-between align-items-center">
								<div class="me-3">
									<div class="fw-semibold">{{ $profName }}</div>
									<div class="small text-muted">Finalizada: {{ $endHuman }}</div>
								</div>
								<div class="rating-stars" data-selected="0">
									@for($i=1;$i<=5;$i++)
										<button type="button" class="btn btn-link p-0 m-0 text-warning rating-star" data-score="{{ $i }}" aria-label="{{ $i }} estrellas"><i class="bi bi-star"></i></button>
									@endfor
								</div>
							</div>
							<div class="mt-2">
								<textarea class="form-control form-control-sm rating-comment" rows="2" maxlength="1000" placeholder="Comentario (requerido, máx 1000 caracteres)" required></textarea>
							</div>
							<div class="mt-2 d-flex justify-content-end gap-2">
								<button type="button" class="btn btn-sm btn-outline-secondary rating-skip" data-action="skip">Omitir ahora</button>
								<button type="button" class="btn btn-sm btn-primary rating-submit" data-action="submit" disabled>Enviar</button>
							</div>
						</div>
						@endforeach
						<div class="small text-muted">Dispones de {{ config('appointments.rating_window_days') }} días para calificar después de cada cita completada.</div>
					</div>
					@endif
				<div class="card card-compact p-3 mb-3">
					@php $hasAppt = isset($nextAppt) && $nextAppt; @endphp
					@if($hasAppt)
						@php
							$apptTitle = $nextAppt->title ?: ($nextAppt->professional?->name ? 'Cita con '.$nextAppt->professional->name : 'Cita');
							$startStr = $nextAppt->start ? $nextAppt->start->format('d/m/Y H:i') : null;
							$endStr = $nextAppt->end ? $nextAppt->end->format('H:i') : null;
							$notes = trim((string)($nextAppt->notes ?? ''));
						@endphp
						<div class="d-flex justify-content-between align-items-start" id="pg-next-appt"
							data-appt-id="{{ $nextAppt->id }}"
							data-professional-id="{{ $nextAppt->professional_id }}"
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
							<div class="me-3">
								<small class="text-muted">PRÓXIMA CITA</small>
								<div class="fw-bold mt-1">{{ $apptTitle }}</div>
								<div>Con: <strong>{{ $nextAppt->professional?->name ?? '—' }}</strong></div>
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
							</div>
							<div class="text-end">
								<button type="button" class="btn btn-outline-secondary btn-sm" data-appt-action="details">Ver detalles</button>
								<button type="button" class="btn btn-success btn-sm" data-appt-action="join">Iniciar / Acceder</button>
								<button type="button" class="btn btn-outline-primary btn-sm" data-appt-action="reschedule">Reprogramar</button>
							</div>
						</div>
					@else
						<div class="d-flex justify-content-between align-items-center">
							<div>
								<small class="text-muted">PRÓXIMA CITA</small>
								<div class="fw-bold">--</div>
								<div>Con: <strong>--</strong></div>
							</div>
							<div>
								<a href="{{ route('appointments.index') }}" class="btn btn-outline-secondary btn-sm">Abrir calendario</a>
							</div>
						</div>
					@endif
				</div>

				<x-card title="Mensajes recientes" titleRight="-- Más recientes" :hover="false" :center="false" class="p-0" height="auto" width="100%">
					<div class="list-group list-group-flush" id="pg-user-messages-list">
						<!-- JS rellenará aquí hasta 3 conversaciones -->
						<div class="list-group-item small text-muted">Cargando conversaciones recientes…</div>
					</div>
				</x-card>
		</div>

@push('scripts')
	@include('partials.appt-join-poller')
<script>
;(function(){
	// Populate recent conversations for the user area (graceful fallback)
	function escapeHtml(s){ return String(s||'').replace(/[&<>"'`]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;', '`':'&#96;'}[c]; }); }
	const container = document.getElementById('pg-user-messages-list');
	if (!container) return;
	(async function(){
		const endpoints = ['/messages/recent','/conversations/recent','/api/conversations/recent','/api/messages/recent'];
		let data = null;
		for (const ep of endpoints){
			try{
				const res = await fetch(ep, { headers: { 'Accept':'application/json' } });
				if (!res.ok) continue;
				const json = await res.json();
				// normalize array
				if (Array.isArray(json)) data = json;
				else if (Array.isArray(json.data)) data = json.data;
				else if (Array.isArray(json.conversations)) data = json.conversations;
				else continue;
				break;
			}catch(e){ continue; }
		}
		container.innerHTML = '';
		if (!data || data.length === 0){
			container.innerHTML = '<div class="list-group-item small text-muted">No tienes conversaciones recientes.</div>';
			return;
		}
		const slice = data.slice(0,3);
		for (const conv of slice){
			const title = conv.title || conv.with || conv.other_name || conv.participant_name || 'Conversación';
			const preview = conv.last_message || conv.preview || conv.snippet || '';
			const time = conv.time_ago || conv.human_time || '';
			const url = conv.url || conv.link || (`/messages/${encodeURIComponent(conv.id||'')}`);
			const a = document.createElement('a');
			a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-start';
			a.href = url || '#';
			a.innerHTML = `<div><div class="fw-semibold">${escapeHtml(title)}</div><div class="small text-muted">${escapeHtml(preview)}</div></div><div class="small text-muted">${escapeHtml(time)}</div>`;
			container.appendChild(a);
		}
	})();
})();
</script>
@endpush

@else
<div class="container mt-5">
	<h1>No estás autenticado</h1>
	<p>Por favor, <a href="/welcome">inicia sesión</a> para acceder a tu área de usuario.</p>
</div>
@endauth

@endsection
