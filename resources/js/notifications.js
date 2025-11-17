let pollInterval = null;
const POLL_SECONDS = 20;

function buildItem(n) {
    const data = (typeof n.data === 'string') ? JSON.parse(n.data) : (n.data || {});
    const title = n.title || data.title || data.message || data.body || JSON.stringify(data);
    const href = n.link || data.link || data.url || '#';
    const icon = n.icon || data.icon || 'bell';
    const body = n.body || data.body || '';
    return `
        <li class="px-2 py-1 notif-row" data-notif-id="${n.id}">
            <div class="d-flex align-items-start gap-2">
                <div class="pt-1"><i class="bi bi-${icon} fs-5"></i></div>
                <div class="flex-grow-1 overflow-hidden">
                    <a class="notif-item text-decoration-none d-block" href="${href}" data-notif-id="${n.id}">
                        <div class="small text-muted">${n.time}</div>
                        <div class="lh-1 fw-semibold text-dark">${escapeHtml(title)}</div>
                        ${body ? `<div class="small text-muted text-truncate">${escapeHtml(body)}</div>` : ''}
                    </a>
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="Acciones">
                    <button type="button" class="btn btn-outline-secondary notif-mark" data-notif-id="${n.id}" title="Marcar como leído"><i class="bi bi-check2"></i></button>
                    <button type="button" class="btn btn-outline-danger notif-del" data-notif-id="${n.id}" title="Eliminar"><i class="bi bi-x"></i></button>
                </div>
            </div>
        </li>
    `;
}

function escapeHtml(str) {
    return String(str).replace(/[&<>\"']/g, function (s) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[s];
    });
}

async function fetchNotifications() {
    try {
        const res = await axios.get('/api/notifications/unread');
        if (!res || !res.data) return;
        const { count, items } = res.data;
        const badge = document.getElementById('notif-badge');
        if (badge) {
            if (count > 0) {
                badge.style.display = '';
                badge.textContent = count;
            } else {
                badge.style.display = 'none';
                badge.textContent = '';
            }
        }
        const dropdown = document.getElementById('notif-dropdown-menu');
        if (dropdown) {
            const header = dropdown.querySelector('.dropdown-header');
            // Remove any legacy server-rendered notification rows (anchor .notif-item not inside .notif-row)
            dropdown.querySelectorAll('li > a.notif-item').forEach(a => {
                // If parent li does not have .notif-row class, remove it (old format)
                const li = a.closest('li');
                if (li && !li.classList.contains('notif-row')) li.remove();
            });
            // Remove all dynamic rows and empty placeholders
            dropdown.querySelectorAll('.notif-row, .notif-empty').forEach(el => el.remove());
            let anchorNode = header; // insert after header
            if (items && items.length) {
                items.forEach(it => {
                    const liEl = htmlToElement(buildItem(it));
                    if (anchorNode) {
                        anchorNode.parentNode.insertBefore(liEl, anchorNode.nextSibling);
                        anchorNode = liEl;
                    } else {
                        dropdown.appendChild(liEl);
                        anchorNode = liEl;
                    }
                });
            } else {
                const emptyEl = document.createElement('li');
                emptyEl.className = 'dropdown-item text-muted notif-empty';
                emptyEl.textContent = 'No hay notificaciones';
                if (anchorNode) anchorNode.parentNode.insertBefore(emptyEl, anchorNode.nextSibling);
                else dropdown.appendChild(emptyEl);
            }
            attachItemHandlers();
            // Refresh dropdown position without recreating instance (avoid flicker / alignment drift)
            try {
                const toggleEl = document.getElementById('globalNotifDropdown');
                if (toggleEl && window.bootstrap && window.bootstrap.Dropdown) {
                    const existing = window.bootstrap.Dropdown.getInstance(toggleEl) || window.bootstrap.Dropdown.getOrCreateInstance(toggleEl);
                    // Bootstrap 5 exposes _popper; use update if available
                    if (existing && typeof existing.update === 'function') {
                        existing.update();
                    } else if (existing && existing._popper && typeof existing._popper.update === 'function') {
                        existing._popper.update();
                    }
                }
            } catch (_) {}
        }
    } catch (e) {
        // ignore polling errors silently
        // console.error('notif fetch', e);
    }
}

