// Video call modal for appointments
// Provides openAppointmentCall({ id, otherUserId, role }) to start session, show modal, heartbeat, and finalize.
import ConnectyCube from 'connectycube';

export function openAppointmentCall(opts) {
	const { id, otherUserId, role, currentUserId, ccSession: incomingCcSession } = opts || {};
	if (!id) { return; }
	const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
	let heartbeatTimer = null;
	let modalId = `modal-appt-call-${id}`;
	let appointmentSessionInfo = null;
	let sessionStarted = false; let sessionCompleted = false;
	let metricsSubmitted = false;
	const sessionStartPerf = performance.now();
	const metrics = {
		total_retries: 0,
		degraded_sequences: 0,
		samples: [], // { bitrateKbps, lossPct, rttMs }
		presence_heartbeats_sent: 0,
		duration_seconds: null,
		avg_bitrate_kbps: null,
		avg_loss_pct: null,
		avg_rtt_ms: null
	};
	const MAX_SAMPLES = (function () { try { return parseInt(window.APPOINTMENT_MAX_METRIC_SAMPLES || '0', 10) || (window.__APPT_CONFIG_QUALITY_MAX_SAMPLES || 250); } catch (_) { return 250; } })();
	// Heartbeat multi-tab mutex
	const HEARTBEAT_LOCK_KEY = 'pg_call_heartbeat_owner';
	const tabId = (window.__pgTabId = window.__pgTabId || (Math.random().toString(36).slice(2) + '-' + Date.now()));
	const HEARTBEAT_LOCK_TTL_MS = (parseInt(window.APPOINTMENT_PING_INTERVAL_SECONDS || '45', 10) || 45) * 1500; // 1.5x interval grace

	const escapeHtml = s => !s ? '' : String(s).replace(/[&<>"'`=\/]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;', '/': '&#x2F;', '`': '&#x60;', '=': '&#x3D;' }[c]));

function renderBody(rtc) {
		const rtcInfo = (rtc && rtc.appointmentSession && rtc.appointmentSession.room_id) ? `<div class="small text-muted">Sala: ${escapeHtml(String(rtc.appointmentSession.room_id))}</div>` : '';
		return `
				<div class="mb-2">
					<strong>Sesión de cita #${escapeHtml(String(id))}</strong> <span class="badge bg-secondary ms-1">${escapeHtml(role || '')}</span>
				</div>
				${rtcInfo}
				<div id="video-call-stage" class="flex-grow-1 d-flex align-items-center justify-content-center border rounded bg-light position-relative" style="min-height:480px">
					<div class="position-absolute top-0 end-0 m-3 p-2 bg-white rounded shadow-sm" style="width:180px;height:120px;display:flex;align-items:center;justify-content:center;font-size:.75rem">Tu cámara</div>
					<div class="text-center" id="video-call-placeholder">
						<div class="spinner-border text-primary mb-3" role="status"><span class="visually-hidden">Loading...</span></div>
						<div class="fw-semibold">Conectando video...</div>
						<div class="small text-muted mt-2">Esperando a que ambos usuarios se unan.</div>
					</div>
					<div id="video-quality-indicator" class="position-absolute top-0 start-0 m-2 d-none" title="Calidad" style="width:16px;height:16px;border-radius:50%;background:#6c757d;box-shadow:0 0 0 2px #fff"></div>
					<div id="video-quality-warning" class="position-absolute bottom-0 start-0 m-2 d-none px-2 py-1 small bg-warning text-dark rounded shadow">Conexión degradada</div>
					<div id="video-quality-panel" class="position-absolute top-50 start-50 translate-middle bg-white border rounded shadow p-3 d-none" style="z-index:25;min-width:260px">
						<div class="d-flex justify-content-between align-items-center mb-2">
							<span class="fw-semibold">Diagnóstico de calidad</span>
							<button type="button" class="btn btn-sm btn-outline-secondary" data-quality-panel="close">×</button>
						</div>
						<table class="table table-sm mb-2" style="font-size:.75rem">
							<tbody>
								<tr><th>Bitrate</th><td data-q="bitrate">-</td></tr>
								<tr><th>Pérdida</th><td data-q="loss">-</td></tr>
								<tr><th>RTT</th><td data-q="rtt">-</td></tr>
								<tr><th>Resolución</th><td data-q="resolution">-</td></tr>
								<tr><th>FPS</th><td data-q="fps">-</td></tr>
							</tbody>
						</table>
						<div class="small text-muted">Se actualiza cada ~2.5s</div>
					</div>
				</div>
			<div class="mt-3 d-flex flex-wrap gap-2" id="video-call-controls">
				<button type="button" class="btn btn-sm btn-outline-primary" data-call-action="toggle-mic">Mic</button>
				<button type="button" class="btn btn-sm btn-outline-primary" data-call-action="toggle-cam">Cam</button>
				<button type="button" class="btn btn-sm btn-primary d-none" data-call-action="join">Unirse</button>
				<button type="button" class="btn btn-sm btn-outline-danger" data-call-action="end">Finalizar sesión</button>
				<button type="button" class="btn btn-sm btn-outline-secondary" data-call-action="toggle-quality" title="Mostrar/Ocultar calidad">Calidad</button>
				<button type="button" class="btn btn-sm btn-outline-secondary d-none" data-call-action="show-quality-panel" title="Panel diagnóstico">Detalles</button>
			</div>
		</div>
	`;
}

	// Obtain RTC bootstrap/config information.
	// Prefer the global bootstrap promise started by `resources/js/rtc.js` when present,
	// otherwise fall back to fetching `/rtc/bootstrap` directly.
	async function loadRtcConfig() {
		try {
			if (window.__rtcBootstrapPromise) {
				try { await window.__rtcBootstrapPromise; } catch (_) { }
			}
			// If the global variables are present, return them
			if (window.__ccConfig || window.__ccUser) {
				return { ccConfig: window.__ccConfig || null, ccUser: window.__ccUser || null, userIdMap: window.__ccUserIdMap || null, appointmentSession: appointmentSessionInfo || null };
			}
		} catch (_) { }
		// Fallback: request bootstrap from server
		try {
			const r = await fetch('/rtc/bootstrap', { headers: { 'Accept': 'application/json' }, credentials: 'include' });
			if (!r.ok) return null;
			const j = await r.json().catch(() => null);
			if (!j) return null;
			return { ccConfig: j.ccConfig || null, ccUser: j.ccUser || null, userIdMap: j.userIdMap || null, appointmentSession: j.appointmentSession || appointmentSessionInfo || null };
		} catch (_) { return null; }
	}

	async function startSession() {
		try {
			const r = await fetch(`/appointments/${encodeURIComponent(id)}/session/start`, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) } });
			if (r && r.ok) {
				try { const j = await r.json().catch(() => null); if (j && j.session) appointmentSessionInfo = j.session; } catch (_) { }
			}
			sessionStarted = true;
		} catch (_) { /* ignore */ }
	}
	async function sendHeartbeat() {
					// Only owner sends heartbeat; attempt takeover if stale
					if (!isHeartbeatOwner()) { if (canTakeoverHeartbeat()) { acquireHeartbeatLock(); } }
					if (!isHeartbeatOwner()) return;
					try {
							const resp = await fetch(`/appointments/${encodeURIComponent(id)}/session/heartbeat`, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) } });
							if (resp.status === 429) {
									// Rate limited: pause heartbeats and retry with backoff (min 8s or Retry-After header)
									const ra = parseInt(resp.headers.get('Retry-After') || '0', 10);
									const backoffMs = ra > 0 ? ra * 1000 : 8000;
									if (heartbeatTimer) { clearInterval(heartbeatTimer); heartbeatTimer = null; }
									setTimeout(() => { beginHeartbeat(); }, backoffMs);
									return; // do not count this attempt
							}
							// Count successful heartbeat
							try { metrics.presence_heartbeats_sent = (metrics.presence_heartbeats_sent || 0) + 1; } catch (_) { }
					} catch (_) { /* ignore */ }
			}

			// Heartbeat control: start/stop and cross-tab ownership using localStorage lock
			function isHeartbeatOwner() {
				try {
					const raw = localStorage.getItem(HEARTBEAT_LOCK_KEY);
					if (!raw) return false;
					const obj = JSON.parse(raw);
					if (!obj || !obj.tabId || !obj.ts) return false;
					const age = Date.now() - (obj.ts || 0);
					return String(obj.tabId) === String(tabId) && age < HEARTBEAT_LOCK_TTL_MS;
				} catch (_) { return false; }
			}

			function canTakeoverHeartbeat() {
				try {
					const raw = localStorage.getItem(HEARTBEAT_LOCK_KEY);
					if (!raw) return true;
					const obj = JSON.parse(raw);
					if (!obj || !obj.ts) return true;
					const age = Date.now() - (obj.ts || 0);
					return age > HEARTBEAT_LOCK_TTL_MS;
				} catch (_) { return true; }
			}

			function acquireHeartbeatLock() {
				try {
					const payload = { tabId: tabId, ts: Date.now() };
					localStorage.setItem(HEARTBEAT_LOCK_KEY, JSON.stringify(payload));
					return true;
				} catch (_) { return false; }
			}

			function releaseHeartbeatLock() {
				try {
					const raw = localStorage.getItem(HEARTBEAT_LOCK_KEY);
					if (!raw) return false;
					const obj = JSON.parse(raw);
					if (obj && String(obj.tabId) === String(tabId)) {
						localStorage.removeItem(HEARTBEAT_LOCK_KEY);
						return true;
					}
				} catch (_) { }
				return false;
			}

			function beginHeartbeat() {
				try {
					if (heartbeatTimer) return;
					// Try to become owner if nobody or lock stale
					if (!isHeartbeatOwner()) {
						if (canTakeoverHeartbeat()) { acquireHeartbeatLock(); }
					}
					// Start periodic timer; sendHeartbeat will no-op if not owner
					const intervalSec = parseInt(window.APPOINTMENT_PING_INTERVAL_SECONDS || '45', 10) || 45;
					// Immediate attempt
					try { sendHeartbeat(); } catch (_) { }
					heartbeatTimer = setInterval(sendHeartbeat, intervalSec * 1000);
				} catch (_) { }
			}

			function stopHeartbeat() {
				try {
					if (heartbeatTimer) { clearInterval(heartbeatTimer); heartbeatTimer = null; }
				} catch (_) { }
			}

	async function init() {
		await startSession();
		const rtc = await loadRtcConfig();
		if (rtc && appointmentSessionInfo) { try { rtc.appointmentSession = appointmentSessionInfo; } catch (_) { } }
		const body = renderBody(rtc);
		window.modalConfirm?.({
			modalId,
			title: `Videollamada cita #${id}`,
			body,
			noFooter: true,
			buttons: false,
		}, 'normal', { size: 'xl', dialogClasses: 'w-100', fullscreen: false, backdrop: 'static', keyboard: false });
		attachHandlers();
		beginHeartbeat();
		try { localStorage.setItem('pg_active_call_appt_id', String(id)); } catch (_) { }
		// Early local media preview to request camera permission even si ConnectyCube falla
		attemptEarlyPreview();
		try { await initConnectyCube(rtc); } catch (e) { console.error('ConnectyCube init error', e); }
	}

	function attemptEarlyPreview() {
		try {
			if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return;
			navigator.mediaDevices.getUserMedia({ audio: true, video: { width: { ideal: 640 }, height: { ideal: 480 } } })
				.then(stream => {
					const camBox = document.querySelector('#video-call-stage .position-absolute');
					if (camBox && !document.getElementById('cc-local-video')) {
						camBox.innerHTML = '<video id="cc-local-video" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;border-radius:4px;background:#000"></video>';
						const v = document.getElementById('cc-local-video'); if (v) { v.srcObject = stream; }
					}
					// Guardar para posible reuso rápido si init ConnectyCube después
					try { window.__earlyCallStream = stream; } catch (_) { }
				})
				.catch(err => { console.warn('Early media preview denied', err); });
		} catch (_) { }
	}

	function attachHandlers() {
		let wrap = document.getElementById('video-call-wrapper');
		const modalEl = document.getElementById(modalId);
		// If the specific wrapper isn't present, fall back to document-level delegation
		const delegateRoot = wrap || modalEl || document;
		delegateRoot.__onClick = async ev => {
			const btn = ev.target && ev.target.closest ? ev.target.closest('[data-call-action]') : null;
			if (!btn) return;
			// Ensure the clicked button is inside our modal
			if (modalEl && !modalEl.contains(btn)) return;
			// Prevent other global handlers (chat/RTC UI) from reacting to clicks inside this modal
			try { ev.preventDefault(); ev.stopImmediatePropagation(); ev.stopPropagation(); } catch (_) { }
			const act = btn.getAttribute('data-call-action');
			if (act === 'end') { manualEnd = true; await completeSession(); window.modalNotification?.('Sesión', 'Finalizada', { template: 'info' }); window.closeAllModals?.(); return; }
			if (act === 'toggle-mic') { btn.classList.toggle('btn-outline-primary'); btn.classList.toggle('btn-primary'); toggleTrack('audio'); }
			if (act === 'toggle-cam') { btn.classList.toggle('btn-outline-primary'); btn.classList.toggle('btn-primary'); toggleTrack('video'); }
			if (act === 'join') { try { await joinConference(); } catch(e){ console.error('joinConference error', e); updatePlaceholder('Error al unirse'); } }
			if (act === 'toggle-quality') { toggleQualityPreference(btn); }
			if (act === 'show-quality-panel') { toggleQualityPanel(); }
			if (act === 'cancel-reconnect') { cancelReconnection(); }
		};
		delegateRoot.addEventListener('click', delegateRoot.__onClick);
		try { console.debug('[VideoCallModal] attached click handler to', delegateRoot === document ? 'document' : (wrap ? '#video-call-wrapper' : ('#' + modalId))); } catch (_) {}
		setupPresenceListeners();
		// Clean up when modal hidden
		if (modalEl) {
			modalEl.addEventListener('hidden.bs.modal', async () => {
				stopHeartbeat();
				if (!sessionCompleted) { await completeSession(); }
				try { delegateRoot.removeEventListener('click', delegateRoot.__onClick); } catch (_) { }
				try { localStorage.removeItem('pg_active_call_appt_id'); } catch (_) { }
				releaseHeartbeatLock();
			}, { once: true });
		}
	}

	function setupPresenceListeners() {
		// Update placeholder when both participants joined based on heartbeat accumulation
		try {
			const placeholder = document.getElementById('video-call-placeholder');
			if (!placeholder) return;
			// Poll new status endpoint every 8s
			let pollTimer = setInterval(async () => {
				try {
					const r = await fetch(`/appointments/${encodeURIComponent(id)}/session/status`, { headers: { 'Accept': 'application/json' } });
					if (!r.ok) return; const j = await r.json().catch(() => null); if (!j || !j.session) return;
					const pj = j.session.professional_joined_at; const pt = j.session.patient_joined_at;
							if (pj && pt) {
								// Notify UI and show join control now that both participants are present
								placeholder.innerHTML = '<div class="fw-semibold">Conectados</div><div class="small text-muted mt-2">Ambos participantes presentes.</div>';
								try { maybeStartIfBothJoined(); } catch (_) { }
								clearInterval(pollTimer);
							} else if (pj || pt) {
								placeholder.innerHTML = '<div class="fw-semibold">Esperando al otro participante...</div><div class="small text-muted mt-2">Conexión parcial detectada.</div>';
							}
				} catch (_) { /* ignore */ }
			}, 8000);
		} catch (_) { }
		// Real-time via Echo private channels
		try {
			if (window.Echo) {
				const selfId = currentUserId || window.__authUserId || document.querySelector('meta[name="auth-user-id"]')?.getAttribute('content');
				if (selfId) {
					window.Echo.private(`appointments.${selfId}`).listen('AppointmentStarted', (e) => {
						const placeholder = document.getElementById('video-call-placeholder');
						if (placeholder) { placeholder.innerHTML = '<div class="fw-semibold">Conectados</div><div class="small text-muted mt-2">La sesión ha iniciado.</div>'; }
					});
				}
				window.Echo.channel('presence').listen('UserPresenceChanged', (e) => {
					if (!e || !e.user_id) return; if (String(e.user_id) === String(otherUserId) && e.status === 'online') {
						const placeholder = document.getElementById('video-call-placeholder');
						if (placeholder) { placeholder.innerHTML = '<div class="fw-semibold">El otro usuario está en línea...</div><div class="small text-muted mt-2">Esperando unión a la sala.</div>'; }
					}
				});
			}
		} catch (_) { }
	}

	init();
	// If the global RTC layer passed an existing ConnectyCube session for this appointment
	// (incoming call), show the incoming prompt reusing that session to avoid races
	// between remote description and ICE candidates.
	try {
		if (incomingCcSession) {
			// small delay to ensure modal elements rendered and handlers attached
			setTimeout(() => {
				try {
					if (typeof window !== 'undefined') {
						// assign to internal session pointer used by CC flow
						ccCurrentSession = incomingCcSession;
						// show the incoming call prompt using the provided session
						try { showIncomingCallPrompt(incomingCcSession); } catch (_) { }
						// try to ensure a local preview is available so accept will work smoothly
						try { setupLocalPreview(); } catch (_) { }
					}
				} catch (_) { }
			}, 250);
		}
	} catch (_) { }

	// ---- ConnectyCube integration layer ----
	let ccSession = null; let ccLocalStream = null; let ccOpponentId = null; let ccCallActive = false; let ccCurrentSession = null;
	let ccConferenceRoomId = null;
	// Ensure completeSession exists early so UI handlers can call it before wrapper is defined.
	let completeSession = async function () { /* placeholder; will be wrapped later */ };

	// Show/hide the Join button in the controls
	function showJoinButton(){
		try{
			const btn = document.querySelector('#video-call-controls [data-call-action="join"]');
			if(btn){ btn.classList.remove('d-none'); }
		}catch(_){ }
	}
	function hideJoinButton(){
		try{
			const btn = document.querySelector('#video-call-controls [data-call-action="join"]');
			if(btn){ btn.classList.add('d-none'); }
		}catch(_){ }
	}

