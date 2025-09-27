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

// Module-scoped handles so init()/destroy() can manage them
let _pollInterval = null;
const POLL_PERIOD = 5000;
let _hbInterval = null;
let _visibilityHandler = null;
let _beforeUnloadHandler = null;
let _btnChangePhotoHandler = null;
let _inputPhotoHandler = null;

async function refreshGallery(){
	try{
		const res = await axios.get(api.list);
		if(res.data && res.data.photos){
			const cont = document.getElementById('photo-gallery');
			if(!cont) return;
			cont.innerHTML = '';
			res.data.photos.forEach(p => {
				const img = document.createElement('img');
				// prefer data_url (returned by server) to avoid extra filesystem mapping
				img.src = p.data_url ? p.data_url : ('/storage/' + (p.path || ''));
				img.width = 80; img.height = 80; img.className = 'rounded'; img.style.objectFit = 'cover'; img.style.cursor = 'pointer';
				if(p.is_profile) img.style.outline = '3px solid #0d6efd';
				img.addEventListener('click', async ()=>{
					// confirm via modalConfirm when available
					const doSet = () => axios.post(api.set(p.id)).then(async ()=>{
						await refreshGallery();
						const avatar = document.getElementById('profile-avatar-img'); if (avatar) avatar.src = p.data_url ? p.data_url : ('/storage/' + (p.path || ''));
						// Also update navbar avatar if present
						const navAvatar = document.getElementById('nav-avatar-img'); if (navAvatar) navAvatar.src = p.data_url ? p.data_url : ('/storage/' + (p.path || ''));
						modalNotification?.('Hecho','Foto establecida como perfil',{template:'success'});
					}).catch((err)=>{
						console.error(err);
						modalNotification?.('Error','No se pudo establecer la foto como perfil',{template:'danger'}, true, { xhr: err?.response, body: 'set-profile' });
					});

					// Use modalConfirm exclusively (no native confirm fallback)
					modalConfirm({ title: 'Confirmar', body: '¿Establecer esta foto como perfil?', btnsType: 'ny', onClickYes: doSet });
				});
				cont.appendChild(img);
			});
		}
	}catch(e){ console.error(e);
		modalNotification('Error','No se pudo cargar la galería',{template:'danger'}, true, { xhr: e?.response, body: 'list' });
	}
}

// Presence polling
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

function startPolling(){ if (_pollInterval) return; fetchStatus(); _pollInterval = setInterval(fetchStatus, POLL_PERIOD); }
function stopPolling(){ if (_pollInterval){ clearInterval(_pollInterval); _pollInterval = null; } }

// Heartbeat
const sendHeartbeat = async () => { try { await axios.post(api.heartbeat); } catch(e){ /* ignore */ } };
function startHeartbeat(){ if (_hbInterval) return; sendHeartbeat(); _hbInterval = setInterval(sendHeartbeat, 30_000); }
function stopHeartbeat(){ if (_hbInterval){ clearInterval(_hbInterval); _hbInterval = null; } }

export function init(){
	const btn = document.getElementById('btn-change-photo');
	const input = document.getElementById('input-photo');
	if (btn && input) {
		_btnChangePhotoHandler = ()=> input.click();
		btn.addEventListener('click', _btnChangePhotoHandler);

		_inputPhotoHandler = async function(){
			const f = this.files[0];
			if(!f) return;
			const fd = new FormData(); fd.append('photo', f);
			try{
				// Let axios set Content-Type with boundary
				const res = await axios.post(api.upload, fd);
				if(res.data && res.data.ok){
					await refreshGallery();
					modalNotification?.('Foto subida','Foto subida correctamente. Puedes establecerla como perfil desde la galería.', {template:'success'});
				} else {
					modalNotification?.('Error','No se pudo subir la foto',{template:'danger'});
				}
			}catch(e){ console.error(e);
				modalNotification?.('Error','No se pudo subir la foto',{template:'danger'}, true, { xhr: e?.response, body: 'upload' });
			} finally {
				// reset input so same file can be selected again if needed
				try{ this.value = ''; }catch(_){}
			}
		};
		input.addEventListener('change', _inputPhotoHandler);
	}

	// start polling and heartbeat
	startPolling();
	if (document.visibilityState === 'visible') startHeartbeat();
	_visibilityHandler = ()=>{ if (document.visibilityState === 'visible') startHeartbeat(); else stopHeartbeat(); };
	document.addEventListener('visibilitychange', _visibilityHandler);

	// send one last heartbeat on unload (best-effort)
	_beforeUnloadHandler = ()=>{ navigator.sendBeacon && navigator.sendBeacon(api.heartbeat); };
	window.addEventListener('beforeunload', _beforeUnloadHandler);

	// initial gallery load
	refreshGallery();
}

export function destroy(){
	// cleanup: stop polling/heartbeat and remove handlers
	try { stopPolling(); } catch(e){}
	try { stopHeartbeat(); } catch(e){}
	try { if (_btnChangePhotoHandler) document.getElementById('btn-change-photo')?.removeEventListener('click', _btnChangePhotoHandler); } catch(_){}
	try { if (_inputPhotoHandler) document.getElementById('input-photo')?.removeEventListener('change', _inputPhotoHandler); } catch(_){}
	try { if (_visibilityHandler) document.removeEventListener('visibilitychange', _visibilityHandler); } catch(_){}
	try { if (_beforeUnloadHandler) window.removeEventListener('beforeunload', _beforeUnloadHandler); } catch(_){}
	_btnChangePhotoHandler = null; _inputPhotoHandler = null; _visibilityHandler = null; _beforeUnloadHandler = null;
}