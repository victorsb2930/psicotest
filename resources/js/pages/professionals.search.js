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
	const photo = p.photo || '/images/default-avatar.png';
	const speciality = p.speciality || 'General';
	const hasRatings = (p.ratings_count || 0) > 0;
	const avg = hasRatings ? (Number(p.ratings_avg) || 0) : null;
	const rating = hasRatings ? `<button type="button" class="btn btn-sm btn-outline-success ms-1 btn-show-ratings" data-id="${escapeHtml(String(p.id))}" aria-label="Ver reseñas">${avg.toFixed(1)}★</button>` : '<span class="badge bg-secondary ms-1">Sin reseñas</span>';
	const location = p.location || 'No especificada';

	// Markup mirrors our Blade Card classes (card, card-compact, card-anim-lift)
	return `
		<div class="col-md-6 col-lg-4">
			<div class="card h-100 shadow-brand mx-auto card-anim-lift overflow-hidden">
				<div class="card-body card-compact">
					<div class="d-flex">
						<div class="me-3" style="width:72px;flex:0 0 72px;">
							<img src="${escapeHtml(photo)}" class="rounded pf-thumb" style="width:72px;height:72px;object-fit:cover;cursor:pointer;" data-photo-src="${escapeHtml(photo)}">
						</div>
						<div class="flex-grow-1">
							<div class="d-flex justify-content-between align-items-start flex-wrap">
								<div class="pe-2" style="min-width:140px;">
									<h5 class="mb-1 card-title fw-bold text-primary">${escapeHtml(p.name + ' ' + p.lastname || 'Profesional')}</h5>
									<div class="card-text text-muted small">${escapeHtml(p.email || '')}</div>
								</div>
								<div class="pf-rating-holder">${rating}</div>
							</div>
							<div class="mt-2 small text-muted">Especialidad: <strong>${escapeHtml(speciality)}</strong></div>
							<div class="mt-1 small">Ubicación: <strong>${escapeHtml(location)}</strong></div>
							<div class="mt-3">
								<a href="/professional/profile/${encodeURIComponent(p.id)}" class="btn btn-sm btn-outline-primary">Perfil</a>
								<button data-id="${escapeHtml(String(p.id))}"
										data-name="${escapeHtml(p.name || '')}"
										data-title="${escapeHtml(speciality)}"
										class="btn btn-sm btn-primary ms-2 btn-request">Solicitar cita</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>`;
}

export default function init() {
	const $q = document.getElementById('pf_q');
	const $spec = document.getElementById('pf_speciality');
	const $btn = document.getElementById('pf_search');
	const $results = document.getElementById('pf_results');
	const $empty = document.getElementById('pf_empty');

	async function doSearch() {
		ensureCardHelperStyles();
		const params = {
			q: $q?.value || '',
			speciality: $spec?.value || ''
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

			// wire request buttons: use requestAppointmentFlow if available, otherwise redirect to /appointments
			Array.from(document.querySelectorAll('.btn-request')).forEach(b => {
				b.addEventListener('click', () => {
					const id = b.getAttribute('data-id');
					let profName = b.getAttribute('data-name') || '';
					let profTitle = b.getAttribute('data-title') || '';
					// fallback DOM extraction
					if (!profName) {
						try { const h5 = b.closest('.card')?.querySelector('h5'); if (h5) profName = h5.textContent.trim(); } catch(_){ }
					}
					if (!profTitle) {
						try { const spec = b.closest('.card')?.querySelector('.mt-2.small strong'); if (spec) profTitle = spec.textContent.trim(); } catch(_){ }
					}
					const params = new URLSearchParams({ professional_id: id || '', professional_name: profName || '', professional_title: profTitle || '' });
					if (typeof window.requestAppointmentFlow === 'function') {
						try { window.requestAppointmentFlow(id || '', profName || '', profTitle || ''); return; } catch (e) { /* fallback to redirect below */ }
					}
					// Calendar route: asumimos /appointments (según blades y rutas)
					window.location.href = '/appointments?' + params.toString();
				});
			});

			// wire ratings buttons
			Array.from(document.querySelectorAll('.btn-show-ratings')).forEach(b => {
				b.addEventListener('click', async () => {
					const id = b.getAttribute('data-id');
					if (!id) return;
					await showRatingsModal(id, b);
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

async function showRatingsModal(profId, btn){
	try {
		// create modal shell if needed
		if (!document.getElementById('profRatingsModal')) {
			document.body.insertAdjacentHTML('beforeend', `
				<div class="modal fade" id="profRatingsModal" tabindex="-1" aria-hidden="true">
					<div class="modal-dialog modal-dialog-centered modal-lg">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title">Calificaciones</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body">
								<div id="profRatingsModalContent" class="py-2"></div>
							</div>
						</div>
					</div>
				</div>`);
		}
		const modalEl = document.getElementById('profRatingsModal');
		const contentEl = document.getElementById('profRatingsModalContent');
		if (!modalEl || !contentEl) return;
		contentEl.innerHTML = '<div class="text-center text-muted py-3">Cargando...</div>';
		// Fetch public ratings list
		const url = `/professionals/${encodeURIComponent(profId)}/ratings/public`;
		let data = null;
		try {
			const res = await fetch(url, { headers: { 'Accept':'application/json' } });
			if (!res.ok) throw new Error(await res.text());
			data = await res.json();
		} catch (e) {
			contentEl.innerHTML = '<div class="text-danger">Error al cargar reseñas.</div>';
			new bootstrap.Modal(modalEl).show();
			return;
		}
		const items = Array.isArray(data.items) ? data.items : [];
		const avg = (data.ratings_avg || 0).toFixed(2);
		const count = data.ratings_count || 0;
		let headerHtml = `<div class="d-flex justify-content-between align-items-center mb-2">
				<div><strong>Promedio:</strong> ${avg} ⭐ (${count} reseña${count===1?'':'s'})</div>
				<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
			</div>`;
			if (count === 0) {
				contentEl.innerHTML = '<div class="text-muted">Sin calificaciones públicas aún.</div>';
			} else {
			const listHtml = items.map(r => renderRatingRow(r)).join('');
			contentEl.innerHTML = headerHtml + `<div class="list-group">${listHtml}</div>`;
		}
		new bootstrap.Modal(modalEl).show();
	} catch (e) {
		console.error(e);
	}
}

function renderRatingRow(r){
	const stars = buildStars(r.score);
	const date = r.created_at ? new Date(r.created_at).toLocaleDateString() : '';
	const comment = (r.comment || '').trim() !== '' ? escapeHtml(r.comment) : '<span class="text-muted">(Sin comentario)</span>';
	const response = (r.response || '').trim() !== '' ? `<div class="small mt-1"><strong>Respuesta:</strong> ${escapeHtml(r.response)}</div>` : '';
	return `<div class="list-group-item">
		<div class="d-flex justify-content-between align-items-center">
			<div>${stars}</div>
			<div class="small text-muted">${date}</div>
		</div>
		<div class="mt-1">${comment}</div>
		${response}
	</div>`;
}

function buildStars(n){
	n = Number(n) || 0; const out=[]; for(let i=1;i<=5;i++){ out.push(`<i class="bi ${i<=n?'bi-star-fill text-warning':'bi-star text-secondary'}"></i>`); } return out.join('');
}
