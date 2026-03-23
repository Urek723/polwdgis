<?php
$pageTitle = 'Consumers';
require_once 'layout.php';
?>
<style>
/* ── Consumer rows ── */
.con-row{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:8px;cursor:pointer;transition:border-color .2s}
.con-row:hover{border-color:var(--accent)}
.type-R{color:var(--accent3)}.type-C{color:var(--accent)}.type-G{color:var(--warn)}

/* ── Chart bars (consumption sparkline) ── */
.chart-bar{height:32px;display:flex;align-items:flex-end;gap:2px;margin-top:8px}
.chart-bar-item{flex:1;background:var(--accent2);border-radius:2px 2px 0 0;min-height:2px;transition:height .3s}

/* ── Chatbot ── */
.chat-msg{padding:8px 12px;border-radius:10px;max-width:80%;margin-bottom:6px;font-size:13px;line-height:1.4}
.chat-user{background:var(--accent2);color:#fff;margin-left:auto;border-radius:10px 10px 0 10px}
.chat-bot{background:var(--surface2);border:1px solid var(--border);border-radius:10px 10px 10px 0}

/* ── Tab toggle ── */
.tab-btn{
  padding:7px 18px;border-radius:8px;border:1px solid var(--border);
  background:var(--surface2);color:var(--text2);cursor:pointer;
  font-size:13px;font-weight:500;font-family:'Sora',sans-serif;
  transition:all .15s;
}
.tab-btn.active{
  background:rgba(0,212,255,.12);border-color:var(--accent);color:var(--accent);
}

/* ── Embedded consumer map ── */
#mapTab{
  display:none;
  height:460px;
  border:1px solid var(--border);
  border-radius:12px;
  overflow:hidden;
  margin-bottom:18px;
}
#mapTab.show{display:block;}
#conMap{height:100%;width:100%;}

/* ── Map legend ── */
.map-legend{
  position:absolute;z-index:1000;bottom:16px;right:16px;
  background:rgba(17,24,39,.92);border:1px solid var(--border);
  border-radius:10px;padding:10px 14px;font-size:11px;pointer-events:none;
}
.legend-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:5px;border:1.5px solid rgba(255,255,255,.5);}
</style>

<main class="main">

<!-- ── Filter / action bar ── -->
<div style="display:flex;gap:8px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
  <button class="tab-btn active" id="tabListBtn" onclick="switchTab('list')">☰ List</button>
  <button class="tab-btn"        id="tabMapBtn"  onclick="switchTab('map')">🗺 Map</button>
  <?php if(in_array($_SESSION['role'],['Admin','Staff'])): ?>
  <button class="btn-primary" onclick="openModal('mcon')">+ Add Consumer</button>
  <?php endif; ?>
  <input id="fq" placeholder="Search name, account, address…" oninput="onFilterChange()" class="filter-input" style="flex:1;min-width:200px">
  <select id="ftype"   onchange="onFilterChange()" class="filter-input">
    <option value="">All Types</option>
    <option value="Residential">Residential</option>
    <option value="Commercial">Commercial</option>
    <option value="Government">Government</option>
  </select>
  <select id="fstatus" onchange="onFilterChange()" class="filter-input">
    <option value="">All Statuses</option>
    <option value="Active">Active</option>
    <option value="Disconnected">Disconnected</option>
    <option value="Pending">Pending</option>
  </select>
  <button onclick="openModal('mchat')" class="btn-secondary">💬 Chatbot</button>
</div>

<!-- ── Summary stats ── -->
<div id="conStats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:18px"></div>

<!-- ── Map tab ── -->
<div id="mapTab">
  <div style="position:relative;height:100%">
    <div id="conMap"></div>
    <div class="map-legend">
      <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:.1em;text-transform:uppercase;margin-bottom:6px">Legend</div>
      <div style="display:flex;flex-direction:column;gap:3px;color:var(--text2)">
        <div><span class="legend-dot" style="background:#00d4ff"></span>Active</div>
        <div><span class="legend-dot" style="background:#ff4d6d"></span>Disconnected</div>
        <div><span class="legend-dot" style="background:#ffb800"></span>Pending</div>
        <div><span class="legend-dot" style="background:#94a3b8"></span>No coords</div>
      </div>
    </div>
  </div>
