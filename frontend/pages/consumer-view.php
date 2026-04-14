<?php
$pageTitle = 'Consumer Details';
require_once 'layout.php';

$account_id = $_GET['account_id'] ?? '';
if (!$account_id) {
    header('Location: consumers.php');
    exit;
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>

<style>
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
  margin-bottom: 20px;
}
.info-item {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 13px 15px;
}
.info-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; margin-bottom: 4px; }
.info-value { font-size: 14px; font-weight: 600; }
.chart-container { position: relative; height: 240px; }

/* Meter coordinate picker */
#meter-map {
  height: 300px;
  width: 100%;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--border);
  position: relative;
  z-index: 0;
  margin-bottom: 10px;
}
.meter-coord-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin-bottom: 8px;
}
.map-hint {
  font-size: 12px;
  color: var(--muted);
  margin-bottom: 8px;
}
.map-hint.pinned { color: var(--accent3); }

/* Meter list items */
.meter-item {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 12px 14px;
  margin-bottom: 6px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

/* Tab system */
.tab-bar {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 16px;
}
.tab-btn-cv {
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 500;
  font-family: 'Sora', sans-serif;
  border: none;
  background: none;
  color: var(--text2);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: all 0.15s;
  margin-bottom: -1px;
}
.tab-btn-cv.active {
  color: var(--accent);
  border-bottom-color: var(--accent);
}
.tab-pane-cv { display: none; }
.tab-pane-cv.active { display: block; }
</style>

<main class="main">

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
  <a href="consumers.php" class="btn-secondary" style="font-size:13px;text-decoration:none;padding:7px 14px;border-radius:8px;border:1px solid var(--border);color:var(--text)">← Back</a>
  <h2 id="conName" style="font-size:20px;font-weight:700">Loading…</h2>
  <span id="conStatus"></span>
</div>

<div id="spinner" style="text-align:center;padding:60px"><div class="spinner"></div></div>

<div id="content" style="display:none">

  <!-- Info grid -->
  <div class="info-grid" id="infoGrid"></div>

  <!-- Tabbed content area -->
  <div class="card">
    <div class="tab-bar">
      <button class="tab-btn-cv active" onclick="showTab('consumption', this)">💧 Consumption</button>
      <button class="tab-btn-cv" onclick="showTab('requests', this)">📋 Requests</button>
      <button class="tab-btn-cv" onclick="showTab('meter', this)">📍 Meter Location</button>
    </div>

    <!-- Consumption chart tab -->
    <div class="tab-pane-cv active" id="tab-consumption">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px">
        <div style="font-size:13px;color:var(--muted)">
          Average: <span id="avgConsumption" style="color:var(--accent);font-weight:600">—</span> m³/month
        </div>
        <button id="btnPredict" onclick="runPrediction()" class="btn-secondary" style="font-size:12px;padding:5px 12px">
          📈 Predict Next Month
        </button>
      </div>
      <div class="chart-container">
        <canvas id="consChart"></canvas>
      </div>
      <div id="predictionResult" style="margin-top:12px"></div>
    </div>

    <!-- Service Requests tab -->
    <div class="tab-pane-cv" id="tab-requests">
      <div id="serviceHistory"><p style="color:var(--muted);font-size:13px">Loading…</p></div>
    </div>

    <!-- Meter Location tab -->
    <div class="tab-pane-cv" id="tab-meter">
      <div style="margin-bottom:14px">
        <div style="font-size:14px;font-weight:600;margin-bottom:4px">Water Meter Location</div>
        <div style="font-size:13px;color:var(--muted);margin-bottom:12px">
          Click the map to update this consumer's meter GPS location. Drag the pin to adjust.
        </div>

        <!-- Map picker -->
        <div class="map-hint" id="meterMapHint">Click the map to place the meter pin</div>
        <div id="meter-map"></div>

        <!-- Coordinate inputs -->
        <div class="meter-coord-row">
          <div>
            <label class="form-label">Latitude</label>
            <input type="number" id="meterLat" class="form-input" step="any" placeholder="e.g. 6.223262" oninput="syncMeterFromInputs()">
          </div>
          <div>
            <label class="form-label">Longitude</label>
            <input type="number" id="meterLng" class="form-input" step="any" placeholder="e.g. 125.072111" oninput="syncMeterFromInputs()">
          </div>
        </div>

        <?php if (in_array($_SESSION['role'], ['Admin', 'Staff'])): ?>
        <div id="meterSaveMsg" style="display:none;margin-bottom:10px;font-size:13px;padding:8px 12px;border-radius:8px"></div>
        <button onclick="saveMeterLocation()" id="meterSaveBtn" class="btn-primary" style="width:100%;padding:11px">
          💾 Save Meter Location
        </button>
        <?php else: ?>
        <div style="font-size:12px;color:var(--muted);padding:8px;background:var(--surface2);border-radius:8px">
          ℹ️ Only Admin/Staff can update meter coordinates.
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>
</main>

<script>
const CONSUMER_ACCOUNT_ID = <?= json_encode($account_id) ?>;
let CONSUMER_ID = null;    // numeric db id, set after loading
let consumerData = null;

// ══════════════════════════════════════════════════════════════
// TAB SYSTEM
// ══════════════════════════════════════════════════════════════
function showTab(id, btn) {
  document.querySelectorAll('.tab-pane-cv').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn-cv').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + id).classList.add('active');
  btn.classList.add('active');

  // Initialise the meter map lazily when the tab is first opened
  if (id === 'meter' && !meterMap) {
    initMeterMap();
  }
}

// ══════════════════════════════════════════════════════════════
// LOAD CONSUMER
// ══════════════════════════════════════════════════════════════
async function loadConsumer() {
  const d = await apiGet('consumer.php', { action: 'get_consumer', account_id: CONSUMER_ACCOUNT_ID });
  const c = d?.data;

  if (!c) {
    document.getElementById('spinner').innerHTML = '<p style="color:var(--danger);text-align:center">Consumer not found.</p>';
    return;
  }

  consumerData = c;
  CONSUMER_ID  = c.id;

  document.getElementById('spinner').style.display = 'none';
  document.getElementById('content').style.display  = 'block';
  document.getElementById('conName').textContent    = c.name;
  document.getElementById('conStatus').innerHTML    =
    `<span class="badge badge-${(c.status || '').toLowerCase()}">${c.status}</span>`;

  // Info grid
  const fields = [
    ['Account No',  c.account_no || c.account_id],
    ['Type',        c.type],
    ['Status',      c.status],
    ['Barangay',    c.barangay || '—'],
    ['Municipal',   c.municipal || '—'],
    ['Zone',        c.zone || '—'],
    ['Contact',     c.contact_no || '—'],
    ['Email',       c.email || '—'],
    ['Meter Brand', c.meter_brand || '—'],
    ['Meter No',    c.meter_number || '—'],
    ['Address',     c.address || '—'],
    ['Senior',      c.is_senior ? 'Yes ⭐' : 'No'],
  ];
  document.getElementById('infoGrid').innerHTML = fields.map(([label, val]) => `
    <div class="info-item">
      <div class="info-label">${label}</div>
      <div class="info-value">${val}</div>
    </div>`).join('');

  // Append coordinates card if available
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

  // Build consumption chart
  buildChart(c.consumption_history || []);

  // Pre-fill meter location inputs if coordinates exist
  if (c.latitude && c.longitude) {
    document.getElementById('meterLat').value = parseFloat(c.latitude).toFixed(7);
    document.getElementById('meterLng').value = parseFloat(c.longitude).toFixed(7);
    document.getElementById('meterMapHint').textContent = `Current: ${parseFloat(c.latitude).toFixed(5)}, ${parseFloat(c.longitude).toFixed(5)} — click map to update`;
    document.getElementById('meterMapHint').className = 'map-hint pinned';
  }

  // Load service history
  loadServiceHistory(c.id);
}

// ══════════════════════════════════════════════════════════════
// CONSUMPTION CHART
// ══════════════════════════════════════════════════════════════
function buildChart(hist) {
  if (!hist.length) {
    document.getElementById('consChart').parentElement.innerHTML =
      '<p style="color:var(--muted);font-size:13px;padding:20px 0">No consumption data available.</p>';
    return;
  }
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
        borderWidth: 1, borderRadius: 4,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.y} m³` } },
      },
      scales: {
        x: { ticks: { color: '#94a3b8', font: { size: 11 } }, grid: { color: '#1e2d40' } },
        y: { ticks: { color: '#94a3b8' }, grid: { color: '#1e2d40' }, beginAtZero: true },
      }
    }
  });
}

// ══════════════════════════════════════════════════════════════
// CONSUMPTION PREDICTION
// ══════════════════════════════════════════════════════════════
async function runPrediction() {
  const btn = document.getElementById('btnPredict');
  const el  = document.getElementById('predictionResult');
  btn.disabled = true; btn.textContent = 'Calculating…';
  const r = await apiGet('consumer.php', { action: 'predict_consumption', consumer_id: CONSUMER_ID });
  btn.disabled = false; btn.textContent = '📈 Predict Next Month';
  if (r.error) {
    el.innerHTML = `<div style="color:var(--danger);font-size:13px;padding:8px">⚠️ ${r.error}</div>`;
    return;
  }
  const trendColor = r.trend === 'increasing' ? 'var(--danger)' : r.trend === 'decreasing' ? 'var(--accent3)' : 'var(--text2)';
  el.innerHTML = `
    <div style="background:rgba(0,87,255,.08);border:1px solid rgba(0,87,255,.2);border-radius:8px;padding:12px;font-size:13px">
      <div style="font-weight:600;color:var(--accent2);margin-bottom:8px">📈 Next Month Prediction</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
        <div>Predicted: <b style="color:var(--accent)">${r.predicted_m3} m³</b></div>
        <div>3-Month Avg: <b>${r.avg_last_3_months} m³</b></div>
        <div>Trend: <b style="color:${trendColor}">${r.trend}</b></div>
        <div>Method: <b style="color:var(--text2)">${r.method}</b></div>
      </div>
    </div>`;
}

// ══════════════════════════════════════════════════════════════
// SERVICE HISTORY
// ══════════════════════════════════════════════════════════════
async function loadServiceHistory(consumerId) {
  const d    = await apiGet('consumer.php', { action: 'get_requests', consumer_id: consumerId });
  const reqs = d?.data || [];
  const el   = document.getElementById('serviceHistory');
  if (!reqs.length) {
    el.innerHTML = '<p style="color:var(--muted);font-size:13px;text-align:center;padding:20px 0">No service history.</p>';
    return;
  }
  el.innerHTML = `
    <div class="table-wrap">
      <table>
        <thead><tr><th>Type</th><th>Subject</th><th>Status</th><th>Date</th></tr></thead>
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

// ══════════════════════════════════════════════════════════════
// METER LOCATION MAP (lazy-initialised when tab opens)
// ══════════════════════════════════════════════════════════════
let meterMap    = null;
let meterMarker = null;

function initMeterMap() {
  // Default center: Polomolok. If consumer has coords, use those.
  const lat0 = consumerData?.latitude  ? parseFloat(consumerData.latitude)  : 6.223262;
  const lng0 = consumerData?.longitude ? parseFloat(consumerData.longitude) : 125.072111;

  meterMap = L.map('meter-map', {
    center: [lat0, lng0],
    zoom:   16,
    zoomControl: true,
  });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 20
  }).addTo(meterMap);

  // If consumer already has a recorded position, show it immediately
  if (consumerData?.latitude && consumerData?.longitude) {
    placeMeterPin(lat0, lng0, false);
  }

  // Map click → place/move pin
  meterMap.on('click', e => placeMeterPin(e.latlng.lat, e.latlng.lng, true));
}

