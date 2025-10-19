import { modalConfirm } from '../utils/modalConfirm';

// Plans module: attach handlers for .plan-cta buttons and use modalConfirm for accessible dialogs
const plansMeta = {
	basico: { title: 'Básico', price: '0', desc: 'Acceso gratuito: perfil público, búsqueda limitada y mensajería básica.' },
	profesional: { title: 'Profesional', price: '9.99', desc: 'Listado destacado, calendario avanzado y gestión de pacientes.' },
	premium: { title: 'Premium', price: '19.99', desc: 'Prioridad en búsquedas, analíticas y soporte prioritario.' }
};

/**
 * Show the payment modal for a given plan.
 * Encapsulates the previous inline modal logic so the downgrade flow can show a small confirmation first.
 */
function showPaymentModal({ key, title, desc, priceCents }) {
	const priceLabel = priceCents === 0 ? 'Gratis' : `$${(priceCents / 100).toFixed(2)}/mes`;
	const bodyHtml = `
				<div class="mb-2 small text-muted">${desc}</div>
				<div class="mb-3">
					<div class="fw-medium">Precio: <span class="fw-bold">${priceLabel}</span></div>
				</div>
				<form id="planPayForm_${key}" class="needs-validation" novalidate>
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
							<input type="text" class="form-control form-control-sm" name="card_exp" placeholder="MM/AA" required />
						</div>
						<div style="width:110px">
							<label class="form-label small">CVC</label>
							<input type="text" class="form-control form-control-sm" name="card_cvc" placeholder="123" required />
						</div>
					</div>
					<div class="form-text small text-muted mt-2">Los datos de tarjeta son simulados y no se almacenan.</div>
				</form>
			`;

	modalConfirm({
		title: `Contratar ${title} — ${priceLabel}`,
		body: bodyHtml,
		buttons: [
			{ text: 'Cerrar', className: 'btn-outline-secondary', dismiss: true },
			{
				text: 'Pagar',
				className: 'btn-primary',
				onClick: ($modal) => {
					// Validate fake form
					try {
						const form = document.getElementById(`planPayForm_${key}`);
						if (form) {
							if (!form.checkValidity()) {
								form.classList.add('was-validated');
								return;
							}
						}
					} catch (e) { }

					// Call server to create subscription
					(async () => {
						try { $modal.find('button').prop('disabled', true); } catch (e) { }
						try {
							const resp = await fetch('/billing/subscribe', {
								method: 'POST',
								headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
								body: JSON.stringify({ plan: key })
							});
							const json = await resp.json().catch(() => null);
							if (!resp.ok) {
								const msg = json?.message || json?.error || 'Error creando la suscripción';
								showNotification('Error', String(msg));
								try { const inst = bootstrap.Modal.getInstance($modal[0]); inst?.hide(); } catch (e) { }
								return;
							}
							if (json && json.ok) {
								showNotification('Suscripción creada', `Has contratado ${title}. Se ha enviado una factura simulada a tu correo.`, { delayAutoClose: 4000 });
								try { const inst = bootstrap.Modal.getInstance($modal[0]); inst?.hide(); } catch (e) { }
								return;
							}
							showNotification('Error', 'Respuesta inesperada del servidor');
						} catch (err) {
							console.error(err);
							showNotification('Error', 'No se pudo conectar con el servidor');
						} finally {
							try { $modal.find('button').prop('disabled', false); } catch (e) { }
						}
					})();
				}
			}
		]
	}, 'normal');
}

function attachPlanHandlers() {
	document.querySelectorAll('.plan-cta').forEach(btn => {
		// Read active plan info once (page-provided)
		const root = document.getElementById('plans-root');
		let pageActivePrice = 0;
		let pageActivePlanId = null;
		try {
			if (root && root.dataset) {
				pageActivePrice = parseInt(root.dataset.activePrice || '0', 10) || 0;
				pageActivePlanId = root.dataset.activePlanId || null;
			}
		} catch (err) { pageActivePrice = 0; pageActivePlanId = null; }

		if (btn.__plansAttached) return; // idempotent
		btn.__plansAttached = true;

		// If this button corresponds to the active plan, replace it with a non-interactive label
		try {
			const pKey = String(btn.dataset.plan || btn.dataset.key || '').trim();
			if (pageActivePlanId && String(pageActivePlanId) === String(pKey)) {
				const span = document.createElement('span');
				span.className = 'badge bg-success';
				span.setAttribute('aria-live', 'polite');
				span.textContent = 'Tu plan actual';
				btn.replaceWith(span);
				return; // no handler attached
			}
		} catch (e) {}

		const handler = function (e) {
			e.preventDefault();
			const key = String(btn.dataset.plan || btn.dataset.key || '').trim();
			const title = String(btn.dataset.title || (plansMeta[key] && plansMeta[key].title) || key || 'Plan');
			const desc = String(btn.dataset.desc || (plansMeta[key] && plansMeta[key].desc) || '');
			const priceCents = parseInt(btn.dataset.price || (plansMeta[key] && Math.round(parseFloat(plansMeta[key].price) * 100)) || 0, 10) || 0;
			const priceLabel = priceCents === 0 ? 'Gratis' : `$${(priceCents / 100).toFixed(2)}/mes`;

			// Determine active plan id and price (from page data if available)
			let activePriceCents = pageActivePrice || 0;
			let activePlanId = pageActivePlanId || null;

			// If this is a downgrade (different plan and cheaper), show a small confirmation first. Otherwise go directly to payment modal.
			if (activePlanId && String(activePlanId) !== String(key) && activePriceCents > 0 && priceCents < activePriceCents) {
				modalConfirm({
					title: 'Atención — cambio a plan inferior',
					body: `<p>Estás a punto de cambiar a <strong>${title}</strong>, que tiene un precio inferior al plan que tienes actualmente. Esto puede suponer pérdida de beneficios o reducción de cuota.</p><p>¿Deseas continuar?</p>`,
					buttons: [
						{ text: 'Cancelar', className: 'btn-outline-secondary', dismiss: true },
						{
							text: 'Sí, continuar',
							className: 'btn-danger',
							onClick: ($confirmModal) => {
								try { const inst = bootstrap.Modal.getInstance($confirmModal[0]); inst?.hide(); } catch (e) { }
								// After user confirms downgrade, open the payment modal
								showPaymentModal({ key, title, desc, priceCents });
							}
						}
					]
				}, 'small');
			} else {
				showPaymentModal({ key, title, desc, priceCents });
			}
		};

		btn.__plansHandler = handler;
		btn.addEventListener('click', handler);
	});
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
}

export function init() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', domReadyHandler);
	} else {
		attachPlanHandlers();
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
	} catch (e) { console.warn('[plans] destroy error', e); }
	// Close any plan modal left open
	try { const $m = document.getElementById('rejectReasonModal'); if ($m) { const inst = bootstrap.Modal.getInstance($m); inst?.hide(); } } catch (e) { }
}

// Auto-init for classical page loads (keeps backward compatibility)
try { init(); } catch (e) { }

export default { init, destroy, attachPlanHandlers };
