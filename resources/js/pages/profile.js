// Profile page module: upload photos, list gallery, set profile photo and presence
import axios from 'axios';

const api = {
    list: '/profile/photos',
    upload: '/profile/photos',
    set: (id) => `/profile/photos/${id}/set-profile`,
    delete: (id) => `/profile/photos/${id}`,
    presence: '/profile/presence'
};

// Heartbeat endpoint
api.heartbeat = '/profile/heartbeat';

async function refreshGallery(){
    try{
        const res = await axios.get(api.list);
        if(res.data && res.data.photos){
            const cont = document.getElementById('photo-gallery');
            cont.innerHTML = '';
            res.data.photos.forEach(p => {
                const img = document.createElement('img');
                img.src = '/storage/' + p.path;
                img.width = 80; img.height = 80; img.className = 'rounded'; img.style.objectFit = 'cover'; img.style.cursor = 'pointer';
                if(p.is_profile) img.style.outline = '3px solid #0d6efd';
                img.addEventListener('click', async ()=>{
                    if(!confirm('Establecer esta foto como perfil?')) return;
                    await axios.post(api.set(p.id));
                    await refreshGallery();
                    document.getElementById('profile-avatar-img').src = '/storage/' + p.path;
                });
                cont.appendChild(img);
            });
        }
    }catch(e){ console.error(e); }
}

export function init(){
    document.getElementById('btn-change-photo')?.addEventListener('click', ()=>document.getElementById('input-photo').click());
    document.getElementById('input-photo')?.addEventListener('change', async function(){
        const f = this.files[0];
        if(!f) return;
        const fd = new FormData(); fd.append('photo', f);
        try{
            const res = await axios.post(api.upload, fd, { headers: {'Content-Type':'multipart/form-data'}});
            if(res.data && res.data.ok){
                await refreshGallery();
                alert('Foto subida. Establece como perfil desde la galería.');
            }
        }catch(e){ console.error(e); alert('Error subiendo foto'); }
    });

    // presence polling: keep profile view in sync with server and other tabs
    // module-scoped poll handle so destroy() can clear it
    let pollInterval = null;
    const pollPeriod = 5000;
    async function fetchStatus(){
        try{
            const res = await axios.get('/profile/status');
            if(res && res.data && res.data.ok){
                const status = res.data.status || 'offline';
                const labels = { online:'Online', busy:'Ocupado', dnd:'No molestar', away:'Ausente', offline:'No disponible' };
                const label = labels[status] || status;
                const desc = document.getElementById('profile-presence-desc');
                if(desc) desc.textContent = label;
                if (typeof window.applyPresenceToUI === 'function') window.applyPresenceToUI(status);
                else {
                    const colors = { online:'#28a745', busy:'#fd7e14', dnd:'#dc3545', away:'#ffc107', offline:'#6c757d' };
                    const el = document.getElementById('profile-presence'); if (el) el.style.background = colors[status] || colors['offline'];
                }
            }
        }catch(e){ /* ignore */ }
    }
    function startPolling(){ if (pollInterval) return; fetchStatus(); pollInterval = setInterval(fetchStatus, pollPeriod); }
    function stopPolling(){ if (pollInterval){ clearInterval(pollInterval); pollInterval = null; } }
    // start polling when page init
    startPolling();

    refreshGallery();

    // Heartbeat: keep presence alive while the page is visible
    let hbInterval = null;
    const sendHeartbeat = async () => {
        try { await axios.post(api.heartbeat); } catch(e){ /* ignore */ }
    };
    const startHeartbeat = () => {
        if (hbInterval) return;
        sendHeartbeat();
        hbInterval = setInterval(sendHeartbeat, 30_000); // 30s
    };
    const stopHeartbeat = () => { if (hbInterval) { clearInterval(hbInterval); hbInterval = null; } };

    // start when visible
    if (document.visibilityState === 'visible') startHeartbeat();
    document.addEventListener('visibilitychange', ()=>{
        if (document.visibilityState === 'visible') startHeartbeat(); else stopHeartbeat();
    });

    // send one last heartbeat on unload (best-effort)
    window.addEventListener('beforeunload', ()=>{ navigator.sendBeacon && navigator.sendBeacon(api.heartbeat); });
}

export function destroy(){
    // cleanup: stop polling and any other long-lived resources
    try { stopPolling(); } catch(e){}
}
