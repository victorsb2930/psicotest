import ConnectyCube from 'connectycube';
import RtcUI from '../rtc-ui';

// Módulo de la página de mensajes: inicializa la UI, enlaza handlers y limpia en destroy

// Estado del módulo
let _state = { currentPartnerId: null, renderedMessageIds: new Set() };
let _els = {}; let _handlers = {}; let _presenceInterval = null; let _friendsInterval = null;
const PRESENCE = { labels: { online: 'Online', busy: 'Ocupado', dnd: 'No molestar', away: 'Ausente', offline: 'No disponible' }, colors: { online: '#28a745', busy: '#fd7e14', dnd: '#dc3545', away: '#ffc107', offline: '#6c757d' } };

// Estado ConnectyCube/WebRTC
let _cc = { initialized: false, connected: false, currentSession: null, localStream: null };

// Resolve a ConnectyCube user id for a given app user id using deterministic login
async function resolveCcId(appUserId) {
	try {
		// Ensure RTC bootstrap (endpoints + session) before hitting Users API to avoid 403 and wrong region
		try { if (window.__rtcBootstrapPromise) await window.__rtcBootstrapPromise; } catch (_) { }
		// If already cached and looks like a real CC id, return immediately
		try {
			const existing = window.__ccUserIdMap?.[appUserId];
			if (existing && Number(existing) >= 100000) return existing;
		} catch (_) { }
		// Require config to build deterministic login
		const cfg = window.__ccConfig || {};
		if (!cfg || !cfg.appId) return null;
		const login = `pg${cfg.appId}_${appUserId}`;
		// Use ConnectyCube Users API to fetch by login
		let res;
		try {
			if (ConnectyCube?.users?.getByLogin) {
				res = await ConnectyCube.users.getByLogin(login);
			} else {
				res = await ConnectyCube.users.get({ login }); // fallback for older SDKs
			}
		} catch (e) {
			// If unauthorized/forbidden (e.g., 401/403), give up silently and let caller fallback later
			return null;
		}
		// SDK may return {items:[{user}]} or a plain user depending on filter
		const user = (res && (res.user || (Array.isArray(res.items) ? (res.items[0] && (res.items[0].user || res.items[0])) : null))) || null;
		const id = user && (user.id || user._id || user.user_id);
		if (id) {
			try {
				window.__ccUserIdMap = window.__ccUserIdMap || {};
				window.__ccUserIdMap[appUserId] = id;
			} catch (_) { }
			return id;
		}
	} catch (e) { /* ignore resolution errors, fallback to null */ }
	return null;
}


function shouldResolveCcId(appUserId) {
	try {
		const map = window.__ccUserIdMap || {};
		const mapped = map[appUserId];
		const appNum = parseInt(String(appUserId), 10);
		if (!mapped) return true;
		// If mapping equals the app user id, it's a fallback and we should resolve the real CC id
		if (Number(mapped) === appNum) return true;
		// Heuristic: real CC ids are large global ints; try to resolve if the id looks too small
		if (Number(mapped) > 0 && Number(mapped) < 100000) return true;
	} catch (_) { }
	return false;
}

async function prewarmCcMap(appUserIds) {
	try {
		// Ensure RTC stack ready before prewarm
		try { if (window.__rtcBootstrapPromise) await window.__rtcBootstrapPromise; } catch (_) { }
		const ids = Array.from(new Set((appUserIds || []).map(x => String(x))));
		for (const id of ids) {
			try {
				if (!shouldResolveCcId(id)) continue;
				const got = await resolveCcId(id);
				if (got) {
					window.__ccUserIdMap = window.__ccUserIdMap || {};
					window.__ccUserIdMap[id] = got;
				}
			} catch (_) { /* continue */ }
		}
	} catch (_) { }
}

function el(id) { return document.getElementById(id); }
function getAuthId() { try { return String(window.__authUserId || ''); } catch (_) { return ''; } }
function applyPresenceToDot(dotEl, status) { if (!dotEl) return; try { const color = PRESENCE.colors[status] || PRESENCE.colors.offline; const title = PRESENCE.labels[status] || 'No disponible'; dotEl.style.background = color; dotEl.setAttribute('title', title); } catch (_) { } }
function setPresenceDot(userId, status) { try { const e = document.querySelector('.presence-dot-small[data-user-id="' + userId + '"]'); applyPresenceToDot(e, status); } catch (_) { } }
function setHeaderPresence(status) { try { applyPresenceToDot(_els.chatPartnerPresence, status); } catch (_) { } }

function appendMessageToChat(msg, opts = {}) { if (msg.id && _state.renderedMessageIds.has(msg.id)) return; const AUTH_ID = getAuthId(); const isMine = String(msg.from_id) === String(AUTH_ID); try { _els.chatMessages.classList.remove('is-empty'); } catch (_) { } const wrap = document.createElement('div'); wrap.className = 'msg mb-2 ' + (isMine ? 'text-end msg-me' : 'msg-other'); const bubble = document.createElement('div'); bubble.className = 'msg-bubble d-inline-block p-2 rounded ' + (isMine ? 'bg-primary text-white' : 'bg-light'); bubble.style.maxWidth = '70%'; bubble.style.whiteSpace = 'pre-wrap'; bubble.textContent = msg.body || ''; const meta = document.createElement('div'); meta.className = 'msg-meta small text-muted mt-1'; meta.textContent = msg.created_at ? (new Date(msg.created_at)).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''; if (msg.id) { _state.renderedMessageIds.add(msg.id); wrap.dataset.msgId = msg.id; } if (opts.tempId) wrap.dataset.tempId = opts.tempId; wrap.appendChild(bubble); wrap.appendChild(meta); _els.chatMessages.appendChild(wrap); try { const threshold = 120; const atBottom = (_els.chatMessages.scrollHeight - _els.chatMessages.clientHeight - _els.chatMessages.scrollTop) < threshold; if (atBottom) _els.chatMessages.scrollTop = _els.chatMessages.scrollHeight; } catch (_) { _els.chatMessages.scrollTop = _els.chatMessages.scrollHeight; } }

