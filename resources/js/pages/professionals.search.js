// Consume JSON from /professionals/search and render cards client-side

function ensureCardHelperStyles() {
	try {
		if (document.getElementById('card-component-helpers')) return;
		const css = `
				/* Injected: card helpers to match Blade component */
				.card-compact { padding: 1rem !important; }
				.card-anim-lift { transition: transform 0.2s ease, box-shadow 0.2s ease; will-change: transform; }
				.card-anim-lift:hover { transform: translateY(-6px); box-shadow: 0 10px 20px rgba(245,129,152,.35) !important; }
				.card-square-md { width: 100%; }
				.card-square-md .card-body { display: flex; flex-direction: column; justify-content: center; align-items: center; }
				@media (min-width: 768px) { .card-square-md { width: 260px; aspect-ratio: 1 / 1; } }
				@media (prefers-reduced-motion: reduce) { .card-anim-lift { transition: none; } .card-anim-lift:hover { transform: none; } }
				`;
		const style = document.createElement('style');
		style.id = 'card-component-helpers';
		style.textContent = css;
		document.head.appendChild(style);
	} catch (_) { }
}

function escapeHtml(s) {
	try { if (window.escapeHtml) return window.escapeHtml(s); } catch (_) { }
	const el = document.createElement('div'); el.innerText = s || ''; return el.innerHTML;
}

function renderCard(p) {
	console.log(p);
	const photo = p.photo || '/images/default-avatar.png';
	const speciality = p.speciality || 'General';
	const rating = (p.rating !== null && p.rating !== undefined)
		? `<span class="badge bg-success">${(typeof p.rating === 'number' && p.rating.toFixed) ? p.rating.toFixed(1) : escapeHtml(String(p.rating))}</span>`
		: '';
	const typesArr = Array.isArray(p.appointment_types)
		? p.appointment_types
		: (p.appointment_types ? String(p.appointment_types).split(',').map(s => s.trim()).filter(Boolean) : []);
	const types = typesArr.length ? typesArr.join(', ') : 'Presencial / Virtual';
	const location = p.location || 'No especificada';

	// Markup mirrors our Blade Card classes (card, card-compact, card-anim-lift)
	return `
		<div class="col-md-6 col-lg-4">
			<div class="card h-100 shadow-brand mx-auto card-anim-lift">
				<div class="card-body card-compact d-flex">
					<div class="me-3" style="width:72px;flex:0 0 72px;">
						<img src="${escapeHtml(photo)}" class="rounded pf-thumb" style="width:72px;height:72px;object-fit:cover;cursor:pointer;" data-photo-src="${escapeHtml(photo)}">
					</div>
					<div class="flex-grow-1">
						<div class="d-flex justify-content-between align-items-start">
							<div>
								<h5 class="mb-1 card-title fw-bold text-primary">${escapeHtml(p.name + ' ' + p.lastname || 'Profesional')}</h5>
								<div class="card-text text-muted small">${escapeHtml(p.email || '')}</div>
							</div>
							<div>${rating}</div>
						</div>
						<div class="mt-2 small text-muted">Especialidad: <strong>${escapeHtml(speciality)}</strong></div>
						<div class="mt-1 small">Tipo: <strong>${escapeHtml(types)}</strong></div>
						<div class="mt-1 small">Ubicación: <strong>${escapeHtml(location)}</strong></div>
						<div class="mt-3">
							<a href="/professional/profile/${encodeURIComponent(p.id)}" class="btn btn-sm btn-outline-primary">Ver perfil</a>
							<button data-id="${escapeHtml(String(p.id))}"
											data-types='${JSON.stringify(typesArr)}'
											data-name="${escapeHtml(p.name || '')}"
											data-title="${escapeHtml(speciality)}"
											class="btn btn-sm btn-primary ms-2 btn-request">Solicitar cita</button>
						</div>
					</div>
				</div>
			</div>
		</div>`;
}

