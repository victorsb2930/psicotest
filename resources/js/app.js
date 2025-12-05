// Globals: jQuery, Bootstrap, Axios, tom-select, uuid, global functions, utils
import './bootstrap';
// RTC bootstrap/connect globally so incoming calls can reach the user on any page
// Carga diferida de RTC para reducir tamaño del bundle principal.
// Se importará dinámicamente sólo si el usuario está autenticado o la página requiere capacidades RTC (chat, calendario profesional, user appointments, disponibilidad).
async function loadRTCIfNeeded() {
	try {
		const requiresRTC = window.__isAuth || ['chat','professional-calendar','user-appointments','professional-availability','professional-area'].includes(currentPageName);
		if (!requiresRTC) return;
		// Import dinámico: genera chunk separado (split) manejado por Vite
		await import('./rtc');
	} catch (_) {}
}
import './partials/quickLogin';
import './notifications';
import './partials/reopen2fa';
// Ensure RTC/realtime chunks are included in production manifest
import './realtime';

// Preload static marketing images so Vite includes them in the production manifest
const staticMarketingAssets = import.meta.glob(['../images/**'], { eager: true, import: 'default' });
if (typeof window !== 'undefined') {
	window.__pgMarketingAssets = staticMarketingAssets;
}

/*
* Agrego aqui tambien la configuracion global CSRF => CSRF token mismatch.
*/

$.ajaxSetup({
	headers: {
		'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
	}
});

// Carga condicional de assets por página con soporte de ciclo de vida { init, destroy }
let currentPageModule = null;
let currentPageModuleDestroy = null;
let currentPageName = null;

const pageModuleMap = {
	'index': './index.js'
	,'login-register': './loginRegister.js'
	,'contact': './contact.js'
	,'profile': './pages/profile.js'
	,'admin-roles': './pages/admin.roles.js'
	,'admin-users': './pages/admin.users.js'
	,'admin-professional-apps': './pages/admin.profapps.js'
	,'admin-appointment-settings': './pages/admin.appointment.settings.js'
	,'admin-appointment-metrics': './pages/admin.appointment.metrics.js'
	,'admin-payments': './pages/admin.payments.js'
	,'professional-calendar': './pages/professional.calendar.js'
	,'user-appointments': './pages/user.appointments.js'
	,'professionals-search': './pages/professionals.search.js'
	,'plans': './components/plans.js'
	,'chat': './pages/chat.js'
	,'professional-availability': './pages/professional.availability.js'
	,'professional-area': './pages/professional.area.js'
	,'professional-ratings': './pages/professional.ratings.js'
	,'user-area': './pages/user.area.js'
	,'user-professionals': './pages/user.professionals.js'
	,'professional-payments': './pages/professional.payments.js'
	,'2fa-challenge': './pages/2fa/twofactor_challenge.js'
};

// Preload page modules so Vite emite los chunks en build (evita 404 tipo index.js)
const __pgPageModules = import.meta.glob([
	'./index.js',
	'./loginRegister.js',
	'./contact.js',
	'./pages/**/*.js',
	'./components/**/*.js'
]);
if (typeof window !== 'undefined') {
	window.__pgPageModules = __pgPageModules;
}

