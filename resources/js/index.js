// Hybrid CTA: open login modal via modalConfirm on desktop, navigate on mobile or when JS disabled
$(function() {
	const $cta = $(".site-header .btn-cta[href='/welcome']");
	const isDesktop = () => window.matchMedia('(min-width: 768px)').matches; // md breakpoint

	$cta.off('click').on('click', function(e) {
		if (isDesktop() && window.modalConfirm) {
			e.preventDefault();
			const bodyHtml = {
				title: 'Iniciar sesión',
				body: `
					<form id="quickLoginForm" action="/login" method="POST">
						<input type="hidden" name="_token" value="${$('meta[name="csrf-token"]').attr('content')}">
						<div class="mb-3">
							<label for="quick_email" class="form-label">Email</label>
							<input type="email" class="form-control" id="quick_email" name="email" autocomplete="email" placeholder="Email">
						</div>
						<div class="mb-3">
							<label for="quick_password" class="form-label">Contraseña</label>
							<input type="password" class="form-control" id="quick_password" name="password" autocomplete="current-password" placeholder="Contraseña">
						</div>
						<div class="d-flex justify-content-between align-items-center">
							<a class="small text-decoration-underline" href="/welcome#forgot">¿Olvidaste tu contraseña?</a>
							<a class="small text-decoration-underline" href="/welcome#registro">Crear cuenta</a>
						</div>
					</form>
				`,
				btnsType: 'ac',
				onClickYes: async () => {
					const $form = $('#quickLoginForm');
					const email = $('#quick_email').val()?.toString().trim();
					const password = $('#quick_password').val()?.toString().trim();
					// Validación mínima; mantener modal abierto en errores
					if (!email || !password) {
						modalNotification('Completa los campos', 'Ingresa email y contraseña.', { template: 'warning' });
						return; // No cerrar modalConfirm
					}

					const url = $form.attr('action');
					try {
						const fd = new FormData($form[0]);
						fd.set('email', email);
						fd.set('password', password);
						const res = await axios.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
						// If backend indicates under-review via JSON, respect redirect
						if (res?.data && res.data.under_review && res.data.redirect) {
							window.location.href = res.data.redirect;
							return;
						}
						const target = res?.data?.redirect || '/';
						window.location.href = target;
					} catch (err) {
						const res = err?.response;
						const status = res?.status;
						const isNetwork = !!err?.isAxiosError && !res;
						const isServer = typeof status === 'number' && status >= 500;
						const isValidation = [400, 401, 403, 422].includes(status ?? -1);

						// If backend denied due to under-review, redirect accordingly
						if (res?.data?.under_review && res?.data?.redirect) {
							window.location.href = res.data.redirect;
							return;
						}

						let message = 'Email o contraseña incorrectos.';
						if (res && typeof res.data === 'object') {
							if (res.data.errors && typeof res.data.errors === 'object') {
								const firstKey = Object.keys(res.data.errors)[0];
								const firstMsg = Array.isArray(res.data.errors[firstKey]) ? res.data.errors[firstKey][0] : res.data.errors[firstKey];
								message = firstMsg || res.data.message || message;
							} else if (res.data.message) {
								message = res.data.message;
							} else {
								try { message = JSON.stringify(res.data); } catch (_) {}
							}
						} else if (res && typeof res.data === 'string') {
							message = res.data;
						} else if (isNetwork && err?.message) {
							message = err.message;
						}

						const severe = isNetwork || isServer;
						const title = severe ? 'Error del servidor' : 'Error de acceso';
						const template = severe ? 'danger' : 'warning';
						const showDetails = !!severe;
						const detailCfg = { xhr: res, fncErr: 'quickLogin', page: 'index', body: 'Error al iniciar sesión' };

						modalNotification(title, window.escapeHtml(String(message)), { template, delayAutoClose: 6000 }, showDetails, detailCfg);
					}
				},
				closeClick: false // No cerrar automáticamente tras Confirmar; controlamos nosotros
			};
			window.modalConfirm(bodyHtml, 'normal', { centered: true, scrollable: false, size: '', draggable: true });
			setTimeout(() => document.getElementById('quick_email')?.focus(), 150);
		}
		// else: fallback to normal navigation
	});

	// Al hacer clic en "Crear cuenta" dentro del modalConfirm, simplemente navega
	$(document).off('click.modal-register').on('click.modal-register', 'a[href*="/welcome#registro"]', function(){ /* noop */ });

	// También soporta enviar con Enter dentro del formulario renderizado por modalConfirm
	$(document).off('submit.quickLogin').on('submit.quickLogin', '#quickLoginForm', function(e){
		// Evitamos submit normal; preferimos el botón Confirmar del modalConfirm
		e.preventDefault();
	});
});