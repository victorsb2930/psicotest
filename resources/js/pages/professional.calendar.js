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
			customButtons: {
				monthJump: {
					text: 'Mes/Año',
					click: function() {
						const body = `<div class="mb-2"><label class="form-label">Seleccione mes y año</label><input id="jumpMonthPickerProf" class="form-control"></div>`;
						if (typeof window.modalConfirm === 'function') {
							window.modalConfirm({ modalId: 'modal-jump-month-prof', title: 'Ir a mes', body, closeClick: true, onClickYes: function(){
								const v = document.getElementById('jumpMonthPickerProf')?.value;
								if (!v) { window.modalNotification?.('Fecha requerida','Selecciona un mes y año',{template:'warning'}); return; }
								try { calendar.gotoDate(v); } catch(e){ window.modalNotification?.('Fecha inválida','Selecciona un mes válido',{template:'warning'}); }
							}});
							setTimeout(()=>{
								try {
									const el = document.getElementById('jumpMonthPickerProf');
									if (el) {
										flatpickr(el, {
											plugins: [new monthSelectPlugin({ shorthand: false, dateFormat: 'Y-m', altFormat: 'F Y' })],
											dateFormat: 'Y-m-01',
											defaultDate: new Date()
										});
									}
								} catch(_){ }
							},120);
						} else {
							const d = prompt('Introduce fecha YYYY-MM (ej: 2025-09)'); if (!d) return; try { calendar.gotoDate(d + '-01'); } catch(e){ window.modalNotification?.('Fecha inválida','Formato esperado YYYY-MM',{template:'warning'}); }
						}
					}
				}
			},
			validRange: { start: todayISO },
			plugins: [dayGridPlugin, interactionPlugin],
			// selection handler - delegate to shared modal helper
			select: function(info) {
				openAppointmentModal({ mode: 'professional', defaults: { start: info.start, end: info.end }, urls: { professionalCreateUrl: document.querySelector('meta[name="professional-create-url"]')?.getAttribute('content'), professionalPatientsUrl: document.querySelector('meta[name="professional-patients-url"]')?.getAttribute('content') }, calendar });
			},
			// dateClick: clicking a day should open modal with sensible defaults
			dateClick: function(info) {
				try {
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
			},
			events: {
				url: document.querySelector('meta[name="professional-events-url"]')?.getAttribute('content') || '/professional/calendar/events',
				method: 'GET'
			}
		});

		calendar.render();

		// wire the top "Nueva cita" button
		const $newBtn = $('#newAppointmentBtn');
		$newBtn.on('click', function() {
			openAppointmentModal({ mode: 'professional', defaults: {}, urls: { professionalCreateUrl: document.querySelector('meta[name="professional-create-url"]')?.getAttribute('content'), professionalPatientsUrl: document.querySelector('meta[name="professional-patients-url"]')?.getAttribute('content') }, calendar });
		});

	} catch (e) {
		console.error('professional.calendar init error', e);
	}
}
