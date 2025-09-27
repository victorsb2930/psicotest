// Lightweight notifications helper: polls for unread notifications and updates header badge + dropdown
import axios from 'axios';

let pollInterval = null;
const POLL_SECONDS = 20;

function buildItem(n) {
    const data = (typeof n.data === 'string') ? JSON.parse(n.data) : n.data || {};
    const title = data.title || data.message || data.body || JSON.stringify(data);
    const href = data.link || data.url || '#';
    const icon = data.icon || 'bell';
    return `
        <li>
            <a class="dropdown-item d-flex align-items-start notif-item" href="${href}" data-notif-id="${n.id}">
                <div class="me-2"><i class="bi bi-${icon} fs-5"></i></div>
                <div class="flex-grow-1">
                    <div class="small text-muted">${n.time}</div>
                    <div class="lh-1">${escapeHtml(title)}</div>
                </div>
            </a>
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
            // remove existing notif-item elements after the header (keep header at top)
            const header = dropdown.querySelector('.dropdown-header');
            // remove all existing items until divider
            const olds = dropdown.querySelectorAll('.notif-item');
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
                const el = document.createElement('li'); el.className = 'dropdown-item text-muted'; el.textContent = 'No hay notificaciones';
                if (insertAfter && insertAfter.nextSibling) insertAfter.parentNode.insertBefore(el, insertAfter.nextSibling);
                else if (insertAfter) insertAfter.parentNode.appendChild(el);
            }
            attachItemHandlers();
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

// Expose helper for manual use
export { startPolling, stopPolling, markAllRead };
