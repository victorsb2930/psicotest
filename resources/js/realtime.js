import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Detect driver using env or meta fallback
const metaDriver = document.querySelector('meta[name="broadcast-driver"]')?.content;
const driver = (import.meta.env.VITE_BROADCAST_DRIVER || metaDriver || 'pusher').toLowerCase();
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
let echoConfig = null;
if (driver === 'reverb') {
	const host = window?.location?.hostname || import.meta.env.VITE_REVERB_HOST || 'localhost';
	const isHttps = (typeof window !== 'undefined' && window.location && window.location.protocol === 'https:');
	const port = import.meta.env.VITE_REVERB_PORT || (isHttps ? '443' : '80');
	echoConfig = {
		broadcaster: 'reverb',
		key: import.meta.env.VITE_REVERB_APP_KEY || 'reverbkey',
		wsHost: host,
		wsPort: parseInt(port, 10),
		wssPort: parseInt(port, 10),
		forceTLS: isHttps,
		enabledTransports: ['ws', 'wss'],
		authEndpoint: '/broadcasting/auth',
		auth: {
			headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}
		}
	};
} else {
	const key = import.meta.env.VITE_PUSHER_KEY;
	if (key) {
		echoConfig = {
			broadcaster: 'pusher',
			key,
			cluster: import.meta.env.VITE_PUSHER_CLUSTER || 'mt1',
			wsHost: import.meta.env.VITE_PUSHER_HOST || window.location.hostname,
			wsPort: import.meta.env.VITE_PUSHER_PORT || 6001,
			wssPort: import.meta.env.VITE_PUSHER_PORT || 6001,
			forceTLS: false,
			enabledTransports: ['ws', 'wss'],
			auth: { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') } }
		};
	}
}

if (echoConfig) {
	try { window.Echo = new Echo(echoConfig); } catch (err) { }

	const userId = window.__authUserId;
	if (userId) {
		const channelName = `user.${userId}`;
		let __lastCountersFetch = 0; let __countersFetchT = null;
		function scheduleCountersRefresh(){
			const now = Date.now();
			const since = now - __lastCountersFetch;
			if (since > 800) { // fetch immediately if been a while
				__lastCountersFetch = now;
				fetch('/api/counters').then(r=>r.json()).then(d=>{ if(d && d.ok){ document.dispatchEvent(new CustomEvent('counters:update',{ detail: d })); updateChatBadgeFromCounters(d); } });
				return;
			}
			if (__countersFetchT) return; // already scheduled
			__countersFetchT = setTimeout(()=>{
				__countersFetchT = null; __lastCountersFetch = Date.now();
				fetch('/api/counters').then(r=>r.json()).then(d=>{ if(d && d.ok){ document.dispatchEvent(new CustomEvent('counters:update',{ detail: d })); updateChatBadgeFromCounters(d); } });
			}, 350);
		}
		function updateChatBadgeFromCounters(payload){
			try {
				const link = document.querySelector('#left-menu a.nav-link i.bi-chat-dots')?.closest('a');
				if (!link) return;
				let badge = link.querySelector('.badge');
				const unread = payload.messages_unread ?? payload.messages ?? null;
				if (unread === null) return; // nothing to render
				if (unread <= 0){ if (badge) { badge.remove(); } return; }
				if (!badge) { badge = document.createElement('span'); badge.className = 'badge text-bg-light text-dark ms-2'; link.appendChild(badge); }
				badge.textContent = String(unread);
			} catch(_){}
		}
		window.Echo.private(channelName)
			.listen('MessageSent', (e) => {
				window.dispatchEvent(new CustomEvent('rt:message', { detail: e }));
				// Solicitar counters reales (evita esperar al polling y elimina incremento inexacto)
				scheduleCountersRefresh();
				if (window.modalNotification) window.modalNotification('Nuevo mensaje', e.body || 'Tienes un nuevo mensaje', { template: 'info' });
			})
			.listen('FriendRequestSent', (e) => {
				window.dispatchEvent(new CustomEvent('rt:friend_request', { detail: e }));
				if (window.modalNotification) window.modalNotification('Solicitud de amistad', `${e.from_name} te ha enviado una solicitud`, { template: 'warning' });
			})
			.listen('FriendRequestAccepted', (e) => {
				window.dispatchEvent(new CustomEvent('rt:friend_request_accepted', { detail: e }));
				if (window.modalNotification) window.modalNotification('Amistad aceptada', `${e.to_name} ahora es tu amigo`, { template: 'success' });
			});

			// Rating response from professional (notify patient)
			window.Echo.private(channelName).listen('RatingResponded', (e) => {
				window.dispatchEvent(new CustomEvent('rt:rating_responded', { detail: e }));
				if (window.modalNotification) window.modalNotification('Respuesta a tu valoración', e.message || 'El profesional respondió a tu valoración', { template: 'info' });
			});

		// Also listen on a public presence channel for status changes
		try {
			window.Echo.channel('presence')
				.listen('.UserPresenceChanged', (e) => {
					// normalize payload { user_id, status }
					const detail = { user_id: e.user_id, status: e.status };
					window.dispatchEvent(new CustomEvent('rt:user_presence', { detail }));
				});
		} catch (err) { }
	}
} else {

}
