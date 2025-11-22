// User area page bootstrap
import { init as initRatings } from '../userarea/ratings.js';
import '../utils/videoCallModal.js';
import { autoOpenOngoingAppointmentCall } from '../utils/videoCallModal.js';
export function init(){
	// Initialize ratings so star buttons become clickable
	try { initRatings(); } catch(_){}
	try { autoOpenOngoingAppointmentCall(); } catch(_){}
	const root = document.querySelector('[data-page="user-area"]') || document.body;

	root.__pg_user_area_onApptAction = async function(ev){
		const btn = ev.target.closest && ev.target.closest('[data-appt-action]');
		if(!btn) return;
		const action = btn.getAttribute('data-appt-action');
		const wrap = document.getElementById('pg-next-appt');
		if(!wrap) return;
		const apptId = wrap.getAttribute('data-appt-id');
		const profId = wrap.getAttribute('data-professional-id');
		const title = wrap.getAttribute('data-title') || 'Cita';
		const startHuman = wrap.getAttribute('data-start-human') || '';
		const endHuman = wrap.getAttribute('data-end-human') || '';
		const notes = wrap.getAttribute('data-notes') || '';
		if(action === 'details'){
			const body = `
				<div class="mb-1"><strong>Título:</strong> ${escapeHtml(title)}</div>
				<div class="mb-1"><strong>Horario:</strong> ${escapeHtml(startHuman)}${endHuman?(' – '+escapeHtml(endHuman)) : ''}</div>
				<div class="mt-2"><strong>Notas:</strong><div class="small mt-1">${escapeHtml(notes) || '<span class="text-muted">(sin notas)</span>'}</div></div>
			`;
			window.modalConfirm?.({ title: 'Detalle de cita', body, buttons: [{ text: 'Cerrar', className: 'btn-outline-secondary', closeOnClick: true }] });
			return;
		}
		if(action === 'join'){
			const currentUserId = window.__authUserId || document.querySelector('meta[name="auth-user-id"]')?.getAttribute('content');
			if(apptId){ window.openAppointmentCall?.({ id: apptId, otherUserId: profId, role: 'paciente', currentUserId }); }
			return;
		}
		if(action === 'reschedule'){
			const existingStart = (wrap.getAttribute('data-start')||'').slice(0,16);
			const existingEnd = (wrap.getAttribute('data-end')||'').slice(0,16);
			const body = `
				<div class=\"mb-2\">Proponer nuevo horario</div>
				<div class=\"mb-2\"><label class=\"form-label small\">Inicio</label><input type=\"datetime-local\" class=\"form-control form-control-sm\" id=\"pg-res-proposed-start\" value=\"${escapeAttr(toLocalInputValue(existingStart))}\"></div>
				<div class=\"mb-2\"><label class=\"form-label small\">Fin</label><input type=\"datetime-local\" class=\"form-control form-control-sm\" id=\"pg-res-proposed-end\" value=\"${escapeAttr(toLocalInputValue(existingEnd))}\"></div>
				<div class=\"mb-2\"><label class=\"form-label small\">Motivo (opcional)</label><textarea class=\"form-control form-control-sm\" id=\"pg-res-reason\" rows=\"2\" maxlength=\"1000\"></textarea></div>
			`;
			const onConfirm = async () => {
				const ps = document.getElementById('pg-res-proposed-start')?.value;
				const pe = document.getElementById('pg-res-proposed-end')?.value;
				const reason = document.getElementById('pg-res-reason')?.value || '';
				if(!ps || !pe){ window.modalNotification?.('Error','Completa inicio y fin',{template:'danger'}); return false; }
				if(apptId){
					try {
						const token = document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content');
						const res = await fetch(`/appointments/${encodeURIComponent(apptId)}/reschedules`, { method:'POST', headers:{ 'Accept':'application/json','Content-Type':'application/json','X-Requested-With':'XMLHttpRequest', ...(token?{'X-CSRF-TOKEN':token}:{}) }, body: JSON.stringify({ proposed_start: toServerDateTime(ps), proposed_end: toServerDateTime(pe), reason }) });
						const j = await res.json().catch(()=>({}));
						if(!res.ok){
							if(j && j.error === 'deadline_passed'){ window.modalNotification?.('No permitido','Se pasó el plazo para reprogramar',{template:'warning'}); }
							else { window.modalNotification?.('Error','No se pudo solicitar reprogramación',{template:'danger'}); }
							return false;
						}
						wrap.setAttribute('data-reschedule-id', String(j?.reschedule?.id||''));
						wrap.setAttribute('data-reschedule-start', new Date(ps).toISOString());
						wrap.setAttribute('data-reschedule-end', new Date(pe).toISOString());
						ensureRescheduleBanner(wrap, ps, pe, j?.reschedule?.id);
						window.modalNotification?.('Enviado','Reprogramación solicitada',{template:'success'});
						return true;
					} catch(_){ window.modalNotification?.('Error','No se pudo solicitar reprogramación',{template:'danger'}); return false; }
				}
				return false;
			};
			window.modalConfirm?.({ title:'Reprogramar cita', body, buttons:[ { text:'Cancelar', className:'btn-outline-secondary', closeOnClick:true }, { text:'Enviar', className:'btn-primary', onClick:onConfirm, closeOnClick:true } ] });
			return;
		}
	};
	root.addEventListener('click', root.__pg_user_area_onApptAction);

	root.__pg_user_area_onRescheduleAction = async function(ev){
		const btn = ev.target.closest && ev.target.closest('[data-reschedule-action]');
		if(!btn) return;
		const action = btn.getAttribute('data-reschedule-action');
		const id = btn.getAttribute('data-reschedule-id');
		if(!id) return;
		const token = document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content');
		try {
			const url = action === 'accept' ? `/reschedules/${encodeURIComponent(id)}/accept` : `/reschedules/${encodeURIComponent(id)}/reject`;
			const res = await fetch(url, { method:'POST', headers:{ 'Accept':'application/json','X-Requested-With':'XMLHttpRequest', ...(token?{'X-CSRF-TOKEN':token}:{}) } });
			const j = await res.json().catch(()=>({}));
			if(!res.ok){ window.modalNotification?.('Error','Acción no disponible',{template:'danger'}); return; }
			const wrap = document.getElementById('pg-next-appt'); if(!wrap) return;
			const banner = document.getElementById('pg-reschedule-banner'); if(banner) banner.remove();
			wrap.removeAttribute('data-reschedule-id'); wrap.removeAttribute('data-reschedule-start'); wrap.removeAttribute('data-reschedule-end');
			if(action === 'accept' && j && j.appointment){
				const startIso = j.appointment.start; const endIso = j.appointment.end;
				const humanStart = toHuman(startIso); const humanEnd = toHuman(endIso, true);
				wrap.setAttribute('data-start', startIso); wrap.setAttribute('data-end', endIso);
				wrap.setAttribute('data-start-human', humanStart); wrap.setAttribute('data-end-human', humanEnd);
				const timeEl = Array.from(wrap.querySelectorAll('.text-muted.small')).find(el=> el.textContent && el.textContent.includes('Horario:')); if(timeEl){ timeEl.textContent = `Horario: ${humanStart}${humanEnd?(' – '+humanEnd):''}`; }
				window.modalNotification?.('Actualizado','Reprogramación aceptada',{template:'success'});
			} else {
				window.modalNotification?.('Listo','Reprogramación rechazada',{template:'info'});
			}
		} catch(_){ window.modalNotification?.('Error','No se pudo completar la acción',{template:'danger'}); }
	};
	root.addEventListener('click', root.__pg_user_area_onRescheduleAction);

	function escapeHtml(s){ if(!s) return ''; return String(s).replace(/[&<>"'`=\/]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c])); }
	function toLocalInputValue(iso){ if(!iso) return ''; const d = new Date(iso); if(isNaN(d)) return ''; const pad=n=>String(n).padStart(2,'0'); return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`; }
	function toServerDateTime(localVal){ if(!localVal) return localVal; return localVal.replace('T',' '); }
	function toHuman(val, timeOnly){ if(!val) return ''; const d=new Date(val); if(isNaN(d)) return ''; const pad=n=>String(n).padStart(2,'0'); const date=`${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()}`; const time=`${pad(d.getHours())}:${pad(d.getMinutes())}`; return timeOnly?time:`${date} ${time}`; }
	function ensureRescheduleBanner(wrap, startVal, endVal, id){ let banner=document.getElementById('pg-reschedule-banner'); if(!banner){ banner=document.createElement('div'); banner.id='pg-reschedule-banner'; banner.className='alert alert-warning py-2 px-3 mt-2'; const firstCol=wrap.querySelector('.me-3')||wrap; (firstCol.parentNode===wrap?wrap:firstCol).insertBefore(banner, firstCol.nextSibling); } const humanStart=toHuman(startVal); const humanEnd=toHuman(endVal); banner.innerHTML=`<div class=\"small\">Reprogramación pendiente: <strong>${escapeHtml(humanStart)}</strong>${humanEnd?` – <strong>${escapeHtml(humanEnd)}</strong>`:''}</div><div class=\"mt-2 d-flex gap-2 flex-wrap\"><button type=\"button\" class=\"btn btn-sm btn-primary\" data-reschedule-action=\"accept\" data-reschedule-id=\"${id||''}\">Aceptar</button><button type=\"button\" class=\"btn btn-sm btn-outline-secondary\" data-reschedule-action=\"reject\" data-reschedule-id=\"${id||''}\">Rechazar</button></div>`; }
	function escapeAttr(s){ return (s||'').replace(/\"/g,'&quot;'); }
}
export function destroy(){
	const root = document.querySelector('[data-page="user-area"]') || document.body;
	try { if(root.__pg_user_area_onApptAction) root.removeEventListener('click', root.__pg_user_area_onApptAction); } catch(_){ }
	try { if(root.__pg_user_area_onRescheduleAction) root.removeEventListener('click', root.__pg_user_area_onRescheduleAction); } catch(_){ }
	try { delete root.__pg_user_area_onApptAction; } catch(_){ }
    try { delete root.__pg_user_area_onRescheduleAction; } catch(_){ }
}
