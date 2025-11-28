import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import openAppointmentModal from '../utils/appointmentModal';
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect/index.js';
import 'flatpickr/dist/plugins/monthSelect/style.css';

export default function init() {
	try {
		const $calendarEl = $('#calendar');
		if ($calendarEl.length === 0) return;
		try { console.info('[professional.calendar] init'); } catch (_) { }
		const todayISO = new Date().toISOString().substring(0, 10);
		const userTz = window.__currentUserTz || null;

		const calendar = new Calendar($calendarEl[0], {
			initialView: 'dayGridMonth',
			selectable: true,
			timeZone: userTz || 'local',
			headerToolbar: {
				left: 'prev,next today monthJump',
				center: 'title',
				right: 'dayGridMonth,dayGridWeek,dayGridDay'
			},
			eventsSet: function (evts) {
				try { console.log('[professional.calendar] eventsSet count', evts.length, evts.map(e => e.id)); } catch (_) { }
				// Quick DOM visibility check: count rendered nodes
				try {
					const nodes = document.querySelectorAll('.fc-daygrid-event');
					console.log('[professional.calendar] DOM .fc-daygrid-event nodes', nodes.length);
				} catch (_) { }
			},
			customButtons: {
				monthJump: {
					text: 'Mes/Año',
					click: function () {
						const body = `<div class="mb-2"><label class="form-label">Seleccione mes y año</label><input id="jumpMonthPickerProf" class="form-control"></div>`;
						modalConfirm({
							modalId: 'modal-jump-month-prof', title: 'Ir a mes', body, closeClick: true, onClickYes: function () {
								const v = document.getElementById('jumpMonthPickerProf')?.value;
								if (!v) { modalNotification('Fecha requerida', 'Selecciona un mes y año', { template: 'warning' }); return; }
								try { calendar.gotoDate(v); } catch (e) { modalNotification('Fecha inválida', 'Selecciona un mes válido', { template: 'warning' }); }
							}
						});
						setTimeout(() => {
							try {
								const el = document.getElementById('jumpMonthPickerProf');
								if (el) {
									flatpickr(el, {
										plugins: [new monthSelectPlugin({ shorthand: false, dateFormat: 'Y-m', altFormat: 'F Y' })],
										dateFormat: 'Y-m-01',
										defaultDate: new Date()
									});
								}
							} catch (_) { }
						}, 120);
					}
				}
			},
			plugins: [dayGridPlugin, interactionPlugin],
			// Style events and attach data-status; hiding handled via CSS + refetch
			eventDidMount: function (info) {
				try {
					const status = (info.event.extendedProps && info.event.extendedProps.status) ? String(info.event.extendedProps.status).toLowerCase() : 'pending';
					const colors = { pending: '#0d6efd', requested: '#0d6efd', accepted: '#198754', rejected: '#dc3545', cancelled: '#6c757d' };
					const color = colors[status] || '#6c757d';
					if (info.el) {
						info.el.setAttribute('data-status', status);
						info.el.style.backgroundColor = color;
						info.el.style.borderColor = color;
						info.el.style.color = '#ffffff';
						if (status === 'pending') info.el.classList.add('pg-pending-highlight');
					}
				} catch (e) { /* ignore styling errors */ }
			},
			// selection handler - delegate to shared modal helper
			select: function (info) {
				openAppointmentModal({ mode: 'professional', defaults: { start: info.start, end: info.end }, urls: { professionalCreateUrl: document.querySelector('meta[name="professional-create-url"]')?.getAttribute('content'), professionalPatientsUrl: document.querySelector('meta[name="professional-patients-url"]')?.getAttribute('content') }, calendar });
			},
			eventClick: function (info) {
				const ev = info.event;
				const start = ev.start ? ev.start.toLocaleString() : '';
				const end = ev.end ? ev.end.toLocaleString() : '';
				// Read status from event extendedProps (e.g. 'pending','accepted','rejected')
				const status = (ev.extendedProps && ev.extendedProps.status) ? String(ev.extendedProps.status).toLowerCase() : 'pending';
				const statusLabel = (status === 'accepted') ? 'Aceptada' : (status === 'rejected' ? 'Rechazada' : (status === 'cancelled' ? 'Cancelada' : 'Pendiente'));
				const statusClass = (status === 'accepted') ? 'bg-success' : (status === 'rejected' ? 'bg-danger' : (status === 'cancelled' ? 'bg-secondary' : 'bg-warning'));
				const patientName = ev.extendedProps?.patient_name || '';
				const body = `
					<div class="mb-2"><strong>Estado:</strong> <span class="badge ${statusClass} text-white">${statusLabel}</span></div>
					<div><strong>Título:</strong> ${ev.title || ''}</div>
					<div class="mt-2"><strong>Paciente:</strong> ${patientName}</div>
					<div class="mt-2"><strong>Inicio:</strong> ${start}</div>
					<div class="mt-1"><strong>Fin:</strong> ${end}</div>
					<div class="mt-2"><strong>Notas:</strong><div class="small mt-1">${ev.extendedProps.notes || ''}</div></div>
				`;
				const acceptTpl = document.querySelector('meta[name="professional-accept-url"]')?.getAttribute('content') || '/professional/calendar/events/' + ev.id + '/accept';
				const rejectTpl = document.querySelector('meta[name="professional-reject-url"]')?.getAttribute('content') || '/professional/calendar/events/' + ev.id + '/reject';
				const acceptUrl = acceptTpl.replace('APPOINTMENT_ID', ev.id);
				const rejectUrl = rejectTpl.replace('APPOINTMENT_ID', ev.id);
				const buttons = [];
				// Only show actionable Accept/Reject buttons when the appointment is pending
				if (status === 'pending') {
					if (acceptUrl) buttons.push({
						text: 'Aceptar', className: 'btn-success', onClick: () => {
							axios.post(acceptUrl).then(() => {
								modalNotification('Aceptada', 'Has aceptado la cita', { template: 'success' });
								calendar.refetchEvents();
								closeAllModals();
							}).catch(() => { modalNotification('Error', 'No se pudo aceptar la cita', { template: 'danger' }); });
						}
					});
					if (rejectUrl) buttons.push({
						text: 'Rechazar', className: 'btn-danger', onClick: () => {
							const bodyReason = `<div class=\"mb-2\"><label>Motivo del rechazo (obligatorio)</label><textarea id=\"rejectReason\" class=\"form-control\" rows=\"3\" placeholder=\"Explica el motivo del rechazo\" required></textarea><div class=\"form-text\">Debes indicar un motivo (mínimo 3 caracteres).</div></div>`;
							modalConfirm({
								title: 'Confirmar rechazo', body: bodyReason, closeClick: false, buttons: [
									{ text: 'Cancelar', className: 'btn-outline-secondary', onClick: () => { }, closeOnClick: true },
									{
										text: 'Confirmar rechazo', className: 'btn-danger', onClick: (btnEl) => {
											if (btnEl && btnEl.disabled) return; if (btnEl) btnEl.disabled = true;
											const reason = (document.getElementById('rejectReason')?.value || '').trim();
											if (reason.length < 3) {
												modalNotification('Motivo requerido', 'Indica al menos 3 caracteres', { template: 'warning' });
												if (btnEl) btnEl.disabled = false;
												return;
											}
											axios.post(rejectUrl, { reason }).then(() => {
												modalNotification('Rechazada', 'Has rechazado la cita', { template: 'info' });
												calendar.refetchEvents();
												closeAllModals();
											}).catch(() => {
												modalNotification('Error', 'No se pudo rechazar la cita', { template: 'danger' });
												if (btnEl) btnEl.disabled = false;
											});
										}, closeOnClick: false
									}
								]
							});
						}
					});
				}
				// If not pending, show a small helper note in the modal (buttons will be omitted to prevent actions)
				// (Close button is added later)
				// Optionally we could add disabled visual buttons; keeping UI simple by hiding actions.
				// Ensure there's always an explicit Close/Cancelar control so the modal can be dismissed.
				buttons.push({ text: 'Cerrar', className: 'btn-outline-secondary', closeOnClick: true });
				modalConfirm({ title: 'Detalle de cita', body, closeClick: false, buttons });
			},
				// dateClick: clicking a day should open modal with sensible defaults
				// Debounce repeated clicks to prevent opening multiple modals accidentally.
				dateClick: (function () {
					let _lastTs = 0;
					const MIN_INTERVAL = 600; // ms
					return function (info) {
						try {
							const now = Date.now();
							if (now - _lastTs < MIN_INTERVAL) return; // ignore rapid duplicate clicks
							_lastTs = now;
							const clicked = info.date;
							const today = new Date();
							const sameDay = clicked.getFullYear() === today.getFullYear() && clicked.getMonth() === today.getMonth() && clicked.getDate() === today.getDate();
							if (sameDay) {
								// Let shared helper round to next 15 minutes based on now
								openAppointmentModal({ mode: 'professional', defaults: {}, urls: { professionalCreateUrl: document.querySelector('meta[name="professional-create-url"]')?.getAttribute('content'), professionalPatientsUrl: document.querySelector('meta[name="professional-patients-url"]')?.getAttribute('content') }, calendar });
							} else {
								const start = new Date(clicked.getFullYear(), clicked.getMonth(), clicked.getDate(), 9, 0, 0, 0);
								openAppointmentModal({ mode: 'professional', defaults: { start }, urls: { professionalCreateUrl: document.querySelector('meta[name="professional-create-url"]')?.getAttribute('content'), professionalPatientsUrl: document.querySelector('meta[name="professional-patients-url"]')?.getAttribute('content') }, calendar });
							}
						} catch (e) {
							openAppointmentModal({ mode: 'professional', defaults: {}, urls: { professionalCreateUrl: document.querySelector('meta[name="professional-create-url"]')?.getAttribute('content'), professionalPatientsUrl: document.querySelector('meta[name="professional-patients-url"]')?.getAttribute('content') }, calendar });
						}
					};
				})(),
			// Dynamic events source with robust handling (supports debug wrapper)
			events: function (fetchInfo, successCallback, failureCallback) {
				const url = document.querySelector('meta[name="professional-events-url"]')?.getAttribute('content') || '/professional/calendar/events';
				const params = new URLSearchParams({ start: fetchInfo.startStr, end: fetchInfo.endStr });
				try { console.debug('[professional.calendar] fetching events', url, Object.fromEntries(params.entries())); } catch (_) { }
				fetch(url + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
					.then(r => {
						if (!r.ok) {
							console.error('Calendar events fetch failed', r.status, r.statusText);
							showCalendarError('No se pudieron cargar las citas (' + r.status + ')');
							failureCallback && failureCallback(r);
							return null;
						}
						return r.json();
					})
					.then(data => {
						if (!data) return;
						clearCalendarError();
						let list = [];
						if (Array.isArray(data)) {
							list = data;
						} else if (data && data.debug && Array.isArray(data.events)) {
							list = data.events;
						} else if (data && Array.isArray(data.data)) { // fallback pattern
							list = data.data;
						}
						try { console.debug('[professional.calendar] events normalized', list.length); } catch (_) { }
						if (list.length === 0) {
							try { console.warn('[professional.calendar] empty events list after fetch; raw=', data); } catch (_) { }
						}
						// Force each event to have a backgroundColor to rule out CSS hiding
						list = list.map(ev => ({ ...ev, backgroundColor: '#0d6efd', borderColor: '#0d6efd' }));
						if (document.body.classList.contains('pg-hide-cancelled')) {
							list = list.filter(e => String(e.status).toLowerCase() !== 'cancelled');
						}
						successCallback(list);
					})
					.catch(err => {
						console.error('Calendar events fetch error', err);
						showCalendarError('Error de red al cargar citas');
						failureCallback && failureCallback(err);
					});
			}
		});

		// Helper banner for errors
		function ensureErrorBanner() {
			let b = document.getElementById('calendarErrorBanner');
			if (!b) {
				b = document.createElement('div');
				b.id = 'calendarErrorBanner';
				b.className = 'alert alert-danger py-2 px-3 mb-2';
				b.style.display = 'none';
				$calendarEl.before(b);
			}
			return b;
		}
		function showCalendarError(msg) {
			const b = ensureErrorBanner();
			b.textContent = msg;
			b.style.display = '';
		}
		function clearCalendarError() {
			const b = document.getElementById('calendarErrorBanner');
			if (b) b.style.display = 'none';
		}
		window.__showCalendarError = showCalendarError;
		window.__clearCalendarError = clearCalendarError;
		calendar.render();

		// Pending highlight CSS injection (idempotent)
		try {
			if (!document.getElementById('pgPendingHighlightStyles')) {
				const st = document.createElement('style');
				st.id = 'pgPendingHighlightStyles';
				st.textContent = '.pg-pending-highlight{outline:2px dashed #ffc107 !important; outline-offset:2px;}';
				document.head.appendChild(st);
			}
		} catch (_) { }

		// Hide cancelled CSS injection (idempotent)
		try {
			if (!document.getElementById('pgHideCancelledStyles')) {
				const st2 = document.createElement('style');
				st2.id = 'pgHideCancelledStyles';
				st2.textContent = '.pg-hide-cancelled [data-status="cancelled"]{display:none !important;}';
				document.head.appendChild(st2);
			}
		} catch (_) { }

		// Hide cancelled checkbox hook (CSS class + refetch)
		window.__hideCancelled = false;
		const hideChk = document.getElementById('hideCancelledChk');
		if (hideChk) {
			hideChk.addEventListener('change', function () {
				window.__hideCancelled = this.checked;
				if (this.checked) { document.body.classList.add('pg-hide-cancelled'); } else { document.body.classList.remove('pg-hide-cancelled'); }
				calendar.refetchEvents();
			});
		}

		// Fallback para cerrar todos los modals si no existe utilidad global
		function closeAllModals() {
			try { if (typeof window.closeAllModals === 'function') { window.closeAllModals(); return; } } catch (_) { }
			try {
				const open = document.querySelectorAll('.modal.show');
				open.forEach(m => {
					try {
						if (window.bootstrap) {
							const inst = window.bootstrap.Modal.getInstance(m) || new window.bootstrap.Modal(m);
							inst.hide();
						} else {
							m.classList.remove('show');
							m.style.display = 'none';
						}
					} catch (_) { }
				});
				document.querySelectorAll('.modal-backdrop').forEach(b => b.parentNode.removeChild(b));
				document.body.classList.remove('modal-open');
				document.body.style.paddingRight = '';
			} catch (_) { }
		}

	} catch (e) {
		console.error('professional.calendar init error', e);
	}
}
