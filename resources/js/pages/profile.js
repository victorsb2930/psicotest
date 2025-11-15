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
let _galleryClickHandler = null;
let _reopen2faSubmitHandler = null;
let _reopen2faCancelHandler = null;
let _resetPasswordConfirmHandler = null;
let _resetPasswordBtnHandler = null;

async function refreshGallery() {
	try {
		const res = await window.axios.get(api.list);
		const data = res.data || {};
		const photos = data.photos || [];
		const container = $('#photo-gallery');
		if (!container) return;
		container.empty();
		// console.log('Gallery photos:', photos);
		if (photos.length === 0) {
			container.append('<p class="text-muted">No hay fotos en la galería.</p>');
			return;
		}
		photos.forEach(p => {
			const origin = window.location?.origin || (window.location.protocol + '//' + window.location.hostname + (window.location.port ? ':' + window.location.port : ''));
			const metaDefault = (document.querySelector('meta[name="default-avatar"]') || {}).content;
			const defaultAvatar = window.__defaultAvatar || metaDefault || (origin + '/images/default-avatar.png');
			const src = p.url || p.secure_url || (p.path ? origin + '/storage/' + p.path.replace(/^\/+/, '') : defaultAvatar);
			// build a small tile with overlay buttons: preview, set as profile, delete
			const tpl = `
				<div class="position-relative m-1" style="width:120px;">
					<img src="${src}" alt="${(p.caption || '')}" width="120" height="120" class="rounded object-fit-cover gallery-photo" data-photo-id="${p.id}" />
					<div class="position-absolute top-0 end-0 m-1 d-flex flex-column gap-1">
						<button type="button" class="btn btn-sm btn-light gallery-preview-btn" title="Ver" data-photo-id="${p.id}"><i class="bi bi-arrows-fullscreen"></i></button>
						<button type="button" class="btn btn-sm btn-success gallery-set-btn" title="Usar como perfil" data-photo-id="${p.id}"><i class="bi bi-person-circle"></i></button>
						<button type="button" class="btn btn-sm btn-danger gallery-delete-btn" title="Eliminar" data-photo-id="${p.id}"><i class="bi bi-trash"></i></button>
					</div>
				</div>`;
			container.append(tpl);
		});

		// After rendering gallery, ensure profile avatar(s) across the page are updated
		try {
			// Find a photo marked as profile or the most recent one
			let profilePhoto = photos.find(pp => pp.is_profile || pp.is_profile === 1);
			if (!profilePhoto && photos.length) profilePhoto = photos[0];
			if (profilePhoto) {
				const origin = window.location?.origin || (window.location.protocol + '//' + window.location.hostname + (window.location.port ? ':' + window.location.port : ''));
				const profSrc = profilePhoto.url || profilePhoto.secure_url || (profilePhoto.path ? origin + '/storage/' + profilePhoto.path.replace(/^\/+/, '') : null);
				if (profSrc) {
					const busted = profSrc + '?_=' + Date.now();
					// Update common selectors used for avatar images
					document.querySelectorAll('#profile-avatar-img, #nav-avatar-img, .profile-avatar-img, img[data-profile-photo]').forEach(img => {
						try { img.src = busted; } catch (_) { }
					});
					// Emit event so other modules can respond
					try { window.dispatchEvent(new CustomEvent('profile-photo-changed', { detail: { src: busted } })); } catch (_) { }
				}
			}
		} catch (e) { /* ignore */ }
	} catch (e) {
		modalNotification?.('Error', 'No se pudo cargar la galería', { template: 'danger' }, true, { xhr: e?.response, body: 'list' });
	}
}

// Presence polling
async function fetchStatus() {
	try {
		const res = await axios.get('/profile/status');
		if (res && res.data && res.data.ok) {
			const status = res.data.status || 'offline';
			const labels = { online: 'Online', busy: 'Ocupado', dnd: 'No molestar', away: 'Ausente', offline: 'No disponible' };
			const label = labels[status] || status;
			const desc = document.getElementById('profile-presence-desc');
			if (desc) desc.textContent = label;
			if (typeof window.applyPresenceToUI === 'function') window.applyPresenceToUI(status);
			else {
				const colors = { online: '#28a745', busy: '#fd7e14', dnd: '#dc3545', away: '#ffc107', offline: '#6c757d' };
				const el = document.getElementById('profile-presence'); if (el) el.style.background = colors[status] || colors['offline'];
			}
		}
	} catch (e) { /* ignore */ }
}