// Helper: find a conference join function across different ConnectyCube SDK namespaces
function findConferenceJoin(){
	try{
		if(typeof ConnectyCube !== 'undefined'){
			if(ConnectyCube.videochatconference && typeof ConnectyCube.videochatconference.join === 'function'){
				return ConnectyCube.videochatconference.join.bind(ConnectyCube.videochatconference);
			}
			if(ConnectyCube.conference && typeof ConnectyCube.conference.join === 'function'){
				return ConnectyCube.conference.join.bind(ConnectyCube.conference);
			}
			if(ConnectyCube.videochat && ConnectyCube.videochat.conference && typeof ConnectyCube.videochat.conference.join === 'function'){
				return ConnectyCube.videochat.conference.join.bind(ConnectyCube.videochat.conference);
			}
			if(ConnectyCube.videochatConference && typeof ConnectyCube.videochatConference.join === 'function'){
				return ConnectyCube.videochatConference.join.bind(ConnectyCube.videochatConference);
			}
		}
	}catch(_){ }
	return null;
}

// Ask server to ensure a canonical room exists for this appointment session
async function ensureRoomFromServer(){
	try{
		const resp = await fetch(`/appointments/${encodeURIComponent(id)}/session/ensure-room`, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) } });
		if(!resp || !resp.ok) return null;
		const j = await resp.json().catch(()=>null);
		if(j && j.room_id){ ccConferenceRoomId = j.room_id; try{ console.debug('[VideoCallModal] ensured room id from server', ccConferenceRoomId); }catch(_){ } return ccConferenceRoomId; }
	}catch(e){ try{ console.warn('ensureRoomFromServer error', e); }catch(_){ } }
	return null;
}

	// Join conference flow: prefer conference.join (room id), fallback to placeCall for initiator
	async function joinConference(){
		try{
			try { console.debug('[VideoCallModal] joinConference invoked, ccLocalStream present?', !!ccLocalStream, 'ccConferenceRoomId', ccConferenceRoomId); } catch(_){}
			// Ensure local preview exists
			if(!ccLocalStream){ try { await setupLocalPreview(); } catch(_){} }
			try { updatePlaceholder('Intentando unirse...'); } catch(_){}
		// Prefer conference room if provided by backend; if missing, ask server to ensure one
		let roomId = ccConferenceRoomId || (appointmentSessionInfo && appointmentSessionInfo.room_id) || null;
		if(!roomId){
			try{ roomId = await ensureRoomFromServer(); } catch(_) { roomId = ccConferenceRoomId || null; }
		}
		const conferenceJoin = findConferenceJoin();
		if(roomId && conferenceJoin){
			try{
				await conferenceJoin(roomId, { stream: ccLocalStream });
				ccCallActive = true;
				hideJoinButton();
				updatePlaceholder('Dentro de la conferencia');
				return;
			}catch(e){
				console.warn('conference join failed, attempting server ensure-room and retry', e);
				try {
					const resp = await fetch(`/appointments/${encodeURIComponent(id)}/session/ensure-room`, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) } });
					if (resp && resp.ok) {
						const j = await resp.json().catch(() => null);
						if (j && j.room_id) {
							ccConferenceRoomId = j.room_id;
							try { await conferenceJoin(ccConferenceRoomId, { stream: ccLocalStream }); ccCallActive = true; hideJoinButton(); updatePlaceholder('Dentro de la conferencia'); return; } catch (e2) { console.warn('retry conference join failed', e2); }
						}
					}
				} catch (eEnsure) { console.warn('ensure-room request failed', eEnsure); }
			}
		}
		else if(roomId && !conferenceJoin){
			console.warn('ConnectyCube conference API not available; SDK may not support conference.join');
			updatePlaceholder('Conferencia no soportada por el cliente, intentando fallback...');
		}
			// Fallback: if we're the determinist initiator, place a normal call; otherwise show waiting message
			const myInt = parseInt(currentUserId || '0',10); const oppInt = parseInt(otherUserId || '0',10);
			if(myInt && oppInt && myInt < oppInt){
				// act as initiator using existing placeCall logic
				hideJoinButton();
				await placeCall();
				return;
			}
			updatePlaceholder('Esperando que el iniciador realice la llamada');
		}catch(e){ console.error('joinConference error', e); throw e; }
	}

	let incomingPromptTimer = null;
	let mediaStateSendDebounce = null;
	let qualityPollTimer = null; let lastVideoBytes = null; let lastTimestamp = null; let degradedCount = 0;
	let manualEnd = false; let retryCount = 0; const maxRetries = 3; let reconnecting = false; let reconnectTimer = null;
	const reconnectBackoffMs = [2000, 5000, 10000];

	function updatePlaceholder(text) {
		try {
			const stage = document.getElementById('video-call-stage');
			if (!stage) return;
			let ph = stage.querySelector('#video-call-placeholder');
			// If remote video present, show status as toast badge top-center
			const remote = document.getElementById('cc-remote-video');
			if (remote) {
				let badge = stage.querySelector('#video-status-badge');
				if (!badge) {
					badge = document.createElement('div');
					badge.id = 'video-status-badge';
					badge.className = 'position-absolute top-0 start-50 translate-middle-x mt-2 px-3 py-1 rounded bg-dark text-white small shadow';
					stage.appendChild(badge);
				}
				badge.textContent = text;
				return;
			}
			if (!ph) {
				ph = document.createElement('div');
				ph.id = 'video-call-placeholder';
				ph.className = 'text-center';
				stage.appendChild(ph);
			}
			ph.innerHTML = `<div class="fw-semibold">${text}</div><div class="small text-muted mt-2">Esperando conexión...</div>`;
		} catch (_) { }
	}

	async function initConnectyCube(rtcBootstrap) {
		if (!rtcBootstrap || !rtcBootstrap.ccConfig || !rtcBootstrap.ccUser) { return; }
		// Patch RTCPeerConnection to buffer addIceCandidate calls until remoteDescription is set.
		// This prevents "Failed to execute 'addIceCandidate': The remote description was null" errors
		// by queuing early candidates and draining them once setRemoteDescription resolves.
		try {
			if (typeof RTCPeerConnection !== 'undefined' && !RTCPeerConnection.prototype.__pg_ice_buffer_patched) {
				const _origAddIce = RTCPeerConnection.prototype.addIceCandidate;
				const _origSetRemote = RTCPeerConnection.prototype.setRemoteDescription;
				const _pcQueue = new WeakMap();

				RTCPeerConnection.prototype.addIceCandidate = function (candidate) {
					try {
						// If remoteDescription is not set yet, queue candidate instead of throwing
						if (!this.remoteDescription || !this.remoteDescription.type) {
							let q = _pcQueue.get(this);
							if (!q) { q = []; _pcQueue.set(this, q); }
							q.push(candidate);
							// Resolve immediately so callers don't await a rejection
							return Promise.resolve();
						}
					} catch (_) { }
					return _origAddIce.apply(this, arguments);
				};

				RTCPeerConnection.prototype.setRemoteDescription = function (desc) {
					const result = _origSetRemote.apply(this, arguments);
					try {
						if (result && typeof result.then === 'function') {
							return result.then(r => {
								try {
									const q = _pcQueue.get(this) || [];
									for (const c of q) {
										try { _origAddIce.apply(this, [c]); } catch (_) { }
									}
									_pcQueue.delete(this);
								} catch (_) { }
								return r;
							}).catch(e => { throw e; });
						}
					} catch (_) { }
					return result;
				};

				RTCPeerConnection.prototype.__pg_ice_buffer_patched = true;
				try { console.debug('[VideoCallModal] Patched RTCPeerConnection to buffer ICE candidates.'); } catch (_) { }
			}
		} catch (_) { }
		const creds = { appId: rtcBootstrap.ccConfig.appId, authKey: rtcBootstrap.ccConfig.authKey, authSecret: rtcBootstrap.ccConfig.authSecret };
		// Normalize endpoints to host only (e.g., 'https//api...' -> 'api-eu.connectycube.com')
		const normalizeEndpoint = (u) => {
			if (typeof u !== 'string') return '';
			let s = u.trim();
			if (!s) return '';
			// Remove any leading protocols (https/http/ws/wss) including malformed 'https//' variants
			const protoPattern = /^(?:https?:\/\/|wss?:\/\/|https?\/\/|wss?\/\/)+/i;
			while (protoPattern.test(s)) { s = s.replace(protoPattern, ''); }
			// Remove any leading slashes left
			s = s.replace(/^\/+/, '');
			// Take host only (strip any path/query)
			const host = s.split('/')[0].trim();
			return host.replace("-eu", "");
		};
		const rawApi = rtcBootstrap.ccConfig.apiEndpoint;
		const rawChat = rtcBootstrap.ccConfig.chatEndpoint;
		const apiEp = normalizeEndpoint(rawApi);
		const chatEp = normalizeEndpoint(rawChat);
		try { console.debug('[RTC endpoints] raw:', rawApi, rawChat, 'normalized:', apiEp, chatEp); } catch (_) { }
		// ConnectyCube expects hostnames here; protocol is configured separately
		const cfg = { protocol: 'https', endpoints: { api: apiEp, chat: chatEp }, debug: { mode: 0 } };
		try { ConnectyCube.init(creds, cfg); } catch (e) { console.error('CC init base error', e); }
		ccSession = await ConnectyCube.createSession({ login: rtcBootstrap.ccUser.login, password: rtcBootstrap.ccUser.password });
		try { await ConnectyCube.chat.connect({ userId: ccSession.user.id, password: rtcBootstrap.ccUser.password }); } catch (e) { console.error('CC chat connect fail', e); }
		// Persist discovered CC user id
		try { fetch('/rtc/sync', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf || '' }, body: JSON.stringify({ cc_user_id: ccSession.user.id, cc_login: rtcBootstrap.ccUser.login }) }); } catch (_) { }
		// If backend provided appointment session info, capture room id for conference semantics
		try { ccConferenceRoomId = rtcBootstrap && rtcBootstrap.appointmentSession ? rtcBootstrap.appointmentSession.room_id : null; } catch (_) { ccConferenceRoomId = null; }
		if (ccConferenceRoomId) { try { console.debug('Using appointment room id:', ccConferenceRoomId); } catch (_) { } }
		// Map opponent
		ccOpponentId = (rtcBootstrap.userIdMap && rtcBootstrap.userIdMap[String(otherUserId)]) ? rtcBootstrap.userIdMap[String(otherUserId)] : otherUserId;
		setupCcListeners();
		await setupLocalPreview();
		// If server provided an appointment room id and ConnectyCube supports conference API, try to join it.
		try {
			// Ensure we have a canonical room id before attempting to join so both sides enter same meeting
			if(!ccConferenceRoomId){ try { await ensureRoomFromServer(); } catch(_) {} }
			const conferenceJoin = findConferenceJoin();
			if (ccConferenceRoomId && conferenceJoin) {
				try {
					await conferenceJoin(ccConferenceRoomId, { stream: ccLocalStream });
					updatePlaceholder('Uniendo conferencia...');
				} catch (e) {
					console.warn('conference join attempt failed, calling ensure-room and retrying', e);
					try {
						const resp = await fetch(`/appointments/${encodeURIComponent(id)}/session/ensure-room`, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) } });
						if (resp && resp.ok) {
							const j = await resp.json().catch(() => null);
							if (j && j.room_id) {
								ccConferenceRoomId = j.room_id;
								try { await conferenceJoin(ccConferenceRoomId, { stream: ccLocalStream }); updatePlaceholder('Uniendo conferencia...'); }
								catch (e2) { console.warn('retry conference join failed', e2); }
							}
						}
					} catch (eEnsure) { console.warn('ensure-room request failed', eEnsure); }
				}
			} else if (ccConferenceRoomId && !conferenceJoin) {
				console.warn('ConnectyCube conference API not available; SDK may not support conference.join');
				updatePlaceholder('Conferencia no soportada por el cliente, intentando fallback...');
			}
		} catch (_) { }
		// After ConnectyCube init and potential conference join, attempt to start the call
		// only if the backend already reports both participants present.
		try { maybeStartIfBothJoined(); } catch (_) { }
	}

	async function setupLocalPreview() {
		try {
			const constraints = { audio: true, video: { width: { ideal: 640 }, height: { ideal: 480 } } };
			ccLocalStream = await navigator.mediaDevices.getUserMedia(constraints);
			const camBox = document.querySelector('#video-call-stage .position-absolute');
			if (camBox) {
				camBox.innerHTML = '<video id="cc-local-video" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;border-radius:4px;background:#000"></video>';
				const v = document.getElementById('cc-local-video'); if (v) { v.srcObject = ccLocalStream; }
			}
		} catch (e) {
			const camBox = document.querySelector('#video-call-stage .position-absolute');
			if (camBox) { camBox.innerHTML = '<div class="text-danger small">Permiso cámara/mic denegado</div>'; }
			console.error('Local media error', e);
		}
	}

	function setupCcListeners() {
		ConnectyCube.videochat.onCallListener = (session/*, extension*/) => {
			ccCurrentSession = session;
			// Only show prompt if opponent matches expected (avoid stray calls)
			if (ccOpponentId && session.initiatorID && parseInt(session.initiatorID, 10) !== parseInt(ccOpponentId, 10)) {
				updatePlaceholder('Llamada desconocida');
				return;
			}
			updatePlaceholder('Llamada entrante...');
			showIncomingCallPrompt(session);
		};
		ConnectyCube.videochat.onAcceptListener = (session, userId/*, extension*/) => {
			updatePlaceholder('Conectando flujo remoto...');
		};
		ConnectyCube.videochat.onRemoteStreamListener = (session, userId, stream) => {
			attachRemoteStream(stream);
		};
		ConnectyCube.videochat.onUserNotAnswerListener = (session, userId) => {
			updatePlaceholder('El usuario no responde');
			finalizeCcCall();
		};
		ConnectyCube.videochat.onRejectCallListener = () => { updatePlaceholder('Llamada rechazada'); finalizeCcCall(); };
		ConnectyCube.videochat.onStopCallListener = () => { updatePlaceholder('Llamada finalizada'); finalizeCcCall(); };
		ConnectyCube.videochat.onSessionConnectionStateChangedListener = (session, userId, state) => {
			if (state === 'failed') { updatePlaceholder('Conexión fallida'); }
			if ((state === 'failed' || state === 'disconnected') && !manualEnd && !sessionCompleted) {
				scheduleReconnect('estado ' + state);
			}
		};
		// Media state signalling
		ConnectyCube.videochat.onMessageListener = (userId, message) => {
			try {
				const ext = message?.extension || message || {};
				if (ext.type === 'media_state') {
					updateRemoteMediaIndicators(ext);
				}
			} catch (_) { }
		};
	}

	function decideInitiator() {
		if (!ccOpponentId || !ccLocalStream) { return; }
		const myInt = parseInt(currentUserId || '0', 10);
		const oppInt = parseInt(otherUserId || '0', 10);
		if (myInt === 0 || oppInt === 0) { updatePlaceholder('IDs inválidos para llamada'); return; }
		// Keep deterministic initiator logic, but do NOT place the call until the server reports both participants joined.
		if (myInt < oppInt) { updatePlaceholder('Preparado para iniciar llamada (iniciador)'); }
		else { updatePlaceholder('Esperando llamada del otro usuario...'); }
	}

	// Attempt to start the call only when the backend reports both participants have joined the appointment session.
	async function maybeStartIfBothJoined() {
		try {
			if (ccCallActive || manualEnd || reconnecting) return;
			// Prefer server-provided appointmentSessionInfo if available
			let both = false;
			try {
				if (appointmentSessionInfo && appointmentSessionInfo.professional_joined_at && appointmentSessionInfo.patient_joined_at) { both = true; }
			} catch (_) { }
			if (!both) {
				// Query status endpoint for a canonical answer
				try {
					const r = await fetch(`/appointments/${encodeURIComponent(id)}/session/status`, { headers: { 'Accept': 'application/json' } });
					if (r && r.ok) { const j = await r.json().catch(() => null); if (j && j.session && j.session.professional_joined_at && j.session.patient_joined_at) { both = true; } }
				} catch (_) { }
			}
			if (!both) return;
			// Both present — decide who places the call deterministically
			const myInt = parseInt(currentUserId || '0', 10); const oppInt = parseInt(otherUserId || '0', 10);
			if (myInt === 0 || oppInt === 0) return;
			// Instead of auto-placing a call, show a Join button so either participant can opt-in.
			try { showJoinButton(); } catch(_){}
			updatePlaceholder('Ambos presentes — pulsa "Unirse" para entrar a la sala');
		} catch (_) { }
	}

	async function placeCall() {
			try {
				hideJoinButton();
			const opponents = [ccOpponentId];
			const type = ConnectyCube.videochat.CallType.VIDEO;
			ccCurrentSession = ConnectyCube.videochat.createNewSession(opponents, type, {});
			try {
				const constraints = { audio: true, video: { width: { ideal: 1280 }, height: { ideal: 720 } } };
				const stream = await ccCurrentSession.getUserMedia(constraints);
				// Keep local reference for UI and signalling
				try { ccLocalStream = stream; } catch (_) { }
			} catch (_) { /* ignore media access error, session.call may still fail */ }
			try {
				// Call via session instance (SDK v4.x pattern)
				// Attach markers in the signalling so receivers can detect this is an appointment call.
				// Use both `userInfo` and `extension` to maximize compatibility across SDK variants.
				const payload = { appointment_id: String(id), module: 'appointment' };
				const callParams = { userInfo: payload, extension: payload };
				// Try to proactively send a signalling message with the appointment metadata as an extra layer
				try {
					if (typeof ConnectyCube.videochat !== 'undefined' && typeof ConnectyCube.videochat.sendMessage === 'function') {
						try { ConnectyCube.videochat.sendMessage(ccCurrentSession, ccOpponentId, { type: 'appointment', appointment_id: String(id), module: 'appointment' }); } catch (_) { }
					}
				} catch (_) { }
				ccCurrentSession.call(callParams, (err) => {
					if (err) { console.warn('call callback error', err); }
				});
			} catch (errCall) { console.error('placeCall call error', errCall); }
			ccCallActive = true;
			updatePlaceholder('Llamando...');
		} catch (e) { console.error('placeCall error', e); updatePlaceholder('Error al iniciar llamada'); }
	}

	async function tryAccept(session) {
		try {
				hideJoinButton();
			try {
				const constraints = { audio: true, video: { width: { ideal: 1280 }, height: { ideal: 720 } } };
				const s = await session.getUserMedia(constraints);
				try { ccLocalStream = s; } catch (_) { }
			} catch (_) { }
			try {
				// Use session.accept per SDK pattern
				session.accept({}, (err) => { if (err) console.warn('accept cb err', err); });
			} catch (eAccept) { console.error('accept error', eAccept); }
			ccCallActive = true;
			updatePlaceholder('Aceptando llamada...');
			hideIncomingCallPrompt();
		} catch (e) { console.error('accept error', e); updatePlaceholder('Error al aceptar'); }
	}

	function attachRemoteStream(stream) {
		let remoteEl = document.getElementById('cc-remote-video');
		const stage = document.getElementById('video-call-stage');
		if (!remoteEl && stage) {
			stage.querySelector('#video-call-placeholder')?.remove();
			remoteEl = document.createElement('video');
			remoteEl.id = 'cc-remote-video'; remoteEl.autoplay = true; remoteEl.playsInline = true;
			remoteEl.style.cssText = 'width:100%;height:100%;object-fit:cover;background:#000;border-radius:6px;';
			stage.appendChild(remoteEl);
			// Add remote media indicators container
			let indicators = document.getElementById('remote-media-indicators');
			if (!indicators) {
				indicators = document.createElement('div');
				indicators.id = 'remote-media-indicators';
				indicators.className = 'position-absolute bottom-0 end-0 m-2 p-1 rounded bg-dark bg-opacity-75 text-white small';
				indicators.innerHTML = '<span data-remote-mic>Mic ?</span> | <span data-remote-cam>Cam ?</span>';
				stage.appendChild(indicators);
			}
		}
		if (remoteEl) { remoteEl.srcObject = stream; updatePlaceholder('Conectados'); hideJoinButton(); }
		// Successful reattachment resets reconnection state
		if (reconnecting) { reconnecting = false; retryCount = 0; hideReconnectUi(); }
	}

	function toggleTrack(kind) {
		try { if (!ccLocalStream) return; ccLocalStream.getTracks().filter(t => t.kind === kind).forEach(t => { t.enabled = !t.enabled; }); } catch (_) { }
		sendMediaState();
	}

	function finalizeCcCall() {
		ccCallActive = false;
		// Stop local tracks
		try { ccLocalStream && ccLocalStream.getTracks().forEach(t => { try { t.stop(); } catch (_) { } }); } catch (_) { }
		try { ConnectyCube.videochat.stop(); } catch (_) { }
		cleanupUiAfterCall();
		stopQualityMonitoring();
		cancelReconnection();
		releaseHeartbeatLock();
	}

	function cleanupUiAfterCall() {
		try { localStorage.removeItem('pg_active_call_appt_id'); } catch (_) { }
		const stage = document.getElementById('video-call-stage');
		if (stage) {
			const remote = document.getElementById('cc-remote-video');
			if (remote) { try { remote.srcObject = null; } catch (_) { } remote.remove(); }
			const indicators = document.getElementById('remote-media-indicators');
			if (indicators) { indicators.remove(); }
			updatePlaceholder('Sesión finalizada');
		}
		hideIncomingCallPrompt();
	}

	function showIncomingCallPrompt(session) {
		hideIncomingCallPrompt();
		hideJoinButton();
		const stage = document.getElementById('video-call-stage'); if (!stage) return;
		const overlay = document.createElement('div');
		overlay.id = 'incoming-call-overlay';
		overlay.className = 'position-absolute top-50 start-50 translate-middle bg-white border rounded shadow p-3 d-flex flex-column align-items-center gap-2';
		overlay.style.zIndex = '30';
		overlay.innerHTML = `
      <div class="fw-semibold">Llamada entrante</div>
      <div class="small text-muted">¿Aceptar la videollamada?</div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-success" data-incoming-action="accept">Aceptar</button>
        <button type="button" class="btn btn-sm btn-outline-danger" data-incoming-action="reject">Rechazar</button>
      </div>
      <div class="small text-muted" id="incoming-call-timer">30s</div>
    `;
		stage.appendChild(overlay);
		let remaining = 30;
		incomingPromptTimer = setInterval(() => {
			remaining--; const t = overlay.querySelector('#incoming-call-timer'); if (t) { t.textContent = remaining + 's'; }
			if (remaining <= 0) {
				clearInterval(incomingPromptTimer); incomingPromptTimer = null;
				try { ConnectyCube.videochat.reject(session, {}); } catch (_) { }
				finalizeCcCall(); updatePlaceholder('Tiempo agotado'); hideIncomingCallPrompt();
			}
		}, 1000);
		overlay.addEventListener('click', ev => {
			const btn = ev.target.closest('[data-incoming-action]'); if (!btn) return;
			const act = btn.getAttribute('data-incoming-action');
			if (act === 'accept') { tryAccept(session); }
			if (act === 'reject') {
				try { ConnectyCube.videochat.reject(session, {}); } catch (_) { }
				finalizeCcCall(); updatePlaceholder('Llamada rechazada'); hideIncomingCallPrompt();
			}
		}, { once: false });
	}

	function hideIncomingCallPrompt() {
		try { if (incomingPromptTimer) { clearInterval(incomingPromptTimer); incomingPromptTimer = null; } } catch (_) { }
		const ov = document.getElementById('incoming-call-overlay'); if (ov) { try { ov.remove(); } catch (_) { } }
	}

	// ---- Quality Monitoring ----
	function toggleQualityPreference(btn) {
		const enabled = !btn.classList.contains('btn-primary');
		if (enabled) { btn.classList.remove('btn-outline-secondary'); btn.classList.add('btn-primary'); startQualityMonitoring(); }
		else { btn.classList.remove('btn-primary'); btn.classList.add('btn-outline-secondary'); stopQualityMonitoring(); }
		try { localStorage.setItem('pg_call_show_quality', enabled ? '1' : '0'); } catch (_) { }
		const detailsBtn = document.querySelector('[data-call-action="show-quality-panel"]');
		if (detailsBtn) { detailsBtn.classList.toggle('d-none', !enabled); }
	}

	function maybeAutoEnableQuality() {
		try { const pref = localStorage.getItem('pg_call_show_quality'); if (pref === '1') { const btn = document.querySelector('[data-call-action="toggle-quality"]'); if (btn) { toggleQualityPreference(btn); } } } catch (_) { }
	}

	function startQualityMonitoring() {
		if (qualityPollTimer) { return; }
		const indicator = document.getElementById('video-quality-indicator'); if (indicator) { indicator.classList.remove('d-none'); }
		qualityPollTimer = setInterval(collectQualityStats, 2500);
	}
	function stopQualityMonitoring() {
		if (qualityPollTimer) { clearInterval(qualityPollTimer); qualityPollTimer = null; }
		const indicator = document.getElementById('video-quality-indicator'); if (indicator) { indicator.classList.add('d-none'); }
		hideQualityPanel(); hideDegradedWarning(); lastVideoBytes = null; lastTimestamp = null; degradedCount = 0;
	}

	function collectQualityStats() {
		if (!ccCurrentSession || !ccCallActive) { return; }
		try {
			// Attempt to retrieve peer connection for opponent
			let pc = null;
			const pid = ccOpponentId;
			if (ccCurrentSession.peerConnections) { pc = ccCurrentSession.peerConnections[pid]; }
			if (!pc) { pc = ccCurrentSession.pc || null; }
			if (!pc) { return; }
			pc.getStats(null).then(stats => {
				let videoInbound = null; let candidatePair = null; let fps = null; let width = null; let height = null;
				stats.forEach(r => {
					if (r.type === 'inbound-rtp' && r.kind === 'video' && !r.isRemote) { videoInbound = r; }
					if (r.type === 'candidate-pair' && r.state === 'succeeded') { candidatePair = r; }
					if (r.type === 'track' && r.kind === 'video') { fps = r.framesPerSecond; width = r.frameWidth; height = r.frameHeight; }
				});
				if (!videoInbound) return;
				const now = performance.now();
				if (lastVideoBytes == null) { lastVideoBytes = videoInbound.bytesReceived; lastTimestamp = now; return; }
				const deltaBytes = videoInbound.bytesReceived - lastVideoBytes;
				const deltaTime = (now - lastTimestamp) / 1000; // seconds
				lastVideoBytes = videoInbound.bytesReceived; lastTimestamp = now;
				const bitrateKbps = deltaTime > 0 ? (deltaBytes * 8 / 1000) / deltaTime : 0;
				const packetsLost = videoInbound.packetsLost || 0;
				const packetsReceived = videoInbound.packetsReceived || 0;
				const totalPackets = packetsLost + packetsReceived;
				const lossRatio = totalPackets > 0 ? packetsLost / totalPackets : 0;
				const rtt = candidatePair?.currentRoundTripTime || 0;
				const metrics = { bitrateKbps, lossRatio, rtt, width, height, fps };
				updateQualityIndicator(metrics);
				updateQualityPanel(metrics);
				// Store sample (limit to 400 to bound payload)
				try {
					if (metrics && metrics.bitrateKbps >= 0) {
						if (metrics.samples && metrics.samples.length) { } // defensive
					}
					if (Array.isArray(metrics.samples)) { }
				} catch (_) { }
				const sample = { bitrateKbps, lossPct: lossRatio * 100, rttMs: rtt * 1000 };
				metrics.samples.push(sample);
				if (metrics.samples.length > MAX_SAMPLES) { metrics.samples.shift(); }
			}).catch(() => { });
		} catch (_) { }
	}

	function classifyQuality(m) {
		if (m.bitrateKbps > 1500 && m.lossRatio < 0.02 && m.rtt < 0.15) return 'excellent';
		if (m.bitrateKbps > 600 && m.lossRatio < 0.08 && m.rtt < 0.3) return 'acceptable';
		return 'degraded';
	}

	function updateQualityIndicator(metrics) {
		const indicator = document.getElementById('video-quality-indicator'); if (!indicator) return;
		const state = classifyQuality(metrics);
		let color = '#6c757d';
		if (state === 'excellent') color = '#28a745';
		else if (state === 'acceptable') color = '#ffc107';
		else color = '#dc3545';
		indicator.style.background = color;
		indicator.title = `Bitrate: ${metrics.bitrateKbps.toFixed(0)} kbps\nPérdida: ${(metrics.lossRatio * 100).toFixed(1)}%\nRTT: ${(metrics.rtt * 1000).toFixed(0)} ms\nEstado: ${state}`;
		if (state === 'degraded') { degradedCount++; if (degradedCount === 3) { metrics.degraded_sequences++; } if (degradedCount >= 3) { showDegradedWarning(); } }
		else { degradedCount = 0; hideDegradedWarning(); }
	}

	function showDegradedWarning() {
		const w = document.getElementById('video-quality-warning'); if (!w) return; w.classList.remove('d-none');
	}
	function hideDegradedWarning() {
		const w = document.getElementById('video-quality-warning'); if (!w) return; w.classList.add('d-none');
	}

	function toggleQualityPanel() {
		const panel = document.getElementById('video-quality-panel'); if (!panel) return;
		panel.classList.toggle('d-none');
	}
	function hideQualityPanel() {
		const panel = document.getElementById('video-quality-panel'); if (panel) { panel.classList.add('d-none'); }
	}

	function updateQualityPanel(m) {
		const panel = document.getElementById('video-quality-panel'); if (!panel || panel.classList.contains('d-none')) return;
		const set = (k, v) => { const el = panel.querySelector(`[data-q="${k}"]`); if (el) { el.textContent = v; } };
		set('bitrate', m.bitrateKbps.toFixed(0) + ' kbps');
		set('loss', (m.lossRatio * 100).toFixed(1) + '%');
		set('rtt', (m.rtt * 1000).toFixed(0) + ' ms');
		set('resolution', (m.width || '?') + 'x' + (m.height || '?'));
		set('fps', m.fps != null ? m.fps : '-');
	}

	// ---- Reconnection logic ----
	function scheduleReconnect(reason) {
		if (reconnecting || manualEnd || sessionCompleted) { return; }
		if (retryCount >= maxRetries) { updatePlaceholder('Reconexión agotada'); return; }
		reconnecting = true;
		showReconnectUi(reason);
		const delay = reconnectBackoffMs[Math.min(retryCount, reconnectBackoffMs.length - 1)];
		if (reconnectTimer) { clearTimeout(reconnectTimer); }
		reconnectTimer = setTimeout(() => { attemptReconnect(); }, delay);
	}

	function attemptReconnect() {
		if (manualEnd || sessionCompleted) { return; }
		retryCount++;
		metrics.total_retries = retryCount;
		updateReconnectAttemptText();
		// Tear down existing session state (without marking manual end)
		try { ConnectyCube.videochat.stop(); } catch (_) { }
		ccCallActive = false;
		// Recreate call: if we were initiator originally (myInt < oppInt) placeCall() else wait small window then fallback to placeCall
		const myInt = parseInt(currentUserId || '0', 10); const oppInt = parseInt(otherUserId || '0', 10);
		if (myInt < oppInt) {
			// Attempt to start only when backend reports both participants present
			try { maybeStartIfBothJoined(); } catch (_) { }
		}
		else {
			updatePlaceholder('Esperando nueva llamada...');
			setTimeout(() => { if (!ccCallActive && reconnecting) { try { maybeStartIfBothJoined(); } catch (_) { } } }, 4000);
		}
		// If this attempt fails further states will trigger another scheduleReconnect
		if (retryCount >= maxRetries) { /* final attempt - if fails, placeholder will show failure */ }
	}

	function cancelReconnection() {
		reconnecting = false; if (reconnectTimer) { clearTimeout(reconnectTimer); reconnectTimer = null; }
		hideReconnectUi();
	}

	function showReconnectUi(reason) {
		const stage = document.getElementById('video-call-stage'); if (!stage) return;
		let box = document.getElementById('reconnect-box');
		if (!box) {
			box = document.createElement('div'); box.id = 'reconnect-box';
			box.className = 'position-absolute top-50 start-50 translate-middle bg-white border rounded shadow p-2 d-flex flex-column align-items-center gap-1';
			box.style.zIndex = '35';
			box.innerHTML = `
        <div class="small fw-semibold">Reconectando...</div>
        <div class="small" data-r="attempt">Razón: ${reason}</div>
        <div class="small" data-r="count">Intento 0/${maxRetries}</div>
        <button type="button" class="btn btn-sm btn-outline-danger" data-call-action="cancel-reconnect">Cancelar</button>
      `;
			stage.appendChild(box);
		} else {
			const r = box.querySelector('[data-r="attempt"]'); if (r) { r.textContent = 'Razón: ' + reason; }
		}
		updateReconnectAttemptText();
	}

	function updateReconnectAttemptText() {
		const box = document.getElementById('reconnect-box'); if (!box) return;
		const c = box.querySelector('[data-r="count"]'); if (c) { c.textContent = `Intento ${retryCount}/${maxRetries}`; }
	}

	function hideReconnectUi() {
		const box = document.getElementById('reconnect-box'); if (box) { try { box.remove(); } catch (_) { } }
	}

	function sendMediaState() {
		if (!ccCurrentSession || !ccCallActive) return;
		if (mediaStateSendDebounce) { clearTimeout(mediaStateSendDebounce); }
		mediaStateSendDebounce = setTimeout(() => {
			try {
				const audioEnabled = ccLocalStream ? ccLocalStream.getAudioTracks().some(t => t.enabled) : false;
				const videoEnabled = ccLocalStream ? ccLocalStream.getVideoTracks().some(t => t.enabled) : false;
				// Attempt to send message via videochat signalling
				try { ConnectyCube.videochat.sendMessage(ccCurrentSession, ccOpponentId, { type: 'media_state', audioEnabled, videoEnabled }); } catch (e) { /* fallback ignored */ }
			} catch (_) { }
		}, 120);
	}

	function updateRemoteMediaIndicators(state) {
		try {
			const micEl = document.querySelector('#remote-media-indicators [data-remote-mic]');
			const camEl = document.querySelector('#remote-media-indicators [data-remote-cam]');
			if (micEl) { micEl.textContent = state.audioEnabled ? 'Mic On' : 'Mic Off'; }
			if (camEl) { camEl.textContent = state.videoEnabled ? 'Cam On' : 'Cam Off'; }
		} catch (_) { }
	}

	// Wrap completeSession to ensure call teardown. Guard if completeSession wasn't defined.
	const _origComplete = (typeof completeSession === 'function') ? completeSession : async function () {};
	completeSession = async function () {
		// Compute and submit metrics summary once before completion
		if (!metricsSubmitted) {
			try {
				metrics.duration_seconds = Math.round((performance.now() - sessionStartPerf) / 1000);
				const samples = metrics.samples;
				const n = samples.length || 0;
				if (n > 0) {
					let sumBit = 0, sumLoss = 0, sumRtt = 0;
					for (const s of samples) { sumBit += (s.bitrateKbps || 0); sumLoss += (s.lossPct || 0); sumRtt += (s.rttMs || 0); }
					metrics.avg_bitrate_kbps = +(sumBit / n).toFixed(1);
					metrics.avg_loss_pct = +(sumLoss / n).toFixed(2);
					metrics.avg_rtt_ms = +(sumRtt / n).toFixed(1);
				}
				const payload = {
					total_retries: metrics.total_retries,
					degraded_sequences: metrics.degraded_sequences,
					avg_bitrate_kbps: metrics.avg_bitrate_kbps,
					avg_loss_pct: metrics.avg_loss_pct,
					avg_rtt_ms: metrics.avg_rtt_ms,
					duration_seconds: metrics.duration_seconds,
					presence_heartbeats_sent: metrics.presence_heartbeats_sent,
					samples: metrics.samples
				};
				const url = `/appointments/${encodeURIComponent(id)}/session/metrics`;
				const body = JSON.stringify(payload);
				let sent = false;
				try {
					const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) }, body });
					sent = r.ok;
				} catch (e) { sent = false; }
				if (!sent && navigator.sendBeacon) { try { const blob = new Blob([body], { type: 'application/json' }); navigator.sendBeacon(url, blob); } catch (_) { } }
				metricsSubmitted = true;
			} catch (e) { /* ignore metric errors */ }
		}
		finalizeCcCall();
		await _origComplete();
		releaseHeartbeatLock();
	};
}




