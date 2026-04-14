<?php
$pageTitle = 'Add Infrastructure';
require_once 'layout.php';
requireRole('Admin', 'Staff');
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

<style>
.infra-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.infra-form-grid .full-width { grid-column: 1 / -1; }

/* Map container for coordinate picking */
#coord-picker-map {
  height: 360px;
  width: 100%;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--border);
  position: relative;
  z-index: 0;
}

/* Marker preview label */
#map-hint {
  font-size: 12px;
  color: var(--muted);
  margin-bottom: 8px;
}
#map-hint.pinned { color: var(--accent3); }

.coord-display-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-top: 10px;
}

/* Type icon picker */
.type-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  gap: 8px;
  margin-top: 6px;
}
.type-option {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 10px 8px;
  border: 1px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.15s;
  font-size: 12px;
  color: var(--text2);
  text-align: center;
}
.type-option:hover {
  border-color: var(--accent);
  background: rgba(0,212,255,0.06);
}
.type-option.selected {
  border-color: var(--accent);
  background: rgba(0,212,255,0.12);
  color: var(--accent);
}
.type-option .icon { font-size: 24px; }
.type-option .label { font-size: 11px; line-height: 1.2; }

/* Existing infrastructure list */
.infra-item {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 12px 16px;
  margin-bottom: 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  transition: border-color 0.15s;
}
.infra-item:hover { border-color: var(--accent); }
.infra-item .infra-icon { font-size: 22px; flex-shrink: 0; }
</style>

<main class="main">

<!-- Page header with nav -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
  <a href="infrastructure-list.php" class="btn-secondary" style="font-size:13px;text-decoration:none;padding:7px 14px;border-radius:8px;border:1px solid var(--border);color:var(--text)">← All Infrastructure</a>
  <h2 style="font-size:18px;font-weight:700">Add Infrastructure</h2>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

  <!-- LEFT: Form -->
  <div>
    <div class="card">
      <div class="card-title">Infrastructure Details</div>

      <!-- Infrastructure Type Picker -->
      <div style="margin-bottom:18px">
        <div class="form-label">Type *</div>
        <div class="type-grid" id="typeGrid">
          <?php
          $types = [
            ['pumping_station', '🏗️', 'Pumping Station'],
            ['reservoir',       '🗄️', 'Reservoir'],
            ['valve',           '🔧', 'Valve'],
            ['hydrant',         '🚒', 'Hydrant'],
            ['blowoff',         '💨', 'Blowoff'],
            ['meter_chamber',   '📊', 'Meter Chamber'],
            ['other',           '📌', 'Other'],
          ];
          foreach ($types as [$val, $emoji, $label]):
          ?>
          <div class="type-option" data-value="<?= $val ?>" onclick="selectType(this)">
            <div class="icon"><?= $emoji ?></div>
            <div class="label"><?= $label ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <input type="hidden" id="infraType" name="type" required>
      </div>

      <div class="infra-form-grid">
        <!-- Name -->
        <div class="full-width">
          <label class="form-label">Name *</label>
          <input type="text" id="infraName" class="form-input" placeholder="e.g. Purok 3 Main Pump">
        </div>

        <!-- Status -->
        <div>
          <label class="form-label">Status</label>
          <select id="infraStatus" class="form-input">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="maintenance">Under Maintenance</option>
          </select>
        </div>

        <!-- Installation Date -->
        <div>
          <label class="form-label">Installation Date</label>
          <input type="date" id="infraInstall" class="form-input">
        </div>

        <!-- Last Inspection -->
        <div>
          <label class="form-label">Last Inspection</label>
          <input type="date" id="infraInspect" class="form-input">
        </div>

        <!-- Barangay -->
        <div>
          <label class="form-label">Barangay</label>
          <input type="text" id="infraBarangay" class="form-input" placeholder="e.g. Poblacion">
        </div>

        <!-- Address -->
        <div class="full-width">
          <label class="form-label">Address / Location Description</label>
          <input type="text" id="infraAddress" class="form-input" placeholder="e.g. Along National Highway, Brgy. Cannery">
        </div>

        <!-- Notes -->
        <div class="full-width">
          <label class="form-label">Notes</label>
          <textarea id="infraNotes" class="form-input" rows="3" placeholder="Additional details, specifications, remarks…"></textarea>
        </div>
      </div>

      <!-- Error / Success messages -->
      <div id="errMsg" style="display:none;background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);border-radius:8px;padding:10px;font-size:13px;color:var(--danger);margin-top:10px"></div>
      <div id="okMsg"  style="display:none;background:rgba(0,200,150,.1);border:1px solid rgba(0,200,150,.3);border-radius:8px;padding:10px;font-size:13px;color:var(--accent3);margin-top:10px"></div>

      <!-- Submit -->
      <div style="display:flex;gap:8px;margin-top:16px">
        <button onclick="submitInfra()" id="submitBtn" class="btn-primary" style="flex:1;padding:12px;font-size:14px">
          Save Infrastructure
        </button>
        <button onclick="resetForm()" class="btn-secondary" style="padding:12px 16px">Reset</button>
      </div>
    </div>
  </div>

  <!-- RIGHT: Map coordinate picker -->
  <div>
    <div class="card">
      <div class="card-title">📍 Pick Location on Map</div>
      <div id="map-hint">Click anywhere on the map to set the coordinates</div>

      <div id="coord-picker-map"></div>

      <div class="coord-display-row">
        <div>
          <label class="form-label">Latitude</label>
          <input type="number" id="infraLat" class="form-input" step="any"
                 placeholder="6.223262" oninput="syncMarkerFromInputs()">
        </div>
        <div>
          <label class="form-label">Longitude</label>
          <input type="number" id="infraLng" class="form-input" step="any"
                 placeholder="125.072111" oninput="syncMarkerFromInputs()">
        </div>
      </div>

      <div style="margin-top:10px;font-size:11px;color:var(--muted)">
        💡 Tip: You can also type coordinates directly and the marker will update automatically.
        Right-click on the map to set location quickly.
      </div>
    </div>

    <!-- Preview card (shown after a type + location are both selected) -->
    <div class="card" id="previewCard" style="display:none">
      <div class="card-title">Preview</div>
      <div style="display:flex;align-items:center;gap:12px">
        <div id="previewIcon" style="font-size:36px"></div>
        <div>
          <div id="previewName" style="font-size:15px;font-weight:700"></div>
          <div id="previewType" style="font-size:12px;color:var(--text2)"></div>
          <div id="previewCoord" style="font-size:11px;color:var(--muted);font-family:'Space Mono',monospace;margin-top:2px"></div>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
