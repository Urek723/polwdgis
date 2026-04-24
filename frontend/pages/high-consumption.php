<?php
$pageTitle = 'High Consumption Consumers';
require_once 'layout.php';

// Run check on page load (non-fatal)
require_once __DIR__ . '/../../backend/consumption/check_high_consumption.php';
try {
    $db     = getDB();
    $result = checkHighConsumption($db);
} catch (Throwable $e) {
    error_log('[HighConsumption] Page check failed: ' . $e->getMessage());
    $result = ['checked' => 0, 'notified' => 0];
}
?>
<style>
.hc-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px; margin-bottom:20px; }
.exceeded-row { background:rgba(255,77,109,.06); border-left:3px solid var(--danger) !important; }
.normal-row   { background:rgba(0,200,150,.04); border-left:3px solid var(--accent3) !important; }
.cons-bar     { height:5px; background:var(--border); border-radius:3px; margin-top:5px; overflow:hidden; }
.cons-fill    { height:100%; border-radius:3px; transition:width .4s; }
</style>

<main class="main">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px">
  <div>
    <h1 style="font-size:20px;font-weight:700;margin-bottom:2px">High Consumption Consumers</h1>
    <p style="font-size:13px;color:var(--muted)">Consumers who exceeded 10 m³ water consumption</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if(in_array($_SESSION['role'],['Admin','Staff'])): ?>
    <button onclick="runCheck()" class="btn-primary" id="btnCheck">⚡ Re-run Detection</button>
    <?php endif; ?>
    <button onclick="exportTable()" class="btn-secondary">⬇ Export CSV</button>
  </div>
</div>

<?php if ($result['notified'] > 0): ?>
<div style="background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--danger);margin-bottom:16px">
  ⚠️ <?= $result['notified'] ?> new high-consumption alert(s) detected and notifications sent.
</div>
<?php endif; ?>

<!-- Stats -->
<div class="hc-stats" id="hcStats"></div>

<!-- Filters -->
<div style="display:flex;gap:8px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
  <input id="fSearch" class="filter-input" placeholder="Search name, account, barangay…"
         oninput="filterTable()" style="flex:1;min-width:200px">
  <select id="fBarangay" class="filter-input" onchange="filterTable()">
    <option value="">All Barangays</option>
  </select>
  <select id="fStatus" class="filter-input" onchange="filterTable()">
    <option value="">All Status</option>
    <option value="Exceeded">Exceeded</option>
    <option value="Normal">Normal (shown for context)</option>
  </select>
  <select id="fMonth" class="filter-input" onchange="filterTable()">
    <option value="">All Months</option>
  </select>
</div>

<!-- Table -->
<div class="card" style="padding:0;overflow:hidden">
  <div class="table-wrap">
    <table id="hcTable">
      <thead>
        <tr>
          <th>Consumer Name</th>
          <th>Account No</th>
          <th>Barangay</th>
          <th>Latest Consumption (m³)</th>
          <th>Billing Month</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody id="hcBody">
        <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--muted)"><div class="spinner"></div></td></tr>
      </tbody>
    </table>
  </div>
</div>

<div id="hcEmpty" style="display:none;text-align:center;padding:40px 0;color:var(--muted);font-size:14px">
  ✅ No consumers exceeding 10 m³ found with current filters.
</div>

</main>

<script>
let allRows = [];

async function loadData() {
  const r = await apiGet('consumer.php', { action: 'consumption_alerts' });
  // consumption_alerts returns is_alert=1 records (threshold-based)
  // We also fetch all >10m³ directly
  const rAll = await apiGet('../../backend/api/high_consumption_api.php', { action: 'get_all' });
  const data = rAll?.data || r?.data || [];
  allRows = data;
  populateFilters(data);
  renderStats(data);
  filterTable();
}

// Fallback: use existing consumption_alerts endpoint + manual filter
async function loadDataFallback() {
  // Get all consumption records with consumption_m3 > 10 via consumers endpoint
  const r = await fetch('../../backend/api/high_consumption_api.php?action=get_all', {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  });
  if (!r.ok) {
    // Use existing endpoint as fallback
    const r2 = await apiGet('consumer.php', { action: 'consumption_alerts' });
    allRows = r2?.data || [];
  } else {
    const j = await r.json();
    allRows = j?.data || [];
  }
  populateFilters(allRows);
  renderStats(allRows);
  filterTable();
}

function populateFilters(data) {
  const barangays = [...new Set(data.map(r => r.barangay).filter(Boolean))].sort();
  const months    = [...new Set(data.map(r => (r.billing_month||'').substring(0,7)))].filter(Boolean).sort().reverse();

  const bSel = document.getElementById('fBarangay');
  bSel.innerHTML = '<option value="">All Barangays</option>' +
    barangays.map(b => `<option value="${b}">${b}</option>`).join('');

  const mSel = document.getElementById('fMonth');
  mSel.innerHTML = '<option value="">All Months</option>' +
    months.map(m => `<option value="${m}">${m}</option>`).join('');
}

