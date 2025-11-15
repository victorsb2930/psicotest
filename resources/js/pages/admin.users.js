// Page module for Admin -> Users
// Exports init() and destroy() to be used by the global page loader.

let _tooltipInstances = [];

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

	const body = `
		<p>Vas a cambiar el estado de <strong>${window.escapeHtml ? window.escapeHtml(userName) : userName}</strong>. Si estás desactivando, por favor indica la razón (opcional):</p>
		<div class="mb-3"><textarea id="deactivateReasonInput" class="form-control" rows="3" placeholder="Motivo (opcional)"></textarea></div>
	`;

	modalConfirm({
		title: 'Confirmar cambio de estado',
		body: body,
		confirmLabel: 'Confirmar',
		cancelLabel: 'Cancelar',
		onClickYes: async function() {
			// Read reason and POST to ban endpoint
			const reasonEl = document.getElementById('deactivateReasonInput');
			const reason = reasonEl ? reasonEl.value : '';
			try {
				const url = `/admin/users/${userId}/ban`;
				const res = await axios.post(url, { reason }, { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') } });
				const data = res.data || {};
				if (data.ok) {
					if (typeof modalNotification === 'function') modalNotification('Éxito', 'Estado actualizado', { template: 'success' });
					// reload to reflect changes
					setTimeout(() => location.reload(), 600);
				} else {
					if (typeof modalNotification === 'function') modalNotification('Error', data.message || 'No se pudo cambiar el estado');
				}
			} catch (err) {
				if (typeof modalNotification === 'function') modalNotification('Error', 'Error al cambiar el estado');
			}
		}
	}, 'normal');
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

function onDeleteClick(e) {
	const btn = e.currentTarget || e.target;
	const deleteUrl = btn.getAttribute('data-delete-url');
	const userName = btn.getAttribute('data-user-name') || '';
	if (!deleteUrl) return;

	modalConfirm({
		title: 'Eliminar usuario',
		body: `<p>¿Estás seguro de eliminar la cuenta de <strong>${window.escapeHtml ? window.escapeHtml(userName) : userName}</strong>? Esta acción realizará un <em>soft-delete</em>.</p>`,
		confirmLabel: 'Eliminar',
		cancelLabel: 'Cancelar',
		onClickYes: async function() {
			try {
				const res = await axios.delete(deleteUrl, { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') } });
				const data = res.data || {};
				if (data.ok) {
					if (typeof modalNotification === 'function') modalNotification('Eliminado', 'Usuario eliminado correctamente', { template: 'success' });
					setTimeout(() => location.reload(), 600);
				} else {
					if (typeof modalNotification === 'function') modalNotification('Error', data.message || 'No se pudo eliminar al usuario');
				}
			} catch (err) {
				if (typeof modalNotification === 'function') modalNotification('Error', 'Error al eliminar usuario');
			}
		}
	}, 'normal');
}

function showAddUserModal() {
		const html = `
		<form id="addUserFormJS">
			<div class="row g-3">
				<div class="col-md-6">
					<label class="form-label">Nombres *</label>
					<input type="text" name="name" class="form-control" required>
				</div>
				<div class="col-md-6">
					<label class="form-label">Apellidos</label>
					<input type="text" name="lastname" class="form-control">
				</div>
				<div class="col-md-6">
					<label class="form-label">Email *</label>
					<input type="email" name="email" class="form-control" required>
				</div>
				<div class="col-12">
					<div class="alert alert-info small mb-0">La contraseña será generada automáticamente y enviada al email proporcionado. Pide al usuario que la cambie tras iniciar sesión.</div>
				</div>
				<div class="col-md-4">
					<label class="form-label">Fecha de nacimiento</label>
					<input type="date" name="birthdate" class="form-control">
				</div>
				<div class="col-md-4">
					<label class="form-label">Género</label>
					<select name="gender" class="form-select">
						<option value="">--</option>
						<option value="masculino">Masculino</option>
						<option value="femenino">Femenino</option>
					</select>
				</div>
				<div class="col-md-4">
					<label class="form-label">Rol inicial</label>
					<select name="role_id" class="form-select">
						<option value="">Sin rol</option>
						<!-- roles will be injected dynamically -->
					</select>
				</div>
			</div>
		</form>
		`;

		// Build and show modal
		modalConfirm({
				title: 'Agregar usuario',
				body: html,
				confirmLabel: 'Crear usuario',
				cancelLabel: 'Cancelar',
				onShow: function(modalEl) {
					// populate roles from existing selects on the page to avoid an extra request
					try {
						const candidateSelectors = ['select[name="role_id"]', 'select[name="roles[]"]', 'select[name^="roles"]'];
						let source = null;
						for (let s of candidateSelectors) {
							const el = document.querySelector(s);
							if (el && el.innerHTML && el.options && el.options.length) { source = el.innerHTML; break; }
						}
						const modalSelect = modalEl.querySelector('select[name="role_id"]');
						if (modalSelect && source) modalSelect.innerHTML = source;
					} catch (_) {}
				},
				onClickYes: async function(modalEl) {
						const form = document.getElementById('addUserFormJS');
						if (!form) return;
						// Basic client-side validation
						const name = form.querySelector('input[name="name"]').value.trim();
						const email = form.querySelector('input[name="email"]').value.trim();
						if (!name || !email) {
								if (typeof modalNotification === 'function') modalNotification('Atención', 'Nombre y email son obligatorios');
								return;
						}
						const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
						const fd = new FormData(form);
						fd.append('_token', token);
						try {
								const res = await axios.post('/admin/users', fd, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
								const data = res.data || {};
								if (data.ok) {
										if (typeof modalNotification === 'function') modalNotification('Creado', 'Usuario creado correctamente', { template: 'success' });
										setTimeout(() => location.reload(), 700);
								} else {
										if (typeof modalNotification === 'function') modalNotification('Error', data.message || 'No se pudo crear el usuario');
								}
						} catch (err) {
								// show validation errors if any
								try {
										const resp = err.response && err.response.data ? err.response.data : null;
										if (resp && resp.errors) {
												const flat = Object.values(resp.errors).map(v => Array.isArray(v) ? v.join(' ') : v).join('\n');
												if (typeof modalNotification === 'function') modalNotification('Error', flat);
												return;
										}
								} catch (_) {}
								modalNotification('Error', 'Error al crear el usuario');
						}
				}
		}, 'normal', { size: 'lg' });
}

export function init() {
	const container = document.getElementById('app-content') || document;
	initTooltips(container);
	// delegated handlers
	$(document).on('click.adminUsers', '.action-deactivate', onDeactivateClick);
	$(document).on('click.adminUsers', '.action-show-sessions', onShowSessionsClick);
	$(document).on('click.adminUsers', '.action-delete', onDeleteClick);
	// Open Add User modal
	$(document).on('click.adminUsers', '#btn-add-user', function(e){ e.preventDefault(); showAddUserModal(); });
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