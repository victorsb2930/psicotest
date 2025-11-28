import flatpickr from 'flatpickr';

// Round a date to the next 15-minute increment
function roundToNext15(d) {
	const dt = new Date(d || Date.now());
	const ms = dt.getTime();
	const mins = dt.getMinutes();
	const remainder = mins % 15;
	if (remainder === 0) {
		// If exactly on the multiple, advance to next slot
		dt.setMinutes(mins + 15);
	} else {
		dt.setMinutes(mins + (15 - remainder));
	}
	dt.setSeconds(0, 0);
	return dt;
}

function formatForInput(dt) {
	if (!dt) return '';
	const d = (dt instanceof Date) ? dt : new Date(dt);
	const pad = (n) => String(n).padStart(2, '0');
	return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/**
 * Open appointment modal used by both professional and patient UIs.
 * options: {
 *  mode: 'patient' | 'professional', // determines which fields are shown
 *  defaults: { start: Date, end: Date, allDay: bool },
 *  urls: { storeUrl, professionalCreateUrl, professionalPatientsUrl } // fallback to meta tags if missing
 *  calendar: FullCalendar instance (optional) - used to refetchEvents after success
 * }
	*/

	export async function openAppointmentModal(options = {}) {
	// Resolver modo priorizando SIEMPRE el rol real del usuario (meta/attr),
	// luego caer a options.mode y finalmente 'patient'.
	let mode = 'patient';
	try {
		const roleGuess = (document.documentElement.getAttribute('data-user-role') || document.querySelector('meta[name="current-user-role"]')?.getAttribute('content') || '').toLowerCase();
		if (roleGuess.includes('professional') || roleGuess.includes('profesional')) {
			mode = 'professional';
		} else if (roleGuess.includes('user') || roleGuess.includes('cliente') || roleGuess.includes('patient') || roleGuess.includes('paciente')) {
			mode = 'patient';
		} else if (options.mode === 'professional' || options.mode === 'patient') {
			mode = options.mode;
		}
	} catch(_) {
		if (options.mode === 'professional' || options.mode === 'patient') mode = options.mode;
	}
	try { console.debug('[openAppointmentModal] mode resolved =', mode, '(role/meta preferred), options.mode =', options.mode); } catch(_) {}
	const defaults = options.defaults || {};
	const calendar = options.calendar || null;

	// ID del usuario autenticado (para fijar professional_id en modo profesional)
	const currentUserId = (()=>{ try { return document.querySelector('meta[name="auth-user-id"]')?.getAttribute('content') || null; } catch(_) { return null; } })();

	const storeUrl = options.urls?.storeUrl || document.querySelector('meta[name="appointments-store-url"]')?.content;
	const profCreateUrl = options.urls?.professionalCreateUrl || document.querySelector('meta[name="professional-create-url"]')?.content;
	const profPatientsUrl = options.urls?.professionalPatientsUrl || document.querySelector('meta[name="professional-patients-url"]')?.content;

	// Decide which endpoint to use for submission
	const submitUrl = (mode === 'professional') ? (profCreateUrl || '/professional/calendar/events') : (storeUrl || '/appointments');

	// Build form HTML dynamically depending on mode. For patients we hide
	// fields that should not be visible (patient search, appointment type)
	// and instead show a readonly professional block with name/title if provided
	// Professional display (single declaration)
	const profDisplayHtml = `<div class="mb-2 professional-display"><div class="form-control-plaintext" id="am_professional_display"></div><input type="hidden" id="am_professional_id" name="professional_id"></div><div id="am_prof_avail_table_wrap" class="mb-3 small" style="display:none"></div>`;

	// Patient display (for professional mode): shows selected patient and allows clearing
	const patientDisplayHtml = `<div class="mb-2 patient-display"><div class="form-control-plaintext" id="am_patient_display"></div><input type="hidden" id="am_patient_id" name="patient_id"></div>`;

	// Fijamos modalidad en 'virtual' para ambos modos (se eliminó selección presencial/virtual)
	const fixedTypeValue = 'virtual';
	const formHtml = `
		<form id="sharedAppointmentForm">
			${mode === 'professional' ? `
			${patientDisplayHtml}
			<div class="mb-2 patient-field" id="am_patient_search_block">
				<label>Buscar paciente</label>
				<input type="text" id="am_patient_search" class="form-control" placeholder="Nombre o email del paciente">
				<div id="am_patient_results" class="list-group mt-2"></div>
			</div>
			` : ''}
			${mode === 'patient' ? `
			${profDisplayHtml}
			<div class="mb-2" id="am_prof_search_block">
				<label>Buscar profesional</label>
				<input type="text" id="am_prof_search" class="form-control" placeholder="Nombre o email del profesional">
				<div id="am_prof_results" class="list-group mt-2"></div>
			</div>
			` : `
			<input type="hidden" id="am_professional_id" name="professional_id" value="${currentUserId || ''}">
			`}

			<div class="mb-2">
				<label>Título</label>
				<input id="am_title" name="title" class="form-control">
			</div>

			<!-- Modalidad fija virtual -->
			<input type="hidden" id="am_appointment_type_hidden" name="appointment_type" value="${fixedTypeValue}">

			<div class="mb-2">
				<label>Inicio</label>
				<input id="am_start" name="start" type="datetime-local" class="form-control" required>
			</div>
			<div class="mb-2">
				<label>Fin (opcional)</label>
				<input id="am_end" name="end" type="datetime-local" class="form-control">
			</div>
			<div class="mb-2">
				<div id="am_availability_status" class="small"></div>
			</div>
			<div class="mb-2">
				<label>Notas</label>
				<textarea id="am_notes" name="notes" class="form-control" rows="3"></textarea>
			</div>
		</form>
	`;

	const modalId = `shared-appointment-${Date.now()}`;
	// Button label depends on mode
	const confirmLabel = mode === 'professional' ? 'Crear' : 'Solicitar';

	// Prevent opening multiple appointment modals at the same time
	try {
		if (window.__openAppointmentModalActive) {
			try { console.warn('[openAppointmentModal] modal already open, skipping'); } catch (_) {}
			return;
		}
	} catch (_) {}

	// Show modal
	if (typeof window.modalConfirm !== 'function') {
		alert('modalConfirm no disponible');
		return;
	}

	try {
		window.__openAppointmentModalActive = true;
		window.modalConfirm({
		modalId, title: (mode === 'professional' ? 'Crear cita' : 'Solicitar cita'), body: formHtml, closeClick: false, buttons: [
			{ text: 'Cancelar', className: 'btn-outline-secondary', onClick: ($modal) => { }, closeOnClick: true },
			{
				text: confirmLabel, className: 'btn-primary', onClick: async ($modal) => {
					// submit handler
					try {
						// gather values
						const patientId = $modal.find('#am_patient_id').val();
						const patientSearch = ($modal.find('#am_patient_search').val() || '').trim();
						const professionalId = $modal.find('#am_professional_id').val();
						const title = ($modal.find('#am_title').val() || '').trim();
						const startVal = $modal.find('#am_start').val();
						const endVal = $modal.find('#am_end').val();
						const notes = $modal.find('#am_notes').val();

						// strict validation: for patient mode require all fields
						if (mode === 'professional') {
							if (!patientId) { window.modalNotification?.('Paciente requerido', 'Selecciona un paciente de la lista', { template: 'warning' }); $modal.find('#am_patient_search').trigger('focus'); return; }
						} else {
							if (!professionalId) { window.modalNotification?.('Profesional requerido', 'Selecciona un profesional', { template: 'warning' }); $modal.find('#am_prof_search').trigger('focus'); return; }
							// require appointment type, title, start, end, notes for patient requests
							// Modalidad ahora fija en hidden input; no mostrar validación de selección.
							const atype = $modal.find('#am_appointment_type_hidden').val() || 'virtual';
							if (!title) { window.modalNotification?.('Título requerido', 'Indica un título para la cita', { template: 'warning' }); $modal.find('#am_title').trigger('focus'); return; }
							if (!startVal) { window.modalNotification?.('Inicio requerido', 'Indica inicio', { template: 'warning' }); $modal.find('#am_start').trigger('focus'); return; }
							if (!endVal) { window.modalNotification?.('Fin requerido', 'Indica fin de la cita', { template: 'warning' }); $modal.find('#am_end').trigger('focus'); return; }
							if (!notes) { window.modalNotification?.('Notas requeridas', 'Especifica notas/razón', { template: 'warning' }); $modal.find('#am_notes').trigger('focus'); return; }
						}

						// Validación de rango horario
						if (startVal) {
							try {
								const sDate = new Date(startVal);
								const nowCheck = getNow();
								// Prevent creating appointments in the past
								if (sDate.getTime() <= nowCheck.getTime()) {
									window.modalNotification?.('Inicio inválido', 'La fecha/hora de inicio no puede ser anterior o igual al momento actual', { template: 'warning' });
									$modal.find('#am_start').trigger('focus');
									return;
								}
								if (endVal) {
									const eDate = new Date(endVal);
									if (eDate <= sDate) {
										window.modalNotification?.('Rango inválido', 'La hora de fin debe ser posterior al inicio', { template: 'warning' });
										$modal.find('#am_end').trigger('focus');
										return;
									}
								}
							} catch(_){
								window.modalNotification?.('Fecha inválida', 'Comprueba la fecha y hora de inicio/fin', { template: 'warning' });
								return;
							}
						}
						// disponibilidad
						if (lastAvailability === false) { window.modalNotification?.('No disponible', 'El horario seleccionado no está disponible', { template: 'warning' }); return; }

						// prepare payload
						const payload = {};
						if (mode === 'professional') payload.patient_id = parseInt(patientId || 0, 10);
						if (mode === 'patient') payload.professional_id = parseInt(professionalId || 0, 10);
						if (title) payload.title = title;
						payload.start = new Date(startVal).toISOString();
						if (endVal) payload.end = new Date(endVal).toISOString();
						if (notes) payload.notes = notes;
						try {
							const apptype = $modal.find('#am_appointment_type').val() || $modal.find('#am_appointment_type_hidden').val();
							if (apptype) payload.appointment_type = apptype;
						} catch (_) { }

						// POST using axios
						await axios.post(submitUrl, payload);
						window.modalNotification?.('Hecho', mode === 'professional' ? 'Cita creada' : 'Solicitud enviada', { template: 'success' });
						// Close the modal reliably: try Bootstrap instance, then fallback to closeAllModals and DOM removal
						try {
							const inst = bootstrap.Modal.getInstance(document.getElementById(modalId));
							if (inst && typeof inst.hide === 'function') {
								inst.hide();
							} else {
								// fallback helper
								if (typeof closeAllModals === 'function') closeAllModals();
								try { $(`#${modalId}`).remove(); } catch (_) { }
							}
						} catch (_) {
							try { if (typeof closeAllModals === 'function') closeAllModals(); } catch (_) { }
							try { $(`#${modalId}`).remove(); } catch (_) { }
						}
						if (calendar && typeof calendar.refetchEvents === 'function') calendar.refetchEvents();
					} catch (err) {
						let msg = 'No se pudo procesar la solicitud';
						try {
							if (err && err.response) {
								const { status, data } = err.response;
								if (status === 422 && data) {
									const errs = [];
									if (data.errors) {
										for (const k in data.errors) {
											if (Array.isArray(data.errors[k]) && data.errors[k].length) {
												errs.push(data.errors[k][0]);
											}
										}
									}
									if (errs.length) {
										msg = errs.join('\n');
									} else if (data.message) {
										msg = data.message;
									}
								} else if (data && data.message) {
									msg = data.message;
								}
							}
						} catch(_){}
						window.modalNotification?.('Error', msg, { template: 'danger' });
					}
				}, closeOnClick: false
			}
		]
		});
	} catch (err) {
		// If showing the modal failed, clear the active flag so future attempts can proceed
		try { window.__openAppointmentModalActive = false; } catch (_) {}
		throw err;
	}

	const $m = $(`#${modalId}`);

	// Availability helpers (after $m exists)
	let lastAvailability = null; // null unknown, true available, false unavailable
	const setAvailabilityUI = (state) => {
		try {
			const $st = $m.find('#am_availability_status');
			lastAvailability = state;
			$st.removeClass('text-success text-danger');
			if (state === true) { $st.addClass('text-success').text('Disponible'); }
			else if (state === false) { $st.addClass('text-danger').text('No disponible'); }
			else { $st.text(''); }
		} catch (_) { }
	};
	const toggleAvailWrap = (show) => { try { $m.find('#am_prof_avail_table_wrap').css('display', show ? '' : 'none'); } catch(_){} };
	const renderWeeklyTable = (data) => {
		try {
			const wrap = $m.find('#am_prof_avail_table_wrap');
			if (!data || !Array.isArray(data.weekly)) { wrap.html(''); return; }
			const days = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
			const rows = [];
			for (let d=0; d<7; d++) {
				const slots = data.weekly.filter(w => w.day_of_week === d);
				if (slots.length === 0) continue;
				const cells = slots.map(s => `${s.start_time.slice(0,5)}–${s.end_time.slice(0,5)}`).join(', ');
				rows.push(`<tr><th class="text-nowrap">${days[d]}</th><td>${cells}</td></tr>`);
			}
			if (rows.length === 0) { wrap.html('<div class="text-muted">Sin disponibilidad semanal</div>'); toggleAvailWrap(true); return; }
			wrap.html(`<div class="fw-semibold mb-1">Disponibilidad semanal</div><table class="table table-sm table-borderless mb-0"><tbody>${rows.join('')}</tbody></table>`);
			toggleAvailWrap(true);
		} catch(_){}
	};
	const fetchWeeklyIfNeeded = async () => {
		const pid = ($m.find('#am_professional_id').val() || '').trim();
		const wrap = $m.find('#am_prof_avail_table_wrap');
		if (!pid) { wrap.html(''); toggleAvailWrap(false); return; }
		try { const res = await axios.get(`/professionals/${encodeURIComponent(pid)}/availability/weekly`); renderWeeklyTable(res.data); }
		catch(_) { wrap.html('<div class="text-muted">No se pudo cargar disponibilidad</div>'); toggleAvailWrap(true); }
	};
	const checkAvailability = async () => {
		try {
			const pid = ($m.find('#am_professional_id').val() || '').trim();
			const s = $m.find('#am_start').val();
			const e = $m.find('#am_end').val();
			if (!pid || !s || !e) { setAvailabilityUI(null); return; }
			// Asegurar que fin > inicio; si el usuario lo dejó menor, auto-ajustar +30min
			try {
				const sd = new Date(s);
				const ed = new Date(e);
				if (ed <= sd) {
					const fixed = new Date(sd.getTime() + 30*60000);
					$m.find('#am_end').val(formatForInput(fixed));
				}
			} catch(_){}
			const endVal = $m.find('#am_end').val();
			if (!endVal) { setAvailabilityUI(null); return; }
			const url = `/professionals/${encodeURIComponent(pid)}/availability/check`;
			const res = await axios.get(url, { params: { start: new Date(s).toISOString(), end: new Date(endVal).toISOString() } });
			const avail = !!(res && res.data && res.data.available);
			setAvailabilityUI(avail);
			// Tooltip sólo cuando existe razón de sobreposición y está No disponible
			const stEl = $m.find('#am_availability_status');
			try { stEl.removeAttr('title'); } catch(_){}
			if (!avail && res && res.data && res.data.reason && /sobrepone/i.test(res.data.reason)) {
				stEl.attr('title', res.data.reason);
			}
		} catch (_) { setAvailabilityUI(null); }
	};
	try { $m.find('#am_start, #am_end').on('change input', checkAvailability); } catch(_){}
	try { $m.find('#am_professional_id').on('change input', checkAvailability); } catch(_){}

	// If caller provided a professional id in defaults, prefill it
	try {
		if (defaults.professional_id) {
			$m.find('#am_professional_id').val(defaults.professional_id);
			// Hide search block if present (patient mode)
			try { $m.find('#am_prof_search_block').hide(); } catch(_){}
			try { fetchWeeklyIfNeeded(); } catch(_){}
		}
	} catch (_) { }

	// Populate professional display (name — title) from defaults if provided.
	try {
		const pName = defaults.professional_name || '';
		const pTitle = defaults.professional_title || '';
		const esc = (s) => {
			try { return window.escapeHtml ? window.escapeHtml(String(s)) : String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); } catch (_) { return String(s); }
		};
		if (pName || pTitle) {
			const escName = esc(pName);
			const escTitle = esc(pTitle);
			const html = escName ? `Profesional: <strong>${escName}</strong>${escTitle ? ' — ' + escTitle : ''}` : (escTitle ? `Profesional: ${escTitle}` : '');
			$m.find('#am_professional_display').html(html + ' <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="am_professional_clear">Quitar</button>');
			// Ensure hidden id is present when name/title provided together with id
			if (defaults.professional_id) {
				$m.find('#am_professional_id').val(defaults.professional_id);
				try { $m.find('#am_prof_search_block').hide(); } catch(_){}
				try { fetchWeeklyIfNeeded(); } catch(_){}
			}
		} else if (defaults.professional_id) {
			// DOM fallback: try to find an element on the page that contains the professional info
			try {
				const pid = String(defaults.professional_id);
				const selector = `[data-id="${pid}"], [data-professional-id="${pid}"], [data-professionalid="${pid}"]`;
				const el = document.querySelector(selector);
				if (el) {
					const dname = el.getAttribute('data-name') || el.getAttribute('data-fullname') || (el.querySelector && el.querySelector('h5') ? el.querySelector('h5').textContent.trim() : '');
					const dtitle = el.getAttribute('data-title') || (el.querySelector && el.querySelector('.mt-2.small strong') ? el.querySelector('.mt-2.small strong').textContent.trim() : '');
					if (dname || dtitle) {
						const html = esc(dname) ? `Profesional: <strong>${esc(dname)}</strong>${dtitle ? ' — ' + esc(dtitle) : ''}` : (dtitle ? `Profesional: ${esc(dtitle)}` : '');
						$m.find('#am_professional_display').html(html + ' <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="am_professional_clear">Quitar</button>');
						try { $m.find('#am_prof_search_block').hide(); } catch(_){}
						try { fetchWeeklyIfNeeded(); } catch(_){}
					}
				}
			} catch (_) { }
		}
	} catch (_) { }

	// Populate appointment type selector from options.types or defaults.types
	// Se elimina lógica de selector de modalidad; aseguramos hidden tenga el valor fijo
	try { const $typeHidden = $m.find('#am_appointment_type_hidden'); if ($typeHidden && !$typeHidden.val()) $typeHidden.val(fixedTypeValue); } catch(_){}

	// Use the global helper set in `resources/js/bootstrap.js`.
	// We prefer a single, explicit guard rather than many nested fallbacks.
	let getNow;
	if (typeof window !== 'undefined' && typeof window.getServerNow === 'function') {
		getNow = window.getServerNow;
	} else {
		// If the helper isn't present something is wrong with app bootstrap.
		// Warn once and fall back to local clock to avoid breaking the UI.
		console.warn('[appointmentModal] window.getServerNow() not found — falling back to local clock. Ensure `resources/js/bootstrap.js` runs before this module.');
		getNow = () => new Date();
	}
	// Prefill start/end: use defaults.start or round to next 15
	const now = getNow();
	let _sd = defaults.start ? new Date(defaults.start) : roundToNext15(now);
	if (isNaN(_sd.getTime())) _sd = roundToNext15(now);
	// Prevent defaults in the past: clamp to next available slot
	if (_sd.getTime() < now.getTime()) { _sd = roundToNext15(now); }
	const startDefault = _sd;
	let _ed = defaults.end ? new Date(defaults.end) : new Date(startDefault.getTime() + 30 * 60000);
	if (isNaN(_ed.getTime()) || _ed.getTime() <= startDefault.getTime()) { _ed = new Date(startDefault.getTime() + 30 * 60000); }
	const endDefault = _ed;

	// set values BEFORE initializing flatpickr to avoid empty-first-open issues
	const sEl = $m.find('#am_start')[0];
	const eEl = $m.find('#am_end')[0];
	if (sEl) sEl.value = formatForInput(startDefault);
	if (eEl) eEl.value = formatForInput(endDefault);

	// initialize flatpickr with altInput showing DD-MM-YYYY HH:MM
	let sFp = null;
	let eFp = null;
	try {
		if (sEl && typeof flatpickr === 'function') {
				const appendTarget = document.getElementById(modalId) || document.body;
				sFp = flatpickr(sEl, {
				enableTime: true,
				dateFormat: 'Y-m-d\\TH:i',
				altInput: true,
				altFormat: 'd-m-Y H:i',
				time_24hr: true,
				defaultDate: sEl.value || undefined,
				allowInput: true,
				clickOpens: true,
					appendTo: appendTarget,
					// Prevent selecting a start time in the past (use server time if available)
					minDate: getNow(),
					onChange: function(selectedDates) {
						try {
							// ensure end picker minDate follows start
							if (selectedDates && selectedDates.length && eFp && typeof eFp.set === 'function') {
								const sd = selectedDates[0];
								const minEnd = new Date(Math.max(sd.getTime() + 15*60000, sd.getTime()));
								try { eFp.set('minDate', minEnd); } catch (_) {}
								// if current end is before min, bump it
								try {
									const cur = eFp.selectedDates && eFp.selectedDates[0];
									if (!cur || cur.getTime() <= sd.getTime()) {
										eFp.setDate(new Date(sd.getTime() + 30*60000), true);
									}
								} catch (_) {}
							}
						} catch (_) {}
					},
				onReady: function (selectedDates, dateStr, instance) {
					// attempt to remove readonly attribute from altInput reliably
					try {
						if (instance && instance.altInput) {
							instance.altInput.removeAttribute('readonly');
							// ensure it's focusable/typable
							instance.altInput.readOnly = false;
							// some browsers may immediately reapply, so double-check on next tick
							setTimeout(() => { try { instance.altInput.removeAttribute('readonly'); instance.altInput.readOnly = false; } catch (_) { } }, 0);
						}
					} catch (_) { }
				},
				onOpen: function (selectedDates, dateStr, instance) {
					// ensure time inputs inside the popup are editable
					try {
						const container = instance && instance.calendarContainer;
						if (container) {
							const timeInputs = container.querySelectorAll('.flatpickr-time input, input.flatpickr-hour, input.flatpickr-minute, input.flatpickr-second, input.numInput');
							timeInputs.forEach(i => { try { i.removeAttribute('readonly'); i.readOnly = false; i.tabIndex = 0; i.inputMode = 'numeric'; } catch (_) { } });
						}
					} catch (_) { }
				}
			});
		}
		if (eEl && typeof flatpickr === 'function') {
			const appendTargetE = document.getElementById(modalId) || document.body;
			eFp = flatpickr(eEl, {
				enableTime: true,
				dateFormat: 'Y-m-d\\TH:i',
				altInput: true,
				altFormat: 'd-m-Y H:i',
				time_24hr: true,
				defaultDate: eEl.value || undefined,
				allowInput: true,
				clickOpens: true,
				appendTo: appendTargetE,
				// End must be at or after startDefault (and not in the past)
				minDate: startDefault,
				onReady: function (selectedDates, dateStr, instance) {
					try {
						if (instance && instance.altInput) {
							instance.altInput.removeAttribute('readonly');
							instance.altInput.readOnly = false;
							setTimeout(() => { try { instance.altInput.removeAttribute('readonly'); instance.altInput.readOnly = false; } catch (_) { } }, 0);
						}
					} catch (_) { }
				},
				onOpen: function (selectedDates, dateStr, instance) {
					try {
						const container = instance && instance.calendarContainer;
						if (container) {
							const timeInputs = container.querySelectorAll('.flatpickr-time input, input.flatpickr-hour, input.flatpickr-minute, input.flatpickr-second, input.numInput');
							timeInputs.forEach(i => { try { i.removeAttribute('readonly'); i.readOnly = false; i.tabIndex = 0; i.inputMode = 'numeric'; } catch (_) { } });
						}
					} catch (_) { }
				}
			});
		}
	} catch (_) { }

	// If patient mode without preselected professional, wire professional search
	if (mode === 'patient') {
		try {
			const hasDefaultProf = !!defaults.professional_id;
			if (hasDefaultProf) {
				$m.find('#am_prof_search_block').hide();
				try { fetchWeeklyIfNeeded(); } catch(_){}
				try { checkAvailability(); } catch(_){}
			} else {
				const $searchProf = $m.find('#am_prof_search');
				const $resultsProf = $m.find('#am_prof_results');
				let timerProf = null;
				$searchProf.off('input').on('input', function(){
					clearTimeout(timerProf);
					const q = ($searchProf.val() || '').trim();
					$resultsProf.empty();
					$m.find('#am_professional_id').val('');
					if (q.length < 2) return;
					timerProf = setTimeout(()=>{
						fetch('/professionals/search?q=' + encodeURIComponent(q))
							.then(r=>r.json())
							.then(list => {
								$resultsProf.empty();
								if (!Array.isArray(list) || list.length === 0) { $resultsProf.append('<div class="list-group-item small text-muted">Sin resultados</div>'); return; }
								const safe = s => { try { return window.escapeHtml ? window.escapeHtml(String(s)) : String(s); } catch(_) { return String(s); } };
								list.slice(0,10).forEach(p => {
									const btn = document.createElement('button');
									btn.type = 'button';
									btn.className = 'list-group-item list-group-item-action';
									btn.innerHTML = `<strong>${safe(p.name || 'Profesional')}</strong><br><small class="text-muted">${safe(p.email || '')}</small>`;
									btn.addEventListener('click', ()=> {
										$m.find('#am_professional_id').val(p.id);
										const displayHtml = `Profesional: <strong>${safe(p.name || '')}</strong>${p.speciality ? ' — ' + safe(p.speciality) : ''}`;
										$m.find('#am_professional_display').html(displayHtml + ' <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="am_professional_clear">Quitar</button>');
										// Hide search block after selection
										try { $m.find('#am_prof_search_block').hide(); } catch(_){}
										$resultsProf.empty();
										try { fetchWeeklyIfNeeded(); } catch(_){}
										try { checkAvailability(); } catch(_){}
									});
									$resultsProf.append(btn);
								});
							})
							.catch(()=>{ $resultsProf.empty(); });
					}, 350);
				});
				$m.one('hidden.bs.modal', () => { clearTimeout(timerProf); });
			}
		} catch(_){}
	}

	// If professional mode, wire patient search
	if (mode === 'professional') {
		const $search = $m.find('#am_patient_search');
		const $results = $m.find('#am_patient_results');
		const $searchBlock = $m.find('#am_patient_search_block');
		let timer = null;
		$search.off('input').on('input', function () {
			clearTimeout(timer);
			const q = $(this).val().trim();
			$results.empty();
			$m.find('#am_patient_id').val('');
			if (q.length < 2) return;
			timer = setTimeout(() => {
				fetch((profPatientsUrl || '/professional/calendar/patients') + '?q=' + encodeURIComponent(q))
					.then(r => r.json()).then(data => {
						const html = data.map(u => `<button type="button" class="list-group-item list-group-item-action" data-id="${u.id}" data-name="${u.name}" data-email="${u.email}"><strong>${u.name}</strong><br><small class="text-muted">${u.email}</small></button>`).join('');
						$results.html(html);
						$results.find('button[data-id]').on('click', function () {
							const selId = $(this).attr('data-id');
							const selName = $(this).attr('data-name') || '';
							const selEmail = $(this).attr('data-email') || '';
							$m.find('#am_patient_id').val(selId);
							const safe = s => { try { return window.escapeHtml ? window.escapeHtml(String(s)) : String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); } catch(_) { return String(s); } };
							$m.find('#am_patient_display').html(`Paciente: <strong>${safe(selName)}</strong> <small class="text-muted">${safe(selEmail)}</small> <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="am_patient_clear">Quitar</button>`);
							if ($searchBlock && $searchBlock.length) $searchBlock.hide();
							$results.empty();
						});
					}).catch(() => { });
			}, 300);
		});
		$m.one('hidden.bs.modal', () => { clearTimeout(timer); });
	}

	// Handler to clear selected patient (professional mode)
	try {
		$m.off('click', '#am_patient_clear').on('click', '#am_patient_clear', function(){
			try {
				$m.find('#am_patient_id').val('');
				$m.find('#am_patient_display').html('');
				$m.find('#am_patient_search').val('');
				$m.find('#am_patient_results').empty();
				$m.find('#am_patient_search_block').show();
			} catch(_){ }
		});
	} catch(_) { }

	// Handler to clear selected professional (patient mode) using delegated events
	try {
		$m.off('click', '#am_professional_clear').on('click', '#am_professional_clear', function(){
			try {
				$m.find('#am_professional_id').val('');
				$m.find('#am_professional_display').html('');
				$m.find('#am_prof_search_block').show();
				try { toggleAvailWrap(false); } catch(_){}
				// reset availability UI
				try { setAvailabilityUI(null); } catch(_){}
				// (Re)bind search input if it was skipped due to preselected professional
				const $searchProf = $m.find('#am_prof_search');
				if ($searchProf.length && !$searchProf.data('pg-bound')) {
					let timerProf = null;
					$searchProf.on('input', function(){
						clearTimeout(timerProf);
						const q = ($searchProf.val() || '').trim();
						$m.find('#am_prof_results').empty();
						if (q.length < 2) return;
						timerProf = setTimeout(()=>{
							fetch('/professionals/search?q=' + encodeURIComponent(q))
								.then(r=>r.json())
								.then(list => {
									const $resultsProf = $m.find('#am_prof_results');
									$resultsProf.empty();
									if (!Array.isArray(list) || list.length === 0) { $resultsProf.append('<div class="list-group-item small text-muted">Sin resultados</div>'); return; }
									const safe = s => { try { return window.escapeHtml ? window.escapeHtml(String(s)) : String(s); } catch(_) { return String(s); } };
									list.slice(0,10).forEach(p => {
										const btn = document.createElement('button');
										btn.type = 'button';
										btn.className = 'list-group-item list-group-item-action';
										btn.innerHTML = `<strong>${safe(p.name || 'Profesional')}</strong><br><small class="text-muted">${safe(p.email || '')}</small>`;
										btn.addEventListener('click', ()=> {
											$m.find('#am_professional_id').val(p.id);
											const displayHtml = `Profesional: <strong>${safe(p.name || '')}</strong>${p.speciality ? ' — ' + safe(p.speciality) : ''}`;
											$m.find('#am_professional_display').html(displayHtml + ' <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="am_professional_clear">Quitar</button>');
											try { $m.find('#am_prof_search_block').hide(); } catch(_){}
											$resultsProf.empty();
											try { fetchWeeklyIfNeeded(); } catch(_){}
											try { checkAvailability(); } catch(_){}
										});
										$resultsProf.append(btn);
									});
								}).catch(()=>{ $m.find('#am_prof_results').empty(); });
						}, 350);
					});
					$searchProf.data('pg-bound', true);
				}
			} catch(_){}
		});
	} catch(_){ }

	// Clean up modal DOM when hidden to avoid stale states and ensure flatpickr popups close
	$m.one('hidden.bs.modal', function () {
		try {
			try { window.__openAppointmentModalActive = false; } catch (_) {}
			// close and destroy flatpickr instances if present so their calendars don't remain visible after modal closes (Esc key)
			try { if (sFp && typeof sFp.close === 'function') sFp.close(); } catch (_) { }
			try { if (eFp && typeof eFp.close === 'function') eFp.close(); } catch (_) { }
			try { if (sFp && typeof sFp.destroy === 'function') sFp.destroy(); } catch (_) { }
			try { if (eFp && typeof eFp.destroy === 'function') eFp.destroy(); } catch (_) { }
			$(this).remove();
		} catch (_) { }
	});
}

export default openAppointmentModal;

// Expose globally for legacy callers
try { if (typeof window !== 'undefined') window.openAppointmentModal = openAppointmentModal; } catch (_) { }