function renderStats(data) {
  const exceeded = data.filter(r => parseFloat(r.consumption_m3) > 10);
  const maxCons  = Math.max(...data.map(r => parseFloat(r.consumption_m3) || 0), 1);
  const avgCons  = data.length ? (data.reduce((s,r) => s + (parseFloat(r.consumption_m3)||0), 0) / data.length).toFixed(2) : 0;

  document.getElementById('hcStats').innerHTML = `
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px;text-align:center">
      <div style="font-size:28px;font-weight:700;color:var(--danger)">${exceeded.length}</div>
      <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em">Exceeded</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px;text-align:center">
      <div style="font-size:28px;font-weight:700;color:var(--accent)">${data.length}</div>
      <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em">Total Records</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px;text-align:center">
      <div style="font-size:28px;font-weight:700;color:var(--warn)">${maxCons.toFixed(1)}</div>
      <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em">Highest m³</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px;text-align:center">
      <div style="font-size:28px;font-weight:700;color:var(--accent3)">${avgCons}</div>
      <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em">Avg m³</div>
    </div>`;
}

function filterTable() {
  const q        = document.getElementById('fSearch').value.toLowerCase();
  const barangay = document.getElementById('fBarangay').value;
  const status   = document.getElementById('fStatus').value;
  const month    = document.getElementById('fMonth').value;

  let filtered = allRows.filter(r => {
    const cons = parseFloat(r.consumption_m3) || 0;
    const isExceeded = cons > 10;

    if (status === 'Exceeded' && !isExceeded) return false;
    if (status === 'Normal'   &&  isExceeded) return false;
    if (barangay && (r.barangay||'') !== barangay) return false;
    if (month    && !(r.billing_month||'').startsWith(month)) return false;
    if (q && ![(r.consumer_name||r.name||''),(r.account_no||r.account_id||''),(r.barangay||'')].join(' ').toLowerCase().includes(q)) return false;
    return true;
  });

  const maxCons = Math.max(...filtered.map(r => parseFloat(r.consumption_m3)||0), 1);
  const tbody   = document.getElementById('hcBody');
  const empty   = document.getElementById('hcEmpty');

  if (!filtered.length) {
    tbody.innerHTML = '';
    empty.style.display = 'block';
    return;
  }
  empty.style.display = 'none';

  tbody.innerHTML = filtered.map(r => {
    const cons       = parseFloat(r.consumption_m3) || 0;
    const isExceeded = cons > 10;
    const pct        = Math.min(100, Math.round(cons / maxCons * 100));
    const barColor   = isExceeded ? 'var(--danger)' : 'var(--accent3)';
    const statusBadge = isExceeded
      ? '<span class="badge badge-critical">Exceeded</span>'
      : '<span class="badge badge-active">Normal</span>';
    const month = (r.billing_month||'').substring(0,7);
    const name  = r.consumer_name || r.name || '—';
    const acct  = r.account_no || r.account_id || '—';

    return `<tr class="${isExceeded ? 'exceeded-row' : 'normal-row'}">
      <td style="font-weight:600">${name}</td>
      <td style="font-size:12px;font-family:'Space Mono',monospace">${acct}</td>
      <td style="color:var(--text2)">${r.barangay||'—'}</td>
      <td>
        <div style="font-weight:700;color:${isExceeded?'var(--danger)':'var(--accent3)'}">${cons.toFixed(2)} m³</div>
        <div class="cons-bar"><div class="cons-fill" style="width:${pct}%;background:${barColor}"></div></div>
      </td>
      <td style="font-size:12px;color:var(--muted)">${month}</td>
      <td>${statusBadge}</td>
    </tr>`;
  }).join('');
}

async function runCheck() {
  const btn = document.getElementById('btnCheck');
  btn.disabled = true; btn.textContent = 'Running…';
  try {
    const r = await fetch('../../backend/consumption/check_high_consumption.php', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    showToast('Detection complete. Reloading data…', 'success');
    await loadData();
  } catch (e) {
    showToast('Detection failed: ' + e.message, 'error');
  }
  btn.disabled = false; btn.textContent = '⚡ Re-run Detection';
}

function exportTable() {
  const rows  = [...document.querySelectorAll('#hcTable tbody tr')];
  const heads = [...document.querySelectorAll('#hcTable thead th')].map(th => th.textContent);
  let csv = heads.join(',') + '\n';
  rows.forEach(tr => {
    const cols = [...tr.querySelectorAll('td')].map(td => '"' + td.innerText.replace(/\n/g,' ').replace(/"/g,'""') + '"');
    csv += cols.join(',') + '\n';
  });
  const blob = new Blob([csv], { type: 'text/csv' });
  const a    = document.createElement('a');
  a.href     = URL.createObjectURL(blob);
  a.download = 'high_consumption_' + new Date().toISOString().substring(0,10) + '.csv';
  a.click();
  showToast('CSV exported', 'success');
}

document.addEventListener('DOMContentLoaded', loadData);
</script>