function htmlToElement(html) {
    const template = document.createElement('template');
    template.innerHTML = html.trim();
    return template.content.firstChild;
}

function attachItemHandlers() {
    const items = document.querySelectorAll('#notif-dropdown-menu .notif-item');
    items.forEach(a => {
        if (a._notifBound) return; // idempotent
        a._notifBound = true;
        a.addEventListener('click', async function (ev) {
            ev.preventDefault();
            const id = this.dataset?.notifId;
            const href = this.getAttribute('href') || '#';
            try {
                if (id) await axios.post('/api/notifications/mark-read', { id });
            } catch (_) {}
            // follow link after marking read
            window.location.href = href;
        });
    });
    // mark read buttons
    document.querySelectorAll('#notif-dropdown-menu .notif-mark').forEach(btn => {
        if (btn._notifBound) return; btn._notifBound = true;
        btn.addEventListener('click', async function (ev) {
            ev.preventDefault(); ev.stopPropagation();
            const id = this.dataset?.notifId; if (!id) return;
            try { await axios.post('/api/notifications/mark-read', { id }); } catch(_){ }
            // remove row immediately for snappier UX
            try {
                const li = this.closest('li'); if (li) li.remove();
            } catch(_) {}
            // refresh asynchronously to update badge/count
            setTimeout(fetchNotifications, 300);
        });
    });
    // delete buttons
    document.querySelectorAll('#notif-dropdown-menu .notif-del').forEach(btn => {
        if (btn._notifBound) return; btn._notifBound = true;
        btn.addEventListener('click', async function (ev) {
            ev.preventDefault(); ev.stopPropagation();
            const id = this.dataset?.notifId; if (!id) return;
            try { await axios.post('/api/notifications/delete', { id }); } catch(_){ }
            // remove row immediately
            try {
                const li = this.closest('li'); if (li) li.remove();
            } catch(_) {}
            setTimeout(fetchNotifications, 300);
        });
    });
}

async function markAllRead() {
    try {
        await axios.post('/api/notifications/mark-read-all');
        await fetchNotifications();
    } catch (e) {}
}

function startPolling() {
    stopPolling();
    fetchNotifications();
    pollInterval = setInterval(fetchNotifications, POLL_SECONDS * 1000);
}

function stopPolling() {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
}

// Initialize automatically if user appears authenticated
document.addEventListener('DOMContentLoaded', function () {
    try {
        if (window.__isAuth) startPolling();
        // Refresh immediately when dropdown is opened for fresher UI without F5
        const toggle = document.getElementById('globalNotifDropdown');
        if (toggle) {
            let lastFetch = 0;
            toggle.addEventListener('click', function(){
                const now = Date.now();
                // throttle: only refetch if > 2s since last
                if (now - lastFetch > 2000) {
                    lastFetch = now;
                    fetchNotifications();
                }
            }, { once: false });
        }
        // Wire up "Marcar todo como leído" form/button on the notifications page
        const markAllForm = document.querySelector('form[action$="notifications/mark-read"]');
        if (markAllForm) {
            markAllForm.addEventListener('submit', function (ev) {
                // Let the server form work as-is; also refresh badge after submit
                setTimeout(() => fetchNotifications(), 1000);
            });
        }
    } catch (_) {}
});

// Ensure the notification dropdown menu exists at startup. If some client-side
// update (PJAX, partial replace) removed it, recreate a minimal placeholder so
// Bootstrap's dropdown has a menu to bind to and avoids null reference errors.
document.addEventListener('DOMContentLoaded', function () {
    try {
        const toggleEl = document.getElementById('globalNotifDropdown');
        if (!toggleEl) return;
        let menu = document.getElementById('notif-dropdown-menu');
        if (!menu) {
            const parent = toggleEl.parentElement;
            if (parent) {
                const ul = document.createElement('ul');
                ul.id = 'notif-dropdown-menu';
                ul.className = 'dropdown-menu dropdown-menu-end shadow';
                ul.setAttribute('aria-labelledby', 'globalNotifDropdown');
                ul.innerHTML = '<li class="dropdown-header">Notificaciones</li><li class="dropdown-item text-muted notif-empty">No hay notificaciones</li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-center small" href="/notifications">Ver todas</a></li>';
                parent.appendChild(ul);
                menu = ul;
            }
        }
        // Ensure a Bootstrap Dropdown instance exists and is fresh
        if (window.bootstrap && window.bootstrap.Dropdown) {
            try { window.bootstrap.Dropdown.getOrCreateInstance(toggleEl); } catch (_) {}
        }
    } catch (_) {}
});