// ══════════════════════════════════════════════════════════════
// COORDINATE PICKER MAP
// ══════════════════════════════════════════════════════════════
const pickerMap = L.map('coord-picker-map', {
  center: [6.223262, 125.072111],
  zoom:   13,
  zoomControl: true,
});
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap', maxZoom: 20
}).addTo(pickerMap);

let pickerMarker = null;

// Custom draggable pin icon
function getPickerIcon(emoji = '📌') {
  return L.divIcon({
    className: '',
    html: `<div style="font-size:30px;filter:drop-shadow(0 3px 5px rgba(0,0,0,.6));line-height:1;cursor:grab">${emoji}</div>`,
    iconSize:   [30, 30],
    iconAnchor: [15, 30],
  });
}

/** Place or move the marker on the map */
function placeMarker(lat, lng) {
  const emoji = getSelectedEmoji() || '📌';
  if (pickerMarker) {
    pickerMarker.setLatLng([lat, lng]);
    pickerMarker.setIcon(getPickerIcon(emoji));
  } else {
    pickerMarker = L.marker([lat, lng], {
      icon:      getPickerIcon(emoji),
      draggable: true,
    }).addTo(pickerMap);

    // Drag end → update inputs
    pickerMarker.on('dragend', e => {
      const p = e.target.getLatLng();
      setCoordInputs(p.lat, p.lng);
    });
  }

  // Center map on marker
  pickerMap.setView([lat, lng], Math.max(pickerMap.getZoom(), 15));
  setCoordInputs(lat, lng);
}

/** Update coordinate inputs and hint */
function setCoordInputs(lat, lng) {
  document.getElementById('infraLat').value = lat.toFixed(7);
  document.getElementById('infraLng').value = lng.toFixed(7);
  const hint = document.getElementById('map-hint');
  hint.textContent = `✓ Location set: ${lat.toFixed(5)}, ${lng.toFixed(5)} — drag the pin to adjust`;
  hint.className = 'map-hint pinned';
  updatePreview();
}

/** Sync marker when user types in the input boxes */
function syncMarkerFromInputs() {
  const lat = parseFloat(document.getElementById('infraLat').value);
  const lng = parseFloat(document.getElementById('infraLng').value);
  if (isFinite(lat) && isFinite(lng)) placeMarker(lat, lng);
}

// Click on map to place marker
pickerMap.on('click', e => placeMarker(e.latlng.lat, e.latlng.lng));
// Right-click also places (alternative for mobile)
pickerMap.on('contextmenu', e => { e.originalEvent.preventDefault(); placeMarker(e.latlng.lat, e.latlng.lng); });

