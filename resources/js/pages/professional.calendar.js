import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';

export default function init() {
	// This module mirrors the previous inline script in the Blade view but lives as a module
	try {
		const $calendarEl = $('#calendar');
		if ($calendarEl.length === 0) return;
		const todayISO = new Date().toISOString().substring(0, 10);
		const userTz = window.__currentUserTz || null; // fallback for PJAX cases

		const calendar = new Calendar($calendarEl[0], {
			initialView: 'dayGridMonth',
			selectable: true,
			validRange: { start: todayISO },
			timeZone: userTz || 'local',
			plugins: [dayGridPlugin, interactionPlugin],
			select: function (info) {
				// unify behavior: delegate to shared open modal logic
				// previously called openNewAppointmentFromSelection (undefined) —
				// call the existing openNewAppointment function instead
				openNewAppointment(info.start, info.end);
			},
			events: {
				url: document.querySelector('meta[name="professional-events-url"]')?.getAttribute('content') || '/professional/calendar/events',
				method: 'GET'
			},
		});
		calendar.render();

		const toLocalInput = (d) => {
			if (!d) return '';
			const pad = (n) => String(n).padStart(2, '0');
			const year = d.getFullYear();
			const month = pad(d.getMonth() + 1);
			const day = pad(d.getDate());
			const hours = pad(d.getHours());
			const minutes = pad(d.getMinutes());
			return `${year}-${month}-${day}T${hours}:${minutes}`;
		};

		// timer used by patient search (declare before usage in dynamic modal handlers)
		let patientTimer = null;

		// Shared function used by both button and calendar selection
		function openNewAppointment(startDate, endDate) {
			// If no dates provided, use now rounded
			const now = new Date();
			const roundUpToNext30 = (d) => {
				const dd = new Date(d);
				const m = dd.getMinutes();
				if (m === 0) { dd.setSeconds(0); dd.setMilliseconds(0); return dd; }
				if (m > 0 && m <= 30) { dd.setMinutes(30, 0, 0); } else { dd.setHours(dd.getHours() + 1, 0, 0, 0); }
				return dd;
			};

			// Build the form HTML to be injected in the dynamic modal
			const formHtml = `
				<form id="newAppointmentForm">
					<div class="mb-3">
						<label class="form-label">Paciente</label>
						<input type="text" id="patientSearch" class="form-control" placeholder="Buscar por nombre o email">
						<input type="hidden" id="patientId" name="patient_id">
						<div id="patientResults" class="list-group mt-2"></div>
					</div>
					<div class="mb-3">
						<label class="form-label">Título</label>
						<input type="text" name="title" id="apptTitle" class="form-control">
					</div>
					<div class="mb-3">
						<label class="form-label">Inicio</label>
						<input type="datetime-local" name="start" id="apptStart" class="form-control">
					</div>
					<div class="mb-3">
						<label class="form-label">Fin (opcional)</label>
						<input type="datetime-local" name="end" id="apptEnd" class="form-control">
					</div>
					<div class="mb-3">
						<label class="form-label">Notas</label>
						<textarea name="notes" id="apptNotes" class="form-control" rows="3"></textarea>
					</div>
				</form>
			`;

			const modalId = 'newAppointmentModal';
			// Use modalConfirm to create/show modal and wire confirm button to submit the form
			modalConfirm({ modalId, title: 'Crear cita', body: formHtml, btnsType: 'ac', modalType: 'normal', closeClick: false, onClickYes: () => {
				const $m = $(`#${modalId}`);
				const $f = $m.find('#newAppointmentForm');
				if ($f.length) {
					$f.trigger('submit');
				}
			} });

			const $m = $(`#${modalId}`);

			// Set initial start/end values inside modal inputs
			if (!startDate) {
				const nowRounded = roundUpToNext30(now);
				const end = new Date(nowRounded.getTime() + 30 * 60000);
				$m.find('#apptStart').val(toLocalInput(nowRounded));
				$m.find('#apptEnd').val(toLocalInput(end));
			} else {
				const isAllDay = (s, e) => {
					try { return s && s.getHours() === 0 && s.getMinutes() === 0 && e && e.getHours() === 0 && e.getMinutes() === 0; } catch (_) { return false; }
				};
				if (isAllDay(startDate, endDate)) {
					const chosen = new Date(startDate);
					const rounded = roundUpToNext30(now);
					chosen.setHours(rounded.getHours(), rounded.getMinutes(), 0, 0);
					const chosenEnd = new Date(chosen.getTime() + 30 * 60000);
					$m.find('#apptStart').val(toLocalInput(chosen));
					$m.find('#apptEnd').val(toLocalInput(chosenEnd));
				} else {
					$m.find('#apptStart').val(toLocalInput(startDate));
					$m.find('#apptEnd').val(toLocalInput(endDate));
				}
			}

			// Attach patient search handler and form submit handler to dynamic modal
			const $patientSearchInModal = $m.find('#patientSearch');
			const $patientResults = $m.find('#patientResults');
			const $formInModal = $m.find('#newAppointmentForm');

			// prevent doubling handlers when modal is reused
			$patientSearchInModal.off('input');
			$formInModal.off('submit');

			if ($patientSearchInModal.length) {
				$patientSearchInModal.on('input', function () {
					clearTimeout(patientTimer);
					const q = $(this).val().trim();
					$patientResults.empty();
					$m.find('#patientId').val('');
					if (q.length < 2) return;
					patientTimer = setTimeout(() => {
						fetch((document.querySelector('meta[name="professional-patients-url"]')?.getAttribute('content') || '/professional/calendar/patients') + '?q=' + encodeURIComponent(q))
							.then(r => r.json())
							.then(data => {
								const html = data.map(u => `<button type="button" class="list-group-item list-group-item-action" data-id="${u.id}" data-name="${u.name}">${u.name} <small class="text-muted">${u.email}</small></button>`).join('');
								$patientResults.html(html);
								$patientResults.find('button[data-id]').on('click', function () {
									$patientSearchInModal.val($(this).attr('data-name'));
									$m.find('#patientId').val($(this).attr('data-id'));
									$patientResults.empty();
								});
							});
					}, 300);
				});
			}

			if ($formInModal.length) {
				$formInModal.on('submit', function (e) {
					e.preventDefault();

					// REQUIRED FIELD VALIDATION: none of these should be empty
					const patientIdVal = $m.find('#patientId').val();
					const patientSearchVal = ($m.find('#patientSearch').val() || '').trim();
					const titleVal = ($m.find('#apptTitle').val() || '').trim();
					const notesVal = ($m.find('#apptNotes').val() || '').trim();
					if (!patientIdVal || patientSearchVal.length === 0) {
						window.modalNotification?.('Paciente requerido', 'Debes seleccionar un paciente de la lista.', { template: 'warning' });
						$m.find('#patientSearch').trigger('focus');
						return;
					}
					if (!titleVal) {
						window.modalNotification?.('Título requerido', 'Introduce un título para la cita.', { template: 'warning' });
						$m.find('#apptTitle').trigger('focus');
						return;
					}
					if (!notesVal) {
						window.modalNotification?.('Notas requeridas', 'Introduce alguna nota para la cita.', { template: 'warning' });
						$m.find('#apptNotes').trigger('focus');
						return;
					}
					const startVal = $m.find('#apptStart').val();
					if (!startVal) { window.modalNotification?.('Selecciona la fecha de inicio', 'Debes indicar la fecha y hora de inicio.', { template: 'warning' }); $m.find('#apptStart').trigger('focus'); return; }
					const startDate = new Date(startVal);
					const now = new Date();
					if (startDate < now) { window.modalNotification?.('Fecha/hora de inicio inválida', 'La fecha/hora de inicio que ingresaste es anterior al momento actual. Revisa el campo de inicio.', { template: 'warning' }); $m.find('#apptStart').trigger('focus'); return; }
					const endValLocal = $m.find('#apptEnd').val();
					if (endValLocal) {
						const endDate = new Date(endValLocal);
						if (!(endDate > startDate)) { window.modalNotification?.('Fecha/hora de fin inválida', 'La fecha/hora de fin debe ser posterior a la de inicio. Revisa el campo de fin.', { template: 'warning' }); $m.find('#apptEnd').trigger('focus'); return; }
					}

					const events = calendar.getEvents();
					const newStart = startDate;
					const newEnd = endValLocal ? new Date(endValLocal) : new Date(newStart.getTime() + 30 * 60000);
					const overlap = events.some(ev => {
						const evStart = new Date(ev.start);
						const evEnd = ev.end ? new Date(ev.end) : new Date(evStart.getTime() + 30 * 60000);
						return (evStart < newEnd) && (evEnd > newStart);
					});
					if (overlap) { window.modalNotification?.('Conflicto de horario', 'La cita que intentas crear solapa con otra cita existente. Revisa inicio/fin y el listado de citas en el calendario.', { template: 'warning', delayAutoClose: 8000 }); return; }

					const data = new FormData($formInModal[0]);
					const toUtcIso = (localInput) => { if (!localInput) return null; return new Date(localInput).toISOString(); };
					data.set('start', toUtcIso($m.find('#apptStart').val()));
					const endVal = $m.find('#apptEnd').val();
					if (endVal) data.set('end', toUtcIso(endVal));

					fetch(document.querySelector('meta[name="professional-create-url"]')?.getAttribute('content') || '/professional/calendar/events', {
						method: 'POST',
						headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') },
						body: data
					}).then(async r => {
						const json = await r.json().catch(() => ({}));
						if (r.ok && json.ok) {
							// hide modal
							try { const bs = bootstrap.Modal.getInstance($m[0]); bs?.hide(); } catch (_) {}
							calendar.refetchEvents();
							window.modalNotification?.('Cita creada', 'La cita fue creada correctamente.', { template: 'success' });
						} else {
							if (json && json.error === 'validation' && json.field) {
								window.modalNotification?.('Error de validación', json.message || 'Verifica los campos.', { template: 'warning' });
								if (json.field === 'start') $m.find('#apptStart').trigger('focus');
								if (json.field === 'end') $m.find('#apptEnd').trigger('focus');
							} else if (json && json.error === 'conflict' && Array.isArray(json.conflicts)) {
								const ids = json.conflicts.map(c => `#${c.id}`).join(', ');
								window.modalNotification?.('Conflicto de horario', `La nueva cita solapa con citas existentes: ${ids}. Revisa y ajusta los horarios.`, { template: 'warning', delayAutoClose: 8000 });
							} else {
								const msg = json.error || json.message || 'Error al crear la cita';
								window.modalNotification?.('Error', msg, { template: 'danger' }, true, { xhr: json });
							}
						}
					}).catch(err => { console.error(err); window.modalNotification?.('Error de red', 'No se pudo conectar con el servidor.', { template: 'danger' }); });
				});
			}

			// Ensure timers are cleared when modal closes
			$m.one('hidden.bs.modal', function () { clearTimeout(patientTimer); $m.remove(); });
		}

		const $newBtn = $('#newAppointmentBtn');
		$newBtn.on('click', function () { openNewAppointment(); });


	} catch (e) { console.error('professional.calendar init error', e); }
}
