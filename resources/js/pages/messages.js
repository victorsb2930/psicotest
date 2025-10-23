import ConnectyCube from 'connectycube';
// Messages page module: initializes chat UI, binds handlers, and cleans up on destroy
// Inspired by resources/views/messages/index.blade.php inline script, adapted to init/destroy pattern like profile.js

// Module state and handler refs
let _state = {
	currentPartnerId: null,
	renderedMessageIds: new Set(),
};
let _els = {};
let _handlers = {};
let _presenceInterval = null;
const PRESENCE = {
	labels: { online: 'Online', busy: 'Ocupado', dnd: 'No molestar', away: 'Ausente', offline: 'No disponible' },
	colors: { online: '#28a745', busy: '#fd7e14', dnd: '#dc3545', away: '#ffc107', offline: '#6c757d' }
};

function el(id) { return document.getElementById(id); }

function getAuthId() {
	try { return String(window.__authUserId || ''); } catch (_) { return ''; }
}

function applyPresenceToDot(dotEl, status) {
	if (!dotEl) return;
	try {
		const color = PRESENCE.colors[status] || PRESENCE.colors.offline;
		const title = PRESENCE.labels[status] || 'No disponible';
		dotEl.style.background = color;
		dotEl.setAttribute('title', title);
	} catch (_) { }
}

function setPresenceDot(userId, status) {
	try {
		const el = document.querySelector('.presence-dot-small[data-user-id="' + userId + '"]');
		applyPresenceToDot(el, status);
	} catch (_) { }
}

function setHeaderPresence(status) {
	try { applyPresenceToDot(_els.chatPartnerPresence, status); } catch (_) { }
}

function appendMessageToChat(msg, opts = {}) {
	if (msg.id && _state.renderedMessageIds.has(msg.id)) return;
	const AUTH_ID = getAuthId();
	const isMine = String(msg.from_id) === String(AUTH_ID);
	const wrap = document.createElement('div');
	wrap.className = 'msg mb-2 ' + (isMine ? 'text-end msg-me' : 'msg-other');
	const bubble = document.createElement('div');
	bubble.className = 'msg-bubble d-inline-block p-2 rounded ' + (isMine ? 'bg-primary text-white' : 'bg-light');
	bubble.style.maxWidth = '70%';
	bubble.style.whiteSpace = 'pre-wrap';
	bubble.textContent = msg.body || '';
	const meta = document.createElement('div');
	meta.className = 'msg-meta small text-muted mt-1';
	meta.textContent = msg.created_at ? (new Date(msg.created_at)).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
	if (msg.id) { _state.renderedMessageIds.add(msg.id); wrap.dataset.msgId = msg.id; }
	if (opts.tempId) wrap.dataset.tempId = opts.tempId;
	wrap.appendChild(bubble);
	wrap.appendChild(meta);
	_els.chatMessages.appendChild(wrap);
	try {
		const threshold = 120;
		const atBottom = (_els.chatMessages.scrollHeight - _els.chatMessages.clientHeight - _els.chatMessages.scrollTop) < threshold;
		if (atBottom) _els.chatMessages.scrollTop = _els.chatMessages.scrollHeight;
	} catch (_) { _els.chatMessages.scrollTop = _els.chatMessages.scrollHeight; }
}

function openChatFor(userId, userName) {
	_state.currentPartnerId = userId;
	if (_els.chatPartnerName) _els.chatPartnerName.textContent = userName;
	if (_els.chatEmpty) _els.chatEmpty.style.display = 'none';
	if (_els.chatContainer) _els.chatContainer.style.display = '';
	if (_els.chatMessages) _els.chatMessages.innerHTML = '';
	_state.renderedMessageIds = new Set();
	// load messages by AJAX
	(async () => {
		try {
			const res = await fetch(`/messages/thread/${encodeURIComponent(userId)}?ajax=1`, { headers: { 'Accept': 'application/json' } });
			if (!res.ok) return; const j = await res.json(); if (!j.ok) return;
			j.messages.forEach(m => { if (!m || (m.id && _state.renderedMessageIds.has(m.id))) return; appendMessageToChat(m); });
			// mark read done server-side by endpoint (best-effort)
			try { await fetch(`/messages/thread/${encodeURIComponent(userId)}?ajax=1`, { headers: { 'Accept': 'application/json' } }); } catch (_) { }
		} catch (err) { console.warn(err); }
	})();
}