export default function init() {
	const $q = document.getElementById('pf_q');
	const $spec = document.getElementById('pf_speciality');
	const $type = document.getElementById('pf_type');
	const $btn = document.getElementById('pf_search');
	const $results = document.getElementById('pf_results');
	const $empty = document.getElementById('pf_empty');

	async function doSearch() {
		ensureCardHelperStyles();
		const params = {
			q: $q?.value || '',
			speciality: $spec?.value || '',
			type: $type?.value || ''
		};
		$results.innerHTML = '<div class="col-12 text-center py-5">Buscando...</div>';
		try {
			const url = document.querySelector('meta[name="professionals-search-url"]')?.getAttribute('content') || '/professionals/search';
			const res = await axios.get(url, { params });
			const data = res?.data || [];
			if (!Array.isArray(data) || data.length === 0) {
				$results.innerHTML = '';
				$empty.classList.remove('d-none');
				return;
			}
			$empty.classList.add('d-none');
			$results.innerHTML = data.map(renderCard).join('');

			// inject modal container for image preview if missing
			if (!document.getElementById('pfImagePreviewModal')) {
				const modalHtml = `
					<div class="modal fade" id="pfImagePreviewModal" tabindex="-1" aria-hidden="true">
						<div class="modal-dialog modal-dialog-centered modal-lg">
							<div class="modal-content">
								<div class="modal-body text-center p-0">
									<img id="pfImagePreviewModalImg" src="" style="width:100%; height:auto;" alt="preview">
								</div>
							</div>
						</div>
					</div>`;
				document.body.insertAdjacentHTML('beforeend', modalHtml);
			}

			// wire thumbs to open modal preview
			Array.from(document.querySelectorAll('.pf-thumb')).forEach(img => {
				img.addEventListener('click', () => {
					const src = img.getAttribute('data-photo-src') || img.src;
					const modalImg = document.getElementById('pfImagePreviewModalImg');
					if (modalImg) modalImg.src = src;
					const modalEl = document.getElementById('pfImagePreviewModal');
					if (modalEl) new bootstrap.Modal(modalEl).show();
				});
			});

			// wire request buttons to open shared appointment modal if available
			Array.from(document.querySelectorAll('.btn-request')).forEach(b => {
				b.addEventListener('click', async () => {
					const id = b.getAttribute('data-id');
					let types = null;
					try {
						const typesStr = b.getAttribute('data-types');
						types = typesStr ? JSON.parse(typesStr) : null;
					} catch (_) { types = null; }

					let profName = b.getAttribute('data-name') || null;
					let profTitle = b.getAttribute('data-title') || null;
					// Fallback: try to read visible card content if attributes missing
					if (!profName) {
						try {
							const card = b.closest && b.closest('.card');
							if (card) {
								const h5 = card.querySelector('h5');
								if (h5 && h5.textContent) profName = h5.textContent.trim();
							}
						} catch (_) { profName = profName || null; }
					}
					if (!profTitle) {
						try {
							const card = b.closest && b.closest('.card');
							if (card) {
								const spec = card.querySelector('.mt-2.small strong');
								if (spec && spec.textContent) profTitle = spec.textContent.trim();
							}
						} catch (_) { profTitle = profTitle || null; }
					}

					const openModal = async () => {
						try {
							if (typeof window.openAppointmentModal === 'function') {
								window.openAppointmentModal({ mode: 'patient', defaults: { professional_id: id, professional_name: profName, professional_title: profTitle }, types: types, urls: {}, calendar: null });
								return true;
							}
						} catch (_) { }
						return false;
					};

					// Try existing global first
					if (await openModal()) return;

					// Try dynamic import of the utility (lazy-load) and retry
					try {
						const mod = await import('../utils/appointmentModal');
						const fn = mod.default || mod.openAppointmentModal || (mod && mod.openAppointmentModal ? mod.openAppointmentModal : null);
						if (typeof fn === 'function') {
							try { window.openAppointmentModal = fn; } catch (_) { }
							fn({ mode: 'patient', defaults: { professional_id: id, professional_name: profName, professional_title: profTitle }, types: types, urls: {}, calendar: null });
							return;
						}
					} catch (_) {
						// ignore import failure and fallthrough to fallback
					}

					// fallback simple prompt
					window.modalNotification?.('Función no disponible', 'No se puede solicitar desde aquí', { template: 'warning' });
				});
			});

		} catch (e) {
			$results.innerHTML = '<div class="col-12 text-danger">Error al buscar</div>';
		}
	}

	$btn && $btn.addEventListener('click', doSearch);
	// quick enter submit on inputs
	[$q, $spec].forEach(el => { if (!el) return; el.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); doSearch(); } }); });

	// initial load
	doSearch();
}
