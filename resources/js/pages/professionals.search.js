import axios from 'axios';

function renderCard(p) {
    const photo = p.photo || '/images/default-avatar.png';
    const specialty = p.specialty || 'General';
    const rating = (p.rating !== null && p.rating !== undefined) ? `<span class="badge bg-success">${p.rating.toFixed ? p.rating.toFixed(1) : p.rating}</span>` : '';
    const types = p.appointment_types ? (Array.isArray(p.appointment_types) ? p.appointment_types.join(', ') : p.appointment_types) : 'Presencial / Virtual';
    const location = p.location || 'No especificada';

    return `
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body d-flex">
                <div class="me-3" style="width:72px;flex:0 0 72px;">
                    <img src="${photo}" class="rounded" style="width:72px;height:72px;object-fit:cover;">
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-1 text-primary">${p.name}</h5>
                            <div class="text-muted small">${p.email || ''}</div>
                        </div>
                        <div>${rating}</div>
                    </div>
                    <div class="mt-2 small text-muted">Especialidad: <strong>${specialty}</strong></div>
                    <div class="mt-1 small">Tipo: <strong>${types}</strong></div>
                    <div class="mt-1 small">Ubicación: <strong>${location}</strong></div>
                    <div class="mt-3">
                        <a href="/professional/profile/${p.id}" class="btn btn-sm btn-outline-primary">Ver perfil</a>
                        <button data-id="${p.id}" class="btn btn-sm btn-primary ms-2 btn-request">Solicitar cita</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    `;
}

export default function init() {
    const $q = document.getElementById('pf_q');
    const $spec = document.getElementById('pf_specialty');
    const $type = document.getElementById('pf_type');
    const $btn = document.getElementById('pf_search');
    const $results = document.getElementById('pf_results');
    const $empty = document.getElementById('pf_empty');

    async function doSearch(){
        const params = {
            q: $q?.value || '',
            specialty: $spec?.value || '',
            type: $type?.value || ''
        };
        $results.innerHTML = '<div class="col-12 text-center py-5">Buscando...</div>';
        try {
            const url = document.querySelector('meta[name="professionals-search-url"]')?.getAttribute('content') || '/professionals/search';
            const res = await axios.get(url, { params });
            const data = res.data || [];
            if (!data || data.length === 0) {
                $results.innerHTML = '';
                $empty.classList.remove('d-none');
                return;
            }
            $empty.classList.add('d-none');
            $results.innerHTML = data.map(renderCard).join('');

            // wire request buttons to open shared appointment modal if available
            Array.from(document.querySelectorAll('.btn-request')).forEach(b=>{
                b.addEventListener('click', ()=>{
                    const id = b.getAttribute('data-id');
                    if (window.openAppointmentModal) {
                        // pass available appointment types from the professional object if present
                        const types = (p.appointment_types && Array.isArray(p.appointment_types)) ? p.appointment_types : (p.appointment_types ? String(p.appointment_types).split(',').map(s=>s.trim()) : null);
                        window.openAppointmentModal({ mode: 'patient', defaults: { professional_id: id }, types: types, urls: {}, calendar: null });
                    } else if (window.modalConfirm) {
                        // fallback simple prompt
                        window.modalNotification?.('Función no disponible','No se puede solicitar desde aquí',{template:'warning'});
                    }
                });
            });

        } catch (e) {
            $results.innerHTML = '<div class="col-12 text-danger">Error al buscar</div>';
            console.error(e);
        }
    }

    $btn && $btn.addEventListener('click', doSearch);
    // quick enter submit on inputs
    [$q, $spec].forEach(el => { if (!el) return; el.addEventListener('keydown', (ev)=>{ if (ev.key === 'Enter') { ev.preventDefault(); doSearch(); } }); });

    // initial load
    doSearch();
}
