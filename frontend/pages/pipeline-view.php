<?php
$pageTitle = 'Pipeline Details';
require_once 'layout.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: pipelines.php');
    exit;
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>

<style>
.info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px; margin-bottom:20px; }
.info-item { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:12px 14px; }
.info-label { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.08em; margin-bottom:4px; }
.info-value { font-size:14px; font-weight:600; }

.tab-bar { display:flex; gap:4px; border-bottom:1px solid var(--border); margin-bottom:16px; }
.tab-btn { padding:8px 16px; font-size:13px; font-weight:500; font-family:'Sora',sans-serif; border:none; background:none; color:var(--text2); cursor:pointer; border-bottom:2px solid transparent; transition:all 0.15s; margin-bottom:-1px; }
.tab-btn.active { color:var(--accent); border-bottom-color:var(--accent); }
.tab-pane { display:none; }
.tab-pane.active { display:block; }

.hist-event { position:relative; padding:10px 10px 10px 34px; border-bottom:1px solid rgba(255,255,255,.04); font-size:13px; }
.hist-event::before { content:''; position:absolute; left:12px; top:14px; width:8px; height:8px; border-radius:50%; background:var(--accent); }
.hist-event.status_change::before { background:var(--warn); }
.hist-event.material_change::before { background:var(--accent2); }
.hist-event.other::before { background:var(--muted); }
.hist-container::before { content:''; position:absolute; left:15px; top:0; bottom:0; width:1px; background:var(--border); }
.hist-container { position:relative; }

.maint-card { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:12px 14px; margin-bottom:8px; }

#pipeline-map { height:280px; width:100%; border-radius:10px; overflow:hidden; border:1px solid var(--border); position:relative; z-index:0; }
</style>

<main class="main">

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
  <a href="pipelines.php" class="btn-secondary" style="font-size:13px;text-decoration:none;padding:7px 14px;border-radius:8px;border:1px solid var(--border);color:var(--text)">← Pipelines</a>
  <h2 id="plName" style="font-size:20px;font-weight:700">Loading…</h2>
  <span id="plStatus"></span>
  <?php if(in_array($_SESSION['role'],['Admin','Staff'])): ?>
  <div style="margin-left:auto;display:flex;gap:8px">
    <button id="btnEdit" onclick="openEditFromView()" class="btn-secondary" style="font-size:13px">✏ Edit</button>
    <button onclick="openMaintenanceFromView()" class="btn-primary" style="font-size:13px">+ Maintenance</button>
  </div>
  <?php endif; ?>
</div>

<div id="spinner" style="text-align:center;padding:60px"><div class="spinner"></div></div>

<div id="content" style="display:none">

  <!-- Info grid -->
  <div class="info-grid" id="infoGrid"></div>

  <!-- Flagged warning -->
  <div id="flagWarn" style="display:none;background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--danger);margin-bottom:16px"></div>

  <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

    <!-- LEFT: Tabs -->
    <div>
      <div class="card">
        <div class="tab-bar">
          <button class="tab-btn active" onclick="showTab('history',this)">📜 Change History</button>
          <button class="tab-btn" onclick="showTab('maintenance',this)">🔧 Maintenance Events</button>
        </div>

        <!-- History -->
        <div class="tab-pane active" id="tab-history">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px">
            <span style="font-size:13px;color:var(--muted)" id="histCount"></span>
            <?php if(in_array($_SESSION['role'],['Admin','Staff'])): ?>
            <button onclick="openLogModal()" class="btn-secondary" style="font-size:12px;padding:5px 12px">+ Manual Log</button>
            <?php endif; ?>
          </div>
          <div class="hist-container" id="histList"><div class="spinner"></div></div>
        </div>

        <!-- Maintenance -->
        <div class="tab-pane" id="tab-maintenance">
          <div id="maintList"><div class="spinner"></div></div>
        </div>
      </div>
    </div>

    <!-- RIGHT: Map + quick info -->
    <div>
      <div class="card" style="margin-bottom:16px">
        <div class="card-title">📍 Pipeline Route</div>
        <div id="pipeline-map"></div>
        <div id="mapNote" style="font-size:11px;color:var(--muted);margin-top:6px;text-align:center"></div>
      </div>

      <div class="card">
        <div class="card-title">Technical Specs</div>
        <div id="specsGrid"></div>
      </div>
    </div>

  </div>
</div>

