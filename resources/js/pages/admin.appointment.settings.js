// Admin Appointment Settings page module
// Provides init/destroy compatible with PJAX lifecycle

let formEl = null;
let feedbackEl = null;
let resetBtn = null;

function setFeedback(type, msg) {
	if (!feedbackEl) return;
	feedbackEl.innerHTML = `<div class="alert alert-${type}">${msg}</div>`;
}

function restoreDefaults() {
	try {
		if (!formEl) return;
		const defaults = {
			presence_threshold_pct: 97,
			early_access_minutes: 5,
			reschedule_deadline_hours: 24,
			unanswered_reprogram_hours: 5,
			ping_interval_seconds: 45,
		};
		Object.entries(defaults).forEach(([k,v])=>{
			const input = formEl.querySelector(`[name="${k}"]`);
			if (input) input.value = v;
		});
		setFeedback('info','Valores por defecto restaurados (sin guardar aún).');
	} catch (_) {}
}

function submitSettings(e) {
	e.preventDefault();
	if (!formEl) return;
	setFeedback('secondary','Guardando...');
	const url = formEl.getAttribute('action');
	const fd = new FormData(formEl);
	const tokenMeta = document.querySelector('meta[name="csrf-token"]');
	const token = tokenMeta ? tokenMeta.getAttribute('content') : null;
	const headers = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
	if (token) headers['X-CSRF-TOKEN'] = token;
	fetch(url, { method:'POST', body: fd, credentials:'same-origin', headers })
		.then(async res => {
			let payload = null; try { payload = await res.json(); } catch(_){}
			if (!res.ok) {
				// Try to extract validation errors
				if (payload && payload.errors) {
					const list = Object.values(payload.errors).map(arr=>arr.join(' ')).join('<br>');
					setFeedback('danger', list || 'Error al guardar.');
				} else {
					setFeedback('danger','Error al guardar configuraciones.');
				}
				return;
			}
			setFeedback('success','Configuraciones guardadas.');
		})
		.catch(()=> setFeedback('danger','Error de red al guardar.'));
}

export function init() {
	try {
		formEl = document.getElementById('appointment-settings-form');
		feedbackEl = document.getElementById('settings-feedback');
		resetBtn = document.getElementById('settings-reset-btn');
		if (formEl) formEl.addEventListener('submit', submitSettings);
		if (resetBtn) resetBtn.addEventListener('click', restoreDefaults);
	} catch (_) {}
}

export function destroy() {
	try {
		if (formEl) formEl.removeEventListener('submit', submitSettings);
		if (resetBtn) resetBtn.removeEventListener('click', restoreDefaults);
		formEl = null; feedbackEl = null; resetBtn = null;
	} catch (_) {}
}
