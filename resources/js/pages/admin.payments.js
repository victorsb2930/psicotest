// JS module for admin payments page (data-page = admin-payments)
import { modalConfirm } from '../utils/modalConfirm';

const getPayoutButton = () => document.getElementById('btn-create-payout');
const getBalanceElement = () => document.getElementById('admin-platform-balance');
const getPayoutsElement = () => document.getElementById('admin-platform-payouts');
const safeHtml = (value) => {
  const str = String(value ?? '');
  try {
    return typeof window !== 'undefined' && typeof window.escapeHtml === 'function'
      ? window.escapeHtml(str)
      : str.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c] || c));
  } catch (_) {
    return str;
  }
};

const formatUsd = (valueCents) => {
  const cents = Number.isFinite(valueCents) ? valueCents : 0;
  return '$' + (Math.max(0, cents) / 100).toFixed(2);
};

const formatAmount = (valueCents, currency = 'USD') => {
  const cents = Number.isFinite(valueCents) ? valueCents : 0;
  return (Math.max(0, cents) / 100).toFixed(2) + ' ' + (currency || 'USD');
};

const formatDateTime = (value) => {
  if (!value) return '—';
  try {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });
  } catch (_) {
    return value;
  }
};

function getPlatformBalanceCents() {
  const btn = getPayoutButton();
  if (!btn) return null;
  const raw = btn.dataset.platformBalanceCents;
  if (typeof raw === 'undefined') return null;
  const parsed = parseInt(raw, 10);
  return Number.isNaN(parsed) ? null : parsed;
}

function updatePlatformBalanceCents(newBalance) {
  const btn = getPayoutButton();
  if (!btn) return;
  const safeValue = Math.max(0, Number.isFinite(newBalance) ? newBalance : 0);
  btn.dataset.platformBalanceCents = String(safeValue);
  btn.dataset.platformBalance = (safeValue / 100).toFixed(2);
  const balanceEl = getBalanceElement();
  if (balanceEl) {
    balanceEl.dataset.balanceCents = String(safeValue);
    balanceEl.textContent = formatUsd(safeValue);
  }
}

const notify = (title, description, template = 'info') => {
  if (typeof window !== 'undefined' && typeof window.modalNotification === 'function') {
    window.modalNotification(title, description, { template });
  } else if (typeof window !== 'undefined') {
    try {
      window.alert(`${title}\n\n${description}`);
    } catch (_) {}
  }
};

function incrementPayoutTotals(amountCents) {
  const el = getPayoutsElement();
  if (!el) return;
  const current = parseInt(el.dataset.payoutsCents || '0', 10) || 0;
  const next = Math.max(0, current + (Number.isFinite(amountCents) ? amountCents : 0));
  el.dataset.payoutsCents = String(next);
  el.textContent = formatUsd(next);
}

function buildName(user, fallbackName = '—') {
  if (!user) return fallbackName;
  const name = [user.name, user.lastname].filter(Boolean).join(' ').trim();
  return name || fallbackName;
}

function buildUserCell(user, fallbackName = '—', fallbackEmail = '') {
  const name = buildName(user, fallbackName);
  const email = user?.email || fallbackEmail;
  if (!email) return safeHtml(name);
  return `${safeHtml(name)}<div class="small text-muted">${safeHtml(email)}</div>`;
}

function prependPaymentRow(payment, extras = {}) {
  if (!payment) return;
  const tbody = document.getElementById('admin-payments-tbody');
  if (!tbody) return;
  const row = document.createElement('tr');
  row.className = 'table-success';
  const recipientCell = buildUserCell(payment.recipient, extras.recipientName || '—', extras.recipientEmail || '');
  const payerCell = buildUserCell(payment.user, '—');
  const amountText = formatAmount(payment.amount_cents ?? payment.amountCents ?? 0, payment.currency);
  const statusText = safeHtml(payment.status ?? 'pending');
  const providerText = safeHtml(payment.provider ?? 'manual');
  const typeText = safeHtml(payment.type ?? 'payout');
  row.innerHTML = `
    <td>${safeHtml(payment.id ?? '—')}</td>
    <td>${safeHtml(formatDateTime(payment.created_at))}</td>
    <td>${payerCell}</td>
    <td>${recipientCell}</td>
    <td>${typeText}</td>
    <td>${safeHtml(amountText)}</td>
    <td>${statusText}</td>
    <td>${providerText}</td>
  `;
  tbody.prepend(row);
  setTimeout(() => {
    row.classList.remove('table-success');
  }, 4000);
}

