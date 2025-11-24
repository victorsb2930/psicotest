import { io } from 'socket.io-client';

// Simple WebRTC signaling client using Socket.IO
// Usage:
//  const s = createSignalingClient({ url: 'https://your-signal:4000' });
//  await s.connect();
//  await s.joinRoom(roomId, localStream, { onRemoteStream, onConnected });

export default function createSignalingClient(opts = {}) {
  const defaultCandidates = [];
  // If user provided explicit URL use it first
  if (opts.url) defaultCandidates.push(opts.url);
  // Allow overriding via global before scripts
  if (window.__SIGNALING_URL) defaultCandidates.push(window.__SIGNALING_URL);
  // Common fallbacks
  defaultCandidates.push(location.protocol + '//' + location.hostname + ':4000');
  defaultCandidates.push(location.protocol + '//127.0.0.1:4000');
  // Docker for Windows host mapping
  defaultCandidates.push(location.protocol + '//' + 'host.docker.internal:4000');
  // Normalize unique list
  const urlCandidates = Array.from(new Set(defaultCandidates));
  let socket = null;
  const pcs = new Map(); // remoteId -> RTCPeerConnection

  function _attachCommonHandlers(s) {
    s.on('connect', () => { console.debug('[Signaler] connected', s.id); });
    s.on('disconnect', () => { console.debug('[Signaler] disconnected'); cleanupAll(); });
    s.on('peer-joined', ({ id }) => { try { if (typeof onPeerJoined === 'function') onPeerJoined(id); } catch (_) { } });
    s.on('peer-left', ({ id }) => { try { const pc = pcs.get(id); if (pc) { try { pc.close(); } catch (_) { } pcs.delete(id); } if (typeof onPeerLeft === 'function') onPeerLeft(id); } catch (_) { } });
    s.on('offer', async ({ from, sdp }) => { try { await handleOffer(from, sdp); } catch (e) { console.warn('handleOffer error', e); } });
    s.on('answer', async ({ from, sdp }) => { try { await handleAnswer(from, sdp); } catch (e) { console.warn('handleAnswer error', e); } });
    s.on('ice-candidate', async ({ from, candidate }) => { try { await handleRemoteCandidate(from, candidate); } catch (e) { console.warn('remote candidate error', e); } });
  }

  // Generic application-level message relay (for media_state, appointment markers, etc.)
  function sendMessage(to, payload) {
    try { if (socket && socket.connected) socket.emit('message', { to, payload, from: socket.id }); } catch (_) { }
  }

  // Listen for generic messages
  function _attachMessageHandler(s) {
    s.on('message', ({ from, payload }) => {
      try { if (payload && payload.type === 'appointment') { /* could dispatch */ } } catch (_) { }
      try { if (typeof onAppMessage === 'function') onAppMessage(from, payload); } catch (_) { }
    });
  }

  async function tryConnectCandidates(list) {
    let lastErr = null;
    for (const u of list) {
      try {
        console.debug('[Signaler] attempting connect to', u);
        const s = io(u, { autoConnect: false, transports: ['websocket'], timeout: 3000 });
        _attachCommonHandlers(s);
        s.open();
        await new Promise((resolve, reject) => {
          const to = setTimeout(() => reject(new Error('connect-timeout')), 3500);
          s.once('connect', () => { clearTimeout(to); resolve(); });
          s.once('connect_error', (err) => { clearTimeout(to); reject(err || new Error('connect_error')); });
        });
        // success
        socket = s;
        return;
      } catch (e) {
        lastErr = e;
        try { console.debug('[Signaler] connect failed for', u, e && e.message); } catch (_) { }
      }
    }
    throw lastErr || new Error('all candidates failed');
  }

  async function connect() {
    if (socket && socket.connected) return Promise.resolve();
    return tryConnectCandidates(urlCandidates);
  }

  let onRemoteStream = null; let onConnected = null; let onPeerJoined = null; let onPeerLeft = null;

  async function joinRoom(roomId, localStream, handlers = {}) {
    if (!socket) await connect();
    onRemoteStream = handlers.onRemoteStream || null;
    onConnected = handlers.onConnected || null;
    onPeerJoined = async (remoteId) => {
      // create PC and act as offerer towards remoteId
      try {
        await createPeerConnection(remoteId, localStream, true);
      } catch (e) { console.warn('peer-joined handler error', e); }
    };
    onPeerLeft = handlers.onPeerLeft || null;

    // request join, server will reply with existing clients
    return new Promise((resolve, reject) => {
      try {
        socket.emit('join', roomId, async (res) => {
          if (!res || !res.ok) return reject(new Error('join-failed'));
          const existing = res.clients || [];
          // If there is already a peer, we wait for their offer (we are joiner)
          if (existing.length > 0) {
            // do nothing; the existing peer(s) should receive peer-joined and create offer
            // But also we could proactively create PC as answerer when receiving offer
          }
          resolve({ existing });
        });
      } catch (e) { reject(e); }
    });
  }

  async function leaveRoom(roomId) {
    try {
      if (socket && socket.connected) socket.emit('leave', roomId);
    } catch (_) { }
    cleanupAll();
  }

  function cleanupAll() {
    try { pcs.forEach(pc => { try { pc.close(); } catch (_) { } }); pcs.clear(); } catch (_) { }
  }

  async function createPeerConnection(remoteId, localStream, isOfferer) {
    if (pcs.has(remoteId)) return pcs.get(remoteId);
    const pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
    pcs.set(remoteId, pc);

    pc.onicecandidate = (ev) => {
      if (ev.candidate) {
        try { socket.emit('ice-candidate', { to: remoteId, candidate: ev.candidate, from: socket.id }); } catch (_) { }
      }
    };
    pc.ontrack = (ev) => {
      try { if (typeof onRemoteStream === 'function') onRemoteStream(ev.streams[0], remoteId); } catch (_) { }
    };
    // add local tracks
    try {
      if (localStream && localStream.getTracks) {
        for (const t of localStream.getTracks()) {
          try { pc.addTrack(t, localStream); } catch (_) { }
        }
      }
    } catch (_) { }

    if (isOfferer) {
      const offer = await pc.createOffer();
      await pc.setLocalDescription(offer);
      socket.emit('offer', { to: remoteId, sdp: pc.localDescription, from: socket.id });
    }

    return pc;
  }

  async function handleOffer(from, sdp) {
    const pc = await createPeerConnection(from, window.__earlyCallStream || null, false);
    await pc.setRemoteDescription(new RTCSessionDescription(sdp));
    const answer = await pc.createAnswer();
    await pc.setLocalDescription(answer);
    socket.emit('answer', { to: from, sdp: pc.localDescription, from: socket.id });
    if (typeof onConnected === 'function') onConnected(from);
  }

  async function handleAnswer(from, sdp) {
    const pc = pcs.get(from);
    if (!pc) return;
    await pc.setRemoteDescription(new RTCSessionDescription(sdp));
    if (typeof onConnected === 'function') onConnected(from);
  }

  async function handleRemoteCandidate(from, candidate) {
    const pc = pcs.get(from);
    if (!pc) return;
    try { await pc.addIceCandidate(new RTCIceCandidate(candidate)); } catch (_) { }
  }

  return {
    connect,
    joinRoom,
    leaveRoom,
    cleanupAll,
    sendMessage,
    onAppMessage: (fn) => { onAppMessage = fn; },
    // allow registering hooks (optional)
    on: (ev, fn) => {
      if (ev === 'remoteStream') onRemoteStream = fn;
      if (ev === 'connected') onConnected = fn;
      if (ev === 'peerJoined') onPeerJoined = fn;
      if (ev === 'peerLeft') onPeerLeft = fn;
    }
  };
}
