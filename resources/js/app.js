// Globals: jQuery, Bootstrap, Axios, tom-select, uuid, global functions, utils
import './bootstrap';
import './partials/quickLogin';

/* 
* Agrego aqui tambien la configuracion global CSRF => CSRF token mismatch.
*/

$.ajaxSetup({
	headers: {
		'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
	}
});

// Carga condicional de assets por página (pago completo - navegación tradicional)
async function initPage() {
	const page = document.body.dataset.page || 'default';
	switch (page) {
		case 'index':
			import('./index.js');
			break;
		case 'loginRegister':
			import('./loginRegister.js');
			break;
		case 'contact':
			import('./contact.js');
			break;
		case 'admin.roles':
			import('./pages/admin.roles.js');
			break;
		case 'admin-professional-apps':
			import('./pages/admin.profapps.js');
			break;
		default:
			break;
	}
}

// Ejecutar la inicialización al cargar la página (full navigation)
document.addEventListener('DOMContentLoaded', () => {
	initPage();
	// Start PJAX after initial page scripts loaded
	try { enablePJAX(); } catch (e) { /* if PJAX not supported ignore */ }
});

// Update visibility of the left-menu "Atrás" button when navigating via PJAX or on load
function isAreaRootPath() {
	const p = (location.pathname || '').replace(/\/$/, '');
	return ['/adminarea', '/professionalarea', '/userarea'].includes(p);
}

function updateLeftmenuBackButton() {
	const btn = document.querySelector('#left-menu a[aria-label="Atrás"]');
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
			// Update container
			container.innerHTML = newContent.innerHTML;
			// Update body data-page if present
			const pageEl = tmp.querySelector('[data-page]');
			if (pageEl && pageEl.dataset && pageEl.dataset.page) {
				document.body.dataset.page = pageEl.dataset.page;
			}
			if (addToHistory) history.pushState({}, '', url);
			// Re-run page-specific initialization
			initPage();
			// Update left-menu back button visibility after init
			try { updateLeftmenuBackButton(); } catch (e) {}
			// Scroll top
			window.scrollTo(0, 0);
		} catch (e) {
			console.error('PJAX error', e);
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
}