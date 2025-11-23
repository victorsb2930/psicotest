import { modalConfirm } from '../utils/modalConfirm';

// Plans module: attach handlers for .plan-cta buttons and use modalConfirm for accessible dialogs
const plansMeta = {
	basico: { title: 'Básico', price: '0', desc: 'Acceso gratuito: perfil público, búsqueda limitada y mensajería básica.' },
	profesional: { title: 'Profesional', price: '9.99', desc: 'Listado destacado, calendario avanzado y gestión de pacientes.' },
	premium: { title: 'Premium', price: '19.99', desc: 'Prioridad en búsquedas, analíticas y soporte prioritario.' }
};

// Heuristic footer finder used by multiple places: prefers the .d-flex that contains the small descriptor
function findFooter(container) {
	if (!container) return null;
	const candidates = container.querySelectorAll('.card .d-flex');
	for (let i = 0; i < candidates.length; i++) {
		const el = candidates[i];
		if (el.querySelector('small.text-muted')) return el;
	}
	return candidates.length ? candidates[candidates.length - 1] : (container.querySelector('.card .card-body') || container);
}

/**
 * Show the payment modal for a given plan.
 * Encapsulates the previous inline modal logic so the downgrade flow can show a small confirmation first.
 */
function showPaymentModal({ key, title, desc, priceCents, discountPercent = 0, activePlanKey = null, activePriceCents = 0 }) {
	const modalId = `planPayModal_${key}_${Date.now()}`;
	const priceLabel = priceCents === 0 ? 'Gratis' : `$${(priceCents / 100).toFixed(2)}/mes`;

	const bodyHtml = {
		modalId: modalId,
		title: `${activePlanKey && String(activePlanKey) === String(key) ? 'Extender suscripción' : 'Contratar'} ${title} — ${priceLabel}`,
		body: `
				<div class="mb-2 small text-muted">${desc}</div>
				<div class="mb-3">
					<div class="fw-medium">Precio por mes: <span class="fw-bold">${priceLabel}</span></div>
					<div class="fw-normal small mt-1">Total estimado: <strong id="modalTotal_${modalId}">${priceLabel}</strong></div>
				</div>
				<form id="planPayForm_${key}" class="needs-validation" novalidate>
					<div class="mb-2">
						<label class="form-label small">Duración</label>
						<select name="months" id="planMonths_${modalId}" class="form-select form-select-sm">
							<option value="1">1 mes</option>
							<option value="3">3 meses (mejor precio)</option>
							<option value="12">12 meses (más ahorro)</option>
						</select>
					</div>
					<hr />
					<div class="mb-2">
						<label class="form-label small">Nombre en la tarjeta</label>
						<input type="text" class="form-control form-control-sm" name="card_name" placeholder="Nombre completo" required />
					</div>
					<div class="mb-2">
						<label class="form-label small">Número (simulado)</label>
						<input type="text" class="form-control form-control-sm" name="card_number" placeholder="4242 4242 4242 4242" required />
					</div>
					<div class="d-flex g-2">
						<div class="me-2" style="flex:1">
							<label class="form-label small">Expira</label>
							<input type="text" class="form-control form-control-sm" name="card_exp" placeholder="MM/YY" inputmode="numeric" maxlength="5" title="Formato MM/YY" required />
						</div>
						<div style="width:110px">
							<label class="form-label small">CVC</label>
							<input type="text" class="form-control form-control-sm" name="card_cvc" placeholder="123" required />
						</div>
					</div>
					<div class="form-text small text-muted mt-2">Los datos de tarjeta son simulados y no se almacenan.</div>
				</form>
			`,
		buttons: [
			{ text: 'Cerrar', className: 'btn-outline-secondary', dismiss: true },
			{ text: activePlanKey && String(activePlanKey) === String(key) ? 'Extender' : 'Pagar', className: 'btn-primary', primary: true }
		]
	};

	modalConfirm(bodyHtml, 'normal');

	// After modal created, locate it and attach handlers (use a short wait to allow modalConfirm to insert DOM)
	(async () => {
		const waitFor = (selector, timeout = 2000) => new Promise((resolve) => {
			const start = Date.now();
			const tick = () => {
				const el = document.querySelector(selector);
				if (el) return resolve(el);
				if (Date.now() - start > timeout) return resolve(null);
				requestAnimationFrame(tick);
			};
			tick();
		});

		const modalEl = await waitFor(`#${modalId}`, 2000);
		if (!modalEl) return;
		const $modal = $(modalEl);

		// Elements inside modal
		const sel = modalEl.querySelector(`#planMonths_${modalId}`);
		const totalEl = modalEl.querySelector(`#modalTotal_${modalId}`);
		const discountEl = modalEl.querySelector(`#modalDiscount_${modalId}`);
		const formEl = modalEl.querySelector(`#planPayForm_${key}`);

		const getMonths = () => {
			try { return parseInt(sel?.value || '1', 10) || 1; } catch (e) { return 1; }
		};

		const updateTotal = () => {
			const months = getMonths();
			const gross = (priceCents * months) / 100.0;
			let multiPercent = 0;
			try {
				const col = document.querySelector(`[data-plan-key\x3d"${key}"]`);
				if (col && col.dataset && col.dataset.planMultiDiscounts) {
					const arr = JSON.parse(col.dataset.planMultiDiscounts || '[]');
					if (Array.isArray(arr)) {
						arr.forEach(it => {
							try {
								const th = parseInt(it.months || it.min || 0, 10) || 0;
								const pc = parseFloat(it.percent || it.p || 0) || 0;
								if (months >= th && pc > multiPercent) multiPercent = pc;
							} catch (e) { }
						});
					}
				}
			} catch (e) { }
			const discount = (typeof discountPercent === 'number' ? discountPercent : parseFloat(String(discountPercent) || '0')) || 0;
			const appliedDiscount = Math.max(discount, multiPercent || 0);
			const net = gross * (1 - appliedDiscount / 100.0);
			const formatter = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD', minimumFractionDigits: 2 });
			if (totalEl) totalEl.textContent = formatter.format(net) + (months > 1 ? ` (${months} meses)` : '');
			if (discountEl) discountEl.textContent = appliedDiscount + '%';
		};

		if (sel) sel.addEventListener('change', updateTotal);
		updateTotal();

		// Find confirm button (try custom id, default id, then footer primary)
		let confirmBtn = modalEl.querySelector(`#modalBtn_${modalId}_1`) || modalEl.querySelector(`#modalConfirmBtn_${modalId}`) || modalEl.querySelector('.modal-footer .btn-primary');
		if (!confirmBtn) return;

		confirmBtn.addEventListener('click', async function () {
			try {
				if (formEl && !formEl.checkValidity()) { formEl.classList.add('was-validated'); return; }
			} catch (e) { }

			const months = getMonths();

			// Expiry validation (normalize then test MM/YY)
			try {
				if (formEl) {
					const expEl = formEl.querySelector('input[name="card_exp"]');
					if (expEl) {
						let v = (expEl.value || '').trim();
						v = v.replace(/[\u2215\u2044\uFF0F\u29F8]/g, '/');
						v = v.replace(/\s+/g, '');
						v = v.replace(/[^\d\/]/g, '');
						if (v !== expEl.value) expEl.value = v;
						const expRe = /^(0[1-9]|1[0-2])\/\d{2}$/;
						if (!expRe.test(v)) { expEl.classList.add('is-invalid'); try { formEl.classList.add('was-validated'); } catch (e) { } return; }
						expEl.classList.remove('is-invalid');
					}
				}
			} catch (e) { }

			// send request
			try { $modal.find('button').prop('disabled', true); } catch (e) { }
			try {
				const resp = await fetch('/billing/subscribe', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
					body: JSON.stringify({ plan: key, months: months })
				});
				const json = await resp.json().catch(() => null);
				if (!resp.ok) {
					const msg = json?.message || json?.error || 'Error creando la suscripción';
					showNotification('Error', String(msg));
					try { const inst = bootstrap.Modal.getInstance(modalEl); inst?.hide(); } catch (e) { }
					return;
				}
				if (json && json.ok) {
					let action = 'suscripción';
					try {
						if (activePlanKey && String(activePlanKey) === String(key)) action = 'extensión';
						else if (activePlanKey && String(activePlanKey) !== String(key)) action = (priceCents > (activePriceCents || 0)) ? 'upgrade' : 'downgrade';
					} catch (e) { action = 'suscripción'; }
					if (action === 'extensión') showNotification('Suscripción extendida', `Has extendido ${title} por ${months} mes(es).`, { delayAutoClose: 4000 });
					else if (action === 'upgrade') showNotification('Actualización realizada', `Has actualizado a ${title}. Se cobrarán los cargos correspondientes.`, { delayAutoClose: 4000 });
					else if (action === 'downgrade') showNotification('Cambio realizado', `Has cambiado a ${title}. Se cobrarán los cargos correspondientes.`, { delayAutoClose: 4000 });
					else showNotification('Suscripción creada', `Has contratado ${title}. Se ha enviado una factura a tu correo.`, { delayAutoClose: 4000 });
					try { const inst = bootstrap.Modal.getInstance(modalEl); inst?.hide(); } catch (e) { }
					try { setActivePlanInDOM(json.activePlanKey || key, (json.activePriceCents !== undefined ? json.activePriceCents : priceCents), (json.ends_at || null)); } catch (e) { }
					return;
				}
				showNotification('Error', 'Respuesta inesperada del servidor');
			} catch (err) {
				showNotification('Error', 'No se pudo conectar con el servidor');
			} finally {
				try { $modal.find('button').prop('disabled', false); } catch (e) { }
			}
		});
	})();
}

