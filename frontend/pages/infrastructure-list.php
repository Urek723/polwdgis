<?php
$pageTitle = 'Infrastructure';
require_once 'layout.php';
?>
<style>
.infra-card {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 16px;
  margin-bottom: 8px;
  display: grid;
  grid-template-columns: 56px 1fr auto;
  gap: 14px;
  align-items: center;
  cursor: pointer;
  text-decoration: none;
  color: inherit;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.infra-card:hover {
  border-color: var(--accent);
  box-shadow: 0 0 0 1px rgba(0,212,255,0.1);
}
.infra-icon {
  width: 52px; height: 52px;
  border-radius: 12px;
  background: rgba(0,87,255,0.12);
  border: 1px solid rgba(0,87,255,0.2);
  display: flex; align-items: center; justify-content: center;
  font-size: 24px;
  flex-shrink: 0;
}
.infra-name   { font-size: 14px; font-weight: 600; margin-bottom: 3px; }
.infra-meta   { font-size: 12px; color: var(--muted); }
.infra-coords { font-size: 10px; font-family: 'Space Mono', monospace; color: var(--muted); margin-top: 2px; }

/* Summary stat cards */
.stat-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
  gap: 10px;
  margin-bottom: 18px;
}
</style>

<main class="main">

<div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;flex-wrap:wrap">
  <?php if (in_array($_SESSION['role'], ['Admin', 'Staff'])): ?>
  <a href="infrastructure-add.php" class="btn-primary" style="font-size:13px;text-decoration:none;padding:8px 16px;border-radius:8px">+ Add Infrastructure</a>
  <?php endif; ?>
  <input id="fq" placeholder="Search name, barangay…" oninput="load()" class="filter-input" style="flex:1;min-width:180px">
  <select id="ftype" onchange="load()" class="filter-input">
    <option value="">All Types</option>
    <option value="pumping_station">Pumping Station</option>
    <option value="reservoir">Reservoir</option>
    <option value="valve">Valve</option>
    <option value="hydrant">Hydrant</option>
    <option value="blowoff">Blowoff</option>
    <option value="meter_chamber">Meter Chamber</option>
    <option value="other">Other</option>
  </select>
  <select id="fstatus" onchange="load()" class="filter-input">
    <option value="">All Statuses</option>
    <option value="active">Active</option>
    <option value="inactive">Inactive</option>
    <option value="maintenance">Maintenance</option>
  </select>
</div>

<!-- Stats -->
<div class="stat-row" id="statRow"></div>

<!-- List -->
<div id="infraList"><div class="spinner"></div></div>

</main>

<script>
const EMOJIS = {
  pumping_station: '🏗️', reservoir: '🗄️', valve: '🔧',
  hydrant: '🚒', blowoff: '💨', meter_chamber: '📊', other: '📌',
};
const TYPE_LABELS = {
  pumping_station: 'Pumping Station', reservoir: 'Reservoir', valve: 'Valve',
  hydrant: 'Hydrant', blowoff: 'Blowoff', meter_chamber: 'Meter Chamber', other: 'Other',
};
const STATUS_COLORS = { active: 'var(--accent3)', inactive: 'var(--muted)', maintenance: 'var(--warn)' };

async function load() {
  const q      = document.getElementById('fq').value.toLowerCase();
  const type   = document.getElementById('ftype').value;
  const status = document.getElementById('fstatus').value;

  const d    = await apiGet('gis.php', { action: 'get_infrastructure', type, status });
  let items  = d?.data || [];

  if (q) items = items.filter(i =>
    (i.name || '').toLowerCase().includes(q) ||
    (i.barangay || '').toLowerCase().includes(q) ||
    (i.address || '').toLowerCase().includes(q)
  );

  renderStats(items);

  const el = document.getElementById('infraList');
  if (!items.length) {
    el.innerHTML = '<p style="color:var(--muted);font-size:13px;text-align:center;padding:30px 0">No infrastructure found.</p>';
    return;
  }

  el.innerHTML = items.map(i => `
    <a href="infrastructure-detail.php?id=${i.id}" class="infra-card">
      <div class="infra-icon">${EMOJIS[i.type] || '📌'}</div>
      <div>
        <div class="infra-name">${i.name || TYPE_LABELS[i.type] || 'Infrastructure'}</div>
        <div class="infra-meta">
          ${TYPE_LABELS[i.type] || i.type}
          ${i.barangay ? ' · ' + i.barangay : ''}
          ${i.installation_date ? ' · Installed ' + i.installation_date : ''}
        </div>
        ${(i.latitude && i.longitude)
          ? `<div class="infra-coords">📍 ${parseFloat(i.latitude).toFixed(5)}, ${parseFloat(i.longitude).toFixed(5)}</div>`
          : ''}
      </div>
      <div style="text-align:right;flex-shrink:0">
        <span class="badge badge-${i.status || 'active'}" style="display:inline-block;margin-bottom:4px">
          ${ucfirst(i.status || 'active')}
        </span>
        <div style="font-size:11px;color:var(--muted);margin-top:2px">
          ${i.last_inspection ? '🔍 ' + i.last_inspection : 'No inspection record'}
        </div>
      </div>
    </a>`).join('');
}

function renderStats(items) {
  const counts = { active: 0, inactive: 0, maintenance: 0 };
  items.forEach(i => counts[i.status] = (counts[i.status] || 0) + 1);
  document.getElementById('statRow').innerHTML = `
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:24px;font-weight:700;color:var(--accent)">${items.length}</div>
      <div style="font-size:11px;color:var(--muted)">Total</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:24px;font-weight:700;color:var(--accent3)">${counts.active || 0}</div>
      <div style="font-size:11px;color:var(--muted)">Active</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:24px;font-weight:700;color:var(--warn)">${counts.maintenance || 0}</div>
      <div style="font-size:11px;color:var(--muted)">In Maintenance</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:24px;font-weight:700;color:var(--muted)">${counts.inactive || 0}</div>
      <div style="font-size:11px;color:var(--muted)">Inactive</div>
    </div>`;
}

function ucfirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

document.addEventListener('DOMContentLoaded', load);
</script>