</div>

<!-- ── List tab ── -->
<div id="conList"><div class="spinner"></div></div>
<div id="pagination" style="display:flex;gap:6px;justify-content:center;margin-top:16px;flex-wrap:wrap"></div>

<!-- ── Consumer detail side panel ── -->
<div id="panel" style="display:none;position:fixed;top:0;right:0;width:480px;height:100vh;background:var(--bg);border-left:1px solid var(--border);overflow-y:auto;z-index:300;padding:20px;box-shadow:-6px 0 24px rgba(0,0,0,.3)">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h3 id="ptitle" style="font-size:16px;font-weight:600"></h3>
    <button onclick="document.getElementById('panel').style.display='none'" style="background:none;border:none;color:var(--text);font-size:22px;cursor:pointer">✕</button>
  </div>
  <div id="pbody"></div>
</div>

<!-- ── Add / Edit Consumer Modal ── -->
<div id="mcon" class="modal-overlay">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-header"><h3>Add / Edit Consumer</h3><button onclick="closeModal('mcon')">✕</button></div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:8px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <input id="cname"   placeholder="Full Name *"   class="form-input">
        <input id="caccno"  placeholder="Account No."   class="form-input">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <select id="ctype"   class="form-input">
          <option>Residential</option><option>Commercial</option><option>Government</option>
        </select>
        <select id="cstatus" class="form-input">
          <option>Active</option><option>Disconnected</option><option>Pending</option>
        </select>
      </div>
      <input id="caddr"     placeholder="Address"        class="form-input">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <input id="cbarangay" placeholder="Barangay"     class="form-input">
        <input id="czone"     placeholder="Zone"         class="form-input">
      </div>
      <input id="ccontact"  placeholder="Contact Number" class="form-input">
      <input id="cemail"    placeholder="Email"          class="form-input" type="email">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <input id="clat" type="number" step="any" placeholder="Latitude (WGS84)"  class="form-input">
        <input id="clng" type="number" step="any" placeholder="Longitude (WGS84)" class="form-input">
      </div>
      <div style="font-size:11px;color:var(--muted)">
        ℹ️ For GIS bulk import (UTM coordinates), use the
        <a href="import-export.php" style="color:var(--accent)">CSV Import/Export</a> page.
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal('mcon')" class="btn-secondary">Cancel</button>
        <button onclick="submitCon()" class="btn-primary">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Chatbot Modal ── -->
<div id="mchat" class="modal-overlay">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header"><h3>🤖 Water Assistant</h3><button onclick="closeModal('mchat')">✕</button></div>
    <div style="padding:16px">
      <div id="chatMsgs" style="height:300px;overflow-y:auto;display:flex;flex-direction:column;margin-bottom:12px;gap:4px">
        <div class="chat-msg chat-bot">Hi! I'm your water district assistant. How can I help you today? Ask about billing, leaks, connections, or interruptions.</div>
      </div>
      <div style="display:flex;gap:8px">
        <input id="chatInput" placeholder="Type a message…" class="form-input" style="flex:1" onkeydown="if(event.key==='Enter')sendChat()">
        <button onclick="sendChat()" class="btn-primary">Send</button>
      </div>
    </div>
  </div>
</div>

</main>

<script>
// ── State ─────────────────────────────────────────────────────
let page       = 1;
const PER      = 50;
let conMap     = null;
let markerLayer = null;
let activeTab  = 'list';

// ── Filter helpers ────────────────────────────────────────────
function getFilters() {
  return {
    search: document.getElementById('fq').value,
    type:   document.getElementById('ftype').value,
    status: document.getElementById('fstatus').value,
  };
}

