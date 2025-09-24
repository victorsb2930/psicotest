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

// Carga condicional de assets por página con soporte de ciclo de vida { init, destroy }
let currentPageModule = null;
let currentPageModuleDestroy = null;
let currentPageName = null;

const pageModuleMap = {
	'index': './index.js',
	'loginRegister': './loginRegister.js',
	'contact': './contact.js',
	'admin.roles': './pages/admin.roles.js',
	'admin-professional-apps': './pages/admin.profapps.js'
};

async function initPage() {
	const page = document.body.dataset.page || 'default';
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
			try { mod.init(); } catch (e) { console.error('page init error', page, e); }
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
		console.error('initPage import error', page, e);
		currentPageModule = null;
		currentPageModuleDestroy = null;
	}
}

// Ejecutar la inicialización al cargar la página (full navigation)
document.addEventListener('DOMContentLoaded', () => {
	initPage();
	// Start PJAX after initial page scripts loaded
	try { enablePJAX(); } catch (e) { /* if PJAX not supported ignore */ }
});

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
			} catch (e) { console.warn('error running destroy for current page', e); }

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
			// Update body data-page if present
			const pageEl = tmp.querySelector('[data-page]');
			if (pageEl && pageEl.dataset && pageEl.dataset.page) {
				document.body.dataset.page = pageEl.dataset.page;
			}
			if (addToHistory) history.pushState({}, '', url);
			if (addToHistory) {
				try { pushToNavStack((new URL(url, location.href)).pathname); } catch(_){}
			}
			// Re-run page-specific initialization
			try { await initPage(); } catch (e) { console.error('initPage after swap error', e); }
			// Update left-menu back button visibility after init
			try { updateLeftmenuBackButton(); } catch (e) {}

			// Recalculate active left-menu link based on current location
			try { updateLeftmenuActive(); } catch (e) {}
			// Recalculate active left-menu link after PJAX swap
			try { updateLeftmenuActive(); } catch (e) {}
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