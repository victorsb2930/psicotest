// Professional Area dashboard page module
// Adjusted "Abrir" buttons to route to unified chat hub /chat?open={id}
export function init(){
	const root = document.querySelector('[data-page="professional-area"]') || document.body;

	root.__pg_prof_area_click = function(ev){
		const btn = ev.target.closest && ev.target.closest('button');
		if(!btn) return;
		const txt = (btn.textContent||'').trim();
		if(txt === 'Ir a sala'){
			window.modalNotification?.('Sala','Redirigiendo a la sala...',{template:'info'});
			return;
		}
		if(txt === 'Reprogramar'){
			window.modalConfirm?.({ title:'Reprogramar', body:'Funcionalidad de reprogramar aún no está implementada en esta demo.', buttons:[ { text:'Cerrar', className:'btn-primary', closeOnClick:true } ] });
			return;
		}
	};

	root.__pg_prof_area_mini_click = function(ev){
		const target = ev.target.closest && ev.target.closest('.mini-cal');
		if(!target) return;
		window.modalNotification?.('Calendario','Mini calendario (vista rápida)',{template:'info'});
	};

	root.addEventListener('click', root.__pg_prof_area_click);
	root.addEventListener('click', root.__pg_prof_area_mini_click);

	root.__pg_prof_area_loadMessages = async function(){
		try{
			const res = await fetch('/friends/list');
			if(!res.ok) return;
			const j = await res.json();
			let friends = [];
			if(Array.isArray(j)) friends = j;
			else if(j && Array.isArray(j.friends)) friends = j.friends;
			else if(j && Array.isArray(j.data)) friends = j.data;
			const listGroup = document.querySelector('.container-fluid .list-group.list-group-flush');
			if(!listGroup) return;
			let messagesList = null;
			try { messagesList = document.querySelector('.container-fluid').querySelector('.card .list-group.list-group-flush'); } catch(_) { messagesList = listGroup; }
			const explicitList = document.getElementById('pg-prof-messages-list');
			const msgCard = Array.from(document.querySelectorAll('.card')).find(c=> c.textContent && c.textContent.includes('Mensajes recientes'));
			const targetList = explicitList || (msgCard ? msgCard.querySelector('.list-group.list-group-flush') : messagesList || listGroup);
			if(!targetList) return;
			targetList.innerHTML = '';
			if(!friends.length){
				const li = document.createElement('div'); li.className='list-group-item text-muted small'; li.textContent='No hay mensajes recientes'; targetList.appendChild(li); return;
			}
			friends.slice(0,3).forEach(f => {
				const li = document.createElement('div'); li.className='list-group-item';
				li.setAttribute('data-user-id', String(f.id));
				const avatar = (f.avatar || f.avatar_url || f.photo || f.picture || f.image) || null;
				const avatarHtml = avatar ? `<img src="${escapeHtml(avatar)}" class="rounded-circle me-2" style="width:36px;height:36px;object-fit:cover" alt="${escapeHtml(f.name)}">` : `<span class="avatar-placeholder rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center me-2" style="width:36px;height:36px">${escapeHtml((f.name||'U').split(/\s+/).map(n=>n[0]||'').slice(0,2).join('').toUpperCase())}</span>`;
				li.innerHTML = `<div class="d-flex justify-content-between align-items-center"><div class="d-flex align-items-center"><div class="avatar-wrap">${avatarHtml}</div><div><div class="fw-bold">${escapeHtml(f.name)}</div><div class="text-muted small">${escapeHtml(f.last_body || '--')}</div></div></div><div><a class="btn btn-sm btn-outline-primary" href="/chat?open=${f.id}">Abrir</a></div></div>`;
				if(f.unread){ const badge = document.createElement('span'); badge.className='badge text-bg-primary small ms-2'; badge.textContent='Nuevo'; li.querySelector('div.d-flex > div:last-child')?.appendChild(badge); }
				targetList.appendChild(li);
			});
		} catch(_){}
	};

	function escapeHtml(s){ if(!s) return ''; return String(s).replace(/[&<>"'`=\/]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c])); }
	function makeAvatarHtml(name, maybeUrl){ const url = maybeUrl || null; if(url){ return `<img src="${escapeHtml(url)}" class="rounded-circle me-2" style="width:36px;height:36px;object-fit:cover" alt="${escapeHtml(name)}">`; } const initials = (String(name||'U').split(/\s+/).map(n=>n[0]||'').slice(0,2).join('')||'U').toUpperCase(); return `<span class="avatar-placeholder rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center me-2" style="width:36px;height:36px">${escapeHtml(initials)}</span>`; }

	// Fallback handler if legacy button with .btn-open-thread exists
	root.__pg_prof_area_onOpenThread = function(ev){
		const btn = ev.target.closest && ev.target.closest('.btn-open-thread'); if(!btn) return;
		const uid = btn.dataset.userId; if(!uid) return;
		window.location.href = `/chat?open=${encodeURIComponent(uid)}`;
	};
	root.addEventListener('click', root.__pg_prof_area_onOpenThread);

	// Appointment actions: details/join/reschedule for "PRÓXIMA CITA"
	root.__pg_prof_area_onApptAction = async function(ev){
		const btn = ev.target.closest && ev.target.closest('[data-appt-action]');
		if(!btn) return;
		const action = btn.getAttribute('data-appt-action');
		const wrap = document.getElementById('pg-next-appt');
		if(!wrap) return;
		const apptId = wrap.getAttribute('data-appt-id');
		const patientId = wrap.getAttribute('data-patient-id');
		const title = wrap.getAttribute('data-title') || 'Cita';
		const startHuman = wrap.getAttribute('data-start-human') || '';
		const endHuman = wrap.getAttribute('data-end-human') || '';
		const notes = wrap.getAttribute('data-notes') || '';
		if(action === 'details'){
			const body = `
				<div class="mb-1"><strong>Título:</strong> ${escapeHtml(title)}</div>
				<div class="mb-1"><strong>Horario:</strong> ${escapeHtml(startHuman)}${endHuman?(' – '+escapeHtml(endHuman)) : ''}</div>
				<div class="mt-2"><strong>Notas:</strong><div class="small mt-1">${escapeHtml(notes) || '<span class=\"text-muted\">(sin notas)</span>'}</div></div>
			`;
			window.modalConfirm?.({ title: 'Detalle de cita', body, buttons: [{ text: 'Cerrar', className: 'btn-outline-secondary', closeOnClick: true }] });
			return;
		}
		if(action === 'join'){
			// Start session (create room + broadcast) before redirecting to chat
			if(apptId){
				try {
					const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
					await fetch(`/appointments/${encodeURIComponent(apptId)}/session/start`, {
						method: 'POST',
						headers: {
							'Accept':'application/json',
							'X-Requested-With':'XMLHttpRequest',
							...(token ? { 'X-CSRF-TOKEN': token } : {})
						}
					});
				} catch(_) { /* ignore start errors */ }
			}
			if(patientId){ window.location.href = `/chat?open=${encodeURIComponent(patientId)}`; return; }
			window.location.href = '/chat';
			return;
		}
		if(action === 'reschedule'){
			const url = apptId ? `/professional/calendar?open=${encodeURIComponent(apptId)}` : '/professional/calendar';
			window.location.href = url;
			return;
		}
	};
	root.addEventListener('click', root.__pg_prof_area_onApptAction);

	root.__pg_prof_area_onRtMessage = function(ev){
		try {
			const d = ev.detail; if(!d) return;
			const fromId = String(d.from_id || d.fromId || d.from);
			const name = d.from_name || d.fromName || d.from || 'Usuario';
			const body = d.body || d.last_body || '';
			const explicitList = document.getElementById('pg-prof-messages-list');
			const msgCard = Array.from(document.querySelectorAll('.card')).find(c=> c.textContent && c.textContent.includes('Mensajes recientes'));
			const targetList = explicitList || (msgCard ? msgCard.querySelector('.list-group.list-group-flush') : document.querySelector('.container-fluid .list-group.list-group-flush'));
			if(!targetList) return;
			let existing = targetList.querySelector(`[data-user-id="${fromId}"]`);
			if(existing){
				const snippetEl = existing.querySelector('.text-muted.small'); if (snippetEl) snippetEl.textContent = body || '--';
				const nameEl = existing.querySelector('.fw-bold'); if (nameEl) nameEl.textContent = name;
				const maybeAvatar = d.avatar || d.avatar_url || d.photo || d.picture || d.from_avatar || null;
				const avatarWrap = existing.querySelector('.avatar-wrap'); if(avatarWrap){ avatarWrap.innerHTML = makeAvatarHtml(name, maybeAvatar); }
				let badge = existing.querySelector('.badge'); if(!badge){ badge = document.createElement('span'); badge.className='badge text-bg-primary small ms-2'; badge.textContent='Nuevo'; existing.querySelector('.d-flex > div:last-child')?.appendChild(badge); }
				targetList.prepend(existing);
			} else {
				const li = document.createElement('div'); li.className='list-group-item';
				li.setAttribute('data-user-id', fromId);
				const avatarHtml = makeAvatarHtml(name, d.avatar || d.avatar_url || d.photo || d.picture || d.from_avatar || null);
				li.innerHTML = `<div class="d-flex justify-content-between align-items-center"><div class="d-flex align-items-center"><div class="avatar-wrap">${avatarHtml}</div><div><div class="fw-bold">${escapeHtml(name)}</div><div class="text-muted small">${escapeHtml(body || '--')}</div></div></div><div><a class="btn btn-sm btn-outline-primary" href="/chat?open=${fromId}">Abrir</a></div></div>`;
				const badge = document.createElement('span'); badge.className='badge text-bg-primary small ms-2'; badge.textContent='Nuevo'; li.querySelector('.d-flex > div:last-child')?.appendChild(badge);
				targetList.prepend(li);
			}
		} catch(_){}
	};
	window.addEventListener('rt:message', root.__pg_prof_area_onRtMessage);

	root.__pg_prof_area_onCountersUpdate = function(){ try { root.__pg_prof_area_loadMessages(); } catch(_){} };
	window.addEventListener('counters:update', root.__pg_prof_area_onCountersUpdate);

	root.__pg_prof_area_pollInterval = setInterval(()=>{ try { root.__pg_prof_area_loadMessages(); } catch(_){} }, 15000);
	root.__pg_prof_area_loadMessages();
}

export function destroy(){
	const root = document.querySelector('[data-page="professional-area"]') || document.body;
	try { if(root.__pg_prof_area_click) root.removeEventListener('click', root.__pg_prof_area_click); } catch(_){}
	try { if(root.__pg_prof_area_mini_click) root.removeEventListener('click', root.__pg_prof_area_mini_click); } catch(_){}
	try { if(root.__pg_prof_area_onOpenThread) root.removeEventListener('click', root.__pg_prof_area_onOpenThread); } catch(_){}
	try { if(root.__pg_prof_area_onApptAction) root.removeEventListener('click', root.__pg_prof_area_onApptAction); } catch(_){}
	try { if(window && root.__pg_prof_area_onRtMessage) window.removeEventListener('rt:message', root.__pg_prof_area_onRtMessage); } catch(_){}
	try { if(window && root.__pg_prof_area_onCountersUpdate) window.removeEventListener('counters:update', root.__pg_prof_area_onCountersUpdate); } catch(_){}
	try { if(root.__pg_prof_area_pollInterval) { clearInterval(root.__pg_prof_area_pollInterval); } } catch(_){}
	try { delete root.__pg_prof_area_click; delete root.__pg_prof_area_mini_click; delete root.__pg_prof_area_onOpenThread; delete root.__pg_prof_area_onApptAction; delete root.__pg_prof_area_onRtMessage; delete root.__pg_prof_area_onCountersUpdate; delete root.__pg_prof_area_loadMessages; delete root.__pg_prof_area_pollInterval; } catch(_){}
}
