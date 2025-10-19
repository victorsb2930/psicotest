// Admin Professional Applications page: rejection with modalConfirm
const NS = '.adminProfapps';
let waitInterval = null;

function ensureJQuery(cb) {
	if (typeof window.$ !== 'undefined' || typeof window.jQuery !== 'undefined') return cb();
	let tries = 0;
	waitInterval = setInterval(() => {
		tries++;
		if (typeof window.$ !== 'undefined' || typeof window.jQuery !== 'undefined') {
			clearInterval(waitInterval); waitInterval = null; cb();
		} else if (tries > 100) { clearInterval(waitInterval); waitInterval = null; console.warn('[admin.profapps] jQuery not found; handlers not attached.'); }
	}, 50);
}

function onRejectClick() {
	const $form = $(this).closest('form.js-reject-form');
	if (!$form.length || !window.modalConfirm) return;
	const body = document.createElement('div');
	body.innerHTML = `
		<div>
			<label class="form-label">Motivo de rechazo (opcional)</label>
			<textarea class="form-control" id="rejectNotes" rows="4" placeholder="Escribe una breve justificación..."></textarea>
		</div>
	`;
	modalConfirm({
		modalId: 'rejectReasonModal',
		title: 'Rechazar solicitud',
		icon: 'fa-regular fa-circle-xmark',
		iconColor: '#dc3545',
		btnsType: 'ac',
		body: body.outerHTML,
		onClickYes: () => {
			const notes = document.getElementById('rejectNotes')?.value || '';
			$form.find('input[name="notes"]').val(notes);
			$form.trigger('submit');
		},
	}, 'normal', { centered: true });
}

function attachHandlers() {
	ensureJQuery(() => {
		$(document).on('click' + NS, '.js-open-reject', onRejectClick);
	});
}

function detachHandlers() {
	try { $(document).off(NS); } catch (e) {}
	if (waitInterval) { clearInterval(waitInterval); waitInterval = null; }
	try {
		const $m = $('#rejectReasonModal');
		if ($m.length) { const inst = bootstrap.Modal.getInstance($m[0]); inst?.hide(); $m.remove(); }
	} catch (e) {}
}

export function init() { attachHandlers(); }
export function destroy() { detachHandlers(); }