<!-- Manual Log Modal -->
<div id="mLog" class="modal-overlay">
  <div class="modal-box" style="max-width:440px">
    <div class="modal-header"><h3>Log Manual Change</h3><button onclick="closeModal('mLog')">✕</button></div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
      <select id="logField" class="form-input">
        <option value="status">Status</option>
        <option value="material">Material</option>
        <option value="diameter_mm">Diameter</option>
        <option value="condition_rating">Condition</option>
        <option value="pressure_class">Pressure Class</option>
        <option value="other">Other</option>
      </select>
      <select id="logChangeType" class="form-input">
        <option value="status_change">Status Change</option>
        <option value="material_change">Material Change</option>
        <option value="diameter_change">Diameter Change</option>
        <option value="other">Other</option>
      </select>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <input id="logOldVal" class="form-input" placeholder="Old value">
        <input id="logNewVal" class="form-input" placeholder="New value *">
      </div>
      <textarea id="logReason" class="form-input" rows="2" placeholder="Reason *"></textarea>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal('mLog')" class="btn-secondary">Cancel</button>
        <button onclick="submitLog()" class="btn-primary">Log Change</button>
      </div>
    </div>
  </div>
</div>

<!-- Maintenance Modal -->
<div id="mMaint" class="modal-overlay">
  <div class="modal-box" style="max-width:460px">
    <div class="modal-header"><h3>Log Maintenance Event</h3><button onclick="closeModal('mMaint')">✕</button></div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
      <select id="meType" class="form-input">
        <option>Inspection</option><option>Repair</option><option>Replacement</option>
        <option>Cleaning</option><option>Pressure Test</option><option>Leak Detection</option>
        <option>Valve Operation</option><option>Other</option>
      </select>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <input id="meDate" type="date" class="form-input">
        <input id="meCost" type="number" step="0.01" class="form-input" placeholder="Cost (PHP)">
      </div>
      <textarea id="meDesc" class="form-input" rows="2" placeholder="Description"></textarea>
      <textarea id="meFindings" class="form-input" rows="2" placeholder="Findings / Results"></textarea>
      <input id="meNextDue" type="date" class="form-input" placeholder="Next due date">
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal('mMaint')" class="btn-secondary">Cancel</button>
        <button onclick="submitMaint()" class="btn-primary">Save Event</button>
      </div>
    </div>
  </div>
</div>

</main>

<script>
const PIPELINE_ID = <?= $id ?>;
const API         = '../../backend/api/pipeline_classification.php';
const CAN_EDIT    = <?= json_encode(in_array($_SESSION['role'], ['Admin','Staff'])) ?>;

const COND_COLORS = { Excellent:'#00c896', Good:'#0057ff', Fair:'#ffb800', Poor:'#ea580c', Critical:'#dc2626' };
const TYPE_COLORS = { Transmission:'#0057ff', Distribution:'#00c896', 'Service Line':'#94a3b8' };
let pipelineData  = null;
let pipelineMap   = null;

// ── Load pipeline ─────────────────────────────────────────────
async function loadPipeline() {
  const r = await apiGet(API, { action: 'get_pipeline', id: PIPELINE_ID });
  const p = r?.data;
  if (!p) {
    document.getElementById('spinner').innerHTML = '<p style="color:var(--danger);text-align:center">Pipeline not found.</p>';
    return;
  }
  pipelineData = p;
  document.getElementById('spinner').style.display  = 'none';
  document.getElementById('content').style.display  = 'block';
  document.getElementById('plName').textContent      = p.name || 'Pipeline #' + p.id;
  document.getElementById('plStatus').innerHTML      =
    `<span class="badge badge-${p.status}">${p.status}</span>`;

  if (parseInt(p.is_flagged)) {
    const fw = document.getElementById('flagWarn');
    fw.style.display = 'block';
    fw.innerHTML = `⚠️ <strong>Flagged:</strong> ${p.flag_reason || 'Requires inspection'}
      ${CAN_EDIT ? `<button onclick="unflag()" style="float:right;background:none;border:1px solid rgba(255,77,109,.4);border-radius:5px;color:var(--danger);cursor:pointer;padding:2px 10px;font-size:11px;font-family:inherit">Clear Flag</button>` : ''}`;
  }

  renderInfoGrid(p);
  renderSpecs(p);
  initMap(p);
  loadHistory();
  loadMaintenance();
}