// ══════════════════════════════════════════════════════════════
// TYPE SELECTION
// ══════════════════════════════════════════════════════════════
const typeEmojis = {
  pumping_station: '🏗️', reservoir: '🗄️', valve: '🔧',
  hydrant: '🚒', blowoff: '💨', meter_chamber: '📊', other: '📌',
};
const typeLabels = {
  pumping_station: 'Pumping Station', reservoir: 'Reservoir', valve: 'Valve',
  hydrant: 'Hydrant', blowoff: 'Blowoff', meter_chamber: 'Meter Chamber', other: 'Other',
};

function selectType(el) {
  document.querySelectorAll('.type-option').forEach(o => o.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('infraType').value = el.dataset.value;

  // Update marker icon if already placed
  if (pickerMarker) {
    pickerMarker.setIcon(getPickerIcon(typeEmojis[el.dataset.value]));
  }
  updatePreview();
}

function getSelectedType() { return document.getElementById('infraType').value; }
function getSelectedEmoji() { return typeEmojis[getSelectedType()] || null; }

// ══════════════════════════════════════════════════════════════
// PREVIEW CARD
// ══════════════════════════════════════════════════════════════
function updatePreview() {
  const name  = document.getElementById('infraName').value.trim();
  const type  = getSelectedType();
  const lat   = document.getElementById('infraLat').value;
  const lng   = document.getElementById('infraLng').value;
  const card  = document.getElementById('previewCard');

  if (!name && !type) { card.style.display = 'none'; return; }
  card.style.display = 'block';
  document.getElementById('previewIcon').textContent  = typeEmojis[type] || '📌';
  document.getElementById('previewName').textContent  = name || '(unnamed)';
  document.getElementById('previewType').textContent  = typeLabels[type] || '—';
  document.getElementById('previewCoord').textContent = lat && lng ? `${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}` : 'No location';
}

// Live name → preview
document.getElementById('infraName').addEventListener('input', updatePreview);

// ══════════════════════════════════════════════════════════════
// FORM SUBMIT
// ══════════════════════════════════════════════════════════════
async function submitInfra() {
  const errEl = document.getElementById('errMsg');
  const okEl  = document.getElementById('okMsg');
  errEl.style.display = 'none';
  okEl.style.display  = 'none';

  const name = document.getElementById('infraName').value.trim();
  const type = document.getElementById('infraType').value;
  if (!name) { errEl.textContent = 'Name is required.'; errEl.style.display = 'block'; return; }
  if (!type) { errEl.textContent = 'Please select an infrastructure type.'; errEl.style.display = 'block'; return; }

  const btn = document.getElementById('submitBtn');
  btn.disabled = true; btn.textContent = 'Saving…';

  const payload = {
    action:           'save_infrastructure',
    type,
    name,
    status:           document.getElementById('infraStatus').value,
    installation_date: document.getElementById('infraInstall').value,
    last_inspection:   document.getElementById('infraInspect').value,
    barangay:          document.getElementById('infraBarangay').value,
    address:           document.getElementById('infraAddress').value,
    notes:             document.getElementById('infraNotes').value,
    latitude:          document.getElementById('infraLat').value,
    longitude:         document.getElementById('infraLng').value,
  };

  const r = await apiPost('gis.php', payload);
  btn.disabled = false; btn.textContent = 'Save Infrastructure';

  if (r.success) {
    okEl.textContent  = `✅ Infrastructure "${name}" saved successfully! Redirecting…`;
    okEl.style.display = 'block';
    setTimeout(() => { window.location.href = `infrastructure-detail.php?id=${r.id || ''}`; }, 1400);
  } else {
    errEl.textContent  = r.error || 'Failed to save. Please try again.';
    errEl.style.display = 'block';
  }
}

function resetForm() {
  ['infraName','infraAddress','infraBarangay','infraNotes','infraInstall','infraInspect'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  document.getElementById('infraType').value   = '';
  document.getElementById('infraStatus').value = 'active';
  document.getElementById('infraLat').value    = '';
  document.getElementById('infraLng').value    = '';
  document.querySelectorAll('.type-option').forEach(o => o.classList.remove('selected'));
  if (pickerMarker) { pickerMap.removeLayer(pickerMarker); pickerMarker = null; }
  document.getElementById('previewCard').style.display = 'none';
  document.getElementById('map-hint').textContent  = 'Click anywhere on the map to set the coordinates';
  document.getElementById('map-hint').className    = 'map-hint';
  document.getElementById('errMsg').style.display  = 'none';
  document.getElementById('okMsg').style.display   = 'none';
}
</script>