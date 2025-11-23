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

					<div class="card p-3 mt-3">
						<small class="text-muted">Accesos rápidos</small>
						<div class="d-grid gap-2 mt-2">
							<a href="#" class="btn btn-outline-primary btn-sm">Nuevo cupón</a>
							<a href="#" class="btn btn-outline-secondary btn-sm">Exportar facturas</a>
							<a href="{{ route('professional.appointments.history') }}" class="btn btn-outline-success btn-sm">Historial de citas</a>
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

	// Configuration: minutes before start to begin polling
	const startPollingBeforeMinutes = 30; // C: increased window

	let pollHandle = null;
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

			// Enable only if professional accepted and within 5 minutes
			if (status === 'accepted' && msToStart >= 0 && msToStart <= (5 * 60 * 1000)) {
				enableBtn();
				setStatus('Listo — puedes iniciar la cita');
				stopPolling();
				return;
			}

			// Manage status text and countdown
			const targetEnableTs = start.getTime() - (5 * 60 * 1000);
			const startPollingTs = start.getTime() - (startPollingBeforeMinutes * 60 * 1000);
			if (now.getTime() >= startPollingTs && now.getTime() < targetEnableTs) {
				startCountdown(targetEnableTs);
			} else if (now.getTime() < startPollingTs) {
				const mins = Math.ceil((startPollingTs - now.getTime())/60000);
				setStatus(`Comprobaciones empezarán en ${mins} min`);
			}

			if (now.getTime() > start.getTime() + (60 * 60 * 1000)) { stopPolling(); }
		};
		await doPoll();
		pollHandle = setInterval(doPoll, 10000);
	}
	function stopPolling(){ if (pollHandle) { clearInterval(pollHandle); pollHandle = null; } if (countdownInterval) { clearInterval(countdownInterval); countdownInterval = null; } }

	(async function scheduleChecks(){
		const meta = await fetchMeta();
		if (!meta || !meta.ok || !meta.start) return;
		const start = new Date(meta.start);
		const now = new Date();
		if (!isSameLocalDay(now, start)) {
			// check once per day until appointment day
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
		schedulePollingOnDay();
	})();

	function schedulePollingOnDay(){
		(async function(){
			const meta = await fetchMeta();
			if (!meta || !meta.ok || !meta.start) return;
			const start = new Date(meta.start);
			const now = new Date();
			const msToStartMinus = start.getTime() - (startPollingBeforeMinutes*60*1000) - now.getTime();
			if (msToStartMinus <= 0) {
				startPolling();
			} else {
				setStatus(`Comprobaciones empezarán en ${Math.ceil(msToStartMinus/60000)} min`);
				setTimeout(startPolling, msToStartMinus);
			}
		})();
	}
})();
</script>
@endpush
