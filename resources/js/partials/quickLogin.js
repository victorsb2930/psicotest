// Quick login partial (PJAX-friendly): exposes init() and destroy()
// Keeps the same UX as before but can be initialized/destroyed by a page loader.
(function (global, $) {
	const ns = '.quickLogin';
	let _inited = false;
	let _captureHandler = null;
	let _observer = null;

	function isDesktop() {
		return window.matchMedia && window.matchMedia('(min-width: 768px)').matches;
	}

	function openQuickLoginModal() {
		if (!modalConfirm) return;
		const modalId = 'quickLoginModalGlobal';
		const bodyHtml = {
			modalId: modalId,
			title: 'Iniciar sesión',
			confirmLabel: 'Iniciar sesión',
			cancelLabel: 'Cancelar',
			body: `
					<form id="quickLoginFormGlobal" action="/login" method="POST">
						<input type="hidden" name="_token" value="${$('meta[name="csrf-token"]').attr('content')}">
						<div class="mb-3">
							<label for="quick_email_global" class="form-label">Email</label>
							<input type="email" class="form-control" id="quick_email_global" name="email" autocomplete="email" placeholder="Email">
						</div>
						<div class="mb-3">
							<label for="quick_password_global" class="form-label">Contraseña</label>
							<input type="password" class="form-control" id="quick_password_global" name="password" autocomplete="current-password" placeholder="Contraseña">
						</div>
						<div class="d-flex justify-content-between align-items-center">
							  <a class="small text-decoration-underline" href="/password/forgot" data-no-pjax="true">¿Olvidaste tu contraseña?</a>
							  <a class="small text-decoration-underline" href="/welcome#registro" data-no-pjax="true">Crear cuenta</a>
						</div>
					</form>
				`,
			btnsType: 'ac',
			onClickYes: async () => {
				// Helper to disable/enable footer buttons and change confirm label
				const setFooterButtons = (disabled, setLabelToLogin) => {
					try {
						const footerBtns = document.querySelectorAll('#' + modalId + ' .modal-footer button');
						footerBtns.forEach(b => {
							try { b.disabled = !!disabled; } catch (_) { }
							// change confirm button label when disabling
							if (b.id === `modalConfirmBtn_${modalId}`) {
								try { b.innerText = setLabelToLogin ? 'Iniciar sesión' : 'Confirmar'; } catch (_) { }
							}
						});
					} catch (_) { }
				};

				// Immediately disable buttons to prevent double clicks
				setFooterButtons(true, true);

				const $form = $('#quickLoginFormGlobal');
				const email = $('#quick_email_global').val()?.toString().trim();
				const password = $('#quick_password_global').val()?.toString().trim();
				if (!email || !password) {
					// restore buttons when validation fails
					setFooterButtons(false, false);
					modalNotification('Completa los campos', 'Ingresa email y contraseña.', { template: 'warning' });
					return;
				}
				const url = $form.attr('action');
				try {
					const fd = new FormData($form[0]);
					fd.set('email', email);
					// encrypt password client-side to avoid sending plaintext in payload
					try {
						const pub = await fetch('/auth/public-key').then(r => r.json()).then(j => j.public_key);
						if (pub) {
							const enc = await (async function encryptWithPem(pem, text) {
								// strip header/footer
								const b64 = pem.replace(/-----BEGIN PUBLIC KEY-----/g, '').replace(/-----END PUBLIC KEY-----/g, '').replace(/\s+/g, '');
								const raw = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
								// Use SHA-1 for OAEP to match server-side OpenSSL default
								const key = await crypto.subtle.importKey('spki', raw.buffer, { name: 'RSA-OAEP', hash: 'SHA-1' }, false, ['encrypt']);
								const encBuf = await crypto.subtle.encrypt({ name: 'RSA-OAEP' }, key, new TextEncoder().encode(text));
								const encB64 = btoa(String.fromCharCode(...new Uint8Array(encBuf)));
								return encB64;
							})(pub, password);
							fd.set('password_enc', enc);
							fd.delete('password');
						}
					} catch (_) {
						// fallback: send plaintext if encryption fails
						fd.set('password', password);
					}
					const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
					let attempt = 0; let res;
					async function doPost() {
						const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
						return axios.post(url, fd, {
							withCredentials: true,
							headers: {
								'X-CSRF-TOKEN': csrf,
								'X-Requested-With': 'XMLHttpRequest'
							}
						});
					}
					// Helper para mostrar modal de reenvío de verificación
					const showResendVerification = () => {
						modalConfirm({
							title: 'Cuenta no verificada',
							body: `<p>Tu email <code>${window.escapeHtml(email)}</code> aún no está verificado.</p><p>¿Deseas enviar un nuevo enlace de verificación?</p>`,
							btnsType: 'ac',
							confirmLabel: 'Reenviar enlace',
							cancelLabel: 'Cancelar',
							onClickYes: async () => {
								try {
									await axios.post('/email/verification-notification', { email }, { headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')||{}).content || '', 'X-Requested-With': 'XMLHttpRequest' } });
									modalNotification('Enlace reenviado', 'Si la cuenta existe y no está verificada, se envió el correo.', { template: 'success' });
								} catch (_) {
									modalNotification('Error', 'No se pudo reenviar el enlace.', { template: 'danger' });
								}
							}
						}, 'normal', { centered: true });
						// restaurar botón principal a "Iniciar sesión"
						setFooterButtons(false, true);
					};

					while (attempt < 2) {
						try { res = await doPost(); break; }
						catch (err) {
							const status = err?.response?.status;
							// Detectar respuesta de cuenta no verificada en error 403/422
							let respData = err?.response?.data;
							if (typeof respData === 'string') { try { respData = JSON.parse(respData); } catch(_){} }
							const unverified = (respData && (respData.verify_email || respData.unverified)) || (status === 403 && (respData?.message||'').toLowerCase().includes('verificar'));
							if (unverified) { showResendVerification(); return; }
							if (status === 419) {
								// Refresh token then retry once
								try {
									const r = await axios.get('/auth/csrf-refresh', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
									if (r?.data?.token) {
										const meta = document.querySelector('meta[name="csrf-token"]');
										if (meta) meta.setAttribute('content', r.data.token);
										const hidden = $form[0].querySelector('input[name="_token"]');
										if (hidden) hidden.value = r.data.token;
									}
								} catch (_) { }
								attempt++;
								continue;
							}
							throw err; // non-CSRF error distinto
						}
					}
				// Email no verificado (éxito con flag) – mostrar modal de reenvío
				if (res?.data?.verify_email || res?.data?.unverified) { showResendVerification(); return; }
				if (res?.data && res.data.rejected && res.data.redirect) {
					// Rejected application: show notes briefly if present, then follow redirect
					try { if (res.data.notes) modalNotification('Solicitud rechazada', window.escapeHtml(String(res.data.notes)), { template: 'warning' }, false); } catch (_) { }
					try { window.__isAuth = true; } catch (_) { }
					try { if (typeof updateHeaderCTA === 'function') updateHeaderCTA(); } catch (_) { }
					try { if (typeof startHeartbeat === 'function') startHeartbeat(60); } catch (_) { }
					window.location.href = res.data.redirect;
					return;
				}
				if (res?.data && res.data.under_review && res.data.redirect) {
					// Mark client as authenticated before following redirect so header
					// UI updates (CTA hide, heartbeat) can run without a full reload.
					try { window.__isAuth = true; } catch (_) { }
					try { if (typeof updateHeaderCTA === 'function') updateHeaderCTA(); } catch (_) { }
					try { if (typeof startHeartbeat === 'function') startHeartbeat(60); } catch (_) { }
					window.location.href = res.data.redirect;
					return;
				}
				const target = res?.data?.redirect || '/';
				try { window.__isAuth = true; } catch (_) { }
				try { if (typeof updateHeaderCTA === 'function') updateHeaderCTA(); } catch (_) { }
				try { if (typeof startHeartbeat === 'function') startHeartbeat(60); } catch (_) { }
				// navigation will unload page; no need to re-enable buttons
				window.location.href = target;
			} catch(err) {
				const res = err?.response;
				// Normalize response data: backend sometimes returns JSON as string
				let respData = res?.data;
				if (typeof respData === 'string') {
					try { respData = JSON.parse(respData); } catch (_) { }
				}
				// If backend explicitly returned rejected info as part of a 4xx
				// response, follow the redirect and show notes when provided.
				if (respData && respData.rejected && respData.redirect) {
					try { if (respData.notes) modalNotification('Solicitud rechazada', window.escapeHtml(String(respData.notes)), { template: 'warning' }, false); } catch (_) { }
					try { window.__isAuth = true; } catch (_) { }
					try { if (typeof updateHeaderCTA === 'function') updateHeaderCTA(); } catch (_) { }
					try { if (typeof startHeartbeat === 'function') startHeartbeat(60); } catch (_) { }
					window.location.href = respData.redirect;
					return;
				}
				if (respData && respData.under_review && respData.redirect) {
					try { window.__isAuth = true; } catch (_) { }
					try { if (typeof updateHeaderCTA === 'function') updateHeaderCTA(); } catch (_) { }
					try { if (typeof startHeartbeat === 'function') startHeartbeat(60); } catch (_) { }
					window.location.href = respData.redirect;
					return;
				}
				let message = 'Email o contraseña incorrectos.';
				if (respData && typeof respData === 'object') {
					if (respData.errors && typeof respData.errors === 'object') {
						const firstKey = Object.keys(respData.errors)[0];
						const firstMsg = Array.isArray(respData.errors[firstKey]) ? respData.errors[firstKey][0] : respData.errors[firstKey];
						message = firstMsg || respData.message || message;
					} else if (respData.message) {
						message = respData.message;
					} else {
						try { message = JSON.stringify(respData); } catch (_) { }
					}
				} else if (res && typeof res.data === 'string') {
					message = res.data;
				} else if (err?.isAxiosError && !res && err?.message) {
					message = err.message;
				}
				const status = res?.status;
				const severe = (!!err?.isAxiosError && !res) || (typeof status === 'number' && status >= 500);
				const title = severe ? 'Error del servidor' : 'Error de acceso';
				const template = severe ? 'danger' : 'warning';
				const detailCfg = { xhr: res, fncErr: 'quickLogin', page: 'global', body: 'Error al iniciar sesión' };
				// on error re-enable buttons so the user can retry
				setFooterButtons(false, false);
				modalNotification(title, window.escapeHtml(String(message)), { template, delayAutoClose: 6000 }, !!severe, detailCfg);
			}
		},
			closeClick: false
	};

	modalConfirm(bodyHtml, 'normal', { centered: true, scrollable: false, size: '' });
	setTimeout(() => document.getElementById('quick_email_global')?.focus(), 150);
}

	// Move focus out of modal to avoid aria-hidden focus race when hiding.
	function ensureFocusMovedOut(modal) {
	try {
		const active = document.activeElement;
		if (!active) return;
		if (!modal || !modal.contains) return;
		if (!modal.contains(active)) return;
		// If focused element is inside modal, move focus to a temporary sentinel
		const sentinelId = 'pg-modal-focus-sentinel';
		let sentinel = document.getElementById(sentinelId);
		if (!sentinel) {
			sentinel = document.createElement('button');
			sentinel.id = sentinelId;
			sentinel.setAttribute('aria-hidden', 'true');
			sentinel.style.position = 'fixed';
			sentinel.style.left = '-9999px';
			sentinel.style.width = '1px';
			sentinel.style.height = '1px';
			sentinel.style.opacity = '0';
			sentinel.style.pointerEvents = 'none';
			document.body.appendChild(sentinel);
		}
		try {
			// blur active element then focus sentinel
			if (typeof active.blur === 'function') try { active.blur(); } catch (_) { }
			sentinel.focus({ preventScroll: true });
			// remove sentinel after a short delay to allow hide animation to proceed
			setTimeout(() => { try { sentinel.parentNode && sentinel.parentNode.removeChild(sentinel); } catch (_) { } }, 600);
		} catch (_) { }
	} catch (_) { }
}

// Capture-phase click handler so we can close modals before PJAX or other handlers run
function captureClickHandler(e) {
	try {
		const a = e.target.closest ? e.target.closest('a[href^="/welcome"]') : null;
		if (!a) return;
		// Only act if the link is inside a modal
		if (a.closest && a.closest('.modal')) {
			closeShownModals();
		}
	} catch (err) {
		// ignore
	}
}

function closeShownModals() {
	document.querySelectorAll('.modal.show').forEach(m => {
		try { ensureFocusMovedOut(m); } catch (_) { }
		// small delay to let UA update focus/accessible tree before aria-hidden changes
		setTimeout(() => {
			try {
				if (window.bootstrap && typeof bootstrap.Modal?.getInstance === 'function') {
					const inst = bootstrap.Modal.getInstance(m);
					if (inst && typeof inst.hide === 'function') { inst.hide(); return; }
				}
			} catch (_) { }
			try {
				if (window.jQuery && $(m).modal) { $(m).modal('hide'); return; }
			} catch (_) { }
			try { if (window.bootstrap && typeof bootstrap.Modal === 'function') { new bootstrap.Modal(m).hide(); } } catch (_) { }
		}, 30);
	});
}

function observeModals() {
	if (window.MutationObserver == null) return;
	_observer = new MutationObserver((mutationsList) => {
		for (const m of mutationsList) {
			for (const node of m.addedNodes) {
				if (!(node instanceof HTMLElement)) continue;
				if (node.classList && node.classList.contains('modal')) {
					// attach a click handler to the modal so links inside close it synchronously
					node.addEventListener('click', function modalLocalClick(ev) {
						try {
							const a = ev.target.closest ? ev.target.closest('a[href^="/welcome"]') : null;
							if (a) {
								// move focus out before hiding
								try { ensureFocusMovedOut(node); } catch (_) { }
								// small delay to let UA update focus/accessible tree before aria-hidden changes
								setTimeout(() => {
									try {
										if (window.bootstrap && typeof bootstrap.Modal?.getInstance === 'function') {
											const inst = bootstrap.Modal.getInstance(node);
											if (inst && typeof inst.hide === 'function') { inst.hide(); return; }
										}
									} catch (_) { }
									try { if (window.jQuery && $(node).modal) { $(node).modal('hide'); return; } } catch (_) { }
									try { if (window.bootstrap && typeof bootstrap.Modal === 'function') { new bootstrap.Modal(node).hide(); } } catch (_) { }
								}, 30);
							}
						} catch (_) { }
					});
				}
			}
		}
	});
	_observer.observe(document.body, { childList: true, subtree: true });
}

function attachHandlers() {
	// header CTA click (delegated) - use delegation so dynamic CTAs are always handled
	$(document).off('click' + ns, ".site-header .btn-cta[href='/welcome']")
		.on('click' + ns, ".site-header .btn-cta[href='/welcome']", function (e) {
			try {
				if (isDesktop() && typeof openQuickLoginModal === 'function') {
					e.preventDefault();
					e.stopImmediatePropagation();
					openQuickLoginModal();
					return false;
				}
			} catch (_) { /* ignore */ }
		});

	// prevent accidental submit of other quickLogin forms on page
	$(document).off('submit' + ns).on('submit' + ns, '#quickLoginForm', function (e) { e.preventDefault(); });

	// capture-phase listener (use native API so it runs before many PJAX handlers)
	if (!_captureHandler) {
		_captureHandler = captureClickHandler;
		document.addEventListener('click', _captureHandler, true);
	}

	observeModals();
}

function detachHandlers() {
	// remove delegated CTA handler
	try { $(document).off('click' + ns, ".site-header .btn-cta[href='/welcome']"); } catch (_) { }
	$(document).off('submit' + ns);
	if (_captureHandler) {
		try { document.removeEventListener('click', _captureHandler, true); } catch (_) { }
		_captureHandler = null;
	}
	if (_observer) {
		try { _observer.disconnect(); } catch (_) { }
		_observer = null;
	}
}

function init() {
	if (_inited) return;
	attachHandlers();
	_inited = true;
	// mark presence globally
	window.__quickLoginGlobalAttached = true;
}

function destroy() {
	if (!_inited) return;
	detachHandlers();
	_inited = false;
	// Do not unset __quickLoginGlobalAttached because other modules may rely on it
}

// Auto-init on DOM ready for backwards compatibility when this file is included globally
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	// already ready
	init();
}

// Expose module to global so a PJAX page loader can call init/destroy explicitly
global.quickLoginPartial = { init, destroy };

}) (window, jQuery);
