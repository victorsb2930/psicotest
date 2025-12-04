import { modalConfirm } from '../utils/modalConfirm';

const formListeners = [];

const notify = (title, description, template = 'info') => {
    if (typeof window !== 'undefined' && typeof window.modalNotification === 'function') {
        window.modalNotification(title, description, { template });
    }
};

function submitWithoutIntercept(form) {
    form.dataset.skipConfirm = '1';
    try {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }
        form.submit();
    } finally {
        setTimeout(() => {
            delete form.dataset.skipConfirm;
        }, 0);
    }
}

function buildDetailsHtml(form) {
    const amount = (form.dataset.confirmAmount || '').trim();
    const currency = (form.dataset.confirmCurrency || '').trim();
    const destination = (form.dataset.confirmDestination || '').trim();
    const created = (form.dataset.confirmCreated || '').trim();
    const provider = (form.dataset.confirmProvider || '').trim();
    const reference = (form.dataset.confirmReference || '').trim();
    const rows = [];
    if (amount || currency) {
        rows.push({ label: 'Monto', value: `${amount || '—'} ${currency}`.trim() });
    }
    if (destination) rows.push({ label: 'Destino', value: destination });
    if (created) rows.push({ label: 'Generado', value: created });
    if (provider) rows.push({ label: 'Proveedor', value: provider });
    if (reference) rows.push({ label: 'Referencia', value: reference });
    if (!rows.length) return '';
    const items = rows.map(r => `
        <div class="d-flex justify-content-between small py-1 border-bottom">
            <span class="text-muted">${r.label}</span>
            <span class="fw-semibold text-dark">${r.value}</span>
        </div>
    `).join('');
    return `<div class="bg-light rounded-3 px-3 py-2 mt-3">${items}</div>`;
}

function openConfirmModal(form) {
    const amount = (form.dataset.confirmAmount || '').trim();
    const currency = (form.dataset.confirmCurrency || '').trim();
    const destination = (form.dataset.confirmDestination || 'tu cuenta destino').trim();
    const message = amount
        ? `Confirma que recibiste <strong>${amount} ${currency}</strong> en ${destination}.`
        : 'Confirma que recibiste este payout en tu destino configurado.';
    const details = buildDetailsHtml(form);
    modalConfirm({
        title: 'Confirmar recepción del payout',
        subtitle: 'Actualizaremos el estado a succeeded',
        icon: 'fa-circle-check',
        body: `
            <p class="mb-2">${message}</p>
            <p class="text-muted small mb-0">Esta acción notificará al equipo y no se puede deshacer.</p>
            ${details}
        `,
        btnsType: 'ac',
        confirmLabel: 'Sí, ya lo recibí',
        cancelLabel: 'Cancelar',
        onClickYes: () => {
            notify('Enviando confirmación…', 'Actualizaremos tu historial en segundos.', 'info');
            submitWithoutIntercept(form);
        }
    }, 'normal', { size: 'md' });
}

function bindForm(form) {
    if (!form || formListeners.find(item => item.form === form)) return;
    const handler = function (ev) {
        if (this.dataset.skipConfirm === '1') {
            return;
        }
        ev.preventDefault();
        openConfirmModal(this);
    };
    form.addEventListener('submit', handler);
    formListeners.push({ form, handler });
}

function attachConfirmForms() {
    const forms = document.querySelectorAll('form[data-payout-confirm="true"]');
    forms.forEach(bindForm);
}

export function init() {
    attachConfirmForms();
}

export function destroy() {
    formListeners.splice(0).forEach(({ form, handler }) => {
        try {
            form.removeEventListener('submit', handler);
        } catch (_) {}
    });
}