function closeChat() {
	_state.currentPartnerId = null;
	if (_els.chatPartnerName) _els.chatPartnerName.textContent = '';
	if (_els.chatContainer) _els.chatContainer.style.display = 'none';
	if (_els.chatEmpty) _els.chatEmpty.style.display = '';
	if (_els.chatMessages) _els.chatMessages.innerHTML = '';
}

async function hydrateContact(userId) {
	try {
		const r = await fetch(`/users/${encodeURIComponent(userId)}/status`);
		if (!r.ok) return; const j = await r.json(); if (!j.ok) return;
		if (j.profile_photo) {
			const btn = document.querySelector('.contact-item[data-user-id="' + userId + '"]');
			if (btn) btn.querySelector('img')?.setAttribute('src', j.profile_photo);
		}
		if (j.status) {
			setPresenceDot(userId, j.status);
			if (String(_state.currentPartnerId || '') === String(userId || '')) setHeaderPresence(j.status);
		}
	} catch (_) { }
}

async function openProfileModal(userId, userName) {
	let photos = [];
	try {
		const r = await fetch(`/users/${encodeURIComponent(userId)}/photos`);
		if (r.ok) { const j = await r.json(); if (j.ok) photos = j.photos || []; }
	} catch (_) { }
	try {
		const st = await fetch(`/users/${encodeURIComponent(userId)}/status`);
		let stj = null; if (st.ok) stj = await st.json();
		const usablePhotos = (photos || []).filter(p => p && (p.url || p.secure_url || p.data_url));
		const profile = stj?.profile_photo || (usablePhotos.length ? (usablePhotos[0].url || usablePhotos[0].secure_url || usablePhotos[0].data_url) : null) || window.__defaultAvatar;
		const actionsHtml = `
			<div class="d-flex gap-3 flex-wrap my-2">
				<button class="btn btn-sm btn-light profile-action" data-action="call"><i class="bi bi-telephone"></i> Llamar</button>
				<button class="btn btn-sm btn-light profile-action" data-action="video"><i class="bi bi-camera-video"></i> Video</button>
				<button class="btn btn-sm btn-light profile-action" data-action="photos"><i class="bi bi-images"></i> Fotos (${usablePhotos.length})</button>
				<button class="btn btn-sm btn-light profile-action" data-action="files"><i class="bi bi-file-earmark"></i> Files</button>
				<button class="btn btn-sm btn-light profile-action" data-action="audios"><i class="bi bi-mic"></i> Audios</button>
				<button class="btn btn-sm btn-light profile-action" data-action="links"><i class="bi bi-link-45deg"></i> Links</button>
				<button class="btn btn-sm btn-outline-danger profile-action" data-action="delete"><i class="bi bi-trash"></i> Eliminar</button>
				<button class="btn btn-sm btn-outline-danger profile-action" data-action="block"><i class="bi bi-slash-circle"></i> Bloquear</button>
			</div>
		`;

		const thumbsHtml = usablePhotos.length ? `<div class="d-flex gap-2 justify-content-center flex-wrap mt-3">${usablePhotos.slice(0,6).map(p => `<img src="${p.url||p.secure_url||p.data_url}" width="72" height="72" style="object-fit:cover; border-radius:6px; cursor:pointer;" data-photo-id="${p.id}">`).join('')}</div>` : '';

		const bodyHtml = {
			title: userName,
			body: `<div class="text-center"><img id="modal-profile-img" src="${profile}" alt="avatar" style="max-width:80%; height:auto; cursor:pointer; border-radius:8px;" /></div>${actionsHtml}${thumbsHtml}`,
			modalId: `profile-modal-${userId}`,
			buttons: [{ text: 'Cerrar', className: 'btn-outline-secondary', dismiss: true }]
		};
		modalConfirm(bodyHtml, 'normal');

		const img = document.getElementById('modal-profile-img');
		if (img) img.addEventListener('click', () => openFullscreenViewer(usablePhotos, 0));
		setTimeout(() => {
			document.querySelectorAll(`#profile-modal-${userId} .profile-action`).forEach(a => {
				a.addEventListener('click', function (e) {
					e.preventDefault(); const act = this.getAttribute('data-action');
					if (act === 'photos') openFullscreenViewer(usablePhotos, 0);
					if (act === 'video') makeVideoCall(userId);
					else modalNotification?.('Acción', act, { template: 'info' });
				});
			});
			document.querySelectorAll(`#profile-modal-${userId} img[data-photo-id]`).forEach((thumb, i) => {
				thumb.addEventListener('click', function () {
					const idx = Array.from(document.querySelectorAll(`#profile-modal-${userId} img[data-photo-id]`)).indexOf(this);
					openFullscreenViewer(usablePhotos, idx);
				});
			});
		}, 120);
	} catch (_) { }
}

