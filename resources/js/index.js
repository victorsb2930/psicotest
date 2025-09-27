// Hybrid CTA module (exports init/destroy)
const NS = '.indexPage';
let waitInterval = null;

function isDesktop() { return window.matchMedia('(min-width: 768px)').matches; }

function handleCtaClick(e) {
	if (isDesktop() && modalConfirm) {
		e.preventDefault();
		const bodyHtml = {
			title: 'Iniciar sesión',
				body: `
				<form id="quickLoginFormLocal" action="/login" method="POST">
					<input type="hidden" name="_token" value="${$('meta[name="csrf-token"]').attr('content')}">
					<div class="mb-3">
						<label for="quick_email_local" class="form-label">Email</label>
						<input type="email" class="form-control" id="quick_email_local" name="email" autocomplete="email" placeholder="Email">
					</div>
					<div class="mb-3">
						<label for="quick_password_local" class="form-label">Contraseña</label>
						<input type="password" class="form-control" id="quick_password_local" name="password" autocomplete="current-password" placeholder="Contraseña">
					</div>
					<div class="d-flex justify-content-between align-items-center">
						<a class="small text-decoration-underline" href="/welcome#forgot">¿Olvidaste tu contraseña?</a>
						<a class="small text-decoration-underline" href="/welcome#registro">Crear cuenta</a>
					</div>
				</form>
			`,
			btnsType: 'ac',
			onClickYes: async () => {
				const $form = $('#quickLoginFormLocal');
				const email = $('#quick_email_local').val()?.toString().trim();
				const password = $('#quick_password_local').val()?.toString().trim();
				if (!email || !password) {
					modalNotification('Completa los campos', 'Ingresa email y contraseña.', { template: 'warning' });
					return;
				}
				const url = $form.attr('action');
				try {
					const fd = new FormData($form[0]); fd.set('email', email); fd.set('password', password);
					const res = await axios.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
					if (res?.data && res.data.under_review && res.data.redirect) { window.location.href = res.data.redirect; return; }
					const target = res?.data?.redirect || '/'; window.location.href = target;
				} catch (err) {
					const res = err?.response; const status = res?.status; const isNetwork = !!err?.isAxiosError && !res; const isServer = typeof status === 'number' && status >= 500;
					if (res?.data?.under_review && res?.data?.redirect) { window.location.href = res.data.redirect; return; }
					let message = 'Email o contraseña incorrectos.';
					if (res && typeof res.data === 'object') {
						if (res.data.errors && typeof res.data.errors === 'object') {
							const firstKey = Object.keys(res.data.errors)[0];
							const firstMsg = Array.isArray(res.data.errors[firstKey]) ? res.data.errors[firstKey][0] : res.data.errors[firstKey];
							message = firstMsg || res.data.message || message;
						} else if (res.data.message) message = res.data.message; else try { message = JSON.stringify(res.data); } catch (_) {}
					} else if (res && typeof res.data === 'string') message = res.data; else if (isNetwork && err?.message) message = err.message;
					const severe = isNetwork || isServer; const title = severe ? 'Error del servidor' : 'Error de acceso'; const template = severe ? 'danger' : 'warning';
					const detailCfg = { xhr: res, fncErr: 'quickLogin', page: 'index', body: 'Error al iniciar sesión' };
					modalNotification(title, window.escapeHtml(String(message)), { template, delayAutoClose: 6000 }, severe, detailCfg);
				}
			},
			closeClick: false
		};
		modalConfirm(bodyHtml, 'normal', { centered: true, scrollable: false, size: ''});
	setTimeout(() => document.getElementById('quick_email_local')?.focus(), 150);
	}
}

function attachHandlers() {
	// Avoid attaching local quick-login handler if a global handler is present
	if (window.__quickLoginGlobalAttached) {
		// still bind other handlers but skip quick-login click
		const $cta = $(".site-header .btn-cta[href='/welcome']");
		$cta.off('click' + NS).on('click' + NS, function(e){ /* delegated to global quickLogin */ });
	} else {
		const $cta = $(".site-header .btn-cta[href='/welcome']");
		$cta.off('click' + NS).on('click' + NS, handleCtaClick);
	}
	$(document).off('click' + NS).on('click' + NS, 'a[href*="/welcome#registro"]', function(){});
	$(document).off('submit' + NS).on('submit' + NS, '#quickLoginFormLocal', function(e){ e.preventDefault(); });
}

function detachHandlers() {
	try { $(".site-header .btn-cta[href='/welcome']").off('click' + NS); } catch (_) {}
	try { $(document).off(NS); } catch (_) {}
	if (waitInterval) { clearInterval(waitInterval); waitInterval = null; }
	// Remove quickLogin modal if present
	try {
	const $m = $('.modal').has('#quickLoginFormLocal');
		$m.each(function(){ const inst = bootstrap.Modal.getInstance(this); inst?.hide(); $(this).remove(); });
	} catch (_) {}
}

export function init() { attachHandlers(); }
export function destroy() { detachHandlers(); }