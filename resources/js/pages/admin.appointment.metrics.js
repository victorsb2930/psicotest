import { Chart } from 'chart.js/auto';

async function fetchMetrics(limit = 60) {
  try {
    const endpointEl = document.querySelector('[data-metrics-endpoint]');
    const url = endpointEl ? endpointEl.getAttribute('data-metrics-endpoint') : null;
    if (!url) return null;
    const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if (!r.ok) return null; const j = await r.json(); if (!j.ok) return null; return j.days || [];
  } catch (_) { return null; }
}

function buildCharts(rows) {
  const statusEl = document.getElementById('chart-status');
  const qualityEl = document.getElementById('chart-quality');
  const networkEl = document.getElementById('chart-network');
  if (!statusEl || !qualityEl || !networkEl) return;
  const fmt = {
    pct: v => (v == null ? '—' : v.toFixed(1) + '%'),
    kbps: v => (v == null ? '—' : v.toFixed(0) + ' kbps'),
    ms: v => (v == null ? '—' : v.toFixed(0) + ' ms'),
    num: v => (v == null ? '—' : v.toString())
  };
  if (!Array.isArray(rows) || !rows.length) {
    const statusSummary = document.getElementById('chart-status-summary');
    if (statusSummary) statusSummary.textContent = 'Sin datos';
    return;
  }
  const data = [...rows].reverse();
  const labels = data.map(d => d.day);
  const completed = data.map(d => d.completed);
  const noShow = data.map(d => d.no_show);
  const skipped = data.map(d => d.skipped);
  const total = data.map(d => d.total);
  const pctCompleted = data.map(d => d.total > 0 ? (d.completed / d.total * 100) : 0);
  const pctNoShow = data.map(d => d.total > 0 ? (d.no_show / d.total * 100) : 0);
  const pctSkipped = data.map(d => d.total > 0 ? (d.skipped / d.total * 100) : 0);
  const bitrate = data.map(d => d.avg_bitrate_kbps ?? null);
  const loss = data.map(d => d.avg_loss_pct ?? null);
  const rtt = data.map(d => d.avg_rtt_ms ?? null);
  const retries = data.map(d => d.avg_retries ?? null);

  new Chart(statusEl, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        { label: 'Completadas', data: completed, backgroundColor: '#198754' },
        { label: 'No-Show', data: noShow, backgroundColor: '#dc3545' },
        { label: 'Skipped', data: skipped, backgroundColor: '#ffc107', borderColor: '#e0a800' },
        { label: '% Completadas', data: pctCompleted, type: 'line', yAxisID: 'y2', borderColor: '#198754', tension: .2 },
        { label: '% No-Show', data: pctNoShow, type: 'line', yAxisID: 'y2', borderColor: '#dc3545', tension: .2 },
        { label: '% Skipped', data: pctSkipped, type: 'line', yAxisID: 'y2', borderColor: '#ffc107', tension: .2 }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      scales: {
        y: { stacked: true, beginAtZero: true },
        y2: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { callback: v => v + '%' } }
      },
      plugins: {
        tooltip: { callbacks: { label: ctx => ctx.dataset.type === 'line' ? ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(1) + '%' : ctx.dataset.label + ': ' + ctx.parsed.y } },
        legend: { labels: { usePointStyle: true } }
      }
    }
  });

  new Chart(qualityEl, {
    type: 'line',
    data: { labels, datasets: [
      { label: 'Bitrate kbps', data: bitrate, borderColor: '#0d6efd', tension: .25 },
      { label: 'Pérdida %', data: loss, borderColor: '#dc3545', tension: .25 },
      { label: 'RTT ms', data: rtt, borderColor: '#6f42c1', tension: .25 }
    ]},
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      scales: { y: { beginAtZero: true } },
      plugins: { tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(1) } } }
    }
  });

  new Chart(networkEl, {
    type: 'line',
    data: { labels, datasets: [
      { label: 'Retries promedio', data: retries, borderColor: '#fd7e14', tension: .25 },
      { label: 'Secuencias degradadas totales (acumuladas)', data: data.map(d => d.degraded_sequences_total), borderColor: '#20c997', tension: .25 }
    ]},
    options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, scales: { y: { beginAtZero: true } } }
  });

  const last = data[data.length - 1] || {};
  const statusSummary = document.getElementById('chart-status-summary');
  const qualitySummary = document.getElementById('chart-quality-summary');
  const networkSummary = document.getElementById('chart-network-summary');
  if (statusSummary) statusSummary.textContent = 'Último día: ' + last.day + ' - Total: ' + (last.total || 0) + ', Completadas: ' + (last.completed || 0) + ' (' + (last.total ? (last.completed / last.total * 100).toFixed(1) : '0.0') + '%)';
  if (qualitySummary) qualitySummary.textContent = 'Bitrate medio: ' + fmt.kbps(last.avg_bitrate_kbps) + ' | Pérdida: ' + fmt.pct(last.avg_loss_pct) + ' | RTT: ' + fmt.ms(last.avg_rtt_ms);
  if (networkSummary) networkSummary.textContent = 'Retries promedio: ' + fmt.num(last.avg_retries) + ' | Degradadas totales: ' + fmt.num(last.degraded_sequences_total);
}

export function init() {
  fetchMetrics().then(buildCharts);
}

export function destroy() {
  // Si se agregan referencias a instancias Chart, aquí se limpiarían.
}
