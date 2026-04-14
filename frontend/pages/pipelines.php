<?php
$pageTitle = 'Pipeline Classification';
require_once 'layout.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>

<style>
/* ── Tab system ─────────────────────────────────────────────── */
.pc-tabs { display:flex; gap:2px; background:var(--surface2); border:1px solid var(--border); border-radius:12px; padding:4px; margin-bottom:18px; flex-wrap:wrap; }
.pc-tab { padding:8px 20px; border-radius:9px; border:none; background:none; color:var(--text2); font-family:'Sora',sans-serif; font-size:13px; font-weight:500; cursor:pointer; transition:all .15s; white-space:nowrap; }
.pc-tab:hover { background:rgba(255,255,255,.04); color:var(--text); }
.pc-tab.active { background:linear-gradient(135deg,rgba(0,87,255,.25),rgba(0,212,255,.15)); color:var(--accent); border:1px solid rgba(0,212,255,.2); }
.pc-pane { display:none; }
.pc-pane.active { display:block; }

/* ── Stats ──────────────────────────────────────────────────── */
.stat-strip { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:10px; margin-bottom:16px; }
.stat-chip { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:14px 16px; text-align:center; transition:border-color .15s; }
.stat-chip:hover { border-color:var(--accent); }
.stat-chip .val { font-size:26px; font-weight:700; letter-spacing:-.03em; }
.stat-chip .lbl { font-size:10px; color:var(--muted); text-transform:uppercase; letter-spacing:.08em; margin-top:3px; }

/* ── Pipeline list item ─────────────────────────────────────── */
.pl-item { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:14px 16px; margin-bottom:8px; cursor:pointer; transition:border-color .15s,box-shadow .15s; display:grid; grid-template-columns:14px 1fr auto; align-items:start; gap:12px; }
.pl-item:hover { border-color:var(--accent); box-shadow:0 0 0 1px rgba(0,212,255,.08); }
.pl-item.flagged { border-left:3px solid var(--danger); }

/* ── Type indicator ─────────────────────────────────────────── */
.type-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; margin-top:5px; }

