// Two Factor Challenge page module
// Exports init() and destroy() for dynamic page loader

let _resendHandler = null;

function notify(title, body, opts) {
    // Prefer modalNotification or showNotification, fallback console
    try {
        if (typeof modalNotification === 'function') { modalNotification(title, body, opts); return; }
        if (typeof showNotification === 'function') { showNotification(title, body, opts); return; }
    } catch (_) { }
    console.log(title + ': ' + body);
}

async function resendCode(btn) {
    const url = '/auth/2fa-challenge'; // POST endpoint (named route auth.2fa.challenge.verify)
    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
    const csrf = tokenMeta ? tokenMeta.getAttribute('content') : '';
    btn.disabled = true;
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify({ resend: 1 })
        });
        let j = null;
        try { j = await res.json(); } catch (_) { j = null; }
        if (j && j.ok) {
            notify('2FA', 'Código reenviado.', { template: 'info' });
        } else {
            notify('Error', (j && j.message) || 'No se pudo reenviar.', { template: 'danger' });
        }
    } catch (e) {
        notify('Error', 'Error de red', { template: 'danger' });
    } finally {
        btn.disabled = false;
    }
}

export function init() {
    const btn = document.getElementById('btnResend2fa');
    if (btn) {
        _resendHandler = (e) => { e.preventDefault(); resendCode(btn); };
        btn.addEventListener('click', _resendHandler);
    }
}

export function destroy() {
    const btn = document.getElementById('btnResend2fa');
    try { if (btn && _resendHandler) btn.removeEventListener('click', _resendHandler); } catch (_) { }
    _resendHandler = null;
}