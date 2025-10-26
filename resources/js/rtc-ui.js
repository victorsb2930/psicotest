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
	localStream: null,
	audioEnabled: true,
	videoEnabled: true,
  endCb: null,
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
		const p = el.play && el.play(); if (p && typeof p.catch === 'function') p.catch(() => { });
	} catch (_) { }
}

function clearTimeoutIfAny() { try { if (state.timeoutId) { clearTimeout(state.timeoutId); state.timeoutId = null; } } catch (_) { } }

function closeModal() {
	try {
		const m = document.getElementById(state.modalId);
		if (m) {
			const btn = m.querySelector('[data-bs-dismiss="modal"], .btn-close');
			if (btn) {
				btn.click();
			} else {
				// Remove modal node if no dismiss button is available
				if (typeof m.remove === 'function') m.remove();
			}
		}
	} catch (_) { }
	// Hard cleanup in case the helper didn't remove Bootstrap's backdrop/body lock
	try { document.querySelectorAll('.modal-backdrop').forEach(el => el?.parentNode?.removeChild(el)); } catch (_) { }
	try { document.body.classList.remove('modal-open'); } catch (_) { }
	try { document.body.style.removeProperty('padding-right'); } catch (_) { }
	try { document.body.style.overflow = ''; } catch (_) { }
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
		const toggle = () => {
			if (!state.audioCtx) return;
			const v = state.gain.gain.value;
			state.gain.gain.setValueAtTime(v > 0 ? 0 : 0.03, state.audioCtx.currentTime);
		};
		state._ringTimer = setInterval(toggle, 1000);
	} catch (_) { }
}

function stopRingtone() {
	try { if (state._ringTimer) { clearInterval(state._ringTimer); state._ringTimer = null; } } catch (_) { }
	try { if (state.osc) { state.osc.stop(); state.osc.disconnect(); } } catch (_) { }
	try { if (state.gain) state.gain.disconnect(); } catch (_) { }
	try { if (state.audioCtx && state.audioCtx.state !== 'closed') state.audioCtx.close(); } catch (_) { }
	state.osc = null; state.gain = null; state.audioCtx = null;
}

function setControls(html) { const c = elControls(); if (c) c.innerHTML = html; }

function computeMediaStatesFromStream(strm) {
	try {
		const a = (strm?.getAudioTracks?.() || []).some(t => t?.enabled !== false);
		const v = (strm?.getVideoTracks?.() || []).some(t => t?.enabled !== false);
		return { audioEnabled: a, videoEnabled: v };
	} catch (_) { return { audioEnabled: true, videoEnabled: true }; }
}

function setAudioEnabled(on) {
	try { (state.localStream?.getAudioTracks?.() || []).forEach(t => { try { t.enabled = !!on; } catch (_) { } }); } catch (_) { }
	state.audioEnabled = !!on;
	renderConnectedControls();
}

function setVideoEnabled(on) {
	try { (state.localStream?.getVideoTracks?.() || []).forEach(t => { try { t.enabled = !!on; } catch (_) { } }); } catch (_) { }
	state.videoEnabled = !!on;
	renderConnectedControls();
}

function toggleAudio() { setAudioEnabled(!state.audioEnabled); }
function toggleVideo() { setVideoEnabled(!state.videoEnabled); }

function renderConnectedControls() {
	if (state.mode !== 'connected') return;
	const micIcon = state.audioEnabled ? 'bi-mic' : 'bi-mic-mute';
	const micClass = state.audioEnabled ? 'btn-outline-secondary' : 'btn-outline-danger';
	const camIcon = state.videoEnabled ? 'bi-camera-video' : 'bi-camera-video-off';
	const camClass = state.videoEnabled ? 'btn-outline-secondary' : 'btn-outline-danger';
	setControls(`
		<div class="d-flex gap-2">
			<button id="cc-ui-toggle-audio" class="btn btn-sm ${micClass}" title="${state.audioEnabled ? 'Silenciar micrófono' : 'Activar micrófono'}"><i class="bi ${micIcon}"></i></button>
			<button id="cc-ui-toggle-video" class="btn btn-sm ${camClass}" title="${state.videoEnabled ? 'Desactivar cámara' : 'Activar cámara'}"><i class="bi ${camIcon}"></i></button>
			<button id="cc-ui-end" class="btn btn-danger btn-sm"><i class="bi bi-telephone-x"></i> Colgar</button>
		</div>
	`);
	// wire
	setTimeout(() => {
		const mic = document.getElementById('cc-ui-toggle-audio');
		const cam = document.getElementById('cc-ui-toggle-video');
		if (mic) mic.addEventListener('click', (e) => { e.preventDefault(); toggleAudio(); });
		if (cam) cam.addEventListener('click', (e) => { e.preventDefault(); toggleVideo(); });
		// Always (re)wire end button after re-render
		const endBtn = document.getElementById('cc-ui-end');
		if (endBtn) endBtn.addEventListener('click', ()=>{
			try { if (typeof state.endCb === 'function') state.endCb(); else if (state.session && state.session.stop) state.session.stop({}, ()=>{}); } catch(_){}
			try { RtcUI.end(); } catch(_){}
		});
	}, 50);
}

