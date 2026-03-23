<?php
$pageTitle = 'Consumer Details';
require_once 'layout.php';

$account_id = $_GET['account_id'] ?? '';
if (!$account_id) {
    header('Location: consumers.php');
    exit;
}
?>
<style>
.info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px; }
.info-item { background: var(--surface2); border: 1px solid var(--border); border-radius: 10px; padding: 14px; }
.info-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; margin-bottom: 4px; }
.info-value { font-size: 14px; font-weight: 600; color: var(--text); }
.chart-container { position: relative; height: 260px; }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>

<main class="main">

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
  <a href="consumers.php" class="btn-secondary" style="font-size:13px">← Back</a>
  <h2 id="conName" style="font-size:20px;font-weight:700">Loading…</h2>
  <span id="conStatus"></span>
</div>

<div id="spinner" style="text-align:center;padding:40px"><div class="spinner"></div></div>

<div id="content" style="display:none">

  <!-- Info grid -->
  <div class="info-grid" id="infoGrid"></div>

  <!-- Consumption chart -->
  <div class="card" style="margin-bottom:16px">
    <div class="card-title">Monthly Consumption (m³)</div>
    <div style="margin-bottom:10px;font-size:13px;color:var(--muted)">
      Average: <span id="avgConsumption" style="color:var(--accent);font-weight:600">—</span> m³/month
    </div>
    <div class="chart-container">
      <canvas id="consChart"></canvas>
    </div>
  </div>

  <!-- Service history -->
  <div class="card" style="margin-bottom:16px">
    <div class="card-title">Service Requests</div>
    <div id="serviceHistory"><p style="color:var(--muted);font-size:13px">No service history.</p></div>
  </div>

</div>

</main>

<script>
const CONSUMER_ACCOUNT_ID = <?= json_encode($account_id) ?>;

async function loadConsumer() {
  const d = await apiGet('consumer.php', { action: 'get_consumer', account_id: CONSUMER_ACCOUNT_ID });
  const c = d?.data;

  if (!c) {
    document.getElementById('spinner').innerHTML = '<p style="color:var(--danger);text-align:center">Consumer not found.</p>';
    return;
  }

  document.getElementById('spinner').style.display = 'none';
  document.getElementById('content').style.display = 'block';
  document.getElementById('conName').textContent = c.name;
  document.getElementById('conStatus').innerHTML =
    `<span class="badge badge-${(c.status || '').toLowerCase()}">${c.status}</span>`;

  // Info grid
  const fields = [
    ['Account No',   c.account_no || c.account_id],
    ['Type',         c.type],
    ['Status',       c.status],
    ['Barangay',     c.barangay || '—'],
    ['Municipal',    c.municipal || '—'],
    ['Zone',         c.zone || '—'],
    ['Contact',      c.contact_no || '—'],
    ['Email',        c.email || '—'],
    ['Meter Brand',  c.meter_brand || '—'],
    ['Meter No',     c.meter_number || '—'],
    ['Address',      c.address || '—'],
    ['Senior',       c.is_senior ? 'Yes ⭐' : 'No'],
  ];

  document.getElementById('infoGrid').innerHTML = fields.map(([label, val]) => `
    <div class="info-item">
      <div class="info-label">${label}</div>
      <div class="info-value">${val}</div>
    </div>`).join('');

  // Coordinates card
  if (c.latitude && c.longitude) {
    document.getElementById('infoGrid').insertAdjacentHTML('afterend', `
      <div class="card" style="margin-bottom:16px;font-size:13px">
        <div class="card-title">📍 Coordinates</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;color:var(--text2)">
          <div>WGS84: <b style="color:var(--text)">${parseFloat(c.latitude).toFixed(6)}, ${parseFloat(c.longitude).toFixed(6)}</b></div>
          ${c.x_utm ? `<div>UTM X: <b style="color:var(--text)">${c.x_utm}</b></div>` : ''}
          ${c.y_utm ? `<div>UTM Y: <b style="color:var(--text)">${c.y_utm}</b></div>` : ''}
          ${c.elevation ? `<div>Elevation: <b style="color:var(--text)">${c.elevation} m</b></div>` : ''}
        </div>
      </div>`);
  }

  // Chart
  const hist = c.consumption_history || [];
  if (hist.length) {
    const reversed = [...hist].reverse();
    const labels   = reversed.map(h => (h.billing_month || '').substring(0, 7));
    const values   = reversed.map(h => parseFloat(h.consumption_m3) || 0);
    const avg      = values.reduce((a, b) => a + b, 0) / values.length;

    document.getElementById('avgConsumption').textContent = avg.toFixed(2);

    new Chart(document.getElementById('consChart'), {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Consumption (m³)',
          data: values,
          backgroundColor: values.map(v => v > avg * 1.5 ? 'rgba(255,77,109,0.7)' : 'rgba(0,87,255,0.7)'),
          borderColor:     values.map(v => v > avg * 1.5 ? '#ff4d6d' : '#0057ff'),
          borderWidth: 1,
          borderRadius: 4,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => ` ${ctx.parsed.y} m³`
            }
          }
        },
        scales: {
          x: { ticks: { color: '#94a3b8', font: { size: 11 } }, grid: { color: '#1e2d40' } },
          y: { ticks: { color: '#94a3b8' }, grid: { color: '#1e2d40' }, beginAtZero: true }
        }
      }
    });
  } else {
    document.getElementById('consChart').parentElement.innerHTML =
      '<p style="color:var(--muted);font-size:13px;padding:20px 0">No consumption data available.</p>';
  }

  // Load service history using numeric id
  loadServiceHistory(c.id);
}

async function loadServiceHistory(consumerId) {
  const d    = await apiGet('consumer.php', { action: 'get_requests', consumer_id: consumerId });
  const reqs = d?.data || [];
  const el   = document.getElementById('serviceHistory');

  if (!reqs.length) return;

  el.innerHTML = `
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Type</th>
            <th>Subject</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          ${reqs.map(r => `
            <tr>
              <td>${r.request_type}</td>
              <td>${r.subject || '—'}</td>
              <td><span class="badge badge-${(r.status || '').toLowerCase().replace(/\s+/g, '-')}">${r.status}</span></td>
              <td style="font-size:12px;color:var(--muted)">${r.created_at || '—'}</td>
            </tr>`).join('')}
        </tbody>
      </table>
    </div>`;
}

loadConsumer();
</script>