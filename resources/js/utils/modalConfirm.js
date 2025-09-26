// modalConfirm: Crea o actualiza un modal Bootstrap 5.3 y lo muestra.
const ModalLocalDefaults = {
	backdrop: 'static',
	keyboard: true,
	zIndex: 1055,
	centered: true,
	scrollable: true,
	size: '',
	fullscreen: '',
	dialogClasses: '',
	contentClasses: '',
	bodyClasses: '',
	draggable: true
};

// Global focus-safety: ensure we have a sentinel to move focus to when a
// modal is about to be hidden. This reduces races where a focused element
// inside the modal remains focused while aria-hidden is applied.
let _pg_modal_focus_handlers_installed = false;
function _ensurePgModalFocusHandlers() {
	if (_pg_modal_focus_handlers_installed) return;
	_pg_modal_focus_handlers_installed = true;

	function ensureSentinel() {
		let s = document.getElementById('pg-modal-focus-sentinel');
		if (!s) {
			s = document.createElement('div');
			s.id = 'pg-modal-focus-sentinel';
			s.setAttribute('tabindex', '-1');
			s.style.position = 'fixed';
			s.style.left = '-9999px';
			s.style.width = '1px';
			s.style.height = '1px';
			s.style.overflow = 'hidden';
			document.body.appendChild(s);
		}
		return s;
	}

	// When the user presses pointers on a dismiss control inside a modal, or
	// when Escape is pressed, move focus to the sentinel before the event
	// bubbles to Bootstrap so aria-hidden is not applied to an element that
	// still has focus.
	document.addEventListener('pointerdown', function (ev) {
		try {
			const target = ev.target;
			if (!target) return;
			const dismiss = target.closest && target.closest('[data-bs-dismiss="modal"]');
			if (dismiss) {
				// If the activeElement is inside the same modal, blur it and
				// move focus to the sentinel synchronously.
				const active = document.activeElement;
				const modal = target.closest('.modal');
				if (active && modal && active.closest && active.closest('.modal') === modal) {
					try { active.blur(); } catch (_) {}
					try { ensureSentinel().focus(); } catch (_) { document.body.focus(); }
				}
			}
		} catch (_) {}
	}, true);

	document.addEventListener('keydown', function (ev) {
		try {
			if (ev.key === 'Escape' || ev.key === 'Esc' || ev.keyCode === 27) {
				const active = document.activeElement;
				if (active && active.closest && active.closest('.modal')) {
					try { active.blur(); } catch (_) {}
					try { ensureSentinel().focus(); } catch (_) { document.body.focus(); }
				}
			}
		} catch (_) {}
	}, true);
}