/* ── Badges ─────────────────────────────────────────────────── */
.b-chip { display:inline-block; padding:1px 7px; border-radius:10px; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; }
.b-active       { background:rgba(0,200,150,.15); color:var(--accent3); border:1px solid rgba(0,200,150,.2); }
.b-inactive     { background:rgba(100,100,120,.2); color:var(--muted); }
.b-rehabilitation { background:rgba(255,184,0,.15); color:var(--warn); }
.b-new          { background:rgba(0,87,255,.15); color:var(--accent2); }
.b-flagged      { background:rgba(255,77,109,.15); color:var(--danger); border:1px solid rgba(255,77,109,.2); }
.b-High         { background:rgba(255,140,0,.15); color:#ff8c00; }
.b-Very-High    { background:rgba(220,38,38,.15); color:#dc2626; }
.b-Medium       { background:rgba(0,87,255,.12); color:var(--accent2); }
.b-Low          { background:rgba(0,200,150,.12); color:var(--accent3); }

/* ── Condition bar ──────────────────────────────────────────── */
.cond-bar { height:4px; background:var(--border); border-radius:2px; margin-top:6px; overflow:hidden; }
.cond-fill { height:100%; border-radius:2px; transition:width .5s; }

/* ── Map ────────────────────────────────────────────────────── */
#pipeline-map { height:520px; width:100%; border-radius:12px; overflow:hidden; border:1px solid var(--border); position:relative; z-index:0; }
#pipeline-map .leaflet-pane { z-index:200; }
#pipeline-map .leaflet-top, #pipeline-map .leaflet-bottom { z-index:300; }
.map-legend-panel { position:absolute; bottom:12px; left:12px; background:rgba(17,24,39,.95); border:1px solid var(--border); border-radius:10px; padding:10px 14px; font-size:11px; z-index:350; pointer-events:none; min-width:160px; }

/* ── History timeline ───────────────────────────────────────── */
.history-event { position:relative; padding:10px 10px 10px 34px; border-bottom:1px solid rgba(255,255,255,.04); font-size:13px; }
.history-event::before { content:''; position:absolute; left:12px; top:14px; width:8px; height:8px; border-radius:50%; background:var(--accent); box-shadow:0 0 6px var(--accent); }
.history-event.status_change::before { background:var(--warn); box-shadow:0 0 6px var(--warn); }
.history-event.material_change::before { background:var(--accent2); box-shadow:0 0 6px var(--accent2); }
.history-event.diameter_change::before { background:var(--accent3); box-shadow:0 0 6px var(--accent3); }
.history-event.other::before { background:var(--muted); box-shadow:none; }
.history-container { position:relative; }
.history-container::before { content:''; position:absolute; left:15px; top:0; bottom:0; width:1px; background:var(--border); }

/* ── Filter row ─────────────────────────────────────────────── */
.filter-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:14px; }

/* ── Risk score bar ─────────────────────────────────────────── */
.risk-bar { height:6px; background:var(--border); border-radius:3px; overflow:hidden; }
.risk-fill { height:100%; border-radius:3px; background:linear-gradient(90deg,var(--accent3),var(--warn),var(--danger)); }

/* ── Forecast cards ─────────────────────────────────────────── */
.forecast-card { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:12px 14px; margin-bottom:8px; }
.forecast-card.critical { border-left:3px solid var(--danger); }
.forecast-card.high     { border-left:3px solid #ea580c; }
.forecast-card.medium   { border-left:3px solid var(--warn); }

/* ── Form grids ─────────────────────────────────────────────── */
.form-2col { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.form-3col { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }
@media(max-width:768px){ .form-2col,.form-3col{ grid-template-columns:1fr; } }

/* ── Leaflet popup dark theme ───────────────────────────────── */
.leaflet-popup-content-wrapper { background:var(--surface)!important; border:1px solid var(--border)!important; border-radius:10px!important; color:var(--text)!important; font-family:'Sora',sans-serif!important; box-shadow:0 8px 24px rgba(0,0,0,.4)!important; }
.leaflet-popup-tip { background:var(--surface)!important; }
.leaflet-popup-content { margin:10px 14px!important; }

/* ── Detail panel ───────────────────────────────────────────── */
#detail-panel { position:fixed; top:0; right:0; width:460px; height:100vh; background:var(--bg); border-left:1px solid var(--border); overflow-y:auto; z-index:400; transform:translateX(100%); transition:transform .3s ease; box-shadow:-8px 0 32px rgba(0,0,0,.4); }
#detail-panel.open { transform:translateX(0); }
.detail-close { background:none; border:none; cursor:pointer; color:var(--muted); font-size:22px; line-height:1; transition:color .2s; }
.detail-close:hover { color:var(--danger); }

/* ── Chart wrap ─────────────────────────────────────────────── */
.chart-wrap { position:relative; height:220px; }
</style>

<main class="main">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px">
  <div>
    <h1 style="font-size:20px;font-weight:700;margin-bottom:2px">Pipeline Classification System</h1>
    <p style="font-size:13px;color:var(--muted)">Classify, track history, and forecast pipeline infrastructure</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if(in_array($_SESSION['role'],['Admin','Staff'])): ?>
    <button class="btn-primary" onclick="openAddModal()">+ Add Pipeline</button>
    <button class="btn-secondary" onclick="runAlerts()">⚡ Run Forecasting</button>
    <?php endif; ?>
    <button class="btn-secondary" onclick="exportData()">⬇ Export CSV</button>
  </div>
</div>

<!-- Stats strip -->
<div class="stat-strip">
  <div class="stat-chip"><div class="val" id="s-total">—</div><div class="lbl">Total</div></div>
  <div class="stat-chip"><div class="val" id="s-trans" style="color:var(--accent2)">—</div><div class="lbl">Transmission</div></div>
  <div class="stat-chip"><div class="val" id="s-dist"  style="color:var(--accent3)">—</div><div class="lbl">Distribution</div></div>
  <div class="stat-chip"><div class="val" id="s-svc"   style="color:var(--muted)">—</div><div class="lbl">Service Line</div></div>
  <div class="stat-chip"><div class="val" id="s-flag"  style="color:var(--danger)">—</div><div class="lbl">Flagged</div></div>
  <div class="stat-chip"><div class="val" id="s-aging" style="color:var(--warn)">—</div><div class="lbl">Aging 20+ yr</div></div>
  <div class="stat-chip"><div class="val" id="s-km"    style="color:var(--accent)">—</div><div class="lbl">Total km</div></div>
</div>

<!-- Tabs -->
<div class="pc-tabs">
  <button class="pc-tab active" onclick="switchTab('list',this)">📋 Pipeline List</button>
  <button class="pc-tab" onclick="switchTab('map',this)">🗺 GIS Map</button>
  <button class="pc-tab" onclick="switchTab('history',this)">📜 Change History</button>
  <button class="pc-tab" onclick="switchTab('forecast',this)">📈 Forecasting</button>
  <button class="pc-tab" onclick="switchTab('charts',this)">📊 Analytics</button>
</div>

<!-- TAB: LIST -->
<div class="pc-pane active" id="pane-list">
  <div class="filter-row">
    <input id="fSearch" class="filter-input" placeholder="Search name, barangay…" oninput="filterPipelines()" style="flex:1;max-width:260px">
    <select id="fType"     class="filter-input" onchange="filterPipelines()">
      <option value="">All Types</option>
      <option value="Transmission">Transmission</option>
      <option value="Distribution">Distribution</option>
      <option value="Service Line">Service Line</option>
    </select>
    <select id="fMaterial" class="filter-input" onchange="filterPipelines()">
      <option value="">All Materials</option>
      <option>PVC</option><option>HDPE</option><option>Steel</option>
      <option>GI</option><option>GIP</option><option>PE</option><option>other</option>
    </select>
    <select id="fStatus"   class="filter-input" onchange="filterPipelines()">
      <option value="">All Statuses</option>
      <option value="active">Active</option>
      <option value="inactive">Inactive</option>
      <option value="rehabilitation">Rehabilitation</option>
      <option value="new">New</option>
    </select>
    <select id="fPressure" class="filter-input" onchange="filterPipelines()">
      <option value="">All Pressure</option>
      <option value="Low">Low</option><option value="Medium">Medium</option>
      <option value="High">High</option><option value="Very High">Very High</option>
    </select>
    <label style="display:flex;align-items:center;gap:5px;font-size:13px;color:var(--text2);cursor:pointer">
      <input type="checkbox" id="fFlagged" onchange="filterPipelines()"> Flagged only
    </label>
  </div>
  <div id="pipelineList"><div class="spinner"></div></div>
</div>

<!-- TAB: MAP -->
<div class="pc-pane" id="pane-map">
  <div class="card" style="padding:12px;margin-bottom:12px">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <span style="font-size:13px;font-weight:600">Style by:</span>
      <button class="btn-secondary btn-sm active" id="styleType"      onclick="setMapStyle('type')">Type</button>
      <button class="btn-secondary btn-sm"        id="styleStatus"    onclick="setMapStyle('status')">Status</button>
      <button class="btn-secondary btn-sm"        id="stylePressure"  onclick="setMapStyle('pressure')">Pressure</button>
      <button class="btn-secondary btn-sm"        id="styleCondition" onclick="setMapStyle('condition')">Condition</button>
      <label style="margin-left:auto;display:flex;align-items:center;gap:5px;font-size:13px;cursor:pointer">
        <input type="checkbox" id="mapFlaggedOnly" onchange="renderMapPipelines()"> Flagged only
      </label>
    </div>
  </div>
  <div style="position:relative;margin-bottom:12px">
    <div id="pipeline-map"></div>
    <div class="map-legend-panel" id="mapLegend"></div>
  </div>
</div>

<!-- TAB: HISTORY -->
<div class="pc-pane" id="pane-history">
  <div class="filter-row">
    <select id="hPipeline" class="filter-input" style="flex:1;max-width:240px" onchange="loadHistory()">
      <option value="">All Pipelines</option>
    </select>
    <select id="hField" class="filter-input" onchange="loadHistory()">
      <option value="">All Fields</option>
      <option value="status">Status</option>
      <option value="material">Material</option>
      <option value="diameter_mm">Diameter</option>
      <option value="condition_rating">Condition</option>
      <option value="pressure_class">Pressure</option>
    </select>
    <input id="hDateFrom" type="date" class="filter-input" onchange="loadHistory()">
    <input id="hDateTo"   type="date" class="filter-input" onchange="loadHistory()">
    <?php if(in_array($_SESSION['role'],['Admin','Staff'])): ?>
    <button class="btn-secondary" onclick="openLogModal()">+ Manual Log</button>
    <?php endif; ?>
  </div>
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <div class="card-title" style="margin:0">Change History Timeline</div>
      <div id="historyCount" style="font-size:12px;color:var(--muted)"></div>
    </div>
    <div class="history-container" id="historyList"><div class="spinner"></div></div>
  </div>
</div>

<!-- TAB: FORECASTING -->
<div class="pc-pane" id="pane-forecast">
  <div id="forecastContent"><div class="spinner"></div></div>
</div>

<!-- TAB: CHARTS -->
<div class="pc-pane" id="pane-charts">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div class="card"><div class="card-title">Pipeline Type Distribution</div><div class="chart-wrap"><canvas id="chartType"></canvas></div></div>
    <div class="card"><div class="card-title">Material Breakdown</div><div class="chart-wrap"><canvas id="chartMaterial"></canvas></div></div>
    <div class="card"><div class="card-title">Status Overview</div><div class="chart-wrap"><canvas id="chartStatus"></canvas></div></div>
    <div class="card"><div class="card-title">Condition Rating</div><div class="chart-wrap"><canvas id="chartCondition"></canvas></div></div>
  </div>
  <div class="card" style="margin-top:16px">
    <div class="card-title">History Event Trend (12 months)</div>
    <div style="position:relative;height:180px"><canvas id="chartTrend"></canvas></div>
  </div>
</div>

</main>

<!-- ── Detail panel ──────────────────────────────────────────── -->
<div id="detail-panel">
  <div style="padding:16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;background:var(--surface)">
    <h3 id="detailTitle" style="font-size:15px;font-weight:700;flex:1"></h3>
    <button class="detail-close" onclick="closeDetail()">✕</button>
  </div>
  <div id="detailContent" style="padding:18px"></div>
</div>

<!-- ── Modal: Add/Edit Pipeline ──────────────────────────────── -->
<div id="mAddEdit" class="modal-overlay">
  <div class="modal-box" style="max-width:620px">
    <div class="modal-header"><h3 id="mAddEditTitle">Add Pipeline</h3><button onclick="closeModal('mAddEdit')">✕</button></div>
    <div style="padding:16px;overflow-y:auto;max-height:80vh">
      <input type="hidden" id="editId">
      <div style="margin-bottom:14px">
        <div style="font-size:11px;color:var(--accent);font-weight:600;text-transform:uppercase;letter-spacing:.08em;border-bottom:1px solid var(--border);padding-bottom:6px;margin-bottom:10px">Classification</div>
        <div class="form-3col">
          <div><label class="form-label">Pipeline Type *</label>
            <select id="ePType" class="form-input">
              <option value="Transmission">Transmission</option>
              <option value="Distribution" selected>Distribution</option>
              <option value="Service Line">Service Line</option>
            </select></div>
          <div><label class="form-label">Material *</label>
            <select id="eMaterial" class="form-input">
              <option>PVC</option><option>HDPE</option><option>Steel</option>
              <option>GI</option><option>GIP</option><option>PE</option>
              <option>SSP</option><option>CLCC Steel</option><option>other</option>
            </select></div>
          <div><label class="form-label">Status *</label>
            <select id="eStatus" class="form-input">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="rehabilitation">Rehabilitation</option>
              <option value="new">New</option>
            </select></div>
        </div>
      </div>
      <div style="margin-bottom:14px">
        <div style="font-size:11px;color:var(--accent);font-weight:600;text-transform:uppercase;letter-spacing:.08em;border-bottom:1px solid var(--border);padding-bottom:6px;margin-bottom:10px">Details</div>
        <div class="form-2col">
          <div><label class="form-label">Name *</label><input id="eName" class="form-input" placeholder="e.g. Main Transmission A"></div>
          <div><label class="form-label">Barangay</label><input id="eBarangay" class="form-input" placeholder="e.g. Poblacion"></div>
          <div><label class="form-label">Diameter (mm)</label><input id="eDiameter" type="number" class="form-input" placeholder="e.g. 200"></div>
          <div><label class="form-label">Pressure Class</label>
            <select id="ePressure" class="form-input">
              <option value="Low">Low</option><option value="Medium" selected>Medium</option>
              <option value="High">High</option><option value="Very High">Very High</option>
            </select></div>
          <div><label class="form-label">Length (m)</label><input id="eLength" type="number" step="0.1" class="form-input"></div>
          <div><label class="form-label">Condition Rating</label>
            <select id="eCondition" class="form-input">
              <option value="Excellent">Excellent</option><option value="Good" selected>Good</option>
              <option value="Fair">Fair</option><option value="Poor">Poor</option><option value="Critical">Critical</option>
            </select></div>
          <div><label class="form-label">Op. Pressure (bar)</label><input id="eOpPressure" type="number" step="0.1" class="form-input"></div>
          <div><label class="form-label">Max Pressure (bar)</label><input id="eMaxPressure" type="number" step="0.1" class="form-input"></div>
          <div><label class="form-label">Installation Date</label><input id="eInstall" type="date" class="form-input"></div>
          <div><label class="form-label">Last Inspection Date</label><input id="eInspect" type="date" class="form-input"></div>
          <div><label class="form-label">Coating / Lining</label><input id="eCoating" class="form-input" placeholder="e.g. Epoxy-lined"></div>
          <div><label class="form-label">Joint Type</label><input id="eJoint" class="form-input" placeholder="e.g. Push-fit"></div>
        </div>
      </div>
      <div style="margin-bottom:10px"><label class="form-label">Notes</label><textarea id="eNotes" class="form-input" rows="2"></textarea></div>
      <div style="margin-bottom:4px"><label class="form-label">Reason for Change</label><input id="eReason" class="form-input" placeholder="e.g. Annual update, rehabilitation completed…"></div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">
        <button onclick="closeModal('mAddEdit')" class="btn-secondary">Cancel</button>
        <button onclick="submitPipeline()" class="btn-primary">Save Pipeline</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal: Manual History Log ─────────────────────────────── -->
<div id="mLogChange" class="modal-overlay">
  <div class="modal-box" style="max-width:460px">
    <div class="modal-header"><h3>Log Manual Change</h3><button onclick="closeModal('mLogChange')">✕</button></div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
      <div><label class="form-label">Pipeline *</label>
        <select id="logPipelineId" class="form-input"><option value="">Select pipeline…</option></select></div>
      <div class="form-2col">
        <div><label class="form-label">Field Changed *</label>
          <select id="logField" class="form-input">
            <option value="status">Status</option><option value="material">Material</option>
            <option value="diameter_mm">Diameter</option><option value="condition_rating">Condition</option>
            <option value="pressure_class">Pressure Class</option><option value="other">Other</option>
          </select></div>
        <div><label class="form-label">Change Type</label>
          <select id="logChangeType" class="form-input">
            <option value="status_change">Status Change</option>
            <option value="material_change">Material Change</option>
            <option value="diameter_change">Diameter Change</option>
            <option value="other">Other</option>
          </select></div>
      </div>
      <div class="form-2col">
        <div><label class="form-label">Old Value</label><input id="logOldVal" class="form-input" placeholder="Previous"></div>
        <div><label class="form-label">New Value *</label><input id="logNewVal" class="form-input" placeholder="New value"></div>
      </div>
      <div><label class="form-label">Reason *</label><textarea id="logReason" class="form-input" rows="2"></textarea></div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal('mLogChange')" class="btn-secondary">Cancel</button>
        <button onclick="submitManualLog()" class="btn-primary">Log Change</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal: Maintenance Event ──────────────────────────────── -->
<div id="mMaintenance" class="modal-overlay">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header"><h3>Log Maintenance Event</h3><button onclick="closeModal('mMaintenance')">✕</button></div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
      <input type="hidden" id="mePipelineId">
      <div><label class="form-label">Event Type *</label>
        <select id="meType" class="form-input">
          <option>Inspection</option><option>Repair</option><option>Replacement</option>
          <option>Cleaning</option><option>Pressure Test</option><option>Leak Detection</option>
          <option>Valve Operation</option><option>Other</option>
        </select></div>
      <div class="form-2col">
        <div><label class="form-label">Date *</label><input id="meDate" type="date" class="form-input"></div>
        <div><label class="form-label">Cost (PHP)</label><input id="meCost" type="number" step="0.01" class="form-input"></div>
      </div>
      <div><label class="form-label">Description</label><textarea id="meDesc" class="form-input" rows="2"></textarea></div>
      <div><label class="form-label">Findings / Results</label><textarea id="meFindings" class="form-input" rows="2"></textarea></div>
      <div><label class="form-label">Next Due Date</label><input id="meNextDue" type="date" class="form-input"></div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal('mMaintenance')" class="btn-secondary">Cancel</button>
        <button onclick="submitMaintenance()" class="btn-primary">Save Event</button>
      </div>
    </div>
  </div>
</div>

<script>
// ── Config ────────────────────────────────────────────────────
const API      = '../../backend/api/pipeline_classification.php';
const CAN_EDIT = <?= json_encode(in_array($_SESSION['role'], ['Admin','Staff'])) ?>;
let allPipelines = [];
let pipelineMap  = null;
let mapLines     = null;
let mapStyle     = 'type';
let activeCharts = {};
let chartsLoaded = false;

// ── Tab switch ────────────────────────────────────────────────
function switchTab(id, btn) {
  document.querySelectorAll('.pc-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.pc-tab').forEach(b => b.classList.remove('active'));
  document.getElementById('pane-' + id).classList.add('active');
  btn.classList.add('active');
  if (id === 'map')      { initMap(); setTimeout(() => { pipelineMap?.invalidateSize(); renderMapPipelines(); }, 200); }
  if (id === 'history')  loadHistory();
  if (id === 'forecast') loadForecast();
  if (id === 'charts' && !chartsLoaded) { chartsLoaded = true; loadCharts(); }
}

// ── Load stats ────────────────────────────────────────────────
async function loadStats() {
  const r = await apiGet(API, { action: 'get_stats' });
  const t = r.totals || {};
  document.getElementById('s-total').textContent = (t.total        || 0).toLocaleString();
  document.getElementById('s-trans').textContent = (t.transmission || 0).toLocaleString();
  document.getElementById('s-dist').textContent  = (t.distribution || 0).toLocaleString();
  document.getElementById('s-svc').textContent   = (t.service_line || 0).toLocaleString();
  document.getElementById('s-flag').textContent  = (t.flagged      || 0).toLocaleString();
  document.getElementById('s-aging').textContent = (t.aging_count  || 0).toLocaleString();
  document.getElementById('s-km').textContent    = t.total_length_km || '—';
}

// ── Load pipelines ────────────────────────────────────────────
async function loadPipelines() {
  const r = await apiGet(API, { action: 'get_pipelines' });
  allPipelines = r.data || [];
  populatePipelineSelects(allPipelines);
  filterPipelines();
  await loadStats();
}

function populatePipelineSelects(pipes) {
  const opts = '<option value="">All Pipelines</option>' +
    pipes.map(p => `<option value="${p.id}">${p.name || 'Pipeline #'+p.id}</option>`).join('');
  ['hPipeline','logPipelineId'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.innerHTML = opts;
  });
}

// ── Filter + render list ──────────────────────────────────────
function filterPipelines() {
  const q        = document.getElementById('fSearch').value.toLowerCase();
  const type     = document.getElementById('fType').value;
  const material = document.getElementById('fMaterial').value;
  const status   = document.getElementById('fStatus').value;
  const pressure = document.getElementById('fPressure').value;
  const flagged  = document.getElementById('fFlagged').checked;
  const filtered = allPipelines.filter(p => {
    if (type     && p.pipeline_type  !== type)     return false;
    if (material && p.material       !== material)  return false;
    if (status   && p.status         !== status)    return false;
    if (pressure && p.pressure_class !== pressure)  return false;
    if (flagged  && !parseInt(p.is_flagged))         return false;
    if (q && ![(p.name||''),(p.barangay||''),(p.material||'')].join(' ').toLowerCase().includes(q)) return false;
    return true;
  });
  renderList(filtered);
}

const TYPE_COLORS = { Transmission:'#0057ff', Distribution:'#00c896', 'Service Line':'#94a3b8' };
const COND_COLORS = { Excellent:'#00c896', Good:'#0057ff', Fair:'#ffb800', Poor:'#ea580c', Critical:'#dc2626' };
const COND_PCT    = { Excellent:100, Good:80, Fair:55, Poor:30, Critical:10 };

function renderList(pipes) {
  const el = document.getElementById('pipelineList');
  if (!pipes.length) {
    el.innerHTML = '<p style="color:var(--muted);text-align:center;padding:30px 0">No pipelines match the current filters.</p>';
    return;
  }
  el.innerHTML = pipes.map(p => {
    const tc   = TYPE_COLORS[p.pipeline_type] || '#4a5a72';
    const cc   = COND_COLORS[p.condition_rating] || '#4a5a72';
    const cp   = COND_PCT[p.condition_rating]   || 50;
    const age  = parseInt(p.age_years || 0);
    const chg  = parseInt(p.status_changes_6mo || 0);
    const prc  = (p.pressure_class||'Medium').replace(' ','-');
    return `<div class="pl-item ${parseInt(p.is_flagged)?'flagged':''}" onclick="openDetail(${p.id})">
      <div class="type-dot" style="background:${tc};box-shadow:0 0 6px ${tc}66" title="${p.pipeline_type}"></div>
      <div>
        <div style="font-weight:600;margin-bottom:4px">
          ${p.name||'Pipeline #'+p.id}
          ${parseInt(p.is_flagged)?'<span class="b-chip b-flagged" style="margin-left:6px">⚠ Flagged</span>':''}
        </div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:5px;display:flex;gap:8px;flex-wrap:wrap">
          <span>${p.material}</span>
          ${p.diameter_mm?`<span>∅${p.diameter_mm}mm</span>`:''}
          ${p.barangay?`<span>📍 ${p.barangay}</span>`:''}
          ${age?`<span>🗓 ${age} yr</span>`:''}
          ${chg>0?`<span style="color:var(--warn)">🔄 ${chg}/6mo</span>`:''}
        </div>
        <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:6px">
          <span class="b-chip b-${p.status}">${p.status}</span>
          <span class="b-chip" style="background:rgba(100,100,200,.1);color:#94a3b8">${p.pipeline_type}</span>
          <span class="b-chip b-${prc}">${p.pressure_class||'Medium'} P</span>
          ${p.condition_rating?`<span class="b-chip" style="background:rgba(100,100,100,.1);color:${cc}">${p.condition_rating}</span>`:''}
        </div>
        <div class="cond-bar"><div class="cond-fill" style="width:${cp}%;background:${cc}"></div></div>
      </div>
      <div style="text-align:right;font-size:12px;color:var(--muted)">
        <div style="font-size:20px">${p.pipeline_type==='Transmission'?'🔵':p.pipeline_type==='Distribution'?'🟢':'⚫'}</div>
        ${p.length_m?`<div>${(p.length_m/1000).toFixed(2)}km</div>`:''}
        <div style="font-size:10px;color:var(--border);margin-top:4px">#${p.id}</div>
      </div>
    </div>`;
  }).join('');
}

// ── Detail panel ──────────────────────────────────────────────
async function openDetail(id) {
  const panel   = document.getElementById('detail-panel');
  const content = document.getElementById('detailContent');
  panel.classList.add('open');
  content.innerHTML = '<div class="spinner"></div>';
  const r = await apiGet(API, { action: 'get_pipeline', id });
  const p = r.data;
  if (!p) { content.innerHTML = '<p style="color:var(--danger)">Failed to load.</p>'; return; }
  document.getElementById('detailTitle').textContent = p.name || 'Pipeline #' + p.id;
  const age  = parseInt(p.age_years || 0);
  const cc   = COND_COLORS[p.condition_rating] || '#94a3b8';
  const events = p.maintenance_events || [];

  content.innerHTML = `
    <div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:14px">
      <span class="b-chip b-${p.status}">${p.status}</span>
      <span class="b-chip" style="background:rgba(100,100,200,.1);color:#94a3b8">${p.pipeline_type}</span>
      <span class="b-chip b-${(p.pressure_class||'Medium').replace(' ','-')}">${p.pressure_class||'Medium'} P</span>
      ${parseInt(p.is_flagged)?'<span class="b-chip b-flagged">⚠ Flagged</span>':''}
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-bottom:14px">
      ${dRow('Material',        p.material||'—')}
      ${dRow('Diameter',        p.diameter_mm?p.diameter_mm+' mm':'—')}
      ${dRow('Length',          p.length_m?(p.length_m/1000).toFixed(3)+' km':'—')}
      ${dRow('Condition',       `<span style="color:${cc};font-weight:700">${p.condition_rating||'—'}</span>`)}
      ${dRow('Age',             age?age+' years':'—')}
      ${dRow('Installed',       p.installation_date||'—')}
      ${dRow('Last Inspection', p.last_inspection_date||'—')}
      ${dRow('Barangay',        p.barangay||'—')}
      ${dRow('Op. Pressure',    p.operating_pressure_bar?p.operating_pressure_bar+' bar':'—')}
      ${dRow('Max Pressure',    p.max_pressure_bar?p.max_pressure_bar+' bar':'—')}
      ${dRow('Coating',         p.coating||'—')}
      ${dRow('Joint Type',      p.joint_type||'—')}
    </div>
    ${parseInt(p.is_flagged)?`
      <div style="background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);border-radius:8px;padding:10px 12px;margin-bottom:12px;font-size:13px;color:var(--danger)">
        <strong>⚠ Flag Reason:</strong> ${p.flag_reason||'Requires inspection'}
        ${CAN_EDIT?`<button onclick="unflag(${p.id})" style="float:right;background:none;border:1px solid rgba(255,77,109,.4);border-radius:5px;color:var(--danger);cursor:pointer;padding:2px 8px;font-size:11px;font-family:inherit">Clear Flag</button>`:''}
      </div>`:''}
    ${p.notes?`<div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px;font-size:13px;color:var(--text2);margin-bottom:12px">${p.notes}</div>`:''}
    ${CAN_EDIT?`
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--border)">
      <button onclick="openEditModal(${JSON.stringify(p).replace(/"/g,'&quot;')})" class="btn-primary" style="font-size:12px">✏ Edit</button>
      <button onclick="openMaintenanceModal(${p.id})" class="btn-secondary" style="font-size:12px">🔧 Maintenance</button>
    </div>`:''}
    <div style="font-size:13px;font-weight:600;margin-bottom:8px;color:var(--accent2)">Maintenance History (${events.length})</div>
    ${events.length?events.slice(0,5).map(e=>`
      <div style="background:var(--surface2);border:1px solid var(--border);border-radius:6px;padding:8px 10px;margin-bottom:5px;font-size:12px">
        <div style="display:flex;justify-content:space-between"><span style="font-weight:600">${e.event_type}</span><span style="color:var(--muted)">${e.event_date}</span></div>
        <div style="color:var(--text2);margin-top:2px">${e.description||''}</div>
        ${e.cost_php?`<div style="color:var(--accent3);margin-top:2px">Cost: ₱${parseFloat(e.cost_php).toLocaleString()}</div>`:''}
      </div>`).join(''):'<p style="color:var(--muted);font-size:12px">No maintenance events.</p>'}
  `;
}

function dRow(label, value) {
  return `<div style="background:var(--surface2);border:1px solid var(--border);border-radius:7px;padding:8px 10px">
    <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">${label}</div>
    <div style="font-size:13px;font-weight:600">${value}</div>
  </div>`;
}
function closeDetail() { document.getElementById('detail-panel').classList.remove('open'); }

// ── Map ───────────────────────────────────────────────────────
function initMap() {
  if (pipelineMap) return;
  pipelineMap = L.map('pipeline-map', { center:[6.223262,125.072111], zoom:13, zoomControl:true });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution:'© OpenStreetMap', maxZoom:20 }).addTo(pipelineMap);
  mapLines = L.layerGroup().addTo(pipelineMap);
}

function setMapStyle(style) {
  ['Type','Status','Pressure','Condition'].forEach(s => document.getElementById('style'+s)?.classList.remove('active'));
  document.getElementById('style' + style.charAt(0).toUpperCase() + style.slice(1))?.classList.add('active');
  mapStyle = style;
  renderMapPipelines();
}

function pipelineColor(p, style) {
  if (style==='type')      return {Transmission:'#0057ff',Distribution:'#00c896','Service Line':'#94a3b8'}[p.pipeline_type]||'#4a5a72';
  if (style==='status')    return {active:'#00c896',inactive:'#4a5a72',rehabilitation:'#ffb800',new:'#00d4ff'}[p.status]||'#4a5a72';
  if (style==='pressure')  return {Low:'#00c896',Medium:'#0057ff',High:'#ffb800','Very High':'#ff4d6d'}[p.pressure_class]||'#4a5a72';
  if (style==='condition') return COND_COLORS[p.condition_rating]||'#4a5a72';
  return '#0057ff';
}

function pipelineWeight(p) { return p.pipeline_type==='Transmission'?5:p.pipeline_type==='Service Line'?1.5:3; }

function renderMapPipelines() {
  if (!pipelineMap||!mapLines) return;
  mapLines.clearLayers();
  const flagOnly = document.getElementById('mapFlaggedOnly')?.checked;
  const bounds   = [];
  allPipelines.filter(p => p.path_geojson && (!flagOnly||parseInt(p.is_flagged))).forEach(p => {
    const color  = parseInt(p.is_flagged)?'#ff4d6d':pipelineColor(p, mapStyle);
    const weight = pipelineWeight(p);
    const dash   = p.status==='rehabilitation'?'8,6':p.status==='inactive'?'4,4':null;
    try {
      const gl = L.geoJSON(p.path_geojson, { style:{ color, weight, opacity:0.9, dashArray:dash, lineCap:'round', lineJoin:'round' } });
      gl.bindPopup(buildMapPopup(p), { maxWidth:260 });
      gl.on('click', () => openDetail(p.id));
      mapLines.addLayer(gl);
      const geom = typeof p.path_geojson==='object'?p.path_geojson:JSON.parse(p.path_geojson);
      const coords = geom.type==='LineString'?geom.coordinates:
                     geom.type==='Feature'?geom.geometry?.coordinates||[]:[];
      coords.forEach(c => bounds.push(Array.isArray(c[0])?c.map(cc=>[cc[1],cc[0]]):[c[1],c[0]]));
    } catch(e) {}
  });
  if (bounds.flat().length) try { pipelineMap.fitBounds(bounds.flat().filter(Array.isArray).length?bounds.flat():[bounds], {padding:[40,40],maxZoom:16}); } catch(e) {}
  updateMapLegend();
}

function buildMapPopup(p) {
  const cc = COND_COLORS[p.condition_rating]||'#94a3b8';
  return `<div style="font-size:12px">
    <div style="font-weight:700;margin-bottom:6px;color:var(--accent)">${p.name||'Pipeline #'+p.id}</div>
    <div style="display:grid;gap:2px;color:var(--text2)">
      <div>Type: <b style="color:var(--text)">${p.pipeline_type}</b></div>
      <div>Material: <b style="color:var(--text)">${p.material}</b></div>
      ${p.diameter_mm?`<div>Diameter: <b>∅${p.diameter_mm}mm</b></div>`:''}
      <div>Status: <b style="color:var(--text)">${p.status}</b></div>
      <div>Pressure: <b>${p.pressure_class||'—'}</b></div>
      <div>Condition: <b style="color:${cc}">${p.condition_rating||'—'}</b></div>
    </div>
    ${parseInt(p.is_flagged)?'<div style="margin-top:6px;padding:3px 7px;background:rgba(255,77,109,.15);border-radius:4px;font-size:11px;color:#ff4d6d">⚠ Flagged</div>':''}
  </div>`;
}

function updateMapLegend() {
  const legends = {
    type:      [{c:'#0057ff',l:'Transmission'},{c:'#00c896',l:'Distribution'},{c:'#94a3b8',l:'Service Line'},{c:'#ff4d6d',l:'Flagged',d:true}],
    status:    [{c:'#00c896',l:'Active'},{c:'#4a5a72',l:'Inactive',d:true},{c:'#ffb800',l:'Rehabilitation',d:true},{c:'#00d4ff',l:'New'}],
    pressure:  [{c:'#00c896',l:'Low'},{c:'#0057ff',l:'Medium'},{c:'#ffb800',l:'High'},{c:'#ff4d6d',l:'Very High'}],
    condition: [{c:'#00c896',l:'Excellent'},{c:'#0057ff',l:'Good'},{c:'#ffb800',l:'Fair'},{c:'#ea580c',l:'Poor'},{c:'#dc2626',l:'Critical'}],
  };
  document.getElementById('mapLegend').innerHTML =
    `<div style="font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px">Legend — ${mapStyle}</div>` +
    (legends[mapStyle]||[]).map(i => `<div style="display:flex;align-items:center;gap:7px;padding:2px 0;color:var(--text2);font-size:11px">
      <div style="width:18px;height:${i.d?0:3}px;background:${i.c};border-radius:2px;${i.d?'border-bottom:3px dashed '+i.c:''}"></div>${i.l}</div>`).join('');
}

// ── History ───────────────────────────────────────────────────
async function loadHistory() {
  const r = await apiGet(API, {
    action:      'get_history',
    pipeline_id: document.getElementById('hPipeline').value,
    field:       document.getElementById('hField').value,
    date_from:   document.getElementById('hDateFrom').value,
    date_to:     document.getElementById('hDateTo').value,
  });
  const events = r.data || [];
  document.getElementById('historyCount').textContent = events.length + ' events';
  const el = document.getElementById('historyList');
  if (!events.length) { el.innerHTML = '<p style="color:var(--muted);text-align:center;padding:20px">No history events found.</p>'; return; }
  const ci = { status_change:'🔄', material_change:'🔩', diameter_change:'📐', path_change:'🗺', other:'📝' };
  const fl = { status:'Status', material:'Material', diameter_mm:'Diameter', condition_rating:'Condition', pressure_class:'Pressure', creation:'Created', deletion:'Deleted', is_flagged:'Flag', maintenance_event:'Maintenance' };
  el.innerHTML = events.map(e => `
    <div class="history-event ${e.change_type}">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px">
        <div><span style="font-size:14px">${ci[e.change_type]||'📝'}</span>
          <span style="font-weight:600;margin-left:4px">${fl[e.field_changed]||e.field_changed||e.change_type}</span>
          ${e.pipeline_id?`<span style="font-size:11px;color:var(--muted);margin-left:6px">Pipeline #${e.pipeline_id}</span>`:''}
        </div>
        <span style="font-size:11px;color:var(--muted);white-space:nowrap">${(e.changed_at||'').substring(0,16)}</span>
      </div>
      ${(e.old_value||e.new_value)?`<div style="display:flex;align-items:center;gap:7px;font-size:12px;margin-bottom:4px">
        ${e.old_value?`<span style="background:rgba(255,77,109,.12);color:#ff4d6d;padding:1px 7px;border-radius:10px">${e.old_value}</span>`:''}
        ${(e.old_value&&e.new_value)?'<span style="color:var(--muted)">→</span>':''}
        ${e.new_value?`<span style="background:rgba(0,200,150,.12);color:#00c896;padding:1px 7px;border-radius:10px">${e.new_value}</span>`:''}
      </div>`:''}
      ${e.reason?`<div style="font-size:12px;color:var(--text2)">${e.reason}</div>`:''}
      <div style="font-size:11px;color:var(--muted);margin-top:3px">By: ${e.changed_by_name||'System'}</div>
    </div>`).join('');
}

// ── Forecasting ───────────────────────────────────────────────
async function loadForecast() {
  const el = document.getElementById('forecastContent');
  el.innerHTML = '<div class="spinner"></div>';
  const [rr, sr, fr] = await Promise.all([
    apiGet(API, {action:'risk_assessment'}),
    apiGet(API, {action:'forecasting_summary'}),
    apiGet(API, {action:'get_flagged'}),
  ]);
  const risks   = rr.data || [];
  const flagged = fr.data || [];
  const highFreq= (sr.high_frequency_changes||[]);
  const maxRisk = Math.max(...risks.map(r=>parseInt(r.risk_score)||0), 1);

  el.innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
      <div class="card">
        <div class="card-title">⚠ Flagged Pipelines (${flagged.length})</div>
        ${!flagged.length?'<p style="color:var(--muted);font-size:13px">No flagged pipelines.</p>':
          flagged.slice(0,6).map(p=>`
            <div class="forecast-card ${parseInt(p.status_change_count||0)>=5?'critical':parseInt(p.status_change_count||0)>=3?'high':'medium'}">
              <div style="font-weight:600;font-size:13px;margin-bottom:2px">${p.name||'Pipeline #'+p.id}</div>
              <div style="font-size:11px;color:var(--muted);margin-bottom:4px">${p.material} · ${parseInt(p.age_years||0)}yr · ${p.status_change_count||0} status changes</div>
              <div style="font-size:11px;color:var(--warn)">${p.flag_reason||''}</div>
              ${CAN_EDIT?`<button onclick="unflag(${p.id})" style="margin-top:5px;background:none;border:1px solid rgba(255,184,0,.3);border-radius:5px;color:var(--warn);cursor:pointer;padding:2px 8px;font-size:11px;font-family:inherit">Clear Flag</button>`:''}
            </div>`).join('')}
        ${flagged.length>6?`<p style="font-size:12px;color:var(--muted)">+ ${flagged.length-6} more</p>`:''}
      </div>
      <div class="card">
        <div class="card-title">🔄 High-Frequency Changes (6 mo)</div>
        ${!highFreq.length?'<p style="color:var(--muted);font-size:13px">No frequently-changing pipelines.</p>':
          highFreq.map(p=>`
            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:7px;padding:10px;margin-bottom:6px">
              <div style="display:flex;justify-content:space-between;margin-bottom:3px">
                <span style="font-weight:600;font-size:13px">${p.name||'Pipeline #'+p.id}</span>
                <span style="font-size:18px;font-weight:700;color:var(--danger)">${p.changes_6mo}</span>
              </div>
              <div style="font-size:11px;color:var(--muted)">${p.material} · ${p.pipeline_type}</div>
              <div style="height:4px;background:rgba(255,77,109,.15);border-radius:2px;margin-top:5px;overflow:hidden">
                <div style="height:100%;width:${Math.min(100,parseInt(p.changes_6mo)*20)}%;background:linear-gradient(90deg,var(--warn),var(--danger));border-radius:2px"></div>
              </div>
              ${parseInt(p.changes_6mo)>=3?'<div style="font-size:11px;color:var(--danger);margin-top:3px">🚨 Exceeds threshold — inspection recommended</div>':''}
            </div>`).join('')}
      </div>
    </div>
    <div class="card">
      <div class="card-title">📊 Pipeline Risk Assessment (Top ${Math.min(risks.length,20)})</div>
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px">
          <thead><tr>${['Pipeline','Type','Material','Status','Condition','Age','Changes/6mo','Risk Score'].map(h=>
            `<th style="padding:8px 10px;text-align:left;font-size:10px;color:var(--muted);text-transform:uppercase;border-bottom:1px solid var(--border);white-space:nowrap">${h}</th>`).join('')}</tr></thead>
          <tbody>${risks.slice(0,20).map(r=>{
            const rp=Math.min(100,Math.round((parseInt(r.risk_score)||0)/maxRisk*100));
            const rc=rp>=70?'var(--danger)':rp>=40?'var(--warn)':'var(--accent3)';
            return `<tr style="border-bottom:1px solid rgba(255,255,255,.04);cursor:pointer" onclick="openDetail(${r.id})">
              <td style="padding:8px 10px;font-weight:600">${r.name||'#'+r.id}</td>
              <td style="padding:8px 10px;color:var(--text2)">${r.pipeline_type}</td>
              <td style="padding:8px 10px;color:var(--text2)">${r.material}</td>
              <td style="padding:8px 10px"><span class="b-chip b-${r.status}">${r.status}</span></td>
              <td style="padding:8px 10px;color:${COND_COLORS[r.condition_rating]||'var(--text2)'}">${r.condition_rating||'—'}</td>
              <td style="padding:8px 10px;color:${parseInt(r.age_years||0)>=20?'var(--warn)':'var(--text2)'}">${r.age_years||'—'}yr</td>
              <td style="padding:8px 10px;color:${parseInt(r.status_changes_6mo||0)>=3?'var(--danger)':'var(--text2)'}">${r.status_changes_6mo||0}</td>
              <td style="padding:8px 10px;min-width:100px">
                <div style="display:flex;align-items:center;gap:5px">
                  <div class="risk-bar" style="flex:1"><div class="risk-fill" style="width:${rp}%"></div></div>
                  <span style="color:${rc};font-weight:600;font-size:11px;min-width:22px">${r.risk_score}</span>
                </div>
              </td>
            </tr>`;}).join('')}
          </tbody>
        </table>
      </div>
    </div>`;
}

// ── Charts ────────────────────────────────────────────────────
async function loadCharts() {
  const [sr, sumR] = await Promise.all([
    apiGet(API, {action:'get_stats'}),
    apiGet(API, {action:'forecasting_summary'}),
  ]);
  const t     = sr.totals || {};
  const mb    = sr.material_breakdown || [];
  const trend = sr.status_trend || [];
  const cond  = (sumR.condition_summary||[]);
  const COLORS= ['#0057ff','#00c896','#94a3b8','#ffb800','#ff4d6d','#8b00ff','#00d4ff'];
  const tks   = { ticks:{color:'#94a3b8'}, grid:{color:'#1e2d40'} };
  const baseOpts = { responsive:true, maintainAspectRatio:false, plugins:{legend:{labels:{color:'#94a3b8',font:{family:'Sora',size:11}}}}, scales:{x:tks,y:{...tks,beginAtZero:true}} };

  const destroyChart = id => { if(activeCharts[id]){activeCharts[id].destroy();delete activeCharts[id];} };
  destroyChart('chartType');
  activeCharts.chartType = new Chart(document.getElementById('chartType'), { type:'doughnut', data:{ labels:['Transmission','Distribution','Service Line'], datasets:[{data:[t.transmission||0,t.distribution||0,t.service_line||0],backgroundColor:['#0057ff','#00c896','#94a3b8'],borderWidth:0}]}, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:'#94a3b8',font:{family:'Sora'}}}}}});
  destroyChart('chartMaterial');
  activeCharts.chartMaterial = new Chart(document.getElementById('chartMaterial'), { type:'bar', data:{ labels:mb.map(m=>m.material), datasets:[{label:'Count',data:mb.map(m=>m.count),backgroundColor:COLORS,borderRadius:4}]}, options:{...baseOpts,plugins:{legend:{display:false}}}});
  destroyChart('chartStatus');
  activeCharts.chartStatus = new Chart(document.getElementById('chartStatus'), { type:'doughnut', data:{ labels:['Active','Inactive','Rehabilitation','New'], datasets:[{data:[t.active||0,t.inactive||0,t.rehabilitation||0,t.new_pipelines||0],backgroundColor:['#00c896','#4a5a72','#ffb800','#00d4ff'],borderWidth:0}]}, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:'#94a3b8',font:{family:'Sora'}}}}}});
  const condOrder=['Excellent','Good','Fair','Poor','Critical'];
  const condColors=['#00c896','#0057ff','#ffb800','#ea580c','#dc2626'];
  const condMap=Object.fromEntries(cond.map(c=>[c.condition_rating,c.count]));
  destroyChart('chartCondition');
  activeCharts.chartCondition = new Chart(document.getElementById('chartCondition'), { type:'bar', data:{ labels:condOrder, datasets:[{label:'Pipelines',data:condOrder.map(c=>condMap[c]||0),backgroundColor:condColors,borderRadius:4}]}, options:{...baseOpts,plugins:{legend:{display:false}}}});
  const months=[...new Set(trend.map(t=>t.month))].sort();
  const types=[...new Set(trend.map(t=>t.change_type))];
  const tColors={status_change:'#ffb800',material_change:'#0057ff',diameter_change:'#00c896',other:'#94a3b8'};
  destroyChart('chartTrend');
  activeCharts.chartTrend = new Chart(document.getElementById('chartTrend'), { type:'line', data:{ labels:months, datasets:types.map((ct,i)=>({ label:ct.replace(/_/g,' '), data:months.map(m=>{const row=trend.find(r=>r.month===m&&r.change_type===ct);return row?.changes||0;}), borderColor:tColors[ct]||COLORS[i], backgroundColor:(tColors[ct]||COLORS[i])+'22', fill:true, tension:0.4, borderWidth:2, pointRadius:3}))}, options:{responsive:true,maintainAspectRatio:false,...{scales:{x:tks,y:{...tks,beginAtZero:true}}},plugins:{legend:{labels:{color:'#94a3b8',font:{family:'Sora',size:11}}}}}});
}

