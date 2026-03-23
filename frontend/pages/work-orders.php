<?php
$pageTitle = 'Work Orders';
require_once 'layout.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.heat/0.2.0/leaflet-heat.js"></script>
<style>
.wo-card{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:10px;cursor:pointer;transition:border-color .2s,box-shadow .2s}
.wo-card:hover{border-color:var(--accent);box-shadow:0 0 0 1px rgba(0,212,255,.15)}
.p-Low{background:#16a34a22;color:#16a34a}.p-Medium{background:#ca8a0422;color:#ca8a04}
.p-High{background:#ea580c22;color:#ea580c}.p-Critical{background:#dc262622;color:#dc2626}
.s-Pending{background:#71717a22;color:#71717a}.s-InProgress{background:#0ea5e922;color:#0ea5e9}
.s-Completed{background:#16a34a22;color:#16a34a}.s-Cancelled{background:#dc262622;color:#dc2626}
.badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}
.check-row{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid var(--border);font-size:13px}
.upd-row{padding:8px 0;border-bottom:1px solid var(--border);font-size:13px}

.filter-toggle-group{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.ftog{padding:5px 12px;border-radius:20px;border:1px solid var(--border);background:var(--surface2);color:var(--text2);font-size:12px;font-weight:500;cursor:pointer;transition:all .15s;font-family:'Sora',sans-serif}
.ftog:hover{border-color:var(--accent);color:var(--accent)}
.ftog.active{border-color:var(--accent);background:rgba(0,212,255,.12);color:var(--accent)}
.ftog.s-Pending.active{border-color:#71717a;background:#71717a22;color:#71717a}
.ftog.s-InProgress.active{border-color:#0ea5e9;background:#0ea5e922;color:#0ea5e9}
.ftog.s-Completed.active{border-color:#16a34a;background:#16a34a22;color:#16a34a}
.ftog.s-Cancelled.active{border-color:#dc2626;background:#dc262622;color:#dc2626}
.ftog.p-Critical.active{border-color:#dc2626;background:#dc262622;color:#dc2626}
.ftog.p-High.active{border-color:#ea580c;background:#ea580c22;color:#ea580c}
.ftog.p-Medium.active{border-color:#ca8a04;background:#ca8a0422;color:#ca8a04}
.ftog.p-Low.active{border-color:#16a34a;background:#16a34a22;color:#16a34a}

/* FIX: explicit dimensions so Leaflet can measure the container */
#wo-map {
  height: 360px;
  width: 100%;
  display: block;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--border);
  margin-bottom: 16px;
  /* FIX: prevent the parent stacking context from clipping tiles */
  position: relative;
  z-index: 0;
}

/* FIX: keep Leaflet internals below modals but above normal content */
#wo-map .leaflet-pane,
#wo-map .leaflet-tile-pane,
#wo-map .leaflet-overlay-pane,
#wo-map .leaflet-shadow-pane,
#wo-map .leaflet-marker-pane,
#wo-map .leaflet-popup-pane {
  z-index: 200;
}
#wo-map .leaflet-top,
#wo-map .leaflet-bottom {
  z-index: 300;
}

/* FIX: pin map inside modal */
#pin-map {
  height: 240px;
  width: 100%;
  display: block;
  border-radius: 9px;
  overflow: hidden;
  border: 1px solid var(--border);
  position: relative;
  z-index: 0;
}

#map-controls{display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap}
</style>

<main class="main">
<div id="mDoc" class="modal-overlay">
  <div class="modal-box" style="max-width:500px">
    <div class="modal-header">
      <h3>📄 Generate Work Order Document</h3>
      <button onclick="closeModal('mDoc')">✕</button>
    </div>
    <div style="padding:18px;display:flex;flex-direction:column;gap:12px">

      <div style="background:rgba(0,87,255,.08);border:1px solid rgba(0,87,255,.2);border-radius:8px;padding:10px 12px;font-size:13px;color:#94a3b8">
        📋 Generating document for: <strong id="docWoTitle" style="color:#e2eaf4"></strong>
        <span style="float:right;font-size:11px;color:#4a5a72" id="docWoId"></span>
      </div>

      <div>
        <label style="font-size:12px;color:#4a5a72;display:block;margin-bottom:5px">
          Assigned Staff / Team Members
          <span style="color:#4a5a72;font-weight:400">(comma-separated)</span>
        </label>
        <input id="docStaff" class="form-input"
               placeholder="e.g. Juan Dela Cruz, Maria Santos, Pedro Reyes">
      </div>

      <div>
        <label style="font-size:12px;color:#4a5a72;display:block;margin-bottom:5px">Team Leader / Supervisor</label>
        <input id="docLeader" class="form-input" placeholder="e.g. Engr. Roberto Cruz">
      </div>

      <div>
        <label style="font-size:12px;color:#4a5a72;display:block;margin-bottom:5px">Prepared By</label>
        <input id="docPreparedBy" class="form-input"
               value="<?= htmlspecialchars($_SESSION['name'] ?? '') ?>">
      </div>

      <div>
        <label style="font-size:12px;color:#4a5a72;display:block;margin-bottom:5px">Approved By <span style="font-weight:400">(optional)</span></label>
        <input id="docApprovedBy" class="form-input" placeholder="e.g. Engr. Anna Reyes">
      </div>

      <div>
        <label style="font-size:12px;color:#4a5a72;display:block;margin-bottom:5px">Special Instructions / Remarks <span style="font-weight:400">(optional)</span></label>
        <textarea id="docInstructions" class="form-input" rows="3"
                  placeholder="Enter any special instructions, safety reminders, or field notes…"></textarea>
      </div>

      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:4px">
        <button onclick="closeModal('mDoc')" class="btn-secondary">Cancel</button>
        <button onclick="openDocumentPage()" class="btn-primary">📄 Generate &amp; Preview</button>
      </div>
    </div>
  </div>
</div>

<!-- Search + New Order bar -->
<div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:12px 16px;margin-bottom:10px;display:flex;gap:8px;align-items:center">
  <input id="fq" placeholder="Search work orders…" oninput="applyFilters()" class="filter-input" style="flex:1;max-width:320px">
  <?php if(in_array($_SESSION['role'],['Admin','Staff'])): ?>
  <button class="btn-primary" onclick="openWOModal()" style="white-space:nowrap">+ New Work Order</button>
  <?php endif; ?>
</div>

<!-- Filter toggles -->
<div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:14px">
  <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start">
    <div>
      <div style="font-size:11px;color:var(--muted);margin-bottom:7px;text-transform:uppercase;letter-spacing:.08em">Status</div>
      <div class="filter-toggle-group" id="statusToggles">
        <button class="ftog s-Pending"    onclick="toggleFilter('status','Pending')">Pending</button>
        <button class="ftog s-InProgress" onclick="toggleFilter('status','In Progress')">In Progress</button>
        <button class="ftog s-Completed"  onclick="toggleFilter('status','Completed')">Completed</button>
        <button class="ftog s-Cancelled"  onclick="toggleFilter('status','Cancelled')">Cancelled</button>
      </div>
    </div>
    <div>
      <div style="font-size:11px;color:var(--muted);margin-bottom:7px;text-transform:uppercase;letter-spacing:.08em">Priority</div>
      <div class="filter-toggle-group" id="priorityToggles">
        <button class="ftog p-Critical" onclick="toggleFilter('priority','Critical')">Critical</button>
        <button class="ftog p-High"     onclick="toggleFilter('priority','High')">High</button>
        <button class="ftog p-Medium"   onclick="toggleFilter('priority','Medium')">Medium</button>
        <button class="ftog p-Low"      onclick="toggleFilter('priority','Low')">Low</button>
      </div>
    </div>
    <div>
      <div style="font-size:11px;color:var(--muted);margin-bottom:7px;text-transform:uppercase;letter-spacing:.08em">Date Range</div>
      <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
        <input type="date" id="fDateFrom" class="filter-input" style="width:140px" oninput="applyFilters()" title="From date">
        <span style="font-size:12px;color:var(--muted)">to</span>
        <input type="date" id="fDateTo" class="filter-input" style="width:140px" oninput="applyFilters()" title="To date">
        <button onclick="clearDateFilter()" class="ftog" id="btnClearDate" style="display:none">✕ Clear</button>
      </div>
    </div>
  </div>
</div>

<!-- Map section -->
<div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px">
  <div id="map-controls">
    <span style="font-size:13px;font-weight:600">🗺 Work Orders Map</span>
    <button class="ftog" id="btnHeatmap" onclick="toggleHeatmap()">🌡 Heatmap</button>
    <span id="map-count" style="font-size:12px;color:var(--muted);margin-left:auto"></span>
  </div>
  <!-- FIX: map div must exist in DOM at page load, NOT inside a modal or hidden container -->
  <div id="wo-map"></div>
</div>

<!-- Stats bar -->
<div id="dtBar" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:16px"></div>

<!-- Work orders list -->
<div id="woList"><div class="spinner"></div></div>

<!-- Side panel -->
<div id="panel" style="display:none;position:fixed;top:0;right:0;width:440px;height:100vh;background:var(--bg);border-left:1px solid var(--border);overflow-y:auto;z-index:300;padding:20px;box-shadow:-6px 0 24px rgba(0,0,0,.3)">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h3 id="ptitle" style="font-size:16px;font-weight:600"></h3>
    <button onclick="document.getElementById('panel').style.display='none'" style="background:none;border:none;color:var(--text);font-size:22px;cursor:pointer">✕</button>
  </div>
  <div id="pbody"></div>
</div>

<!-- New Work Order Modal -->
<div id="mwo" class="modal-overlay">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-header">
      <h3>New Work Order</h3>
      <button onclick="closeModal('mwo')">✕</button>
    </div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
      <input id="wt" placeholder="Title *" class="form-input">
      <textarea id="wd" placeholder="Description" class="form-input" rows="2"></textarea>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <select id="wtype" class="form-input">
          <option>Mainline</option><option>Serviceline</option><option>Pump</option>
          <option>Valve</option><option>Reservoir</option><option>Electrical</option><option>Other</option>
        </select>
        <select id="wpri" class="form-input">
          <option>Medium</option><option>Low</option><option>High</option><option>Critical</option>
        </select>
      </div>
      <input id="wloc" placeholder="Location description" class="form-input">
      <input id="wcause" placeholder="Cause (optional)" class="form-input">
      <input id="wsched" type="date" class="form-input">

      <div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:6px">
          📍 Click map to pin location <span style="color:var(--muted)">(optional)</span>
        </div>
        <!-- FIX: pin-map is always in DOM; shown/hidden via the modal overlay -->
        <div id="pin-map"></div>
        <div id="pin-coords" style="font-size:12px;color:var(--muted);margin-top:5px">No location pinned.</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px">
          <input id="wlat" type="number" step="any" placeholder="Latitude"  class="form-input" oninput="syncPinFromInputs()">
          <input id="wlng" type="number" step="any" placeholder="Longitude" class="form-input" oninput="syncPinFromInputs()">
        </div>
      </div>

      <div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:4px">Checklist items</div>
        <div id="clf"></div>
        <button onclick="addCL()" class="btn-secondary" style="font-size:12px;margin-top:4px">+ Add Item</button>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal('mwo')" class="btn-secondary">Cancel</button>
        <button onclick="submitWO()" class="btn-primary">Create</button>
      </div>
    </div>
  </div>
</div>

<script>
  // ── Document Generator ─────────────────────────────
let currentDocWoId    = null;
let currentDocWoTitle = '';

function openDocModal(woId, woTitle) {
  currentDocWoId    = woId;
  currentDocWoTitle = woTitle;
  document.getElementById('docWoTitle').textContent = woTitle;
  document.getElementById('docWoId').textContent    = '#' + String(woId).padStart(5, '0');
  // Reset fields
  document.getElementById('docStaff').value        = '';
  document.getElementById('docLeader').value       = '';
  document.getElementById('docInstructions').value = '';
  document.getElementById('docApprovedBy').value   = '';
  openModal('mDoc');
}


function openDocumentPage() {
  if (!currentDocWoId) return;


  const params = new URLSearchParams({
    id:           currentDocWoId,
    staff:        document.getElementById('docStaff').value.trim(),
    team_leader:  document.getElementById('docLeader').value.trim(),
    prepared_by:  document.getElementById('docPreparedBy').value.trim(),
    approved_by:  document.getElementById('docApprovedBy').value.trim(),
    instructions: document.getElementById('docInstructions').value.trim(),
  });

  closeModal('mDoc');
  window.open('work-order-document.php?' + params.toString(), '_blank');
}
const ROLE     = <?php echo json_encode($_SESSION['role']); ?>;
const CAN_EDIT = ['Admin','Staff'].includes(ROLE);

// ── Filter state ──────────────────────────────────────────────
const activeFilters = { status: new Set(), priority: new Set() };
let allWorkOrders   = [];

function toggleFilter(type, value) {
  const set = activeFilters[type];
  set.has(value) ? set.delete(value) : set.add(value);

  const cls = type === 'status'
    ? 's-' + value.replace(/\s/g, '')
    : 'p-' + value;
  const containerId = type === 'status' ? 'statusToggles' : 'priorityToggles';
  const btn = document.querySelector(`#${containerId} .ftog.${cls}`);
  if (btn) btn.classList.toggle('active', set.has(value));

  applyFilters();
}

function clearDateFilter() {
  document.getElementById('fDateFrom').value = '';
  document.getElementById('fDateTo').value   = '';
  document.getElementById('btnClearDate').style.display = 'none';
  applyFilters();
}


function getFiltered() {
  const q        = document.getElementById('fq').value.toLowerCase().trim();
  const dateFrom = document.getElementById('fDateFrom').value; // 'YYYY-MM-DD' or ''
  const dateTo   = document.getElementById('fDateTo').value;   // 'YYYY-MM-DD' or ''


  document.getElementById('btnClearDate').style.display =
    (dateFrom || dateTo) ? 'inline-block' : 'none';

  let out = allWorkOrders; // all records, past and present

  // Status filter
  if (activeFilters.status.size > 0) {
    out = out.filter(w => activeFilters.status.has(w.status));
  }

  // Priority filter
  if (activeFilters.priority.size > 0) {
    out = out.filter(w => activeFilters.priority.has(w.priority));
  }

  // Date range filter
  // Uses created_at as the base date. Falls back to scheduled_date if created_at absent.
  if (dateFrom || dateTo) {
    out = out.filter(w => {
      // Normalise: take only the date part (first 10 chars of 'YYYY-MM-DD HH:MM:SS')
      const raw  = (w.created_at || w.scheduled_date || '').trim();
      const wDate = raw.substring(0, 10); // 'YYYY-MM-DD'
      if (!wDate) return true; // no date info → include rather than hide

      if (dateFrom && wDate < dateFrom) return false;
      if (dateTo   && wDate > dateTo)   return false;
      return true;
    });
  }

  // Text search
  if (q) {
    out = out.filter(w =>
      (w.title        || '').toLowerCase().includes(q) ||
      (w.location     || '').toLowerCase().includes(q) ||
      (w.description  || '').toLowerCase().includes(q) ||
      (w.type         || '').toLowerCase().includes(q)
    );
  }

  return out;
}

function applyFilters() {
  const filtered = getFiltered();
  renderList(filtered);
  updateMapMarkers(filtered);
}

// ── Fetch ALL work orders (no server-side filter) ─────────────
async function load() {
  const d = await apiGet('maintenance.php', { action: 'get_work_orders' });
  // FIX: store every record regardless of status/date — client filters handle visibility
  allWorkOrders = d?.data || [];
  applyFilters();
}

// ── Render list ───────────────────────────────────────────────
function renderList(wos) {
  const el = document.getElementById('woList');
  if (!wos.length) {
    el.innerHTML = '<p style="color:var(--muted);padding:20px 0">No work orders match the current filters.</p>';
    return;
  }
  el.innerHTML = wos.map(w => `
    <div class="wo-card" onclick="openWO(${w.id})">
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;gap:8px">
        <span style="font-weight:600">${w.title}</span>
        <div style="display:flex;gap:5px;flex-shrink:0">
          <span class="badge p-${w.priority}">${w.priority}</span>
          <span class="badge s-${(w.status || '').replace(/\s/g, '')}">${w.status}</span>
        </div>
      </div>
      <div style="font-size:12px;color:var(--muted)">
        ${w.type}${w.location ? ' · ' + w.location : ''}
        ${validCoord(w.latitude, w.longitude) ? ' · 📍' : ''}
      </div>
      ${w.created_at    ? `<div style="font-size:11px;color:var(--muted);margin-top:3px">🕐 Created: ${w.created_at.substring(0,10)}</div>` : ''}
      ${w.scheduled_date? `<div style="font-size:11px;color:var(--muted);margin-top:2px">📅 Scheduled: ${w.scheduled_date}</div>` : ''}
      ${w.completed_at  ? `<div style="font-size:11px;color:var(--accent3);margin-top:2px">✅ Completed: ${w.completed_at.substring(0,10)}</div>` : ''}
      ${w.downtime_minutes ? `<div style="font-size:11px;color:var(--warn);margin-top:2px">⏱ ${w.downtime_minutes} min downtime</div>` : ''}
    </div>`).join('');
}

// ── Main map ──────────────────────────────────────────────────
let woMap        = null;
let markersLayer = null;
let heatLayer    = null;
let heatmapOn    = false;

const priorityColors = {
  Critical: '#dc2626', High: '#ea580c', Medium: '#ca8a04', Low: '#16a34a'
};

function initWoMap() {
  if (woMap) return;
  const container = document.getElementById('wo-map');
  if (!container) return;
  if (container.offsetWidth === 0 || container.offsetHeight === 0) {
    setTimeout(initWoMap, 100);
    return;
  }
  woMap = L.map('wo-map', { center: [6.2232, 125.0721], zoom: 13, zoomControl: true });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 20,
  }).addTo(woMap);
  markersLayer = L.layerGroup().addTo(woMap);
}

function validCoord(lat, lng) {
  const lt = parseFloat(lat);
  const lg = parseFloat(lng);
  return (
    isFinite(lt) && isFinite(lg) &&
    !(lt === 0 && lg === 0) &&
    lt >= -90  && lt <= 90 &&
    lg >= -180 && lg <= 180
  );
}

function updateMapMarkers(wos) {
  if (!woMap || !markersLayer) return;
  markersLayer.clearLayers();
  if (heatLayer) { woMap.removeLayer(heatLayer); heatLayer = null; }

  const withCoords = wos.filter(w => validCoord(w.latitude, w.longitude));
  document.getElementById('map-count').textContent =
    withCoords.length
      ? `${withCoords.length} of ${wos.length} plotted`
      : `${wos.length} work order(s) — none have coordinates`;

  const bounds = [];

  withCoords.forEach(w => {
    const lat   = parseFloat(w.latitude);
    const lng   = parseFloat(w.longitude);
    const color = priorityColors[w.priority] || '#0057ff';

    const marker = L.circleMarker([lat, lng], {
      radius: 8, color: '#fff', weight: 2, fillColor: color, fillOpacity: 0.9,
    });

    marker.bindPopup(`
      <div style="font-family:sans-serif;min-width:200px">
        <div style="font-size:13px;font-weight:700;margin-bottom:6px">${w.title}</div>
        <div style="font-size:12px;margin-bottom:2px">Status: <b>${w.status}</b></div>
        <div style="font-size:12px;margin-bottom:2px">Priority: <b style="color:${color}">${w.priority}</b></div>
        <div style="font-size:12px;margin-bottom:2px">Type: ${w.type}</div>
        ${w.created_at ? `<div style="font-size:11px;color:#888;margin-top:4px">Created: ${w.created_at.substring(0,10)}</div>` : ''}
        ${w.completed_at ? `<div style="font-size:11px;color:#16a34a;margin-top:2px">Completed: ${w.completed_at.substring(0,10)}</div>` : ''}
        ${w.description ? `<div style="font-size:12px;margin-top:4px;color:#444">${w.description.substring(0,80)}${w.description.length > 80 ? '…' : ''}</div>` : ''}
        <button
          style="margin-top:8px;width:100%;padding:5px;background:#0057ff;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:11px"
          onclick="document.querySelectorAll('.leaflet-popup-close-button').forEach(b=>b.click());openWO(${w.id})">
          View Details
        </button>
      </div>
    `);

    markersLayer.addLayer(marker);
    bounds.push([lat, lng]);
  });

  if (bounds.length > 0) {
    try { woMap.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 }); } catch (e) {}
  }

  if (heatmapOn) renderHeatmap(withCoords);
}

function renderHeatmap(wos) {
  if (heatLayer) { woMap.removeLayer(heatLayer); heatLayer = null; }
  const pts = wos
    .filter(w => validCoord(w.latitude, w.longitude))
    .map(w => [
      parseFloat(w.latitude),
      parseFloat(w.longitude),
      w.priority === 'Critical' ? 1.0 : w.priority === 'High' ? 0.7 : w.priority === 'Medium' ? 0.4 : 0.2,
    ]);
  if (pts.length > 0) {
    heatLayer = L.heatLayer(pts, { radius: 30, blur: 25, maxZoom: 17 }).addTo(woMap);
  }
}

function toggleHeatmap() {
  heatmapOn = !heatmapOn;
  document.getElementById('btnHeatmap').classList.toggle('active', heatmapOn);
  const filtered = getFiltered();
  if (heatmapOn) renderHeatmap(filtered);
  else if (heatLayer) { woMap.removeLayer(heatLayer); heatLayer = null; }
}

// ── Pin-selection map inside modal ────────────────────────────
let pinMap    = null;
let pinMarker = null;

function initPinMap() {
  if (pinMap) { pinMap.invalidateSize(); return; }
  const container = document.getElementById('pin-map');
  if (!container || container.offsetHeight === 0) { setTimeout(initPinMap, 120); return; }
  pinMap = L.map('pin-map', { center: [6.2232, 125.0721], zoom: 13, zoomControl: true });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 20,
  }).addTo(pinMap);
  pinMap.on('click', e => setPin(e.latlng.lat, e.latlng.lng));
}

function setPin(lat, lng) {
  if (!pinMap) return;
  if (pinMarker) pinMap.removeLayer(pinMarker);
  pinMarker = L.marker([lat, lng], { draggable: true }).addTo(pinMap);
  pinMarker.on('dragend', e => {
    const p = e.target.getLatLng();
    updatePinInputs(p.lat, p.lng);
  });
  updatePinInputs(lat, lng);
}

function updatePinInputs(lat, lng) {
  document.getElementById('wlat').value = lat.toFixed(6);
  document.getElementById('wlng').value = lng.toFixed(6);
  document.getElementById('pin-coords').innerHTML =
    `<span style="color:var(--accent3)">✓ Pinned:</span> ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
}

function syncPinFromInputs() {
  const lat = parseFloat(document.getElementById('wlat').value);
  const lng = parseFloat(document.getElementById('wlng').value);
  if (isFinite(lat) && isFinite(lng) && pinMap) {
    setPin(lat, lng);
    pinMap.setView([lat, lng], 15);
  }
}

function openWOModal() {
  ['wt','wd','wloc','wcause'].forEach(id => { document.getElementById(id).value = ''; });
  document.getElementById('wsched').value  = '';
  document.getElementById('wlat').value    = '';
  document.getElementById('wlng').value    = '';
  document.getElementById('pin-coords').textContent = 'No location pinned.';
  document.getElementById('clf').innerHTML = '';
  if (pinMarker && pinMap) { pinMap.removeLayer(pinMarker); pinMarker = null; }
  openModal('mwo');
  setTimeout(initPinMap, 300);
}

// ── Checklist builder ─────────────────────────────────────────
function addCL() {
  const d = document.createElement('div');
  d.style.cssText = 'display:flex;gap:6px;margin-top:4px';
  d.innerHTML = `<input name="cl" placeholder="Item…" class="form-input" style="flex:1;font-size:13px">
    <button onclick="this.parentElement.remove()" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:18px">✕</button>`;
  document.getElementById('clf').appendChild(d);
}

// ── Submit new work order ─────────────────────────────────────
async function submitWO() {
  const title = document.getElementById('wt').value.trim();
  if (!title) { showToast('Title required', 'error'); return; }
  const lat = document.getElementById('wlat').value.trim();
  const lng = document.getElementById('wlng').value.trim();
  const cl  = [...document.querySelectorAll('#clf input[name="cl"]')]
    .map(i => i.value.trim()).filter(Boolean);
  const data = {
    action: 'save_work_order',
    title,
    description:    document.getElementById('wd').value,
    type:           document.getElementById('wtype').value,
    priority:       document.getElementById('wpri').value,
    location:       document.getElementById('wloc').value,
    cause:          document.getElementById('wcause').value,
    scheduled_date: document.getElementById('wsched').value,
    latitude:       lat || '',
    longitude:      lng || '',
  };
  if (cl.length) data.checklist = JSON.stringify(cl);
  const r = await apiPost('maintenance.php', data);
  if (r?.success || r?.id) {
    closeModal('mwo');
    showToast('Work order created', 'success');
    await load();
  } else {
    showToast(r?.error || 'Failed to create work order', 'error');
  }
}

// ── Work order detail panel ───────────────────────────────────
let detailMiniMap = null;

async function openWO(id) {
  document.getElementById('panel').style.display = 'block';
  document.getElementById('pbody').innerHTML     = '<div class="spinner"></div>';
  const d  = await apiGet('maintenance.php', { action: 'get_work_order', id });
  const wo = d?.data;
  if (!wo) { document.getElementById('pbody').innerHTML = '<p style="color:var(--danger)">Failed to load.</p>'; return; }
  document.getElementById('ptitle').textContent = wo.title;

  const sa = CAN_EDIT
    ? ['Pending','In Progress','Completed','Cancelled'].map(s =>
        `<button onclick="chStatus(${wo.id},'${s}')" class="btn-secondary" style="font-size:11px;padding:4px 8px">${s}</button>`
      ).join('')
    : '';

  const cl = (wo.checklist || []).map(c => `
    <div class="check-row">
      <input type="checkbox" ${c.is_done ? 'checked' : ''}
        ${CAN_EDIT ? `onchange="togCL(${c.id}, this.checked)"` : 'disabled'}>
      <span style="${c.is_done ? 'text-decoration:line-through;color:var(--muted)' : ''}">${c.item}</span>
      ${c.done_by ? `<span style="font-size:11px;color:var(--muted);margin-left:auto">${c.done_by}</span>` : ''}
    </div>`).join('') || '<p style="color:var(--muted);font-size:13px">No checklist items.</p>';

  const upd = (wo.updates || []).map(u => `
    <div class="upd-row">
      <div>${u.note}</div>
      <div style="font-size:11px;color:var(--muted);margin-top:2px">${u.updated_by_name || ''} · ${u.updated_at || ''}</div>
    </div>`).join('') || '<p style="color:var(--muted);font-size:13px">No updates yet.</p>';

  const hasCoords = validCoord(wo.latitude, wo.longitude);

document.getElementById('pbody').innerHTML = `
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
      <span class="badge p-${wo.priority}">${wo.priority}</span>
      <span class="badge s-${wo.status.replace(/\s/g,'')}">${wo.status}</span>
      <span style="font-size:12px;color:var(--muted)">${wo.type}</span>
    </div>
    ${wo.description?`<p style="font-size:13px;color:var(--text2);margin-bottom:12px">${wo.description}</p>`:''}
    <div style="font-size:13px;display:grid;gap:5px;margin-bottom:14px">
      ${wo.location?`<div>📍 ${wo.location}</div>`:''}
      ${wo.scheduled_date?`<div>📅 Scheduled: ${wo.scheduled_date}</div>`:''}
      ${wo.started_at?`<div>▶️ Started: ${wo.started_at}</div>`:''}
      ${wo.completed_at?`<div>✅ Completed: ${wo.completed_at}</div>`:''}
      ${wo.downtime_minutes?`<div style="color:var(--warn)">⏱ Downtime: ${wo.downtime_minutes} min</div>`:''}
      ${wo.cause?`<div style="color:var(--warn)">⚠️ ${wo.cause}</div>`:''}
      ${wo.resolution?`<div>🔧 ${wo.resolution}</div>`:''}
    </div>
    ${sa?`<div style="margin-bottom:14px"><div style="font-size:12px;color:var(--muted);margin-bottom:6px">Change Status</div><div style="display:flex;gap:6px;flex-wrap:wrap">${sa}</div></div>`:''}
    <div style="margin-bottom:14px;padding:10px 0;border-top:1px solid var(--border)">
      <button onclick="openDocModal(${wo.id}, '${(wo.title||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'")}'); document.getElementById('panel').style.display='none';"
        class="btn-primary" style="width:100%;font-size:13px;padding:10px">
        📄 Generate Work Order Document
      </button>
    </div>
    ${CAN_EDIT?`<div style="margin-bottom:14px">
      <textarea id="unote" placeholder="Realtime update note…" class="form-input" rows="2" style="font-size:13px"></textarea>
      <button onclick="postUpd(${wo.id})" class="btn-primary" style="width:100%;margin-top:6px;font-size:13px">Post Update</button>
    </div>`:''}
    <div style="font-size:13px;font-weight:600;margin-bottom:8px">Checklist (${(d.checklist||[]).filter(c=>c.is_done).length}/${(d.checklist||[]).length})</div>
    ${cl}
    <div style="font-size:13px;font-weight:600;margin:14px 0 8px">Live Updates</div>
    ${upd}`;

  if (hasCoords) {
    setTimeout(() => {
      if (detailMiniMap) { detailMiniMap.remove(); detailMiniMap = null; }
      const lat   = parseFloat(wo.latitude);
      const lng   = parseFloat(wo.longitude);
      const color = priorityColors[wo.priority] || '#0057ff';
      detailMiniMap = L.map('detail-map', {
        center: [lat, lng], zoom: 15, zoomControl: false, attributionControl: false,
      });
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20 }).addTo(detailMiniMap);
      L.circleMarker([lat, lng], {
        radius: 9, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1,
      }).addTo(detailMiniMap).bindPopup(wo.title).openPopup();
    }, 80);
  }
}

