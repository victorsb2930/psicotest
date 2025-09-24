// Admin Roles page enhancements: icon/color pickers using modalConfirm
// Requires: modalConfirm (wired in bootstrap.js), Bootstrap Icons CSS loaded in layout

// Admin Roles page enhancements: icon/color pickers using modalConfirm
// Requires: modalConfirm (wired in bootstrap.js), Bootstrap Icons CSS loaded in layout

const NS = '.adminRoles';
let waitInterval = null;
let currentTargetInput = null;
const colorClasses = ['bg-primary', 'bg-secondary', 'bg-success', 'bg-danger', 'bg-warning', 'bg-info', 'bg-dark', 'bg-light'];

async function getAllBootstrapIcons() {
		// First try loading a pre-generated JSON listing in /bootstrap-icons-list.json
		const fromJson = async () => {
			try {
				const url = '/bootstrap-icons-list.json';
				const cacheKey = 'bootstrap-icons-list-v1';
				// Try localStorage cached copy
				try {
					const cached = localStorage.getItem(cacheKey);
					if (cached) return JSON.parse(cached);
				} catch (e) { /* ignore localStorage errors */ }
				const res = await fetch(url, { cache: 'no-cache' });
				if (!res.ok) return null;
				const obj = await res.json();
				if (obj && Array.isArray(obj.icons)) {
					try { localStorage.setItem(cacheKey, JSON.stringify(obj.icons)); } catch (e) { }
					return obj.icons;
				}
				return null;
			} catch (e) { return null; }
		};

		const jsonIcons = await fromJson();
		if (jsonIcons && jsonIcons.length) return jsonIcons;

		// Try reading from CSSOM first; if blocked, fetch the CSS href and parse
		const fromCssom = () => {
			try {
				const out = new Set();
				for (const sheet of Array.from(document.styleSheets)) {
					try {
						const rules = sheet.cssRules || [];
						for (const rule of Array.from(rules)) {
							if (!rule.selectorText) continue;
							const sels = rule.selectorText.split(',');
							for (const raw of sels) {
								const s = raw.trim();
								// Accept .bi-xxx::before or .bi-xxx:before optionally with spaces
								const m = s.match(/\.bi-([a-z0-9-]+)(\s*::?\s*before)?$/i);
								if (m && m[1]) out.add(`bi-${m[1]}`);
							}
						}
					} catch (innerErr) {
						// Cross-origin or inaccessible stylesheet — skip
						continue;
					}
				}
				return Array.from(out).sort();
			} catch (e) {
				return null;
			}
		};
		const cssom = fromCssom();
		if (cssom && cssom.length) return cssom;

		// Fallback: find the bootstrap-icons <link> and fetch its CSS
		const link = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
			.find(l => /bootstrap-icons/i.test(l.href || ''));
		if (link && link.href) {
			try {
				const res = await fetch(link.href, { credentials: 'omit' });
				const css = await res.text();
				const set = new Set();
				// Match .bi-<name>::before { ... } allowing optional spaces and single/double colon
				const re = /\.bi-([a-z0-9-]+)\s*::?\s*before\s*\{/gi;
				let m;
				while ((m = re.exec(css)) !== null) {
					set.add(`bi-${m[1]}`);
				}
				return Array.from(set).sort();
			} catch (e) { }
		}
		// Last resort small list (if CSSOM and local link parsing both fail)
		return ['bi-people', 'bi-person', 'bi-shield-lock', 'bi-briefcase', 'bi-house', 'bi-gear'];
	}

function getModalConfirm() {
		if (typeof modalConfirm !== 'undefined') return modalConfirm;
		// Minimal fallback implementation: create a Bootstrap modal skeleton and show it
		return function (opts, type = 'dialog', cfg = {}) {
				const modalId = opts.modalId || ('modal_' + Math.random().toString(36).slice(2,8));
				let $m = $('#' + modalId);
				if ($m.length === 0) {
						const body = opts.body || '';
						const title = opts.title || '';
						const html = `
						<div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true">
							<div class="modal-dialog ${cfg.size === 'lg' ? 'modal-lg' : ''} ${cfg.centered ? 'modal-dialog-centered' : ''}">
								<div class="modal-content">
									<div class="modal-header"><h5 class="modal-title">${title}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
									<div class="modal-body">${body}</div>
									<div class="modal-footer"></div>
								</div>
							</div>
						</div>`;
						$('body').append(html);
						$m = $('#' + modalId);
				}
				const bs = new bootstrap.Modal($m[0], { backdrop: 'static' });
				$m.on('hidden.bs.modal', function () { try { $m.remove(); } catch (e) {} });
				$m.find('.modal-footer').empty();
				if (opts.btnsType === 'ac') {
						const $ok = $('<button/>', { type: 'button', class: 'btn btn-primary' }).text('OK');
						$ok.on('click', function () { if (typeof opts.onClickYes === 'function') opts.onClickYes(); bs.hide(); });
						const $cancel = $('<button/>', { type: 'button', class: 'btn btn-secondary', 'data-bs-dismiss': 'modal' }).text('Cancelar');
						$m.find('.modal-footer').append($cancel, $ok);
				}
				bs.show();
		};
}

const modalConfirmInstance = getModalConfirm();

function openIconPicker(targetId) {
		currentTargetInput = document.getElementById(targetId);
		const $body = $(`
	<div>
		<input type="text" class="form-control mb-3" placeholder="Buscar icono..." id="iconFilter">
		<div id="iconGrid" class="row row-cols-2 row-cols-sm-3 row-cols-md-4 g-2" style="max-height:60vh; overflow:auto"></div>
	</div>
	`);
	modalConfirmInstance({
			modalId: 'biIconPicker',
			title: 'Elegir icono',
			body: $body[0].outerHTML,
			btnsType: 'ac',
			onClickYes: () => { },
		}, 'dialog', { size: 'lg', scrollable: true, centered: true });
		const $mIcon = $('#biIconPicker');
		const $iconGrid = $mIcon.find('#iconGrid');
		const $iconFilter = $mIcon.find('#iconFilter');
		getAllBootstrapIcons().then((allIcons) => {
			const render = (list) => {
				$iconGrid.empty();
				if (!Array.isArray(list) || list.length === 0) {
					$iconGrid.append(`<div class="col-12"><div class="alert alert-secondary mb-0">No se encontraron iconos locales. Asegúrate de ejecutar <code>npm run generate-icons</code> y recargar.</div></div>`);
					return;
				}
				const $frag = $(document.createDocumentFragment());
				list.forEach(cls => {
					const $col = $('<div/>', { class: 'col' });
					const $btn = $(`
			<button type="button" class="btn btn-light w-100 d-flex align-items-center justify-content-start gap-2" data-icon="${cls}">
			<i class="bi ${cls}"></i><span class="small">bi ${cls}</span>
			</button>
		`);
					$btn.on('click', () => {
						if (currentTargetInput) currentTargetInput.value = `bi ${cls}`;
						const $m = $('#biIconPicker');
						const inst = bootstrap.Modal.getInstance($m[0]);
						inst?.hide();
					});
					$col.append($btn);
					$frag.append($col);
				});
				$iconGrid.append($frag);
			};
			render(allIcons);
			$iconFilter.on('input', function () {
				const q = (this.value || '').toLowerCase();
				render(allIcons.filter(i => i.toLowerCase().includes(q)));
			});
		});
}

function openColorPicker(targetId) {
		currentTargetInput = document.getElementById(targetId);
		const $body = $(`
	<div>
		<div class="mb-3">
		<label class="form-label">Clases de Bootstrap</label>
		<div class="d-flex flex-wrap gap-2 mb-2" id="colorClassGrid"></div>
		</div>
		<div>
		<label class="form-label">Color personalizado</label>
		<input type="color" id="colorHexInput" class="form-control form-control-color" value="#0d6efd" title="Elige un color">
		</div>
	</div>
	`);
		modalConfirmInstance({
			modalId: 'badgeColorPicker',
			title: 'Elegir color',
			body: $body[0].outerHTML,
			btnsType: 'ac',
			onClickYes: () => { },
		}, 'dialog', { centered: true });
		const $mColor = $('#badgeColorPicker');
		const $colorClassGrid = $mColor.find('#colorClassGrid');
		const $colorHexInput = $mColor.find('#colorHexInput');
		$colorClassGrid.empty();
		colorClasses.forEach(c => {
			const $btn = $('<button/>', { type: 'button', class: `btn btn-sm ${c}`, title: c }).css('minWidth', '3rem');
			$btn.on('click', () => {
				if (currentTargetInput) currentTargetInput.value = c;
				const $m = $('#badgeColorPicker');
				const inst = bootstrap.Modal.getInstance($m[0]);
				inst?.hide();
			});
			$colorClassGrid.append($btn);
		});
		$colorHexInput.on('input', function () {
			if (currentTargetInput) currentTargetInput.value = this.value;
		});
}

function attachHandlers() {
	// Ensure jQuery present, otherwise wait briefly
	if (typeof window.$ === 'undefined' && typeof window.jQuery === 'undefined') {
		let tries = 0;
		waitInterval = setInterval(() => {
			tries++;
			if (typeof window.$ !== 'undefined' || typeof window.jQuery !== 'undefined') {
				clearInterval(waitInterval); waitInterval = null;
				attachHandlers();
			} else if (tries > 100) {
				clearInterval(waitInterval); waitInterval = null;
				console.warn('[admin.roles] jQuery not found after waiting; pickers will not be initialized.');
			}
		}, 50);
		return;
	}

	if (typeof modalConfirm === 'undefined') {
		console.warn('[admin.roles] modalConfirm helper not found; icon/color picker modals may fail.');
	}

	$(document).on('click' + NS, '[data-role="open-icon-picker"]', function () {
		openIconPicker(this.dataset.target);
	});
	$(document).on('click' + NS, '[data-role="open-color-picker"]', function () {
		openColorPicker(this.dataset.target);
	});
}

function detachHandlers() {
	try {
		$(document).off(NS);
	} catch (e) { /* ignore */ }
	// Clear waiting interval if any
	if (waitInterval) {
		clearInterval(waitInterval);
		waitInterval = null;
	}
	// Close and remove modals created by this module if present
	['#biIconPicker', '#badgeColorPicker'].forEach(id => {
		try {
			const $m = $(id);
			if ($m.length) {
				const inst = bootstrap.Modal.getInstance($m[0]);
				inst?.hide();
				$m.remove();
			}
		} catch (e) { /* ignore */ }
	});
}

export function init() {
	attachHandlers();
}

export function destroy() {
	detachHandlers();
}