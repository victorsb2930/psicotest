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
  let lastLocalStream = null;

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
    try {
      if (socket && socket.connected) {
        console.debug('[Signaler] sendMessage', { to, payload });
        socket.emit('message', { to, payload, from: socket.id });
      } else {
        console.debug('[Signaler] sendMessage called but socket not connected', { to, payload });
      }
    } catch (e) { console.warn('[Signaler] sendMessage error', e); }
  }

  // Listen for generic messages
  function _attachMessageHandler(s) {
    s.on('message', ({ from, payload }) => {
      try { console.debug('[Signaler] received message', { from, payload }); } catch (_) { }
      try { if (payload && payload.type === 'appointment') { /* could dispatch */ } } catch (_) { }
      try {
        if (typeof onAppMessage === 'function') onAppMessage(from, payload);
      } catch (_) { }
      // Automatic lightweight ACK: if the payload requests an ACK, reply back to sender
      try {
        if (payload && payload._expectAck && payload._messageId) {
          try { s.emit('message-ack', { to: from, messageId: payload._messageId }); } catch (_) { }
        }
      } catch (_) { }
    });
    // Listen for ACKs from peers relayed by server
    s.on('message-ack', ({ from, messageId }) => {
      try { console.debug('[Signaler] received message-ack', { from, messageId }); } catch (_) { }
      try {
        const entry = pendingAcks.get(messageId);
        if (entry) {
          clearTimeout(entry.timer);
          pendingAcks.delete(messageId);
          try { entry.resolve({ ok: true, from }); } catch (_) { }
        }
      } catch (_) { }
    });
  }

  const pendingAcks = new Map();

  // Enhanced sendMessage that supports waiting for a lightweight ACK.
  // Usage: sendMessage(to, payload, { expectAck: true, timeoutMs: 4000 })
  async function sendMessageWithAck(to, payload, opts = {}) {
    const expectAck = !!opts.expectAck;
    const timeoutMs = typeof opts.timeoutMs === 'number' ? opts.timeoutMs : 4000;
    if (!expectAck) {
      sendMessage(to, payload);
      return { ok: true };
    }
    // Ensure payload has a message id and marker so recipients will ACK.
    const msgId = (payload && payload._messageId) ? payload._messageId : ('m_' + Math.random().toString(36).slice(2) + Date.now());
    const wrapped = Object.assign({}, payload, { _expectAck: true, _messageId: msgId });
    return await new Promise((resolve, reject) => {
      try {
        // store pending ack
        const timer = setTimeout(() => {
          pendingAcks.delete(msgId);
          resolve({ ok: false, reason: 'timeout' });
        }, timeoutMs);
        pendingAcks.set(msgId, { resolve, reject, timer });
        // emit
        sendMessage(to, wrapped);
      } catch (e) {
        resolve({ ok: false, reason: 'error' });
      }
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
        _attachMessageHandler(s);
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

  let onRemoteStream = null; let onConnected = null; let onPeerJoined = null; let onPeerLeft = null; let onAppMessage = null;

  async function joinRoom(roomId, localStream, handlers = {}) {
    if (!socket) await connect();
    // remember last local stream so we can manipulate senders later
    lastLocalStream = localStream || null;
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

  function presenceJoin(roomId) {
    if (!socket) connect();
    try { if (socket && socket.connected) socket.emit('presence-join', roomId); } catch (_) { }
  }

  function setLocalStream(stream) {
    try { lastLocalStream = stream || null; } catch (_) { }
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

  // Allow toggling local track enabled state across all peer connections.
  // This function is async because it may perform renegotiation when tracks need to be re-added.
  async function setLocalTrackEnabled(kind, enabled) {
    try {
      // Update known lastLocalStream tracks
      if (lastLocalStream && lastLocalStream.getTracks) {
        const tracks = kind === 'audio' ? lastLocalStream.getAudioTracks() : lastLocalStream.getVideoTracks();
        tracks.forEach(t => { try { t.enabled = !!enabled; } catch (_) { } });
      }

      // Iterate peer connections and update senders. If enabling and replaceTrack isn't sufficient,
      // try to add the track and trigger renegotiation.
      for (const pc of Array.from(pcs.values())) {
        try {
          const senders = pc.getSenders ? pc.getSenders() : [];
          for (const s of senders) {
            try {
              if (!s || !s.track) continue;
              if (s.track.kind !== (kind === 'audio' ? 'audio' : 'video')) continue;

              if (!enabled) {
                // Prefer simply disabling the existing track for easier re-enable.
                try { s.track.enabled = false; } catch (_) { }
                // As a more aggressive fallback, replace with null if supported.
                try { if (typeof s.replaceTrack === 'function') s.replaceTrack(null); } catch (_) { }
              } else {
                // Enabling: prefer replacing with available local track
                const replacement = lastLocalStream ? (kind === 'audio' ? lastLocalStream.getAudioTracks()[0] : lastLocalStream.getVideoTracks()[0]) : null;
                if (replacement) {
                  if (typeof s.replaceTrack === 'function') {
                    try { await s.replaceTrack(replacement); continue; } catch (e) { /* fallthrough */ }
                  }
                  // If no replaceTrack or it failed, try toggling enabled
                  try { s.track.enabled = true; } catch (_) { }
                } else {
                  // No replacement available: try to add track and renegotiate
                  try {
                    const toAdd = lastLocalStream ? (kind === 'audio' ? lastLocalStream.getAudioTracks()[0] : lastLocalStream.getVideoTracks()[0]) : null;
                    if (toAdd) {
                      try { pc.addTrack(toAdd, lastLocalStream); } catch (_) { }
                      try {
                        const offer = await pc.createOffer();
                        await pc.setLocalDescription(offer);
                        // send offer to peer(s)
                        if (socket && socket.connected) {
                          // find remote id by matching pc in pcs map
                          for (const [remoteId, remotePc] of pcs.entries()) {
                            if (remotePc === pc) {
                              try { socket.emit('offer', { to: remoteId, sdp: pc.localDescription, from: socket.id }); } catch (_) { }
                              break;
                            }
                          }
                        }
                      } catch (_) { }
                    }
                  } catch (_) { }
                }
              }
            } catch (_) { }
          }
        } catch (_) { }
      }
    } catch (_) { }
  }

  return {
    connect,
    joinRoom,
    presenceJoin,
    setLocalStream,
    setLocalTrackEnabled,
    getPeerConnections: () => Array.from(pcs.values()),
    leaveRoom,
    cleanupAll,
    sendMessage: sendMessageWithAck,
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
