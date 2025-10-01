import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const key = import.meta.env.VITE_PUSHER_KEY;
if (key) {
	window.Echo = new Echo({
		broadcaster: 'pusher',
		key: key,
		cluster: import.meta.env.VITE_PUSHER_CLUSTER || 'mt1',
		wsHost: import.meta.env.VITE_PUSHER_HOST || window.location.hostname,
		wsPort: import.meta.env.VITE_PUSHER_PORT || 6001,
		wssPort: import.meta.env.VITE_PUSHER_PORT || 6001,
		forceTLS: false,
		enabledTransports: ['ws','wss'],
		// auth headers via sanctum/csrf not configured; fallback token via meta tag
		auth: { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') } }
	});

	const userId = window.__authUserId;
	if (userId) {
		window.Echo.private(`user.${userId}`)
			.listen('MessageSent', (e) => {
				window.dispatchEvent(new CustomEvent('rt:message', { detail: e }));
			})
			.listen('FriendRequestSent', (e) => {
				window.dispatchEvent(new CustomEvent('rt:friend_request', { detail: e }));
			})
			.listen('FriendRequestAccepted', (e) => {
				window.dispatchEvent(new CustomEvent('rt:friend_request_accepted', { detail: e }));
			});
	}
} else {
	console.warn('Realtime deshabilitado: falta VITE_PUSHER_KEY');
}
