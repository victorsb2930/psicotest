import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
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
	// Fallback: si el usuario tiene rol professional pero llegó a la página de usuario (/appointments)
	// redirigir automáticamente al calendario profesional para evitar ver el calendario equivocado.
	try {
		const role = document.querySelector('meta[name="current-user-role"]')?.content;
		if (role === 'professional' && window.location.pathname.replace(/\/$/, '') === '/appointments') {
			console.info('[appointments] profesional detectado en vista de usuario, redirigiendo a /professional/calendar');
			window.location.replace('/professional/calendar');
			return; // evitar inicializar este módulo
		}
	} catch (_) { }
	const eventsUrl = document.querySelector('meta[name="appointments-events-url"]')?.content;
	const storeUrl = document.querySelector('meta[name="appointments-store-url"]')?.content;
	const cancelTpl = document.querySelector('meta[name="appointments-cancel-url"]')?.content;
	const tz = window.__currentUserTz || Intl.DateTimeFormat().resolvedOptions().timeZone;

	calendar = new Calendar(el, {
		plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin],
		initialView: 'dayGridMonth',
		timeZone: tz || 'local',
		headerToolbar: {
			left: 'prev,next today',
			center: 'title',
			right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
		},
		events: eventsUrl,
		// Click en día: abrir modal para nueva cita (09:00) con fallback
		dateClick: function(info){
			try {
				const d = info.date;
				const start = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 9, 0, 0, 0);
				openNewAppointmentModal({ start, allDay: !!info.allDay });
			} catch(e){
				openNewAppointmentModal({ start: info.date, allDay: !!info.allDay });
			}
		},
		selectable: false,
		eventDidMount: function(info){
			try {
				if (info.event.extendedProps && info.event.extendedProps.status) {
					const badge = formatStatusBadge(info.event.extendedProps.status);
					info.el.querySelector('.fc-event-title')?.appendChild(badge);
				}
				const status = (info.event.extendedProps && info.event.extendedProps.status) ? String(info.event.extendedProps.status).toLowerCase() : 'pending';
				const colors = { pending: '#0d6efd', requested: '#0d6efd', accepted: '#198754', rejected: '#dc3545', cancelled: '#6c757d' };
				const color = colors[status] || '#6c757d';
				if (info.el) {
					info.el.style.backgroundColor = color;
					info.el.style.borderColor = color;
					info.el.style.color = '#ffffff';
				}
			} catch(_){}
		},
		eventClick: function (info) {
			// show modal with details and accept/reject if patient
			const ev = info.event;
			const start = ev.start ? ev.start.toLocaleString() : '';
			const end = ev.end ? ev.end.toLocaleString() : '';
			const status = ev.extendedProps?.status || 'unknown';
			const statusBadge = `<span class="badge ms-2 bg-secondary">${status}</span>`;
			const professionalName = ev.extendedProps?.professional_name || '';
			const patientNotesRaw = ev.extendedProps?.notes || '';
			const rejectionRaw = ev.extendedProps?.rejection_reason || '';
			const esc = s => String(s).replace(/[<>&]/g,c=>({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]));
			const patientNotes = esc(patientNotesRaw);
			const rejectionReason = esc(rejectionRaw);
			let extraSection = '';
			if (status === 'rejected') {
				extraSection = `<div class=\"mt-2\"><strong>Motivo del rechazo (profesional):</strong><div class=\"small mt-1 text-danger\">${rejectionReason || '(Sin motivo registrado)'}</div></div>`;
			}
			const body = `
				<div><strong>Título:</strong> ${esc(ev.title || '')} ${statusBadge}</div>
				<div class=\"mt-2\"><strong>Profesional:</strong> ${esc(professionalName)}</div>
				<div class=\"mt-2\"><strong>Inicio:</strong> ${esc(start)}</div>
				<div class=\"mt-1\"><strong>Fin:</strong> ${esc(end)}</div>
				<div class=\"mt-2\"><strong>Tu nota de solicitud:</strong><div class=\"small mt-1\">${patientNotes}</div></div>
				${extraSection}
			`;
			const cancelUrl = (cancelTpl ? cancelTpl.replace('APPOINTMENT_ID', ev.id) : (`/appointments/${ev.id}/cancel`));
			const buttons = [];
			// Only allow cancel when appointment is still pending (patient cannot accept/reject)
			if (status === 'pending' && cancelUrl) {
				buttons.push({
					label: 'Cancelar solicitud', cls: 'btn-danger', action: () => {
						const bodyReason = `<div class="mb-2"><label>Motivo de la cancelación</label><textarea id="cancelReasonUser" class="form-control" rows="3" placeholder="Explica por qué cancelas"></textarea></div>`;
						modalConfirm({
							title: 'Confirmar cancelación', body: bodyReason, closeClick: false, buttons: [
								{ text: 'Cerrar', className: 'btn-outline-secondary', onClick: () => { }, closeOnClick: true },
								{
									text: 'Cancelar solicitud', className: 'btn-danger', onClick: () => {
										const reason = document.getElementById('cancelReasonUser')?.value || null;
										axios.post(cancelUrl, { reason }).then(() => { window.modalNotification?.('Cancelada', 'Has cancelado la solicitud', { template: 'info' }); calendar.refetchEvents(); try { closeAllModals(); } catch (_) { } }).catch(() => { window.modalNotification?.('Error', 'No se pudo cancelar la solicitud', { template: 'danger' }); });
									}, closeOnClick: false
								}
							]
						});
					}
				});
			}
			// Ensure a Close button is always present
			buttons.push({ label: 'Cerrar', cls: 'btn-outline-secondary', action: () => { }, closeOnClick: true });

			// use modalNotification helper to show a modal-like dialog (we have modalConfirm util elsewhere but keep simple)
			const html = `<div>${body}</div>`;
			const modalButtons = buttons.map(b => ({ text: b.label, className: b.cls, onClick: b.action, closeOnClick: b.closeOnClick }));
			modalConfirm({
				title: 'Detalle de cita',
				body: html,
				closeClick: false,
				buttons: modalButtons
			});
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
		jumpBtn.addEventListener('click', () => {
			const v = jumpInput.value; if (!v) return; try { calendar.gotoDate(v); } catch (e) { window.modalNotification?.('Fecha inválida', 'Revisa la fecha indicada', { template: 'warning' }); }
		});
	}

	// Auto-lanzar modal si venimos con parámetros de profesional (desde búsqueda)
	try {
		const qs = new URLSearchParams(window.location.search || '');
		const pid = qs.get('professional_id');
		const pname = qs.get('professional_name');
		const ptitle = qs.get('professional_title');
		if (pid) {
			// Limpiar la query de la barra para evitar relanzamientos en navegación interna (no recarga)
			try { window.history.replaceState({}, document.title, window.location.pathname); } catch (_) { }
			openAppointmentModal({ mode: 'patient', defaults: { professional_id: pid, professional_name: pname, professional_title: ptitle }, urls: { storeUrl }, calendar });
		}
	} catch (_) { }

	// Reusable modal for creating a patient appointment. Accepts defaults: { start: Date, end: Date, allDay: bool }
	// Use the shared appointment modal helper for consistency
	function openNewAppointmentModal(defaults = {}) {
		openAppointmentModal({ mode: 'patient', defaults: defaults, urls: { storeUrl }, calendar });
	}

	// show placeholder if no events after initial fetch
	calendar.on('eventsSet', function (events) {
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
	if (calendar) { try { calendar.destroy(); } catch (_) { } calendar = null; }
}