async function initPage() {
	// Determine current page name from multiple possible markers so the
	// page-module loader works whether `data-page` is placed on <body>,
	// on the `#app-content` container, or on any element returned by the
	// server fragment. Fall back to 'default' when none are found.
	let page = 'default';
	try {
		// Prefer reading the raw attribute value where possible for maximum
		// compatibility with HTML parsing and server-fragment responses.
		if (document.body && document.body.getAttribute && document.body.getAttribute('data-page')) {
			page = document.body.getAttribute('data-page');
		} else {
			const container = document.getElementById('app-content');
			if (container && container.getAttribute && container.getAttribute('data-page')) {
				page = container.getAttribute('data-page');
			} else {
				const anyPageEl = document.querySelector('[data-page]');
				if (anyPageEl && anyPageEl.getAttribute) {
					const attr = anyPageEl.getAttribute('data-page');
					if (attr) page = attr;
				}
			}
		}
	} catch (e) {
		// defensive fallback to dataset if getAttribute failed for some reason
		page = (document.body && document.body.dataset && document.body.dataset.page) || 'default';
	}
	currentPageName = page;
	// Importar e inicializar módulo por página si existe
	const modulePath = pageModuleMap[page];
	try {
		if (!modulePath) {
			// no hay módulo específico para esta página
			currentPageModule = null;
			currentPageModuleDestroy = null;
			return;
		}
		const mod = await import(/* @vite-ignore */ modulePath);
		currentPageModule = mod;
		// Si exporta init(), llamarla
		if (typeof mod.init === 'function') {
			try { mod.init(); } catch (e) {  }
		} else if (typeof mod.default === 'function') {
			// compatibilidad con módulos que exportan una función por defecto
			try { mod.default(); } catch (e) { /* ignore */ }
		}
		// Guardar destroy si existe para limpiar antes de reemplazar contenido
		if (typeof mod.destroy === 'function') {
			currentPageModuleDestroy = mod.destroy;
		} else {
			currentPageModuleDestroy = null;
		}
	} catch (e) {
		currentPageModule = null;
		currentPageModuleDestroy = null;
	}
}

// Ejecutar la inicialización al cargar la página (full navigation)

document.addEventListener('DOMContentLoaded', () => {
	initPage().then(()=>{ try { loadRTCIfNeeded(); } catch(_){ } });
	// Start PJAX after initial page scripts loaded
	try { enablePJAX(); } catch (e) { /* if PJAX not supported ignore */ }
	try { updateHeaderCTA(); } catch(_) {}
});

// Attempt to notify server when the page is unloaded so we can mark session end.
// Uses navigator.sendBeacon when available; falls back to synchronous fetch for old browsers.
function sendSessionEndBeacon() {
	try {
		const url = '/sessions/end';
		const data = new FormData();
		// Add a lightweight payload to satisfy CSRF protection if present on server
		const tokenMeta = document.querySelector('meta[name="csrf-token"]');
		const token = tokenMeta ? tokenMeta.getAttribute('content') : null;
		if (token) data.append('_token', token);
		// Attempt sendBeacon first
		if (navigator && typeof navigator.sendBeacon === 'function') {
			const blob = new Blob([new URLSearchParams(data).toString()], { type: 'application/x-www-form-urlencoded' });
			navigator.sendBeacon(url, blob);
			return true;
		}
		// Fallback: synchronous XHR (deprecated) or fetch with keepalive
		if (window.fetch) {
			try {
				fetch(url, { method: 'POST', body: data, keepalive: true, credentials: 'same-origin' });
				return true;
			} catch (_) {}
		}
		// Last resort: synchronous AJAX (may be blocked in some browsers)
		try {
			const xhr = new XMLHttpRequest();
			xhr.open('POST', url, false);
			xhr.setRequestHeader('Accept', 'application/json');
			xhr.send(new URLSearchParams(data).toString());
			return true;
		} catch (_) {}
	} catch (e) {}
	return false;
}

// Hook unload and visibilitychange to call sendBeacon when the user closes tab/window.
// Do NOT mark session as ended just because the document became hidden.
// Calling sendSessionEndBeacon() on every visibilitychange causes a false
// 'ended_at' to be recorded when users switch tabs, minimize the window,
// or otherwise hide the page. We keep unload/beforeunload as the reliable
// place to send a best-effort beacon, and explicit logout handlers still
// call sendSessionEndBeacon() before invalidating the session.
//
// Keep a no-op listener so other code can still listen for visibility
// changes in the future without surprising behavior here.
window.addEventListener('visibilitychange', function () {
	// Intentionally empty: do not close session on tab hide.
});
// beforeunload as backup
window.addEventListener('beforeunload', function () {
	sendSessionEndBeacon();
});