// Auto reopen helper: call on page init
export function autoOpenOngoingAppointmentCall() {
	try {
		const id = localStorage.getItem('pg_active_call_appt_id');
		if (!id) return;
		// Ensure appointment still visible and within time window if we have start/end data
		const wrap = document.getElementById('pg-next-appt');
		if (!wrap) return;
		const startIso = wrap.getAttribute('data-start');
		const endIso = wrap.getAttribute('data-end');
		const now = Date.now();
		let okWindow = true;
		try {
			if (startIso) { const s = new Date(startIso).getTime(); if (!isNaN(s) && now < s - 15 * 60 * 1000) okWindow = false; }
			if (endIso) { const e = new Date(endIso).getTime(); if (!isNaN(e) && now > e + 10 * 60 * 1000) okWindow = false; }
		} catch (_) { }
		if (!okWindow) { localStorage.removeItem('pg_active_call_appt_id'); return; }
		const role = wrap.getAttribute('data-patient-id') ? 'profesional' : 'paciente';
		const otherUserId = role === 'profesional' ? wrap.getAttribute('data-patient-id') : wrap.getAttribute('data-professional-id');
		openAppointmentCall({ id, otherUserId, role });
	} catch (_) { }
}
// Optional global assignment for legacy callers
try { window.openAppointmentCall = openAppointmentCall; } catch (_) { }

// Listen for global RTC appointment events emitted by `rtc.js` and open the modal when requested.
try {
	window.addEventListener('rtc:incoming_appointment_call', (ev) => {
		try {
			const d = ev && ev.detail ? ev.detail : null;
			if (!d) return;
			// Normalise fields expected by openAppointmentCall
			const opts = {
				id: d.id || d.appointment_id || null,
				otherUserId: d.otherUserId || d.other_user_id || d.caller || null,
				role: d.role || undefined,
				currentUserId: d.currentUserId || window.__authUserId || null,
				ccSession: d.ccSession || null
			};
			if (!opts.id) return;
			try { openAppointmentCall(opts); } catch (_) { }
		} catch (_) { }
	});
} catch (_) { }
