// Video call modal for appointments
// Provides openAppointmentCall({ id, otherUserId, role }) to start session, show modal, heartbeat, and finalize.
import createSignalingClient from './webrtcSignaling';

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
					<!-- Quality details are shown in a modal via modalConfirm (Detalles button) -->
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
			if (act === 'end') {
				// Request remote confirmation before ending the session.
				try {
					// mark that user requested an end; do not immediately teardown
					manualEnd = true;
					// If we have signaling, ensure connection and room membership, then send a request to peers
					if (pgSignaler && typeof pgSignaler.sendMessage === 'function') {
						try {
							// Ensure the signaller is connected
							try { await pgSignaler.connect(); } catch (_) { }
							// Ensure presence in the appointment room so broadcast reaches peers
							const roomToJoin = pgSignalingRoom || ccConferenceRoomId || (appointmentSessionInfo && appointmentSessionInfo.room_id) || null;
							if (roomToJoin && typeof pgSignaler.presenceJoin === 'function') {
								try { pgSignaler.presenceJoin(roomToJoin); } catch (_) { }
							}
										// Attempt to send the request via signaling first; if it fails, fall back to server POST
										try {
											console.debug('[VideoCallModal] attempting to send request_end_session via signaling (awaiting ACK)');
											let ackResp = { ok: false };
											try {
												if (pgSignaler && typeof pgSignaler.sendMessage === 'function') {
													ackResp = await pgSignaler.sendMessage(null, { type: 'request_end_session', appointmentId: id, from: currentUserId || null }, { expectAck: true, timeoutMs: 4000 });
												}
											} catch (e) {
												console.warn('request_end_session signaling send/ack failed', e);
											}
											// If ACKed via signaling, we can rely on it. Otherwise fall back to server POST.
											if (ackResp && ackResp.ok) {
												window.modalNotification?.('Solicitud enviada', 'Se ha solicitado finalizar la sesión. Esperando respuesta del otro participante.', { template: 'info', timeout: 5000 });
												showEndRequestWaitingModal();
											} else {
												// No ACK received — use server broadcast fallback
												window.modalNotification?.('Solicitud enviada', 'Se ha solicitado finalizar la sesión. Intentando entrega vía servidor.', { template: 'info', timeout: 5000 });
												showEndRequestWaitingModal();
												try {
													console.debug('[VideoCallModal] falling back to server POST /session/request-end');
													await fetch(`/appointments/${encodeURIComponent(id)}/session/request-end`, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) } });
												} catch (e) { console.warn('request-end POST failed', e); }
											}
										} catch (e) {
											console.warn('request_end_session flow failed', e);
											window.modalNotification?.('Error', 'Fallo al preparar la solicitud de fin de sesión.', { template: 'danger' });
										}
						} catch (e) {
							console.warn('request_end_session flow failed', e);
							window.modalNotification?.('Error', 'Fallo al preparar la señalización.', { template: 'danger' });
						}
					} else {
						// Fallback: ask locally (no signaling available)
						window.modalConfirm?.({ title: 'Finalizar sesión', body: 'No se ha detectado servidor de señalización. ¿Deseas finalizar la sesión ahora?', buttons: [{ text: 'Cancelar', className: 'btn-outline-secondary', dismiss: true }, { text: 'Finalizar', className: 'btn-danger', onClick: async () => { await fetchCompleteAndClose(); } }] }, 'normal');
					}
				} catch (e) {
					console.error('end action error', e);
				}
				return;
			}
			if (act === 'toggle-mic') { btn.classList.toggle('btn-outline-primary'); btn.classList.toggle('btn-primary'); toggleTrack('audio'); }
			if (act === 'toggle-cam') { btn.classList.toggle('btn-outline-primary'); btn.classList.toggle('btn-primary'); await toggleTrack('video'); }
			if (act === 'join') { try { await joinConference(); } catch (e) { console.error('joinConference error', e); updatePlaceholder('Error al unirse'); } }
			if (act === 'toggle-quality') { toggleQualityPreference(btn); }
			if (act === 'show-quality-panel') { toggleQualityPanel(); }
			if (act === 'cancel-reconnect') { cancelReconnection(); }
		};
		delegateRoot.addEventListener('click', delegateRoot.__onClick);
		try { console.debug('[VideoCallModal] attached click handler to', delegateRoot === document ? 'document' : (wrap ? '#video-call-wrapper' : ('#' + modalId))); } catch (_) { }
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

					// Listen for server-side end-request broadcasts as a fallback
					window.Echo.private(`appointments.${selfId}`).listen('AppointmentEndRequested', (e) => {
						try {
							// Normalize payload shape similar to signaling payload
							const payload = e || {};
							if (payload && (payload.type === 'request_end_session' || payload.appointment_id)) {
								const askId = `${modalId}-end-ask`;
								window.modalConfirm?.({
									modalId: askId,
									title: 'Solicitan finalizar la sesión',
									body: '<div>El otro participante solicita finalizar la sesión. ¿Aceptas finalizar y cerrar la sesión?</div>',
									buttons: [
										{ text: 'Rechazar', className: 'btn-outline-secondary', onClick: () => { try { if (pgSignaler && typeof pgSignaler.sendMessage === 'function') pgSignaler.sendMessage(null, { type: 'decline_end_session', appointmentId: id, by: currentUserId || null }); } catch (_) { } }, dismiss: true },
										{ text: 'Aceptar', className: 'btn-danger', onClick: async () => { try { await fetchCompleteAndClose(); } catch (_) { } }, dismiss: true }
									]
								}, 'normal');
							}
						} catch (_) { }
					});
				}
				window.Echo.channel('presence').listen('UserPresenceChanged', (e) => {
					if (!e || !e.user_id) return; if (String(e.user_id) === String(otherUserId) && e.status === 'online') {
						const placeholder = document.getElementById('video-call-placeholder');
						if (placeholder) { placeholder.innerHTML = '<div class="fw-semibold">El otro usuario está en línea...</div><div class="small text-muted mt-2">Esperando unión a la sala.</div>'; }
					}
				});

					// Listen for appointment completion broadcasts and close UI accordingly
					window.Echo.private(`appointments.${selfId}`).listen('AppointmentCompleted', (e) => {
						try {
							if (!e || !e.id) return;
							if (String(e.id) !== String(id)) return;
							// Close any waiting modals and the main call modal, then cleanup UI
							try { closeEndRequestWaitingModal(); } catch (_) { }
							try {
								const el = document.getElementById(modalId);
								if (el) {
									try { const inst = bootstrap.Modal.getInstance(el); inst?.hide(); } catch (_) { try { $(el).modal('hide'); } catch (_) { } }
								}
							} catch (_) { }
							// Ensure local teardown
							try { if (typeof completeSession === 'function') completeSession(); } catch (_) { }
							// Remove the upcoming appointment card if it matches
							try {
								const next = document.getElementById('pg-next-appt');
								if (next) {
									const apptId = next.getAttribute('data-appt-id') || next.getAttribute('data-apptid') || next.getAttribute('data-appointment-id');
									if (String(apptId) === String(id)) { next.remove(); }
								}
							} catch (_) { }
						} catch (_) { }
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
	// P2P signaling (Socket.IO)
	let pgSignaler = null; let pgSignalingRoom = null;
	let _lastRemoteStream = null;
	// Ensure completeSession exists early so UI handlers can call it before wrapper is defined.
	let completeSession = async function () { /* placeholder; will be wrapped later */ };

	// Show/hide the Join button in the controls
	function showJoinButton() {
		try {
			const btn = document.querySelector('#video-call-controls [data-call-action="join"]');
			if (btn) { btn.classList.remove('d-none'); }
		} catch (_) { }
	}
	function hideJoinButton() {
		try {
			const btn = document.querySelector('#video-call-controls [data-call-action="join"]');
			if (btn) { btn.classList.add('d-none'); }
		} catch (_) { }
	}

	// Helper: find a conference join function across different ConnectyCube SDK namespaces
	function findConferenceJoin() {
		// Force use of the Socket.IO-based P2P signaling fallback.
		// We intentionally avoid invoking ConnectyCube's conference.join even if the
		// global SDK is present to keep appointment modal self-contained and
		// prevent ConnectyCube from emitting runtime warnings when the adapter
		// is not available or when the SDK is being removed.
		try {
			console.debug('[VideoCallModal] skipping ConnectyCube conference join (using P2P signaling)');
		} catch (_) { }
		return null;
	}

	// Ask server to ensure a canonical room exists for this appointment session
	async function ensureRoomFromServer() {
		try {
			const resp = await fetch(`/appointments/${encodeURIComponent(id)}/session/ensure-room`, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) } });
			if (!resp || !resp.ok) return null;
			const j = await resp.json().catch(() => null);
			if (j && j.room_id) { ccConferenceRoomId = j.room_id; try { console.debug('[VideoCallModal] ensured room id from server', ccConferenceRoomId); } catch (_) { } return ccConferenceRoomId; }
		} catch (e) { try { console.warn('ensureRoomFromServer error', e); } catch (_) { } }
		return null;
	}

	// Join conference flow: prefer conference.join (room id), fallback to placeCall for initiator
	async function joinConference() {
		try {
			try { console.debug('[VideoCallModal] joinConference invoked, ccLocalStream present?', !!ccLocalStream, 'ccConferenceRoomId', ccConferenceRoomId); } catch (_) { }
			// Ensure local preview exists
			if (!ccLocalStream) { try { await setupLocalPreview(); } catch (_) { } }
			try { updatePlaceholder('Intentando unirse...'); } catch (_) { }

			// Prefer conference room if provided by backend; if missing, ask server to ensure one
			let roomId = ccConferenceRoomId || (appointmentSessionInfo && appointmentSessionInfo.room_id) || null;
			if (!roomId) {
				try { roomId = await ensureRoomFromServer(); } catch (_) { roomId = ccConferenceRoomId || null; }
			}

			const conferenceJoin = findConferenceJoin();

			// If SDK conference join exists (unlikely now), prefer it — kept for backward compat.
			if (roomId && conferenceJoin) {
				try {
					await conferenceJoin(roomId, { stream: ccLocalStream });
					ccCallActive = true;
					hideJoinButton();
					updatePlaceholder('Dentro de la conferencia');
					return;
				} catch (e) {
					console.warn('conference join failed, attempting fallback', e);
				}
			}

			// If we have a room id, try P2P signaling fallback
			if (roomId) {
				updatePlaceholder('Conferencia no soportada por el cliente, intentando fallback...');
				// UX: disable join button while attempting P2P fallback
				try { const jb = document.querySelector('#video-call-controls [data-call-action="join"]'); if (jb) { jb.disabled = true; jb.textContent = 'Conectando...'; } } catch (_) { }

				if (!pgSignaler) pgSignaler = createSignalingClient();
				try {
					await pgSignaler.connect();
					console.debug('[VideoCallModal] signaller connected');
				} catch (eConn) {
					console.warn('[VideoCallModal] signaller connect failed', eConn);
					updatePlaceholder('No se pudo conectar al servidor de señalización');
					try { const jb = document.querySelector('#video-call-controls [data-call-action="join"]'); if (jb) { jb.disabled = false; jb.textContent = 'Unirse'; } } catch (_) { }
					return;
				}

				pgSignalingRoom = roomId;
				try {
					await pgSignaler.joinRoom(roomId, ccLocalStream, {
						onRemoteStream: (stream) => {
							try { attachRemoteStream(stream); ccCallActive = true; hideJoinButton(); updatePlaceholder('Dentro de la conferencia (P2P)'); } catch (_) { }
						},
						onConnected: () => { try { console.debug('[VideoCallModal] P2P joined room'); } catch (_) { } }
					});
					return;
				} catch (eJoin) {
					console.warn('[VideoCallModal] pgSignaler.joinRoom failed', eJoin);
					updatePlaceholder('Error al unirse por señalización');
					try { const jb = document.querySelector('#video-call-controls [data-call-action="join"]'); if (jb) { jb.disabled = false; jb.textContent = 'Unirse'; } } catch (_) { }
					return;
				}
			}

			// No room id: fall back to deterministic initiator placing a call
			const myInt = parseInt(currentUserId || '0', 10); const oppInt = parseInt(otherUserId || '0', 10);
			if (myInt && oppInt && myInt < oppInt) {
				// act as initiator using existing placeCall logic
				hideJoinButton();
				await placeCall();
				return;
			}

			updatePlaceholder('Esperando que el iniciador realice la llamada');
		} catch (e) { console.error('joinConference error', e); throw e; }
	}

	let incomingPromptTimer = null;
	let mediaStateSendDebounce = null;
	let qualityPollTimer = null; let lastVideoBytes = null; let lastTimestamp = null; let degradedCount = 0;
	let manualEnd = false; let retryCount = 0; const maxRetries = 3; let reconnecting = false; let reconnectTimer = null;
	const reconnectBackoffMs = [2000, 5000, 10000];
	let joinInProgress = false;

	function updatePlaceholder(text) {
		try {
			const stage = document.getElementById('video-call-stage');
			if (!stage) return;
			let ph = stage.querySelector('#video-call-placeholder');
			// If remote video present, show status as toast badge top-center
			const remote = document.getElementById('cc-remote-video');
			// If remote video present, we no longer show a floating badge; leave UI to video element
			if (remote) { return; }
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
		// Initialize lightweight signaling + WebRTC flow (replaces ConnectyCube init)
		try {
			if (!rtcBootstrap) return;
			if (!pgSignaler) pgSignaler = createSignalingClient();
			// capture appointment room id if provided
			try { ccConferenceRoomId = rtcBootstrap && rtcBootstrap.appointmentSession ? rtcBootstrap.appointmentSession.room_id : null; } catch (_) { ccConferenceRoomId = null; }
			if (ccConferenceRoomId) { try { console.debug('Using appointment room id:', ccConferenceRoomId); } catch (_) { } }
			// Map opponent
			ccOpponentId = (rtcBootstrap.userIdMap && rtcBootstrap.userIdMap[String(otherUserId)]) ? rtcBootstrap.userIdMap[String(otherUserId)] : otherUserId;
			// Connect signaling and attach basic handlers
			try { await pgSignaler.connect(); } catch (e) { console.warn('Signaler connect failed', e); }
			// Join presence silently so we can receive app-level messages (end-session requests, media_state, etc.)
			try { if (ccConferenceRoomId && pgSignaler && typeof pgSignaler.presenceJoin === 'function') { pgSignaler.presenceJoin(ccConferenceRoomId); } } catch (_) { }
			try { pgSignaler.on('remoteStream', (s) => { try { attachRemoteStream(s); } catch (_) { } }); } catch (_) { }
			// Ensure application-level signaling handlers are wired (message handlers, peer events)
			try { setupCcListeners(); } catch (_) { }
			await setupLocalPreview();
			// Auto-join disabled: the appointment modal must remain authoritative.
			// We intentionally DO NOT automatically join the room when a backend
			// `room_id` is present so the user has to explicitly press the
			// `Unirse` button. If you want to enable auto-join for another
			// workflow, call `pgSignaler.joinRoom(ccConferenceRoomId, ccLocalStream, ...)` here.
		} catch (e) { console.warn('initConnectyCube error', e); }
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
			// Inform the signaling client about the local stream so it can update senders
			try { if (pgSignaler && typeof pgSignaler.setLocalStream === 'function') pgSignaler.setLocalStream(ccLocalStream); } catch (_) { }
		} catch (e) {
			const camBox = document.querySelector('#video-call-stage .position-absolute');
			if (camBox) { camBox.innerHTML = '<div class="text-danger small">Permiso cámara/mic denegado</div>'; }
			console.error('Local media error', e);
		}
	}

	function setupCcListeners() {
		// Wire minimal signaling handlers when using Socket.IO-based signaling
		try {
			if (pgSignaler) {
				pgSignaler.on('peerJoined', (peerId) => {
					// If we're using a canonical conference room (room-based flow), do not show
					// the legacy incoming-call overlay; treat peer-join as normal presence.
					try {
						if (pgSignalingRoom || ccConferenceRoomId) {
							try { updatePlaceholder('Llamada entrante...'); } catch (_) { }
							return;
						}
						// Legacy place-call flow (no room): show incoming prompt
						try { updatePlaceholder('Llamada entrante...'); showIncomingCallPrompt({ id: peerId }); } catch (_) { }
					} catch (_) { }
				});
				pgSignaler.on('remoteStream', (stream, peerId) => {
					try { attachRemoteStream(stream); } catch (_) { }
				});
				pgSignaler.on('peerLeft', (peerId) => {
					try { updatePlaceholder('El otro usuario se fue'); finalizeCcCall(); } catch (_) { }
				});
				// Application-level messages (media_state, end-session requests, etc.)
				try {
					pgSignaler.onAppMessage((from, payload) => {
						try {
							if (!payload || !payload.type) return;
							if (payload.type === 'media_state') {
								updateRemoteMediaIndicators(payload);
								return;
							}
							if (payload.type === 'request_end_session') {
								// Show confirmation modal to the receiving participant
								try {
									const askId = `${modalId}-end-ask`;
									window.modalConfirm?.({
										modalId: askId,
										title: 'Solicitan finalizar la sesión',
										body: '<div>El otro participante solicita finalizar la sesión. ¿Aceptas finalizar y cerrar la sesión?</div>',
										buttons: [
											{ text: 'Rechazar', className: 'btn-outline-secondary', onClick: () => { try { if (pgSignaler && typeof pgSignaler.sendMessage === 'function') pgSignaler.sendMessage(from || null, { type: 'decline_end_session', appointmentId: id, by: currentUserId || null }); } catch (_) { } }, dismiss: true },
											{ text: 'Aceptar', className: 'btn-danger', onClick: async () => { try { await fetchCompleteAndClose(); } catch (_) { } }, dismiss: true }
										]
									}, 'normal');
								} catch (_) { }
								return;
							}
							if (payload.type === 'cancel_end_request') {
								// The requester cancelled before recipient answered — close any ask modal
								try {
									const askId = `${modalId}-end-ask`;
									const el = document.getElementById(askId);
									if (el) {
										try { const inst = bootstrap.Modal.getInstance(el); inst?.hide(); } catch (_) { try { $(el).modal('hide'); } catch (_) { } }
									}
									try { window.modalNotification?.('Solicitud cancelada', 'El otro participante canceló la solicitud.', { template: 'info' }); } catch (_) { }
								} catch (_) { }
								return;
							}
							if (payload.type === 'confirm_end_session') {
								// Remote accepted - finalize locally
								try { window.modalNotification?.('Sesión finalizada', 'La sesión ha sido finalizada por mutuo acuerdo.', { template: 'info' }); } catch (_) { }
								try { closeEndRequestWaitingModal(); } catch (_) { }
								try { completeSession(); } catch (_) { }
								return;
							}
							if (payload.type === 'decline_end_session') {
								try { window.modalNotification?.('Solicitud rechazada', 'El otro participante no aceptó finalizar la sesión.', { template: 'warning' }); } catch (_) { }
								// revert manualEnd flag so user can attempt again
								try { closeEndRequestWaitingModal(); } catch (_) { }
								manualEnd = false;
								return;
							}
						} catch (_) { }
					});
				} catch (_) { }
			}
		} catch (_) { }
	}

	// Helper: call server to mark session complete and then cleanup locally and notify peers
	async function fetchCompleteAndClose() {
		try {
			const url = `/appointments/${encodeURIComponent(id)}/session/complete`;
			const resp = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) } });
			if (resp && resp.ok) {
				// notify peers via signaling
				try { if (pgSignaler && typeof pgSignaler.sendMessage === 'function') { pgSignaler.sendMessage(null, { type: 'confirm_end_session', appointmentId: id, by: currentUserId || null }); } } catch (_) { }
				try { window.modalNotification?.('Sesión completada', 'La sesión se ha marcado como finalizada.', { template: 'info' }); } catch (_) { }
				try { completeSession(); } catch (_) { }
			} else {
				try { window.modalNotification?.('Error', 'No se pudo finalizar la sesión en servidor.', { template: 'danger' }); } catch (_) { }
			}
		} catch (e) {
			console.error('fetchCompleteAndClose error', e);
			try { window.modalNotification?.('Error', 'Fallo al conectar con servidor.', { template: 'danger' }); } catch (_) { }
		}
	}

	// Waiting modal for end-session request (shown to the requester)
	let _endRequestTimer = null;
	function showEndRequestWaitingModal() {
		const waitId = `${modalId}-end-wait`;
		try {
			window.modalConfirm?.({
				modalId: waitId,
				title: 'Solicitud enviada',
				body: '<div>Esperando respuesta del otro participante...</div>',
				buttons: [
					{ text: 'Cancelar', className: 'btn-outline-secondary', onClick: () => {
						// Cancel the pending request and allow retries
						try { if (pgSignaler && typeof pgSignaler.sendMessage === 'function') pgSignaler.sendMessage(null, { type: 'cancel_end_request', appointmentId: id, by: currentUserId || null }); } catch (_) { }
						manualEnd = false;
					}, dismiss: true }
				]
			}, 'normal');
			// Auto-timeout after 60s: close modal and reset manualEnd
			try { if (_endRequestTimer) clearTimeout(_endRequestTimer); _endRequestTimer = setTimeout(() => { const el = document.getElementById(waitId); if (el) { try { const inst = bootstrap.Modal.getInstance(el); inst?.hide(); } catch (_) { } } manualEnd = false; }, 60000); } catch (_) { }
		} catch (_) { }
	}

	function closeEndRequestWaitingModal() {
		const waitId = `${modalId}-end-wait`;
		try {
			if (_endRequestTimer) { clearTimeout(_endRequestTimer); _endRequestTimer = null; }
			const el = document.getElementById(waitId);
			if (el) {
				try { const inst = bootstrap.Modal.getInstance(el); inst?.hide(); } catch (_) { try { $(el).modal('hide'); } catch (_) { } }
			}
		} catch (_) { }
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
			try { showJoinButton(); } catch (_) { }
			updatePlaceholder('Ambos presentes — pulsa "Unirse" para entrar a la sala');
		} catch (_) { }
	}

	async function placeCall() {
		try {
			hideJoinButton();
			// Ensure local media
			try { await setupLocalPreview(); } catch (_) { }
			// Ensure canonical room
			let roomId = ccConferenceRoomId || (appointmentSessionInfo && appointmentSessionInfo.room_id) || null;
			if (!roomId) { try { roomId = await ensureRoomFromServer(); } catch (_) { roomId = ccConferenceRoomId || null; } }
			if (!roomId) { updatePlaceholder('No hay sala disponible'); return; }
			// Join via signaling; this will negotiate with other peer(s)
			try {
				if (!pgSignaler) pgSignaler = createSignalingClient();
				try { await pgSignaler.connect(); } catch (_) { }
				await pgSignaler.joinRoom(roomId, ccLocalStream, { onRemoteStream: (s) => { try { attachRemoteStream(s); } catch (_) { } } });
				ccCallActive = true; updatePlaceholder('Llamando...');
			} catch (e) { console.error('placeCall signaling error', e); updatePlaceholder('Error al iniciar llamada'); }
		} catch (e) { console.error('placeCall error', e); updatePlaceholder('Error al iniciar llamada'); }
	}

	async function tryAccept(session) {
		try {
			hideJoinButton();
			try { await setupLocalPreview(); } catch (_) { }
			// Accept by joining the canonical room
			let roomId = ccConferenceRoomId || (appointmentSessionInfo && appointmentSessionInfo.room_id) || null;
			if (!roomId) { try { roomId = await ensureRoomFromServer(); } catch (_) { roomId = ccConferenceRoomId || null; } }
			if (!roomId) { updatePlaceholder('No hay sala disponible'); return; }
			try {
				if (!pgSignaler) pgSignaler = createSignalingClient();
				try { await pgSignaler.connect(); } catch (_) { }
				await pgSignaler.joinRoom(roomId, ccLocalStream, { onRemoteStream: (s) => { try { attachRemoteStream(s); } catch (_) { } } });
				ccCallActive = true; updatePlaceholder('Aceptando llamada...'); hideIncomingCallPrompt();
			} catch (e) { console.error('accept signaling error', e); updatePlaceholder('Error al aceptar'); }
		} catch (e) { console.error('accept error', e); updatePlaceholder('Error al aceptar'); }
	}

	function attachRemoteStream(stream) {
		let remoteEl = document.getElementById('cc-remote-video');
		const stage = document.getElementById('video-call-stage');
		// Ensure placeholder removed when attaching a stream so video area is available
		try { const ph = stage && stage.querySelector('#video-call-placeholder'); if (ph) { ph.remove(); } } catch (_) { }
		if (!remoteEl && stage) {
			stage.querySelector('#video-call-placeholder')?.remove();
			remoteEl = document.createElement('video');
			remoteEl.id = 'cc-remote-video'; remoteEl.autoplay = true; remoteEl.playsInline = true;
			remoteEl.style.cssText = 'width:100%;height:100%;object-fit:cover;background:#000;border-radius:6px;';
			stage.appendChild(remoteEl);
		}
		_lastRemoteStream = stream;
		if (remoteEl) {
			// ensure the element has the latest stream object
			try { remoteEl.srcObject = stream; } catch (_) { remoteEl.srcObject = null; }
			// Track video presence so UI reflects remote camera on/off
			try {
				const applyVisibility = () => {
					try {
						const hasVideo = stream && stream.getVideoTracks && stream.getVideoTracks().some(t => t && t.enabled && !t.muted);
						if (hasVideo) { remoteEl.style.display = ''; remoteEl.dataset.videoEnabled = '1'; }
						else { remoteEl.style.display = 'none'; remoteEl.dataset.videoEnabled = '0'; }
					} catch (_) { }
				};
				// Initial visibility
				applyVisibility();
				// React to track ended/removed events
				try { stream.addEventListener('removetrack', applyVisibility); } catch (_) { }
				try { stream.getVideoTracks().forEach(t => { try { t.addEventListener('ended', applyVisibility); t.addEventListener('mute', applyVisibility); t.addEventListener('unmute', applyVisibility); } catch (_) { } }); } catch (_) { }
			} catch (_) { }
			// remove any placeholder and show connected state
			try { const ph = stage && stage.querySelector('#video-call-placeholder'); if (ph) { ph.remove(); } } catch (_) { }
			updatePlaceholder('Conectados'); hideJoinButton();
		}
		// Successful reattachment resets reconnection state
		if (reconnecting) { reconnecting = false; retryCount = 0; hideReconnectUi(); }
	}

	async function toggleTrack(kind) {
		try {
			// Determine existing enabled state by peeking at first available track
			let stream = ccLocalStream || (typeof window !== 'undefined' ? window.__earlyCallStream : null);
			let tracks = stream ? (kind === 'audio' ? stream.getAudioTracks() : stream.getVideoTracks()) : [];
			let currentEnabled = null;
			if (tracks && tracks.length) currentEnabled = !!tracks[0].enabled;

			// If no stream/tracks for audio, attempt to acquire audio track so we can mute it
			if ((!tracks || tracks.length === 0) && kind === 'audio') {
				try {
					const aStream = await navigator.mediaDevices.getUserMedia({ audio: true });
					// merge or set as local stream
					if (ccLocalStream) {
						// add audio tracks to ccLocalStream
						for (const t of aStream.getAudioTracks()) {
							try { ccLocalStream.addTrack(t); } catch (_) { }
						}
					} else {
						ccLocalStream = aStream;
						const v = document.getElementById('cc-local-video'); if (v) v.srcObject = ccLocalStream;
					}
					stream = ccLocalStream;
					tracks = stream.getAudioTracks();
					if (tracks && tracks.length) currentEnabled = !!tracks[0].enabled;
				} catch (e) {
					// permission denied or error
					return;
				}
			}

			// If no video tracks, try to create a local preview (to obtain camera) and re-evaluate
			if ((!tracks || tracks.length === 0) && kind === 'video') {
				try {
					await setupLocalPreview();
					stream = ccLocalStream || (typeof window !== 'undefined' ? window.__earlyCallStream : null);
					tracks = stream ? stream.getVideoTracks() : [];
					if (tracks && tracks.length) currentEnabled = !!tracks[0].enabled;
				} catch (_) { }
			}

			// If we still have no tracks, nothing to do
			if (!tracks || tracks.length === 0) return;

			const newEnabled = !(currentEnabled === null ? true : currentEnabled);

			// Update local tracks first
			try {
				tracks.forEach(t => { try { t.enabled = !!newEnabled; } catch (_) { } });
			} catch (_) { }

			// If signaller provides API to update sender tracks, use it so remote peers stop receiving
			try {
				if (pgSignaler && typeof pgSignaler.setLocalTrackEnabled === 'function') {
					try { await pgSignaler.setLocalTrackEnabled(kind, !!newEnabled); } catch (_) { }
				}
			} catch (_) { }
		} catch (_) { }
		sendMediaState();
	}

	function finalizeCcCall() {
		ccCallActive = false;
		// Stop local tracks
		try { ccLocalStream && ccLocalStream.getTracks().forEach(t => { try { t.stop(); } catch (_) { } }); } catch (_) { }
		try { if (pgSignaler) pgSignaler.cleanupAll(); } catch (_) { }
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
			if (remote) {
				try {
					const active = document.activeElement;
					if (active && remote.contains(active)) { try { active.blur(); document.body.focus(); } catch (_) { } }
				} catch (_) { }
				try { remote.srcObject = null; } catch (_) { }
				remote.remove();
			}
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
				// If ConnectyCube is not available, try to notify peers via signaling
				try {
					if (pgSignaler && typeof pgSignaler.sendMessage === 'function') {
						pgSignaler.sendMessage(null, { type: 'reject', sessionId: session && (session.ID || session.id || session.sessionId) || null });
					}
				} catch (_) { }
				finalizeCcCall(); updatePlaceholder('Tiempo agotado'); hideIncomingCallPrompt();
			}
		}, 1000);
		overlay.addEventListener('click', ev => {
			const btn = ev.target.closest('[data-incoming-action]'); if (!btn) return;
			const act = btn.getAttribute('data-incoming-action');
			if (act === 'accept') { tryAccept(session); }
			if (act === 'reject') {
				try {
					if (pgSignaler && typeof pgSignaler.sendMessage === 'function') {
						pgSignaler.sendMessage(null, { type: 'reject', sessionId: session && (session.ID || session.id || session.sessionId) || null });
					}
				} catch (_) { }
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
		// Open a modalConfirm showing stored metrics and attempt to populate
		// with live RTCPeerConnection getStats() results when available.
		(async () => {
			const qModalId = `${modalId}-quality`;
			// Build initial summary from collected metrics
			let avgBit = metrics.avg_bitrate_kbps || 0;
			let avgLoss = metrics.avg_loss_pct || 0;
			let avgRtt = metrics.avg_rtt_ms || 0;
			if (!avgBit && Array.isArray(metrics.samples) && metrics.samples.length) {
				const s = metrics.samples; let sb = 0, sl = 0, sr = 0;
				for (const it of s) { sb += (it.bitrateKbps || 0); sl += (it.lossPct || 0); sr += (it.rttMs || 0); }
				avgBit = +(sb / s.length).toFixed(1); avgLoss = +(sl / s.length).toFixed(2); avgRtt = +(sr / s.length).toFixed(1);
			}
			const recent = (Array.isArray(metrics.samples) ? metrics.samples.slice(-20) : []).reverse();
			let body = `<div class="mb-2"><strong>Resumen</strong>`;
			body += `<div class="small text-muted">Muestras almacenadas: ${metrics.samples.length || 0}</div>`;
			body += `<ul class="list-unstyled mb-2"><li><strong>Bitrate (avg):</strong> ${avgBit} kbps</li><li><strong>Pérdida (avg):</strong> ${avgLoss}%</li><li><strong>RTT (avg):</strong> ${avgRtt} ms</li></ul></div>`;
			body += `<div><strong>Muestras recientes</strong><div class="table-responsive"><table class="table table-sm"><thead><tr><th>#</th><th>Bitrate (kbps)</th><th>Pérdida (%)</th><th>RTT (ms)</th></tr></thead><tbody>`;
			if (recent.length === 0) { body += `<tr><td colspan="4" class="text-muted">No hay muestras disponibles todavía.</td></tr>`; }
			else {
				for (let i = 0; i < recent.length; i++) {
					const it = recent[i]; body += `<tr><td>${i + 1}</td><td>${(it.bitrateKbps || 0).toFixed ? (it.bitrateKbps||0).toFixed(0) : (it.bitrateKbps||0)}</td><td>${(it.lossPct||0).toFixed(2)}</td><td>${(it.rttMs||0).toFixed(0)}</td></tr>`;
				}
			}
			body += `</tbody></table></div></div>`;
			// Show modal immediately with collected data
			try {
				window.modalConfirm?.({ modalId: qModalId, title: `Calidad — Cita #${id}`, body, buttons: [{ text: 'Cerrar', className: 'btn-secondary', dismiss: true }] }, 'normal', { size: 'lg' });
			} catch (_) { }
			// Try to augment with live stats from peer connections (if signaling client exposes them)
			try {
				if (pgSignaler && typeof pgSignaler.getPeerConnections === 'function') {
					const pcs = pgSignaler.getPeerConnections() || {};
					const pcList = Object.values(pcs).filter(x => x);
					if (pcList.length) {
						const liveRows = [];
						for (const pc of pcList) {
							try {
								const stats = await pc.getStats();
								let videoInbound = null; let candidatePair = null; let trackInfo = null;
								stats.forEach(r => {
									if (r.type === 'inbound-rtp' && r.kind === 'video' && !r.isRemote) { videoInbound = r; }
									if (r.type === 'candidate-pair' && r.state === 'succeeded') { candidatePair = r; }
									if (r.type === 'track' && r.kind === 'video') { trackInfo = r; }
								});
								const br = videoInbound ? (videoInbound.bytesReceived || 0) : 0;
								const pkLost = videoInbound ? (videoInbound.packetsLost || 0) : 0;
								const pkRecv = videoInbound ? (videoInbound.packetsReceived || 0) : 0;
								const loss = (pkLost + pkRecv) > 0 ? (pkLost / (pkLost + pkRecv)) * 100 : 0;
								const rtt = candidatePair ? (candidatePair.currentRoundTripTime || 0) * 1000 : 0;
								const fps = trackInfo ? (trackInfo.framesPerSecond || null) : null;
								liveRows.push({ bitrateBytes: br, lossPct: +loss.toFixed(2), rttMs: Math.round(rtt), fps });
							} catch (e) { /* ignore pc stats errors */ }
						}
						// Build live stats HTML and replace modal body
						if (liveRows.length) {
							let liveHtml = `<div class="mt-3"><strong>Estadísticas en vivo</strong><div class="table-responsive"><table class="table table-sm"><thead><tr><th>#</th><th>Bitrate (est.)</th><th>Pérdida (%)</th><th>RTT (ms)</th><th>FPS</th></tr></thead><tbody>`;
							for (let i = 0; i < liveRows.length; i++) {
								const r = liveRows[i];
								// bitrate estimate from bytes is not straightforward without prior timestamp; show bytes as hint
								liveHtml += `<tr><td>${i + 1}</td><td>${r.bitrateBytes} bytes</td><td>${r.lossPct}</td><td>${r.rttMs}</td><td>${r.fps || '-'}</td></tr>`;
							}
							liveHtml += `</tbody></table></div></div>`;
							const modalEl = document.getElementById(qModalId);
							if (modalEl) {
								const mb = modalEl.querySelector('.modal-body');
								if (mb) {
									mb.insertAdjacentHTML('beforeend', liveHtml);
								}
							}
						}
					}
				}
			} catch (_) { }
		})();
	}
	function hideQualityPanel() {
		return;
	}

	function updateQualityPanel(m) {
		// We no longer update an inline panel. Details are shown via modalConfirm.
		try {
			// keep panel data in metrics for modal view
			// metrics.samples already updated in collectQualityStats
		} catch (_) { }
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
		try { if (pgSignaler) pgSignaler.cleanupAll(); } catch (_) { }
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
				// Attempt to send message via signaling server
				try { if (pgSignaler) pgSignaler.sendMessage(null, { type: 'media_state', audioEnabled, videoEnabled }); } catch (e) { /* fallback ignored */ }
			} catch (_) { }
		}, 120);
	}

	function updateRemoteMediaIndicators(state) {
		try {
			// Force UI visibility update for remote video based on reported state.
			try {
				const remoteEl = document.getElementById('cc-remote-video');
				const stage = document.getElementById('video-call-stage');
				if (remoteEl) {
					if (state.videoEnabled) {
						// show remote video; if no srcObject attach yet, show placeholder
						remoteEl.style.display = '';
						remoteEl.dataset.videoEnabled = '1';
						// If we don't have an attached stream or it lacks video, show placeholder and wait for ontrack
						try {
							const hasAttachedVideo = remoteEl.srcObject && remoteEl.srcObject.getVideoTracks && remoteEl.srcObject.getVideoTracks().some(t => t && t.enabled);
							if (!hasAttachedVideo) {
								// create/ensure placeholder content while waiting for the new remote track
								let ph = stage.querySelector('#video-call-placeholder');
								if (!ph) {
									ph = document.createElement('div'); ph.id = 'video-call-placeholder'; ph.className = 'text-center'; stage.appendChild(ph);
								}
								ph.innerHTML = '<div class="fw-semibold">Esperando video...</div><div class="small text-muted mt-2">El otro usuario ha activado la cámara, esperando stream.</div>';
							}
						} catch (_) { }
					} else {
						// hide remote video to avoid frozen frame
						remoteEl.style.display = 'none';
						remoteEl.dataset.videoEnabled = '0';
						// update placeholder to indicate camera off
						try {
							let ph = stage.querySelector('#video-call-placeholder');
							if (!ph) { ph = document.createElement('div'); ph.id = 'video-call-placeholder'; ph.className = 'text-center'; stage.appendChild(ph); }
							ph.innerHTML = '<div class="fw-semibold">Cámara desactivada</div><div class="small text-muted mt-2">El otro usuario ha apagado la cámara.</div>';
						} catch (_) { }
					}
				}
			} catch (_) { }
		} catch (_) { }
	}

	// Wrap completeSession to ensure call teardown. Guard if completeSession wasn't defined.
	const _origComplete = (typeof completeSession === 'function') ? completeSession : async function () { };
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