function openFullscreenViewer(photos, startIndex) {
	if (!photos || !photos.length) return modalNotification?.('Sin fotos', 'Este usuario no tiene fotos', { template: 'info' });
	let idx = startIndex || 0;
	const overlay = document.createElement('div');
	overlay.style.position = 'fixed'; overlay.style.left = '0'; overlay.style.top = '0'; overlay.style.right = '0'; overlay.style.bottom = '0'; overlay.style.background = 'rgba(0,0,0,0.9)'; overlay.style.zIndex = 2000; overlay.style.display = 'flex'; overlay.style.alignItems = 'center'; overlay.style.justifyContent = 'center';
	const img = document.createElement('img');
	img.src = photos[idx].url || photos[idx].secure_url || window.__defaultAvatar;
	img.style.maxWidth = '95%'; img.style.maxHeight = '95%'; img.style.objectFit = 'contain'; img.style.cursor = 'pointer';
	overlay.appendChild(img);
	const close = () => { try { document.body.removeChild(overlay); } catch (_) { } window.removeEventListener('keydown', onKey); };
	img.addEventListener('click', close);
	function onKey(e) {
		if (e.key === 'ArrowRight') { idx = Math.min(idx + 1, photos.length - 1); img.src = photos[idx].url || photos[idx].secure_url || window.__defaultAvatar; }
		else if (e.key === 'ArrowLeft') { idx = Math.max(0, idx - 1); img.src = photos[idx].url || photos[idx].secure_url || window.__defaultAvatar; }
		else if (e.key === 'Escape') close();
	}
	window.addEventListener('keydown', onKey);
	document.body.appendChild(overlay);
}

function makeVideoCall(userId){
	// Placeholder for video call functionality
	console.log('Video call initiated to user:', userId);
	const CREDENTIALS = {
		appId: 21,
		authKey: "hhf87hfushuiwef",
	};

	ConnectyCube.init(CREDENTIALS);

	const session = ConnectyCube.videochat.createNewSession([parseInt(userId,10)], {video: true, audio: true});

	const mediaParams = {
		audio: true,
		video: {
			width: { min: 640, ideal: 1280, max: 1920 },
			height: { min: 480, ideal: 720, max: 1080 }
		}
	};
	console.log(session);
	/* session.getUserMedia(mediaParams, function(err, stream) {
		if (err) {
			console.log(err);
		} else {
			console.log('Media stream obtained:', stream);
			// Aquí se manejaría la transmisión de video
			ConnectyCube.videochat.attachMediaStream(session, stream);
			ConnectyCube.videochat.startCall(session);
		}
	}); */
}

