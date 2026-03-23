<?php
$pageTitle = 'Dashboard';
require_once 'layout.php';
?>
<style>
.dash-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 20px; }
.dash-grid2 { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 20px; }
.dash-grid3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; }
.stat-card { position: relative; overflow: hidden; }
.stat-card::after {
  content: '';
  position: absolute; top: -30px; right: -30px;
  width: 80px; height: 80px;
  border-radius: 50%;
  opacity: 0.06;
}
.sc-blue::after { background: var(--accent); }
.sc-green::after { background: var(--accent3); }
.sc-warn::after { background: var(--warn); }
.sc-red::after { background: var(--danger); }
.stat-icon { font-size: 28px; margin-bottom: 10px; }
@media (max-width: 1200px) { .dash-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 768px) { .dash-grid, .dash-grid2, .dash-grid3 { grid-template-columns: 1fr; } }
</style>

<main class="main">
<div class="dash-grid" id="statsGrid">
  <div class="stat-card card sc-blue">
    <div class="stat-label">Total Consumers</div>
    <div class="stat-value" id="totalConsumers">—</div>
    <div class="stat-sub">Active accounts</div>
  </div>
  <div class="stat-card card sc-green">
    <div class="stat-label">Active Meters</div>
    <div class="stat-value" id="activeMeters">—</div>
    <div class="stat-sub">Connected</div>
  </div>
  <div class="stat-card card sc-warn">
    <div class="stat-label">Pending Work Orders</div>
    <div class="stat-value" id="pendingWO">—</div>
    <div class="stat-sub">Open tickets</div>
  </div>
  <div class="stat-card card sc-red">
    <div class="stat-label">Active Alerts</div>
    <div class="stat-value" id="activeAlerts">—</div>
    <div class="stat-sub">Deterioration alerts</div>
  </div>
</div>

<div class="dash-grid2">
  <div class="card">
    <div class="card-title">Recent Work Orders</div>
    <div class="table-wrap" id="recentWOTable">
      <table>
        <thead><tr><th>Title</th><th>Type</th><th>Priority</th><th>Status</th><th>Date</th></tr></thead>
        <tbody id="recentWOBody"><tr><td colspan="5" style="text-align:center;color:var(--muted);">Loading...</td></tr></tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card-title">Deterioration Alerts</div>
    <div id="alertList" style="max-height:300px;overflow-y:auto;">
      <p style="color:var(--muted);font-size:13px;text-align:center;padding:20px;">Loading...</p>
    </div>
  </div>
</div>

<div class="dash-grid3">
  <div class="card">
    <div class="card-title">Upcoming Maintenance</div>
    <div id="schedList" style="max-height:240px;overflow-y:auto;">
      <p style="color:var(--muted);font-size:13px;text-align:center;padding:20px;">Loading...</p>
    </div>
  </div>
  <div class="card">
    <div class="card-title">Consumption Alerts</div>
    <div id="consAlertList" style="max-height:240px;overflow-y:auto;">
      <p style="color:var(--muted);font-size:13px;text-align:center;padding:20px;">Loading...</p>
    </div>
  </div>
  <div class="card">
    <div class="card-title">Active Interruptions</div>
    <div id="intrList" style="max-height:240px;overflow-y:auto;">
      <p style="color:var(--muted);font-size:13px;text-align:center;padding:20px;">Loading...</p>
    </div>
  </div>
</div>
</main>

<script>
const priorityColor = { Critical: 'var(--danger)', High: '#ff8c00', Medium: 'var(--accent)', Low: 'var(--text2)' };