// Intercept logout forms/links to send session end before submitting
document.addEventListener('click', function (e) {
	const el = e.target.closest && e.target.closest('form, a, button');
	if (!el) return;
	// Common pattern: form[action='/logout'] or any element with data-logout
	try {
		if ((el.tagName && el.tagName.toLowerCase() === 'form' && (el.getAttribute('action') || '').endsWith('/logout')) || el.dataset?.logout === 'true' || el.getAttribute && (el.getAttribute('href') || '').endsWith('/logout')) {
			// best-effort beacon before logout
			sendSessionEndBeacon();
		}
	} catch (_) {}
});

// Heartbeat (keepalive) logic: when authenticated, ping /profile/heartbeat periodically
let __pg_heartbeat_interval = null;
function startHeartbeat(intervalSeconds = 60) {
	try {
		if (!window.__isAuth) return;
		stopHeartbeat();
		const url = '/profile/heartbeat';
		const fn = () => {
			try {
				// Attach CSRF token (if present) so Laravel's VerifyCsrfToken accepts this POST.
				const tokenMeta = document.querySelector('meta[name="csrf-token"]');
				const token = tokenMeta ? tokenMeta.getAttribute('content') : null;
				const headers = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
				if (token) headers['X-CSRF-TOKEN'] = token;
				fetch(url, { method: 'POST', credentials: 'same-origin', headers }).then(async (res)=>{
					try {
						if (!res.ok) return;
						const j = await res.json().catch(()=>null);
						if (j && j.two_factor_required) {
							// notify any listener to show 2FA modal
							window.dispatchEvent(new CustomEvent('pg:reopen-2fa-required', { detail: j }));
						}
					} catch (_) {}
				}).catch(()=>{});
			} catch (_) {}
		};
		fn(); // immediate
		__pg_heartbeat_interval = setInterval(fn, Math.max(10, intervalSeconds) * 1000);
	} catch (_) {}
}
function stopHeartbeat() {
	try { if (__pg_heartbeat_interval) { clearInterval(__pg_heartbeat_interval); __pg_heartbeat_interval = null; } } catch(_){}
}

// Start heartbeat on initial load if the page indicates authenticated state
document.addEventListener('DOMContentLoaded', function(){ try { if (window.__isAuth) startHeartbeat(60); } catch(_){} });

// Maintain navigation stack for authenticated sessions to control leftmenu back behavior
function pushToNavStack(path) {
	try {
		if (!window.__isAuth) return;
		window.__navStack = window.__navStack || [];
		const last = window.__navStack[window.__navStack.length - 1];
		if (String(last || '') !== String(path || '')) {
			window.__navStack.push(path);
			try { sessionStorage.setItem('pg_nav_stack_v1', JSON.stringify(window.__navStack)); } catch(_){}
			try { window.dispatchEvent(new CustomEvent('pg:navstack:changed')); } catch(_){}
		}
	} catch(_){}
}

// Ensure initial path is recorded
document.addEventListener('DOMContentLoaded', function(){
	try { pushToNavStack(location.pathname); } catch(_){}
});

// Update visibility of the left-menu "Atrás" button when navigating via PJAX or on load
function isAreaRootPath() {
	const p = (location.pathname || '').replace(/\/$/, '');
	return ['/adminarea', '/professionalarea', '/userarea'].includes(p);
}

function updateLeftmenuBackButton() {
	// support either <a aria-label="Atrás"> or <button id="leftmenu-safe-back"> patterns
	const btn = document.querySelector('#left-menu a[aria-label="Atrás"]') || document.querySelector('#leftmenu-safe-back');
	if (!btn) return;
	try {
		if (isAreaRootPath()) {
			btn.style.display = 'none';
		} else {
			btn.style.display = 'inline-flex';
		}
	} catch (e) { /* ignore */ }
}

