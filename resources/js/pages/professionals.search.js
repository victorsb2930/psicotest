// Consume JSON from /professionals/search and render cards client-side

function ensureCardHelperStyles() {
	try {
		if (document.getElementById('card-component-helpers')) return;
		const css = `
			.pf-card { border-radius: 1.5rem; padding: 1.5rem; background: #fff; border: 1px solid rgba(0,0,0,0.03); box-shadow: 0 8px 30px rgba(15,23,42,0.08); transition: transform .2s ease, box-shadow .2s ease; }
			.pf-card:hover { transform: translateY(-6px); box-shadow: 0 18px 35px rgba(15,23,42,0.15); }
			.pf-thumb-btn { border: none; padding: 0; border-radius: 50%; width: 88px; height: 88px; background: transparent; }
			.pf-thumb-btn img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; box-shadow: inset 0 0 0 4px rgba(13,110,253,.15); }
			.pf-pill { font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; }
			.pf-meta { font-size: .9rem; color: #6c757d; }
			.pf-meta strong { color: #212529; }
			.pf-actions { margin-top: auto; }
			.skeleton-card { border-radius: 1.5rem; padding: 1.5rem; background: linear-gradient(120deg, #f8f9fa 25%, #eceff3 50%, #f8f9fa 75%); background-size: 200% 100%; animation: skeleton-loading 1.5s infinite; min-height: 220px; }
			@keyframes skeleton-loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
			@media (prefers-reduced-motion: reduce) { .pf-card, .pf-card:hover { transition: none; transform: none; } .skeleton-card { animation: none; } }
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
	const rating = hasRatings
		? `<button type="button" class="btn btn-sm btn-light border btn-show-ratings" data-id="${escapeHtml(String(p.id))}" aria-label="Ver reseñas"><span class="fw-semibold">${avg.toFixed(1)}★</span> · ${p.ratings_count} reseña${p.ratings_count === 1 ? '' : 's'}</button>`
		: '<span class="badge bg-secondary-subtle text-secondary">Sin reseñas</span>';
	const location = p.location || 'No especificada';
	const name = `${p.name || ''} ${p.lastname || ''}`.trim() || 'Profesional';
	const email = p.email || '';

	return `
		<div class="col">
			<article class="pf-card h-100 d-flex flex-column">
				<div class="d-flex align-items-center gap-3 flex-wrap">
					<button type="button" class="pf-thumb-btn js-pf-thumb" data-photo-src="${escapeHtml(photo)}" aria-label="Ver foto de ${escapeHtml(name)}">
						<img src="${escapeHtml(photo)}" alt="${escapeHtml(name)}">
					</button>
					<div class="flex-grow-1">
						<h5 class="mb-1 fw-semibold text-dark">${escapeHtml(name)}</h5>
						<p class="mb-0 text-muted small text-break">${escapeHtml(email)}</p>
					</div>
					<div class="text-end">${rating}</div>
				</div>
				<div class="mt-3 d-flex flex-column gap-2 pf-meta">
					<div><span class="text-muted">Especialidad:</span> <strong>${escapeHtml(speciality)}</strong></div>
					<div><span class="text-muted">Ubicación:</span> <strong>${escapeHtml(location)}</strong></div>
					<div><span class="text-muted">Tipos de cita:</span> <strong>${escapeHtml(Array.isArray(p.appointment_types) ? p.appointment_types.join(', ') : (p.appointment_types || 'Consulta general'))}</strong></div>
				</div>
				<div class="pf-actions d-flex flex-wrap gap-2 mt-4">
					<a href="/professional/profile/${encodeURIComponent(p.id)}" class="btn btn-outline-primary flex-grow-1">Ver perfil</a>
					<button data-id="${escapeHtml(String(p.id))}"
							data-name="${escapeHtml(p.name || '')}"
							data-title="${escapeHtml(speciality)}"
							class="btn btn-primary flex-grow-1 btn-request">Solicitar cita</button>
				</div>
			</article>
		</div>`;
}

export default function init() {
	const $q = document.getElementById('pf_q');
	const $spec = document.getElementById('pf_speciality');
	const $btn = document.getElementById('pf_search');
	const $results = document.getElementById('pf_results');
	const $empty = document.getElementById('pf_empty');
	const $status = document.getElementById('pf_status');
	const $form = document.getElementById('pf_filters');

	function setStatus(message, show = true) {
		if (!$status) return;
		if (!message || !show) {
			$status.classList.add('d-none');
			$status.textContent = '';
			return;
		}
		$status.textContent = message;
		$status.classList.remove('d-none');
	}

	function renderSkeletons() {
		return ['','',''].map(() => '<div class="col"><div class="skeleton-card"></div></div>').join('');
	}

	function attachCardInteractions() {
		// ensure preview modal exists once
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

		Array.from(document.querySelectorAll('.js-pf-thumb')).forEach(btn => {
			btn.addEventListener('click', () => {
				const src = btn.getAttribute('data-photo-src');
				const modalImg = document.getElementById('pfImagePreviewModalImg');
				if (modalImg && src) modalImg.src = src;
				const modalEl = document.getElementById('pfImagePreviewModal');
				if (modalEl) new bootstrap.Modal(modalEl).show();
			});
		});

		Array.from(document.querySelectorAll('.btn-request')).forEach(b => {
			b.addEventListener('click', () => {
				const id = b.getAttribute('data-id');
				let profName = b.getAttribute('data-name') || '';
				let profTitle = b.getAttribute('data-title') || '';
				if (!profName) {
					try { const h5 = b.closest('.pf-card')?.querySelector('h5'); if (h5) profName = h5.textContent.trim(); } catch (_) { }
				}
				if (!profTitle) {
					try { const spec = b.closest('.pf-card')?.querySelector('.pf-meta strong'); if (spec) profTitle = spec.textContent.trim(); } catch (_) { }
				}
				const params = new URLSearchParams({ professional_id: id || '', professional_name: profName || '', professional_title: profTitle || '' });
				if (typeof window.requestAppointmentFlow === 'function') {
					try { window.requestAppointmentFlow(id || '', profName || '', profTitle || ''); return; } catch (_) { }
				}
				window.location.href = '/appointments?' + params.toString();
			});
		});

		Array.from(document.querySelectorAll('.btn-show-ratings')).forEach(b => {
			b.addEventListener('click', async () => {
				const id = b.getAttribute('data-id');
				if (!id) return;
				await showRatingsModal(id, b);
			});
		});
	}

	async function doSearch() {
		ensureCardHelperStyles();
		const params = {
			q: $q?.value || '',
			speciality: $spec?.value || ''
		};
		$results.setAttribute('aria-busy', 'true');
		$results.innerHTML = renderSkeletons();
		setStatus('Buscando profesionales…', true);
		$empty.classList.add('d-none');
		try {
			const url = document.querySelector('meta[name="professionals-search-url"]')?.getAttribute('content') || '/professionals/search';
			const res = await axios.get(url, { params });
			const data = res?.data || [];
			if (!Array.isArray(data) || data.length === 0) {
				$results.innerHTML = '';
				$empty.classList.remove('d-none');
				setStatus('Sin resultados para la búsqueda aplicada.', true);
				return;
			}
			setStatus('', false);
			$empty.classList.add('d-none');
			$results.innerHTML = data.map(renderCard).join('');
			attachCardInteractions();
		} catch (e) {
			console.error(e);
			$results.innerHTML = '<div class="col"><div class="alert alert-danger">Ocurrió un error al buscar profesionales.</div></div>';
			setStatus('No se pudo completar la búsqueda. Intenta nuevamente.', true);
		} finally {
			$results.setAttribute('aria-busy', 'false');
		}
	}

	const handleEnter = (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); doSearch(); } };
	[$q, $spec].forEach(el => el && el.addEventListener('keydown', handleEnter));
	$btn?.addEventListener('click', (ev) => { ev.preventDefault(); doSearch(); });
	$form?.addEventListener('submit', (ev) => { ev.preventDefault(); doSearch(); });

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