async function loadDashboard() {
  // Stats
  try {
    const [consumersR, woR, alertsR] = await Promise.all([
      apiGet('consumer.php', { action: 'get_consumers', status: 'Active' }),
      apiGet('maintenance.php', { action: 'get_work_orders', status: 'Pending' }),
      apiGet('maintenance.php', { action: 'get_alerts' }),
    ]);
    document.getElementById('totalConsumers').textContent = (consumersR.total || 0).toLocaleString();
    document.getElementById('activeMeters').textContent   = (consumersR.total || 0).toLocaleString();
    document.getElementById('pendingWO').textContent      = (woR.data?.length || 0);
    document.getElementById('activeAlerts').textContent   = (alertsR.data?.length || 0);
  } catch {}

  // Work Orders
  try {
    const r = await apiGet('maintenance.php', { action: 'get_work_orders' });
    const rows = (r.data || []).slice(0, 8);
    document.getElementById('recentWOBody').innerHTML = rows.length
      ? rows.map(wo => `
          <tr>
            <td><a href="work-orders.php?id=${wo.id}" style="color:var(--accent);text-decoration:none;">${wo.title}</a></td>
            <td>${wo.type}</td>
            <td style="color:${priorityColor[wo.priority]}">${wo.priority}</td>
            <td><span class="badge badge-${wo.status?.toLowerCase().replace(' ','-')}">${wo.status}</span></td>
            <td style="font-size:11px;">${new Date(wo.created_at).toLocaleDateString()}</td>
          </tr>`).join('')
      : '<tr><td colspan="5" style="text-align:center;color:var(--muted);">No work orders</td></tr>';
  } catch {}

  // Deterioration Alerts
  try {
    const r = await apiGet('maintenance.php', { action: 'get_alerts' });
    document.getElementById('alertList').innerHTML = (r.data||[]).slice(0,6).map(a => `
      <div style="padding:10px;border-bottom:1px solid var(--border);font-size:12px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
          <span class="badge badge-${a.severity?.toLowerCase()}">${a.severity}</span>
          <span style="font-weight:600;color:var(--text);">${a.alert_type}</span>
        </div>
        <div style="color:var(--text2);">${a.description}</div>
      </div>`).join('') || '<p style="text-align:center;padding:20px;color:var(--muted);">No active alerts</p>';
  } catch {}

  // Schedules
  try {
    const r = await apiGet('maintenance.php', { action: 'get_schedules' });
    document.getElementById('schedList').innerHTML = (r.data||[]).slice(0,5).map(s => `
      <div style="padding:10px;border-bottom:1px solid var(--border);font-size:12px;">
        <div style="font-weight:600;color:var(--text);">${s.title}</div>
        <div style="color:var(--muted);">Due: ${s.next_due} • ${s.frequency}</div>
      </div>`).join('') || '<p style="text-align:center;padding:20px;color:var(--muted);">No schedules</p>';
  } catch {}

  // Consumption alerts
  try {
    const r = await apiGet('consumer.php', { action: 'consumption_alerts' });
    document.getElementById('consAlertList').innerHTML = (r.data||[]).slice(0,5).map(a => `
      <div style="padding:10px;border-bottom:1px solid var(--border);font-size:12px;">
        <div style="font-weight:600;color:var(--text);">${a.consumer_name}</div>
        <div style="color:var(--danger);">${parseFloat(a.consumption_m3).toFixed(1)} m³ — exceeds threshold</div>
        <div style="color:var(--muted);">${a.barangay} • ${a.billing_month}</div>
      </div>`).join('') || '<p style="text-align:center;padding:20px;color:var(--muted);">No alerts</p>';
  } catch {}

  // Interruptions
  try {
    const r = await apiGet('consumer.php', { action: 'get_interruptions' });
    const active = (r.data||[]).filter(i => i.status !== 'Resolved').slice(0,5);
    document.getElementById('intrList').innerHTML = active.map(i => `
      <div style="padding:10px;border-bottom:1px solid var(--border);font-size:12px;">
        <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;">
          <span class="badge badge-${i.status?.toLowerCase()}">${i.status}</span>
          <span style="font-weight:600;color:var(--text);">${i.title}</span>
        </div>
        <div style="color:var(--text2);">${i.affected_barangays}</div>
      </div>`).join('') || '<p style="text-align:center;padding:20px;color:var(--muted);">No active interruptions</p>';
  } catch {}
}

loadDashboard();
</script>