export function init() {
	// Cache elements
	_els = {
		contactsList: el('contacts-list'),
		searchInput: el('contacts-search'),
		chatPanel: el('chat-panel'),
		chatEmpty: el('chat-empty'),
		chatContainer: el('chat-container'),
		chatMessages: el('chat-messages'),
		chatSendForm: el('chat-send-form'),
		chatPartnerName: el('chat-partner-name'),
		chatPartnerPresence: el('chat-partner-presence'),
		chatClose: el('chat-close'),
	};

	// Guard if core container not present
	if (!_els.contactsList || !_els.chatMessages) return;

	// Initial presence dots to offline (with proper title)
	try { document.querySelectorAll('.presence-dot-small').forEach(el => { applyPresenceToDot(el, 'offline'); }); } catch (_) { }

	// Hydrate contacts (presence and avatar)
	try { document.querySelectorAll('.contact-item').forEach(it => { const uid = it.getAttribute('data-user-id'); if (uid) hydrateContact(uid); }); } catch (_) { }

	// Periodic presence polling for contacts and current partner (fallback when no realtime)
	function startPresencePolling() {
		if (_presenceInterval) return;
		const PERIOD = 20000; // 20s
		const tick = async () => {
			try {
				const ids = Array.from(document.querySelectorAll('.contact-item')).map(it => it.getAttribute('data-user-id')).filter(Boolean);
				// dedupe
				const unique = Array.from(new Set(ids));
				await Promise.all(unique.map(uid => hydrateContact(uid)));
			} catch (_) { }
		};
		// immediate + interval
		tick();
		_presenceInterval = setInterval(tick, PERIOD);
	}
	function stopPresencePolling() { try { if (_presenceInterval) { clearInterval(_presenceInterval); _presenceInterval = null; } } catch (_) { } }
	_handlers.stopPresencePolling = stopPresencePolling;
	startPresencePolling();

	// Event handlers
	_handlers.onContactClick = function (e) {
		const btn = e.target.closest && e.target.closest('.contact-item'); if (!btn) return;
		const uid = btn.getAttribute('data-user-id');
		const uname = btn.getAttribute('data-user-name') || btn.querySelector('.fw-semibold')?.textContent || 'Usuario';
		// Mark active contact visually
		try { document.querySelectorAll('.contact-item.active').forEach(it => it.classList.remove('active')); btn.classList.add('active'); } catch (_) { }
		openChatFor(uid, uname);
	};
	_els.contactsList.addEventListener('click', _handlers.onContactClick);

	_handlers.onSend = async function (e) {
		e.preventDefault();
		if (!_els.chatSendForm) return;
		const fd = new FormData(_els.chatSendForm);
		const body = (fd.get('body') || '').toString().trim();
		if (!body) return;
		const tempId = 't' + Date.now() + Math.floor(Math.random() * 1000);
		appendMessageToChat({ from_id: getAuthId(), body: body, created_at: new Date().toISOString() }, { tempId });
		try { _els.chatSendForm.querySelector('[name=body]').value = ''; } catch (_) { }
		try {
			const res = await fetch(`/messages/thread/${encodeURIComponent(_state.currentPartnerId)}`, { method: 'POST', headers: { 'X-CSRF-TOKEN': fd.get('_token') }, body: fd });
			const j = await res.json();
			if (j.ok && j.message) {
				const tempEl = _els.chatMessages.querySelector('[data-temp-id="' + tempId + '"]'); if (tempEl) tempEl.remove();
				appendMessageToChat(j.message);
				try { await fetch('/api/counters').then(r => r.json()).then(d => document.dispatchEvent(new CustomEvent('counters:update', { detail: d }))); } catch (_) { }
			} else {
				const tempEl = _els.chatMessages.querySelector('[data-temp-id="' + tempId + '"]'); if (tempEl) tempEl.querySelector('.d-inline-block')?.classList.add('bg-danger', 'text-white');
			}
		} catch (err) {
			console.warn(err);
			const tempEl = _els.chatMessages.querySelector('[data-temp-id="' + tempId + '"]'); if (tempEl) tempEl.querySelector('.d-inline-block')?.classList.add('bg-danger', 'text-white');
		}
	};
	_els.chatSendForm?.addEventListener('submit', _handlers.onSend);

	_handlers.onSearch = function () {
		const q = (this.value || '').toLowerCase().trim();
		document.querySelectorAll('.contact-item').forEach(it => {
			const name = (it.querySelector('.fw-semibold')?.textContent || '').toLowerCase();
			const msg = (it.querySelector('.text-truncate')?.textContent || '').toLowerCase();
			it.style.display = (!q || name.indexOf(q) !== -1 || msg.indexOf(q) !== -1) ? '' : 'none';
		});
	};
	_els.searchInput?.addEventListener('input', _handlers.onSearch);

	_handlers.onClose = function () { closeChat(); };
	_els.chatClose?.addEventListener('click', _handlers.onClose);

	_handlers.onRtMessage = function (ev) {
		try {
			const d = ev.detail; if (!d) return;
			const fromId = String(d.from_id);
			if (String(_state.currentPartnerId) === fromId) {
				appendMessageToChat({ id: d.id, from_id: d.from_id, body: d.body, created_at: d.created_at });
				try { fetch(`/messages/thread/${encodeURIComponent(_state.currentPartnerId)}?ajax=1`, { headers: { 'Accept': 'application/json' } }); } catch (_) { }
			}
			const contactBtn = document.querySelector('.contact-item[data-user-id="' + fromId + '"]');
			if (contactBtn) {
				let badge = contactBtn.querySelector('.badge');
				if (!badge) { badge = document.createElement('span'); badge.className = 'badge text-bg-primary small'; badge.textContent = 'Nuevo'; contactBtn.appendChild(badge); }
				_els.contactsList.prepend(contactBtn);
			}
		} catch (_) { }
	};
	window.addEventListener('rt:message', _handlers.onRtMessage);

	_handlers.onPresence = function (ev) { try { const d = ev.detail; if (d && d.user_id) setPresenceDot(d.user_id, d.status || 'offline'); } catch (_) { } };
	window.addEventListener('rt:user_presence', _handlers.onPresence);

	if (_els.chatPartnerName) {
		_els.chatPartnerName.style.cursor = 'pointer';
		_handlers.onPartnerClick = function () { if (_state.currentPartnerId) openProfileModal(_state.currentPartnerId, _els.chatPartnerName.textContent || 'Usuario'); };
		_els.chatPartnerName.addEventListener('click', _handlers.onPartnerClick);
	}
}

export function destroy() {
	try { _els.contactsList?.removeEventListener('click', _handlers.onContactClick); } catch (_) { }
	try { _els.chatSendForm?.removeEventListener('submit', _handlers.onSend); } catch (_) { }
	try { _els.searchInput?.removeEventListener('input', _handlers.onSearch); } catch (_) { }
	try { _els.chatClose?.removeEventListener('click', _handlers.onClose); } catch (_) { }
	try { window.removeEventListener('rt:message', _handlers.onRtMessage); } catch (_) { }
	try { window.removeEventListener('rt:user_presence', _handlers.onPresence); } catch (_) { }
	try { if (_handlers.stopPresencePolling) _handlers.stopPresencePolling(); } catch (_) { }
	try { if (_handlers.onPartnerClick && _els.chatPartnerName) _els.chatPartnerName.removeEventListener('click', _handlers.onPartnerClick); } catch (_) { }

	// Reset state
	_state.currentPartnerId = null;
	_state.renderedMessageIds = new Set();
	_els = {};
	_handlers = {};
	_presenceInterval = null;
}