export function modalConfirm(bodyHtml, modalType = 'normal', options = {}) {
	// Ensure global focus-safety handlers are installed (idempotent)
	try { _ensurePgModalFocusHandlers(); } catch(_){}

	const opts = { ...modalConfirm.defaults, ...options };
	// Soporta bodyHtml.icona o bodyHtml.icon (alias)
	const modalId = bodyHtml.modalId || `modal-${(window.uuidv7 ? window.uuidv7() : Date.now())}`;
	const iconClass = bodyHtml.icona || bodyHtml.icon; // FontAwesome u otra librería
	const iconColor = bodyHtml.iconColor || bodyHtml.iconColour;
	const titleText = bodyHtml.title || 'Título por defecto';
	const bodyContent = bodyHtml.body || '';
	const btnsType = (bodyHtml.btnsType === 'ny' || bodyHtml.btnsType === 'ac') ? bodyHtml.btnsType : 'ny';
	const labels = {
		ny: ['No', 'Sí'],
		ac: ['Cancelar', 'Confirmar']
	}[btnsType];

	let $modal = $(`#${modalId}`);

	// Crea el modal si no existe
	if ($modal.length === 0) {
		const closeBtnHtml = modalType === 'dialog'
			? '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
			: '';

		const iconHtml = iconClass
			? `<span class="modal-icon bg-white rounded-circle d-inline-flex align-items-center justify-content-center me-3" style="width:56px;height:56px;box-shadow:0 6px 18px rgba(0,0,0,0.06);border:1px solid rgba(0,0,0,0.06);">
					<i class="fa ${iconClass} fa-2x" ${iconColor ? `style="color:${iconColor}"` : ''}></i>
			   </span>`
			: '';

		let footerHtml = '';
		if (modalType === 'normal') {
			// Allow callers to disable the footer/buttons explicitly. This is handy
			// when the modal is informational and no actions are needed.
			const hideFooter = bodyHtml.noFooter === true || bodyHtml.noButtons === true || bodyHtml.buttons === false || bodyHtml.btnsType === 'none';
			if (hideFooter) {
				footerHtml = '';
			} else if (Array.isArray(bodyHtml.buttons) && bodyHtml.buttons.length) {
				// If caller provided a custom buttons array, render those instead of the default two-button footer
				const parts = bodyHtml.buttons.map((b, i) => {
					const id = `modalBtn_${modalId}_${i}`;
					const cls = b.className || b.cls || (b.primary ? 'btn-primary' : 'btn-secondary');
					const text = b.text || b.label || `Button ${i+1}`;
					const dismiss = b.dismiss || b.closeOnClick ? ' data-bs-dismiss="modal"' : '';
					return `<button type="button" id="${id}" class="btn ${cls}"${dismiss}>${text}</button>`;
				});
				footerHtml = `<div class="modal-footer border-0 pt-0">${parts.join('')}</div>`;
			} else {
				footerHtml = `<div class="modal-footer border-0 pt-0">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">${labels[0]}</button>
					<button type="button" class="btn btn-primary" id="modalConfirmBtn_${modalId}">${labels[1]}</button>
				</div>`;
			}
		} else {
			footerHtml = '';
		}

		const dialogCls = [
			'modal-dialog',
			opts.centered ? 'modal-dialog-centered' : '',
			opts.scrollable ? 'modal-dialog-scrollable' : '',
			opts.size ? `modal-${opts.size}` : '',
			opts.fullscreen ? (typeof opts.fullscreen === 'string' ? `modal-fullscreen-${opts.fullscreen}` : 'modal-fullscreen') : '',
			opts.dialogClasses || ''
		].filter(Boolean).join(' ');

	const contentCls = ['modal-content shadow-lg rounded-4', opts.contentClasses || ''].filter(Boolean).join(' ');
	const bodyCls = ['modal-body py-4 px-5', opts.bodyClasses || ''].filter(Boolean).join(' ');

		const template = `
			<div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true" role="dialog">
				<div class="${dialogCls}" role="document">
				<div class="${contentCls}">
				<div class="modal-header border-0 d-flex align-items-center" style="padding:1rem 1.25rem;">
					${iconHtml}
					<div class="d-flex flex-column">
						<h5 class="modal-title mb-0" id="modalTitle${modalId}" style="font-weight:600; font-size:1.05rem;">${window.escapeHtml(titleText)}</h5>
						<small class="text-muted">${window.escapeHtml(bodyHtml.subtitle || '')}</small>
					</div>
					<div class="ms-auto">${closeBtnHtml}</div>
				</div>
				<div class="${bodyCls}" id="modalBody${modalId}">${bodyContent}</div>
				<div class="px-4 pb-4 d-flex justify-content-end">${footerHtml}</div>
				</div>
			</div>
			</div>`;

		$modal = $(template).appendTo('body');

		// Mark as generated by modalConfirm so we can safely remove it on close
		$modal.attr('data-modal-confirm-generated', '1');

		// Click en el fondo del modal: animación 'shake' sobre el contenido, sin alterar posición del dialog
		$modal.off('click.modalShade').on('click.modalShade', function (e) {
			if (e.target === this) {
				const $content = $(this).find('.modal-content');
				$content.addClass('shake');
				$content.one('animationend', () => $content.removeClass('shake'));
			}
		});
	} else {
		// Si existe, actualizar título y cuerpo
		$modal.find(`#modalTitle${modalId}`).text(titleText);
		$modal.find(`#modalBody${modalId}`).html(bodyContent);
	}

	// Small DOM fixes: ensure close button has visible contrast and confirm button will be focused
	$modal.find('.btn-close').attr('aria-label', 'Cerrar');
	// Place footer buttons to the right
	$modal.find('.modal-footer').addClass('justify-content-end');

	// Reasignar handler del botón Confirmar (si existe) y bind de botones custom
	// Bind confirm button only if it exists (support modals without footers)
	const $confirmBtn = $modal.find(`#modalConfirmBtn_${modalId}`);
	if ($confirmBtn.length) {
		$confirmBtn.off('click').on('click', function () {
			if (typeof bodyHtml.onClickYes === 'function') {
				bodyHtml.onClickYes();
			}
			const closeClick = bodyHtml.closeClick ?? true;
			if (closeClick) {
				closeAllModals();
			}
		});
	}

	// Bind custom buttons if provided as array
	if (Array.isArray(bodyHtml.buttons) && bodyHtml.buttons.length) {
		bodyHtml.buttons.forEach((b, i) => {
			const id = `modalBtn_${modalId}_${i}`;
			const $btn = $modal.find(`#${id}`);
			if ($btn.length) {
				$btn.off('click').on('click', function (ev) {
					// Call action (can be sync or async)
					try {
						if (typeof b.onClick === 'function') {
							const res = b.onClick($modal, ev);
							// If the handler returns a Promise, catch errors
							if (res && typeof res.then === 'function') {
								res.catch(()=>{});
							}
						}
					} catch (_) {}
					// Decide whether to close modals after click
					const shouldClose = ('closeOnClick' in b) ? !!b.closeOnClick : (b.dismiss || b.dismissOnClick || false);
					if (shouldClose) {
						closeAllModals();
					}
				});
			}
		});
	}

	// Mostrar el modal con backdrop estático por defecto
	if (opts.zIndex) {
		$modal.css('z-index', opts.zIndex);
	}
	const bsModal = bootstrap.Modal.getOrCreateInstance($modal[0], {
		backdrop: opts.backdrop,
		keyboard: opts.keyboard
	});

	// Helper: safe hide ensures no focused element remains inside the modal
	// when Bootstrap applies aria-hidden. It blurs active elements, focuses
	// the sentinel and then hides the modal instance.
	function safeHide() {
		try {
			const active = document.activeElement;
			if (active && active.closest && active.closest('.modal') === $modal[0]) {
				try { active.blur(); } catch (_) {}
			}
			// ensure sentinel exists and focus it
			let sentinel = document.getElementById('pg-modal-focus-sentinel');
			if (!sentinel) {
				sentinel = document.createElement('div');
				sentinel.id = 'pg-modal-focus-sentinel';
				sentinel.setAttribute('tabindex', '-1');
				sentinel.style.position = 'fixed';
				sentinel.style.left = '-9999px';
				sentinel.style.width = '1px';
				sentinel.style.height = '1px';
				sentinel.style.overflow = 'hidden';
				document.body.appendChild(sentinel);
			}
			try { sentinel.focus(); } catch (_) { try { document.body.focus(); } catch(_){} }
			// Hide the modal instance (this will set aria-hidden on the modal)
			try {
				const inst = bootstrap.Modal.getInstance($modal[0]);
				inst?.hide();
			} catch (_) {}
		} catch (_) {}
	}

	// Intercept clicks on elements that would normally dismiss the modal
	// to ensure we move focus before letting Bootstrap handle hiding.
	$modal.off('click.modalConfirmDismiss').on('click.modalConfirmDismiss', '[data-bs-dismiss="modal"]', function (ev) {
		ev.preventDefault();
		ev.stopImmediatePropagation();
		safeHide();
	});

	// Intercept Escape key presses inside this modal to hide safely
	$modal.off('keydown.modalConfirmEsc').on('keydown.modalConfirmEsc', function (ev) {
		if (ev.key === 'Escape' || ev.key === 'Esc' || ev.keyCode === 27) {
			ev.preventDefault();
			safeHide();
		}
	});

	// For forms inside the modal, ensure focus is moved before submit
	$modal.off('submit.modalConfirmForm').on('submit.modalConfirmForm', 'form', function (ev) {
		try {
			const active = document.activeElement;
			if (active && active.closest && active.closest('.modal') === $modal[0]) {
				try { active.blur(); } catch (_) {}
			}
			const sentinel = document.getElementById('pg-modal-focus-sentinel');
			if (sentinel) try { sentinel.focus(); } catch (_) {}
		} catch (_) {}
		// allow submission to continue
	});

	// Focus confirm button when modal shown
	$modal.off('shown.bs.modal.focus').on('shown.bs.modal.focus', function () {
		const $btn = $modal.find(`#modalConfirmBtn_${modalId}`);
		if ($btn.length) {
			$btn.trigger('focus');
		}
	});

	// Habilitar arrastre opcional del modal por la cabecera
	if (opts.draggable) {
		try {
			const $dialog = $modal.find('.modal-dialog');
			const $header = $modal.find('.modal-header');
			$header.css('cursor', 'move');
			// Unbind previous handlers to avoid duplicates
			$header.off(`mousedown.pgdrag-${modalId}`).off(`touchstart.pgdrag-${modalId}`);

			const startDrag = (ev) => {
				const rectStart = $dialog[0].getBoundingClientRect();
				const startX = (ev.clientX ?? ev.touches?.[0]?.clientX);
				const startY = (ev.clientY ?? ev.touches?.[0]?.clientY);
				let dragging = true; let engaged = false; let frame = null;
				const baseTx = parseFloat($dialog.data('tx')) || 0;
				const baseTy = parseFloat($dialog.data('ty')) || 0;
				let nextTx = baseTx; let nextTy = baseTy;

				const apply = () => {
					frame = null;
					$dialog.css('transform', `translate3d(${nextTx}px, ${nextTy}px, 0)`);
				};

				const onMove = (e2) => {
					if (!dragging) return;
					const cx = (e2.clientX ?? e2.touches?.[0]?.clientX);
					const cy = (e2.clientY ?? e2.touches?.[0]?.clientY);
					const dx = cx - startX; const dy = cy - startY;
					if (!engaged) {
						const threshold = 3;
						if (Math.abs(dx) + Math.abs(dy) < threshold) return;
						engaged = true;
						$dialog.css({ willChange: 'transform' });
						$('body').css('user-select', 'none');
					}
					// Clamp con tolerancia: permitimos que parte del modal salga de pantalla.
					const vw = window.innerWidth; const vh = window.innerHeight; const w = rectStart.width; const h = rectStart.height;
					let newLeft = rectStart.left + dx; let newTop = rectStart.top + dy;
					const marginX = 16; // deja al menos 16px visibles a los lados
					const headerH = ($header && $header.length ? $header.outerHeight() : 0) || 44; // deja visible la cabecera
					const keepY = Math.max(32, Math.min(96, headerH + 8));
					const minLeft = -(w - marginX);
					const maxLeft = vw - marginX;
					const minTop = -(h - keepY);
					const maxTop = vh - keepY;
					newLeft = Math.max(minLeft, Math.min(newLeft, maxLeft));
					newTop = Math.max(minTop, Math.min(newTop, maxTop));
					const dxc = newLeft - rectStart.left; const dyc = newTop - rectStart.top;
					nextTx = baseTx + dxc; nextTy = baseTy + dyc;
					if (!frame) frame = requestAnimationFrame(apply);
				};

				const onUp = () => {
					dragging = false; engaged = false;
					$dialog.css({ willChange: '' });
					$('body').css('user-select', '');
					$dialog.data('tx', nextTx).data('ty', nextTy);
					$(document)
						.off(`mousemove.pgdrag-${modalId}`, onMove)
						.off(`mouseup.pgdrag-${modalId}`, onUp)
						.off(`touchmove.pgdrag-${modalId}`, onMove)
						.off(`touchend.pgdrag-${modalId}`, onUp);
				};

				$(document)
					.on(`mousemove.pgdrag-${modalId}`, onMove)
					.on(`mouseup.pgdrag-${modalId}`, onUp)
					.on(`touchmove.pgdrag-${modalId}`, onMove)
					.on(`touchend.pgdrag-${modalId}`, onUp);
			};

			$header.on(`mousedown.pgdrag-${modalId}`, function (e) {
				if (e.which !== 1) return; // solo click izquierdo
				e.preventDefault();
				startDrag(e);
			});
			$header.on(`touchstart.pgdrag-${modalId}`, function (ev) {
				ev.preventDefault();
				startDrag(ev.originalEvent || ev);
			});
		} catch (_) {}
	}
	bsModal.show();

	// Remember the element that had focus when opening so we can restore it
	try {
		$modal[0].__modalConfirmOpener = document.activeElement;
	} catch (_) {}

	// Before the modal is hidden, ensure focus is moved away from any focused
	// element inside the modal. This avoids the "descendant retained focus"
	// accessibility error when aria-hidden is applied.
	$modal.off('hide.bs.modal.modalConfirmFocus').on('hide.bs.modal.modalConfirmFocus', function () {
		try {
			// Create or reuse a global focus sentinel so we always have a safe
			// place to move focus before the modal is hidden. This is better
			// than focusing body because it avoids scroll jumps.
			let sentinel = document.getElementById('pg-modal-focus-sentinel');
			let createdSentinel = false;
			if (!sentinel) {
				sentinel = document.createElement('div');
				sentinel.id = 'pg-modal-focus-sentinel';
				sentinel.setAttribute('tabindex', '-1');
				// off-screen, non-intrusive
				sentinel.style.position = 'fixed';
				sentinel.style.left = '-9999px';
				sentinel.style.width = '1px';
				sentinel.style.height = '1px';
				sentinel.style.overflow = 'hidden';
				document.body.appendChild(sentinel);
				createdSentinel = true;
			}

			// If an element inside the modal has focus, blur it first to remove
			// focus from the hidden tree (some browsers may re-focus later,
			// but blurring reduces the race window).
			const active = document.activeElement;
			if (active && active.closest && active.closest('.modal')) {
				try { active.blur(); } catch (_) {}
			}

			// Focus the sentinel so that when the modal is hidden there is no
			// focused element inside the modal subtree.
			try { sentinel.focus(); } catch (_) {
				// fallback to body if focus fails
				document.body.setAttribute('tabindex', '-1');
				document.body.focus();
			}

			// Store whether we created the sentinel so cleanup can remove it later
			this.__modalConfirmCreatedSentinel = createdSentinel;
		} catch (e) { /* ignore */ }
	});

	// After the modal is fully hidden, dispose the Bootstrap instance, remove
	// generated modals from the DOM and clean jQuery handlers to avoid leaks.
	$modal.off('hidden.bs.modal.modalConfirmCleanup').on('hidden.bs.modal.modalConfirmCleanup', function () {
		try {
			// Blur any focused element that might still be inside
			const active = document.activeElement;
			if (active && active.closest && active.closest('.modal')) {
				try { active.blur(); } catch(_){}
			}
			// Dispose bootstrap instance
			const inst = bootstrap.Modal.getInstance(this);
			try { inst?.dispose(); } catch(_){}
			// Unbind all jQuery events attached to this modal
			try { $(this).off(); } catch(_){}
			// If this modal was generated by modalConfirm, remove it from DOM so
			// subsequent calls recreate a fresh instance. If it was not generated
			// (e.g., a static modal in the page), keep the element but clean state.
			const $self = $(this);
			if ($self.attr('data-modal-confirm-generated') === '1') {
				try { $self.remove(); } catch(_){}
			} else {
				// For static modals, ensure aria-hidden/display attributes are reset
				try {
					$self.removeClass('show').attr('aria-hidden', 'true').css('display', 'none');
				} catch(_){}
			}

			// If we created a sentinel for focus, remove it when no modals are open
			try {
				const sentinel = document.getElementById('pg-modal-focus-sentinel');
				// Only remove sentinel if we created it for this modal and there are
				// no other visible modals.
				const anyOpen = document.querySelectorAll('.modal.show').length > 0;
				if (!anyOpen && sentinel && this.__modalConfirmCreatedSentinel) {
					try { sentinel.remove(); } catch(_){}
				}
			} catch (_) {}
		} catch (e) {
			// best-effort cleanup
		}
	});

	// Resetear transform y estado al cerrar, para próxima apertura centrada
	$modal.off('hidden.bs.modal.reset').on('hidden.bs.modal.reset', function(){
		const $dialog = $(this).find('.modal-dialog');
		$dialog.css({ transform: '', willChange: '' }).removeData('tx').removeData('ty');
	});
}

