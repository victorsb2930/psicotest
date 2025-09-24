// Simple client-side validation and submit module (exports init/destroy)
const NS = '.contactPage';

function onSubmit(e) {
	e.preventDefault();
	const name = $('#name').val()?.toString().trim();
	const email = $('#email').val()?.toString().trim();
	const subject = $('#subject').val()?.toString().trim();
	const message = $('#message').val()?.toString().trim();

	if (!name || !email || !subject || !message) {
		window.modalNotification?.('Completa los campos', 'Todos los campos son obligatorios.', { template: 'warning' });
		return;
	}

	(async () => {
		try {
			await window.axios.post('/contact', { name, email, subject, message });
			window.modalNotification?.('Mensaje enviado', 'Gracias por contactarnos. Te responderemos pronto.', { template: 'success' });
			document.getElementById('contactForm')?.reset();
		} catch (err) {
			const res = err?.response; const status = res?.status;
			const isSevere = !res || (status >= 500);
			const title = isSevere ? 'Error del servidor' : 'No se pudo enviar';
			const body = isSevere ? 'Ocurrió un problema, inténtalo más tarde.' : (res?.data?.message || 'Revisa tus datos e inténtalo de nuevo.');
			window.modalNotification?.(title, window.escapeHtml(String(body)), { template: isSevere ? 'danger' : 'warning' }, isSevere, { xhr: res, fncErr: 'contactForm', page: 'contact' });
		}
	})();
}

export function init() {
	const form = $('#contactForm');
	if (!form.length) return;
	form.off('submit' + NS).on('submit' + NS, onSubmit);
}

export function destroy() {
	try { $('#contactForm').off('submit' + NS); } catch (_) {}
}
