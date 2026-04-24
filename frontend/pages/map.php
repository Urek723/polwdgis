<?php
$pageTitle = 'GIS Map';
require_once 'layout.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.heat/0.2.0/leaflet-heat.js"></script>
<link rel="stylesheet" href="../css/map-fix.css">

<style>
.main { padding: 0; overflow: hidden; }

#map-container {
  display: flex;
  height: calc(100vh - var(--topbar-h));
  overflow: hidden;
}
#map {
  flex: 1;
  background: #1a1f2e;
}

/* MAP TOOLBAR */
#map-toolbar {
  position: absolute;
  top: calc(var(--topbar-h) + 12px);
  left: calc(var(--sidebar-w) + 12px);
  display: flex;
  flex-direction: column;
  gap: 6px;
  z-index: 350;
}
.map-tool-group {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.map-btn {
  width: 36px; height: 36px;
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: var(--text2);
  transition: all 0.15s;
  font-size: 16px;
  position: relative;
}
.map-btn:hover, .map-btn.active {
  border-color: var(--accent);
  color: var(--accent);
  background: rgba(0,212,255,0.08);
}
.map-btn.proximity-active {
  border-color: var(--warn);
  color: var(--warn);
  background: rgba(255,184,0,0.12);
}

/* MAP SIDE PANEL */
#map-panel {
  width: 320px;
  background: var(--surface);
  border-left: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  transform: translateX(100%);
  transition: transform 0.3s ease;
  position: relative;
  z-index: 360;
}
#map-panel.open { transform: translateX(0); }
#panel-content { overflow-y: auto; flex: 1; padding: 16px; }

/* LEGEND */
#legend {
  position: absolute;
  bottom: calc(var(--topbar-h) + 24px);
  left: calc(var(--sidebar-w) + 12px);
  background: rgba(17,24,39,0.92);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px 14px;
  font-size: 11px;
  min-width: 150px;
  z-index: 350;
}
.legend-title {
  font-family: 'Space Mono', monospace;
  font-size: 10px;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  margin-bottom: 8px;
}
.legend-item {
  display: flex; align-items: center; gap: 8px;
  color: var(--text2);
  padding: 2px 0;
}
.legend-dot  { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.legend-line { width: 20px; height: 3px; border-radius: 2px; flex-shrink: 0; }

.legend-prox { display: none; }
.legend-prox.visible { display: flex; }

/* COORDINATE DISPLAY */
#coord-display {
  position: absolute;
  bottom: 8px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(17,24,39,0.85);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 5px 12px;
  font-family: 'Space Mono', monospace;
  font-size: 11px;
  color: var(--text2);
  pointer-events: none;
  z-index: 350;
}

/* MEASURE TOOLTIP */
.measure-label {
  background: rgba(0,212,255,0.9);
  color: #000;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-family: 'Space Mono', monospace;
  font-weight: 700;
  white-space: nowrap;
  pointer-events: none;
}

/* Proximity ring animation */
@keyframes proximityRing {
  0%   { stroke-opacity: 0.6; }
  50%  { stroke-opacity: 0.25; }
  100% { stroke-opacity: 0.6; }
}
.proximity-ring {
  animation: proximityRing 2s ease-in-out infinite;
}

/* LEAFLET POPUP — dark theme */
.leaflet-popup-content-wrapper {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.4);
  color: var(--text);
  font-family: 'Sora', sans-serif;
}
.leaflet-popup-tip { background: var(--surface); }
.popup-title { font-size: 14px; font-weight: 700; margin-bottom: 8px; color: var(--accent); }
.popup-row {
  display: flex; justify-content: space-between;
  font-size: 12px; color: var(--text2);
  padding: 3px 0;
  border-bottom: 1px solid rgba(255,255,255,0.04);
}
.popup-row span:last-child { color: var(--text); font-weight: 500; }
.popup-actions { display: flex; gap: 6px; margin-top: 10px; }
.popup-btn {
  flex: 1; padding: 6px; font-size: 11px;
  text-align: center; border-radius: 7px; border: none;
  cursor: pointer; font-family: 'Sora', sans-serif;
  font-weight: 600; transition: opacity 0.15s;
}
.popup-btn-info { background: rgba(0,212,255,0.15); color: var(--accent); }
.popup-btn-warn { background: rgba(255,184,0,0.15); color: var(--warn); }

/* COORDINATE INPUT PANEL */
#coord-input-panel {
  position: absolute;
  top: calc(var(--topbar-h) + 56px);
  right: 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 14px;
  width: 230px;
  display: none;
  z-index: 350;
}
#coord-input-panel.show { display: block; }

/* Proximity status bar */
#proximity-status {
  display: none;
  position: absolute;
  top: calc(var(--topbar-h) + 12px);
  left: 50%;
  transform: translateX(-50%);
  background: rgba(255,184,0,0.15);
  border: 1px solid rgba(255,184,0,0.4);
  border-radius: 20px;
  padding: 6px 18px;
  font-size: 12px;
  color: var(--warn);
  font-weight: 600;
  z-index: 350;
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.2s;
}
#proximity-status:hover {
  background: rgba(255,184,0,0.25);
}
#proximity-status.show { display: block; }
</style>