/**
 * Place or drag the water-meter pin to new coordinates.
 * @param {number}  lat
 * @param {number}  lng
 * @param {boolean} userAction  true = update inputs and hint; false = silent init
 */
function placeMeterPin(lat, lng, userAction = true) {
  const icon = L.divIcon({
    className: '',
    html: '<div style="font-size:26px;filter:drop-shadow(0 3px 5px rgba(0,0,0,.6));line-height:1;cursor:grab">💧</div>',
    iconSize:   [26, 26],
    iconAnchor: [13, 26],
  });

  if (meterMarker) {
    meterMarker.setLatLng([lat, lng]);
  } else {
    meterMarker = L.marker([lat, lng], { icon, draggable: true }).addTo(meterMap);
    // Dragging the pin also updates inputs
    meterMarker.on('dragend', e => {
      const p = e.target.getLatLng();
      setMeterInputs(p.lat, p.lng);
    });
  }

  if (userAction) {
    setMeterInputs(lat, lng);
    meterMap.setView([lat, lng], Math.max(meterMap.getZoom(), 16));
  }
}

/** Update the lat/lng inputs and the hint text */
function setMeterInputs(lat, lng) {
  document.getElementById('meterLat').value = lat.toFixed(7);
  document.getElementById('meterLng').value = lng.toFixed(7);
  const hint = document.getElementById('meterMapHint');
  hint.textContent = `✓ Pinned: ${lat.toFixed(5)}, ${lng.toFixed(5)} — drag to adjust`;
  hint.className   = 'map-hint pinned';
}