// Cierra todos los modales abiertos de Bootstrap 5
function closeAllModals() {
	// Workaround accessibility issue: if an element inside a modal retains focus
	// when the modal is hidden, some browsers block setting aria-hidden on that
	// ancestor. Move focus to a safe element (body) before hiding modals.
	try {
		const active = document.activeElement;
		let movedFocus = false;
		let prevTabindex = null;
		if (active && typeof active.closest === 'function' && active.closest('.modal')) {
			// Make body focusable temporarily and move focus there
			prevTabindex = document.body.getAttribute('tabindex');
			document.body.setAttribute('tabindex', '-1');
			document.body.focus();
			movedFocus = true;
		}
		$('.modal.show').each(function () {
			const instance = bootstrap.Modal.getInstance(this);
			instance?.hide();
		});
		// Restore previous tabindex state
		if (movedFocus) {
			if (prevTabindex === null) {
				document.body.removeAttribute('tabindex');
			} else {
				document.body.setAttribute('tabindex', prevTabindex);
			}
		}
	} catch (e) {
		// Fallback: try to hide modals anyway
		$('.modal.show').each(function () {
			const instance = bootstrap.Modal.getInstance(this);
			instance?.hide();
		});
	}
}

// Defaults accesibles/modificables
modalConfirm.defaults = ModalLocalDefaults;

