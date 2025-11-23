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

@push('scripts')
<script>
window.__currentUserTz = @json(optional(auth()->user())->timezone ?? null);
</script>
<script>
;(function(){
	const container = document.getElementById('pg-next-appt');
	if (!container) return;
	const apptId = container.getAttribute('data-appt-id');
	const startIso = container.getAttribute('data-start');
	if (!apptId || !startIso) return;

	const joinBtn = container.querySelector('[data-appt-action="join"]');
	if (!joinBtn) return;

	function disableBtn() {
		try { joinBtn.disabled = true; joinBtn.classList.add('disabled'); joinBtn.setAttribute('aria-disabled','true'); } catch(e){}
	}
	function enableBtn() {
		try { joinBtn.disabled = false; joinBtn.classList.remove('disabled'); joinBtn.removeAttribute('aria-disabled'); } catch(e){}
	}
	disableBtn();

	function getLocalDateParts(date, tz) {
		try {
			const opts = { timeZone: tz || undefined, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' };
			const fmt = new Intl.DateTimeFormat(undefined, opts);
			const parts = fmt.formatToParts(date);
			const obj = {};
			for (const p of parts) {
				if (p.type && p.type !== 'literal') obj[p.type] = p.value;
			}
			return {
				year: parseInt(obj.year,10),
				month: parseInt(obj.month,10),
				day: parseInt(obj.day,10),
				hour: parseInt(obj.hour,10),
				minute: parseInt(obj.minute,10),
				second: parseInt(obj.second,10)
			};
		} catch (e) {
			return { year: date.getFullYear(), month: date.getMonth()+1, day: date.getDate(), hour: date.getHours(), minute: date.getMinutes(), second: date.getSeconds() };
		}
	}

	function isSameLocalDay(d1, d2) {
		const tz = (typeof window !== 'undefined' && window.__currentUserTz) ? window.__currentUserTz : undefined;
		const a = getLocalDateParts(d1, tz);
		const b = getLocalDateParts(d2, tz);
		return a.year === b.year && a.month === b.month && a.day === b.day;
	}

	async function fetchMeta() {
		try {
			const res = await fetch(`/appointments/${encodeURIComponent(apptId)}/meta`, { headers: { 'Accept':'application/json' } });
			if (!res.ok) return null;
			return await res.json();
		} catch (e) { return null; }
	}

	// Configuration: start polling this many minutes before appointment
	const startPollingBeforeMinutes = 30; // configurable (C)

	// Polling loop (every 10s) to detect acceptance and 5-min window
	let pollHandle = null;
	let statusTimer = null;
	let countdownInterval = null;

	// Ensure a status node next to the join button
	let statusNode = container.querySelector('.appt-join-status');
	if (!statusNode) {
		statusNode = document.createElement('div');
		statusNode.className = 'appt-join-status small text-muted mb-1';
		joinBtn.parentNode.insertBefore(statusNode, joinBtn);
	}

	function setStatus(text) {
		try { statusNode.textContent = text; } catch (e) {}
	}

	function startCountdown(targetTs) {
		if (countdownInterval) clearInterval(countdownInterval);
		countdownInterval = setInterval(function(){
			const now = Date.now();
			const diff = Math.max(0, targetTs - now);
			const mins = Math.floor(diff / 60000);
			const secs = Math.floor((diff % 60000) / 1000);
			setStatus(`Esperando aceptación — habilitará en ${mins}m ${secs}s`);
			if (diff <= 0) { clearInterval(countdownInterval); countdownInterval = null; }
		}, 1000);
	}

	async function startPolling() {
		if (pollHandle) return;
		const doPoll = async () => {
			const meta = await fetchMeta();
			if (!meta || !meta.ok) return;
			const status = (meta.status || '').toLowerCase();
			const start = meta.start ? new Date(meta.start) : null;
			if (!start) return;
			const now = new Date();
			const msToStart = start.getTime() - now.getTime();
			// enable only if accepted and within 5 minutes (>=0 and <=5min)
			if (status === 'accepted' && msToStart >= 0 && msToStart <= (5 * 60 * 1000)) {
				enableBtn();
				setStatus('Listo — puedes iniciar la cita');
				stopPolling();
				return;
			}

			// Update status and countdown when within polling window
			const targetEnableTs = start.getTime() - (5 * 60 * 1000);
			const startPollingTs = start.getTime() - (startPollingBeforeMinutes * 60 * 1000);
			if (now.getTime() >= startPollingTs && now.getTime() < targetEnableTs) {
				// within polling window but before enable window
				const diff = targetEnableTs - now.getTime();
				startCountdown(targetEnableTs);
			} else if (now.getTime() < startPollingTs) {
				// not yet polling window
				const mins = Math.ceil((startPollingTs - now.getTime())/60000);
				setStatus(`Comprobaciones empezarán en ${mins} min`);
			}

			// stop polling if appointment day passed
			if (now.getTime() > start.getTime() + (60 * 60 * 1000)) { // 1h after start stop
				stopPolling();
			}
		};
		await doPoll();
		pollHandle = setInterval(doPoll, 10000);
	}
	function stopPolling(){ if (pollHandle) { clearInterval(pollHandle); pollHandle = null; } }

	// Daily check: if not appointment day, schedule next daily check at appointment-day start
	(async function scheduleChecks(){
		const meta = await fetchMeta();
		if (!meta || !meta.ok || !meta.start) return;
		const start = new Date(meta.start);
		const now = new Date();
		if (!isSameLocalDay(now, start)) {
			// Not the appointment day yet: check once per day until it is
			const dailyCheckMs = 24 * 60 * 60 * 1000;
			const intervalId = setInterval(async function(){
				const m = await fetchMeta();
				if (!m || !m.ok || !m.start) return;
				const s = new Date(m.start);
				if (isSameLocalDay(new Date(), s)) {
					clearInterval(intervalId);
					schedulePollingOnDay();
				}
			}, dailyCheckMs);
			return;
		}
		// If same day already, schedule polling logic
		schedulePollingOnDay();
	})();

	function schedulePollingOnDay(){
		// start polling `startPollingBeforeMinutes` before appointment local start
		(async function(){
			const meta = await fetchMeta();
			if (!meta || !meta.ok || !meta.start) return;
			const start = new Date(meta.start);
			const now = new Date();
			const msToStartMinus = start.getTime() - (startPollingBeforeMinutes*60*1000) - now.getTime();
			if (msToStartMinus <= 0) {
				// within polling window already: start polling now
				startPolling();
			} else {
				// schedule to start polling at that time
				setTimeout(startPolling, msToStartMinus);
				setStatus(`Comprobaciones empezarán en ${Math.ceil(msToStartMinus/60000)} min`);
			}
		})();
	}
})();
</script>
@endpush
