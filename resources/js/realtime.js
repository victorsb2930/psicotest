import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Detect driver using env or meta fallback
const metaDriver = document.querySelector('meta[name="broadcast-driver"]')?.content;
const driver = (import.meta.env.VITE_BROADCAST_DRIVER || metaDriver || 'pusher').toLowerCase();
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
let echoConfig = null;
if (driver === 'reverb') {
	echoConfig = {
		broadcaster: 'reverb',
		key: import.meta.env.VITE_REVERB_APP_KEY || 'reverbkey',
		wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
		wsPort: parseInt(import.meta.env.VITE_REVERB_PORT || '8080', 10),
		wssPort: parseInt(import.meta.env.VITE_REVERB_PORT || '8080', 10),
		forceTLS: false,
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
		window.Echo.private(channelName)
			.listen('MessageSent', (e) => {
				window.dispatchEvent(new CustomEvent('rt:message', { detail: e }));
				// Actualiza badge mensajes
				try {
					const link = document.querySelector('#left-menu a.nav-link i.bi-chat-dots')?.closest('a');
					if (link) {
						let badge = link.querySelector('.badge');
						if (!badge) { badge = document.createElement('span'); badge.className = 'badge text-bg-light text-dark ms-2'; link.appendChild(badge); badge.textContent = '0'; }
						badge.textContent = String(parseInt(badge.textContent || '0', 10) + 1);
					}
				} catch (_) { }
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