// ── Modal: Add/Edit ───────────────────────────────────────────
function openAddModal() {
  document.getElementById('editId').value = '';
  document.getElementById('mAddEditTitle').textContent = 'Add Pipeline';
  ['eName','eBarangay','eCoating','eJoint','eNotes','eReason'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  ['eDiameter','eLength','eOpPressure','eMaxPressure'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  document.getElementById('eInstall').value='';
  document.getElementById('eInspect').value='';
  document.getElementById('ePType').value='Distribution';
  document.getElementById('eMaterial').value='PVC';
  document.getElementById('eStatus').value='active';
  document.getElementById('ePressure').value='Medium';
  document.getElementById('eCondition').value='Good';
  openModal('mAddEdit');
}

function openEditModal(p) {
  document.getElementById('editId').value       = p.id;
  document.getElementById('mAddEditTitle').textContent = 'Edit Pipeline';
  document.getElementById('eName').value        = p.name||'';
  document.getElementById('eBarangay').value    = p.barangay||'';
  document.getElementById('ePType').value       = p.pipeline_type||'Distribution';
  document.getElementById('eMaterial').value    = p.material||'PVC';
  document.getElementById('eStatus').value      = p.status||'active';
  document.getElementById('ePressure').value    = p.pressure_class||'Medium';
  document.getElementById('eCondition').value   = p.condition_rating||'Good';
  document.getElementById('eDiameter').value    = p.diameter_mm||'';
  document.getElementById('eLength').value      = p.length_m||'';
  document.getElementById('eOpPressure').value  = p.operating_pressure_bar||'';
  document.getElementById('eMaxPressure').value = p.max_pressure_bar||'';
  document.getElementById('eInstall').value     = p.installation_date||'';
  document.getElementById('eInspect').value     = p.last_inspection_date||'';
  document.getElementById('eCoating').value     = p.coating||'';
  document.getElementById('eJoint').value       = p.joint_type||'';
  document.getElementById('eNotes').value       = p.notes||'';
  document.getElementById('eReason').value      = '';
  openModal('mAddEdit');
}

async function submitPipeline() {
  const name = document.getElementById('eName').value.trim();
  if (!name) { showToast('Pipeline name is required','error'); return; }
  const payload = {
    action:'save_pipeline',
    id: document.getElementById('editId').value||undefined,
    name, pipeline_type:document.getElementById('ePType').value,
    material:document.getElementById('eMaterial').value, status:document.getElementById('eStatus').value,
    pressure_class:document.getElementById('ePressure').value, condition_rating:document.getElementById('eCondition').value,
    diameter_mm:document.getElementById('eDiameter').value||undefined, length_m:document.getElementById('eLength').value||undefined,
    operating_pressure_bar:document.getElementById('eOpPressure').value||undefined, max_pressure_bar:document.getElementById('eMaxPressure').value||undefined,
    installation_date:document.getElementById('eInstall').value||undefined, last_inspection_date:document.getElementById('eInspect').value||undefined,
    coating:document.getElementById('eCoating').value||undefined, joint_type:document.getElementById('eJoint').value||undefined,
    barangay:document.getElementById('eBarangay').value||'', notes:document.getElementById('eNotes').value||'',
    reason:document.getElementById('eReason').value||'Updated via pipeline manager',
  };
  const r = await apiJSON(API, payload);
  if (r?.success) { closeModal('mAddEdit'); showToast('Pipeline saved','success'); await loadPipelines(); if(r.id) openDetail(r.id); }
  else showToast(r?.error||'Failed to save','error');
}

// ── Modal: Manual Log ─────────────────────────────────────────
function openLogModal() { openModal('mLogChange'); }

async function submitManualLog() {
  const pid    = document.getElementById('logPipelineId').value;
  const newVal = document.getElementById('logNewVal').value.trim();
  const reason = document.getElementById('logReason').value.trim();
  if (!pid)    { showToast('Select a pipeline','error'); return; }
  if (!newVal) { showToast('New value required','error'); return; }
  if (!reason) { showToast('Reason required','error'); return; }
  const r = await apiJSON(API, { action:'log_change', pipeline_id:pid, field_changed:document.getElementById('logField').value, change_type:document.getElementById('logChangeType').value, old_value:document.getElementById('logOldVal').value, new_value:newVal, reason });
  if (r?.success) { closeModal('mLogChange'); showToast('Change logged','success'); loadHistory(); }
  else showToast(r?.error||'Failed','error');
}

// ── Modal: Maintenance ────────────────────────────────────────
function openMaintenanceModal(pid) {
  document.getElementById('mePipelineId').value = pid;
  document.getElementById('meDate').value = new Date().toISOString().substring(0,10);
  ['meDesc','meFindings','meCost','meNextDue'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  openModal('mMaintenance');
}

async function submitMaintenance() {
  const pid  = document.getElementById('mePipelineId').value;
  const date = document.getElementById('meDate').value;
  if (!date) { showToast('Event date required','error'); return; }
  const r = await apiJSON(API, { action:'save_maintenance', pipeline_id:pid, event_type:document.getElementById('meType').value, event_date:date, description:document.getElementById('meDesc').value, findings:document.getElementById('meFindings').value, cost_php:document.getElementById('meCost').value||undefined, next_due_date:document.getElementById('meNextDue').value||undefined });
  if (r?.success) { closeModal('mMaintenance'); showToast('Maintenance event logged','success'); await loadPipelines(); if(document.getElementById('detail-panel').classList.contains('open')&&pid) openDetail(parseInt(pid)); }
  else showToast(r?.error||'Failed','error');
}

// ── Unflag / Alerts / Export ──────────────────────────────────
async function unflag(id) {
  if (!confirm('Clear the flag on this pipeline?')) return;
  const r = await apiPost(API, {action:'unflag', id});
  if (r?.success) { showToast('Flag cleared','success'); await loadPipelines(); closeDetail(); }
  else showToast(r?.error||'Failed','error');
}

async function runAlerts() {
  const btn=event.target; btn.disabled=true; btn.textContent='Running…';
  const r = await apiPost(API, {action:'generate_alerts'});
  btn.disabled=false; btn.textContent='⚡ Run Forecasting';
  if (r?.success) { showToast(`Done — ${r.alerts_created||0} alerts, ${r.flagged_count||0} pipelines flagged`,'success'); await loadPipelines(); }
  else showToast(r?.error||'Failed','error');
}

async function exportData() {
  const r = await apiGet('utilities.php', {action:'export_csv',table:'pipelines'});
  if (!r?.csv_base64) { showToast('Export failed','error'); return; }
  const blob=new Blob([atob(r.csv_base64)],{type:'text/csv'});
  const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=r.filename||'pipelines.csv'; a.click();
  showToast(`Exported ${r.row_count} records`,'success');
}

// ── JSON POST helper ──────────────────────────────────────────
async function apiJSON(url, data={}) {
  const res = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body:JSON.stringify(data) });
  return res.json();
}

// ── Init ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', loadPipelines);
</script>