function onFilterChange() {
  if (activeTab === 'list') load(1);
  else loadConMapMarkers();
}

// ── Tab switching ─────────────────────────────────────────────
function switchTab(tab) {
  activeTab = tab;
  document.getElementById('tabListBtn').classList.toggle('active', tab === 'list');
  document.getElementById('tabMapBtn').classList.toggle('active', tab === 'map');

  document.getElementById('mapTab').classList.toggle('show', tab === 'map');
  document.getElementById('conList').style.display    = tab === 'list' ? '' : 'none';
  document.getElementById('pagination').style.display = tab === 'list' ? '' : 'none';

  if (tab === 'map') {
    initConMap();
    loadConMapMarkers();
  }
}

// ── List load ─────────────────────────────────────────────────
async function load(pg = 1) {
  page = pg;
  const f = getFilters();
  const d = await apiGet('consumer.php', { action: 'get_consumers', page, ...f });
  const cons  = d?.data || [];
  const total = d?.total || 0;
  const el    = document.getElementById('conList');

  if (!cons.length) {
    el.innerHTML = '<p style="color:var(--muted);padding:20px 0">No consumers found.</p>';
    renderStats([]);
    renderPagination(0, 0);
    return;
  }
  renderStats(cons);
  el.innerHTML = cons.map(c => `
    <div class="con-row" onclick="openCon('${c.account_id || c.id}')">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
        <div>
          <div style="font-weight:600">${c.name}</div>
          <div style="font-size:12px;color:var(--muted)">
            ${c.account_no || c.account_id} · ${c.address || ''} · ${c.barangay || ''}
            ${c.meter_number ? ' · Meter: ' + c.meter_number : ''}
          </div>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <span class="type-${c.type?c.type[0]:'R'}" style="font-size:12px;font-weight:600">${c.type || 'Residential'}</span>
          <div style="font-size:11px;color:${c.status==='Active'?'var(--accent3)':'var(--danger)'}">${c.status}</div>
          ${c.latitude && c.longitude
            ? `<div style="font-size:10px;color:var(--muted)">📍 ${parseFloat(c.latitude).toFixed(4)}, ${parseFloat(c.longitude).toFixed(4)}</div>`
            : '<div style="font-size:10px;color:var(--muted)">No coords</div>'}
        </div>
      </div>
    </div>`).join('');
  renderPagination(total, page);
}

function renderStats(cons) {
  const res = cons.filter(c => c.type === 'Residential').length;
  const com = cons.filter(c => c.type === 'Commercial').length;
  const gov = cons.filter(c => c.type === 'Government').length;
  document.getElementById('conStats').innerHTML = `
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:22px;font-weight:700;color:var(--accent)">${cons.length}</div>
      <div style="font-size:11px;color:var(--muted)">Shown</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:22px;font-weight:700;color:var(--accent3)">${res}</div>
      <div style="font-size:11px;color:var(--muted)">Residential</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:22px;font-weight:700;color:var(--accent)">${com}</div>
      <div style="font-size:11px;color:var(--muted)">Commercial</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:22px;font-weight:700;color:var(--warn)">${gov}</div>
      <div style="font-size:11px;color:var(--muted)">Government</div>
    </div>`;
}

function renderPagination(total, cur) {
  const pages = Math.ceil(total / PER);
  const el    = document.getElementById('pagination');
  if (pages <= 1) { el.innerHTML = ''; return; }
  el.innerHTML = Array.from({ length: Math.min(pages, 10) }, (_, i) => i + 1)
    .map(p => `<button onclick="load(${p})" class="${p === cur ? 'btn-primary' : 'btn-secondary'}" style="padding:4px 10px;font-size:12px">${p}</button>`)
    .join('');
}

// ── Map initialise ────────────────────────────────────────────
function initConMap() {
  if (conMap) { conMap.invalidateSize(); return; }

  conMap = L.map('conMap', { center: [6.7056, 125.0942], zoom: 13, zoomControl: true });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
    maxZoom: 20,
  }).addTo(conMap);

  markerLayer = L.layerGroup().addTo(conMap);
}