function renderSearchResults(container, items) {
  container.innerHTML = '';
  if (!items || !items.length) {
    const no = document.createElement('div'); no.className = 'text-muted small'; no.textContent = 'No se encontraron profesionales'; container.appendChild(no); return;
  }
  const list = document.createElement('div'); list.className = 'list-group';
  items.forEach(u => {
    const btn = document.createElement('button'); btn.type = 'button'; btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-start';
    const left = document.createElement('div');
    left.className = 'ms-0';
    left.textContent = (u.name || '') + (u.lastname ? (' ' + u.lastname) : '');
    const small = document.createElement('div'); small.className = 'small text-muted'; small.textContent = u.email || '';
    left.appendChild(small);
    const badges = document.createElement('div'); badges.className = 'text-end small';
    const totalBadge = document.createElement('div'); totalBadge.innerHTML = `<span class="badge bg-secondary">Citas totales: ${u.completed_count ?? 0}</span>`;
    const monthBadge = document.createElement('div'); monthBadge.innerHTML = `<span class="badge bg-info text-dark">Este mes: ${u.completed_count_month ?? 0}</span>`;
    badges.appendChild(totalBadge); badges.appendChild(monthBadge);
    btn.appendChild(left);
    btn.appendChild(badges);
    btn.dataset.userId = u.id;
    btn.dataset.userName = (u.name || '') + (u.lastname ? (' ' + u.lastname) : '');
    if (u.email) btn.dataset.userEmail = u.email;
    btn.addEventListener('click', () => {
      // mark selected
      container.querySelectorAll('.list-group-item').forEach(n => n.classList.remove('active'));
      btn.classList.add('active');
      const target = document.getElementById('admin-payout-selected-id');
      if (target) {
        target.value = u.id;
        target.dataset.completed = (u.completed_count ?? 0);
        target.dataset.completedMonth = (u.completed_count_month ?? 0);
        target.dataset.email = u.email || '';
        target.dataset.name = btn.dataset.userName || '';
      }
      const targetName = document.getElementById('admin-payout-selected-name'); if (targetName) targetName.textContent = btn.dataset.userName;
      // show additional info about counts
      const infoEl = document.getElementById('admin-payout-selected-info'); if (infoEl) {
        infoEl.innerHTML = `Citas completadas: <strong>${u.completed_count ?? 0}</strong> — Este mes: <strong>${u.completed_count_month ?? 0}</strong>`;
      }
        try { syncAppointmentCountWithSelection(); } catch (err) { }
    });
    list.appendChild(btn);
  });
  container.appendChild(list);
}

async function searchProfessionals(q, container) {
  try {
    const url = '/professionals/search?q=' + encodeURIComponent(q);
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!res.ok) return renderSearchResults(container, []);
    const j = await res.json();
    return renderSearchResults(container, Array.isArray(j) ? j : []);
  } catch (e) { renderSearchResults(container, []); }
}

