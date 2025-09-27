// modalNotification: crea una alerta tipo toast apilable (arriba-derecha) con variantes y estilo mejorado.
// Usa jQuery global y clases de Bootstrap; no importa Bootstrap internamente.
const ModalNotificationDefaults = {
	template: 'warning',
	delayAutoClose: 5000,
	zIndex: 1080,
	gap: 10,
	topOffset: 12,
	alignClasses: 'end-0 me-3',
	// Opacidad base para el fondo/gradiente de la notificación (0..1). Aumenta para menos transparencia.
	bgOpacity: 0.95
};

export function modalNotification(
	title,
	description,
	options = {},
	showDetails = false,
	detailConfig = {}
) {
	const opts = { ...ModalNotificationDefaults, ...options };
	const { variant, icon, leftClass, bgRgb } = mapVariant(opts.template);

	// Construir alerta con background rgba y border-left según template
	const id = `notif-${(window.uuidv7 ? window.uuidv7() : `${Date.now()}-${Math.floor(Math.random() * 1000)}`)}`;
	// Asegurar que description pueda incluir HTML cuando ya venga escapado por el llamador
	const descHtml = (typeof description === 'string') ? description : String(description);

	// helper to make rgba from rgb string like 'rgb(r,g,b)'
	const rgbToRgba = (rgb, a) => {
		const m = String(rgb).match(/(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
		if (!m) return `rgba(0,0,0,${a})`;
		return `rgba(${m[1]},${m[2]},${m[3]},${a})`;
	};

	// Parse rgb string into numeric components
	const getRgbComponents = (rgb) => {
		const m = String(rgb).match(/(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
		if (!m) return { r: 0, g: 0, b: 0 };
		return { r: Number(m[1]), g: Number(m[2]), b: Number(m[3]) };
	};

	// Compute perceived brightness (0-255)
	const perceivedBrightness = (rgb) => {
		const { r, g, b } = getRgbComponents(rgb);
		return (r * 299 + g * 587 + b * 114) / 1000;
	};

	// Convert hex color or rgb numbers to rgba string with alpha
	const makeRgbaFromRgb = (rgbObj, alpha) => {
		return `rgba(${rgbObj.r},${rgbObj.g},${rgbObj.b},${alpha})`;
	};

	// Determine readable text/icon color based on template brightness
	const brightness = perceivedBrightness(bgRgb);
	const textColor = brightness > 160 ? '#212529' : '#ffffff';
	const iconFg = textColor;
	// Create a gradient using the template color: use configured opacity to make backgrounds less transparent
	// Create a pleasing gradient using multiple alpha stops based on bgOpacity
	const rgbObj = getRgbComponents(bgRgb);
	const clamp = (v) => Math.max(0, Math.min(1, v));
	const startColor = makeRgbaFromRgb(rgbObj, clamp(opts.bgOpacity));
	const midColor = makeRgbaFromRgb(rgbObj, clamp(opts.bgOpacity * 0.7));
	const endColor = makeRgbaFromRgb(rgbObj, clamp(opts.bgOpacity * 0.35));
	const bodyBg = `linear-gradient(90deg, ${startColor} 0%, ${midColor} 45%, ${endColor} 100%)`;

	const iconBg = makeRgbaFromRgb(rgbObj, clamp(opts.bgOpacity * 1.0));
	// Secondary text should be more opaque when bgOpacity is high
	const secondaryText = textColor === '#ffffff' ? 'rgba(255,255,255,0.95)' : 'rgba(0,0,0,0.85)';
	const $el = $(
		`<div id="${id}" class="toast-notif position-fixed ${opts.alignClasses} d-flex align-items-stretch" role="alert" data-notification="true" style="z-index:${opts.zIndex}; min-width:320px; box-shadow:0 12px 32px rgba(0,0,0,0.12); border-radius:8px; overflow:hidden;">
			<div class="notif-left" style="width:0;border-left:8px solid ${bgRgb};"></div>
				<div class="notif-body p-3 flex-grow-1" style="background: ${bodyBg};">
					<div class="d-flex align-items-start gap-3">
						<span class="notif-icon d-inline-flex align-items-center justify-content-center rounded-circle" style="width:40px;height:40px;background:${iconBg};color:${iconFg};flex-shrink:0;">
							<i class="bi ${icon} fs-5"></i>
						</span>
						<div class="flex-grow-1">
							<div class="d-flex justify-content-between align-items-start">
								<div class="me-2">
									<strong style="color:${textColor};">${window.escapeHtml(title)}</strong>
									<div class="small mt-1" style="color:${secondaryText};">${descHtml}</div>
								</div>
								<button type="button" class="btn-close" aria-label="Close"></button>
							</div>
						</div>
					</div>
				</div>
		</div>`
	);

	// Cerrar con botón
	$el.off('click').on('click', '.btn-close', function (e) {
		e.stopPropagation();
		$el.remove();
		updateNotificationPositions();
	});

	// Click para ver detalles (solo si corresponde)
	if (variant === 'danger' && showDetails) {
		$el.css('cursor', 'pointer');
		$el.off('click').on('click', function (e) {
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
	const rgbMap = {
		primary: 'rgb(13,110,253)',
		secondary: 'rgb(108,117,125)',
		success: 'rgb(25,135,84)',
		danger: 'rgb(220,53,69)',
		warning: 'rgb(255,193,7)',
		info: 'rgb(13,202,240)',
		light: 'rgb(248,249,250)',
		dark: 'rgb(33,37,41)'
	};
	// Use standard Bootstrap utilities: border-start + border-<variant> for the colored stripe
	const leftClass = `border-start border-${safeVariant}`;
	return { variant: safeVariant, icon: iconMap[safeVariant], leftClass, bgRgb: (rgbMap[safeVariant] || rgbMap.warning) };
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