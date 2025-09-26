// Page module for Admin -> Users
// Exports init() and destroy() to be used by the global page loader.

let _tooltipInstances = [];
let _bsDeactivateModal = null;

function initTooltips(root = document) {
	_tooltipInstances.forEach(t => { try { t.dispose(); } catch (_) { } });
	_tooltipInstances = [];
	const els = Array.from((root || document).querySelectorAll('[data-bs-toggle="tooltip"]'));
	els.forEach(el => {
		try { _tooltipInstances.push(new bootstrap.Tooltip(el)); } catch (_) { }
	});
}

function onDeactivateClick(e) {
	const btn = e.currentTarget || e.target;
	const userId = btn.getAttribute('data-user-id');
	const userName = btn.getAttribute('data-user-name');
	const form = document.getElementById('deactivateForm');
	if (form) form.action = `/admin/users/${userId}/ban`;
	const msgEl = document.getElementById('deactivateMessage'); if (msgEl) msgEl.textContent = 'Vas a cambiar el estado de ' + userName + '. Si estás desactivando, por favor indica la razón.';
	const reasonEl = document.getElementById('deactivateReason'); if (reasonEl) reasonEl.value = '';
	try { _bsDeactivateModal && _bsDeactivateModal.show(); } catch (_) { }
}

function onShowSessionsClick(e) {
	const btn = e.currentTarget || e.target;
	const userId = btn.getAttribute('data-user-id');
	const url = `/admin/users/${userId}/sessions`;
	axios.get(url).then(function (resp) {
		const data = resp.data || {};
		if (!data.ok) { if (typeof modalNotification === 'function') modalNotification('Atención', 'No se pudo obtener el historial de sesiones'); return; }
		const sessions = data.sessions || [];

		function pad(n){ return String(n).padStart(2,'0'); }
		function formatDateTime(dtStr){
			if (!dtStr) return '-';
			try {
				const iso = dtStr.replace(' ', 'T');
				const d = new Date(iso);
				if (isNaN(d.getTime())) return dtStr;
				return `${pad(d.getDate())}-${pad(d.getMonth()+1)}-${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
			} catch(_) { return dtStr; }
		}
		function formatDurationSecs(sec){
			if (sec === null || typeof sec === 'undefined') return '-';
			sec = Number(sec) || 0;
			if (sec < 60) return sec + (sec === 1 ? ' segundo' : ' segundos');
			const mins = Math.floor(sec/60);
			if (mins < 60) return mins + (mins === 1 ? ' minuto' : ' minutos');
			const hours = Math.floor(mins/60);
			if (hours < 24) return hours + (hours === 1 ? ' hora' : ' horas');
			const days = Math.floor(hours/24);
			return days + (days === 1 ? ' día' : ' días');
		}

		let first = '-'; let last = '-';
		if (sessions.length) {
			first = formatDateTime(sessions[sessions.length - 1].started_at ?? null);
			last = formatDateTime(sessions[0].started_at ?? null);
		}
		const totalSessions = sessions.length;
		const totalDurationSeconds = sessions.reduce((acc, s) => acc + (Number(s.duration_seconds) || 0), 0);

		let html
		=`	<div class="mb-3"><strong>Primer acceso:</strong> ${first}<br><strong>Último acceso:</strong> ${last}</div>
			<div class="mb-2"><strong>Total sesiones:</strong> ${totalSessions} &nbsp; <strong>Duración total:</strong> ${formatDurationSecs(totalDurationSeconds)}</div>
			<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Inicio</th><th>Fin</th><th>Duración</th><th>IP</th><th>Agente</th></tr></thead><tbody>
		`;
		sessions.forEach(function (s) {
			const started = formatDateTime(s.started_at ?? null);
			const ended = formatDateTime(s.ended_at ?? null);
			const durLabel = formatDurationSecs(s.duration_seconds ?? null);
			const ip = s.ip ?? '-';
			const ua = s.user_agent ?? '-';
			html += `<tr><td>${started}</td><td>${ended}</td><td>${durLabel}</td><td>${ip}</td><td><small class="text-muted">${ua}</small></td></tr>`;
		});
		html += `</tbody></table></div>`;
		modalConfirm({
			title: 'Historial de sesiones',
			body: html,
			noFooter: true
		}, 'dialog', { centered: true, backdrop: true, size: 'xl' });
	}).catch(function () { if (typeof modalNotification === 'function') modalNotification('Atención', 'Error al consultar historial'); });
}

export function init() {
	const container = document.getElementById('app-content') || document;
	initTooltips(container);
	const deactivateModalEl = document.getElementById('deactivateModal');
	_bsDeactivateModal = deactivateModalEl ? bootstrap.Modal.getOrCreateInstance(deactivateModalEl) : null;
	// delegated handlers
	$(document).on('click.adminUsers', '.action-deactivate', onDeactivateClick);
	$(document).on('click.adminUsers', '.action-show-sessions', onShowSessionsClick);
}

export function destroy() {
	try { $(document).off('.adminUsers'); } catch (_) { }
	_tooltipInstances.forEach(t => { try { t.dispose(); } catch (_) { } });
	_tooltipInstances = [];
	try {
		if (_bsDeactivateModal) { try { _bsDeactivateModal.hide(); } catch (_) { }; try { _bsDeactivateModal.dispose(); } catch (_) { }; _bsDeactivateModal = null; }
	} catch (_) { }
}

// Note: the global app loader will call init() after PJAX swaps.