function openChatFor(userId, userName) { _state.currentPartnerId = userId; if (_els.chatPartnerName) _els.chatPartnerName.textContent = userName; if (_els.chatEmpty) _els.chatEmpty.style.display = 'none'; if (_els.chatContainer) _els.chatContainer.style.display = ''; if (_els.chatMessages) { _els.chatMessages.innerHTML = ''; try { _els.chatMessages.classList.add('is-empty'); } catch (_) { } } _state.renderedMessageIds = new Set(); (async () => { try { const res = await fetch(`/messages/thread/${encodeURIComponent(userId)}?ajax=1`, { headers: { 'Accept': 'application/json' } }); if (!res.ok) return; const j = await res.json(); if (!j.ok) return; if (!j.messages || !j.messages.length) { try { _els.chatMessages.classList.add('is-empty'); } catch (_) { } } else { j.messages.forEach(m => { if (!m || (m.id && _state.renderedMessageIds.has(m.id))) return; appendMessageToChat(m); }); } try { await fetch(`/messages/thread/${encodeURIComponent(userId)}?ajax=1`, { headers: { 'Accept': 'application/json' } }); } catch (_) { } } catch (err) { } })(); }

function closeChat() { _state.currentPartnerId = null; if (_els.chatPartnerName) _els.chatPartnerName.textContent = ''; if (_els.chatContainer) _els.chatContainer.style.display = 'none'; if (_els.chatEmpty) _els.chatEmpty.style.display = ''; if (_els.chatMessages) _els.chatMessages.innerHTML = ''; }

async function hydrateContact(userId) { try { const r = await fetch(`/users/${encodeURIComponent(userId)}/status`); if (!r.ok) return; const j = await r.json(); if (!j.ok) return; if (j.profile_photo) { const btn = document.querySelector('.contact-item[data-user-id="' + userId + '"]'); if (btn) btn.querySelector('img')?.setAttribute('src', j.profile_photo); } if (j.status) { setPresenceDot(userId, j.status); if (String(_state.currentPartnerId || '') === String(userId || '')) setHeaderPresence(j.status); } } catch (_) { } }

async function openProfileModal(userId, userName) {
	let photos = []; try { const r = await fetch(`/users/${encodeURIComponent(userId)}/photos`); if (r.ok) { const j = await r.json(); if (j.ok) photos = j.photos || []; } } catch (_) { } try {
		const st = await fetch(`/users/${encodeURIComponent(userId)}/status`); let stj = null; if (st.ok) stj = await st.json(); const usable = (photos || []).filter(p => p && (p.url || p.secure_url || p.data_url)); const profile = stj?.profile_photo || (usable.length ? (usable[0].url || usable[0].secure_url || usable[0].data_url) : null) || window.__defaultAvatar; const actionsHtml = `
            <div class="d-flex gap-3 flex-wrap my-2">
                <button class="btn btn-sm btn-light profile-action" data-action="call"><i class="bi bi-telephone"></i> Llamar</button>
                <button class="btn btn-sm btn-light profile-action" data-action="video"><i class="bi bi-camera-video"></i> Video</button>
                <button class="btn btn-sm btn-light profile-action" data-action="photos"><i class="bi bi-images"></i> Fotos (${usable.length})</button>
                <button class="btn btn-sm btn-light profile-action" data-action="files"><i class="bi bi-file-earmark"></i> Files</button>
                <button class="btn btn-sm btn-light profile-action" data-action="audios"><i class="bi bi-mic"></i> Audios</button>
                <button class="btn btn-sm btn-light profile-action" data-action="links"><i class="bi bi-link-45deg"></i> Links</button>
                <button class="btn btn-sm btn-outline-danger profile-action" data-action="delete"><i class="bi bi-trash"></i> Eliminar</button>
                <button class="btn btn-sm btn-outline-danger profile-action" data-action="block"><i class="bi bi-slash-circle"></i> Bloquear</button>
            </div>`;
		const thumbsHtml = usable.length ? `<div class="d-flex gap-2 justify-content-center flex-wrap mt-3">${usable.slice(0, 6).map(p => `<img src="${p.url || p.secure_url || p.data_url}" width="72" height="72" style="object-fit:cover; border-radius:6px; cursor:pointer;" data-photo-id="${p.id}">`).join('')}</div>` : '';
		const bodyHtml = { title: userName, body: `<div class="text-center"><img id="modal-profile-img" src="${profile}" alt="avatar" style="max-width:80%; height:auto; cursor:pointer; border-radius:8px;" /></div>${actionsHtml}${thumbsHtml}`, modalId: `profile-modal-${userId}`, buttons: [{ text: 'Cerrar', className: 'btn-outline-secondary', dismiss: true }] };
		modalConfirm(bodyHtml, 'normal');
		const img = document.getElementById('modal-profile-img'); if (img) img.addEventListener('click', () => openFullscreenViewer(usable, 0));
		setTimeout(() => {
			const modalSel = `#profile-modal-${userId}`;
			const videoBtn = document.querySelector(`${modalSel} .profile-action[data-action="video"]`);
			if (videoBtn) {
				const originalText = videoBtn.innerHTML;
				const setLoading = (on) => { try { videoBtn.disabled = !!on; videoBtn.innerHTML = on ? '<span class="spinner-border spinner-border-sm me-1"></span> Video' : originalText; } catch (_) { } };
				// No bloqueamos el botón de forma indefinida; solo mostramos spinner durante el click
				videoBtn.addEventListener('click', async function (e) {
					e.preventDefault();
					setLoading(true);
					try { if (window.__rtcBootstrapPromise) await window.__rtcBootstrapPromise; } catch (_) { }
					setLoading(false);
					if (!window.__rtcBootstrapReady) {
						return modalNotification?.('Video', 'RTC no está listo. Intenta nuevamente en unos segundos.', { template: 'warning' });
					}
					makeVideoCall(userId);
				});
			}
			document.querySelectorAll(`${modalSel} .profile-action`).forEach(a => { const act = a.getAttribute('data-action'); if (act === 'video') return; a.addEventListener('click', function (e) { e.preventDefault(); const action = this.getAttribute('data-action'); if (action === 'photos') { openFullscreenViewer(usable, 0); } else if (action === 'call') { try { if (window.__rtcBootstrapPromise) window.__rtcBootstrapPromise.then(() => makeVoiceCall(userId)); else makeVoiceCall(userId); } catch (_) { makeVoiceCall(userId); } } else { modalNotification?.('Acción', action, { template: 'info' }); } }); });
			document.querySelectorAll(`#profile-modal-${userId} img[data-photo-id]`).forEach((thumb) => { thumb.addEventListener('click', function () { const idx = Array.from(document.querySelectorAll(`#profile-modal-${userId} img[data-photo-id]`)).indexOf(this); openFullscreenViewer(usable, idx); }); });
		}, 120);
	} catch (_) { }
}

