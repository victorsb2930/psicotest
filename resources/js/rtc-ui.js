// UI centralizada para videollamadas (en español): modal + ringtone + timeout
// Depende de: modalConfirm, modalNotification (ya existentes en el proyecto)

let state = {
  modalId: 'cc-call-ui',
  mode: null, // 'incoming' | 'outgoing' | 'connected'
  timeoutId: null,
  audioCtx: null,
  osc: null,
  gain: null,
  session: null,
};

function createModalBody() {
  return `
    <div class="text-center">
      <div class="row g-2 align-items-center justify-content-center">
        <div class="col-5">
          <video id="cc-ui-local" autoplay muted playsinline style="width:100%; border-radius:8px; background:#000"></video>
        </div>
        <div class="col-7">
          <video id="cc-ui-remote" autoplay playsinline style="width:100%; border-radius:8px; background:#000"></video>
        </div>
      </div>
      <div class="mt-3 d-flex gap-2 justify-content-center" id="cc-ui-controls"></div>
    </div>`;
}

function openModal(title) {
  const cfg = { title, body: createModalBody(), modalId: state.modalId, buttons: [] };
  (modalConfirm || modalNotification)?.(cfg, 'large');
}

function elLocal() { return document.getElementById('cc-ui-local'); }
function elRemote() { return document.getElementById('cc-ui-remote'); }
function elControls() { return document.getElementById('cc-ui-controls'); }

function attachStreamToVideo(el, stream) {
  if (!el) return;
  try {
    if ('srcObject' in el) el.srcObject = stream; else el.src = URL.createObjectURL(stream);
    const p = el.play && el.play(); if (p && typeof p.catch === 'function') p.catch(()=>{});
  } catch (_) {}
}

function clearTimeoutIfAny(){ try { if (state.timeoutId) { clearTimeout(state.timeoutId); state.timeoutId = null; } } catch(_){} }

function closeModal(){
  try {
    const m = document.getElementById(state.modalId);
    if (m) { const btn = m.querySelector('[data-bs-dismiss="modal"], .btn-close'); btn ? btn.click() : (m.remove && m.remove()); }
  } catch(_){}
}

function startRingtone() {
  try {
    if (state.audioCtx) stopRingtone();
    const AudioCtx = window.AudioContext || window.webkitAudioContext; if (!AudioCtx) return;
    state.audioCtx = new AudioCtx();
    state.osc = state.audioCtx.createOscillator();
    state.gain = state.audioCtx.createGain();
    state.osc.type = 'sine';
    state.osc.frequency.setValueAtTime(440, state.audioCtx.currentTime);
    state.gain.gain.value = 0.03; // volumen bajo
    state.osc.connect(state.gain); state.gain.connect(state.audioCtx.destination);
    state.osc.start();
    // Patrón de ring: 1s on, 1s off
    const toggle = ()=>{
      if (!state.audioCtx) return;
      const v = state.gain.gain.value;
      state.gain.gain.setValueAtTime(v > 0 ? 0 : 0.03, state.audioCtx.currentTime);
    };
    state._ringTimer = setInterval(toggle, 1000);
  } catch(_){}
}

function stopRingtone(){
  try { if (state._ringTimer) { clearInterval(state._ringTimer); state._ringTimer = null; } } catch(_){}
  try { if (state.osc) { state.osc.stop(); state.osc.disconnect(); } } catch(_){}
  try { if (state.gain) state.gain.disconnect(); } catch(_){}
  try { if (state.audioCtx && state.audioCtx.state !== 'closed') state.audioCtx.close(); } catch(_){}
  state.osc = null; state.gain = null; state.audioCtx = null;
}

function setControls(html){ const c = elControls(); if (c) c.innerHTML = html; }

const RtcUI = {
  showIncoming(session, { onAccept, onReject, timeoutMs = 30000, nombre = 'Contacto' } = {}){
    state.mode = 'incoming'; state.session = session; clearTimeoutIfAny();
    openModal('Llamada entrante');
    setControls(`
      <button id="cc-ui-accept" class="btn btn-success btn-sm">Aceptar</button>
      <button id="cc-ui-reject" class="btn btn-outline-secondary btn-sm">Rechazar</button>
    `);
    startRingtone();
    // wire
    setTimeout(()=>{
      const acc = document.getElementById('cc-ui-accept');
      const rej = document.getElementById('cc-ui-reject');
      if (acc) acc.addEventListener('click', async ()=>{ try { stopRingtone(); if (onAccept) await onAccept(); } catch(e){ modalNotification?.('Atención','No se pudo acceder a la cámara/mic.',{template:'danger'}); }});
      if (rej) rej.addEventListener('click', ()=>{ try { stopRingtone(); if (onReject) onReject(); } catch(_){} });
    }, 50);
    // timeout auto-rechazar
    state.timeoutId = setTimeout(()=>{ try { stopRingtone(); if (onReject) onReject(); } catch(_){} }, Math.max(5000, timeoutMs));
  },

  showOutgoing(session, { onCancel, timeoutMs = 30000, nombre = 'Contacto' } = {}){
    state.mode = 'outgoing'; state.session = session; clearTimeoutIfAny();
    openModal('Llamando a ' + nombre);
    // Usamos el mismo patrón de "Colgar" que en conectado para coherencia visual
    setControls(`<button id="cc-ui-end" class="btn btn-danger btn-sm"><i class="bi bi-telephone-x"></i> Colgar</button>`);
    startRingtone();
    setTimeout(()=>{ const c = document.getElementById('cc-ui-end'); if (c) c.addEventListener('click', ()=>{ try { stopRingtone(); if (onCancel) onCancel(); } catch(_){} }); }, 50);
    state.timeoutId = setTimeout(()=>{ try { stopRingtone(); if (onCancel) onCancel(); } catch(_){} }, Math.max(5000, timeoutMs));
  },

  setLocalStream(stream){ try { attachStreamToVideo(elLocal(), stream); } catch(_){} },
  onRemoteStream(stream){ try { attachStreamToVideo(elRemote(), stream); } catch(_){} },

  showConnected(){
    state.mode = 'connected'; clearTimeoutIfAny(); stopRingtone();
    setControls(`<button id="cc-ui-end" class="btn btn-danger btn-sm"><i class="bi bi-telephone-x"></i> Colgar</button>`);
  },

  onEnd(cb){
    // attach once to current end button if present
    setTimeout(()=>{ const b = document.getElementById('cc-ui-end'); if (b) b.addEventListener('click', ()=>{ try { if (cb) cb(); } catch(_){} }); }, 50);
  },

  end(){ clearTimeoutIfAny(); stopRingtone(); closeModal(); state.mode = null; state.session = null; },
};

export default RtcUI;
