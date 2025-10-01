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
				// Actualiza badge mensajes
				try {
					const link = document.querySelector('#left-menu a.nav-link i.bi-chat-dots')?.closest('a');
					if (link) {
						let badge = link.querySelector('.badge');
						if (!badge) { badge = document.createElement('span'); badge.className='badge text-bg-light text-dark ms-2'; link.appendChild(badge); badge.textContent='0'; }
						badge.textContent = String(parseInt(badge.textContent||'0',10)+1);
					}
				} catch(_){ }
				if (window.modalNotification) window.modalNotification('Nuevo mensaje', e.body || 'Tienes un nuevo mensaje',{template:'info'});
			})
			.listen('FriendRequestSent', (e) => {
				window.dispatchEvent(new CustomEvent('rt:friend_request', { detail: e }));
				if (window.modalNotification) window.modalNotification('Solicitud de amistad', `${e.from_name} te ha enviado una solicitud`, {template:'warning'});
			})
			.listen('FriendRequestAccepted', (e) => {
				window.dispatchEvent(new CustomEvent('rt:friend_request_accepted', { detail: e }));
				if (window.modalNotification) window.modalNotification('Amistad aceptada', `${e.to_name} ahora es tu amigo`, {template:'success'});
			});
	}
} else {
	console.warn('Realtime deshabilitado: falta VITE_PUSHER_KEY');
}