function startPolling() { if (_pollInterval) return; fetchStatus(); _pollInterval = setInterval(fetchStatus, POLL_PERIOD); }
function stopPolling() { if (_pollInterval) { clearInterval(_pollInterval); _pollInterval = null; } }

// Heartbeat
const sendHeartbeat = async () => { try { await axios.post(api.heartbeat); } catch (e) { /* ignore */ } };
function startHeartbeat() { if (_hbInterval) return; sendHeartbeat(); _hbInterval = setInterval(sendHeartbeat, 30_000); }
function stopHeartbeat() { if (_hbInterval) { clearInterval(_hbInterval); _hbInterval = null; } }

export function init() {
	const btn = document.getElementById('btn-change-photo');
	const input = document.getElementById('input-photo');
	if (btn && input) {
		_btnChangePhotoHandler = () => input.click();
		btn.addEventListener('click', _btnChangePhotoHandler);

		_inputPhotoHandler = async function () {
			const f = this.files[0];
			if (!f) return;
			const fd = new FormData(); fd.append('photo', f);
			try {
				// Let axios set Content-Type with boundary
				const res = await axios.post(api.upload, fd);
				if (res.data && res.data.ok) {
					await refreshGallery();
					modalNotification?.('Foto subida', 'Foto subida correctamente. Puedes establecerla como perfil desde la galería.', { template: 'success' });
				} else {
					modalNotification?.('Error', 'No se pudo subir la foto', { template: 'danger' });
				}
			} catch (e) {
				modalNotification?.('Error', 'No se pudo subir la foto', { template: 'danger' }, true, { xhr: e?.response, body: 'upload' });
			} finally {
				// reset input so same file can be selected again if needed
				try { this.value = ''; } catch (_) { }
			}
		};
		input.addEventListener('change', _inputPhotoHandler);
	}

	// start polling and heartbeat
	startPolling();
	if (document.visibilityState === 'visible') startHeartbeat();
	_visibilityHandler = () => { if (document.visibilityState === 'visible') startHeartbeat(); else stopHeartbeat(); };
	document.addEventListener('visibilitychange', _visibilityHandler);

	// send one last heartbeat on unload (best-effort)
	_beforeUnloadHandler = () => { navigator.sendBeacon && navigator.sendBeacon(api.heartbeat); };
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

	// 2FA Reopen modal: ensure a single modal exists and attach handlers once
	(function setupReopen2fa(){
		let modalEl = document.getElementById('reopen2faModal');
		if (!modalEl) {
			const modal2 = `
			<div class="modal fade" id="reopen2faModal" tabindex="-1" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title">Confirmar reapertura de sesión</h5>
						</div>
						<div class="modal-body">
							<p>Hemos enviado un código a tu correo. Introduce el código de 6 dígitos para confirmar que eres tú.</p>
							<div class="mb-2"><input id="reopen2faCode" class="form-control" placeholder="Código 6 dígitos" maxlength="6" inputmode="numeric"></div>
							<div id="reopen2faError" class="text-danger small" style="display:none"></div>
						</div>
						<div class="modal-footer">
							<button id="reopen2faCancel" type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
							<button id="reopen2faSubmit" type="button" class="btn btn-primary">Confirmar</button>
						</div>
					</div>
				</div>
			</div>`;
			document.body.insertAdjacentHTML('beforeend', modal2);
			modalEl = document.getElementById('reopen2faModal');
		}

		const input = modalEl.querySelector('#reopen2faCode');
		const err = modalEl.querySelector('#reopen2faError');
		const submit = modalEl.querySelector('#reopen2faSubmit');
		const cancel = modalEl.querySelector('#reopen2faCancel');

		// attach submit handler once
		_reopen2faSubmitHandler = async function() {
			err.style.display = 'none';
			const code = input.value.trim();
			if (!/^[0-9]{6}$/.test(code)) { err.textContent = 'Introduce un código válido de 6 dígitos.'; err.style.display = 'block'; return; }
			submit.disabled = true;
			try {
				const res = await fetch('/profile/heartbeat/confirm', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }, body: JSON.stringify({ code }) });
				const j = await res.json();
				if (j.ok) {
					const bs = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
					bs.hide();
					location.reload();
				} else {
					err.textContent = j.message || 'Código incorrecto';
					err.style.display = 'block';
				}
			} catch (errNet) {
				err.textContent = 'Error de red. Intenta de nuevo.'; err.style.display = 'block';
			} finally { submit.disabled = false; }
		};
		submit.addEventListener('click', _reopen2faSubmitHandler);

		// clear UI on cancel
		_reopen2faCancelHandler = function(){ try { err.style.display = 'none'; input.value = ''; } catch(_){} };
		cancel.addEventListener('click', _reopen2faCancelHandler);

		// expose show helper that only resets and shows (no handler re-attach)
		window.showReopen2faModal = function(){ try { err.style.display = 'none'; input.value = ''; const bs = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl); bs.show(); setTimeout(()=> input.focus(),250); } catch(_) {} };
	})();

	// Reset password: use the global modalConfirm helper for focus-safe confirm flow
	const btnReset = document.getElementById('btn-reset-password');
	if (btnReset) {
		_resetPasswordBtnHandler = function() {
			modalConfirm({
				title: 'Cambiar contraseña',
				body: '<p>Se enviará un enlace para restablecer la contraseña a tu correo registrado. ¿Deseas continuar?</p>',
				confirmLabel: 'Enviar enlace',
				cancelLabel: 'Cancelar',
				// onClickYes may be async; modalConfirm will call it and then close the confirm modal
				onClickYes: async function() {
					try {
						const res = await fetch('/profile/password/reset-email', {
							method: 'POST',
							headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
							body: JSON.stringify({})
						});
						const j = await res.json();
						if (res.ok && j.ok) {
							modalConfirm({ title: 'Enviado', body: '<p>Enlace enviado correctamente. Revisa tu correo.</p>', btnsType: 'ac' }, 'normal');
						} else {
							modalConfirm({ title: 'Error', body: `<p>${(j && j.message) ? j.message : 'No se pudo enviar el enlace.'}</p>`, btnsType: 'ac' }, 'normal');
						}
					} catch (err) {
						modalConfirm({ title: 'Error de red', body: '<p>Error de red. Intenta de nuevo.</p>', btnsType: 'ac' }, 'normal');
					}
				}
			}, 'normal');
		};
		btnReset.addEventListener('click', _resetPasswordBtnHandler);
	}

	// Attach click handler to avatar to open preview modal (store handler to remove on destroy)
	const avatarWrap = document.getElementById('profile-avatar');
	const modalImg = document.getElementById('profileImagePreviewModalImg');
	_avatarClickHandler = function () {
		const src = document.getElementById('profile-avatar-img')?.src || '';
		if (!src) return;
		if (modalImg) modalImg.src = src;
		const modalEl = document.getElementById('profileImagePreviewModal');
		if (modalEl) new bootstrap.Modal(modalEl).show();
	};
	if (avatarWrap) avatarWrap.addEventListener('click', _avatarClickHandler);

	// Gallery actions via delegation: preview, set as profile, delete
	_galleryClickHandler = function (ev) {
		try {
			const target = ev.target;
			const btn = target.closest && target.closest('.gallery-preview-btn, .gallery-set-btn, .gallery-delete-btn');
			if (!btn) {
				// If clicked directly on image, open preview
				const img = target.closest && target.closest('.gallery-photo');
				if (img) {
					const src = img.src;
					if (modalImg) modalImg.src = src;
					const modalEl = document.getElementById('profileImagePreviewModal');
					if (modalEl) new bootstrap.Modal(modalEl).show();
				}
				return;
			}
			const photoId = btn.dataset.photoId;
			if (!photoId) return;
			if (btn.classList.contains('gallery-preview-btn')) {
				// preview
				const imgEl = document.querySelector(`[data-photo-id="${photoId}"]`);
				const src = imgEl?.src;
				if (src && modalImg) modalImg.src = src;
				const modalEl = document.getElementById('profileImagePreviewModal');
				if (modalEl) new bootstrap.Modal(modalEl).show();
				return;
			}
			if (btn.classList.contains('gallery-set-btn')) {
				// set as profile
				(async () => {
					try {
						const res = await axios.post(api.set(photoId));
						if (res.data && res.data.ok) {
							modalNotification?.('Foto perfil', 'Foto establecida como perfil', { template: 'success' });
							// If server returned the new profile URL, normalize and use it (cache-busted)
							if (res.data.profile_photo_url) {
								let finalUrl = res.data.profile_photo_url || window.__defaultAvatar || (window.location.origin + '/images/default-avatar.png');
								// prefer the Vite-served default if server returned a relative /images/default-avatar.png
								if (window.__defaultAvatar && finalUrl.includes('/images/default-avatar.png')) finalUrl = window.__defaultAvatar;
								const busted = finalUrl + '?_=' + Date.now();
								document.querySelectorAll('#profile-avatar-img, #nav-avatar-img, .profile-avatar-img, img[data-profile-photo]').forEach(img => { try { img.src = busted; } catch (_) { } });
								try { window.dispatchEvent(new CustomEvent('profile-photo-changed', { detail: { src: busted } })); } catch (_) { }
							}
							// refresh gallery
							await refreshGallery();
						} else {
							modalNotification?.('Error', 'No se pudo establecer como perfil', { template: 'danger' });
						}
					} catch (e) {
						modalNotification?.('Error', 'No se pudo establecer como perfil', { template: 'danger' }, true, { xhr: e?.response });
					}
				})();
				return;
			}
			if (btn.classList.contains('gallery-delete-btn')) {
				// confirm delete
				const doDelete = async () => {
					try {
						const res = await axios.delete(api.delete(photoId));
						if (res.data && res.data.ok) {
							modalNotification?.('Foto eliminada', 'La foto ha sido eliminada', { template: 'success' });
							// Normalize server profile URL and prefer Vite default when necessary
							let finalUrl = res.data.profile_photo_url || window.__defaultAvatar || (window.location.origin + '/images/default-avatar.png');
							if (window.__defaultAvatar && finalUrl.includes('/images/default-avatar.png')) finalUrl = window.__defaultAvatar;
							const busted = finalUrl + '?_=' + Date.now();
							document.querySelectorAll('#profile-avatar-img, #nav-avatar-img, .profile-avatar-img, img[data-profile-photo]').forEach(img => { try { img.src = busted; } catch (_) { } });
							try { window.dispatchEvent(new CustomEvent('profile-photo-changed', { detail: { src: busted } })); } catch (_) { }
							await refreshGallery();
						} else {
							modalNotification?.('Error', 'No se pudo eliminar la foto', { template: 'danger' });
						}
					} catch (e) {
						modalNotification?.('Error', 'No se pudo eliminar la foto', { template: 'danger' }, true, { xhr: e?.response });
					}
				};
				modalConfirm({ title: 'Eliminar foto', body: '<p>¿Deseas eliminar esta foto de tu galería?</p>', buttons: [{ text: 'Cancelar', className: 'btn-outline-secondary', dismiss: true }, { text: 'Eliminar', className: 'btn-danger', onClick: ($m) => { try { const inst = bootstrap.Modal.getInstance($m[0]); inst?.hide(); } catch (e) { }; doDelete(); } }] }, 'normal');
				return;
			}
		} catch (e) { }
	};
	const galleryEl = document.getElementById('photo-gallery');
	if (galleryEl) galleryEl.addEventListener('click', _galleryClickHandler);

	// then load gallery
	refreshGallery();
}

