import flatpickr from 'flatpickr';
import axios from 'axios';

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
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
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
    const mode = options.mode === 'professional' ? 'professional' : 'patient';
    const defaults = options.defaults || {};
    const calendar = options.calendar || null;

    const storeUrl = options.urls?.storeUrl || document.querySelector('meta[name="appointments-store-url"]')?.content;
    const profCreateUrl = options.urls?.professionalCreateUrl || document.querySelector('meta[name="professional-create-url"]')?.content;
    const profPatientsUrl = options.urls?.professionalPatientsUrl || document.querySelector('meta[name="professional-patients-url"]')?.content;

    // Decide which endpoint to use for submission
    const submitUrl = (mode === 'professional') ? (profCreateUrl || '/professional/calendar/events') : (storeUrl || '/appointments');

    const formHtml = `
        <form id="sharedAppointmentForm">
            <div class="mb-2 patient-field" style="display:${mode==='professional' ? 'block' : 'none'}">
                <label>Paciente</label>
                <input type="text" id="am_patient_search" class="form-control" placeholder="Buscar por nombre o email">
                <input type="hidden" id="am_patient_id" name="patient_id">
                <div id="am_patient_results" class="list-group mt-2"></div>
            </div>
            <div class="mb-2 professional-field" style="display:${mode==='patient' ? 'block' : 'none'}">
                <label>Profesional (ID)</label>
                <input type="number" id="am_professional_id" name="professional_id" class="form-control">
            </div>
                <div class="mb-2">
                    <label>Título</label>
                    <input id="am_title" name="title" class="form-control">
                </div>
                <div class="mb-2">
                    <label>Modalidad</label>
                    <select id="am_appointment_type" name="appointment_type" class="form-select">
                        <option value="">Seleccione modalidad</option>
                    </select>
                    <input type="hidden" id="am_appointment_type_hidden" name="appointment_type">
                </div>
            <div class="mb-2">
                <label>Inicio</label>
                <input id="am_start" name="start" type="datetime-local" class="form-control" required>
            </div>
            <div class="mb-2">
                <label>Fin (opcional)</label>
                <input id="am_end" name="end" type="datetime-local" class="form-control">
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

    // Show modal
    if (typeof window.modalConfirm !== 'function') {
        alert('modalConfirm no disponible');
        return;
    }

    window.modalConfirm({ modalId, title: (mode==='professional' ? 'Crear cita' : 'Solicitar cita'), body: formHtml, closeClick: false, buttons: [
        { text: 'Cancelar', className: 'btn-outline-secondary', onClick: ($modal)=>{}, closeOnClick: true },
        { text: confirmLabel, className: 'btn-primary', onClick: async ($modal)=>{
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

                // basic validation
                if (mode === 'professional') {
                    if (!patientId && patientSearch.length === 0) { window.modalNotification?.('Paciente requerido','Selecciona un paciente',{template:'warning'}); $modal.find('#am_patient_search').trigger('focus'); return; }
                } else {
                    if (!professionalId) { window.modalNotification?.('Profesional requerido','Indica el profesional (ID)',{template:'warning'}); $modal.find('#am_professional_id').trigger('focus'); return; }
                }
                if (!startVal) { window.modalNotification?.('Inicio requerido','Indica inicio',{template:'warning'}); $modal.find('#am_start').trigger('focus'); return; }

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
                } catch(_){}

                // POST using axios
                await axios.post(submitUrl, payload);
                window.modalNotification?.('Hecho', mode==='professional' ? 'Cita creada' : 'Solicitud enviada', { template: 'success' });
                try { $modal.modal && $modal.modal('hide'); } catch(_){}
                if (calendar && typeof calendar.refetchEvents === 'function') calendar.refetchEvents();
            } catch (err) {
                console.error(err);
                window.modalNotification?.('Error','No se pudo procesar la solicitud',{template:'danger'});
            }
        }, closeOnClick: false }
    ] });

    const $m = $(`#${modalId}`);

    // If caller provided a professional id in defaults, prefill it
    try {
        if (defaults.professional_id) {
            $m.find('#am_professional_id').val(defaults.professional_id);
        }
    } catch(_){}

    // Populate appointment type selector from options.types or defaults.types
    const types = options.types || defaults.types || null; // e.g. ['presencial','virtual'] or [{value,label},...]
    const $typeSelect = $m.find('#am_appointment_type');
    const $typeHidden = $m.find('#am_appointment_type_hidden');
    if ($typeSelect && types) {
        try {
            // clear existing non-placeholder options
            $typeSelect.find('option:not([value=""])').remove();
            const normalized = Array.isArray(types) ? types.map(t => (typeof t === 'string' ? { value: t, label: (t.charAt(0).toUpperCase() + t.slice(1)) } : t)) : [];
            normalized.forEach(t => { $typeSelect.append(`<option value="${t.value}">${t.label}</option>`); });
            // If only one option available, select it by default
            if (normalized.length === 1) {
                $typeSelect.val(normalized[0].value);
                $typeHidden.val(normalized[0].value);
            }
            $typeSelect.off('change').on('change', function(){ $typeHidden.val(this.value || ''); });
        } catch(_){}
    }

    // Prefill start/end: use defaults.start or round to next 15
    const now = new Date();
    const startDefault = defaults.start ? new Date(defaults.start) : roundToNext15(now);
    const endDefault = defaults.end ? new Date(defaults.end) : new Date(startDefault.getTime() + 30*60000);

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
            sFp = flatpickr(sEl, {
                enableTime: true,
                dateFormat: 'Y-m-d\\TH:i',
                altInput: true,
                altFormat: 'd-m-Y H:i',
                time_24hr: true,
                defaultDate: sEl.value || undefined,
                allowInput: true,
                clickOpens: true,
                onReady: function(selectedDates, dateStr, instance) {
                    // attempt to remove readonly attribute from altInput reliably
                    try {
                        if (instance && instance.altInput) {
                            instance.altInput.removeAttribute('readonly');
                            // ensure it's focusable/typable
                            instance.altInput.readOnly = false;
                            // some browsers may immediately reapply, so double-check on next tick
                            setTimeout(()=>{ try{ instance.altInput.removeAttribute('readonly'); instance.altInput.readOnly = false; }catch(_){} }, 0);
                        }
                    } catch(_){}
                },
                onOpen: function(selectedDates, dateStr, instance) {
                    // ensure time inputs inside the popup are editable
                    try {
                        const container = instance && instance.calendarContainer;
                        if (container) {
                            const timeInputs = container.querySelectorAll('.flatpickr-time input, input.flatpickr-hour, input.flatpickr-minute, input.flatpickr-second, input.numInput');
                            timeInputs.forEach(i => { try { i.removeAttribute('readonly'); i.readOnly = false; i.tabIndex = 0; i.inputMode = 'numeric'; } catch(_){} });
                        }
                    } catch(_){}
                }
            });
        }
        if (eEl && typeof flatpickr === 'function') {
            eFp = flatpickr(eEl, {
                enableTime: true,
                dateFormat: 'Y-m-d\\TH:i',
                altInput: true,
                altFormat: 'd-m-Y H:i',
                time_24hr: true,
                defaultDate: eEl.value || undefined,
                allowInput: true,
                clickOpens: true,
                onReady: function(selectedDates, dateStr, instance) {
                    try {
                        if (instance && instance.altInput) {
                            instance.altInput.removeAttribute('readonly');
                            instance.altInput.readOnly = false;
                            setTimeout(()=>{ try{ instance.altInput.removeAttribute('readonly'); instance.altInput.readOnly = false; }catch(_){} }, 0);
                        }
                    } catch(_){}
                },
                onOpen: function(selectedDates, dateStr, instance) {
                    try {
                        const container = instance && instance.calendarContainer;
                        if (container) {
                            const timeInputs = container.querySelectorAll('.flatpickr-time input, input.flatpickr-hour, input.flatpickr-minute, input.flatpickr-second, input.numInput');
                            timeInputs.forEach(i => { try { i.removeAttribute('readonly'); i.readOnly = false; i.tabIndex = 0; i.inputMode = 'numeric'; } catch(_){} });
                        }
                    } catch(_){}
                }
            });
        }
    } catch (_) {}

    // If professional mode, wire patient search
    if (mode === 'professional') {
        const $search = $m.find('#am_patient_search');
        const $results = $m.find('#am_patient_results');
        let timer = null;
        $search.off('input').on('input', function(){
            clearTimeout(timer);
            const q = $(this).val().trim();
            $results.empty();
            $m.find('#am_patient_id').val('');
            if (q.length < 2) return;
            timer = setTimeout(()=>{
                fetch((profPatientsUrl || '/professional/calendar/patients') + '?q=' + encodeURIComponent(q))
                    .then(r=>r.json()).then(data=>{
                        const html = data.map(u => `<button type="button" class="list-group-item list-group-item-action" data-id="${u.id}" data-name="${u.name}">${u.name} <small class="text-muted">${u.email}</small></button>`).join('');
                        $results.html(html);
                        $results.find('button[data-id]').on('click', function(){
                            $search.val($(this).attr('data-name'));
                            $m.find('#am_patient_id').val($(this).attr('data-id'));
                            $results.empty();
                        });
                    }).catch(()=>{});
            }, 300);
        });
        $m.one('hidden.bs.modal', ()=>{ clearTimeout(timer); });
    }

    // Clean up modal DOM when hidden to avoid stale states and ensure flatpickr popups close
    $m.one('hidden.bs.modal', function(){
        try{
            // close and destroy flatpickr instances if present so their calendars don't remain visible after modal closes (Esc key)
            try { if (sFp && typeof sFp.close === 'function') sFp.close(); } catch(_){}
            try { if (eFp && typeof eFp.close === 'function') eFp.close(); } catch(_){}
            try { if (sFp && typeof sFp.destroy === 'function') sFp.destroy(); } catch(_){}
            try { if (eFp && typeof eFp.destroy === 'function') eFp.destroy(); } catch(_){}
            $(this).remove();
        } catch(_){}
    });
}

export default openAppointmentModal;

// Expose globally for legacy callers
try { if (typeof window !== 'undefined') window.openAppointmentModal = openAppointmentModal; } catch(_){}