<main class="main">
  <div id="map-container">
    <div id="map"></div>
  </div>
</main>

<!-- TOOLBARS (outside .main) -->
<div id="map-toolbar">
  <div class="map-tool-group">
    <button class="map-btn" id="btnHome"    title="Reset View">🏠</button>
    <button class="map-btn" id="btnZoomIn"  title="Zoom In">+</button>
    <button class="map-btn" id="btnZoomOut" title="Zoom Out">−</button>
  </div>
  <div class="map-tool-group">
    <button class="map-btn" id="btnMeasure"    title="Measure Distance">📏</button>
    <button class="map-btn" id="btnCoordInput" title="Go to Coordinates">📍</button>
    <button class="map-btn" id="btnProximity"  title="Proximity Analysis (click to toggle)">⭕</button>
    <button class="map-btn" id="btnHeatmap"    title="Toggle Heatmap">🌡️</button>
    <button class="map-btn" id="btnPrint"      title="Print Map">🖨️</button>
  </div>
  <div class="map-tool-group">
    <button class="map-btn" id="btnEmergency" title="Emergency Mapping">🚨</button>
    <button class="map-btn" id="btnLayers"    title="Map Layers">🗂️</button>
    <button class="map-btn" id="btnPanel"     title="Toggle Side Panel">▶️</button>
  </div>
</div>

<!-- Proximity active banner (click to cancel) -->
<div id="proximity-status" onclick="exitProximityMode()" title="Click to exit proximity mode">
  ⭕ Proximity Mode ON — click map to analyze · click here to cancel
</div>

<!-- Legend -->
<div id="legend">
  <div class="legend-title">Legend</div>
  <div class="legend-item"><div class="legend-dot" style="background:#00d4ff;"></div> Active Meter</div>
  <div class="legend-item"><div class="legend-dot" style="background:#ff4d6d;"></div> Disconnected</div>
  <div class="legend-item"><div class="legend-dot" style="background:#ffb800;"></div> Infrastructure</div>
  <div class="legend-item"><div class="legend-dot" style="background:#00c896;"></div> Pump Station</div>
  <div class="legend-item"><div class="legend-line" style="background:#0057ff;"></div> Pipeline (Active)</div>
  <div class="legend-item"><div class="legend-line" style="background:#ff4d6d;"></div> Pipeline (Rehab)</div>
  <div class="legend-item">
    <div class="legend-line" style="background:#00d4ff;height:3px;border-radius:2px;"></div>
    Polomolok Boundary
  </div>
  <div class="legend-item"><div class="legend-dot" style="background:#8b00ff;border:2px solid #fff;"></div> Emergency</div>
  <div class="legend-item legend-prox" id="legendProx">
    <div style="width:20px;height:2px;border:1.5px dashed #ffb800;border-radius:2px;flex-shrink:0;"></div> Proximity Radius
  </div>
</div>

<!-- Coordinate display -->
<div id="coord-display">Lat: — &nbsp;Lng: —</div>

<!-- Coordinate Input Panel -->
<div id="coord-input-panel">
  <div style="font-size:12px;font-weight:600;margin-bottom:10px;color:var(--accent);">Go to Coordinates</div>
  <input type="text" id="coordLat" class="form-control" placeholder="Latitude"  style="margin-bottom:6px;">
  <input type="text" id="coordLng" class="form-control" placeholder="Longitude" style="margin-bottom:8px;">
  <button class="btn btn-primary" style="width:100%;padding:7px;" onclick="goToCoords()">Go</button>
</div>

<!-- Side Panel -->
<div id="map-panel">
  <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;">
    <span style="font-weight:600;font-size:14px;" id="panelTitle">Feature Info</span>
    <button onclick="document.getElementById('map-panel').classList.remove('open')"
            style="margin-left:auto;background:none;border:none;cursor:pointer;color:var(--muted);">✕</button>
  </div>
  <div id="panel-content">
    <p style="color:var(--muted);font-size:13px;text-align:center;padding:24px 0;">
      Click a feature on the map to view details
    </p>
  </div>
</div>

<!-- Map Layers Modal -->
<div class="modal-overlay" id="layersModal">
  <div class="modal" style="max-width:360px;">
    <div class="modal-header">
      <span class="modal-title">Map Layers</span>
      <button class="modal-close" onclick="closeModal('layersModal')">✕</button>
    </div>
    <div>
      <div style="font-size:12px;color:var(--muted);margin-bottom:10px;">Base Map</div>
      <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:16px;">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="radio" name="basemap" value="osm" checked> OpenStreetMap</label>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="radio" name="basemap" value="satellite"> Satellite (Esri)</label>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="radio" name="basemap" value="topo"> Topographic</label>
      </div>
      <div style="font-size:12px;color:var(--muted);margin-bottom:10px;">Overlays</div>
      <div style="display:flex;flex-direction:column;gap:6px;">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="checkbox" id="layConsumers" checked> Water Meters / Consumers</label>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="checkbox" id="layPipelines" checked> Pipelines</label>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="checkbox" id="layInfra" checked> Infrastructure</label>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="checkbox" id="layParcels"> Parcel Borders</label>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="checkbox" id="layHeatmap"> Consumption Heatmap</label>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="checkbox" id="layEmergency" checked> Emergency Incidents</label>
      </div>
    </div>
  </div>