const RtcUI = {
	showIncoming(session, { onAccept, onReject, timeoutMs = 30000, nombre = 'Contacto' } = {}) {
		state.mode = 'incoming'; state.session = session; clearTimeoutIfAny();
		openModal('Llamada entrante');
		setControls(`
      <button id="cc-ui-accept" class="btn btn-success btn-sm">Aceptar</button>
      <button id="cc-ui-reject" class="btn btn-outline-secondary btn-sm">Rechazar</button>
    `);
		startRingtone();
		// wire
		setTimeout(() => {
			const acc = document.getElementById('cc-ui-accept');
			const rej = document.getElementById('cc-ui-reject');
			if (acc) acc.addEventListener('click', async () => { try { stopRingtone(); if (onAccept) await onAccept(); } catch (e) { modalNotification?.('Atención', 'No se pudo acceder a la cámara/mic.', { template: 'danger' }); } });
			if (rej) rej.addEventListener('click', () => { try { stopRingtone(); if (onReject) onReject(); } catch (_) { } });
		}, 50);
		// timeout auto-rechazar
		state.timeoutId = setTimeout(() => { try { stopRingtone(); if (onReject) onReject(); } catch (_) { } }, Math.max(5000, timeoutMs));
	},

	showOutgoing(session, { onCancel, timeoutMs = 30000, nombre = 'Contacto' } = {}) {
		state.mode = 'outgoing'; state.session = session; clearTimeoutIfAny();
		openModal('Llamando a ' + nombre);
		// Usamos el mismo patrón de "Colgar" que en conectado para coherencia visual
		setControls(`<button id="cc-ui-end" class="btn btn-danger btn-sm"><i class="bi bi-telephone-x"></i> Colgar</button>`);
		startRingtone();
		setTimeout(() => { const c = document.getElementById('cc-ui-end'); if (c) c.addEventListener('click', () => { try { stopRingtone(); if (onCancel) onCancel(); } catch (_) { } }); }, 50);
		state.timeoutId = setTimeout(() => { try { stopRingtone(); if (onCancel) onCancel(); } catch (_) { } }, Math.max(5000, timeoutMs));
	},

	setLocalStream(stream) { try { state.localStream = stream; const st = computeMediaStatesFromStream(stream); state.audioEnabled = st.audioEnabled; state.videoEnabled = st.videoEnabled; attachStreamToVideo(elLocal(), stream); if (state.mode === 'connected') renderConnectedControls(); } catch (_) { } },
	onRemoteStream(stream) { try { attachStreamToVideo(elRemote(), stream); } catch (_) { } },

	showConnected() {
		state.mode = 'connected'; clearTimeoutIfAny(); stopRingtone();
		// If we already have a localStream, derive the initial toggle state; otherwise use defaults
		try { if (state.localStream) { const st = computeMediaStatesFromStream(state.localStream); state.audioEnabled = st.audioEnabled; state.videoEnabled = st.videoEnabled; } } catch (_) { }
		renderConnectedControls();
	},

	onEnd(cb) {
			// remember callback and (re)wire button now and on future re-renders
			state.endCb = cb;
			setTimeout(()=>{
				const b = document.getElementById('cc-ui-end');
				if (b) b.addEventListener('click', () => {
					try { if (typeof state.endCb === 'function') state.endCb(); else if (state.session && state.session.stop) state.session.stop({}, ()=>{}); } catch(_){}
					try { RtcUI.end(); } catch(_){}
				});
			}, 50);
	},

	  end() { clearTimeoutIfAny(); stopRingtone(); closeModal(); state.mode = null; state.session = null; state.localStream = null; state.audioEnabled = true; state.videoEnabled = true; state.endCb = null; },
};

export default RtcUI;