function attachPlanHandlers() {
	document.querySelectorAll('.plan-cta').forEach(btn => {
		// Read active plan info once (page-provided)
		const root = document.getElementById('plans-root');
		let pageActivePrice = 0;
		let pageActivePlanId = null;
		let pageActivePlanKey = null;
		try {
			if (root && root.dataset) {
				pageActivePrice = parseInt(root.dataset.activePrice || '0', 10) || 0;
				pageActivePlanId = root.dataset.activePlanId || null;
				pageActivePlanKey = root.dataset.activePlanKey || null;
			}
		} catch (err) { pageActivePrice = 0; pageActivePlanId = null; pageActivePlanKey = null; }

		if (btn.__plansAttached) return; // idempotent
		btn.__plansAttached = true;

		// If this button corresponds to the active plan, replace it with a non-interactive label
		try {
			const pKey = String(btn.dataset.plan || btn.dataset.key || '').trim();
			const pId = String(btn.dataset.planId || btn.dataset.id || '').trim();
			if ((pageActivePlanKey && String(pageActivePlanKey) === String(pKey)) || (pageActivePlanId && String(pageActivePlanId) === String(pId))) {
				const span = document.createElement('span');
				span.className = 'badge bg-success';
				span.setAttribute('aria-live', 'polite');
				span.textContent = 'Tu plan actual';
				btn.replaceWith(span);
				return; // no handler attached
			}
		} catch (e) { }

		const handler = function (e) {
			e.preventDefault();
			const key = String(btn.dataset.plan || btn.dataset.key || '').trim();
			const title = String(btn.dataset.title || (plansMeta[key] && plansMeta[key].title) || key || 'Plan');
			const desc = String(btn.dataset.desc || (plansMeta[key] && plansMeta[key].desc) || '');
			const priceCents = parseInt(btn.dataset.price || (plansMeta[key] && Math.round(parseFloat(plansMeta[key].price) * 100)) || 0, 10) || 0;
			const priceLabel = priceCents === 0 ? 'Gratis' : `$${(priceCents / 100).toFixed(2)}/mes`;

			// Determine active plan id/key and price (from page data if available)
			let activePriceCents = pageActivePrice || 0;
			let activePlanId = pageActivePlanId || null;
			let activePlanKey = pageActivePlanKey || null;

			const isDifferentPlan = activePlanKey && String(activePlanKey) !== String(key);

			// If changing to a different plan, ask for confirmation on upgrade/downgrade
			if (isDifferentPlan) {
				if (activePriceCents > 0 && priceCents < activePriceCents) {
					// Downgrade
					modalConfirm({
						title: 'Atención — cambio a plan inferior',
						body: `<p>Estás a punto de cambiar de tu plan actual a <strong>${title}</strong>, que tiene un precio inferior. Esto puede suponer pérdida de beneficios o reducción de cuota.</p><p>¿Deseas continuar?</p>`,
						buttons: [
							{ text: 'Cancelar', className: 'btn-outline-secondary', dismiss: true },
							{
								text: 'Sí, continuar',
								className: 'btn-danger',
								onClick: ($confirmModal) => {
									try { const inst = bootstrap.Modal.getInstance($confirmModal[0]); inst?.hide(); } catch (e) { }
									// try to get discount from button or parent column
									const discountFromBtn = parseFloat(btn.dataset.discount || btn.dataset.planDiscount || '') || 0;
									let discountFinal = discountFromBtn;
									try {
										const col = btn.closest('[data-plan-discount]');
										if (col && col.dataset && col.dataset.planDiscount) discountFinal = parseFloat(col.dataset.planDiscount) || discountFinal;
									} catch (e) { }
									showPaymentModal({ key, title, desc, priceCents, discountPercent: discountFinal, activePlanKey, activePriceCents });
								}
							}
						]
					}, 'normal');
					return;
				} else if (activePriceCents >= 0 && priceCents > activePriceCents) {
					// Upgrade confirmation
					modalConfirm({
						title: 'Confirmar — cambio de plan',
						body: `<p>Vas a actualizar tu suscripción a <strong>${title}</strong> por ${priceLabel}. ¿Deseas continuar?</p>`,
						buttons: [
							{ text: 'Cancelar', className: 'btn-outline-secondary', dismiss: true },
							{
								text: 'Continuar',
								className: 'btn-primary',
								onClick: ($confirmModal) => {
									try { const inst = bootstrap.Modal.getInstance($confirmModal[0]); inst?.hide(); } catch (e) { }
									const discountFromBtn2 = parseFloat(btn.dataset.discount || btn.dataset.planDiscount || '') || 0;
									let discountFinal2 = discountFromBtn2;
									try {
										const col = btn.closest('[data-plan-discount]');
										if (col && col.dataset && col.dataset.planDiscount) discountFinal2 = parseFloat(col.dataset.planDiscount) || discountFinal2;
									} catch (e) { }
									showPaymentModal({ key, title, desc, priceCents, discountPercent: discountFinal2, activePlanKey, activePriceCents });
								}
							}
						]
					}, 'normal');
					return;
				}
			}

			// Same plan -> allow extension (open payment modal but label indicates extension)
			const discountFromBtn3 = parseFloat(btn.dataset.discount || btn.dataset.planDiscount || '') || 0;
			let discountFinal3 = discountFromBtn3;
			try {
				const col = btn.closest('[data-plan-discount]');
				if (col && col.dataset && col.dataset.planDiscount) discountFinal3 = parseFloat(col.dataset.planDiscount) || discountFinal3;
			} catch (e) { }
			showPaymentModal({ key, title, desc, priceCents, discountPercent: discountFinal3, activePlanKey, activePriceCents });
		};

		btn.__plansHandler = handler;
		btn.addEventListener('click', handler);
	});
}