// ── Load map markers ──────────────────────────────────────────
async function loadConMapMarkers() {
  if (!conMap) return;
  markerLayer.clearLayers();

  const f = getFilters();
  // Fetch up to 5000 records for map (no pagination)
  const d = await apiGet('consumer.php', {
    action: 'get_consumers', page: 1, limit: 5000, ...f
  });
  const cons   = d?.data || [];
  const bounds = [];
  let   shown  = 0;

  cons.forEach(c => {
    const lat = parseFloat(c.latitude);
    const lng = parseFloat(c.longitude);
    if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;

    const color = c.status === 'Active'       ? '#00d4ff'
                : c.status === 'Disconnected' ? '#ff4d6d'
                :                               '#ffb800';

    const icon = L.divIcon({
      className: '',
      html: `<div style="
        width:11px;height:11px;
        background:${color};
        border:2px solid rgba(255,255,255,.75);
        border-radius:50%;
        box-shadow:0 2px 6px rgba(0,0,0,.45);
        cursor:pointer;
      "></div>`,
      iconSize:   [11, 11],
      iconAnchor: [5.5, 5.5],
    });

    const marker = L.marker([lat, lng], { icon });

    // ── Popup content ────────────────────────────────────────
    marker.bindPopup(`
      <div style="font-family:'Sora',sans-serif;min-width:200px;color:#111">
        <div style="font-size:14px;font-weight:700;margin-bottom:6px;color:#111">${c.name}</div>
        <table style="width:100%;border-collapse:collapse;font-size:12px">
          <tr><td style="padding:2px 4px;color:#555">Account No</td><td style="padding:2px 4px;font-weight:600">${c.account_no || c.account_id}</td></tr>
          <tr><td style="padding:2px 4px;color:#555">Type</td><td style="padding:2px 4px">${c.type}</td></tr>
          <tr><td style="padding:2px 4px;color:#555">Status</td><td style="padding:2px 4px;font-weight:600;color:${c.status==='Active'?'#16a34a':'#dc2626'}">${c.status}</td></tr>
          <tr><td style="padding:2px 4px;color:#555">Barangay</td><td style="padding:2px 4px">${c.barangay || '—'}</td></tr>
          ${c.zone         ? `<tr><td style="padding:2px 4px;color:#555">Zone</td><td style="padding:2px 4px">${c.zone}</td></tr>` : ''}
          ${c.meter_number ? `<tr><td style="padding:2px 4px;color:#555">Meter</td><td style="padding:2px 4px">${c.meter_brand || ''} ${c.meter_number}</td></tr>` : ''}
          ${c.contact_no   ? `<tr><td style="padding:2px 4px;color:#555">Contact</td><td style="padding:2px 4px">${c.contact_no}</td></tr>` : ''}
        </table>
        <div style="font-size:10px;color:#888;margin-top:6px">📍 ${lat.toFixed(6)}, ${lng.toFixed(6)}</div>
        <button onclick="openCon('${c.account_id || c.id}')"
          style="margin-top:8px;width:100%;padding:6px;background:#0057ff;color:#fff;border:none;border-radius:7px;cursor:pointer;font-size:12px;font-weight:600;font-family:'Sora',sans-serif">
          View Full Details
        </button>
      </div>
    `, { maxWidth: 260 });

    marker.on('click', () => openCon(c.account_id || c.id));
    markerLayer.addLayer(marker);
    bounds.push([lat, lng]);
    shown++;
  });

  if (bounds.length > 0) {
    try { conMap.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 }); } catch {}
  }

  showToast(
    `${shown} consumer${shown !== 1 ? 's' : ''} plotted on map` +
    (cons.length - shown > 0 ? ` (${cons.length - shown} without coordinates)` : ''),
    'info'
  );
}

