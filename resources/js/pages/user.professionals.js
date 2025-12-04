function redirectToAppointments(id, name, title) {
	const params = new URLSearchParams();
	if (id) params.set('professional_id', id);
	if (name) params.set('professional_name', name);
	if (title) params.set('professional_title', title);
	window.location.href = '/appointments?' + params.toString();
}

function handleRequest(button) {
	if (!button) return;
	const id = button.dataset.professionalId || '';
	if (!id) return;
	const name = button.dataset.professionalName || '';
	const title = button.dataset.professionalTitle || '';

	if (typeof window.requestAppointmentFlow === 'function') {
		try {
			window.requestAppointmentFlow(id, name, title);
			return;
		} catch (e) {
			// fallback below
		}
	}
	redirectToAppointments(id, name, title);
}

export function init() {
	const buttons = document.querySelectorAll('.js-user-prof-request');
	if (!buttons || buttons.length === 0) return;
	buttons.forEach(btn => {
		const handler = () => handleRequest(btn);
		btn.__userProfHandler = handler;
		btn.addEventListener('click', handler);
	});
}

export function destroy() {
	const buttons = document.querySelectorAll('.js-user-prof-request');
	buttons.forEach(btn => {
		if (btn.__userProfHandler) {
			btn.removeEventListener('click', btn.__userProfHandler);
			delete btn.__userProfHandler;
		}
	});
}