function renderInfoGrid(p) {
  const age = parseInt(p.age_years || 0);
  const cc  = COND_COLORS[p.condition_rating] || '#94a3b8';
  const tc  = TYPE_COLORS[p.pipeline_type]    || '#94a3b8';
  const fields = [
    ['Pipeline Type',    `<span style="color:${tc};font-weight:700">${p.pipeline_type}</span>`],
    ['Material',         p.material || '—'],
    ['Status',           `<span class="badge badge-${p.status}">${p.status}</span>`],
    ['Pressure Class',   p.pressure_class || '—'],
    ['Diameter',         p.diameter_mm ? p.diameter_mm + ' mm' : '—'],
    ['Length',           p.length_m ? (p.length_m/1000).toFixed(3) + ' km' : '—'],
    ['Condition',        `<span style="color:${cc};font-weight:700">${p.condition_rating || '—'}</span>`],
    ['Age',              age ? age + ' years' : '—'],
    ['Barangay',         p.barangay || '—'],
    ['Installed',        p.installation_date || '—'],
    ['Last Inspection',  p.last_inspection_date || '—'],
    ['Zone',             p.zone_name || '—'],
  ];
  document.getElementById('infoGrid').innerHTML = fields.map(([l, v]) => `
    <div class="info-item">
      <div class="info-label">${l}</div>
      <div class="info-value">${v}</div>
    </div>`).join('');
}

function renderSpecs(p) {
  const specs = [
    ['Op. Pressure',  p.operating_pressure_bar ? p.operating_pressure_bar + ' bar' : '—'],
    ['Max Pressure',  p.max_pressure_bar        ? p.max_pressure_bar + ' bar'        : '—'],
    ['Flow Rate',     p.flow_rate_lps           ? p.flow_rate_lps + ' L/s'           : '—'],
    ['Coating',       p.coating                 || '—'],
    ['Joint Type',    p.joint_type              || '—'],
    ['Status Changes',p.status_change_count     || 0],
    ['Notes',         p.notes                   || '—'],
  ];
  document.getElementById('specsGrid').innerHTML = specs.map(([l, v]) => `
    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:13px">
      <span style="color:var(--muted)">${l}</span>
      <span style="font-weight:500;text-align:right;max-width:60%">${v}</span>
    </div>`).join('');
}

// ── Map ───────────────────────────────────────────────────────
function initMap(p) {
  const mapEl = document.getElementById('pipeline-map');
  const note  = document.getElementById('mapNote');

  if (!p.path_geojson) {
    mapEl.style.display = 'flex';
    mapEl.style.alignItems = 'center';
    mapEl.style.justifyContent = 'center';
    mapEl.innerHTML = '<p style="color:var(--muted);font-size:13px">No path data recorded</p>';
    return;
  }

  pipelineMap = L.map('pipeline-map', { zoomControl: true, attributionControl: false });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20 }).addTo(pipelineMap);

  const colors = { active:'#0057ff', inactive:'#4a5a72', rehabilitation:'#ffb800', new:'#00c896' };
  const color  = colors[p.status] || '#0057ff';
  const geom   = typeof p.path_geojson === 'object' ? p.path_geojson : JSON.parse(p.path_geojson);

  const gl = L.geoJSON(geom, { style: { color, weight: 4, opacity: 0.9, lineCap:'round' } });
  gl.addTo(pipelineMap);
  try { pipelineMap.fitBounds(gl.getBounds(), { padding: [20, 20] }); } catch(e) {}

  note.textContent = `${p.pipeline_type} · ${p.material} · ${p.status}`;
}