async function chStatus(id, status) {
  const r = await apiPost('maintenance.php', { action: 'update_status', id, status });
  if (r?.success || r?.ok) { showToast('Status updated', 'success'); await load(); openWO(id); }
  else showToast(r?.error || 'Failed', 'error');
}

async function togCL(itemId) {
  await apiPost('maintenance.php', { action: 'toggle_checklist', item_id: itemId });
}

async function postUpd(woid) {
  const el = document.getElementById('unote');
  const n  = el ? el.value.trim() : '';
  if (!n) return;
  const r = await apiPost('maintenance.php', { action: 'add_update', id: woid, note: n });
  if (r?.success || r?.ok) openWO(woid);
  else showToast(r?.error || 'Failed', 'error');
}

async function loadDT() {
  const d = await apiGet('maintenance.php', { action: 'downtime_summary' });
  const s = d?.data || [];
  if (!s.length) return;
  document.getElementById('dtBar').innerHTML = s.map(m => `
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:22px;font-weight:700;color:var(--warn)">${m.total_downtime_min || 0}</div>
      <div style="font-size:11px;color:var(--muted)">${m.type || 'All'} min</div>
    </div>`).join('');
}

document.addEventListener('DOMContentLoaded', () => {
  initWoMap();
  setTimeout(async () => {
    await load();
    loadDT();
  }, 50);
});
</script>