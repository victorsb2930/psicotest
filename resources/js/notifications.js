let pollInterval = null;
const POLL_SECONDS = 20;

function buildItem(n) {
    const data = (typeof n.data === 'string') ? JSON.parse(n.data) : (n.data || {});
    const title = n.title || data.title || data.message || data.body || JSON.stringify(data);
    const href = n.link || data.link || data.url || '#';
    const icon = n.icon || data.icon || 'bell';
    const body = n.body || data.body || '';
    return `
        <li class="px-2 py-1">
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
            // remove existing notif-item and notif-empty elements after the header (keep header at top)
            const header = dropdown.querySelector('.dropdown-header');
            // remove all existing dynamic items
            const olds = dropdown.querySelectorAll('.notif-item, .notif-empty');
            olds.forEach(o => o.parentElement && o.parentElement.remove());
            // insert items after header
            let insertAfter = header ? header : null;
            if (items && items.length) {
                items.forEach(it => {
                    const liHtml = buildItem(it);
                    if (insertAfter && insertAfter.nextSibling) {
                        insertAfter.parentNode.insertBefore(htmlToElement(liHtml), insertAfter.nextSibling);
                    } else if (insertAfter) {
                        insertAfter.parentNode.appendChild(htmlToElement(liHtml));
                    }
                });
            } else {
                const el = document.createElement('li'); el.className = 'dropdown-item text-muted notif-empty'; el.textContent = 'No hay notificaciones';
                if (insertAfter && insertAfter.nextSibling) insertAfter.parentNode.insertBefore(el, insertAfter.nextSibling);
                else if (insertAfter) insertAfter.parentNode.appendChild(el);
            }
            attachItemHandlers();
            // Re-initialize Bootstrap Dropdown instance for the toggle so it refreshes its internal menu reference
            try {
                const toggleEl = document.getElementById('globalNotifDropdown');
                if (toggleEl && window.bootstrap && window.bootstrap.Dropdown) {
                    // If an instance exists, dispose it so a new one will attach to the updated menu
                    const existing = window.bootstrap.Dropdown.getInstance(toggleEl);
                    if (existing && typeof existing.dispose === 'function') {
                        try { existing.dispose(); } catch (_) {}
                    }
                    // Create a fresh instance which will re-bind to the current menu element
                    window.bootstrap.Dropdown.getOrCreateInstance(toggleEl);
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
            toggle.addEventListener('click', function(){ setTimeout(fetchNotifications, 50); }, { once: false });
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
            try {
                const existing = window.bootstrap.Dropdown.getInstance(toggleEl);
                if (existing && typeof existing.dispose === 'function') existing.dispose();
            } catch (_) {}
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
            // ensure dropdown instance
            if (window.bootstrap && window.bootstrap.Dropdown) {
                try {
                    const existing = window.bootstrap.Dropdown.getInstance(toggleEl);
                    if (existing && typeof existing.dispose === 'function') existing.dispose();
                } catch(_){}
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
            const existing = window.bootstrap.Dropdown.getInstance(toggleEl);
            if (!existing || !document.getElementById('notif-dropdown-menu')) {
                try { if (existing && typeof existing.dispose === 'function') existing.dispose(); } catch (_) {}
                try { window.bootstrap.Dropdown.getOrCreateInstance(toggleEl); } catch (_) {}
            }
        }
    } catch (_) {}
}, true);

// Expose helper for manual use
export { startPolling, stopPolling, markAllRead };