function buildBodyHtml() {
  const body = `
    <div class="mb-2">
      <label class="form-label">Buscar profesional por nombre o email</label>
      <input id="admin-payout-search" class="form-control" placeholder="Escribe nombre o email">
    </div>
    <div class="mb-2">
      <div id="admin-payout-results" style="max-height:240px; overflow:auto; border:1px solid #eee; padding:6px; border-radius:6px"></div>
    </div>
    <div class="mb-2">
      <label class="form-label">Seleccionado</label>
      <div><strong id="admin-payout-selected-name" class="me-2">—</strong></div>
        <div id="admin-payout-selected-info" class="small text-muted">&nbsp;</div>
      <input type="hidden" id="admin-payout-selected-id">
    </div>
      <div class="mb-2"><label class="form-label">Monto (ej. 12.34)</label><input id="admin-payout-amount" class="form-control" placeholder="0.00"></div>
      <div class="mb-2 d-flex gap-2 align-items-center">
        <div style="min-width:160px">
          <label class="form-label">Periodo</label>
          <select id="admin-payout-period" class="form-select"><option value="total">Total</option><option value="month">Este mes</option></select>
        </div>
        <div style="min-width:160px">
          <label class="form-label">Tarifa por cita</label>
          <input id="admin-payout-rate" class="form-control" placeholder="Ej. 10.00">
        </div>
        <div style="padding-top:24px">
          <button id="admin-payout-calc" type="button" class="btn btn-outline-primary btn-sm">Calcular</button>
        </div>
      </div>
    <div class="mb-2">
      <label class="form-label">Citas incluidas en este pago</label>
      <input id="admin-payout-appointments-count" type="number" min="0" class="form-control" value="0">
      <div class="form-text">Se actualiza al seleccionar profesional o al calcular, pero puedes ajustarlo manualmente.</div>
    </div>
    <div class="mb-2"><label class="form-label">Moneda</label><input id="admin-payout-currency" class="form-control" value="USD"></div>
    <div class="mb-2">
      <label class="form-label">Notas para el recibo (opcional)</label>
      <textarea id="admin-payout-notes" class="form-control" rows="2" placeholder="Ej. Pago correspondiente a las citas de noviembre"></textarea>
    </div>
    <div id="admin-payout-feedback" class="text-danger small" style="display:none"></div>
  `;
  return body;
}