</div>

<!-- Emergency Modal -->
<div class="modal-overlay" id="emergencyModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">🚨 Report Emergency</span>
      <button class="modal-close" onclick="closeModal('emergencyModal')">✕</button>
    </div>
    <form id="emergencyForm">
      <div class="form-group">
        <label class="form-label">Type</label>
        <select class="form-control" name="type" required>
          <option value="Pipeline Break">Pipeline Break</option>
          <option value="Pump Failure">Pump Failure</option>
          <option value="Contamination">Contamination</option>
          <option value="Flood">Flood</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Title</label>
        <input type="text" class="form-control" name="title" required placeholder="Brief description">
      </div>
      <div class="form-group">
        <label class="form-label">Severity</label>
        <select class="form-control" name="severity" required>
          <option value="Critical">Critical</option>
          <option value="High">High</option>
          <option value="Medium">Medium</option>
          <option value="Low">Low</option>
        </select>
      </div>
      <div class="grid grid-2 gap-2">
        <div class="form-group">
          <label class="form-label">Latitude</label>
          <input type="number" class="form-control" name="latitude" id="eLat" step="any" required>
        </div>
        <div class="form-group">
          <label class="form-label">Longitude</label>
          <input type="number" class="form-control" name="longitude" id="eLng" step="any" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Location Text</label>
        <input type="text" class="form-control" name="location_text" placeholder="Street, barangay">
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-control" name="description" placeholder="Details..."></textarea>
      </div>
      <div style="display:flex;gap:8px;margin-top:4px;">
        <button type="submit" class="btn btn-danger"    style="flex:1;">Report Emergency</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('emergencyModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
// ══════════════════════════════════════════════════════════════
// MAP INITIALIZATION
// ══════════════════════════════════════════════════════════════
const DEFAULT_CENTER = [6.223262, 125.072111];
const map = L.map('map', {
  center:      DEFAULT_CENTER,
  zoom:        14,
  zoomControl: false,
});

// ── Tile layers ──────────────────────────────────────────────
const tileLayers = {
  osm: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 20
  }),
  satellite: L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    { attribution: '© Esri', maxZoom: 19 }
  ),
  topo: L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenTopoMap', maxZoom: 17
  }),
};
tileLayers.osm.addTo(map);
let currentBasemap = 'osm';

// ── Data layer groups ─────────────────────────────────────────
const layerGroups = {
  consumers: L.layerGroup().addTo(map),
  pipelines: L.layerGroup().addTo(map),
  infra:     L.layerGroup().addTo(map),
  parcels:   L.layerGroup(),
  heatmap:   null,
  emergency: L.layerGroup().addTo(map),
};
let boundaryLayer  = null;
let boundaryReady  = false;

fetch('../../assets/geojson/polomolokboundary.geojson')
    .then(r => r.json())
    .then(geojson => {
        boundaryLayer = L.geoJSON(geojson, {
            style: {
                color:       '#00d4ff',
                weight:      3,
                opacity:     0.85,
                fillColor:   '#00d4ff',
                fillOpacity: 0.04,
            },
            onEachFeature(feature, layer) {
                layer.on({
                    mouseover() {
                        layer.setStyle({ weight: 5, color: '#ffffff', fillOpacity: 0.08 });
                    },
                    mouseout() {
                        boundaryLayer.resetStyle(layer);
                    },
                });
            },
        });
        boundaryReady = true;
    })
    .catch(err => console.warn('Boundary load failed:', err));

// ══════════════════════════════════════════════════════════════
// COORDINATE DISPLAY
// ══════════════════════════════════════════════════════════════
map.on('mousemove', (e) => {
  document.getElementById('coord-display').textContent =
    `Lat: ${e.latlng.lat.toFixed(6)}  Lng: ${e.latlng.lng.toFixed(6)}`;
});

// ══════════════════════════════════════════════════════════════
// ICON FACTORIES
// ══════════════════════════════════════════════════════════════
function makeIcon(color, size = 10) {
  return L.divIcon({
    className: '',
    html: `<div style="width:${size}px;height:${size}px;background:${color};border:2px solid rgba(255,255,255,0.8);border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,0.4);"></div>`,
    iconSize:   [size, size],
    iconAnchor: [size / 2, size / 2],
  });
}
function makeInfraIcon(emoji) {
  return L.divIcon({
    className: '',
    html: `<div style="font-size:18px;line-height:1;filter:drop-shadow(0 2px 3px rgba(0,0,0,0.5));">${emoji}</div>`,
    iconSize:   [22, 22],
    iconAnchor: [11, 11],
  });
}
function makeEmergencyIcon(severity) {
  const colors = { Critical: '#ff4d6d', High: '#ff8c00', Medium: '#ffb800', Low: '#00d4ff' };
  const color  = colors[severity] || '#ff4d6d';
  return L.divIcon({
    className: '',
    html: `<div style="width:18px;height:18px;background:${color};border:3px solid #fff;border-radius:50%;box-shadow:0 0 12px ${color},0 2px 6px rgba(0,0,0,0.4);animation:emergencyPulse 1.5s infinite;"></div>`,
    iconSize:   [18, 18],
    iconAnchor: [9, 9],
  });
}

