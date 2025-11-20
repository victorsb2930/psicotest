export function init() {
	const DAY_NAMES = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
	const ACTION_CELL_CLASS = 'text-nowrap text-center';

	const qs = sel => document.querySelector(sel);

	function formatDateForTable(dateString){
		if(!dateString) return '';
		try{
			const d = new Date(dateString);
			if(isNaN(d)) return dateString;
			const day = String(d.getUTCDate()).padStart(2,'0');
			const month = String(d.getUTCMonth()+1).padStart(2,'0');
			const year = d.getUTCFullYear();
			return `${day}/${month}/${year}`;
		}catch(e){ return dateString; }
	}

	function normalizeDateForInput(dateString){
		if(!dateString) return '';
		try{
			const d = new Date(dateString);
			if(isNaN(d)) return dateString;
			const day = String(d.getUTCDate()).padStart(2,'0');
			const month = String(d.getUTCMonth()+1).padStart(2,'0');
			const year = d.getUTCFullYear();
			return `${year}-${month}-${day}`;
		}catch(e){ return dateString; }
	}
	function buildSlotRow(slot){
		const start = slot.start_time.slice(0,5); const end = slot.end_time.slice(0,5);
		const tr = document.createElement('tr');
		tr.dataset.id = slot.id; tr.dataset.day = slot.day_of_week; tr.dataset.start = start; tr.dataset.end = end;
		tr.innerHTML = `<td>${DAY_NAMES[slot.day_of_week]}</td><td>${start}</td><td>${end}</td><td class="${ACTION_CELL_CLASS}">${slotActionButtons(slot.id)}</td>`;
		return tr;
	}

	function buildExceptionRow(exc){
		const st = exc.start_time? exc.start_time.slice(0,5): ''; const et = exc.end_time? exc.end_time.slice(0,5): '';
		const tr = document.createElement('tr');
		tr.dataset.id = exc.id; tr.dataset.date = exc.date; tr.dataset.status = exc.status; tr.dataset.start = st; tr.dataset.end = et; tr.dataset.reason = exc.reason || '';
		const displayDate = formatDateForTable(exc.date);
		tr.innerHTML = `<td>${displayDate}</td><td>${exc.status==='blocked'?'Bloqueado':'Disponible extra'}</td><td>${st||'-'}</td><td>${et||'-'}</td><td>${exc.reason||'-'}</td><td class="${ACTION_CELL_CLASS}">${exceptionActionButtons(exc.id)}</td>`;
		return tr;
	}

	function slotActionButtons(id){
		return `<button class="btn btn-sm btn-outline-secondary" data-action="edit-slot" data-id="${id}">Editar</button> <button class="btn btn-sm btn-outline-primary" data-action="dup-slot" data-id="${id}">Duplicar</button> <button class="btn btn-sm btn-outline-danger" data-action="del-slot" data-id="${id}">Eliminar</button>`;
	}
	function exceptionActionButtons(id){
		return `<button class="btn btn-sm btn-outline-secondary" data-action="edit-exc" data-id="${id}">Editar</button> <button class="btn btn-sm btn-outline-primary" data-action="dup-exc" data-id="${id}">Duplicar</button> <button class="btn btn-sm btn-outline-danger" data-action="del-exc" data-id="${id}">Eliminar</button>`;
	}

	function ensureEmpty(tableSel, cols, message){
		const tbody = qs(`${tableSel} tbody`); if(!tbody) return;
		if(!tbody.querySelector('tr')){ const tr = document.createElement('tr'); tr.innerHTML = `<td colspan="${cols}" class="text-muted">${message}</td>`; tbody.appendChild(tr); }
	}

	function updateSlotRow(tr, slot){
		if(!tr) return;
		tr.dataset.day = slot.day_of_week; tr.dataset.start = slot.start_time.slice(0,5); tr.dataset.end = slot.end_time.slice(0,5);
		const tds = tr.querySelectorAll('td'); if(tds.length>=4){ tds[0].textContent = DAY_NAMES[slot.day_of_week]; tds[1].textContent = slot.start_time.slice(0,5); tds[2].textContent = slot.end_time.slice(0,5); }
	}

	function updateExceptionRow(tr, exc){
		if(!tr) return;
		const st = exc.start_time? exc.start_time.slice(0,5): ''; const et = exc.end_time? exc.end_time.slice(0,5): '';
		tr.dataset.date = exc.date; tr.dataset.status = exc.status; tr.dataset.start = st; tr.dataset.end = et; tr.dataset.reason = exc.reason || '';
		const displayDate = formatDateForTable(exc.date);
		const tds = tr.querySelectorAll('td'); if(tds.length>=6){ tds[0].textContent = displayDate; tds[1].textContent = exc.status==='blocked'?'Bloqueado':'Disponible extra'; tds[2].textContent = st||'-'; tds[3].textContent = et||'-'; tds[4].textContent = exc.reason||'-'; }
	}

	function openSlotModal(mode, tr){
		const data = tr? { day: tr.dataset.day, start: tr.dataset.start, end: tr.dataset.end }: { day:'0', start:'', end:'' };
		const titleMap = { add:'Añadir rango', edit:'Editar rango', dup:'Duplicar rango' };
		const body = `<form id="formSlot">
			<div class="mb-2"><label>Día</label><select name="day_of_week" class="form-select">${DAY_NAMES.map((d,i)=>`<option value="${i}" ${String(i)===String(data.day)?'selected':''}>${d}</option>`).join('')}</select></div>
			<div class="mb-2"><label>Inicio (HH:MM)</label><input name="start_time" type="time" value="${data.start}" class="form-control" required></div>
			<div class="mb-2"><label>Fin (HH:MM)</label><input name="end_time" type="time" value="${data.end}" class="form-control" required></div>
		</form>`;
		window.modalConfirm?.({ title: titleMap[mode], body, buttons:[
			{ text:'Cancelar', className:'btn-outline-secondary', closeOnClick:true },
			{ text: mode==='add'?'Guardar': (mode==='edit'?'Guardar cambios':'Crear copia'), className:'btn-primary', closeOnClick:true, onClick: async ()=>{
				const f = document.getElementById('formSlot'); if(!f) return; const fd = new FormData(f); const payload = Object.fromEntries(fd.entries());
				try {
					let res;
					if(mode==='edit') { res = await axios.patch(`/professional/availability/${tr.dataset.id}`, payload); }
					else { res = await axios.post('/professional/availability', payload); }
					if(res.data?.ok){
						if(mode==='edit') { updateSlotRow(tr, res.data.slot); window.modalNotification?.('Actualizado','Rango modificado',{template:'success'}); }
						else { const tbody = qs('#weeklySlots tbody'); if(tbody){ const empty = tbody.querySelector('tr td.text-muted'); if(empty) empty.closest('tr').remove(); tbody.appendChild(buildSlotRow(res.data.slot)); } window.modalNotification?.(mode==='dup'?'Hecho':'Hecho', mode==='dup'?'Rango duplicado':'Rango añadido',{template:'success'}); }
					} else { window.modalNotification?.('Error', res.data?.message||'Error',{template:'danger'}); }
				} catch(e){ window.modalNotification?.('Error','Error de red',{template:'danger'}); }
			}}
		]});
	}

	function openExceptionModal(mode, tr){
		const raw = tr? { date: tr.dataset.date, status: tr.dataset.status, start: tr.dataset.start, end: tr.dataset.end, reason: tr.dataset.reason }: { date:'', status:'blocked', start:'', end:'', reason:'' };
		const data = { date: normalizeDateForInput(raw.date), status: raw.status, start: raw.start, end: raw.end, reason: raw.reason };
		const titleMap = { add:'Añadir excepción', edit:'Editar excepción', dup:'Duplicar excepción' };
		const body = `<form id="formExc">
			<div class="mb-2"><label>Fecha</label><input name="date" type="date" value="${data.date}" class="form-control" required></div>
			<div class="mb-2"><label>Tipo</label><select name="status" class="form-select"><option value="blocked" ${data.status==='blocked'?'selected':''}>Bloquear</option><option value="available" ${data.status==='available'?'selected':''}>Disponible extra</option></select></div>
			<div class="mb-2"><label>Inicio (opcional)</label><input name="start_time" type="time" value="${data.start}" class="form-control"></div>
			<div class="mb-2"><label>Fin (opcional)</label><input name="end_time" type="time" value="${data.end}" class="form-control"></div>
			<div class="mb-2"><label>Razón (opcional)</label><input name="reason" type="text" value="${data.reason||''}" class="form-control" maxlength="255"></div>
		</form>`;
		window.modalConfirm?.({ title: titleMap[mode], body, buttons:[
			{ text:'Cancelar', className:'btn-outline-secondary', closeOnClick:true },
			{ text: mode==='add'?'Guardar': (mode==='edit'?'Guardar cambios':'Crear copia'), className:'btn-primary', closeOnClick:true, onClick: async ()=>{
				const f = document.getElementById('formExc'); if(!f) return; const fd = new FormData(f); const payload = Object.fromEntries(fd.entries());
				try {
					let res;
					if(mode==='edit'){ res = await axios.patch(`/professional/availability/exceptions/${tr.dataset.id}`, payload); }
					else { res = await axios.post('/professional/availability/exceptions', payload); }
					if(res.data?.ok){
						if(mode==='edit'){ updateExceptionRow(tr, res.data.exception); window.modalNotification?.('Actualizado','Excepción modificada',{template:'success'}); }
						else { const tbody = qs('#exceptionsList tbody'); if(tbody){ const empty = tbody.querySelector('tr td.text-muted'); if(empty) empty.closest('tr').remove(); tbody.prepend(buildExceptionRow(res.data.exception)); } window.modalNotification?.('Hecho', mode==='dup'?'Excepción duplicada':'Excepción añadida',{template:'success'}); }
					} else { window.modalNotification?.('Error', res.data?.message||'Error',{template:'danger'}); }
				} catch(e){ window.modalNotification?.('Error','Error de red',{template:'danger'}); }
			}}
		]});
	}

	function confirmDeleteSlot(tr){
		if(!tr) return; const id = tr.dataset.id;
		window.modalConfirm?.({ title:'Eliminar', body:'¿Eliminar este rango?', buttons:[
			{ text:'Cancelar', className:'btn-outline-secondary', closeOnClick:true },
			{ text:'Eliminar', className:'btn-danger', closeOnClick:true, onClick: async ()=>{
				try { const res = await axios.delete(`/professional/availability/${id}`); if(res.data?.ok){ tr.remove(); window.modalNotification?.('Eliminado','Rango eliminado',{template:'success'}); ensureEmpty('#weeklySlots',4,'Sin rangos definidos'); } else { window.modalNotification?.('Error','No se pudo eliminar',{template:'danger'}); } } catch(e){ window.modalNotification?.('Error','Error de red',{template:'danger'}); }
			}}
		]});
	}

	function confirmDeleteException(tr){
		if(!tr) return; const id = tr.dataset.id;
		window.modalConfirm?.({ title:'Eliminar', body:'¿Eliminar esta excepción?', buttons:[
			{ text:'Cancelar', className:'btn-outline-secondary', closeOnClick:true },
			{ text:'Eliminar', className:'btn-danger', closeOnClick:true, onClick: async ()=>{
				try { const res = await axios.delete(`/professional/availability/exceptions/${id}`); if(res.data?.ok){ tr.remove(); window.modalNotification?.('Eliminado','Excepción eliminada',{template:'success'}); ensureEmpty('#exceptionsList',6,'Sin excepciones recientes'); } else { window.modalNotification?.('Error','No se pudo eliminar',{template:'danger'}); } } catch(e){ window.modalNotification?.('Error','Error de red',{template:'danger'}); }
			}}
		]});
	}

	// Event delegation
	document.addEventListener('click', (ev)=>{
		const btn = ev.target.closest('button'); if(!btn) return;
		const action = btn.dataset.action;
		if(btn.id==='btn-add-slot'){ openSlotModal('add'); }
		else if(btn.id==='btn-add-exc'){ openExceptionModal('add'); }
		else if(action==='edit-slot'){ const tr = btn.closest('tr'); openSlotModal('edit', tr); }
		else if(action==='dup-slot'){ const tr = btn.closest('tr'); openSlotModal('dup', tr); }
		else if(action==='del-slot'){ const tr = btn.closest('tr'); confirmDeleteSlot(tr); }
		else if(action==='edit-exc'){ const tr = btn.closest('tr'); openExceptionModal('edit', tr); }
		else if(action==='dup-exc'){ const tr = btn.closest('tr'); openExceptionModal('dup', tr); }
		else if(action==='del-exc'){ const tr = btn.closest('tr'); confirmDeleteException(tr); }
	});

	// Initial ensure (in case tables start empty after dynamic removal before load)
	ensureEmpty('#weeklySlots',4,'Sin rangos definidos');
	ensureEmpty('#exceptionsList',6,'Sin excepciones recientes');
}

export function destroy() {}