// Helper: update DOM to mark a plan as active and restore other CTAs
function _formatEnds(iso) {
	if (!iso) return 'Indefinido';
	try {
		const d = new Date(iso);
		const now = new Date();
		const diffMs = d - now;
		const absDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
		if (absDays > 1) return `en ${absDays} días (${d.toLocaleDateString()})`;
		const absHours = Math.ceil(diffMs / (1000 * 60 * 60));
		if (absHours >= 1) return `en ${absHours} horas (${d.toLocaleString()})`;
		return `hoy (${d.toLocaleTimeString()})`;
	} catch (e) { return iso; }
}

function setActivePlanInDOM(newKey, newPriceCents, endsAtIso) {
	const root = document.getElementById('plans-root');
	if (root && root.dataset) {
		root.dataset.activePlanKey = newKey;
		root.dataset.activePrice = String(newPriceCents || 0);
		if (endsAtIso) root.dataset.activeEndsAt = endsAtIso; else delete root.dataset.activeEndsAt;
	}

	// Update the top-banner that shows current plan (if present)
	try {
		const banner = document.querySelector('#plans-root .alert.alert-info');
		const col = document.querySelector(`#plans-root [data-plan-key="${newKey}"]`);
		if (banner) {
			if (!newKey || !col) {
				// If no active plan, remove banner
				banner.remove();
			} else {
				// Update inner contents: plan name, ends and price
				const planName = col.dataset.planTitle || newKey;
				const priceCents = parseInt(col.dataset.planPrice || String(newPriceCents || 0), 10) || 0;
				const ends = endsAtIso || (root && root.dataset ? root.dataset.activeEndsAt : null);
				let endsHuman = 'Indefinido';
				try {
					if (!ends) endsHuman = 'Ninguno';
					else {
						const d = new Date(ends);
						const now = new Date();
						const diffMs = d - now;
						const absDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
						if (absDays > 1) endsHuman = `en ${absDays} días (${d.toLocaleDateString()})`;
						else { const absHours = Math.ceil(diffMs / (1000 * 60 * 60)); endsHuman = absHours >= 1 ? `en ${absHours} horas (${d.toLocaleString()})` : `hoy (${d.toLocaleTimeString()})`; }
					}
				} catch (e) { endsHuman = ends || 'Indefinido'; }
				const currency = (col && col.dataset && col.dataset.planCurrency) ? col.dataset.planCurrency : 'USD';
				const priceLabel = priceCents === 0 ? 'Gratis' : `$${(priceCents/100).toFixed(2)}/mes`;
				// Replace contents conservatively
				const left = banner.querySelector('div:first-child');
				const right = banner.querySelector('div.text-end');
				if (left) {
					left.innerHTML = `<div><strong>Tu plan actual:</strong> <span class="ms-1">${planName}</span><small class="text-muted d-block">Vence: ${endsHuman}${ends ? ` (${new Date(ends).toLocaleDateString()})` : ''}</small></div>`;
				}
				if (right) {
					right.innerHTML = `<div class="text-end small text-muted"><span>Precio actual: <strong>${priceLabel}</strong></span></div>`;
				}
			}
		} else {
			// If banner missing but we have an active plan, insert one before the plans grid
			if (newKey && col) {
				const container = document.querySelector('#plans-root > .row') || document.querySelector('#plans-root');
				const priceCents = parseInt(col.dataset.planPrice || String(newPriceCents || 0), 10) || 0;
				const planName = col.dataset.planTitle || newKey;
				const ends = endsAtIso || (root && root.dataset ? root.dataset.activeEndsAt : null);
				let endsHuman = ends ? new Date(ends).toLocaleString() : 'Indefinido';
				const priceLabel = priceCents === 0 ? 'Gratis' : `$${(priceCents/100).toFixed(2)}/mes`;
				const node = document.createElement('div');
				node.className = 'mb-3';
				node.innerHTML = `<div class="alert alert-info d-flex justify-content-between align-items-center"><div><strong>Tu plan actual:</strong> <span class="ms-1">${planName}</span><small class="text-muted d-block">Vence: ${endsHuman}${ends ? ` (${new Date(ends).toLocaleDateString()})` : ''}</small></div><div class="text-end small text-muted"><span>Precio actual: <strong>${priceLabel}</strong></span></div></div>`;
				if (container && container.parentNode) container.parentNode.insertBefore(node, container);
			}
		}
	} catch (e) { /* non-fatal */ }

	// Ensure previous active badges are removed and CTAs are consistent
	try {
		// Remove any global success badges first to avoid duplicates
		document.querySelectorAll('#plans-root .badge.bg-success').forEach(b => b.remove());
		// For each plan column, ensure it reflects whether it's active or not
		document.querySelectorAll('#plans-root [data-plan-key]').forEach(col => {
			const planKey = String(col.dataset.planKey || '').trim();
			const cardBody = col.querySelector('.card .card-body') || col;
			// remove any 'plan-remaining' stale element
			const remOld = col.querySelector('.plan-remaining'); if (remOld) remOld.remove();
			if (planKey === String(newKey)) {
				// Remove any CTA button
				const btn = col.querySelector('.plan-cta'); if (btn) btn.remove();
				const footer = findFooter(col);
				if (!col.querySelector('.badge.bg-success')) {
					const span = document.createElement('span');
					span.className = 'badge bg-success';
					span.setAttribute('aria-live', 'polite');
					span.textContent = 'Tu plan actual';
					if (footer) footer.appendChild(span);
					const rem = document.createElement('div');
					rem.className = 'plan-remaining text-muted small mt-2';
					rem.textContent = 'Vence: ' + _formatEnds(endsAtIso || (root && root.dataset ? root.dataset.activeEndsAt : null));
					if (footer) footer.appendChild(rem);
				}
			} else {
				// Not active: ensure CTA exists and badges removed
				const existingBtn = col.querySelector('.plan-cta');
				if (!existingBtn) {
						const title = col.dataset.planTitle || '';
						const desc = col.dataset.planDesc || '';
						const price = col.dataset.planPrice || '0';
						const key = col.dataset.planKey || '';
						const priceNum = parseInt(String(price || '0'), 10) || 0;
						const label = priceNum === 0 ? 'Seleccionar' : 'Contratar';
					const btn = document.createElement('button');
					btn.setAttribute('type', 'button');
					// Match the original blade markup classes so styles/spacing are preserved
					btn.className = 'btn btn-primary btn-plan-cta plan-cta w-100 w-md-auto py-2';
					btn.setAttribute('aria-label', `Contratar ${title}`);
					// Populate dataset attributes similar to the server-rendered button
					btn.dataset.plan = key; btn.dataset.key = key; btn.dataset.title = title; btn.dataset.desc = desc; btn.dataset.price = String(priceNum || 0);
					// If this column contains an admin-only notice, do not create a CTA
					const adminNotice = col.querySelector('.text-muted.small');
					if (adminNotice && /Modo administración/i.test((adminNotice.textContent||''))) {
						// leave admin notice as-is and skip creating CTA
					} else {
					// Ensure the small descriptor exists and keep it as first item in footer
					const footer = findFooter(col);
					if (footer) {
						let descEl = footer.querySelector('small.text-muted');
						if (!descEl) {
							descEl = document.createElement('small');
							descEl.className = 'text-muted';
							descEl.textContent = 'Beneficios clave incluidos';
							footer.insertBefore(descEl, footer.firstChild);
						}
						btn.textContent = label;
						footer.appendChild(btn);
					} else if (cardBody) {
						btn.textContent = label;
						cardBody.appendChild(btn);
					}
					try { attachPlanHandlers(); } catch (e) { }
					}
				}
				const bad = col.querySelectorAll('.badge.bg-success'); if (bad && bad.length) bad.forEach(x=>x.remove());
				const rem2 = col.querySelector('.plan-remaining'); if (rem2) rem2.remove();
			}
		});
	} catch (e) { /* non-fatal */ }
}