async function openCon(accountId) {
  window.location.href = `consumer-view.php?account_id=${accountId}`;
}
// ── Predict consumption ───────────────────────────────────────
async function predict(consumerId) {
  document.getElementById('predResult').innerHTML = '<div class="spinner" style="margin:12px auto"></div>';
  const d = await apiGet('consumer.php', { action: 'predict_consumption', consumer_id: consumerId });
  if (!d || d.error) {
    document.getElementById('predResult').innerHTML =
      `<p style="color:var(--danger);font-size:13px">${d?.error || 'Prediction failed'}</p>`;
    return;
  }
  document.getElementById('predResult').innerHTML = `
    <div style="background:var(--surface2);border:1px solid var(--accent2);border-radius:8px;padding:12px">
      <div style="font-weight:600;margin-bottom:6px;color:var(--accent2)">📈 Consumption Prediction</div>
      <div style="font-size:13px;display:grid;gap:4px">
        <div>Predicted next month: <b style="color:var(--accent)">${d.predicted_m3 || '—'} m³</b></div>
        <div>Trend: <b style="color:${d.trend==='increasing'?'var(--danger)':d.trend==='decreasing'?'var(--accent3)':'var(--muted)'}">${d.trend || 'stable'}</b></div>
        <div>3-month avg: ${d.avg_last_3_months || '—'} m³</div>
      </div>
    </div>`;
}

// ── Generate document ────────────────────────────────────────
async function genDoc(consumerId) {
  const templates = await apiGet('consumer.php', { action: 'get_templates' });
  const tlist = templates?.data || [];
  if (!tlist.length) { showToast('No document templates available', 'error'); return; }
  const sel = prompt('Template ID (' + tlist.map(t => `${t.id}:${t.name}`).join(', ') + '):');
  if (!sel) return;
  const r = await apiPost('consumer.php', { action: 'generate_document', consumer_id: consumerId, template_id: sel });
  if (r?.success) {
    document.getElementById('docResult').innerHTML =
      `<div style="background:var(--surface2);border:1px solid var(--accent3);border-radius:8px;padding:10px;font-size:13px;color:var(--accent3)">✅ Document generated (ID: ${r.id || 'N/A'})</div>`;
    showToast('Document generated', 'success');
  } else {
    showToast(r?.error || 'Failed', 'error');
  }
}

// ── Add consumer ──────────────────────────────────────────────
async function submitCon() {
  const r = await apiPost('consumer.php', {
    action:    'save_consumer',
    name:      document.getElementById('cname').value,
    account_no: document.getElementById('caccno').value,
    type:      document.getElementById('ctype').value,
    status:    document.getElementById('cstatus').value,
    address:   document.getElementById('caddr').value,
    barangay:  document.getElementById('cbarangay').value,
    zone:      document.getElementById('czone').value,
    contact_no: document.getElementById('ccontact').value,
    email:     document.getElementById('cemail').value,
    latitude:  document.getElementById('clat').value,
    longitude: document.getElementById('clng').value,
  });
  if (r?.success) { closeModal('mcon'); load(); showToast('Consumer saved', 'success'); }
  else showToast(r?.error || 'Failed', 'error');
}

// ── Chatbot ───────────────────────────────────────────────────
let chatSession = '';
async function sendChat() {
  const input = document.getElementById('chatInput');
  const msg   = input.value.trim();
  if (!msg) return;
  input.value = '';
  const msgs = document.getElementById('chatMsgs');
  msgs.innerHTML += `<div style="display:flex;justify-content:flex-end"><div class="chat-msg chat-user">${msg}</div></div>`;
  msgs.scrollTop = msgs.scrollHeight;
  const d = await apiPost('consumer.php', { action: 'chatbot', message: msg, session_token: chatSession });
  chatSession = d?.session_token || chatSession;
  const reply = d?.response || "I'm sorry, I couldn't understand that.";
  msgs.innerHTML += `<div style="display:flex"><div class="chat-msg chat-bot">${reply}</div></div>`;
  msgs.scrollTop = msgs.scrollHeight;
}

// ── Init ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => load(1));
</script>
