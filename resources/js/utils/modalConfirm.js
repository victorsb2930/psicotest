import { preventDefault } from "tom-select/utils";

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

export function modalConfirm(bodyHtml, modalType = 'normal', options = {}) {
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

		const footerHtml = modalType === 'normal'
			? `<div class="modal-footer border-0 pt-0">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">${labels[0]}</button>
					<button type="button" class="btn btn-primary" id="modalConfirmBtn_${modalId}">${labels[1]}</button>
			</div>`
			: '';

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

	// Reasignar handler del botón Confirmar (si existe)
	const $confirmBtn = $modal.find(`#modalConfirmBtn_${modalId}`);
	$confirmBtn.off('click').on('click', function () {
		if (typeof bodyHtml.onClickYes === 'function') {
			bodyHtml.onClickYes();
		}
		const closeClick = bodyHtml.closeClick ?? true;
		if (closeClick) {
			closeAllModals();
		}
	});

	// Mostrar el modal con backdrop estático por defecto
	if (opts.zIndex) {
		$modal.css('z-index', opts.zIndex);
	}
	const bsModal = bootstrap.Modal.getOrCreateInstance($modal[0], {
		backdrop: opts.backdrop,
		keyboard: opts.keyboard
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

	// Resetear transform y estado al cerrar, para próxima apertura centrada
	$modal.off('hidden.bs.modal.reset').on('hidden.bs.modal.reset', function(){
		const $dialog = $(this).find('.modal-dialog');
		$dialog.css({ transform: '', willChange: '' }).removeData('tx').removeData('ty');
	});
}

// Cierra todos los modales abiertos de Bootstrap 5
function closeAllModals() {
	$('.modal.show').each(function () {
		const instance = bootstrap.Modal.getInstance(this);
		instance?.hide();
	});
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