// Watch for DOM changes that remove or replace the notification menu (PJAX, partial renders)
// If the menu disappears, recreate it and rebind the Bootstrap Dropdown to avoid runtime errors.
(function(){
    try {
        const ensureMenu = function(){
            const toggleEl = document.getElementById('globalNotifDropdown');
            if (!toggleEl) return;
            let menu = document.getElementById('notif-dropdown-menu');
            if (!menu) {
                const parent = toggleEl.parentElement;
                if (parent) {
                    const ul = document.createElement('ul');
                    ul.id = 'notif-dropdown-menu';
                    ul.className = 'dropdown-menu dropdown-menu-end shadow';
                    ul.setAttribute('aria-labelledby', 'globalNotifDropdown');
                    ul.innerHTML = '<li class="dropdown-header">Notificaciones</li><li class="dropdown-item text-muted notif-empty">No hay notificaciones</li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-center small" href="/notifications">Ver todas</a></li>';
                    parent.appendChild(ul);
                    menu = ul;
                }
            }
            // ensure dropdown instance (do not dispose to keep alignment state stable)
            if (window.bootstrap && window.bootstrap.Dropdown) {
                try { window.bootstrap.Dropdown.getOrCreateInstance(toggleEl); } catch(_){}
            }
        };

        const observer = new MutationObserver(function(mutations){
            let relevant = false;
            for(const m of mutations){
                if (m.type === 'childList') {
                    // if nodes removed/added in subtree, we should ensure menu
                    if (m.removedNodes && m.removedNodes.length) relevant = true;
                    if (m.addedNodes && m.addedNodes.length) relevant = true;
                }
            }
            if (relevant) {
                // small debounce
                setTimeout(ensureMenu, 50);
            }
        });

        observer.observe(document.documentElement || document.body, { childList: true, subtree: true });
        // run once at registration
        ensureMenu();
    } catch(_){}
})();

// Capture-phase handler: ensure dropdown instance/menu exist before Bootstrap's delegated handler runs
document.addEventListener('click', function (ev) {
    try {
        const toggleEl = document.getElementById('globalNotifDropdown');
        if (!toggleEl) return;
        // If the click is inside the toggle itself, run the guard
        const clicked = ev.target && (ev.target === toggleEl || toggleEl.contains(ev.target) || ev.target.closest && ev.target.closest('#globalNotifDropdown'));
        if (!clicked) return;

        const menuEl = document.getElementById('notif-dropdown-menu');
        // If menu is missing, stop propagation (avoid Bootstrap errors) and create an empty menu placeholder
        if (!menuEl) {
            ev.stopPropagation();
            ev.preventDefault();
            // Recreate a minimal menu to avoid errors
            const parent = toggleEl.parentElement;
            if (parent) {
                const ul = document.createElement('ul');
                ul.id = 'notif-dropdown-menu';
                ul.className = 'dropdown-menu dropdown-menu-end shadow';
                ul.setAttribute('aria-labelledby', 'globalNotifDropdown');
                ul.innerHTML = '<li class="dropdown-header">Notificaciones</li><li class="dropdown-item text-muted notif-empty">No hay notificaciones</li>';
                parent.appendChild(ul);
            }
        }

        if (window.bootstrap && window.bootstrap.Dropdown) {
            const existing = window.bootstrap.Dropdown.getInstance(toggleEl) || window.bootstrap.Dropdown.getOrCreateInstance(toggleEl);
            try {
                if (existing && typeof existing.update === 'function') existing.update();
                else if (existing && existing._popper && typeof existing._popper.update === 'function') existing._popper.update();
            } catch(_){}
        }
    } catch (_) {}
}, true);

// Expose helper for manual use
export { startPolling, stopPolling, markAllRead };
