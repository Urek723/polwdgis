<?php
$pageTitle = 'Track Requests';
require_once 'layout.php';
?>
<div class="page-wrap">
  <h2 style="font-size:18px;font-weight:700;margin-bottom:4px">My Requests</h2>
  <p style="font-size:13px;color:var(--muted);margin-bottom:20px">Track the status of your submitted reports</p>

  <div class="card">
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
      <select id="filterStatus" class="form-input" style="width:auto;min-width:150px" onchange="load()">
        <option value="">All Statuses</option>
        <option value="Submitted">Submitted</option>
        <option value="Under Review">Under Review</option>
        <option value="In Progress">In Progress</option>
        <option value="Resolved">Resolved</option>
        <option value="Closed">Closed</option>
      </select>
    </div>
    <div id="requestList"><div class="spinner"></div></div>
  </div>

  <!-- Detail panel -->
  <div id="detailPanel" style="display:none" class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div style="font-weight:600;font-size:15px" id="detailTitle"></div>
      <button onclick="document.getElementById('detailPanel').style.display='none'" style="background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer">✕</button>
    </div>
    <div id="detailBody"></div>
    <div id="detailMap" style="height:250px;border-radius:8px;overflow:hidden;border:1px solid var(--border);margin-top:14px;display:none"></div>
  </div>
</div>

<script>
let detailMapInstance = null;

async function load() {
  const statusFilter = document.getElementById('filterStatus').value;
  const d   = await apiGet(CONSUMER_API, { action: 'get_my_requests' });
  let list  = d?.data || [];
  if (statusFilter) list = list.filter(r => r.status === statusFilter);
  const el  = document.getElementById('requestList');

  if (!list.length) {
    el.innerHTML = '<p style="color:var(--muted);font-size:13px;text-align:center;padding:20px 0">No requests found.</p>';
    return;
  }

  const statusKey = s => s.toLowerCase().replace(/\s+/g, '');
  const statusIcon = {
    'Submitted':    '🕐',
    'Under Review': '🔍',
    'In Progress':  '🔧',
    'Resolved':     '✅',
    'Closed':       '🗂️',
  };

  el.innerHTML = list.map(r => `
    <div onclick="openDetail(${JSON.stringify(r).replace(/"/g,'&quot;')})"
      style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:8px;cursor:pointer;transition:border-color .2s"
      onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
        <div style="font-weight:600">${statusIcon[r.status] || '•'} ${r.request_type}</div>
        <span class="badge badge-${statusKey(r.status)}">${r.status}</span>
      </div>
      <div style="font-size:12px;color:var(--text2);margin-bottom:4px">${r.subject || ''}</div>
      <div style="font-size:11px;color:var(--muted)">📅 ${r.created_at} ${r.location_text ? '· 📍 ' + r.location_text : ''}</div>
    </div>`).join('');
}

function openDetail(r) {
  const panel = document.getElementById('detailPanel');
  panel.style.display = 'block';
  document.getElementById('detailTitle').textContent = r.request_type + ' — #' + r.id;

  const statusKey = s => s.toLowerCase().replace(/\s+/g,'');

  document.getElementById('detailBody').innerHTML = `
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
      <span class="badge badge-${statusKey(r.status)}">${r.status}</span>
    </div>
    <div style="font-size:13px;color:var(--text2);line-height:1.6;margin-bottom:10px">${r.details || '—'}</div>
    <div style="font-size:12px;color:var(--muted);display:grid;gap:4px">
      <div>📅 Submitted: ${r.created_at}</div>
      ${r.location_text ? `<div>📍 ${r.location_text}</div>` : ''}
      ${r.latitude && r.longitude ? `<div>🗺️ ${parseFloat(r.latitude).toFixed(5)}, ${parseFloat(r.longitude).toFixed(5)}</div>` : ''}
      ${r.resolved_at ? `<div>✅ Resolved: ${r.resolved_at}</div>` : ''}
    </div>
    ${r.resolution_notes ? `<div style="margin-top:12px;background:rgba(0,200,150,.08);border:1px solid rgba(0,200,150,.2);border-radius:8px;padding:10px;font-size:13px"><b>Resolution:</b> ${r.resolution_notes}</div>` : ''}`;

  // Show map if coordinates available
  const mapEl = document.getElementById('detailMap');
  if (r.latitude && r.longitude) {
    mapEl.style.display = 'block';
    setTimeout(() => {
      if (detailMapInstance) {
        detailMapInstance.remove();
        detailMapInstance = null;
      }
      detailMapInstance = L.map('detailMap', { center: [parseFloat(r.latitude), parseFloat(r.longitude)], zoom: 16 });
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(detailMapInstance);
      L.marker([parseFloat(r.latitude), parseFloat(r.longitude)])
       .addTo(detailMapInstance)
       .bindPopup(r.request_type)
       .openPopup();
    }, 50);
  } else {
    mapEl.style.display = 'none';
  }

  panel.scrollIntoView({ behavior: 'smooth' });
}

load();
</script>