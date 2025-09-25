import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';

export default function init(){
    // This module mirrors the previous inline script in the Blade view but lives as a module
    try {
        const calendarEl = document.getElementById('calendar');
        if(!calendarEl) return;
        const todayISO = new Date().toISOString().substring(0,10);
        const userTz = window.__currentUserTz || null; // fallback for PJAX cases

        const calendar = new Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            selectable: true,
            validRange: { start: todayISO },
            timeZone: userTz || 'local',
            plugins: [ dayGridPlugin, interactionPlugin ],
            select: function(info){
                const now = new Date();
                const selStart = info.start;
                const selEnd = info.end;
                // helper: detect all-day selection (start/end at midnight)
                const isAllDaySelection = (s,e) => {
                    try {
                        return s && s.getHours() === 0 && s.getMinutes() === 0 && e && e.getHours() === 0 && e.getMinutes() === 0;
                    } catch(_) { return false; }
                };

                if ((selEnd ? selEnd <= now : selStart < now)) {
                    const sameDay = selStart.toDateString() === now.toDateString();
                    if (sameDay) {
                        setDefaultStartEnd();
                        newModal.show();
                        calendar.unselect();
                        return;
                    }
                    let field = (selEnd && selEnd <= now) ? 'fin' : 'inicio';
                    let detail = `Parece que la fecha/hora de <strong>${field}</strong> seleccionada est\u00E1 en el pasado.`;
                    window.modalNotification?.('No puedes seleccionar una fecha/hora pasada', detail, { template: 'warning', delayAutoClose: 6000 });
                    calendar.unselect();
                    return;
                }
                // If the user selected a whole-day cell (midnight timestamps),
                // pick a reasonable time on that date (use current time rounded up to next 30min)
                if (isAllDaySelection(selStart, selEnd)) {
                    const chosen = new Date(selStart);
                    // round current time up to next 30-min slot
                    const roundUpToNext30 = (d) => {
                        const dd = new Date(d);
                        const m = dd.getMinutes();
                        if (m === 0) {
                            dd.setSeconds(0); dd.setMilliseconds(0);
                            return dd;
                        }
                        if (m > 0 && m <= 30) {
                            dd.setMinutes(30, 0, 0);
                        } else {
                            dd.setHours(dd.getHours() + 1, 0, 0, 0);
                        }
                        return dd;
                    };
                    const nowRounded = roundUpToNext30(now);
                    // Apply the rounded time to the chosen date
                    chosen.setHours(nowRounded.getHours(), nowRounded.getMinutes(), 0, 0);
                    const chosenEnd = new Date(chosen.getTime() + 30*60000);
                    document.getElementById('apptStart').value = toLocalInput(chosen);
                    document.getElementById('apptEnd').value = toLocalInput(chosenEnd);
                } else {
                    document.getElementById('apptStart').value = toLocalInput(info.start);
                    document.getElementById('apptEnd').value = toLocalInput(info.end);
                }
                newModal.show();
            },
            events: {
                url: document.querySelector('meta[name="professional-events-url"]')?.getAttribute('content') || '/professional/calendar/events',
                method: 'GET'
            },
        });
    calendar.render();

        const newModalEl = document.getElementById('newAppointmentModal');
        const newModal = new bootstrap.Modal(newModalEl);

        const toLocalInput = (d) => {
            if (!d) return '';
            const pad = (n) => String(n).padStart(2,'0');
            const year = d.getFullYear();
            const month = pad(d.getMonth()+1);
            const day = pad(d.getDate());
            const hours = pad(d.getHours());
            const minutes = pad(d.getMinutes());
            return `${year}-${month}-${day}T${hours}:${minutes}`;
        };

        function setDefaultStartEnd() {
            const now = new Date();
            const end = new Date(now.getTime() + 30*60000);
            document.getElementById('apptStart').value = toLocalInput(now);
            document.getElementById('apptEnd').value = toLocalInput(end);
        }

        const newBtn = document.getElementById('newAppointmentBtn');
        if (newBtn) newBtn.addEventListener('click', ()=>{ setDefaultStartEnd(); newModal.show(); });

        newModalEl.querySelectorAll('[data-bs-dismiss], .btn-close').forEach(btn=>{ btn.addEventListener('click', ()=>{ try{ newModal.hide(); }catch(_){ } }); });

        let patientTimer;
        const patientSearchEl = document.getElementById('patientSearch');
        if (patientSearchEl) {
            patientSearchEl.addEventListener('input', function(e){
                clearTimeout(patientTimer);
                const q = this.value.trim();
                const results = document.getElementById('patientResults');
                results.innerHTML = '';
                document.getElementById('patientId').value = '';
                if (q.length < 2) return;
                patientTimer = setTimeout(()=>{
                    fetch((document.querySelector('meta[name="professional-patients-url"]')?.getAttribute('content') || '/professional/calendar/patients') + '?q='+encodeURIComponent(q))
                        .then(r=>r.json())
                        .then(data=>{
                            results.innerHTML = data.map(u=>`<button type="button" class="list-group-item list-group-item-action" data-id="${u.id}" data-name="${u.name}">${u.name} <small class="text-muted">${u.email}</small></button>`).join('');
                            results.querySelectorAll('button[data-id]').forEach(btn=>{
                                btn.addEventListener('click', ()=>{
                                    document.getElementById('patientSearch').value = btn.getAttribute('data-name');
                                    document.getElementById('patientId').value = btn.getAttribute('data-id');
                                    results.innerHTML = '';
                                });
                            });
                        });
                }, 300);
            });
        }

        const form = document.getElementById('newAppointmentForm');
        if (form) {
            form.addEventListener('submit', function(e){
                e.preventDefault();
                const startVal = document.getElementById('apptStart').value;
                if (!startVal) { window.modalNotification?.('Selecciona la fecha de inicio', 'Debes indicar la fecha y hora de inicio.', { template: 'warning' }); document.getElementById('apptStart').focus(); return; }
                const startDate = new Date(startVal);
                const now = new Date();
                if (startDate < now) { window.modalNotification?.('Fecha/hora de inicio inválida', 'La fecha/hora de inicio que ingresaste es anterior al momento actual. Revisa el campo de inicio.', { template: 'warning' }); document.getElementById('apptStart').focus(); return; }
                const endValLocal = document.getElementById('apptEnd').value;
                if (endValLocal) {
                    const endDate = new Date(endValLocal);
                    if (!(endDate > startDate)) { window.modalNotification?.('Fecha/hora de fin inválida', 'La fecha/hora de fin debe ser posterior a la de inicio. Revisa el campo de fin.', { template: 'warning' }); document.getElementById('apptEnd').focus(); return; }
                }

                const events = calendar.getEvents();
                const newStart = startDate;
                const newEnd = endValLocal ? new Date(endValLocal) : new Date(newStart.getTime() + 30*60000);
                const overlap = events.some(ev=>{
                    const evStart = new Date(ev.start);
                    const evEnd = ev.end ? new Date(ev.end) : new Date(evStart.getTime() + 30*60000);
                    return (evStart < newEnd) && (evEnd > newStart);
                });
                if (overlap) { window.modalNotification?.('Conflicto de horario', 'La cita que intentas crear solapa con otra cita existente. Revisa inicio/fin y el listado de citas en el calendario.', { template: 'warning', delayAutoClose: 8000 }); return; }

                const data = new FormData(form);
                const toUtcIso = (localInput) => { if (!localInput) return null; return new Date(localInput).toISOString(); };
                data.set('start', toUtcIso(document.getElementById('apptStart').value));
                const endVal = document.getElementById('apptEnd').value;
                if (endVal) data.set('end', toUtcIso(endVal));

                fetch(document.querySelector('meta[name="professional-create-url"]')?.getAttribute('content') || '/professional/calendar/events', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                    body: data
                }).then(async r=>{
                    const json = await r.json().catch(()=>({}));
                    if (r.ok && json.ok) {
                        newModal.hide();
                        calendar.refetchEvents();
                        window.modalNotification?.('Cita creada', 'La cita fue creada correctamente.', { template: 'success' });
                    } else {
                        if (json && json.error === 'validation' && json.field) {
                            window.modalNotification?.('Error de validación', json.message || 'Verifica los campos.', { template: 'warning' });
                            if (json.field === 'start') document.getElementById('apptStart').focus();
                            if (json.field === 'end') document.getElementById('apptEnd').focus();
                        } else if (json && json.error === 'conflict' && Array.isArray(json.conflicts)) {
                            const ids = json.conflicts.map(c=>`#${c.id}`).join(', ');
                            window.modalNotification?.('Conflicto de horario', `La nueva cita solapa con citas existentes: ${ids}. Revisa y ajusta los horarios.`, { template: 'warning', delayAutoClose: 8000 });
                        } else {
                            const msg = json.error || json.message || 'Error al crear la cita';
                            window.modalNotification?.('Error', msg, { template: 'danger' }, true, { xhr: json });
                        }
                    }
                }).catch(err=>{ console.error(err); window.modalNotification?.('Error de red', 'No se pudo conectar con el servidor.', { template: 'danger' }); });
            });
        }

    } catch (e) { console.error('professional.calendar init error', e); }
}
