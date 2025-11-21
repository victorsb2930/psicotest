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
		} else if (tries > 100) { clearInterval(waitInterval); waitInterval = null; }
	}, 50);
}

function onRejectClick() {
	const $form = $(this).closest('form.js-reject-form');
	if (!$form.length || !window.modalConfirm) return;
	const body = document.createElement('div');
	body.innerHTML = `
		<div>
			<label class="form-label">Motivo de rechazo (requerido)</label>
			<textarea class="form-control" id="rejectNotes" rows="4" placeholder="Explica brevemente el motivo" required></textarea>
			<div class="small text-muted mt-1">Este motivo se enviará al solicitante por email.</div>
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
			const notesEl = document.getElementById('rejectNotes');
			const notes = notesEl?.value.trim() || '';
			if (notes === '') { notesEl.classList.add('is-invalid'); return false; }
			$form.find('input[name="notes"]').val(notes);
			$form.trigger('submit');
		},
	}, 'normal', { centered: true });
}

function markDocViewed(appId, doc, row){
	return fetch(`/admin/professional-applications/${appId}/doc-view`, {
		method: 'POST',
		headers: { 'Content-Type':'application/json','X-CSRF-TOKEN': getCsrf(), 'Accept':'application/json' },
		body: JSON.stringify({ field: doc })
	}).then(async r => {
		if(!r.ok){ throw new Error(await r.text()); }
		const data = await r.json();
		if(row){
			// update data-docs-viewed attribute
			const viewed = row.getAttribute('data-docs-viewed')?.split(',').filter(Boolean) || [];
			if(!viewed.includes(doc)) viewed.push(doc);
			row.setAttribute('data-docs-viewed', viewed.join(','));
			const docStateSpan = row.querySelector(`a.js-doc[data-doc="${doc}"] .doc-state`);
			if(docStateSpan && !docStateSpan.querySelector('i')){
				docStateSpan.innerHTML = '<i class="bi bi-check-circle text-success"></i>';
			}
			// update progress count
			const progressLabel = row.querySelector('.doc-seen-count');
			if(progressLabel){ progressLabel.textContent = viewed.length; }
			reevaluateRow(row);
		}
		return data;
	}).catch(err => { console.error('doc-view failed', err); });
}

function reevaluateRow(row){
	const required = (row.getAttribute('data-docs-required')||'').split(',').filter(Boolean);
	const viewed = (row.getAttribute('data-docs-viewed')||'').split(',').filter(Boolean);
	const allViewed = required.every(r => viewed.includes(r));
	const approveBtn = row.querySelector('form.app-approve button');
	const rejectBtn = row.querySelector('form.app-reject button.js-open-reject');
	const hint = row.querySelector('.action-hint');
	if(approveBtn) approveBtn.disabled = !allViewed;
	if(rejectBtn) rejectBtn.disabled = !allViewed;
	if(hint){ hint.style.display = allViewed ? 'none':'block'; }
	if(allViewed){ row.classList.add('table-info'); setTimeout(()=>row.classList.remove('table-info'), 1000); }
}

function onDocClick(e){
	const a = e.currentTarget;
	const doc = a.getAttribute('data-doc');
	const row = a.closest('tr.app-row');
	const appId = row?.getAttribute('data-app-id');
	if(!doc || !appId) return;
	// Fire and forget mark doc viewed (download opens in new tab)
	markDocViewed(appId, doc, row);
}

function attachHandlers() {
	ensureJQuery(() => {
		$(document).on('click' + NS, '.js-open-reject', onRejectClick);
		document.querySelectorAll('tr.app-row a.js-doc').forEach(a => {
			a.addEventListener('click', onDocClick, { passive: true });
		});
		// Initial evaluation for rows with server-marked viewed docs
		document.querySelectorAll('tr.app-row').forEach(reevaluateRow);
	});
}

function detachHandlers() {
	try { $(document).off(NS); } catch (e) { }
	if (waitInterval) { clearInterval(waitInterval); waitInterval = null; }
	try {
		const $m = $('#rejectReasonModal');
		if ($m.length) { const inst = bootstrap.Modal.getInstance($m[0]); inst?.hide(); $m.remove(); }
	} catch (e) { }
	document.querySelectorAll('tr.app-row a.js-doc').forEach(a => {
		try { a.removeEventListener('click', onDocClick); } catch(_) {}
	});
}

function getCsrf(){
	const el = document.querySelector('meta[name="csrf-token"]');
	return el ? el.getAttribute('content') : '';
}

export function init() { attachHandlers(); }
export function destroy() { detachHandlers(); }