// Run on initial load as well
document.addEventListener('DOMContentLoaded', () => {
	try { updateLeftmenuBackButton(); } catch (e) {}
});

// Lightweight PJAX implementation: replaces #app-content with fetched HTML
function enablePJAX() {
	const container = document.getElementById('app-content');
	if (!container) return;

	const isSameOrigin = (url) => {
		try {
			const u = new URL(url, location.href);
			return u.origin === location.origin;
		} catch (e) { return false; }
	};

	const shouldHandle = (anchor) => {
		if (!anchor || !anchor.href) return false;
		if (anchor.target && anchor.target !== '_self') return false;
		if (anchor.dataset && anchor.dataset.noPjax === 'true') return false;
		if (anchor.hasAttribute('download')) return false;
		if (!isSameOrigin(anchor.href)) return false;
		// Only handle GET navigation
		return true;
	};

	const swapContent = async (url, addToHistory = true) => {
		try {
			const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
			if (!res.ok) {
				// If server responds with redirect to login or error, fall back to full navigation
				window.location.href = url;
				return;
			}
			const html = await res.text();
			const tmp = document.createElement('div'); tmp.innerHTML = html;
			const newContent = tmp.querySelector('#app-content') || tmp.querySelector('main') || tmp;
			if (!newContent) { window.location.href = url; return; }
			// Call destroy on current page module (if it exports destroy) before replacing DOM
			try {
				if (typeof currentPageModuleDestroy === 'function') {
					currentPageModuleDestroy();
				}
			} catch (e) {  }

			// Update container
			container.innerHTML = newContent.innerHTML;
			// Update document title if provided in fetched HTML
			try {
				const fetchedTitle = tmp.querySelector('title')?.textContent;
				if (fetchedTitle) document.title = fetchedTitle;
				// fallback: look for an element with data-page and a data-title attribute
				const titleMeta = tmp.querySelector('[data-page]')?.dataset?.title;
				if (!fetchedTitle && titleMeta) document.title = titleMeta;
			} catch(e) { /* ignore */ }
			// Update body data-page if present in the fetched content.
			// Use getAttribute('data-page') for maximum compatibility (dataset may not be populated
			// in some parsing edge-cases), and fall back to dataset.page.
			// Prefer any data-page marker in the full fetched HTML, but also check inside the
			// newContent fragment (some servers return only the inner fragment without <body>).
			let pageEl = tmp.querySelector('[data-page]');
			if (!pageEl && newContent && typeof newContent.querySelector === 'function') {
				pageEl = newContent.querySelector('[data-page]');
			}
			if (pageEl) {
				const pageAttr = pageEl.getAttribute('data-page') || (pageEl.dataset && pageEl.dataset.page) || null;
				if (pageAttr) {
					document.body.setAttribute('data-page', pageAttr);
				} else {
					document.body.removeAttribute('data-page');
				}
			}
			if (addToHistory) history.pushState({}, '', url);
			if (addToHistory) {
				try { pushToNavStack((new URL(url, location.href)).pathname); } catch(_){ }
			}
			// Re-run page-specific initialization
			try { await initPage(); } catch (e) {  }
			// Tras inicializar nuevo módulo de página, evaluar si ahora se requiere RTC (navegación interna)
			try { loadRTCIfNeeded(); } catch(_){}
			// Update left-menu back button visibility after init
			try { updateLeftmenuBackButton(); } catch (e) {}

			// Update header CTA visibility
			try { updateHeaderCTA(); } catch (e) {}

			// Recalculate active left-menu link based on current location
			try { updateLeftmenuActive(); } catch (e) {}
			// Recalculate active left-menu link after PJAX swap
			try { updateLeftmenuActive(); } catch (e) {}
			// Scroll top
			window.scrollTo(0, 0);
		} catch (e) {
			window.location.href = url;
		}
	};

	// Delegated click handler for anchor elements
	document.addEventListener('click', function (e) {
		const a = e.target.closest('a');
		if (!a) return;
		if (!shouldHandle(a)) return;
		// Ignore modified clicks
		if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
		e.preventDefault();
		swapContent(a.href, true);
	});

	// popstate handler for back/forward
	window.addEventListener('popstate', function () {
		swapContent(location.href, false);
	});

	// Mark global flag so other scripts can detect PJAX is active
	try { window.__pjaxEnabled = true; } catch (e) {}
}

