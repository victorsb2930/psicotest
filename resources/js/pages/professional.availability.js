import axios from 'axios';

export function init() {
	const dayNames = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];

	function addSlotModal() {
		const body = `<form id="formAddSlot">
			<div class="mb-2"><label>Día</label><select name="day_of_week" class="form-select">${dayNames.map((d,i)=>`<option value="${i}">${d}</option>`).join('')}</select></div>
			<div class="mb-2"><label>Inicio (HH:MM)</label><input name="start_time" type="time" class="form-control" required></div>
			<div class="mb-2"><label>Fin (HH:MM)</label><input name="end_time" type="time" class="form-control" required></div>
		</form>`;
		window.modalConfirm?.({ title:'Añadir rango', body, buttons:[
			{ text:'Cancelar', className:'btn-outline-secondary', closeOnClick:true },
			{ text:'Guardar', className:'btn-primary', onClick: async ($m)=>{
				const f = document.getElementById('formAddSlot');
				if(!f) return; const fd = new FormData(f);
				try {
					const res = await axios.post('/professional/availability', Object.fromEntries(fd.entries()));
					if(res.data?.ok){ window.modalNotification?.('Hecho','Rango añadido',{template:'success'}); location.reload(); }
					else { window.modalNotification?.('Error', res.data?.message || 'No se pudo añadir',{template:'danger'}); }
				} catch(e){ window.modalNotification?.('Error','Error de red',{template:'danger'}); }
			}, closeOnClick:false }
		] });
	}

	function addExceptionModal() {
		const body = `<form id="formAddExc">
			<div class="mb-2"><label>Fecha</label><input name="date" type="date" class="form-control" required></div>
			<div class="mb-2"><label>Tipo</label><select name="status" class="form-select"><option value="blocked">Bloquear</option><option value="available">Disponible extra</option></select></div>
			<div class="mb-2"><label>Inicio (opcional)</label><input name="start_time" type="time" class="form-control"></div>
			<div class="mb-2"><label>Fin (opcional)</label><input name="end_time" type="time" class="form-control"></div>
			<div class="mb-2"><label>Razón (opcional)</label><input name="reason" type="text" class="form-control" maxlength="255"></div>
		</form>`;
		window.modalConfirm?.({ title:'Añadir excepción', body, buttons:[
			{ text:'Cancelar', className:'btn-outline-secondary', closeOnClick:true },
			{ text:'Guardar', className:'btn-primary', onClick: async ($m)=>{
				const f = document.getElementById('formAddExc');
				if(!f) return; const fd = new FormData(f);
				try {
					const res = await axios.post('/professional/availability/exceptions', Object.fromEntries(fd.entries()));
					if(res.data?.ok){ window.modalNotification?.('Hecho','Excepción añadida',{template:'success'}); location.reload(); }
					else { window.modalNotification?.('Error', res.data?.message || 'No se pudo añadir',{template:'danger'}); }
				} catch(e){ window.modalNotification?.('Error','Error de red',{template:'danger'}); }
			}, closeOnClick:false }
		] });
	}

	document.getElementById('btn-add-slot')?.addEventListener('click', addSlotModal);
	document.getElementById('btn-add-exc')?.addEventListener('click', addExceptionModal);

	// delete handlers
	Array.from(document.querySelectorAll('.btn-del-slot')).forEach(btn => {
		btn.addEventListener('click', async ()=>{
			const id = btn.getAttribute('data-id'); if(!id) return;
			window.modalConfirm?.({ title:'Eliminar', body:'¿Eliminar este rango?', buttons:[
				{ text:'Cancelar', className:'btn-outline-secondary', closeOnClick:true },
				{ text:'Eliminar', className:'btn-danger', onClick: async ()=>{
					try { const res = await axios.delete(`/professional/availability/${id}`); if(res.data?.ok){ location.reload(); } else { window.modalNotification?.('Error','No se pudo eliminar',{template:'danger'}); } } catch(e){ window.modalNotification?.('Error','Error de red',{template:'danger'}); }
				} }
			] });
		});
	});
	Array.from(document.querySelectorAll('.btn-del-exc')).forEach(btn => {
		btn.addEventListener('click', async ()=>{
			const id = btn.getAttribute('data-id'); if(!id) return;
			window.modalConfirm?.({ title:'Eliminar', body:'¿Eliminar esta excepción?', buttons:[
				{ text:'Cancelar', className:'btn-outline-secondary', closeOnClick:true },
				{ text:'Eliminar', className:'btn-danger', onClick: async ()=>{
					try { const res = await axios.delete(`/professional/availability/exceptions/${id}`); if(res.data?.ok){ location.reload(); } else { window.modalNotification?.('Error','No se pudo eliminar',{template:'danger'}); } } catch(e){ window.modalNotification?.('Error','Error de red',{template:'danger'}); }
				} }
			] });
		});
	});
}

export function destroy() {
	// no-op for now
}