/** Sync marker when user types into coordinate inputs */
function syncMeterFromInputs() {
  const lat = parseFloat(document.getElementById('meterLat').value);
  const lng = parseFloat(document.getElementById('meterLng').value);
  if (isFinite(lat) && isFinite(lng) && meterMap) {
    placeMeterPin(lat, lng, true);
  }
}

// ══════════════════════════════════════════════════════════════
// SAVE METER LOCATION
// ══════════════════════════════════════════════════════════════
async function saveMeterLocation() {
  const lat    = document.getElementById('meterLat').value.trim();
  const lng    = document.getElementById('meterLng').value.trim();
  const msgEl  = document.getElementById('meterSaveMsg');
  const btn    = document.getElementById('meterSaveBtn');

  if (!lat || !lng) {
    msgEl.style.cssText = 'display:block;background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);color:var(--danger)';
    msgEl.textContent   = '⚠️ Please pin a location on the map first.';
    return;
  }

  btn.disabled = true; btn.textContent = 'Saving…';
  msgEl.style.display = 'none';

  // Re-use the save_consumer action which updates lat/lng
  const r = await apiPost('consumer.php', {
    action:    'save_consumer',
    id:        CONSUMER_ID,
    account_id: CONSUMER_ACCOUNT_ID,
    account_no: consumerData.account_no   || '',
    name:       consumerData.name,
    type:       consumerData.type,
    status:     consumerData.status,
    address:    consumerData.address      || '',
    barangay:   consumerData.barangay     || '',
    zone:       consumerData.zone         || '',
    contact_no: consumerData.contact_no   || '',
    email:      consumerData.email        || '',
    latitude:   lat,
    longitude:  lng,
  });

  btn.disabled = false; btn.textContent = '💾 Save Meter Location';

  if (r.success) {
    // Update local cache
    consumerData.latitude  = lat;
    consumerData.longitude = lng;

    msgEl.style.cssText = 'display:block;background:rgba(0,200,150,.1);border:1px solid rgba(0,200,150,.3);color:var(--accent3)';
    msgEl.textContent   = `✅ Meter location saved: ${parseFloat(lat).toFixed(6)}, ${parseFloat(lng).toFixed(6)}`;
  } else {
    msgEl.style.cssText = 'display:block;background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);color:var(--danger)';
    msgEl.textContent   = '❌ ' + (r.error || 'Failed to save. Please try again.');
  }
}

// ══════════════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════════════
loadConsumer();
</script>