// Update which leftmenu link has the .active class
function updateLeftmenuActive(){
	try{
		const links = Array.from(document.querySelectorAll('#left-menu a.nav-link'));
		if(!links || links.length===0) return;
		const path = (location.pathname || '/').replace(/\/$/, '');
		// Prefer exact pathname match. Ignore hash-only anchors (href starting with '#')
		let best = null;
		let bestScore = -1;
		links.forEach(a=>{
			try{
				const rawHref = a.getAttribute('href') || '';
				if(rawHref.startsWith('#')) {
					// only active if pathname matches and hash matches
					try {
						const u = new URL(a.href, location.href);
						if(u.pathname.replace(/\/$/, '') === path && u.hash === location.hash) {
							a.classList.add('active');
						} else {
							a.classList.remove('active');
						}
					} catch (_){ a.classList.remove('active'); }
					return;
				}
				const u = new URL(a.href, location.href);
				const hrefPath = (u.pathname || '/').replace(/\/$/, '');
				if(hrefPath === path) {
					// best possible match
					best = a; bestScore = Infinity;
					return;
				}
				// partial match score = length of shared prefix (but only if path starts with hrefPath)
				if(hrefPath !== '/' && path.startsWith(hrefPath + '/')) {
					const score = hrefPath.length;
					if(score > bestScore) { best = a; bestScore = score; }
				}
			}catch(_){ /* ignore */ }
		});
		// Clear all then mark best (if any)
		links.forEach(a=>a.classList.remove('active'));
		if(best) best.classList.add('active');
	}catch(_){/* ignore */}
}

// Keep the header "Iniciar sesión" CTA in sync when PJAX swaps or history changes.
function updateHeaderCTA() {
	try {
			let cta = document.querySelector(".site-header .btn-cta[href='/welcome']");
			const path = (location.pathname || '/').replace(/\/$/, '');
			// If CTA doesn't exist in the header (e.g., server hid it for welcome page and PJAX didn't replace header), recreate it
			if (!cta) {
				// don't create CTA for authenticated users or when on welcome page
				if (window.__isAuth) return;
				if (path === '/welcome' || path.startsWith('/welcome/')) return;
				try {
					const nav = document.querySelector('.site-header ul.nav.nav-pills');
					if (!nav) return;
					const li = document.createElement('li'); li.className = 'nav-item ms-2';
					const a = document.createElement('a');
					// Match the server-rendered structure so styles and ARIA behave the same
					a.className = 'nav-link btn-cta';
					a.setAttribute('role', 'button');
					a.setAttribute('href', '/welcome');
					a.textContent = 'Iniciar sesión';
					li.appendChild(a);
					nav.appendChild(li);
					cta = a;
					// Re-initialize quickLogin handlers if available so the new CTA is bound
					try { if (window.quickLoginPartial && typeof window.quickLoginPartial.init === 'function') window.quickLoginPartial.init(); } catch (_) {}
				} catch (_) { return; }
			}
		// If user is authenticated, do not show the CTA
		if (window.__isAuth) { cta.style.display = 'none'; return; }
			// Hide CTA when on the welcome page or any welcome/* route
			if (path === '/welcome' || path.startsWith('/welcome/')) {
			cta.style.display = 'none';
		} else {
			// restore default display (let CSS decide)
			cta.style.display = '';
		}
	} catch (_) {}
}
