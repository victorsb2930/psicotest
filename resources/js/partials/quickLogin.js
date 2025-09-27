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
		const bodyHtml = {
			title: 'Iniciar sesión',
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
							  <a class="small text-decoration-underline" href="/welcome#forgot" data-no-pjax="true">¿Olvidaste tu contraseña?</a>
							  <a class="small text-decoration-underline" href="/welcome#registro" data-no-pjax="true">Crear cuenta</a>
						</div>
					</form>
				`,
			btnsType: 'ac',
			onClickYes: async () => {
				const $form = $('#quickLoginFormGlobal');
				const email = $('#quick_email_global').val()?.toString().trim();
				const password = $('#quick_password_global').val()?.toString().trim();
				if (!email || !password) {
					modalNotification('Completa los campos', 'Ingresa email y contraseña.', { template: 'warning' });
					return;
				}
				const url = $form.attr('action');
				try {
					const fd = new FormData($form[0]);
					fd.set('email', email);
					fd.set('password', password);
					const res = await axios.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
					if (res?.data && res.data.under_review && res.data.redirect) {
						window.location.href = res.data.redirect;
						return;
					}
					const target = res?.data?.redirect || '/';
					window.location.href = target;
				} catch (err) {
					const res = err?.response;
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
							try { message = JSON.stringify(res.data); } catch (_) { }
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
				if (typeof active.blur === 'function') try { active.blur(); } catch(_){}
				sentinel.focus({ preventScroll: true });
				// remove sentinel after a short delay to allow hide animation to proceed
				setTimeout(()=>{ try { sentinel.parentNode && sentinel.parentNode.removeChild(sentinel); } catch(_){} }, 600);
			} catch (_) {}
		} catch (_) {}
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
				try { ensureFocusMovedOut(m); } catch(_) {}
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
												try { ensureFocusMovedOut(node); } catch(_) {}
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
		try { $(document).off('click' + ns, ".site-header .btn-cta[href='/welcome']"); } catch (_) {}
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

})(window, jQuery);