// Defaults para flujos secuenciales (Opción B puede depender de estos)
modalConfirm.sequenceDefaults = {
	defaultB: {}, // valores por defecto que se mezclarán con bodyHtml de la Opción B
};

/**
 * Muestra dos modales en secuencia: primero la `optionA` y si el usuario confirma, muestra la `optionB`.
 * La `optionB` puede ser un objeto `bodyHtml` o una función que reciba los `defaultB` y devuelva un objeto `bodyHtml`.
 * Retorna una Promise que resuelve con un objeto: { confirmedA: boolean, confirmedB: boolean }
 */
export function modalConfirmSequence(optionA, optionBOrFactory, sequenceOpts = {}) {
	const defaults = { ...modalConfirm.sequenceDefaults, ...sequenceOpts };
	return new Promise((resolve) => {
		const modalIdA = optionA.modalId || `modal-seq-a-${Date.now()}`;
		let confirmedA = false;

		const bodyA = { ...optionA, modalId: modalIdA };

		// Interceptar confirm de A
		bodyA.onClickYes = () => {
			confirmedA = true;
			// Cerrar modal A y abrir B después de un pequeño delay para permitir la animación
			closeAllModals();
			setTimeout(() => {
				// Construir body para B usando defaults.defaultB
				let bodyB = {};
				if (typeof optionBOrFactory === 'function') {
					bodyB = optionBOrFactory(defaults.defaultB || {});
				} else {
					bodyB = { ...optionBOrFactory };
				}
				// Mezclar defaults: los valores de bodyB deben sobreescribir los defaults
				bodyB = { ...(defaults.defaultB || {}), ...bodyB };
				const modalIdB = bodyB.modalId || `modal-seq-b-${Date.now()}`;
				let confirmedB = false;
				bodyB.modalId = modalIdB;

				bodyB.onClickYes = () => {
					confirmedB = true;
					closeAllModals();
					resolve({ confirmedA: true, confirmedB: true });
				};

				// Si se cierra B sin confirmar, resolver con confirmedB = false
				// Registramos un listener una sola vez
				const $onceB = $(() => {
					const $modalB = $(`#${modalIdB}`);
					$modalB.one('hidden.bs.modal', function () {
						if (!confirmedB) {
							resolve({ confirmedA: true, confirmedB: false });
						}
					});
				});

				modalConfirm(bodyB, 'normal', defaults);
			}, 260);
		};

		// Mostrar modal A
		modalConfirm(bodyA, 'normal', defaults);

		// Si se cierra A sin confirmar, resolver con falses (attach after creation)
		const $modalA = $(`#${modalIdA}`);
		$modalA.one('hidden.bs.modal', function () {
			if (!confirmedA) {
				resolve({ confirmedA: false, confirmedB: false });
			}
		});
	});
}