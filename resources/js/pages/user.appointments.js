import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import axios from 'axios';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import openAppointmentModal from '../utils/appointmentModal';

let calendar = null;

function formatStatusBadge(status) {
    const span = document.createElement('span');
    span.className = 'badge ms-2 bg-secondary';
    span.textContent = status;
    return span;
}

export function init() {
    const el = document.getElementById('calendar');
    if (!el) return;
    const eventsUrl = document.querySelector('meta[name="appointments-events-url"]')?.content;
    const storeUrl = document.querySelector('meta[name="appointments-store-url"]')?.content;
    const acceptTpl = document.querySelector('meta[name="appointments-accept-url"]')?.content;
    const rejectTpl = document.querySelector('meta[name="appointments-reject-url"]')?.content;
    const tz = window.__currentUserTz || Intl.DateTimeFormat().resolvedOptions().timeZone;

    calendar = new Calendar(el, {
        plugins: [ dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin ],
        initialView: 'dayGridMonth',
        timeZone: tz || 'local',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        events: eventsUrl,
        // Allow clicking a day to open the 'Solicitar cita' modal prefilled
        dateClick: function(info) {
            // Ensure we prefill the modal with the clicked day (local) at 09:00
            try {
                const d = info.date; // Date object
                const start = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 9, 0, 0, 0);
                openNewAppointmentModal({ start: start, allDay: !!info.allDay });
            } catch (e) {
                // fallback
                openNewAppointmentModal({ start: info.date, allDay: !!info.allDay });
            }
        },
        selectable: false,
        eventDidMount: function(info) {
            if (info.event.extendedProps && info.event.extendedProps.status) {
                info.el.querySelector('.fc-event-title')?.appendChild(formatStatusBadge(info.event.extendedProps.status));
            }
            // apply color to event element based on status
            try {
                const status = (info.event.extendedProps && info.event.extendedProps.status) ? String(info.event.extendedProps.status).toLowerCase() : 'pending';
                const colors = { pending: '#0d6efd', requested: '#0d6efd', accepted: '#198754', rejected: '#dc3545' };
                const color = colors[status] || '#6c757d';
                if (info.el) {
                    info.el.style.backgroundColor = color;
                    info.el.style.borderColor = color;
                    info.el.style.color = '#ffffff';
                }
            } catch (e) { /* noop */ }
        },
        eventClick: function(info) {
            // show modal with details and accept/reject if patient
            const ev = info.event;
            const start = ev.start ? ev.start.toLocaleString() : '';
            const end = ev.end ? ev.end.toLocaleString() : '';
            const status = ev.extendedProps?.status || 'unknown';
            const statusBadge = `<span class="badge ms-2 bg-secondary">${status}</span>`;
            const body = `
                <div><strong>Título:</strong> ${ev.title || ''} ${statusBadge}</div>
                <div class="mt-2"><strong>Inicio:</strong> ${start}</div>
                <div class="mt-1"><strong>Fin:</strong> ${end}</div>
                <div class="mt-2"><strong>Notas:</strong><div class="small mt-1">${ev.extendedProps.notes || ''}</div></div>
            `;
            const acceptUrl = acceptTpl?.replace('APPOINTMENT_ID', ev.id);
            const rejectUrl = rejectTpl?.replace('APPOINTMENT_ID', ev.id);
            const buttons = [];
            // Only allow actions when appointment is still pending
            if (status === 'pending') {
                if (acceptUrl) buttons.push({ label: 'Aceptar', cls: 'btn-success', action: () => { axios.post(acceptUrl).then(()=>{ window.modalNotification?.('Aceptada','Has aceptado la cita',{template:'success'}); calendar.refetchEvents(); }).catch(()=>{ window.modalNotification?.('Error','No se pudo aceptar la cita',{template:'danger'}); }); } });
                if (rejectUrl) buttons.push({ label: 'Rechazar', cls: 'btn-danger', action: () => {
                        const bodyReason = `<div class="mb-2"><label>Motivo del rechazo</label><textarea id="rejectReasonUser" class="form-control" rows="3" placeholder="Opcional, explica por qué se rechaza"></textarea></div>`;
                        if (typeof window.modalConfirm === 'function') {
                            window.modalConfirm({ title: 'Confirmar rechazo', body: bodyReason, closeClick: false, buttons: [
                                { text: 'Cancelar', className: 'btn-outline-secondary', onClick: ()=>{}, closeOnClick: true },
                                { text: 'Confirmar rechazo', className: 'btn-danger', onClick: ()=>{
                                    const reason = document.getElementById('rejectReasonUser')?.value || null;
                                    axios.post(rejectUrl, { reason }).then(()=>{ window.modalNotification?.('Rechazada','Has rechazado la cita',{template:'info'}); calendar.refetchEvents(); closeAllModals(); }).catch(()=>{ window.modalNotification?.('Error','No se pudo rechazar la cita',{template:'danger'}); });
                                }, closeOnClick: false }
                            ] });
                        } else {
                            const reason = prompt('Motivo del rechazo (opcional)') || null;
                            axios.post(rejectUrl, { reason }).then(()=>{ window.modalNotification?.('Rechazada','Has rechazado la cita',{template:'info'}); calendar.refetchEvents(); }).catch(()=>{ window.modalNotification?.('Error','No se pudo rechazar la cita',{template:'danger'}); });
                        }
                    } });
            }
            // use modalNotification helper to show a modal-like dialog (we have modalConfirm util elsewhere but keep simple)
            const html = `<div>${body}</div>`;
            // Create a lightweight modal using bootstrap's modalConfirm utility if exists
            if (typeof window.modalConfirm === 'function') {
                // If there are no action buttons (non-pending), show only a close button
                const modalButtons = buttons.length ? buttons.map(b=>({ text: b.label, className: b.cls, onClick: b.action })) : [{ text: 'Cerrar', className: 'btn-secondary', onClick: ()=>{}, closeOnClick: true }];
                window.modalConfirm({
                    title: 'Detalle de cita',
                    body: html,
                    closeClick: false,
                    buttons: modalButtons
                });
            } else {
                // fallback to notification and prompt
                window.modalNotification?.('Detalle de cita', html, { template: 'info', delayAutoClose: 8000 });
            }
        }
    });

    calendar.render();

    // Jump to date controls (top header)
    const jumpInput = document.getElementById('jumpToDate');
    const jumpBtn = document.getElementById('jumpToDateBtn');
    if (jumpInput && typeof flatpickr === 'function') {
        try {
            // prefill with calendar's current date
            const currentDate = calendar.getDate();
            flatpickr(jumpInput, { altInput: true, altFormat: 'F j, Y', dateFormat: 'Y-m-d', defaultDate: currentDate || new Date() });
        } catch (e) { /* ignore */ }
    }
    if (jumpBtn && jumpInput) {
        jumpBtn.addEventListener('click', ()=>{
            const v = jumpInput.value; if (!v) return; try { calendar.gotoDate(v); } catch(e){ window.modalNotification?.('Fecha inválida','Revisa la fecha indicada',{template:'warning'}); }
        });
    }

    // New appointment button: show unified modal to create request (patient mode)
    const newBtn = document.getElementById('newAppointmentBtn');
    if (newBtn && storeUrl) {
        newBtn.addEventListener('click', ()=> openAppointmentModal({ mode: 'patient', defaults: {}, urls: { storeUrl }, calendar }));
    }

    // Auto-lanzar modal si venimos con parámetros de profesional (desde búsqueda)
    try {
        const qs = new URLSearchParams(window.location.search || '');
        const pid = qs.get('professional_id');
        const pname = qs.get('professional_name');
        const ptitle = qs.get('professional_title');
        if (pid) {
            // Limpiar la query de la barra para evitar relanzamientos en navegación interna (no recarga)
            try { window.history.replaceState({}, document.title, window.location.pathname); } catch(_){}
            openAppointmentModal({ mode: 'patient', defaults: { professional_id: pid, professional_name: pname, professional_title: ptitle }, urls: { storeUrl }, calendar });
        }
    } catch(_){}

    // Helper: format a Date to 'YYYY-MM-DDTHH:mm' for datetime-local / flatpickr default
    function formatForInput(dt, opts = {}) {
        if (!dt) return '';
        const d = (dt instanceof Date) ? dt : new Date(dt);
        const pad = (n) => String(n).padStart(2,'0');
        const year = d.getFullYear();
        const month = pad(d.getMonth()+1);
        const day = pad(d.getDate());
        const hours = pad(d.getHours());
        const mins = pad(d.getMinutes());
        // If opts.allDay, set to 09:00 by default
        if (opts.allDay && (!opts.preserveTime)) {
            return `${year}-${month}-${day}T09:00`;
        }
        return `${year}-${month}-${day}T${hours}:${mins}`;
    }

    // Reusable modal for creating a patient appointment. Accepts defaults: { start: Date, end: Date, allDay: bool }
    // Use the shared appointment modal helper for consistency
    function openNewAppointmentModal(defaults = {}) {
        openAppointmentModal({ mode: 'patient', defaults: defaults, urls: { storeUrl }, calendar });
    }

    // show placeholder if no events after initial fetch
    calendar.on('eventsSet', function(events) {
        const container = el.parentElement;
        const existing = container.querySelector('.no-appointments');
        if (events.length === 0) {
            if (!existing) {
                const msg = document.createElement('div');
                msg.className = 'no-appointments alert alert-info mt-3';
                msg.textContent = 'No tienes citas en el periodo seleccionado.';
                container.insertBefore(msg, el);
            }
        } else {
            if (existing) existing.remove();
        }
    });
}

export function destroy() {
    if (calendar) { try { calendar.destroy(); } catch(_){} calendar = null; }
}
