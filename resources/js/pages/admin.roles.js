// Admin Roles page enhancements: icon/color pickers using modalConfirm
// Requires: modalConfirm (wired in bootstrap.js), Bootstrap Icons CSS loaded in layout

(function () {
	if (($(document.body).data('page') || '') !== 'admin.roles') return;

	const colorClasses = ['bg-primary', 'bg-secondary', 'bg-success', 'bg-danger', 'bg-warning', 'bg-info', 'bg-dark', 'bg-light'];

	let currentTargetInput = null;

	async function getAllBootstrapIcons() {
		// Try reading from CSSOM first; if blocked, fetch the CSS href and parse
		const fromCssom = () => {
			try {
				const out = new Set();
				for (const sheet of Array.from(document.styleSheets)) {
					const href = sheet.href || '';
					if (!/bootstrap-icons/i.test(href)) continue;
					for (const rule of Array.from(sheet.cssRules || [])) {
						if (!rule.selectorText) continue;
						const sels = rule.selectorText.split(',');
						for (const raw of sels) {
							const s = raw.trim();
							// Accept .bi-xxx::before or .bi-xxx:before optionally with spaces
							const m = s.match(/\.bi-([a-z0-9-]+)(\s*::?\s*before)?$/i);
							if (m && m[1]) out.add(`bi-${m[1]}`);
						}
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
		// Last resort small list
		return ['bi-people', 'bi-person', 'bi-shield-lock', 'bi-briefcase', 'bi-house', 'bi-gear'];
	}

	function openIconPicker(targetId) {
		currentTargetInput = document.getElementById(targetId);
		const $body = $(`
	<div>
		<input type="text" class="form-control mb-3" placeholder="Buscar icono..." id="iconFilter">
		<div id="iconGrid" class="row row-cols-2 row-cols-sm-3 row-cols-md-4 g-2" style="max-height:60vh; overflow:auto"></div>
	</div>
	`);
		modalConfirm({
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
		modalConfirm({
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

	$(document).on('click', '[data-role="open-icon-picker"]', function () {
		openIconPicker(this.dataset.target);
	});
	$(document).on('click', '[data-role="open-color-picker"]', function () {
		openColorPicker(this.dataset.target);
	});
})();