// ── History tab ───────────────────────────────────────────────
async function loadHistory() {
  const r      = await apiGet(API, { action: 'get_history', pipeline_id: PIPELINE_ID, limit: 200 });
  const events = r?.data || [];
  const el     = document.getElementById('histList');
  document.getElementById('histCount').textContent = events.length + ' events';

  if (!events.length) {
    el.innerHTML = '<p style="color:var(--muted);font-size:13px;text-align:center;padding:20px 0">No history events.</p>';
    return;
  }

  const ci = { status_change:'🔄', material_change:'🔩', diameter_change:'📐', path_change:'🗺', other:'📝' };
  const fl = { status:'Status', material:'Material', diameter_mm:'Diameter', condition_rating:'Condition',
               pressure_class:'Pressure', creation:'Created', deletion:'Deleted', maintenance_event:'Maintenance', is_flagged:'Flag' };

  el.innerHTML = events.map(e => `
    <div class="hist-event ${e.change_type}">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px">
        <div>
          <span style="font-size:14px">${ci[e.change_type]||'📝'}</span>
          <span style="font-weight:600;margin-left:4px">${fl[e.field_changed]||e.field_changed||e.change_type}</span>
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

// ── Maintenance tab ───────────────────────────────────────────
async function loadMaintenance() {
  const r      = await apiGet(API, { action: 'get_maintenance', pipeline_id: PIPELINE_ID });
  const events = r?.data || [];
  const el     = document.getElementById('maintList');

  if (!events.length) {
    el.innerHTML = '<p style="color:var(--muted);font-size:13px;text-align:center;padding:20px 0">No maintenance events recorded.</p>';
    return;
  }

  const typeColors = { Inspection:'#0057ff', Repair:'#ff4d6d', Replacement:'#ffb800', Cleaning:'#00c896', 'Pressure Test':'#8b00ff', 'Leak Detection':'#ff8c00', Other:'#94a3b8' };

  el.innerHTML = events.map(e => {
    const tc = typeColors[e.event_type] || '#94a3b8';
    return `<div class="maint-card">
      <div style="display:flex;justify-content:space-between;margin-bottom:6px">
        <span style="font-weight:600;color:${tc}">${e.event_type}</span>
        <span style="font-size:12px;color:var(--muted)">${e.event_date}</span>
      </div>
      ${e.description?`<div style="font-size:13px;color:var(--text2);margin-bottom:4px">${e.description}</div>`:''}
      ${e.findings?`<div style="font-size:12px;color:var(--muted);margin-bottom:4px">Findings: ${e.findings}</div>`:''}
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted)">
        <span>By: ${e.performed_by_name||'—'}</span>
        ${e.cost_php?`<span style="color:var(--accent3)">₱${parseFloat(e.cost_php).toLocaleString()}</span>`:''}
        ${e.next_due_date?`<span>Next: ${e.next_due_date}</span>`:''}
      </div>
    </div>`;
  }).join('');
}

// ── Tab ───────────────────────────────────────────────────────
function showTab(id, btn) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + id).classList.add('active');
  btn.classList.add('active');
}

// ── Unflag ────────────────────────────────────────────────────
async function unflag() {
  if (!confirm('Clear the flag on this pipeline?')) return;
  const r = await apiPost(API, { action: 'unflag', id: PIPELINE_ID });
  if (r?.success) { showToast('Flag cleared', 'success'); loadPipeline(); }
  else showToast(r?.error || 'Failed', 'error');
}

// ── Log modal ─────────────────────────────────────────────────
function openLogModal() { openModal('mLog'); }

async function submitLog() {
  const newVal = document.getElementById('logNewVal').value.trim();
  const reason = document.getElementById('logReason').value.trim();
  if (!newVal || !reason) { showToast('New value and reason required', 'error'); return; }
  const r = await apiPost(API, {
    action:        'log_change',
    pipeline_id:   PIPELINE_ID,
    field_changed: document.getElementById('logField').value,
    change_type:   document.getElementById('logChangeType').value,
    old_value:     document.getElementById('logOldVal').value,
    new_value:     newVal,
    reason,
  });
  if (r?.success) { closeModal('mLog'); showToast('Change logged', 'success'); loadHistory(); }
  else showToast(r?.error || 'Failed', 'error');
}

// ── Maintenance modal ─────────────────────────────────────────
function openMaintenanceFromView() {
  document.getElementById('meDate').value = new Date().toISOString().substring(0, 10);
  ['meDesc','meFindings','meCost','meNextDue'].forEach(id => { document.getElementById(id).value = ''; });
  openModal('mMaint');
}

async function submitMaint() {
  const date = document.getElementById('meDate').value;
  if (!date) { showToast('Event date required', 'error'); return; }
  const r = await apiPost(API, {
    action:        'save_maintenance',
    pipeline_id:   PIPELINE_ID,
    event_type:    document.getElementById('meType').value,
    event_date:    date,
    description:   document.getElementById('meDesc').value,
    findings:      document.getElementById('meFindings').value,
    cost_php:      document.getElementById('meCost').value || '',
    next_due_date: document.getElementById('meNextDue').value || '',
  });
  if (r?.success) { closeModal('mMaint'); showToast('Maintenance event saved', 'success'); loadMaintenance(); }
  else showToast(r?.error || 'Failed', 'error');
}

function openEditFromView() {
  if (pipelineData) {
    window.location.href = 'pipelines.php?edit=' + PIPELINE_ID;
  }
}

loadPipeline();
</script>