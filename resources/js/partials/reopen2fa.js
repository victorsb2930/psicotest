// Minimal module to handle reopen-2FA modal and confirm/resend actions.
export default (function(){
    // create modal when requested
    function ensureModal() {
        if (document.getElementById('reopen2faModal')) return document.getElementById('reopen2faModal');
        const modalHtml = `
        <div class="modal fade" id="reopen2faModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Confirmar reapertura de sesión</h5></div>
                    <div class="modal-body">
                        <p>Hemos enviado un código a tu correo o por SMS (según tu elección). Introduce el código de 6 dígitos para confirmar.</p>
                        <div class="mb-2">
                            <label class="form-label small">Enviar vía</label>
                            <select id="reopen2faMethod" class="form-select form-select-sm">
                                <option value="email">Correo electrónico</option>
                                <option value="sms">SMS (si tienes teléfono registrado)</option>
                            </select>
                        </div>
                        <input id="reopen2faCodeGlobal" class="form-control mb-2" maxlength="6" inputmode="numeric" placeholder="Código de 6 dígitos">
                        <div id="reopen2faGlobalError" class="text-danger small" style="display:none"></div>
                    </div>
                    <div class="modal-footer">
                        <button id="reopen2faResend" type="button" class="btn btn-link">Reenviar</button>
                        <button id="reopen2faCancelGlobal" type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button id="reopen2faSubmitGlobal" type="button" class="btn btn-primary">Confirmar</button>
                    </div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        return document.getElementById('reopen2faModal');
    }

    async function confirmCode(code) {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        const headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (tokenMeta) headers['X-CSRF-TOKEN'] = tokenMeta.getAttribute('content');
        try {
            const res = await fetch('/profile/heartbeat/confirm', { method: 'POST', credentials: 'same-origin', headers, body: JSON.stringify({ code }) });
            return await res.json();
        } catch (e) { return { ok: false, message: 'network' }; }
    }

    async function resend(method = 'email') {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        const headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (tokenMeta) headers['X-CSRF-TOKEN'] = tokenMeta.getAttribute('content');
        try {
            const res = await fetch('/profile/heartbeat/resend', { method: 'POST', credentials: 'same-origin', headers, body: JSON.stringify({ method }) });
            return await res.json();
        } catch (e) { return { ok:false, message:'network' }; }
    }

    function showModal() {
        const el = ensureModal();
        const bs = new bootstrap.Modal(el);
        const input = document.getElementById('reopen2faCodeGlobal');
        const err = document.getElementById('reopen2faGlobalError');
    const submit = document.getElementById('reopen2faSubmitGlobal');
    const resendBtn = document.getElementById('reopen2faResend');
    const methodSel = document.getElementById('reopen2faMethod');
        err.style.display = 'none'; input.value = '';
        submit.disabled = false;
        submit.onclick = async function(){
            const code = (input.value || '').trim();
            if (!/^[0-9]{6}$/.test(code)) { err.textContent = 'Introduce un código válido de 6 dígitos.'; err.style.display='block'; return; }
            submit.disabled = true; err.style.display = 'none';
            const j = await confirmCode(code);
            if (j && j.ok) { bs.hide(); location.reload(); }
            else { err.textContent = j.message || 'Código incorrecto'; err.style.display = 'block'; }
            submit.disabled = false;
        };
        resendBtn.onclick = async function(){
            resendBtn.disabled = true;
            const m = (methodSel && methodSel.value) ? methodSel.value : 'email';
            const r = await resend(m);
            if (!r.ok) { err.textContent = r.message || 'No se pudo reenviar'; err.style.display='block'; }
            else { err.textContent = 'Código reenviado.'; err.style.display='block'; }
            setTimeout(()=>{ err.style.display = 'none'; resendBtn.disabled = false; }, 3000);
        };
        bs.show(); setTimeout(()=> input.focus(), 250);
    }

    // Listen for global event dispatched by heartbeat when server requires 2FA
    window.addEventListener('pg:reopen-2fa-required', function(e){ showModal(); });

    // expose for debugging
    return { showModal, confirmCode, resend };
})();