// ══════════════════════════════════════════════════════════════
// DATA LOADERS
// ══════════════════════════════════════════════════════════════
async function loadConsumers() {
  layerGroups.consumers.clearLayers();
  try {
    const r = await apiGet('gis.php', { action: 'get_consumers_geo' });
    (r.data || []).forEach(c => {
      if (!c.latitude || !c.longitude) return;
      const icon = makeIcon(c.status === 'Active' ? '#00d4ff' : '#ff4d6d');
      const m    = L.marker([parseFloat(c.latitude), parseFloat(c.longitude)], { icon });

      m.bindPopup(buildConsumerPopup(c), { closeButton: true, autoClose: false });
      m.on('click', () => openFeaturePanel('consumer', c));
      layerGroups.consumers.addLayer(m);
    });
  } catch (e) { console.warn('Consumers load failed', e); }
}
t
function buildConsumerPopup(c) {
  const safe = JSON.stringify(c).replace(/"/g, '&quot;');
  return `
    <div class="popup-title">${c.name}</div>
    <div class="popup-row"><span>Account ID</span><span>${c.account_id}</span></div>
    <div class="popup-row"><span>Type</span><span>${c.type}</span></div>
    <div class="popup-row"><span>Status</span><span>${c.status}</span></div>
    <div class="popup-row"><span>Barangay</span><span>${c.barangay || '—'}</span></div>
    ${c.latest_consumption ? `<div class="popup-row"><span>Last Cons.</span><span>${parseFloat(c.latest_consumption).toFixed(2)} m³</span></div>` : ''}
    <div class="popup-actions">
      <button class="popup-btn popup-btn-info" onclick='openFeaturePanel("consumer",${safe})'>Details</button>
    </div>`;
}

async function loadPipelines() {
  layerGroups.pipelines.clearLayers();
  try {
    const r = await apiGet('gis.php', { action: 'get_pipelines' });
    (r.data || []).forEach(p => {
      if (!p.path_geojson) return;
      const colors = { active: '#0057ff', inactive: '#4a5a72', rehabilitation: '#ff4d6d', new: '#00c896' };
      const color  = colors[p.status] || '#0057ff';
      const line   = L.geoJSON(p.path_geojson, { style: { color, weight: 3, opacity: 0.85 } });
      const safeObj = JSON.stringify({ id: p.id, name: p.name, material: p.material, status: p.status }).replace(/"/g, '&quot;');
      const popupHtml = `
        <div class="popup-title">${p.name || 'Pipeline'}</div>
        <div class="popup-row"><span>Material</span><span>${p.material}</span></div>
        <div class="popup-row"><span>Diameter</span><span>${p.diameter_mm || '?'} mm</span></div>
        <div class="popup-row"><span>Status</span><span>${p.status}</span></div>
        <div class="popup-row"><span>Barangay</span><span>${p.barangay || '—'}</span></div>
        <div class="popup-actions">
          <button class="popup-btn popup-btn-info" onclick='openFeaturePanel("pipeline",${safeObj})'>Details &amp; History</button>
        </div>`;
      line.bindPopup(popupHtml, { closeButton: true, autoClose: false });
      layerGroups.pipelines.addLayer(line);
    });
  } catch (e) { console.warn('Pipelines load failed', e); }
}

async function loadInfrastructure() {
  layerGroups.infra.clearLayers();
  try {
    const r = await apiGet('gis.php', { action: 'get_infrastructure' });
    const emojis = {
      pumping_station: '🏗️', reservoir: '🗄️', valve: '🔧',
      hydrant: '🚒', blowoff: '💨', meter_chamber: '📊', other: '📌',
    };
    (r.data || []).forEach(i => {
      if (!i.latitude || !i.longitude) return;
      const icon = makeInfraIcon(emojis[i.type] || '📌');
      const m    = L.marker([parseFloat(i.latitude), parseFloat(i.longitude)], { icon });
      const popupHtml = `
        <div class="popup-title">${i.name || i.type}</div>
        <div class="popup-row"><span>Type</span><span>${i.type.replace('_', ' ')}</span></div>
        <div class="popup-row"><span>Status</span><span>${i.status}</span></div>
        <div class="popup-row"><span>Barangay</span><span>${i.barangay || '—'}</span></div>
        ${i.installation_date ? `<div class="popup-row"><span>Installed</span><span>${i.installation_date}</span></div>` : ''}`;
      m.bindPopup(popupHtml, { closeButton: true, autoClose: false });
      m.on('click', () => openFeaturePanel('infrastructure', i));
      layerGroups.infra.addLayer(m);
    });
  } catch (e) { console.warn('Infrastructure load failed', e); }
}

async function loadParcels() {
  layerGroups.parcels.clearLayers();
  try {
    const r = await apiGet('gis.php', { action: 'get_parcels' });
    (r.data || []).forEach(p => {
      if (!p.boundary_geojson) return;
      const poly = L.geoJSON(p.boundary_geojson, {
        style: { color: '#ffb800', fillColor: 'rgba(255,184,0,0.08)', weight: 2, dashArray: '6,4' }
      });
      poly.bindPopup(`
        <div class="popup-title">${p.parcel_code || 'Parcel'}</div>
        <div class="popup-row"><span>Owner</span><span>${p.owner_name || '—'}</span></div>
        <div class="popup-row"><span>Area</span><span>${p.area_sqm ? p.area_sqm + ' m²' : '—'}</span></div>`,
        { closeButton: true, autoClose: false });
      layerGroups.parcels.addLayer(poly);
    });
  } catch (e) { console.warn('Parcels load failed', e); }
}

async function loadEmergencies() {
  layerGroups.emergency.clearLayers();
  try {
    const r = await apiGet('gis.php', { action: 'emergency_incidents' });
    (r.data || []).forEach(inc => {
      if (!inc.latitude || !inc.longitude) return;
      const icon = makeEmergencyIcon(inc.severity);
      const m    = L.marker([parseFloat(inc.latitude), parseFloat(inc.longitude)], { icon });
      const popupHtml = `
        <div class="popup-title">🚨 ${inc.title}</div>
        <div class="popup-row"><span>Type</span><span>${inc.type}</span></div>
        <div class="popup-row"><span>Severity</span><span>${inc.severity}</span></div>
        <div class="popup-row"><span>Status</span><span>${inc.status}</span></div>
        <div class="popup-row"><span>Reported</span><span>${new Date(inc.created_at).toLocaleString()}</span></div>
        <div class="popup-actions">
          <button class="popup-btn popup-btn-warn" onclick="resolveEmergency(${inc.id})">Mark Resolved</button>
        </div>`;
      m.bindPopup(popupHtml, { closeButton: true, autoClose: false });
      layerGroups.emergency.addLayer(m);
    });
  } catch (e) { console.warn('Emergency load failed', e); }
}

async function loadHeatmap() {
  if (layerGroups.heatmap) { map.removeLayer(layerGroups.heatmap); layerGroups.heatmap = null; }
  try {
    const r = await apiGet('gis.php', { action: 'heatmap_data' });
    if (r.points && r.points.length > 0) {
      layerGroups.heatmap = L.heatLayer(r.points, { radius: 25, blur: 20, maxZoom: 17 });
      layerGroups.heatmap.addTo(map);
    } else {
      showToast('No consumption data for heatmap', 'info');
    }
  } catch { showToast('Heatmap data unavailable', 'error'); }
}

// ══════════════════════════════════════════════════════════════
// PROXIMITY ANALYSIS
// ══════════════════════════════════════════════════════════════
const proximityLayer = L.layerGroup().addTo(map);
let proximityMode    = false;

function enterProximityMode() {
  proximityMode = true;
  document.getElementById('btnProximity').classList.add('proximity-active');
  document.getElementById('proximity-status').classList.add('show');
  document.getElementById('legendProx').classList.add('visible');
  map.getContainer().style.cursor = 'crosshair';
  showToast('Click anywhere on the map to run proximity analysis', 'info');
}

function exitProximityMode() {
  proximityMode = false;
  document.getElementById('btnProximity').classList.remove('proximity-active');
  document.getElementById('proximity-status').classList.remove('show');
  document.getElementById('legendProx').classList.remove('visible');
  map.getContainer().style.cursor = '';
  proximityLayer.clearLayers();

  const title = document.getElementById('panelTitle').textContent;
  if (title === 'Proximity Analysis') {
    document.getElementById('panel-content').innerHTML =
      '<p style="color:var(--muted);font-size:13px;text-align:center;padding:24px 0;">Click a feature on the map to view details</p>';
    document.getElementById('panelTitle').textContent = 'Feature Info';
  }
}

document.getElementById('btnPanel').addEventListener('click', () => {
    document.getElementById('map-panel').classList.toggle('open');
    setTimeout(() => map.invalidateSize(), 310);
});

document.getElementById('btnProximity').addEventListener('click', () => {
  if (proximityMode) {
    exitProximityMode();
    showToast('Proximity mode cancelled', 'info');
  } else {
    enterProximityMode();
  }
});

map.on('click', async (e) => {
  if (!proximityMode) return;

  proximityLayer.clearLayers();

  const { lat, lng } = e.latlng;

  const centerPin = L.circleMarker([lat, lng], {
    radius: 7, color: '#fff', weight: 2,
    fillColor: '#ffb800', fillOpacity: 1,
  });
  centerPin.bindTooltip('Analysis center', { permanent: false });
  proximityLayer.addLayer(centerPin);

  const ring = L.circle([lat, lng], {
    radius:      500,
    color:       '#ffb800',
    weight:      2,
    dashArray:   '8,6',
    fillColor:   '#ffb800',
    fillOpacity: 0.05,
    className:   'proximity-ring',
  });
  proximityLayer.addLayer(ring);

  const r = await apiGet('gis.php', { action: 'proximity_analysis', lat, lng, radius: 0.5 });

  (r.infrastructure || []).forEach(i => {
    const m = L.circleMarker([parseFloat(i.latitude), parseFloat(i.longitude)], {
      radius: 6, color: '#ffb800', weight: 2, fillColor: '#ffb800', fillOpacity: 0.7,
    });
    m.bindTooltip(`${i.name || i.type} — ${(i.distance_km * 1000).toFixed(0)} m`, { sticky: true });
    proximityLayer.addLayer(m);
  });

  (r.consumers || []).forEach(c => {
    const m = L.circleMarker([parseFloat(c.latitude), parseFloat(c.longitude)], {
      radius: 5, color: '#00d4ff', weight: 1.5, fillColor: '#00d4ff', fillOpacity: 0.6,
    });
    m.bindTooltip(`${c.name} — ${(c.distance_km * 1000).toFixed(0)} m`, { sticky: true });
    proximityLayer.addLayer(m);
  });

  const panel   = document.getElementById('map-panel');
  const content = document.getElementById('panel-content');
  panel.classList.add('open');
  document.getElementById('panelTitle').textContent = 'Proximity Analysis';
  content.innerHTML = `
    <div style="background:rgba(255,184,0,.1);border:1px solid rgba(255,184,0,.3);border-radius:8px;padding:10px 12px;margin-bottom:12px;font-size:12px;color:var(--warn)">
      ⭕ 500 m radius · ${lat.toFixed(5)}, ${lng.toFixed(5)}
      <button onclick="exitProximityMode()" style="float:right;background:none;border:none;cursor:pointer;color:var(--muted);font-size:14px" title="Close">✕</button>
    </div>
    <div class="card" style="margin-bottom:12px;padding:12px">
      <div class="card-title">Infrastructure (${r.infrastructure?.length || 0})</div>
      ${(r.infrastructure || []).slice(0, 8).map(i =>
        `<div style="font-size:12px;padding:5px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between">
          <span>${i.name || i.type}</span>
          <span style="color:var(--warn)">${(i.distance_km * 1000).toFixed(0)} m</span>
        </div>`).join('') || '<p style="font-size:12px;color:var(--muted)">None found</p>'}
    </div>
    <div class="card" style="padding:12px">
      <div class="card-title">Consumers (${r.consumers?.length || 0})</div>
      ${(r.consumers || []).slice(0, 8).map(c =>
        `<div style="font-size:12px;padding:5px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between">
          <span>${c.name}</span>
          <span style="color:var(--accent)">${(c.distance_km * 1000).toFixed(0)} m</span>
        </div>`).join('') || '<p style="font-size:12px;color:var(--muted)">None found</p>'}
    </div>`;
});

// ══════════════════════════════════════════════════════════════
// MEASURE TOOL
// ══════════════════════════════════════════════════════════════
let measuring         = false;
let measurePoints     = [];
let measureLabel      = null;
const measureLayerGrp = L.layerGroup().addTo(map);

function toggleMeasure() {
  measuring = !measuring;
  document.getElementById('btnMeasure').classList.toggle('active', measuring);
  if (!measuring) { measureLayerGrp.clearLayers(); measurePoints = []; measureLabel = null; }
  showToast(measuring ? 'Click on the map to measure distance' : 'Measurement stopped', 'info');
}

map.on('click', (e) => {
  if (!measuring || proximityMode) return;
  measurePoints.push(e.latlng);
  L.circleMarker(e.latlng, { radius: 5, color: '#00d4ff', fillColor: '#00d4ff', fillOpacity: 1 })
   .addTo(measureLayerGrp);
  if (measurePoints.length > 1) {
    L.polyline([measurePoints[measurePoints.length - 2], e.latlng],
      { color: '#00d4ff', weight: 2, dashArray: '6,4' }).addTo(measureLayerGrp);
    let total = 0;
    for (let i = 1; i < measurePoints.length; i++) total += measurePoints[i-1].distanceTo(measurePoints[i]);
    if (measureLabel) measureLayerGrp.removeLayer(measureLabel);
    const distText = total < 1000 ? total.toFixed(1) + ' m' : (total / 1000).toFixed(3) + ' km';
    measureLabel = L.marker(e.latlng, {
      icon: L.divIcon({ className: '', html: `<div class="measure-label">${distText}</div>`, iconAnchor: [-4, 10] })
    });
    measureLayerGrp.addLayer(measureLabel);
  }
});

// ══════════════════════════════════════════════════════════════
// FEATURE DETAIL PANEL
// ══════════════════════════════════════════════════════════════
async function openFeaturePanel(type, data) {
  const panel   = document.getElementById('map-panel');
  const content = document.getElementById('panel-content');
  panel.classList.add('open');
  document.getElementById('panelTitle').textContent =
    type === 'consumer' ? 'Consumer Details' :
    type === 'pipeline' ? 'Pipeline Details' : 'Infrastructure Details';

  if (type === 'consumer') {
    content.innerHTML = `
      <div style="margin-bottom:16px;">
        <div style="font-size:16px;font-weight:700;">${data.name}</div>
        <div style="font-size:12px;color:var(--text2);">${data.account_id} • ${data.type}</div>
      </div>
      <div class="card" style="margin-bottom:12px;">
        ${row('Status', `<span class="badge badge-${(data.status||'').toLowerCase()}">${data.status}</span>`)}
        ${row('Barangay', data.barangay)}
        ${row('Last Consumption', data.latest_consumption ? parseFloat(data.latest_consumption).toFixed(2) + ' m³' : '—')}
      </div>
      <button class="btn btn-primary" style="width:100%;margin-bottom:8px;"
        onclick="window.location.href='consumers.php?id=${data.id}'">View Full Profile</button>
      <button class="btn btn-secondary" style="width:100%;"
        onclick="predictConsumption(${data.id})">Predict Consumption</button>
      <div id="predictionResult" style="margin-top:12px;"></div>`;

  } else if (type === 'pipeline') {
    content.innerHTML = `
      <div style="margin-bottom:16px;">
        <div style="font-size:16px;font-weight:700;">${data.name || 'Pipeline'}</div>
        <div style="font-size:12px;color:var(--text2);">${data.material}</div>
      </div>
      <div class="card" style="margin-bottom:12px;">
        ${row('Status', `<span class="badge badge-${data.status}">${data.status}</span>`)}
        ${row('Diameter', data.diameter_mm ? data.diameter_mm + ' mm' : '—')}
      </div>
      <button class="btn btn-primary" style="width:100%;" onclick="loadPipelineHistory(${data.id})">View Change History</button>
      <div id="pipelineHistory" style="margin-top:12px;"></div>`;

  } else if (type === 'infrastructure') {
    content.innerHTML = `
      <div style="margin-bottom:16px;">
        <div style="font-size:16px;font-weight:700;">${data.name || data.type}</div>
        <div style="font-size:12px;color:var(--text2);">${(data.type || '').replace('_', ' ')}</div>
      </div>
      <div class="card" style="margin-bottom:12px;">
        ${row('Status', `<span class="badge badge-${data.status}">${data.status}</span>`)}
        ${row('Barangay', data.barangay)}
        ${row('Installed', data.installation_date || '—')}
        ${row('Last Inspection', data.last_inspection || '—')}
        ${row('Notes', data.notes || '—')}
      </div>
      <a href="infrastructure-detail.php?id=${data.id}" class="btn btn-primary" style="width:100%;display:block;text-align:center;text-decoration:none;">
        View Full History
      </a>`;
  }
}

function row(label, value) {
  return `<div class="popup-row"><span>${label}</span><span>${value || '—'}</span></div>`;
}

async function predictConsumption(consumerId) {
  const el = document.getElementById('predictionResult');
  el.innerHTML = '<p style="color:var(--muted);font-size:12px;">Loading...</p>';
  try {
    const r = await apiGet('consumer.php', { action: 'predict_consumption', consumer_id: consumerId });
    if (r.error) { el.innerHTML = `<p style="color:var(--danger);font-size:12px;">${r.error}</p>`; return; }
    el.innerHTML = `
      <div class="card">
        <div class="card-title">Consumption Prediction</div>
        ${row('Next Month Est.', r.predicted_m3 + ' m³')}
        ${row('Avg Last 3 Mo.', r.avg_last_3_months + ' m³')}
        ${row('Trend', r.trend)}
      </div>`;
  } catch { el.innerHTML = '<p style="color:var(--danger);font-size:12px;">Prediction failed</p>'; }
}

async function loadPipelineHistory(id) {
  const el = document.getElementById('pipelineHistory');
  el.innerHTML = '<p style="font-size:12px;color:var(--muted);">Loading history...</p>';
  const r = await apiGet('gis.php', { action: 'pipeline_history', pipeline_id: id });
  if (!r.data?.length) { el.innerHTML = '<p style="font-size:12px;color:var(--muted);">No history</p>'; return; }
  el.innerHTML = '<div class="card"><div class="card-title">History</div>' +
    r.data.map(h => `
      <div style="border-bottom:1px solid var(--border);padding:8px 0;font-size:12px;">
        <div style="color:var(--text);font-weight:600;">${h.change_type}</div>
        <div style="color:var(--text2);">${h.changed_by_name || 'System'} • ${new Date(h.changed_at).toLocaleDateString()}</div>
        ${h.reason ? `<div style="color:var(--muted);margin-top:2px;">${h.reason}</div>` : ''}
      </div>`).join('') + '</div>';
}

// ══════════════════════════════════════════════════════════════
// RESOLVE EMERGENCY
// ══════════════════════════════════════════════════════════════
async function resolveEmergency(id) {
  const notes = prompt('Resolution notes (optional):') || '';
  const r = await apiPost('gis.php', { action: 'resolve_emergency', id, notes });
  if (r.success) { showToast('Emergency resolved', 'success'); loadEmergencies(); }
  else showToast('Failed to resolve', 'error');
}

// ══════════════════════════════════════════════════════════════
// GO TO COORDINATES
// ══════════════════════════════════════════════════════════════
function goToCoords() {
  const lat = parseFloat(document.getElementById('coordLat').value);
  const lng = parseFloat(document.getElementById('coordLng').value);
  if (isNaN(lat) || isNaN(lng)) { showToast('Invalid coordinates', 'error'); return; }
  map.flyTo([lat, lng], 17, { duration: 1.5 });
  L.circleMarker([lat, lng], { radius: 8, color: '#00d4ff', fillColor: '#00d4ff', fillOpacity: 0.7 }).addTo(map);
  document.getElementById('coord-input-panel').classList.remove('show');
}

// ══════════════════════════════════════════════════════════════
// TOOLBAR BUTTON BINDINGS
// ══════════════════════════════════════════════════════════════
document.getElementById('btnHome').addEventListener('click',    () => map.flyTo(DEFAULT_CENTER, 14));
document.getElementById('btnZoomIn').addEventListener('click',  () => map.zoomIn());
document.getElementById('btnZoomOut').addEventListener('click', () => map.zoomOut());
document.getElementById('btnMeasure').addEventListener('click', toggleMeasure);
document.getElementById('btnCoordInput').addEventListener('click', () =>
  document.getElementById('coord-input-panel').classList.toggle('show'));
document.getElementById('btnHeatmap').addEventListener('click', async () => {
  if (layerGroups.heatmap) {
    map.removeLayer(layerGroups.heatmap); layerGroups.heatmap = null;
    document.getElementById('btnHeatmap').classList.remove('active');
  } else {
    await loadHeatmap();
    if (layerGroups.heatmap) document.getElementById('btnHeatmap').classList.add('active');
  }
});
document.getElementById('btnPrint').addEventListener('click', () => window.print());
document.getElementById('btnLayers').addEventListener('click', () => openModal('layersModal'));
document.getElementById('btnEmergency').addEventListener('click', () => {
  const c = map.getCenter();
  document.getElementById('eLat').value = c.lat.toFixed(6);
  document.getElementById('eLng').value = c.lng.toFixed(6);
  openModal('emergencyModal');
});

// ── Layer toggle checkboxes ───────────────────────────────────
document.getElementById('layConsumers').addEventListener('change', e =>
  e.target.checked ? layerGroups.consumers.addTo(map) : map.removeLayer(layerGroups.consumers));
document.getElementById('layPipelines').addEventListener('change', e =>
  e.target.checked ? layerGroups.pipelines.addTo(map) : map.removeLayer(layerGroups.pipelines));
document.getElementById('layInfra').addEventListener('change', e =>
  e.target.checked ? layerGroups.infra.addTo(map) : map.removeLayer(layerGroups.infra));
document.getElementById('layParcels').addEventListener('change', e => {
    if (!boundaryReady) {
        showToast('Boundary still loading, please wait', 'warn');
        e.target.checked = false;
        return;
    }
    if (e.target.checked) {
        if (!map.hasLayer(boundaryLayer)) {
            boundaryLayer.addTo(map);
        }
    } else {
        if (map.hasLayer(boundaryLayer)) {
            map.removeLayer(boundaryLayer);
        }
    }
});
document.getElementById('layHeatmap').addEventListener('change', async e => {
  if (e.target.checked) await loadHeatmap();
  else if (layerGroups.heatmap) { map.removeLayer(layerGroups.heatmap); layerGroups.heatmap = null; }
});
document.getElementById('layEmergency').addEventListener('change', e =>
  e.target.checked ? layerGroups.emergency.addTo(map) : map.removeLayer(layerGroups.emergency));

// ── Basemap switcher ──────────────────────────────────────────
document.querySelectorAll('input[name="basemap"]').forEach(radio => {
  radio.addEventListener('change', () => {
    map.removeLayer(tileLayers[currentBasemap]);
    currentBasemap = radio.value;
    tileLayers[currentBasemap].addTo(map);
    closeModal('layersModal');
  });
});

// ── Emergency form submit ─────────────────────────────────────
document.getElementById('emergencyForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const data  = Object.fromEntries(new FormData(e.target));
  data.action = 'add_emergency';
  const r = await apiJson('gis.php', data);
  if (r.success) {
    showToast('Emergency reported!', 'success');
    closeModal('emergencyModal');
    e.target.reset();
    loadEmergencies();
  } else {
    showToast(r.error || 'Failed to report emergency', 'error');
  }
});

// ── Right-click → emergency form ──────────────────────────────
map.on('contextmenu', (e) => {
  if (proximityMode) return;
  document.getElementById('eLat').value = e.latlng.lat.toFixed(6);
  document.getElementById('eLng').value = e.latlng.lng.toFixed(6);
  openModal('emergencyModal');
});

// ── Initial data load ─────────────────────────────────────────
Promise.all([loadConsumers(), loadPipelines(), loadInfrastructure(), loadEmergencies()]);

// ── Print styles ──────────────────────────────────────────────
document.head.insertAdjacentHTML('beforeend', `<style>
  @keyframes emergencyPulse {
    0%, 100% { box-shadow: 0 0 8px currentColor; }
    50%       { box-shadow: 0 0 20px currentColor; }
  }
  @media print {
    #map-toolbar, #legend, #coord-display,
    #coord-input-panel, #map-panel, #proximity-status,
    .sidebar, .topbar, .toast-container { display: none !important; }
    .main { margin: 0 !important; }
    #map  { height: 100vh !important; }
  }
</style>`);
</script>