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
let _avatarClickHandler = null;

async function refreshGallery(){
	try{
		const res = await window.axios.get(api.list);
		const data = res.data || {};
		const photos = data.photos || [];
		const container = $('#photo-gallery');
		if(!container) return;
		container.empty();
		console.log('Gallery photos:', photos);
		if(photos.length === 0){
			container.append('<p class="text-muted">No hay fotos en la galería.</p>');
			return;
		}
		photos.forEach(p => {
			const origin = window.location?.origin || (window.location.protocol + '//' + window.location.hostname + (window.location.port ? ':'+window.location.port : ''));
			const src = p.url || p.secure_url || (p.path ? origin + '/storage/' + p.path.replace(/^\/+/, '') : origin + '/images/default-avatar.png');
			const img = document.createElement('img');
			img.src = src;
			img.alt = p.caption || '';
			img.width = 120;
			img.height = 120;
			img.className = 'rounded m-1 object-fit-cover';
			// opcional: data-attrs para acciones (set profile, delete)
			img.dataset.photoId = p.id;
			img.dataset.ownerId = p.owner_id;
			// use jQuery append on the jQuery container
			container.append(img);
        });
    }catch(e){
		modalNotification?.('Error','No se pudo cargar la galería',{template:'danger'}, true, { xhr: e?.response, body: 'list' });
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
			}catch(e){
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

	// Ensure profile image preview modal exists (the blade includes a fallback script
	// that runs only on full-page loads; when navigating via PJAX we must ensure
	// the modal and click handler are attached from this module).
	if (!document.getElementById('profileImagePreviewModal')) {
		const modalHtml = `
		<div class="modal fade" id="profileImagePreviewModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-lg">
				<div class="modal-content">
					<div class="modal-body text-center p-0">
						<img id="profileImagePreviewModalImg" src="" style="width:100%; height:auto;" alt="preview">
					</div>
				</div>
			</div>
		</div>`;
		document.body.insertAdjacentHTML('beforeend', modalHtml);
	}

	// Attach click handler to avatar to open preview modal (store handler to remove on destroy)
	const avatarWrap = document.getElementById('profile-avatar');
	const modalImg = document.getElementById('profileImagePreviewModalImg');
	_avatarClickHandler = function () {
		const src = document.getElementById('profile-avatar-img')?.src || '';
		if(!src) return;
		if (modalImg) modalImg.src = src;
		const modalEl = document.getElementById('profileImagePreviewModal');
		if (modalEl) new bootstrap.Modal(modalEl).show();
	};
	if (avatarWrap) avatarWrap.addEventListener('click', _avatarClickHandler);

	// then load gallery
	refreshGallery();
}

export function destroy(){
	// cleanup: stop polling/heartbeat and remove handlers
	try { stopPolling(); } catch(e){}
	try { stopHeartbeat(); } catch(e){}
	try { if (_btnChangePhotoHandler) document.getElementById('btn-change-photo')?.removeEventListener('click', _btnChangePhotoHandler); } catch(_){ }
	try { if (_inputPhotoHandler) document.getElementById('input-photo')?.removeEventListener('change', _inputPhotoHandler); } catch(_){ }
	try { if (_avatarClickHandler) document.getElementById('profile-avatar')?.removeEventListener('click', _avatarClickHandler); } catch(_){ }
	try { if (_visibilityHandler) document.removeEventListener('visibilitychange', _visibilityHandler); } catch(_){ }
	try { if (_beforeUnloadHandler) window.removeEventListener('beforeunload', _beforeUnloadHandler); } catch(_){ }

	_btnChangePhotoHandler = null;
	_inputPhotoHandler = null;
	_avatarClickHandler = null;
	_visibilityHandler = null;
	_beforeUnloadHandler = null;
}