function showNotification(title, message, opts) {
	try {
		if (typeof window.modalNotification === 'function') {
			window.modalNotification(title, message, Object.assign({ template: 'success' }, opts || {}));
			return;
		}
	} catch (e) { }
	alert(title + "\n" + message);
}

// Lifecycle helpers so this module can be init/destroyed on PJAX/navigation
function domReadyHandler() {
	attachPlanHandlers();
	try { attachAdminEditHandlers(); } catch (e) { }
}

function attachAdminEditHandlers() {
	// Only attach once
	if (attachAdminEditHandlers.attached) return; attachAdminEditHandlers.attached = true;
	document.querySelectorAll('.plan-edit').forEach(btn => {
		if (btn.__planEditAttached) return; btn.__planEditAttached = true;
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			const planId = btn.dataset.planId || btn.getAttribute('data-plan-id');
			const planKey = btn.dataset.planKey || btn.getAttribute('data-plan-key');
			const col = btn.closest('[data-plan-id]');
			const priceCents = parseInt(col?.dataset.planPrice || '0', 10) || 0;
			const discount = parseFloat(col?.dataset.planDiscount || '0') || 0;

			const modalBody = {
				title: `Editar precio — ${planKey}`,
				body: (function () {
					// build form with price, discount and roles selector (for visibility)
					const root = document.getElementById('plans-root');
					let roles = [];
					try { if (root && root.dataset && root.dataset.roles) roles = JSON.parse(root.dataset.roles); } catch (e) { roles = []; }
					let vis = [];
					try { vis = JSON.parse(col?.dataset?.planVisibleRoles || '[]') || []; } catch (e) { vis = []; }
					const rolesHtml = roles.map(r => {
						const checked = vis.indexOf(r.id) !== -1 ? 'checked' : '';
						return `<div class="form-check"><input class="form-check-input" type="checkbox" name="visible_role_ids[]" id="role_chk_${planId}_${r.id}" value="${r.id}" ${checked}><label class="form-check-label" for="role_chk_${planId}_${r.id}">${r.name}</label></div>`;
					}).join('');
					return `
						<form id="adminPriceForm_${planId}" class="needs-validation" novalidate>
							<div class="mb-2">
								<label class="form-label small">Precio (cents)</label>
								<input type="number" class="form-control form-control-sm" name="price_cents" value="${priceCents}" required />
							</div>
							<div class="mb-2">
								<label class="form-label small">Descuento (%)</label>
								<input type="number" min="0" max="100" step="0.1" class="form-control form-control-sm" name="discount_percent" value="${discount}" />
							</div>
							<div class="mb-2">
								<label class="form-label small">Ofertas por duración</label>
								<div id="multiDiscountsContainer_${planId}">
									<!-- filas dinámicas insertadas por JS: cada fila tiene .md-months y .md-percent -->
								</div>
								<div class="mt-2 d-flex gap-2">
									<button type="button" id="addMulti_${planId}" class="btn btn-sm btn-outline-primary">Agregar oferta</button>
									<button type="button" class="btn btn-sm btn-outline-secondary" onclick="(function(e){const c=document.getElementById('multiDiscountsContainer_${planId}'); if(c) c.innerHTML='';})(event)">Limpiar</button>
								</div>
								<div class="form-text small text-muted">Agrega filas con <strong>meses</strong> y <strong>%</strong>. Se aplica el mayor descuento válido.</div>
							</div>
							<div class="mb-2">
								<label class="form-label small d-block">Visible para roles</label>
								<div class="d-flex flex-wrap gap-2">${rolesHtml}</div>
								<div class="form-text small text-muted">Si no se selecciona ninguno, el plan será visible para <strong>todos</strong> por defecto.</div>
							</div>
						</form>
						`;
				})(),
				buttons: [
					{ text: 'Cancelar', className: 'btn-outline-secondary', dismiss: true },
					{ text: 'Guardar', className: 'btn-primary' }
				]
			};

			modalConfirm(modalBody, 'normal');
			setTimeout(() => {
				try {
					// Find the modal we just created by matching title
					const $m = $('.modal').filter(function () { return $(this).find('.modal-title').text().trim() === modalBody.title.trim(); }).last();
					const $confirm = $m.find('.btn-primary').last();

						// Populate dynamic multi-month discount rows and wire add/remove
						try {
							const container = document.getElementById(`multiDiscountsContainer_${planId}`);
							if (container) {
								// helper to create a row element
								const makeRow = (m, p) => {
									const wrapper = document.createElement('div');
									wrapper.className = 'input-group mb-2 multi-discount-row';
									wrapper.innerHTML = `
										<input type="number" min="1" class="form-control form-control-sm md-months" value="${m || ''}" placeholder="Meses" />
										<input type="number" min="0" max="100" step="0.1" class="form-control form-control-sm md-percent" value="${p || ''}" placeholder="%" />
										<button type="button" class="btn btn-outline-danger btn-sm md-remove">Eliminar</button>
									`;
									return wrapper;
								};
								let existing = [];
								try { existing = JSON.parse(col?.dataset?.planMultiDiscounts || '[]'); } catch (e) { existing = []; }
								existing.forEach(it => {
									try {
										const months = parseInt(it.months || it.min || 0, 10) || 0;
										const percent = parseFloat(it.percent || it.p || 0) || 0;
										container.appendChild(makeRow(months, percent));
									} catch (e) { }
								});
								const attachRemoveHandlers = () => {
									container.querySelectorAll('.md-remove').forEach(b => {
										if (b.__md_remove_attached) return;
										b.__md_remove_attached = true;
										b.addEventListener('click', function () { const r = this.closest('.multi-discount-row'); if (r) r.remove(); });
									});
								};
								attachRemoveHandlers();
								const addBtn = document.getElementById(`addMulti_${planId}`);
								if (addBtn) {
									addBtn.addEventListener('click', function () {
										container.appendChild(makeRow('', ''));
										attachRemoveHandlers();
									});
								}
							}
						} catch (e) { }
					$confirm.off('click').on('click', async function () {
						const form = document.getElementById(`adminPriceForm_${planId}`);
						if (form && !form.checkValidity()) { form.classList.add('was-validated'); return; }
						const fd = new FormData(form);
						const visible = [];
						try {
							for (const pair of fd.entries()) {
								if (pair[0] === 'visible_role_ids[]' || pair[0] === 'visible_role_ids') {
									// pair[1] may be string id
									const v = parseInt(pair[1], 10);
									if (!isNaN(v)) visible.push(v);
								}
							}
						} catch (e) { }
						// Collect multi-month discounts from dynamic rows (preferred) or fallback to textarea value
						let multi = [];
						try {
							const container = document.getElementById(`multiDiscountsContainer_${planId}`);
							if (container) {
								const rows = container.querySelectorAll('.multi-discount-row');
								rows.forEach(r => {
									try {
										const monthsEl = r.querySelector('.md-months');
										const percentEl = r.querySelector('.md-percent');
										const months = parseInt(monthsEl?.value || '0', 10) || 0;
										const percent = parseFloat(percentEl?.value || '0') || 0;
										if (months >= 1 && !isNaN(percent)) multi.push({ months: months, percent: percent });
									} catch (e) { }
								});
							} else {
								const raw = fd.get('multi_month_discounts');
								if (raw) {
									if (typeof raw === 'string') multi = JSON.parse(raw);
									else multi = raw;
								}
							}
						} catch (e) { multi = []; }

						// Validate multi structure: array of {months:int>=1, percent:0..100}
						if (!Array.isArray(multi)) {
							showNotification('Error', 'Formato inválido: las ofertas por duración deben ser un array JSON.');
							try { $m.find('button').prop('disabled', false); } catch (e) { }
							return;
						}
						for (let i = 0; i < multi.length; i++) {
							const it = multi[i];
							if (!it || typeof it !== 'object') {
								showNotification('Error', `Entrada inválida en ofertas por duración en índice ${i}`);
								try { $m.find('button').prop('disabled', false); } catch (e) { }
								return;
							}
							const months = parseInt(it.months || it.min || 0, 10) || 0;
							const percent = parseFloat(it.percent || it.p || 0) || 0;
							if (months < 1 || isNaN(months)) {
								showNotification('Error', `El campo 'months' debe ser entero >= 1 en la entrada ${i}`);
								try { $m.find('button').prop('disabled', false); } catch (e) { }
								return;
							}
							if (isNaN(percent) || percent < 0 || percent > 100) {
								showNotification('Error', `El campo 'percent' debe estar entre 0 y 100 en la entrada ${i}`);
								try { $m.find('button').prop('disabled', false); } catch (e) { }
								return;
							}
							// normalize values
							multi[i] = { months: months, percent: percent };
						}

						const payload = { price_cents: parseInt(fd.get('price_cents') || '0', 10), discount_percent: parseFloat(fd.get('discount_percent') || '0'), visible_role_ids: visible, multi_month_discounts: multi };
						try { $m.find('button').prop('disabled', true); } catch (e) { }
						try {
							const resp = await fetch(`/admin/plans/${encodeURIComponent(planId)}/pricing`, {
								method: 'POST',
								headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
								body: JSON.stringify(payload)
							});
							const json = await resp.json().catch(() => null);
							if (!resp.ok) {
								showNotification('Error', json?.message || json?.error || 'No se pudo actualizar el plan');
								return;
							}
							if (json && json.ok) {
								showNotification('Guardado', 'Precio actualizado correctamente', { delayAutoClose: 3000 });
								// Update DOM attributes and display
								try {
									if (col) {
										col.dataset.planPrice = String(json.plan.price_cents || payload.price_cents || 0);
										const dp = (json.plan.features && json.plan.features.discount_percent) ? json.plan.features.discount_percent : payload.discount_percent;
										col.dataset.planDiscount = String(dp || 0);
										// update visible price text (display-6 inside card)
										const priceEl = col.querySelector('.display-6');
										if (priceEl) {
											const cents = parseInt(col.dataset.planPrice || '0', 10) || 0;
											priceEl.textContent = `$${(cents / 100).toFixed(2)}/mes`;
										}
										// update visible roles dataset
										try {
											const vis = (json.plan.features && json.plan.features.visible_roles) ? json.plan.features.visible_roles : payload.visible_role_ids || [];
											col.dataset.planVisibleRoles = JSON.stringify(vis || []);
											// update multi-month discounts dataset
											try {
												const mm = (json.plan.features && json.plan.features.multi_month_discounts) ? json.plan.features.multi_month_discounts : payload.multi_month_discounts || [];
												col.dataset.planMultiDiscounts = JSON.stringify(mm || []);
											} catch (e) { }
										} catch (e) { }
									}
								} catch (e) { }
								// close modal
								try { closeAllModals(); } catch (e) { }
							}
						} catch (err) {
							showNotification('Error', 'Fallo al conectar con el servidor');
						} finally { try { $m.find('button').prop('disabled', false); } catch (e) { } }
					});
				} catch (e) { }
			}, 120);
		});
	});
}

export function init() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', domReadyHandler);
	} else {
		// If document already ready, run the full DOM-ready handler so admin handlers attach too
		domReadyHandler();
	}
}

export function destroy() {
	try { document.removeEventListener('DOMContentLoaded', domReadyHandler); } catch (e) { }
	try {
		document.querySelectorAll('.plan-cta').forEach(btn => {
			if (btn.__plansHandler) {
				btn.removeEventListener('click', btn.__plansHandler);
				try { delete btn.__plansHandler; } catch (e) { btn.__plansHandler = undefined; }
			}
			try { delete btn.__plansAttached; } catch (e) { btn.__plansAttached = undefined; }
		});
	} catch (e) { }
	// Close any plan modal left open
	try { const $m = document.getElementById('rejectReasonModal'); if ($m) { const inst = bootstrap.Modal.getInstance($m); inst?.hide(); } } catch (e) { }
}

// Auto-init for classical page loads (keeps backward compatibility)
try { init(); } catch (e) { }

export default { init, destroy, attachPlanHandlers };