export function destroy() {
	// cleanup: stop polling/heartbeat and remove handlers
	try { stopPolling(); } catch (e) { }
	try { stopHeartbeat(); } catch (e) { }
	try { if (_btnChangePhotoHandler) document.getElementById('btn-change-photo')?.removeEventListener('click', _btnChangePhotoHandler); } catch (_) { }
	try { if (_inputPhotoHandler) document.getElementById('input-photo')?.removeEventListener('change', _inputPhotoHandler); } catch (_) { }
	try { if (_avatarClickHandler) document.getElementById('profile-avatar')?.removeEventListener('click', _avatarClickHandler); } catch (_) { }
	try { if (_galleryClickHandler) document.getElementById('photo-gallery')?.removeEventListener('click', _galleryClickHandler); } catch (_) { }
	try { if (_reopen2faSubmitHandler) document.getElementById('reopen2faSubmit')?.removeEventListener('click', _reopen2faSubmitHandler); } catch(_) {}
	try { if (_reopen2faCancelHandler) document.getElementById('reopen2faCancel')?.removeEventListener('click', _reopen2faCancelHandler); } catch(_) {}
	try { if (_resetPasswordConfirmHandler) document.getElementById('resetPasswordConfirm')?.removeEventListener('click', _resetPasswordConfirmHandler); } catch(_) {}
	try { if (_resetPasswordBtnHandler) document.getElementById('btn-reset-password')?.removeEventListener('click', _resetPasswordBtnHandler); } catch(_) {}
	try { if (_visibilityHandler) document.removeEventListener('visibilitychange', _visibilityHandler); } catch (_) { }
	try { if (_beforeUnloadHandler) window.removeEventListener('beforeunload', _beforeUnloadHandler); } catch (_) { }

	_btnChangePhotoHandler = null;
	_inputPhotoHandler = null;
	_avatarClickHandler = null;
	_galleryClickHandler = null;
	_visibilityHandler = null;
	_beforeUnloadHandler = null;
}