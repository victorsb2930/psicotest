// modalNotification: Crea una alerta tipo toast apilable (arriba-derecha) con variantes Bootstrap 5.3 e íconos de Bootstrap Icons.
// Usa jQuery global y clases de Bootstrap; no importa Bootstrap internamente.
const ModalNotificationDefaults = {
	template: 'warning',
	delayAutoClose: 5000,
	zIndex: 1080,
	gap: 8,
	topOffset: 8,
	alignClasses: 'end-0 me-3'
};

export function modalNotification(
	title,
	description,
	options = {},
	showDetails = false,
	detailConfig = {}
) {
	const opts = { ...ModalNotificationDefaults, ...options };
	const { variant, icon } = mapVariant(opts.template);

	// Construir alerta
	const id = `notif-${(window.uuidv7 ? window.uuidv7() : `${Date.now()}-${Math.floor(Math.random() * 1000)}`)}`;
	const $el = $(
		`<div id="${id}" class="alert alert-${variant} position-fixed ${opts.alignClasses}" role="alert" data-notification="true" style="z-index:${opts.zIndex}; min-width:260px;">
				<div class="d-flex align-items-start gap-2">
					<i class="bi ${icon} fs-5 mt-1 flex-shrink-0"></i>
					<div class="flex-grow-1">
						<div class="d-flex justify-content-between align-items-start">
							<strong class="me-2">${window.escapeHtml(title)}</strong>
							<button type="button" class="btn-close" aria-label="Close"></button>
						</div>
						<div class="mt-1">${description}</div>
					</div>
				</div>
		</div>`
	);

	// Cerrar con botón
	$el.on('click', '.btn-close', function (e) {
		e.stopPropagation();
		$el.remove();
		updateNotificationPositions();
	});

	// Click para ver detalles (solo si corresponde)
	if (variant === 'danger' && showDetails) {
		$el.css('cursor', 'pointer');
		$el.on('click', function (e) {
			if ($(e.target).is('.btn-close')) return;
			showErrorDetailModal(detailConfig);
		});
	} else {
		// Autocierre
		setTimeout(() => { $el.remove(); updateNotificationPositions(); }, opts.delayAutoClose);
	}

	// Agregar al DOM y reposicionar
	$('body').append($el);
	updateNotificationPositions();
}

// Mapea la plantilla a variante Bootstrap y a un ícono de Bootstrap Icons
function mapVariant(template) {
	const key = String(template || 'warning').toLowerCase();
	const aliases = { error: 'danger' };
	const variant = aliases[key] || key;
	const allowed = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
	const safeVariant = allowed.includes(variant) ? variant : 'warning';
	const iconMap = {
		primary: 'bi-info-circle',
		secondary: 'bi-info-circle',
		success: 'bi-check-circle',
		danger: 'bi-x-circle',
		warning: 'bi-exclamation-triangle',
		info: 'bi-info-circle',
		light: 'bi-info-circle',
		dark: 'bi-exclamation-diamond'
	};
	return { variant: safeVariant, icon: iconMap[safeVariant] };
}

// Reposiciona todas las notificaciones (stack top-right)
function updateNotificationPositions() {
	const gap = modalNotification.defaults.gap;
	let top = modalNotification.defaults.topOffset;
	$('[data-notification="true"]').each(function () {
		this.style.transition = 'top .3s';
		this.style.top = `${top}px`;
		top += this.offsetHeight + gap;
	});
}

// Muestra detalle de error usando modalConfirm si está disponible, si no, fallback simple
function showErrorDetailModal(detailConfig) {
	if (!window.modalConfirm) {
		alert('Detalles del error no disponibles.');
		return;
	}

	const xhr = detailConfig?.xhr ?? {};
	const data = (xhr && typeof xhr === 'object') ? (xhr.responseJSON ?? xhr.data) : undefined;
	const text = xhr.responseText ?? (typeof xhr?.data === 'string' ? xhr.data : undefined);
	const status = xhr.status;
	const method = xhr.config?.method?.toUpperCase?.();
	const url = xhr.config?.url;

	let errorText = '';
	let isStructuredHtml = false;
	if (data && typeof data === 'object') {
		const ex = window.escapeHtml(data.exception ?? '');
		const msg = window.escapeHtml(data.message ?? '');
		const file = window.escapeHtml(data.file ?? '');
		const line = window.escapeHtml(String(data.line ?? ''));
		errorText += `<strong>Exception:</strong> ${ex}<br>` +
					 `<strong>Message:</strong> ${msg}<br>` +
					 (data.file ? `<strong>File:</strong> ${file}<br><strong>Line:</strong> ${line}<br>` : '');
		isStructuredHtml = true;
	} else if (typeof text === 'string') {
		const hasSql = text.includes('SQLSTATE');
		if (hasSql) {
			const match = text.match(/SQLSTATE[\s\S]{0,1200}/i);
			errorText = match ? match[0] : text;
		} else {
			const titleMatch = text.match(/<title[^>]*>([\s\S]*?)<\/title>/i);
			const h1Match = text.match(/<h1[^>]*>([\s\S]*?)<\/h1>/i);
			const raw = (titleMatch?.[1] || h1Match?.[1] || text)
				.replace(/<[^>]+>/g, ' ')
				.replace(/\s+/g, ' ')
				.trim();
			errorText = raw.slice(0, 1200);
		}
	} else {
		errorText = 'No hay detalles de error disponibles.';
	}

	const summaryParts = [];
	if (typeof status !== 'undefined') summaryParts.push(`Status: ${status}`);
	if (method || url) summaryParts.push([method, url].filter(Boolean).join(' '));
	const defaultSummary = summaryParts.join(' | ') || 'Error de servidor';

	const detailBlock = isStructuredHtml
		? `<div class="small" style="word-break: break-word; overflow-wrap: anywhere;">${errorText}</div>`
		: `<pre class="bg-light p-2 rounded border overflow-auto" style="max-height:300px; white-space: pre-wrap; word-break: break-word; overflow-wrap: anywhere;">${window.escapeHtml(errorText)}</pre>`;

	const body = `
		<div class="mb-2">
			<span class="badge bg-warning text-dark">Se produjo un error, por favor contacta al soporte.</span>
		</div>
		<p><strong>Detalle:</strong> ${window.escapeHtml(detailConfig.body || defaultSummary || '')}<br>
			 <strong>Función:</strong> ${detailConfig.fncErr || ''}<br>
			 <strong>Página:</strong> ${detailConfig.page || ''}</p>
		${detailBlock}`;

	window.modalConfirm({
		modalId: 'modal-error-detail',
		title: 'Detalles del error',
		body,
		btnsType: 'ac',
		closeClick: true
	}, 'dialog', { size: 'xl', scrollable: true });
}

// Exponer defaults desde la función para uso global/configurable
modalNotification.defaults = ModalNotificationDefaults;