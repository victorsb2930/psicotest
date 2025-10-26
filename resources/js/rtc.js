// Global RTC bootstrap + chat connect, to receive incoming calls anywhere
import ConnectyCube from 'connectycube';
import RtcUI from './rtc-ui';

(function(){
  let ccInitialized = false;
  let ccConnected = false;
  let ccConnecting = false;
  let localStream = null;
  let currentSession = null;

  function ccConfigOk(){
    // Solo validamos credenciales de app y la identidad del usuario; la contraseña puede omitirse si usamos token de sesión
    try{ const c = window.__ccConfig; const u = window.__ccUser; return !!(c && c.appId && c.authKey && u && (u.userId||u.login)); }catch(_){ return false; }
  }

  async function bootstrap(){
    if (ccConfigOk()) return true;
    try{
      const r = await fetch('/rtc/bootstrap', { headers: { 'Accept':'application/json' }, credentials: 'include' });
      if(!r.ok) return false; const j = await r.json(); if(!j || !j.ok) return false;
      if (j.ccConfig) window.__ccConfig = j.ccConfig;
      if (j.ccUser) window.__ccUser = j.ccUser;
      if (j.userIdMap) window.__ccUserIdMap = j.userIdMap;
      return ccConfigOk();
    }catch(_){ return false; }
  }

  async function ensureInit(){
    if (ccInitialized) return;
    const cfg = window.__ccConfig || {};
    const initParams = { appId: cfg.appId, authKey: cfg.authKey };
    if (cfg.authSecret) initParams.authSecret = cfg.authSecret; // tolerar paneles sin secret explícito
    console.info('[RTC] init', { appId: initParams.appId, hasAuthKey: !!initParams.authKey, hasSecret: !!initParams.authSecret });
    ConnectyCube.init(initParams);
    // Configurar endpoints regionales si están definidos (usar hostnames sin esquema)
    try{
      if (cfg.apiEndpoint || cfg.chatEndpoint) {
        const sanitizeHost = (v)=>{
          try{
            if (!v) return '';
            // elimina esquema y trailing slash
            v = String(v).replace(/^https?:\/\//i,'').replace(/\/$/,'');
            return v;
          }catch(_){ return v; }
        };
        const endpoints = {};
        if (cfg.apiEndpoint) endpoints.api = sanitizeHost(cfg.apiEndpoint);
        if (cfg.chatEndpoint) endpoints.chat = sanitizeHost(cfg.chatEndpoint);
        // Derivar MUC desde el host de chat si no viene explícito
        if (endpoints.chat) {
          let mucHost = endpoints.chat.includes('chat-')
            ? endpoints.chat.replace('chat-','muc.chat-')
            : endpoints.chat.replace(/^chat\./,'muc.chat.');
          endpoints.muc = mucHost;
        }
        console.info('[RTC] setConfig endpoints', endpoints);
        // Nota: para cambiar API base, el SDK usa set({ endpoints }), no setConfig
        try { ConnectyCube.set({ endpoints }); } catch(_) {}
      }
    }catch(_){ }
    ccInitialized = true;
  }

  async function ensureLocalStream(){
    if (localStream) return localStream;
    const constraints = { audio:true, video:{ width:{ ideal:1280 }, height:{ ideal:720 } } };
    if (ConnectyCube?.videochat?.getUserMedia){
      localStream = await ConnectyCube.videochat.getUserMedia(constraints);
      return localStream;
    }
    const s = await navigator.mediaDevices.getUserMedia(constraints); localStream = s; return s;
  }

  async function ensureConnected(){
    if (ccConnected || ccConnecting) return;
    ccConnecting = true;
    await ensureInit();
    const user = window.__ccUser || {};
    let ccUserId = user.userId || null;
  let sessionToken = null; let lastLoginSession = null; let hasUserToken = false; let discoveredCcId = null;
    // First, try to obtain an app session token; if this fails (422), config is likely invalid
    try{
      console.info('[RTC] createSession...');
      const sess = await ConnectyCube.createSession();
      sessionToken = (sess && (sess.token || sess.session?.token)) || null;
      console.info('[RTC] createSession OK (app session)');
    }catch(e){
      console.warn('[RTC] createSession failed. Revisa CONNECTYCUBE_APP_ID/AUTH_KEY/AUTH_SECRET en .env', e);
      // Without a session token, don't attempt chat connect with default password (likely wrong)
    }
    try{
      if (user.login && user.password){
        try{
          // Try credentialed session to also discover the CC user id
          lastLoginSession = await ConnectyCube.createSession({ login:user.login, password:user.password });
          sessionToken = (lastLoginSession && (lastLoginSession.token || lastLoginSession.session?.token)) || sessionToken;
          // extrae el CC user id de distintas formas según SDK
          discoveredCcId = (lastLoginSession.user && lastLoginSession.user.id) || lastLoginSession.user_id || lastLoginSession.session?.user_id || null;
          if (discoveredCcId) ccUserId = discoveredCcId;
          hasUserToken = !!((lastLoginSession && (lastLoginSession.user || lastLoginSession.user_id)));
          // Persist discovered identifiers to backend for future mapping
          try {
            const csrf = (document.querySelector('meta[name="csrf-token"]')||{}).getAttribute?.('content') || '';
            fetch('/rtc/sync', { method:'POST', headers:{ 'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN': csrf }, credentials:'include', body: JSON.stringify({ cc_user_id: ccUserId, cc_login: user.login }) });
          } catch(_){ }
        } catch(e1){
          // If credentialed session failed:
          // - 401 => bad credentials (user exists with other password)
          // - 422 => validation (e.g., invalid params)
          // Avoid blind signup loops; only attempt signup if error is NOT a validation/duplicate login
          const code = (e1 && (e1.code || e1.status)) || 0;
          const isValidation = code === 422;
          const isUnauthorized = code === 401;
          const msg = isValidation ? 'validation' : (isUnauthorized ? 'unauthorized' : 'other');
          if (msg !== 'validation'){
            try{
              const fullname = (window.__authUserName || ('User ' + (user.userId||'')));
              await ConnectyCube.users.signup({ login:user.login, password:user.password, full_name:String(fullname) });
              lastLoginSession = await ConnectyCube.createSession({ login:user.login, password:user.password });
              sessionToken = (lastLoginSession && (lastLoginSession.token || lastLoginSession.session?.token)) || sessionToken;
              discoveredCcId = (lastLoginSession.user && lastLoginSession.user.id) || lastLoginSession.user_id || lastLoginSession.session?.user_id || null;
              if (discoveredCcId) ccUserId = discoveredCcId;
              hasUserToken = !!((lastLoginSession && (lastLoginSession.user || lastLoginSession.user_id)));
              try {
                const csrf = (document.querySelector('meta[name="csrf-token"]')||{}).getAttribute?.('content') || '';
                fetch('/rtc/sync', { method:'POST', headers:{ 'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN': csrf }, credentials:'include', body: JSON.stringify({ cc_user_id: ccUserId, cc_login: user.login }) });
              } catch(_){ }
            }catch(e2){ console.warn('CC signup/login failed', e1, e2); }
          } else {
            console.warn('[RTC] Login de CC falló (422). El usuario puede existir con otra contraseña. Usaremos sessionToken si está disponible.');
          }
        }
      }
      if (!ccUserId) ccUserId = user.userId || null;
      console.info('[RTC] using ccUserId =', ccUserId, 'login =', user.login);
      // Prefer session token for XMPP auth to avoid password mismatches
      if (sessionToken && hasUserToken){
        try{
          console.info('[RTC] chat.connect via user token...');
          await ConnectyCube.chat.connect({ userId: ccUserId, password: sessionToken });
          await waitForChatConnected();
          ccConnected = true; console.info('[RTC] chat.connect OK');
        }
        catch(e3){
          // as a fallback, try the provided password if present
          if (user.password){
            await safeChatDisconnect();
            console.info('[RTC] chat.connect via password fallback...');
            await ConnectyCube.chat.connect({ userId: ccUserId, password: user.password });
            await waitForChatConnected();
            ccConnected = true;
          }
          else throw e3;
        }
      } else {
        // No token; only attempt password connect if we explicitly have one
        if (user.password){
          await safeChatDisconnect();
          console.info('[RTC] chat.connect via password (no token)...');
          await ConnectyCube.chat.connect({ userId: ccUserId, password: user.password });
          await waitForChatConnected();
          ccConnected = true;
        }
        else throw new Error('No session token y sin contraseña de CC');
      }
    }catch(e){ console.warn('CC chat connect failed', e); throw e; }
    finally { ccConnecting = false; }
  }

  async function waitForChatConnected(timeoutMs=8000){
    const deadline = Date.now() + Math.max(1000, timeoutMs);
    while (Date.now() < deadline){
      try { if (ConnectyCube?.chat?.isConnected) return true; } catch(_){ }
      await new Promise(r=>setTimeout(r, 200));
    }
    throw new Error('Chat connect timeout');
  }

  async function safeChatDisconnect(){
    try{ if (ConnectyCube?.chat?.isConnected) { await ConnectyCube.chat.disconnect(); } }catch(_){ }
    await new Promise(r=>setTimeout(r, 300));
  }

  function attachGlobalRtcUiListeners(){
    if (!ConnectyCube?.videochat) return;
    // Centralized, one-time UI listeners using RtcUI
    ConnectyCube.videochat.onCallListener = function(session){
      currentSession = session;
      RtcUI.showIncoming(session, {
        onAccept: async () => {
          try { await ensureLocalStream(); RtcUI.setLocalStream(localStream); } catch(_) {}
          session.accept({}, (err)=>{ if (err) console.warn('accept error', err); });
          RtcUI.showConnected();
          RtcUI.onEnd(()=>{ try{ if(currentSession) currentSession.stop({},()=>{}); }catch(_){} });
        },
        onReject: () => {
          try { session.reject({},()=>{}); } catch(_) {}
          RtcUI.end(); currentSession = null;
        }
      });
    };
    ConnectyCube.videochat.onRemoteStreamListener = function(session, _userId, remoteStream){
      if (currentSession && session.ID !== currentSession.ID) return;
      RtcUI.onRemoteStream(remoteStream);
    };
    ConnectyCube.videochat.onAcceptCallListener = function(session){
      currentSession = session;
      RtcUI.showConnected();
      RtcUI.onEnd(()=>{ try{ if(currentSession) currentSession.stop({},()=>{}); }catch(_){} });
    };
    ConnectyCube.videochat.onStopCallListener = function(){
      RtcUI.end();
      try{ if(localStream){ localStream.getTracks()?.forEach(t=>{ try{ t.stop(); }catch(_){} }); } }catch(_){}
      localStream = null; currentSession = null;
    };
  }

  async function start(){
    try { window.__rtcBootstrapReady = false; } catch(_){}
    const p = (async ()=>{
      console.info('[RTC] bootstrap start');
      const ok = await bootstrap();
      if (!ok){ console.warn('[RTC] bootstrap failed'); try { window.__rtcBootstrapReady = false; } catch(_){ } return false; }
      let connected = false;
      try { await ensureConnected(); connected = true; } catch(e){ console.warn('[RTC] ensureConnected failed', e); connected = false; }
      try { window.__rtcBootstrapReady = !!(ok && connected && ccConnected); console.info('[RTC] ready =', window.__rtcBootstrapReady); } catch(_){ }
      try { attachGlobalRtcUiListeners(); } catch(_){ }
      return !!(ok && connected);
    })();
    try { window.__rtcBootstrapPromise = p; } catch(_){}
    await p;
  }

  // Start immediately to avoid missing DOMContentLoaded (e.g., with PJAX or late load)
  try {
    if (!window.__rtcBootstrapPromise) start();
  } catch(_) { start(); }
  // Also start on DOMContentLoaded as a fallback when scripts are bundled differently
  document.addEventListener('DOMContentLoaded', ()=>{ try { if (!window.__rtcBootstrapPromise) start(); } catch(_){} });
  // Expose global helper to avoid duplicating media access logic per page
  try { window.__rtcGetLocalStream = ensureLocalStream; } catch(_){}
})();