function openFullscreenViewer(photos, startIndex) { if (!photos || !photos.length) return modalNotification?.('Sin fotos', 'Este usuario no tiene fotos', { template: 'info' }); let idx = startIndex || 0; const overlay = document.createElement('div'); overlay.style.position = 'fixed'; overlay.style.left = '0'; overlay.style.top = '0'; overlay.style.right = '0'; overlay.style.bottom = '0'; overlay.style.background = 'rgba(0,0,0,0.9)'; overlay.style.zIndex = 2000; overlay.style.display = 'flex'; overlay.style.alignItems = 'center'; overlay.style.justifyContent = 'center'; const img = document.createElement('img'); img.src = photos[idx].url || photos[idx].secure_url || window.__defaultAvatar; img.style.maxWidth = '95%'; img.style.maxHeight = '95%'; img.style.objectFit = 'contain'; img.style.cursor = 'pointer'; overlay.appendChild(img); const close = () => { try { document.body.removeChild(overlay); } catch (_) { } window.removeEventListener('keydown', onKey); }; img.addEventListener('click', close); function onKey(e) { if (e.key === 'ArrowRight') { idx = Math.min(idx + 1, photos.length - 1); img.src = photos[idx].url || photos[idx].secure_url || window.__defaultAvatar; } else if (e.key === 'ArrowLeft') { idx = Math.max(0, idx - 1); img.src = photos[idx].url || photos[idx].secure_url || window.__defaultAvatar; } else if (e.key === 'Escape') { close(); } } window.addEventListener('keydown', onKey); document.body.appendChild(overlay); }

async function makeVideoCall(appUserId) {
	try {
		try { if (window.__rtcBootstrapPromise) await window.__rtcBootstrapPromise; } catch (_) { } if (!window.__rtcBootstrapReady) { return modalNotification('Video', 'RTC no está listo. Intenta nuevamente en unos segundos.', { template: 'warning' }); }
		// Prefer a real mapping; avoid falling back to the numeric app id for calls
		let opponentCcId = null;
		try {
			const map = window.__ccUserIdMap || {};
			if (Object.prototype.hasOwnProperty.call(map, appUserId)) opponentCcId = map[appUserId];
		} catch (_) { }
		if (!opponentCcId || shouldResolveCcId(appUserId)) {
			const resolved = await resolveCcId(appUserId);
			if (resolved) {
				opponentCcId = resolved;
				try { window.__ccUserIdMap = window.__ccUserIdMap || {}; window.__ccUserIdMap[appUserId] = resolved; } catch (_) { }
			}
		}
		if (!opponentCcId) return modalNotification('Video', 'No se pudo resolver el ID del destinatario para ConnectyCube.');
		const session = ConnectyCube.videochat.createNewSession([opponentCcId], ConnectyCube.videochat.CallType.VIDEO); _cc.currentSession = session; const contactBtn = document.querySelector(`.contact-item[data-user-id="${appUserId}"]`); const name = contactBtn?.getAttribute('data-user-name') || _els.chatPartnerName?.textContent || 'Contacto'; RtcUI.showOutgoing(session, { nombre: name, onCancel: () => { try { session.stop({}, () => { }); } catch (_) { } RtcUI.end(); _cc.currentSession = null; } }); try { const constraints = { audio: true, video: { width: { ideal: 1280 }, height: { ideal: 720 } } }; const stream = await session.getUserMedia(constraints); RtcUI.setLocalStream(stream); } catch (e) { return modalNotification('Atención', 'No se pudo acceder a la cámara o micrófono.', { template: 'danger' }); } session.call({}, (err) => { });
	} catch (e) { modalNotification('Video', 'Error iniciando la llamada.', { template: 'danger' }); }
}

