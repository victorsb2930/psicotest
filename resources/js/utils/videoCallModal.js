// Video call modal for appointments
// Provides openAppointmentCall({ id, otherUserId, role }) to start session, show modal, heartbeat, and finalize.
import ConnectyCube from 'connectycube';

export function openAppointmentCall(opts){
  const { id, otherUserId, role, currentUserId } = opts || {};
  if(!id){ return; }
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  let heartbeatTimer = null;
  let modalId = `modal-appt-call-${id}`;
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
  const MAX_SAMPLES = (function(){ try { return parseInt(window.APPOINTMENT_MAX_METRIC_SAMPLES || '0', 10) || (window.__APPT_CONFIG_QUALITY_MAX_SAMPLES || 250); } catch(_){ return 250; } })();
  // Heartbeat multi-tab mutex
  const HEARTBEAT_LOCK_KEY = 'pg_call_heartbeat_owner';
  const tabId = (window.__pgTabId = window.__pgTabId || (Math.random().toString(36).slice(2)+'-'+Date.now()));
  const HEARTBEAT_LOCK_TTL_MS = (parseInt(window.APPOINTMENT_PING_INTERVAL_SECONDS||'45',10)||45)*1500; // 1.5x interval grace

  const escapeHtml = s => !s ? '' : String(s).replace(/[&<>"'`=\/]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c]));

  async function startSession(){
    try {
      await fetch(`/appointments/${encodeURIComponent(id)}/session/start`, { method:'POST', headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest', ...(csrf?{'X-CSRF-TOKEN':csrf}:{}) } });
      sessionStarted = true;
    } catch(_) { /* ignore */ }
  }
  async function sendHeartbeat(){
    // Only owner sends heartbeat; attempt takeover if stale
    if(!isHeartbeatOwner()){ if(canTakeoverHeartbeat()){ acquireHeartbeatLock(); } }
    if(!isHeartbeatOwner()) return;
    try {
      const resp = await fetch(`/appointments/${encodeURIComponent(id)}/session/heartbeat`, { method:'POST', headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest', ...(csrf?{'X-CSRF-TOKEN':csrf}:{}) } });
      if(resp.status === 429){
        // Rate limited: pause heartbeats and retry with backoff (min 8s or Retry-After header)
        const ra = parseInt(resp.headers.get('Retry-After')||'0',10);
        const backoffMs = ra > 0 ? ra*1000 : 8000;
        if(heartbeatTimer){ clearInterval(heartbeatTimer); heartbeatTimer = null; }
        setTimeout(()=>{ beginHeartbeat(); }, backoffMs);
        return; // do not count this attempt
      }
      if(resp.ok){
        refreshHeartbeatLock();
        metrics.presence_heartbeats_sent++;
      }
    } catch(_) {}
  }
  async function completeSession(){
    if(sessionCompleted) return;
    try { await fetch(`/appointments/${encodeURIComponent(id)}/session/complete`, { method:'POST', headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest', ...(csrf?{'X-CSRF-TOKEN':csrf}:{}) } }); sessionCompleted = true; } catch(_) {}
  }

  function beginHeartbeat(){
    if(heartbeatTimer) clearInterval(heartbeatTimer);
    heartbeatTimer = setInterval(sendHeartbeat, (parseInt(window.APPOINTMENT_PING_INTERVAL_SECONDS||'45',10)||45)*1000);
    acquireHeartbeatLock();
    sendHeartbeat(); // immediate first tick if owner
  }
  function stopHeartbeat(){ if(heartbeatTimer){ clearInterval(heartbeatTimer); heartbeatTimer=null; } }

  // --- Heartbeat lock helpers ---
  function readHeartbeatLock(){ try { const raw = localStorage.getItem(HEARTBEAT_LOCK_KEY); return raw ? JSON.parse(raw) : null; } catch(_) { return null; } }
  function isHeartbeatOwner(){ const l = readHeartbeatLock(); return !!(l && l.apptId === String(id) && l.tabId === tabId); }
  function canTakeoverHeartbeat(){ const l = readHeartbeatLock(); if(!l) return true; if(l.apptId !== String(id)) return true; const age = Date.now() - (l.ts||0); return age > HEARTBEAT_LOCK_TTL_MS; }
  function acquireHeartbeatLock(){ if(!canTakeoverHeartbeat()) return false; try { localStorage.setItem(HEARTBEAT_LOCK_KEY, JSON.stringify({ apptId:String(id), tabId, ts:Date.now() })); return true; } catch(_) { return false; } }
  function refreshHeartbeatLock(){ if(!isHeartbeatOwner()) return; try { const l = readHeartbeatLock(); if(l){ l.ts = Date.now(); localStorage.setItem(HEARTBEAT_LOCK_KEY, JSON.stringify(l)); } } catch(_){} }
  function releaseHeartbeatLock(){ if(!isHeartbeatOwner()) return; try { localStorage.removeItem(HEARTBEAT_LOCK_KEY); } catch(_){} }

  // Basic ConnectyCube bootstrap (optional). If fails, we still show placeholder UI.
  async function loadRtcConfig(){
    try {
      const r = await fetch('/rtc/bootstrap');
      if(!r.ok) return null; const j = await r.json(); return j;
    } catch(_) { return null; }
  }

  function renderBody(rtc){
    const rtcInfo = rtc ? `<div class="small text-muted">App RTC listo (ID ${escapeHtml(String(rtc?.ccUser?.userId||''))})</div>` : '<div class="small text-muted">Inicializando video...</div>';
    return `
      <div id="video-call-wrapper" data-appt-id="${escapeHtml(String(id))}" class="d-flex flex-column" style="min-height:640px">
        <div class="mb-2">
          <strong>Sesión de cita #${escapeHtml(String(id))}</strong> <span class="badge bg-secondary ms-1">${escapeHtml(role||'')}</span>
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
          <button type="button" class="btn btn-sm btn-outline-danger" data-call-action="end">Finalizar sesión</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-call-action="toggle-quality" title="Mostrar/Ocultar calidad">Calidad</button>
          <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-call-action="show-quality-panel" title="Panel diagnóstico">Detalles</button>
        </div>
      </div>
    `;
  }

  async function init(){
    await startSession();
    const rtc = await loadRtcConfig();
    const body = renderBody(rtc);
    window.modalConfirm?.({
      modalId,
      title:`Videollamada cita #${id}`,
      body,
      noFooter:true,
      buttons:false,
    }, 'normal', { size: 'xl', dialogClasses: 'w-100', fullscreen: false, backdrop: 'static', keyboard: false });
    attachHandlers();
    beginHeartbeat();
    try { localStorage.setItem('pg_active_call_appt_id', String(id)); } catch(_){}
    // Early local media preview to request camera permission even si ConnectyCube falla
    attemptEarlyPreview();
    try { await initConnectyCube(rtc); } catch(e){ console.error('ConnectyCube init error', e); }
  }

  function attemptEarlyPreview(){
    try {
      if(!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return;
      navigator.mediaDevices.getUserMedia({ audio:true, video:{ width:{ideal:640}, height:{ideal:480} } })
        .then(stream => {
          const camBox = document.querySelector('#video-call-stage .position-absolute');
          if(camBox && !document.getElementById('cc-local-video')){
            camBox.innerHTML = '<video id="cc-local-video" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;border-radius:4px;background:#000"></video>';
            const v = document.getElementById('cc-local-video'); if(v){ v.srcObject = stream; }
          }
          // Guardar para posible reuso rápido si init ConnectyCube después
          try { window.__earlyCallStream = stream; } catch(_) {}
        })
        .catch(err => { console.warn('Early media preview denied', err); });
    } catch(_){}
  }

  function attachHandlers(){
    const wrap = document.getElementById('video-call-wrapper');
    if(!wrap) return;
    wrap.__onClick = async ev => {
      const btn = ev.target.closest && ev.target.closest('[data-call-action]');
      if(!btn) return;
      const act = btn.getAttribute('data-call-action');
      if(act === 'end'){ manualEnd = true; await completeSession(); window.modalNotification?.('Sesión','Finalizada',{template:'info'}); window.closeAllModals?.(); return; }
      if(act === 'toggle-mic'){ btn.classList.toggle('btn-outline-primary'); btn.classList.toggle('btn-primary'); toggleTrack('audio'); }
      if(act === 'toggle-cam'){ btn.classList.toggle('btn-outline-primary'); btn.classList.toggle('btn-primary'); toggleTrack('video'); }
      if(act === 'toggle-quality'){ toggleQualityPreference(btn); }
      if(act === 'show-quality-panel'){ toggleQualityPanel(); }
      if(act === 'cancel-reconnect'){ cancelReconnection(); }
    };
    wrap.addEventListener('click', wrap.__onClick);
    setupPresenceListeners();
    // Clean up when modal hidden
    const modalEl = document.getElementById(modalId);
    if(modalEl){
      modalEl.addEventListener('hidden.bs.modal', async () => {
        stopHeartbeat();
        if(!sessionCompleted){ await completeSession(); }
        if(wrap.__onClick) try { wrap.removeEventListener('click', wrap.__onClick); } catch(_){}
        try { localStorage.removeItem('pg_active_call_appt_id'); } catch(_){}
        releaseHeartbeatLock();
      }, { once:true });
    }
  }

  function setupPresenceListeners(){
    // Update placeholder when both participants joined based on heartbeat accumulation
    try {
      const placeholder = document.getElementById('video-call-placeholder');
      if(!placeholder) return;
      // Poll new status endpoint every 8s
      let pollTimer = setInterval(async () => {
        try {
          const r = await fetch(`/appointments/${encodeURIComponent(id)}/session/status`, { headers:{'Accept':'application/json'} });
          if(!r.ok) return; const j = await r.json().catch(()=>null); if(!j || !j.session) return;
          const pj = j.session.professional_joined_at; const pt = j.session.patient_joined_at;
          if(pj && pt){
            placeholder.innerHTML = '<div class="fw-semibold">Conectados</div><div class="small text-muted mt-2">Ambos participantes presentes.</div>';
            clearInterval(pollTimer);
          } else if(pj || pt){
            placeholder.innerHTML = '<div class="fw-semibold">Esperando al otro participante...</div><div class="small text-muted mt-2">Conexión parcial detectada.</div>';
          }
        } catch(_){ /* ignore */ }
      }, 8000);
    } catch(_){}
    // Real-time via Echo private channels
    try {
      if(window.Echo){
        const selfId = currentUserId || window.__authUserId || document.querySelector('meta[name="auth-user-id"]')?.getAttribute('content');
        if(selfId){
          window.Echo.private(`appointments.${selfId}`).listen('AppointmentStarted', (e)=>{
            const placeholder = document.getElementById('video-call-placeholder');
            if(placeholder){ placeholder.innerHTML = '<div class="fw-semibold">Conectados</div><div class="small text-muted mt-2">La sesión ha iniciado.</div>'; }
          });
        }
        window.Echo.channel('presence').listen('UserPresenceChanged', (e)=>{
          if(!e || !e.user_id) return; if(String(e.user_id) === String(otherUserId) && e.status === 'online'){
            const placeholder = document.getElementById('video-call-placeholder');
            if(placeholder){ placeholder.innerHTML = '<div class="fw-semibold">El otro usuario está en línea...</div><div class="small text-muted mt-2">Esperando unión a la sala.</div>'; }
          }
        });
      }
    } catch(_){}
  }

  init();

  // ---- ConnectyCube integration layer ----
  let ccSession = null; let ccLocalStream = null; let ccOpponentId = null; let ccCallActive = false; let ccCurrentSession = null;
  let incomingPromptTimer = null;
  let mediaStateSendDebounce = null;
  let qualityPollTimer = null; let lastVideoBytes = null; let lastTimestamp = null; let degradedCount = 0;
  let manualEnd = false; let retryCount = 0; const maxRetries = 3; let reconnecting = false; let reconnectTimer = null;
  const reconnectBackoffMs = [2000, 5000, 10000];

  function updatePlaceholder(text){
    try {
      const stage = document.getElementById('video-call-stage');
      if(!stage) return;
      let ph = stage.querySelector('#video-call-placeholder');
      // If remote video present, show status as toast badge top-center
      const remote = document.getElementById('cc-remote-video');
      if(remote){
        let badge = stage.querySelector('#video-status-badge');
        if(!badge){
          badge = document.createElement('div');
          badge.id='video-status-badge';
          badge.className='position-absolute top-0 start-50 translate-middle-x mt-2 px-3 py-1 rounded bg-dark text-white small shadow';
          stage.appendChild(badge);
        }
        badge.textContent = text;
        return;
      }
      if(!ph){
        ph = document.createElement('div');
        ph.id='video-call-placeholder';
        ph.className='text-center';
        stage.appendChild(ph);
      }
      ph.innerHTML = `<div class="fw-semibold">${text}</div><div class="small text-muted mt-2">Esperando conexión...</div>`;
    } catch(_){ }
  }

  async function initConnectyCube(rtcBootstrap){
    if(!rtcBootstrap || !rtcBootstrap.ccConfig || !rtcBootstrap.ccUser){ return; }
    const creds = { appId: rtcBootstrap.ccConfig.appId, authKey: rtcBootstrap.ccConfig.authKey, authSecret: rtcBootstrap.ccConfig.authSecret };
    // Normalize endpoints to host only (e.g., 'https//api...' -> 'api-eu.connectycube.com')
    const normalizeEndpoint = (u) => {
      if (typeof u !== 'string') return '';
      let s = u.trim();
      if(!s) return '';
      // Remove any leading protocols (https/http/ws/wss) including malformed 'https//' variants
      const protoPattern = /^(?:https?:\/\/|wss?:\/\/|https?\/\/|wss?\/\/)+/i;
      while(protoPattern.test(s)) { s = s.replace(protoPattern, ''); }
      // Remove any leading slashes left
      s = s.replace(/^\/+/, '');
      // Take host only (strip any path/query)
      const host = s.split('/')[0].trim();
      return host;
    };
    const rawApi = rtcBootstrap.ccConfig.apiEndpoint;
    const rawChat = rtcBootstrap.ccConfig.chatEndpoint;
    const apiEp = normalizeEndpoint(rawApi);
    const chatEp = normalizeEndpoint(rawChat);
    try { console.debug('[RTC endpoints] raw:', rawApi, rawChat, 'normalized:', apiEp, chatEp); } catch(_){}
    // ConnectyCube expects hostnames here; protocol is configured separately
    const cfg = { protocol: 'https', endpoints: { api: apiEp, chat: chatEp }, debug: { mode: 0 } };
    try { ConnectyCube.init(creds, cfg); } catch(e){ console.error('CC init base error', e); }
    ccSession = await ConnectyCube.createSession({ login: rtcBootstrap.ccUser.login, password: rtcBootstrap.ccUser.password });
    try { await ConnectyCube.chat.connect({ userId: ccSession.user.id, password: rtcBootstrap.ccUser.password }); } catch(e){ console.error('CC chat connect fail', e); }
    // Persist discovered CC user id
    try { fetch('/rtc/sync', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf||''}, body: JSON.stringify({ cc_user_id: ccSession.user.id, cc_login: rtcBootstrap.ccUser.login }) }); } catch(_){ }
    // Map opponent
    ccOpponentId = (rtcBootstrap.userIdMap && rtcBootstrap.userIdMap[String(otherUserId)]) ? rtcBootstrap.userIdMap[String(otherUserId)] : otherUserId;
    setupCcListeners();
    await setupLocalPreview();
    decideInitiator();
  }

  async function setupLocalPreview(){
    try {
      const constraints = { audio: true, video: { width: { ideal: 640 }, height: { ideal: 480 } } };
      ccLocalStream = await navigator.mediaDevices.getUserMedia(constraints);
      const camBox = document.querySelector('#video-call-stage .position-absolute');
      if(camBox){
        camBox.innerHTML = '<video id="cc-local-video" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;border-radius:4px;background:#000"></video>';
        const v = document.getElementById('cc-local-video'); if(v){ v.srcObject = ccLocalStream; }
      }
    } catch(e){
      const camBox = document.querySelector('#video-call-stage .position-absolute');
      if(camBox){ camBox.innerHTML = '<div class="text-danger small">Permiso cámara/mic denegado</div>'; }
      console.error('Local media error', e);
    }
  }

  function setupCcListeners(){
    ConnectyCube.videochat.onCallListener = (session/*, extension*/)=>{
      ccCurrentSession = session;
      // Only show prompt if opponent matches expected (avoid stray calls)
      if(ccOpponentId && session.initiatorID && parseInt(session.initiatorID,10) !== parseInt(ccOpponentId,10)){
        updatePlaceholder('Llamada desconocida');
        return;
      }
      updatePlaceholder('Llamada entrante...');
      showIncomingCallPrompt(session);
    };
    ConnectyCube.videochat.onAcceptListener = (session, userId/*, extension*/)=>{
      updatePlaceholder('Conectando flujo remoto...');
    };
    ConnectyCube.videochat.onRemoteStreamListener = (session, userId, stream)=>{
      attachRemoteStream(stream);
    };
    ConnectyCube.videochat.onUserNotAnswerListener = (session, userId)=>{
      updatePlaceholder('El usuario no responde');
      finalizeCcCall();
    };
    ConnectyCube.videochat.onRejectCallListener = ()=>{ updatePlaceholder('Llamada rechazada'); finalizeCcCall(); };
    ConnectyCube.videochat.onStopCallListener = ()=>{ updatePlaceholder('Llamada finalizada'); finalizeCcCall(); };
    ConnectyCube.videochat.onSessionConnectionStateChangedListener = (session, userId, state)=>{
      if(state === 'failed'){ updatePlaceholder('Conexión fallida'); }
      if((state === 'failed' || state === 'disconnected') && !manualEnd && !sessionCompleted){
        scheduleReconnect('estado '+state);
      }
    };
    // Media state signalling
    ConnectyCube.videochat.onMessageListener = (userId, message)=>{
      try {
        const ext = message?.extension || message || {};
        if(ext.type === 'media_state'){
          updateRemoteMediaIndicators(ext);
        }
      } catch(_){ }
    };
  }

  function decideInitiator(){
    if(!ccOpponentId || !ccLocalStream){ return; }
    const myInt = parseInt(currentUserId||'0',10);
    const oppInt = parseInt(otherUserId||'0',10);
    if(myInt === 0 || oppInt === 0){ updatePlaceholder('IDs inválidos para llamada'); return; }
    if(myInt < oppInt){ placeCall(); } else { updatePlaceholder('Esperando llamada del otro usuario...'); }
  }

  function placeCall(){
    try {
      const opponents = [ccOpponentId];
      const type = ConnectyCube.videochat.CallType.VIDEO;
      ccCurrentSession = ConnectyCube.videochat.createNewSession(opponents, type, {});
      try { ccCurrentSession.getUserMedia({ audio: true, video: true }); } catch(_){ }
      ConnectyCube.videochat.call(opponents, type, {});
      ccCallActive = true;
      updatePlaceholder('Llamando...');
    } catch(e){ console.error('placeCall error', e); updatePlaceholder('Error al iniciar llamada'); }
  }

  function tryAccept(session){
    try {
      session.getUserMedia({ audio: true, video: true });
      ConnectyCube.videochat.accept(session, {});
      ccCallActive = true;
      updatePlaceholder('Aceptando llamada...');
      hideIncomingCallPrompt();
    } catch(e){ console.error('accept error', e); updatePlaceholder('Error al aceptar'); }
  }

  function attachRemoteStream(stream){
    let remoteEl = document.getElementById('cc-remote-video');
    const stage = document.getElementById('video-call-stage');
    if(!remoteEl && stage){
      stage.querySelector('#video-call-placeholder')?.remove();
      remoteEl = document.createElement('video');
      remoteEl.id='cc-remote-video'; remoteEl.autoplay=true; remoteEl.playsInline=true;
      remoteEl.style.cssText='width:100%;height:100%;object-fit:cover;background:#000;border-radius:6px;';
      stage.appendChild(remoteEl);
      // Add remote media indicators container
      let indicators = document.getElementById('remote-media-indicators');
      if(!indicators){
        indicators = document.createElement('div');
        indicators.id='remote-media-indicators';
        indicators.className='position-absolute bottom-0 end-0 m-2 p-1 rounded bg-dark bg-opacity-75 text-white small';
        indicators.innerHTML = '<span data-remote-mic>Mic ?</span> | <span data-remote-cam>Cam ?</span>';
        stage.appendChild(indicators);
      }
    }
    if(remoteEl){ remoteEl.srcObject = stream; updatePlaceholder('Conectados'); }
    // Successful reattachment resets reconnection state
    if(reconnecting){ reconnecting = false; retryCount = 0; hideReconnectUi(); }
  }

  function toggleTrack(kind){
    try { if(!ccLocalStream) return; ccLocalStream.getTracks().filter(t=>t.kind===kind).forEach(t=>{ t.enabled = !t.enabled; }); } catch(_){ }
    sendMediaState();
  }

  function finalizeCcCall(){
    ccCallActive = false;
    // Stop local tracks
    try { ccLocalStream && ccLocalStream.getTracks().forEach(t=>{ try { t.stop(); } catch(_){} }); } catch(_){ }
    try { ConnectyCube.videochat.stop(); } catch(_){ }
    cleanupUiAfterCall();
    stopQualityMonitoring();
    cancelReconnection();
    releaseHeartbeatLock();
  }

  function cleanupUiAfterCall(){
    try { localStorage.removeItem('pg_active_call_appt_id'); } catch(_){ }
    const stage = document.getElementById('video-call-stage');
    if(stage){
      const remote = document.getElementById('cc-remote-video');
      if(remote){ try { remote.srcObject = null; } catch(_){ } remote.remove(); }
      const indicators = document.getElementById('remote-media-indicators');
      if(indicators){ indicators.remove(); }
      updatePlaceholder('Sesión finalizada');
    }
    hideIncomingCallPrompt();
  }

  function showIncomingCallPrompt(session){
    hideIncomingCallPrompt();
    const stage = document.getElementById('video-call-stage'); if(!stage) return;
    const overlay = document.createElement('div');
    overlay.id='incoming-call-overlay';
    overlay.className='position-absolute top-50 start-50 translate-middle bg-white border rounded shadow p-3 d-flex flex-column align-items-center gap-2';
    overlay.style.zIndex='30';
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
    incomingPromptTimer = setInterval(()=>{
      remaining--; const t = overlay.querySelector('#incoming-call-timer'); if(t){ t.textContent = remaining+'s'; }
      if(remaining <= 0){
        clearInterval(incomingPromptTimer); incomingPromptTimer=null;
        try { ConnectyCube.videochat.reject(session, {}); } catch(_){ }
        finalizeCcCall(); updatePlaceholder('Tiempo agotado'); hideIncomingCallPrompt();
      }
    },1000);
    overlay.addEventListener('click', ev => {
      const btn = ev.target.closest('[data-incoming-action]'); if(!btn) return;
      const act = btn.getAttribute('data-incoming-action');
      if(act==='accept'){ tryAccept(session); }
      if(act==='reject'){
        try { ConnectyCube.videochat.reject(session, {}); } catch(_){ }
        finalizeCcCall(); updatePlaceholder('Llamada rechazada'); hideIncomingCallPrompt();
      }
    }, { once:false });
  }

  function hideIncomingCallPrompt(){
    try { if(incomingPromptTimer){ clearInterval(incomingPromptTimer); incomingPromptTimer=null; } } catch(_){ }
    const ov = document.getElementById('incoming-call-overlay'); if(ov){ try { ov.remove(); } catch(_){ } }
  }

  // ---- Quality Monitoring ----
  function toggleQualityPreference(btn){
    const enabled = !btn.classList.contains('btn-primary');
    if(enabled){ btn.classList.remove('btn-outline-secondary'); btn.classList.add('btn-primary'); startQualityMonitoring(); }
    else { btn.classList.remove('btn-primary'); btn.classList.add('btn-outline-secondary'); stopQualityMonitoring(); }
    try { localStorage.setItem('pg_call_show_quality', enabled ? '1' : '0'); } catch(_){ }
    const detailsBtn = document.querySelector('[data-call-action="show-quality-panel"]');
    if(detailsBtn){ detailsBtn.classList.toggle('d-none', !enabled); }
  }

  function maybeAutoEnableQuality(){
    try { const pref = localStorage.getItem('pg_call_show_quality'); if(pref === '1'){ const btn = document.querySelector('[data-call-action="toggle-quality"]'); if(btn){ toggleQualityPreference(btn); } } } catch(_){ }
  }

  function startQualityMonitoring(){
    if(qualityPollTimer){ return; }
    const indicator = document.getElementById('video-quality-indicator'); if(indicator){ indicator.classList.remove('d-none'); }
    qualityPollTimer = setInterval(collectQualityStats, 2500);
  }
  function stopQualityMonitoring(){
    if(qualityPollTimer){ clearInterval(qualityPollTimer); qualityPollTimer=null; }
    const indicator = document.getElementById('video-quality-indicator'); if(indicator){ indicator.classList.add('d-none'); }
    hideQualityPanel(); hideDegradedWarning(); lastVideoBytes=null; lastTimestamp=null; degradedCount=0;
  }

  function collectQualityStats(){
    if(!ccCurrentSession || !ccCallActive){ return; }
    try {
      // Attempt to retrieve peer connection for opponent
      let pc = null;
      const pid = ccOpponentId;
      if(ccCurrentSession.peerConnections){ pc = ccCurrentSession.peerConnections[pid]; }
      if(!pc){ pc = ccCurrentSession.pc || null; }
      if(!pc){ return; }
      pc.getStats(null).then(stats => {
        let videoInbound = null; let candidatePair = null; let fps = null; let width = null; let height = null;
        stats.forEach(r => {
          if(r.type === 'inbound-rtp' && r.kind === 'video' && !r.isRemote){ videoInbound = r; }
          if(r.type === 'candidate-pair' && r.state === 'succeeded'){ candidatePair = r; }
          if(r.type === 'track' && r.kind === 'video'){ fps = r.framesPerSecond; width = r.frameWidth; height = r.frameHeight; }
        });
        if(!videoInbound) return;
        const now = performance.now();
        if(lastVideoBytes == null){ lastVideoBytes = videoInbound.bytesReceived; lastTimestamp = now; return; }
        const deltaBytes = videoInbound.bytesReceived - lastVideoBytes;
        const deltaTime = (now - lastTimestamp)/1000; // seconds
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
          if(metrics && metrics.bitrateKbps >= 0){
            if(metrics.samples && metrics.samples.length){} // defensive
          }
          if(Array.isArray(metrics.samples)){}
        } catch(_){}
        const sample = { bitrateKbps, lossPct: lossRatio*100, rttMs: rtt*1000 };
        metrics.samples.push(sample);
        if(metrics.samples.length > MAX_SAMPLES){ metrics.samples.shift(); }
      }).catch(()=>{});
    } catch(_){ }
  }

  function classifyQuality(m){
    if(m.bitrateKbps > 1500 && m.lossRatio < 0.02 && m.rtt < 0.15) return 'excellent';
    if(m.bitrateKbps > 600 && m.lossRatio < 0.08 && m.rtt < 0.3) return 'acceptable';
    return 'degraded';
  }

  function updateQualityIndicator(metrics){
    const indicator = document.getElementById('video-quality-indicator'); if(!indicator) return;
    const state = classifyQuality(metrics);
    let color = '#6c757d';
    if(state === 'excellent') color = '#28a745';
    else if(state === 'acceptable') color = '#ffc107';
    else color = '#dc3545';
    indicator.style.background = color;
    indicator.title = `Bitrate: ${metrics.bitrateKbps.toFixed(0)} kbps\nPérdida: ${(metrics.lossRatio*100).toFixed(1)}%\nRTT: ${(metrics.rtt*1000).toFixed(0)} ms\nEstado: ${state}`;
    if(state === 'degraded'){ degradedCount++; if(degradedCount === 3){ metrics.degraded_sequences++; } if(degradedCount >= 3){ showDegradedWarning(); } }
    else { degradedCount = 0; hideDegradedWarning(); }
  }

  function showDegradedWarning(){
    const w = document.getElementById('video-quality-warning'); if(!w) return; w.classList.remove('d-none');
  }
  function hideDegradedWarning(){
    const w = document.getElementById('video-quality-warning'); if(!w) return; w.classList.add('d-none');
  }

  function toggleQualityPanel(){
    const panel = document.getElementById('video-quality-panel'); if(!panel) return;
    panel.classList.toggle('d-none');
  }
  function hideQualityPanel(){
    const panel = document.getElementById('video-quality-panel'); if(panel){ panel.classList.add('d-none'); }
  }

  function updateQualityPanel(m){
    const panel = document.getElementById('video-quality-panel'); if(!panel || panel.classList.contains('d-none')) return;
    const set = (k,v)=>{ const el = panel.querySelector(`[data-q="${k}"]`); if(el){ el.textContent = v; } };
    set('bitrate', m.bitrateKbps.toFixed(0)+' kbps');
    set('loss', (m.lossRatio*100).toFixed(1)+'%');
    set('rtt', (m.rtt*1000).toFixed(0)+' ms');
    set('resolution', (m.width||'?')+'x'+(m.height||'?'));
    set('fps', m.fps != null ? m.fps : '-');
  }

  // ---- Reconnection logic ----
  function scheduleReconnect(reason){
    if(reconnecting || manualEnd || sessionCompleted){ return; }
    if(retryCount >= maxRetries){ updatePlaceholder('Reconexión agotada'); return; }
    reconnecting = true;
    showReconnectUi(reason);
    const delay = reconnectBackoffMs[Math.min(retryCount, reconnectBackoffMs.length-1)];
    if(reconnectTimer){ clearTimeout(reconnectTimer); }
    reconnectTimer = setTimeout(()=>{ attemptReconnect(); }, delay);
  }

  function attemptReconnect(){
    if(manualEnd || sessionCompleted){ return; }
    retryCount++;
    metrics.total_retries = retryCount;
    updateReconnectAttemptText();
    // Tear down existing session state (without marking manual end)
    try { ConnectyCube.videochat.stop(); } catch(_){ }
    ccCallActive = false;
    // Recreate call: if we were initiator originally (myInt < oppInt) placeCall() else wait small window then fallback to placeCall
    const myInt = parseInt(currentUserId||'0',10); const oppInt = parseInt(otherUserId||'0',10);
    if(myInt < oppInt){ placeCall(); }
    else {
      updatePlaceholder('Esperando nueva llamada...');
      setTimeout(()=>{ if(!ccCallActive && reconnecting){ placeCall(); } }, 4000);
    }
    // If this attempt fails further states will trigger another scheduleReconnect
    if(retryCount >= maxRetries){ /* final attempt - if fails, placeholder will show failure */ }
  }

  function cancelReconnection(){
    reconnecting = false; if(reconnectTimer){ clearTimeout(reconnectTimer); reconnectTimer=null; }
    hideReconnectUi();
  }

  function showReconnectUi(reason){
    const stage = document.getElementById('video-call-stage'); if(!stage) return;
    let box = document.getElementById('reconnect-box');
    if(!box){
      box = document.createElement('div'); box.id='reconnect-box';
      box.className='position-absolute top-50 start-50 translate-middle bg-white border rounded shadow p-2 d-flex flex-column align-items-center gap-1';
      box.style.zIndex='35';
      box.innerHTML = `
        <div class="small fw-semibold">Reconectando...</div>
        <div class="small" data-r="attempt">Razón: ${reason}</div>
        <div class="small" data-r="count">Intento 0/${maxRetries}</div>
        <button type="button" class="btn btn-sm btn-outline-danger" data-call-action="cancel-reconnect">Cancelar</button>
      `;
      stage.appendChild(box);
    } else {
      const r = box.querySelector('[data-r="attempt"]'); if(r){ r.textContent = 'Razón: '+reason; }
    }
    updateReconnectAttemptText();
  }

  function updateReconnectAttemptText(){
    const box = document.getElementById('reconnect-box'); if(!box) return;
    const c = box.querySelector('[data-r="count"]'); if(c){ c.textContent = `Intento ${retryCount}/${maxRetries}`; }
  }

  function hideReconnectUi(){
    const box = document.getElementById('reconnect-box'); if(box){ try { box.remove(); } catch(_){ } }
  }

  function sendMediaState(){
    if(!ccCurrentSession || !ccCallActive) return;
    if(mediaStateSendDebounce){ clearTimeout(mediaStateSendDebounce); }
    mediaStateSendDebounce = setTimeout(()=>{
      try {
        const audioEnabled = ccLocalStream ? ccLocalStream.getAudioTracks().some(t=>t.enabled) : false;
        const videoEnabled = ccLocalStream ? ccLocalStream.getVideoTracks().some(t=>t.enabled) : false;
        // Attempt to send message via videochat signalling
        try { ConnectyCube.videochat.sendMessage(ccCurrentSession, ccOpponentId, { type:'media_state', audioEnabled, videoEnabled }); } catch(e){ /* fallback ignored */ }
      } catch(_){ }
    }, 120);
  }

  function updateRemoteMediaIndicators(state){
    try {
      const micEl = document.querySelector('#remote-media-indicators [data-remote-mic]');
      const camEl = document.querySelector('#remote-media-indicators [data-remote-cam]');
      if(micEl){ micEl.textContent = state.audioEnabled ? 'Mic On' : 'Mic Off'; }
      if(camEl){ camEl.textContent = state.videoEnabled ? 'Cam On' : 'Cam Off'; }
    } catch(_){ }
  }

  // Wrap completeSession to ensure call teardown
  const _origComplete = completeSession;
  completeSession = async function(){
    // Compute and submit metrics summary once before completion
    if(!metricsSubmitted){
      try {
        metrics.duration_seconds = Math.round((performance.now() - sessionStartPerf)/1000);
        const samples = metrics.samples;
        const n = samples.length || 0;
        if(n > 0){
          let sumBit=0, sumLoss=0, sumRtt=0;
          for(const s of samples){ sumBit += (s.bitrateKbps||0); sumLoss += (s.lossPct||0); sumRtt += (s.rttMs||0); }
          metrics.avg_bitrate_kbps = +(sumBit/n).toFixed(1);
          metrics.avg_loss_pct = +(sumLoss/n).toFixed(2);
          metrics.avg_rtt_ms = +(sumRtt/n).toFixed(1);
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
          const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest', ...(csrf?{'X-CSRF-TOKEN':csrf}:{}) }, body });
          sent = r.ok;
        } catch(e){ sent=false; }
        if(!sent && navigator.sendBeacon){ try { const blob = new Blob([body], { type:'application/json' }); navigator.sendBeacon(url, blob); } catch(_){ } }
        metricsSubmitted = true;
      } catch(e){ /* ignore metric errors */ }
    }
    finalizeCcCall();
    await _origComplete();
    releaseHeartbeatLock();
  };
}


// Auto reopen helper: call on page init
export function autoOpenOngoingAppointmentCall(){
  try {
    const id = localStorage.getItem('pg_active_call_appt_id');
    if(!id) return;
    // Ensure appointment still visible and within time window if we have start/end data
    const wrap = document.getElementById('pg-next-appt');
    if(!wrap) return;
    const startIso = wrap.getAttribute('data-start');
    const endIso = wrap.getAttribute('data-end');
    const now = Date.now();
    let okWindow = true;
    try {
      if(startIso){ const s = new Date(startIso).getTime(); if(!isNaN(s) && now < s - 15*60*1000) okWindow = false; }
      if(endIso){ const e = new Date(endIso).getTime(); if(!isNaN(e) && now > e + 10*60*1000) okWindow = false; }
    } catch(_){}
    if(!okWindow){ localStorage.removeItem('pg_active_call_appt_id'); return; }
    const role = wrap.getAttribute('data-patient-id') ? 'profesional' : 'paciente';
    const otherUserId = role === 'profesional' ? wrap.getAttribute('data-patient-id') : wrap.getAttribute('data-professional-id');
    openAppointmentCall({ id, otherUserId, role });
  } catch(_){}
}
// Optional global assignment for legacy callers
try { window.openAppointmentCall = openAppointmentCall; } catch(_){}
