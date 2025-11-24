import express from 'express';
import http from 'http';
import { Server } from 'socket.io';

const app = express();
const server = http.createServer(app);
const io = new Server(server, { cors: { origin: '*', methods: ['GET','POST'] } });

// Basic health
app.get('/', (req, res) => res.send('Signaling server running'));

io.on('connection', (socket) => {
  console.log('[signaling] socket connected', socket.id);

  socket.on('join', (roomId, cb) => {
    try {
      const room = io.sockets.adapter.rooms.get(roomId) || new Set();
      const existing = Array.from(room).filter(id => id !== socket.id);
      // Notify existing peers that a new peer joined
      for (const peerId of existing) {
        io.to(peerId).emit('peer-joined', { id: socket.id });
      }
      socket.join(roomId);
      console.log(`[signaling] ${socket.id} joined ${roomId}, existing:`, existing);
      if (typeof cb === 'function') cb({ ok: true, clients: existing });
    } catch (e) {
      console.warn('[signaling] join error', e);
      if (typeof cb === 'function') cb({ ok: false });
    }
  });

  socket.on('leave', (roomId) => {
    try {
      socket.leave(roomId);
      // notify others
      const room = io.sockets.adapter.rooms.get(roomId) || new Set();
      for (const peerId of room) {
        io.to(peerId).emit('peer-left', { id: socket.id });
      }
    } catch (_) { }
  });

  socket.on('offer', ({ to, sdp, from }) => {
    io.to(to).emit('offer', { from, sdp });
  });
  socket.on('answer', ({ to, sdp, from }) => {
    io.to(to).emit('answer', { from, sdp });
  });
  socket.on('ice-candidate', ({ to, candidate, from }) => {
    io.to(to).emit('ice-candidate', { from, candidate });
  });
  // Generic application-level message relay
  socket.on('message', ({ to, payload, from }) => {
    try {
      if (to) {
        io.to(to).emit('message', { from: socket.id, payload });
      } else {
        // broadcast to rooms the sender is in
        const rooms = Array.from(socket.rooms || []).filter(r => r !== socket.id);
        for (const r of rooms) {
          socket.to(r).emit('message', { from: socket.id, payload });
        }
      }
    } catch (e) { console.warn('[signaling] message relay error', e); }
  });

  socket.on('disconnect', (reason) => {
    console.log('[signaling] disconnected', socket.id, reason);
    // broadcast leave to all rooms
    try {
      const rooms = Array.from(socket.rooms || []).filter(r => r !== socket.id);
      for (const roomId of rooms) {
        const room = io.sockets.adapter.rooms.get(roomId) || new Set();
        for (const peerId of room) {
          io.to(peerId).emit('peer-left', { id: socket.id });
        }
      }
    } catch (_) { }
  });
});

const PORT = process.env.PORT || 4000;
server.listen(PORT, () => console.log(`[signaling] listening on ${PORT}`));