export function init() {
	_els = { contactsList: el('contacts-list'), searchInput: el('contacts-search'), chatPanel: el('chat-panel'), chatEmpty: el('chat-empty'), chatContainer: el('chat-container'), chatMessages: el('chat-messages'), chatSendForm: el('chat-send-form'), chatPartnerName: el('chat-partner-name'), chatPartnerPresence: el('chat-partner-presence'), chatClose: el('chat-close') };
	if (!_els.contactsList || !_els.chatMessages) return;
	// Fast-filter bookkeeping: keep a cached list and lowercase fields to avoid repeated DOM reads
	const _filter = { items: [], tId: null };
	const rebuildFilterIndex = () => {
		try {
			_filter.items = Array.from(_els.contactsList.querySelectorAll('.contact-item'));
			_filter.items.forEach(it => {
				if (!it.dataset.nameLc) {
					const nm = (it.getAttribute('data-user-name') || it.querySelector('.fw-semibold')?.textContent || '').toLowerCase();
					it.dataset.nameLc = nm;
				}
				if (!it.dataset.lastLc) {
					const truncs = it.querySelectorAll('.text-truncate');
					const msg = truncs && truncs.length ? (truncs[truncs.length - 1].textContent || '') : '';
					it.dataset.lastLc = msg.toLowerCase();
				}
			});
		} catch (_) { }
	};
	try { document.querySelectorAll('.presence-dot-small').forEach(el => { applyPresenceToDot(el, 'offline'); }); } catch (_) { }
	try { document.querySelectorAll('.contact-item').forEach(it => { const uid = it.getAttribute('data-user-id'); if (uid) hydrateContact(uid); }); } catch (_) { }
	function startPresencePolling() { if (_presenceInterval) return; const PERIOD = 20000; const tick = async () => { try { const ids = Array.from(document.querySelectorAll('.contact-item')).map(it => it.getAttribute('data-user-id')).filter(Boolean); const unique = Array.from(new Set(ids)); await Promise.all(unique.map(uid => hydrateContact(uid))); } catch (_) { } }; tick(); _presenceInterval = setInterval(tick, PERIOD); }
	function stopPresencePolling() { try { if (_presenceInterval) { clearInterval(_presenceInterval); _presenceInterval = null; } } catch (_) { } }
	_handlers.startPresencePolling = startPresencePolling;
	_handlers.stopPresencePolling = stopPresencePolling;
	startPresencePolling();
	// Precalentar mapeo CC para contactos visibles
	try {
		const ids = Array.from(new Set(Array.from(document.querySelectorAll('.contact-item')).map(it => it.getAttribute('data-user-id')).filter(Boolean)));
		(async () => { try { if (window.__rtcBootstrapPromise) await window.__rtcBootstrapPromise; } catch (_) { }; prewarmCcMap(ids); })();
	} catch (_) { }
	// Contacts loader (accepted friends)
	function escapeHtml(s) { if (!s) return ''; return String(s).replace(/[&<>"'`=\/]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;', '/': '&#x2F;', '`': '&#x60;', '=': '&#x3D;' }[c]; }); }
	async function loadContacts() {
		try {
			const res = await fetch('/friends/list');
			const j = await res.json(); if (!j.ok) return;
			const current = String(_state.currentPartnerId || '');
			_els.contactsList.innerHTML = '';
			if (!j.friends || !j.friends.length) {
				_els.contactsList.innerHTML = '<div class="text-muted small">Aún no tienes contactos.</div>';
				return;
			}
			const frag = document.createDocumentFragment();
			const ids = [];
			const q = (_els.searchInput?.value || '').toLowerCase().trim();
			let added = 0;
			j.friends.forEach(f => {
				const nameL = (f.name || '').toLowerCase();
				const lastL = (f.last_body || '').toLowerCase();
				if (q && (nameL.indexOf(q) === -1 && lastL.indexOf(q) === -1)) return; // skip non-matching while filtering
				ids.push(String(f.id));
				const btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'list-group-item list-group-item-action contact-item d-flex justify-content-between align-items-center';
				btn.setAttribute('data-user-id', String(f.id));
				btn.setAttribute('data-user-name', f.name || 'Usuario');
				// Cache lowercase strings for fast filtering
				btn.dataset.nameLc = nameL;
				btn.dataset.lastLc = lastL;
				if (current && String(f.id) === current) btn.classList.add('active');
				const avatar = f.profile_photo || (window.__defaultAvatar || '');
				btn.innerHTML = `
					<div class="d-flex align-items-start gap-2">
						<img src="${avatar}" alt="avatar" width="36" height="36" class="rounded-circle" style="object-fit:cover;">
						<div style="min-width:0">
							<div class="fw-semibold text-truncate">${escapeHtml(f.name)}</div>
							<div class="text-muted small text-truncate" style="max-width:160px">${escapeHtml(f.last_body || '')}</div>
						</div>
					</div>
					<div>
						<span class="presence-dot-small" data-user-id="${String(f.id)}" title="No disponible" style="width:10px;height:10px;border-radius:50%;background:#6c757d;display:inline-block;margin-top:6px;margin-right:8px"></span>
						${f.unread ? '<span class="badge text-bg-primary small">Nuevo</span>' : ''}
					</div>`;
				frag.appendChild(btn);
				added++;
			});
			if (added === 0) {
				_els.contactsList.innerHTML = '<div class="text-muted small">Sin contactos coincidentes.</div>';
			} else {
				_els.contactsList.appendChild(frag);
			}
			// Rebuild fast-filter index and re-apply if a query is present
			rebuildFilterIndex();
			try { if (_els.searchInput && _els.searchInput.value) { _handlers.applyFilter?.(); } } catch(_){}
			// Pre-warm CC map and hydrate presence for new DOM
			try { prewarmCcMap(ids); } catch (_) { }
			try { await Promise.all(ids.map(hydrateContact)); } catch (_) { }
		} catch (_) { }
	}

	_handlers.onContactClick = function (e) { const btn = e.target.closest && e.target.closest('.contact-item'); if (!btn) return; const uid = btn.getAttribute('data-user-id'); const uname = btn.getAttribute('data-user-name') || btn.querySelector('.fw-semibold')?.textContent || 'Usuario'; try { document.querySelectorAll('.contact-item.active').forEach(it => it.classList.remove('active')); btn.classList.add('active'); } catch (_) { } openChatFor(uid, uname); };
	_els.contactsList.addEventListener('click', _handlers.onContactClick);
	_handlers.onSend = async function (e) { e.preventDefault(); if (!_els.chatSendForm) return; const fd = new FormData(_els.chatSendForm); const body = (fd.get('body') || '').toString().trim(); if (!body) return; const tempId = 't' + Date.now() + Math.floor(Math.random() * 1000); appendMessageToChat({ from_id: getAuthId(), body: body, created_at: new Date().toISOString() }, { tempId }); try { _els.chatSendForm.querySelector('[name=body]').value = ''; } catch (_) { } try { const res = await fetch(`/messages/thread/${encodeURIComponent(_state.currentPartnerId)}`, { method: 'POST', headers: { 'X-CSRF-TOKEN': fd.get('_token') }, body: fd }); const j = await res.json(); if (j.ok && j.message) { const tempEl = _els.chatMessages.querySelector('[data-temp-id="' + tempId + '"]'); if (tempEl) tempEl.remove(); appendMessageToChat(j.message); try { await fetch('/api/counters').then(r => r.json()).then(d => document.dispatchEvent(new CustomEvent('counters:update', { detail: d }))); } catch (_) { } } else { const tempEl = _els.chatMessages.querySelector('[data-temp-id="' + tempId + '"]'); if (tempEl) tempEl.querySelector('.d-inline-block')?.classList.add('bg-danger', 'text-white'); } } catch (err) { const tempEl = _els.chatMessages.querySelector('[data-temp-id="' + tempId + '"]'); if (tempEl) tempEl.querySelector('.d-inline-block')?.classList.add('bg-danger', 'text-white'); } };
	_els.chatSendForm?.addEventListener('submit', _handlers.onSend);
	// Fast, debounced contacts filter using cached dataset fields, batched to avoid layout thrash
	_handlers.applyFilter = function () {
		const q = (_els.searchInput?.value || '').toLowerCase().trim();
		const list = _els.contactsList;
		if (!list) return;
		const items = (_filter.items && _filter.items.length) ? _filter.items : Array.from(list.querySelectorAll('.contact-item'));
		// Hide container to minimize reflow while toggling many rows
		const prevDisplay = list.style.display;
		list.style.display = 'none';
		let visible = 0;
		for (const it of items) {
			const name = it.dataset?.nameLc || '';
			const last = it.dataset?.lastLc || '';
			const show = (!q || name.includes(q) || last.includes(q));
			if (show) { visible++; if (it.classList.contains('d-none')) it.classList.remove('d-none'); }
			else { if (!it.classList.contains('d-none')) it.classList.add('d-none'); }
		}
		// Toggle placeholder for no matches
		let empty = list.querySelector('#contacts-no-matches');
		if (visible === 0) {
			if (!empty) {
				empty = document.createElement('div');
				empty.id = 'contacts-no-matches';
				empty.className = 'text-muted small px-2 py-1';
				empty.textContent = 'Sin contactos coincidentes.';
				list.appendChild(empty);
			}
		} else if (empty) {
			try { empty.remove(); } catch (_) { }
		}
		list.style.display = prevDisplay || '';
	};
	_handlers.onSearch = function () {
		// Pause background polling while typing to avoid contention
		try { _handlers.stopPresencePolling?.(); } catch (_) {}
		try { _handlers.stopFriendsPolling?.(); } catch (_) {}
		try { if (_filter.tId) clearTimeout(_filter.tId); } catch (_) {}
		_filter.tId = setTimeout(() => {
			_handlers.applyFilter();
			// Resume polling shortly after applying filter
			setTimeout(() => {
				try { _handlers.startPresencePolling?.(); } catch (_) {}
				try { _handlers.startFriendsPolling?.(); } catch (_) {}
			}, 400);
		}, 80);
	};
	_els.searchInput?.addEventListener('input', _handlers.onSearch);
	_els.searchInput?.addEventListener('compositionend', _handlers.onSearch);
	// If input already has value on init (e.g., persisted by browser), build index and apply once
	try { rebuildFilterIndex(); if (_els.searchInput && _els.searchInput.value) { _handlers.applyFilter(); } } catch(_){ }
	_handlers.onClose = function () { closeChat(); }; _els.chatClose?.addEventListener('click', _handlers.onClose);
	_handlers.onRtMessage = function (ev) { try { const d = ev.detail; if (!d) return; const fromId = String(d.from_id); if (String(_state.currentPartnerId) === fromId) { appendMessageToChat({ id: d.id, from_id: d.from_id, body: d.body, created_at: d.created_at }); try { fetch(`/messages/thread/${encodeURIComponent(_state.currentPartnerId)}?ajax=1`, { headers: { 'Accept': 'application/json' } }); } catch (_) { } } const contactBtn = document.querySelector('.contact-item[data-user-id="' + fromId + '"]'); if (contactBtn) { let badge = contactBtn.querySelector('.badge'); if (!badge) { badge = document.createElement('span'); badge.className = 'badge text-bg-primary small'; badge.textContent = 'Nuevo'; contactBtn.appendChild(badge); } _els.contactsList.prepend(contactBtn); } } catch (_) { } }; window.addEventListener('rt:message', _handlers.onRtMessage);
	_handlers.onPresence = function (ev) { try { const d = ev.detail; if (d && d.user_id) setPresenceDot(d.user_id, d.status || 'offline'); } catch (_) { } }; window.addEventListener('rt:user_presence', _handlers.onPresence);
	if (_els.chatPartnerName) { _els.chatPartnerName.style.cursor = 'pointer'; _handlers.onPartnerClick = function () { if (_state.currentPartnerId) openProfileModal(_state.currentPartnerId, _els.chatPartnerName.textContent || 'Usuario'); }; _els.chatPartnerName.addEventListener('click', _handlers.onPartnerClick); }

	// Wire call buttons (video + voice)
	try {
		const videoBtn = document.getElementById('chat-video-call');
		const voiceBtn = document.getElementById('chat-voice-call');
		if (videoBtn) {
			_handlers.onVideoCall = function () {
				if (!_state.currentPartnerId) { return modalNotification?.('Videollamada', 'Selecciona una conversación primero.', { template: 'info' }); }
				makeVideoCall(_state.currentPartnerId);
			};
			videoBtn.addEventListener('click', _handlers.onVideoCall);
		}
		if (voiceBtn) {
			_handlers.onVoiceCall = function () {
				if (!_state.currentPartnerId) { return modalNotification?.('Llamada', 'Selecciona una conversación primero.', { template: 'info' }); }
				makeVoiceCall(_state.currentPartnerId);
			};
			voiceBtn.addEventListener('click', _handlers.onVoiceCall);
		}
	} catch (_) { }

	// Support the Chat hub's "Añadir contacto" button even when PJAX swaps content
	try {
		const gotoBtn = document.getElementById('goto-search');
		if (gotoBtn) {
			_handlers.onGotoSearch = function () {
				try {
					const section = document.getElementById('contacts-section');
					if (section) {
						const inst = bootstrap.Collapse.getOrCreateInstance(section, { toggle: false });
						if (section.classList.contains('show')) { inst.hide(); return; }
						inst.show();
					}
					// Activate the 'Buscar' tab if present
					try {
						const tabLink = document.querySelector('#chat-tabs a[href="#tab-search"], [data-bs-toggle="tab"][href="#tab-search"]');
						if (tabLink) { tabLink.click(); }
					} catch (_) { }
					const input = document.getElementById('friend-search');
					if (!input) return;
					if (typeof input.scrollIntoView === 'function') {
						input.scrollIntoView({ behavior: 'smooth', block: 'center' });
					}
					try { input.focus(); } catch (_) { }
				} catch (_) { }
			};
			gotoBtn.addEventListener('click', _handlers.onGotoSearch);
		}
	} catch (_) { }

	// Integrated friends management (search + incoming/outgoing)
	(function setupFriendsArea() {
		const tokenMeta = document.querySelector('meta[name="csrf-token"]');
		const csrf = tokenMeta ? tokenMeta.getAttribute('content') : null;
		const incomingList = document.getElementById('incoming-list');
		const outgoingList = document.getElementById('outgoing-list');
		const incomingCount = document.getElementById('incoming-count');
		const outgoingCount = document.getElementById('outgoing-count');
		const friendSearch = document.getElementById('friend-search');
		const friendSearchResults = document.getElementById('friend-search-results');

		if (!incomingList && !outgoingList && !friendSearch) return;

		function escapeHtml(s) { if (!s) return ''; return String(s).replace(/[&<>"'`=\/]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;', '/': '&#x2F;', '`': '&#x60;', '=': '&#x3D;' }[c]; }); }

		async function refreshCounters() {
			try { const r = await fetch('/api/counters'); const j = await r.json(); if (!j.ok) return; document.dispatchEvent(new CustomEvent('counters:update', { detail: j })); } catch (_) { }
		}

		async function loadIncoming() {
			if (!incomingList) return;
			try {
				const r = await fetch('/friend/requests/pending');
				const j = await r.json();
				incomingList.innerHTML = '';
				if (!j.ok || !j.requests?.length) {
					incomingList.innerHTML = '<div class="list-group-item text-muted small">Sin solicitudes.</div>';
					if (incomingCount) incomingCount.textContent = '0';
					return;
				}
				if (incomingCount) incomingCount.textContent = String(j.requests.length);
				j.requests.forEach(n => {
					const div = document.createElement('div'); div.className = 'list-group-item d-flex justify-content-between align-items-center';
					div.innerHTML = `<div><div class='fw-semibold'>${escapeHtml(n.from.name)}</div><div class='text-muted small'>(nueva)</div></div><div class='btn-group btn-group-sm'><button class='btn btn-success btn-accept' data-id='${n.id}'>Aceptar</button><button class='btn btn-outline-danger btn-rechazar' data-id='${n.id}'>Rechazar</button></div>`;
					incomingList.appendChild(div);
				});
			} catch (_) { }
		}

		async function loadOutgoing() {
			if (!outgoingList) return;
			try {
				const r = await fetch('/friend/requests/outgoing');
				const j = await r.json();
				outgoingList.innerHTML = '';
				if (!j.ok || !j.requests?.length) {
					outgoingList.innerHTML = '<div class="list-group-item text-muted small">Ninguna.</div>';
					if (outgoingCount) outgoingCount.textContent = '0';
					return;
				}
				if (outgoingCount) outgoingCount.textContent = String(j.requests.length);
				j.requests.forEach(n => {
					const div = document.createElement('div'); div.className = 'list-group-item d-flex justify-content-between align-items-center';
					div.innerHTML = `<div><div class='fw-semibold'>${escapeHtml(n.to.name)}</div><div class='text-muted small'>(enviada)</div></div><span class='badge text-bg-warning'>Pendiente</span>`;
					outgoingList.appendChild(div);
				});
			} catch (_) { }
		}

		if (incomingList) {
			_handlers.onIncomingClick = async function (e) {
				const t = e.target; if (!t) return; const id = t.getAttribute('data-id'); if (!id) return;
				if (t.classList.contains('btn-accept')) {
					try {
						const r = await fetch(`/friend/request/${id}/accept`, { method: 'POST', headers: csrf ? { 'X-CSRF-TOKEN': csrf } : {} });
						const j = await r.json();
						if (j.ok) {
							window.modalNotification?.('Amistad aceptada', 'Ahora son amigos', { template: 'success' });
							loadIncoming(); refreshCounters();
							// Ensure the new friend appears in the contacts list immediately
							try { if (typeof _handlers.loadContacts === 'function') _handlers.loadContacts(); } catch (_) { }
						}
					} catch (_) { }
				}
				if (t.classList.contains('btn-reject') || t.classList.contains('btn-rechazar')) {
					try { const r = await fetch(`/friend/request/${id}/reject`, { method: 'POST', headers: csrf ? { 'X-CSRF-TOKEN': csrf } : {} }); const j = await r.json(); if (j.ok) { window.modalNotification?.('Solicitud rechazada', 'Se ha descartado la solicitud', { template: 'info' }); loadIncoming(); refreshCounters(); } } catch (_) { }
				}
			};
			incomingList.addEventListener('click', _handlers.onIncomingClick);
		}

		if (friendSearch && friendSearchResults) {
			_handlers.onFriendSearch = function () {
				const v = this.value.trim();
				if (!v) { friendSearchResults.innerHTML = ''; return; }
				(async function search(q) {
					try {
						const res = await fetch(`/friends/search?q=${encodeURIComponent(q)}`);
						const j = await res.json();
						friendSearchResults.innerHTML = '';
						if (!j.ok) return;
						if (!j.results.length) { friendSearchResults.innerHTML = '<div class="list-group-item text-muted">Sin resultados</div>'; return; }
						j.results.forEach(u => {
							const a = document.createElement('button'); a.type = 'button'; a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
							a.innerHTML = `<span><strong>${escapeHtml(u.name)}</strong><br><span class='text-muted small'>${escapeHtml(u.email)}</span></span><span class='badge text-bg-primary'>+</span>`;
							a.addEventListener('click', async () => {
								try {
									const r = await fetch(`/friend/${u.id}/request`, { method: 'POST', headers: csrf ? { 'X-CSRF-TOKEN': csrf } : {} });
									const jj = await r.json();
									if (jj.ok) { window.modalNotification?.('Solicitud enviada', u.name, { template: 'success' }); a.remove(); loadIncoming(); loadOutgoing(); refreshCounters(); }
								} catch (_) { }
							});
							friendSearchResults.appendChild(a);
						});
					} catch (_) { }
				})(v);
			};
			friendSearch.addEventListener('input', _handlers.onFriendSearch);
		}

		_handlers.onRtFriendRequest = function () { loadIncoming(); refreshCounters(); };
		_handlers.onRtFriendAccepted = function () { loadIncoming(); loadOutgoing(); refreshCounters(); try { loadContacts(); } catch (_) { } };
		window.addEventListener('rt:friend_request', _handlers.onRtFriendRequest);
		window.addEventListener('rt:friend_request_accepted', _handlers.onRtFriendAccepted);

		loadIncoming();
		loadOutgoing();
		// Initial contacts load
		loadContacts();

		// Polling periódico para reflejar cambios (p.ej. aceptación por el destinatario) sin refrescar la página
		function startFriendsPolling() {
			if (_friendsInterval) return;
			const PERIOD = 20000; // 20s
			const tick = async () => { try { await Promise.all([loadIncoming(), loadOutgoing(), refreshCounters(), loadContacts()]); } catch (_) { } };
			_friendsInterval = setInterval(tick, PERIOD);
		}
		function stopFriendsPolling() {
			try { if (_friendsInterval) { clearInterval(_friendsInterval); _friendsInterval = null; } } catch (_) { }
		}
		_handlers.startFriendsPolling = startFriendsPolling;
		_handlers.stopFriendsPolling = stopFriendsPolling;
		startFriendsPolling();
	})();

	// Expose for other handlers
	_handlers.loadContacts = loadContacts;
}

export function destroy() {
	try { _els.contactsList?.removeEventListener('click', _handlers.onContactClick); } catch (_) { }
	try { _els.chatSendForm?.removeEventListener('submit', _handlers.onSend); } catch (_) { }
	try { _els.searchInput?.removeEventListener('input', _handlers.onSearch); } catch (_) { }
	try { _els.searchInput?.removeEventListener('compositionend', _handlers.onSearch); } catch (_) { }
	try { _els.chatClose?.removeEventListener('click', _handlers.onClose); } catch (_) { }
	try { window.removeEventListener('rt:message', _handlers.onRtMessage); } catch (_) { }
	try { window.removeEventListener('rt:user_presence', _handlers.onPresence); } catch (_) { }
	try { if (_handlers.stopPresencePolling) _handlers.stopPresencePolling(); } catch (_) { }
	try { if (_handlers.onPartnerClick && _els.chatPartnerName) _els.chatPartnerName.removeEventListener('click', _handlers.onPartnerClick); } catch (_) { }
	// Friends area teardown
	try { const inc = document.getElementById('incoming-list'); if (inc && _handlers.onIncomingClick) inc.removeEventListener('click', _handlers.onIncomingClick); } catch (_) { }
	try { const fs = document.getElementById('friend-search'); if (fs && _handlers.onFriendSearch) fs.removeEventListener('input', _handlers.onFriendSearch); } catch (_) { }
	try { window.removeEventListener('rt:friend_request', _handlers.onRtFriendRequest); } catch (_) { }
	try { window.removeEventListener('rt:friend_request_accepted', _handlers.onRtFriendAccepted); } catch (_) { }
	try { const b = document.getElementById('goto-search'); if (b && _handlers.onGotoSearch) b.removeEventListener('click', _handlers.onGotoSearch); } catch (_) { }
	try { if (_handlers.stopFriendsPolling) _handlers.stopFriendsPolling(); } catch (_) { }
	try { const v = document.getElementById('chat-video-call'); if (v && _handlers.onVideoCall) v.removeEventListener('click', _handlers.onVideoCall); } catch (_) { }
	try { const a = document.getElementById('chat-voice-call'); if (a && _handlers.onVoiceCall) a.removeEventListener('click', _handlers.onVoiceCall); } catch (_) { }
	// no-op: contacts list uses event delegation, no extra teardown needed
	_state.currentPartnerId = null; _state.renderedMessageIds = new Set(); _els = {}; _handlers = {}; _presenceInterval = null;
}

async function makeVoiceCall(appUserId) {
	try {
		try { if (window.__rtcBootstrapPromise) await window.__rtcBootstrapPromise; } catch (_) { }
		if (!window.__rtcBootstrapReady) {
			return modalNotification('Llamada', 'RTC no está listo. Intenta nuevamente en unos segundos.', { template: 'warning' });
		}
		let opponentCcId = null;
		try {
			const map = window.__ccUserIdMap || {};
			if (Object.prototype.hasOwnProperty.call(map, appUserId)) opponentCcId = map[appUserId];
		} catch (_) { }
		if (!opponentCcId || shouldResolveCcId(appUserId)) {
			const resolved = await resolveCcId(appUserId);
			if (resolved) {
				opponentCcId = resolved;
				try { window.__ccUserIdMap = window.__ccUserIdMap || {}; window.__ccUserIdMap[appUserId] = resolved; } catch (_) { }
			}
		}
		if (!opponentCcId) return modalNotification('Llamada', 'No se pudo resolver el ID del destinatario para ConnectyCube.');

		const session = ConnectyCube.videochat.createNewSession([opponentCcId], ConnectyCube.videochat.CallType.AUDIO);
		_cc.currentSession = session;

		const contactBtn = document.querySelector(`.contact-item[data-user-id="${appUserId}"]`);
		const name = contactBtn?.getAttribute('data-user-name') || _els.chatPartnerName?.textContent || 'Contacto';
		RtcUI.showOutgoing(session, { nombre: name, onCancel: () => { try { session.stop({}, () => { }); } catch (_) { } RtcUI.end(); _cc.currentSession = null; } });

		try {
			const constraints = { audio: true, video: false };
			const stream = await session.getUserMedia(constraints);
			RtcUI.setLocalStream(stream);
		} catch (e) {
			return modalNotification('Atención', 'No se pudo acceder al micrófono.', { template: 'danger' });
		}

		session.call({}, (err) => { });
	} catch (e) {
		modalNotification('Llamada', 'Error iniciando la llamada.', { template: 'danger' });
	}
}