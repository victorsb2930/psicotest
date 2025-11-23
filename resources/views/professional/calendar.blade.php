@extends('layout')

@section('title','Calendario')
@section('page', 'professional-calendar')
@section('content')
<div class="container py-4">
	<div class="d-flex align-items-center mb-3">
	<h1 class="me-3">Calendario</h1>
	<button class="btn btn-sm btn-primary" id="newAppointmentBtn">Crear nueva cita</button>
	</div>
	<div class="mb-3 form-check">
		<input type="checkbox" class="form-check-input" id="hideCancelledChk">
		<label for="hideCancelledChk" class="form-check-label">Ocultar canceladas</label>
	</div>
	<div id="calendar" style="max-width: 1100px; margin: 0 auto;"></div>
</div>

<!-- Provide endpoints and user timezone to the page module via meta tags so the JS module can work with PJAX -->
<meta name="professional-events-url" content="{{ route('professional.calendar.events') }}">
<meta name="professional-patients-url" content="{{ route('professional.calendar.patients') }}">
<meta name="professional-create-url" content="{{ route('professional.calendar.events.store') }}">
<script>
	window.__currentUserTz = @json(optional(auth()->user())->timezone ?? null);
</script>

<script>
// requestAppointmentFlow: checks user appointment credits and offers to purchase a single appointment credit
window.requestAppointmentFlow = async function(professionalId, professionalName, professionalTitle) {
	try {
		// Try to get current credits via a lightweight endpoint
		let credits = null;
		try {
			const res = await fetch('/user/appointment-credits', { headers: { 'Accept': 'application/json' } });
			if (res.ok) {
				const j = await res.json();
				if (j && typeof j.credits !== 'undefined') credits = parseInt(j.credits, 10) || 0;
			}
		} catch (e) { /* ignore */ }

		const openRedirectToAppointments = (profId, profName, profTitle) => {
			const params = new URLSearchParams({ professional_id: profId || '', professional_name: profName || '', professional_title: profTitle || '' });
			window.location.href = '/appointments?' + params.toString();
		};

		if (credits !== null) {
			if (credits > 0) {
				// user has credits, go to appointment creation
				return openRedirectToAppointments(professionalId, professionalName, professionalTitle);
			}
		}

		// No credits info or zero credits -> show modal to purchase a single appointment credit
		const modalId = 'buyAppointmentModal';
		// Remove existing if present
		const existing = document.getElementById(modalId); if (existing) existing.remove();
		const modalHtml = `
			<div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title">Comprar cita</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
						</div>
						<div class="modal-body">
							<p class="mb-2">Para solicitar una cita con <strong>${professionalName || 'este profesional'}</strong> necesitas créditos de cita. Puedes comprar una cita ahora y proceder con la solicitud.</p>
							<p class="small text-muted">La plataforma cobrará la cita y se encargará de pagar al profesional según nuestros términos.</p>
							<div id="buyAppointmentError" class="text-danger small d-none"></div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
							<button type="button" id="confirmBuyAppointment" class="btn btn-primary">Comprar cita</button>
						</div>
					</div>
				</div>
			</div>`;
		document.body.insertAdjacentHTML('beforeend', modalHtml);
		const modalEl = document.getElementById(modalId);
		const bsModal = new bootstrap.Modal(modalEl);
		bsModal.show();

		const confirmBtn = modalEl.querySelector('#confirmBuyAppointment');
		const errBox = modalEl.querySelector('#buyAppointmentError');
		confirmBtn.addEventListener('click', async function () {
			try {
				confirmBtn.disabled = true;
				errBox.classList.add('d-none'); errBox.textContent = '';
				// Call server to purchase a single appointment credit. Backend should charge and return success.
				const resp = await fetch('/billing/purchase-appointment', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '', 'X-Requested-With': 'XMLHttpRequest' },
					body: JSON.stringify({ professional_id: professionalId || null })
				});
				if (!resp.ok) {
					let j = null;
					try { j = await resp.json(); } catch (e) { }
					const msg = j?.message || j?.error || 'No se pudo completar la compra';
					errBox.classList.remove('d-none'); errBox.textContent = String(msg);
					confirmBtn.disabled = false;
					return;
				}
				// success: proceed to appointments creation
				try { bsModal.hide(); } catch (e) { }
				openRedirectToAppointments(professionalId, professionalName, professionalTitle);
			} catch (err) {
				errBox.classList.remove('d-none'); errBox.textContent = 'Error de red. Intenta de nuevo.';
				confirmBtn.disabled = false;
			}
		});

		// if the server endpoints are not implemented, provide a fallback link to plans
		modalEl.addEventListener('hidden.bs.modal', function () {
			try { modalEl.remove(); } catch (e) { }
		});
	} catch (e) {
		// fallback: redirect to plans
		window.location.href = '/plans';
	}
};

// Wire newAppointmentBtn on the calendar page to use the same flow (if present)
(function(){
	const newBtn = document.getElementById('newAppointmentBtn');
	if (!newBtn) return;
	newBtn.addEventListener('click', function () {
		// On calendar page, assume professional booking may be generic; call flow without professional id
		if (typeof window.requestAppointmentFlow === 'function') {
			try { window.requestAppointmentFlow('', '', ''); return; } catch (e) { }
		}
		window.location.href = '/plans';
	});
})();
</script>

@endsection