async function openPayoutModal() {
  const bodyHtml = { title: 'Crear Payout', body: buildBodyHtml() };
  bodyHtml.onClickYes = async function () {
    const feedback = document.getElementById('admin-payout-feedback'); if (feedback) { feedback.style.display = 'none'; }
    const recipient = document.getElementById('admin-payout-selected-id')?.value || '';
    const amount = document.getElementById('admin-payout-amount')?.value?.trim() || '';
    const currency = document.getElementById('admin-payout-currency')?.value?.trim() || 'USD';
    const appointmentsCount = parseInt(document.getElementById('admin-payout-appointments-count')?.value || '0', 10);
    const period = document.getElementById('admin-payout-period')?.value || 'total';
    const rateValue = document.getElementById('admin-payout-rate')?.value?.trim() || '';
    const notes = document.getElementById('admin-payout-notes')?.value?.trim() || '';
    const selectedMeta = document.getElementById('admin-payout-selected-id');
    const selectedName = document.getElementById('admin-payout-selected-name')?.textContent?.trim() || 'el profesional';
    const selectedEmail = selectedMeta?.dataset.email || '';
    if (!recipient) { if (feedback) { feedback.textContent = 'Selecciona un profesional.'; feedback.style.display = ''; } return; }
    if (!amount) { if (feedback) { feedback.textContent = 'Ingresa un monto válido.'; feedback.style.display = ''; } notify('Monto inválido', 'Ingresa un monto mayor a cero.', 'warning'); return; }
    const normalizedAmount = amount.replace(',', '.');
    const numericAmount = Number.parseFloat(normalizedAmount);
    if (!Number.isFinite(numericAmount) || numericAmount <= 0) {
      if (feedback) { feedback.textContent = 'Ingresa un monto mayor a cero.'; feedback.style.display = ''; }
      notify('Monto inválido', 'El monto debe ser mayor a cero.', 'warning');
      return;
    }
    const amountCents = Math.round(numericAmount * 100);
    const balanceCents = getPlatformBalanceCents();
    if (balanceCents !== null && amountCents > balanceCents) {
      const available = (balanceCents / 100).toFixed(2);
      const msg = `Fondos insuficientes. Disponible: ${available}`;
      if (feedback) { feedback.textContent = msg; feedback.style.display = ''; }
      notify('Fondos insuficientes', msg, 'warning');
      return;
    }
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    try {
      const payload = { recipient_user_id: recipient, amount, currency, appointments_count: appointmentsCount, period, rate: rateValue, notes };
      const res = await fetch('/admin/payments/payout', { method: 'POST', headers: Object.assign({ 'Accept': 'application/json', 'Content-Type': 'application/json' }, token ? { 'X-CSRF-TOKEN': token } : {}), body: JSON.stringify(payload) });
      const j = await res.json().catch(()=>null);
      if (!res.ok || !(j && j.ok)) {
        const message = (j && j.message) ? j.message : 'Error creando payout';
        if (feedback) { feedback.textContent = message; feedback.style.display = ''; }
        notify('No se pudo crear el payout', message, res.status === 422 ? 'warning' : 'danger');
        return;
      }
      const successMsg = `Registramos el payout de $${numericAmount.toFixed(2)} ${currency} para ${selectedName}.`;
      notify('Payout registrado', successMsg, 'success');
      if (balanceCents !== null) {
        updatePlatformBalanceCents(Math.max(0, balanceCents - amountCents));
      }
      incrementPayoutTotals(amountCents);
      prependPaymentRow(j.payment, { recipientName: selectedName, recipientEmail: selectedEmail });
    } catch (e) {
      if (feedback) { feedback.textContent = 'Error de red'; feedback.style.display = ''; }
      notify('Error de red', 'No se pudo conectar con el servidor.', 'danger');
    }
  };

  modalConfirm(bodyHtml, 'normal');

  // After modal created, wire search behavior
  setTimeout(() => {
    const input = document.getElementById('admin-payout-search');
    const results = document.getElementById('admin-payout-results');
    const periodSelect = document.getElementById('admin-payout-period');
    if (!input || !results) return;
    let tId = null;
    input.addEventListener('input', function () {
      const q = this.value.trim();
      if (tId) clearTimeout(tId);
      tId = setTimeout(() => {
        if (!q) { renderSearchResults(results, []); return; }
        searchProfessionals(q, results);
      }, 250);
    });
    // support enter to search immediately
    input.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); searchProfessionals(this.value.trim(), results); } });
    if (periodSelect) {
      periodSelect.addEventListener('change', () => {
        try { syncAppointmentCountWithSelection(); } catch (_) {}
      });
    }
  }, 80);
  // wire calculate button after modal present as well
  setTimeout(() => {
    const calc = document.getElementById('admin-payout-calc');
    if (!calc) return;
    calc.addEventListener('click', () => {
      const selected = document.getElementById('admin-payout-selected-id');
      const rateEl = document.getElementById('admin-payout-rate');
      const periodEl = document.getElementById('admin-payout-period');
      const amountEl = document.getElementById('admin-payout-amount');
      const countEl = document.getElementById('admin-payout-appointments-count');
      const feedback = document.getElementById('admin-payout-feedback'); if (feedback) feedback.style.display = 'none';
      if (!selected || !selected.value) { if (feedback) { feedback.textContent = 'Selecciona un profesional primero.'; feedback.style.display = ''; } return; }
      const rate = parseFloat((rateEl?.value || '').toString().replace(',', '.')) || 0;
      if (!rate || rate <= 0) { if (feedback) { feedback.textContent = 'Ingresa una tarifa válida.'; feedback.style.display = ''; } return; }
      const period = (periodEl?.value || 'total');
      const count = period === 'month' ? parseInt(selected.dataset.completedMonth || '0', 10) : parseInt(selected.dataset.completed || '0', 10);
      const calcAmount = (count * rate);
      if (amountEl) amountEl.value = calcAmount.toFixed(2);
      if (countEl) countEl.value = count;
    });
  }, 160);
}

function syncAppointmentCountWithSelection() {
  const selected = document.getElementById('admin-payout-selected-id');
  const countEl = document.getElementById('admin-payout-appointments-count');
  const periodEl = document.getElementById('admin-payout-period');
  if (!selected || !countEl) return;
  if (!selected.value) { countEl.value = '0'; return; }
  const period = periodEl?.value || 'total';
  const count = period === 'month' ? parseInt(selected.dataset.completedMonth || '0', 10) : parseInt(selected.dataset.completed || '0', 10);
  countEl.value = isNaN(count) ? '0' : String(count);
}

export function init(){
  const btn = document.getElementById('btn-create-payout');
  if (btn) btn.addEventListener('click', openPayoutModal);
}

export function destroy(){
  try { const btn = document.getElementById('btn-create-payout'); if (btn) btn.removeEventListener('click', openPayoutModal); } catch(_){}
}
