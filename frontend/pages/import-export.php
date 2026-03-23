<?php
$pageTitle = 'CSV Import & Export';
require_once 'layout.php';
?>
<style>
.exp-card{
  background:var(--surface2);border:1px solid var(--border);border-radius:10px;
  padding:20px;display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;
}
.import-zone{
  border:2px dashed var(--border);border-radius:10px;padding:32px;text-align:center;
  cursor:pointer;transition:border-color .2s;margin-bottom:16px;
}
.import-zone:hover,.import-zone.dragover{border-color:var(--accent);}
.import-hist{
  background:var(--surface2);border:1px solid var(--border);
  border-radius:8px;padding:12px;margin-bottom:6px;font-size:13px;
}
.gis-info{
  background:var(--surface2);border:1px solid var(--accent2);
  border-radius:8px;padding:12px;font-size:12px;
  color:var(--text2);margin-bottom:12px;line-height:1.6;
}
.gis-info code{
  background:rgba(0,87,255,.15);padding:1px 5px;
  border-radius:4px;font-family:'Space Mono',monospace;font-size:11px;color:var(--accent);
}
.col-ok{color:var(--accent3);}
.col-err{color:var(--danger);}

/* ── Upload loading overlay ── */
#uploadOverlay{
  display:none;
  position:fixed;inset:0;
  background:rgba(0,0,0,.7);
  backdrop-filter:blur(4px);
  z-index:500;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  gap:20px;
}
#uploadOverlay.show{display:flex;}
.upload-spinner{
  width:56px;height:56px;
  border:5px solid var(--border);
  border-top-color:var(--accent);
  border-radius:50%;
  animation:spin .8s linear infinite;
}
.upload-progress-wrap{
  width:320px;
  background:var(--border);
  border-radius:6px;
  height:8px;
  overflow:hidden;
}
.upload-progress-bar{
  height:100%;
  background:linear-gradient(90deg,var(--accent2),var(--accent));
  border-radius:6px;
  width:0;
  transition:width .4s ease;
}
.upload-label{
  font-size:14px;color:var(--text2);
  font-family:'Space Mono',monospace;
  letter-spacing:.05em;
}
.upload-sub{
  font-size:12px;color:var(--muted);
}
</style>

<!-- ── Upload loading overlay ── -->
<div id="uploadOverlay">
  <div class="upload-spinner"></div>
  <div class="upload-label" id="overlayLabel">Uploading file…</div>
  <div class="upload-progress-wrap">
    <div class="upload-progress-bar" id="overlayBar"></div>
  </div>
  <div class="upload-sub" id="overlaySub">Please wait, do not close this page</div>
</div>

<main class="main">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

  <!-- ══════════════════════════════════════════════
       EXPORT SECTION
  ══════════════════════════════════════════════ -->
  <div>
    <h2 style="font-size:16px;font-weight:600;margin-bottom:16px">⬇ Export Data</h2>
    <?php
    $tables = [
      ['consumers',           '👥 Consumers (Full GIS)',  'Includes lat/lng, UTM x/y, elevation, meter info'],
      ['consumption_records', '💧 Consumption Records',   'Monthly reading & billing data'],
      ['pipelines',           '🔧 Pipelines',             'Pipeline inventory & status'],
      ['infrastructure',      '🏗 Infrastructure',        'Pumping stations, valves, reservoirs'],
      ['work_orders',         '📋 Work Orders',           'Maintenance work orders'],
      ['inventory_items',     '📦 Inventory',             'Stock and equipment items'],
    ];
    foreach ($tables as [$table, $label, $desc]): ?>
    <div class="exp-card">
      <div>
        <div style="font-weight:600;margin-bottom:4px"><?= $label ?></div>
        <div style="font-size:12px;color:var(--muted)"><?= $desc ?></div>
      </div>
      <button onclick="exportCSV('<?= $table ?>')" class="btn-primary" style="font-size:13px;white-space:nowrap">
        Export CSV
      </button>
    </div>
    <?php endforeach; ?>

    <div class="gis-info" style="margin-top:8px">
      <div style="font-weight:600;color:var(--accent2);margin-bottom:6px">📋 Consumer Export Column Reference</div>
      The consumer CSV export includes all columns needed for re-import or GIS analysis:
      <code>account_id</code> <code>account_no</code> <code>name</code> <code>type</code> <code>status</code>
      <code>address</code> <code>barangay</code> <code>municipal</code> <code>zone</code>
      <code>meter_brand</code> <code>meter_number</code>
      <code>x_utm</code> <code>y_utm</code> <code>elevation</code>
      <code>latitude</code> <code>longitude</code>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════
       IMPORT SECTION
  ══════════════════════════════════════════════ -->
  <div>
    <h2 style="font-size:16px;font-weight:600;margin-bottom:16px">⬆ Import Data</h2>

    <div style="margin-bottom:12px">
      <label style="font-size:13px;color:var(--muted);display:block;margin-bottom:6px">Import Type</label>
      <select id="importTable" class="filter-input" style="width:100%" onchange="onImportTypeChange()">
        <option value="consumers_gis">🌍 Consumer GIS Data (UTM → WGS84)</option>
        <option value="billing_consumers">📄 Consumer Billing CSV (ACCOUNTID format)</option>
        <option value="consumers">👥 Consumers (standard CSV)</option>
        <option value="inventory_items">📦 Inventory Items</option>
      </select>
    </div>

    <!-- GIS hint -->
    <div id="gisHint" class="gis-info">
      <div style="font-weight:600;color:var(--accent2);margin-bottom:6px">📌 GIS CSV Format</div>
      Accepted columns (in any order):<br>
      <code>id</code> <code>y</code> <code>x</code> <code>z</code>
      <code>ACCOUNTID</code> <code>customer_n</code> <code>zone</code>
      <code>cust_type</code> <code>status</code> <code>met_brand</code>
      <code>met_no</code> <code>address</code> <code>barangay</code>
      <code>municipal</code> <code>account_no</code> <code>contact_no</code>
      <br><br>
      <strong>Required:</strong> <code>ACCOUNTID</code> <code>customer_n</code> <code>x</code> <code>y</code>
      <br>
      <strong>CRS:</strong> Input UTM <em>EPSG:32651 (Zone 51N)</em> →
      automatically converted to WGS84 <em>EPSG:4326</em> before saving.
      <br>
      <strong>Duplicates:</strong> Rows with existing <code>ACCOUNTID</code> are <em>updated</em>, not duplicated.
    </div>

    <!-- Billing hint -->
    <div id="billingHint" class="gis-info" style="display:none;border-color:var(--accent3)">
      <div style="font-weight:600;color:var(--accent3);margin-bottom:6px">📄 Billing CSV Format</div>
      Accepted columns include:<br>
      <code>ACCOUNTID</code> <code>ACCOUNT_NAME</code> <code>ACCOUNTNO</code>
      <code>ADDRESS1</code> <code>ADDRESS2</code> <code>ZONE_DESC</code>
      <code>CATEGORY_DESC</code> <code>STATUS_CODE</code> <code>METER_BRAND</code>
      <code>METER_NUMBER</code> <code>INSTALL_DATE</code>
      <br><br>
      <strong>Required:</strong> <code>ACCOUNTID</code> <code>ACCOUNT_NAME</code>
      <br>
      <strong>Duplicates:</strong> Rows with existing <code>ACCOUNTID</code> are <em>updated</em>, not duplicated.
      <br>
      <strong>Large files:</strong> Supported — processed in batches of 500 rows.
    </div>

    <!-- Standard hint -->
    <div id="stdHint" class="gis-info" style="display:none;border-color:var(--border)">
      <div style="font-weight:600;color:var(--accent);margin-bottom:4px">📋 Standard CSV Format</div>
      Column headers must match the database field names exactly.
      Download an existing export to use as a template.
    </div>

    <!-- Drop zone -->
    <div class="import-zone" id="dropZone"
         onclick="document.getElementById('csvFile').click()"
         ondragover="dragOver(event)"
         ondragleave="dragLeave(event)"
         ondrop="dropFile(event)">
      <div style="font-size:32px;margin-bottom:8px">📂</div>
      <div style="font-weight:600;margin-bottom:4px">Drop CSV file here or click to browse</div>
      <div style="font-size:12px;color:var(--muted)">Accepts .csv files only</div>
      <input type="file" id="csvFile" accept=".csv" style="display:none" onchange="handleFile(event)">
    </div>

    <!-- File preview -->
    <div id="filePreview" style="display:none;margin-bottom:12px">
      <div style="font-size:13px;font-weight:600;margin-bottom:8px">
        Preview — <span id="previewFilename" style="color:var(--muted);font-weight:400"></span>
      </div>
      <div id="previewTable" style="overflow-x:auto;font-size:11px;max-height:200px;border:1px solid var(--border);border-radius:8px"></div>
      <div id="colCheck" style="margin-top:8px;font-size:12px"></div>
      <div style="display:flex;gap:8px;margin-top:10px">
        <button onclick="clearFile()" class="btn-secondary">✕ Clear</button>
        <button onclick="uploadCSV()" class="btn-primary" id="uploadBtn">⬆ Import</button>
      </div>
    </div>

    <!-- Import result -->
    <div id="importResult" style="display:none;margin-bottom:12px"></div>

    <!-- History -->
    <h3 style="font-size:14px;font-weight:600;margin:20px 0 12px">Recent Imports</h3>
    <div id="importHistory"><div class="spinner"></div></div>
  </div>

</div>
</main>

<script>
let selectedFile = null;

// ── Import type toggle ────────────────────────────────────────
function onImportTypeChange() {
  const t = document.getElementById('importTable').value;
  document.getElementById('gisHint').style.display     = t === 'consumers_gis'      ? 'block' : 'none';
  document.getElementById('billingHint').style.display = t === 'billing_consumers'  ? 'block' : 'none';
  document.getElementById('stdHint').style.display     = (t !== 'consumers_gis' && t !== 'billing_consumers') ? 'block' : 'none';
  if (selectedFile) validateColumns(selectedFile);
}
onImportTypeChange();

// ── Overlay helpers ───────────────────────────────────────────
function showOverlay(label, pct, sub) {
  document.getElementById('overlayLabel').textContent = label;
  document.getElementById('overlayBar').style.width   = pct + '%';
  document.getElementById('overlaySub').textContent   = sub || 'Please wait, do not close this page';
  document.getElementById('uploadOverlay').classList.add('show');
}

function updateOverlay(label, pct, sub) {
  document.getElementById('overlayLabel').textContent = label;
  document.getElementById('overlayBar').style.width   = pct + '%';
  if (sub) document.getElementById('overlaySub').textContent = sub;
}

function hideOverlay() {
  document.getElementById('uploadOverlay').classList.remove('show');
  document.getElementById('overlayBar').style.width = '0';
}

// ── Export ────────────────────────────────────────────────────
async function exportCSV(table) {
  const btn = event.target;
  btn.disabled = true; btn.textContent = 'Exporting…';
  const d = await apiGet('utilities.php', { action: 'export_csv', table });
  btn.disabled = false; btn.textContent = 'Export CSV';
  if (!d?.csv_base64) { showToast('Export failed: ' + (d?.error || 'unknown error'), 'error'); return; }

  const bytes = atob(d.csv_base64);
  const blob  = new Blob([bytes], { type: 'text/csv;charset=utf-8;' });
  const a     = document.createElement('a');
  a.href      = URL.createObjectURL(blob);
  a.download  = d.filename || table + '_export.csv';
  a.click();
  showToast(`${table} exported — ${d.row_count} rows`, 'success');
}

// ── Drag / drop ───────────────────────────────────────────────
function dragOver(e)  { e.preventDefault(); document.getElementById('dropZone').classList.add('dragover'); }
function dragLeave()  { document.getElementById('dropZone').classList.remove('dragover'); }
function dropFile(e)  { e.preventDefault(); dragLeave(); const f = e.dataTransfer.files[0]; if (f) processFile(f); }
function handleFile(e){ processFile(e.target.files[0]); }

function processFile(file) {
  if (!file || !file.name.toLowerCase().endsWith('.csv')) {
    showToast('Please select a CSV file', 'error'); return;
  }
  selectedFile = file;
  const fileSizeMB = (file.size / 1024 / 1024).toFixed(2);
  const reader = new FileReader();
  reader.onload = e => {
    const lines   = e.target.result.split('\n').filter(l => l.trim()).slice(0, 7);
    const headers = lines[0].split(',').map(h => h.trim().replace(/^"|"$/g, ''));
    const rows    = lines.slice(1, 5);

    let html = `<table style="border-collapse:collapse;width:100%"><thead><tr>`;
    html += headers.map(h => `<th style="padding:5px 8px;border:1px solid var(--border);background:var(--surface);white-space:nowrap">${h}</th>`).join('');
    html += `</tr></thead><tbody>`;
    html += rows.map(r => `<tr>${
      r.split(',').map(c => `<td style="padding:4px 8px;border:1px solid var(--border)">${c.trim().replace(/^"|"$/g,'')}</td>`).join('')
    }</tr>`).join('');
    html += '</tbody></table>';

    document.getElementById('previewTable').innerHTML  = html;
    document.getElementById('previewFilename').textContent = file.name + ' (' + fileSizeMB + ' MB)';
    validateColumns(headers);
    document.getElementById('filePreview').style.display = 'block';
    document.getElementById('importResult').style.display = 'none';
  };
  reader.readAsText(file);
}

function validateColumns(headersOrFile) {
  const importType = document.getElementById('importTable').value;
  const colCheck   = document.getElementById('colCheck');

  let required = [];
  if (importType === 'consumers_gis')     required = ['ACCOUNTID', 'customer_n', 'x', 'y'];
  if (importType === 'billing_consumers') required = ['ACCOUNTID', 'ACCOUNT_NAME'];

  if (!required.length || !Array.isArray(headersOrFile)) {
    colCheck.innerHTML = '';
    return;
  }

  const missing = required.filter(r => !headersOrFile.includes(r));
  if (missing.length) {
    colCheck.innerHTML = `<span class="col-err">⚠️ Missing required columns: <b>${missing.join(', ')}</b></span>`;
    document.getElementById('uploadBtn').disabled = true;
  } else {
    colCheck.innerHTML = `<span class="col-ok">✅ All required columns found (${required.join(', ')})</span>`;
    document.getElementById('uploadBtn').disabled = false;
  }
}

function clearFile() {
  selectedFile = null;
  document.getElementById('filePreview').style.display  = 'none';
  document.getElementById('importResult').style.display = 'none';
  document.getElementById('csvFile').value = '';
  document.getElementById('colCheck').innerHTML = '';
  document.getElementById('uploadBtn').disabled = false;
}

// ── Upload ────────────────────────────────────────────────────
async function uploadCSV() {
  if (!selectedFile) { showToast('No file selected', 'error'); return; }

  const importType  = document.getElementById('importTable').value;
  const btn         = document.getElementById('uploadBtn');
  const fileSizeMB  = (selectedFile.size / 1024 / 1024).toFixed(2);

  btn.disabled    = true;
  btn.textContent = 'Importing…';
  document.getElementById('importResult').style.display = 'none';

  showOverlay(
    'Uploading file…',
    10,
    `File size: ${fileSizeMB} MB — large files may take a minute`
  );

  let result;

  if (importType === 'consumers_gis') {
    const fd = new FormData();
    fd.append('csv_file', selectedFile);

    updateOverlay('Converting UTM coordinates…', 40);

    try {
      const res = await fetch('../../backend/import/import_consumers.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
      });
      updateOverlay('Saving to database…', 80);
      result = await res.json();
    } catch (err) {
      hideOverlay();
      showToast('Network error: ' + err.message, 'error');
      btn.disabled = false; btn.textContent = '⬆ Import';
      return;
    }

  } else if (importType === 'billing_consumers') {
    const fd = new FormData();
    fd.append('csv_file', selectedFile);

    updateOverlay('Processing billing data…', 30, `Processing ${fileSizeMB} MB — this may take 1–2 minutes for large files`);

    // Animate progress bar while waiting since we have no real progress events
    let fakePct = 30;
    const fakeTimer = setInterval(() => {
      fakePct = Math.min(fakePct + 2, 90);
      updateOverlay('Importing rows into database…', fakePct);
    }, 2000);

    try {
      const res = await fetch('../../backend/import/import_billing_consumers.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
      });
      clearInterval(fakeTimer);
      updateOverlay('Finishing up…', 95);
      result = await res.json();
    } catch (err) {
      clearInterval(fakeTimer);
      hideOverlay();
      showToast('Network error: ' + err.message, 'error');
      btn.disabled = false; btn.textContent = '⬆ Import';
      return;
    }

  } else {
    updateOverlay('Processing rows…', 40);
    result = await new Promise(resolve => {
      const reader = new FileReader();
      reader.onload = async e => {
        const fd = new FormData();
        fd.append('action', 'import_csv');
        fd.append('table', importType);
        fd.append('csv_file', selectedFile);
        const res = await fetch('../../backend/api/utilities.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: fd,
        });
        resolve(await res.json());
      };
      reader.readAsText(selectedFile);
    });
  }

  updateOverlay('Done!', 100);
  setTimeout(hideOverlay, 600);

  btn.disabled    = false;
  btn.textContent = '⬆ Import';

  // ── Show result ───────────────────────────────────────────
  const resultEl = document.getElementById('importResult');
  resultEl.style.display = 'block';

  if (result?.success || result?.imported >= 0) {
    const imp     = result.imported  ?? 0;
    const skipped = result.skipped   ?? result.failed ?? 0;
    const total   = result.total_rows ?? (imp + skipped);

    resultEl.innerHTML = `
      <div style="background:rgba(0,200,150,.1);border:1px solid var(--accent3);border-radius:8px;padding:14px">
        <div style="font-weight:600;color:var(--accent3);margin-bottom:8px">✅ Import Complete</div>
        <div style="font-size:13px;display:grid;gap:3px">
          <div>Total rows: <b>${total}</b></div>
          <div>Imported / Updated: <b style="color:var(--accent3)">${imp}</b></div>
          <div>Skipped: <b style="color:${skipped ? 'var(--warn)' : 'var(--muted)'}">${skipped}</b></div>
        </div>
        ${result.errors?.length ? `
        <details style="margin-top:10px;font-size:12px">
          <summary style="cursor:pointer;color:var(--warn)">⚠️ ${result.errors.length} row errors (click to view)</summary>
          <ul style="margin-top:6px;padding-left:16px;color:var(--muted)">
            ${result.errors.map(e => `<li>${e}</li>`).join('')}
          </ul>
        </details>` : ''}
      </div>`;
    showToast(`Imported ${imp} / ${total} rows`, 'success');
    clearFile();
    loadHistory();
  } else {
    resultEl.innerHTML = `
      <div style="background:rgba(255,77,109,.1);border:1px solid var(--danger);border-radius:8px;padding:14px;color:var(--danger)">
        ❌ Import failed: ${result?.error || 'Unknown error'}
      </div>`;
    showToast(result?.error || 'Import failed', 'error');
  }
}

// ── Import history ────────────────────────────────────────────
async function loadHistory() {
  const d    = await apiGet('utilities.php', { action: 'get_imports' });
  const hist = d?.data || [];
  const el   = document.getElementById('importHistory');
  if (!hist.length) {
    el.innerHTML = '<p style="color:var(--muted);font-size:13px">No import history yet.</p>';
    return;
  }
  el.innerHTML = hist.slice(0, 12).map(h => `
    <div class="import-hist">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px">
        <span style="font-weight:600">${h.filename || 'Upload'}</span>
        <span style="font-size:11px;font-family:'Space Mono',monospace;color:${
          h.status === 'Completed' ? 'var(--accent3)'
          : h.status === 'Failed'  ? 'var(--danger)'
          :                          'var(--muted)'}">${h.status}</span>
      </div>
      <div style="font-size:11px;color:var(--muted)">
        ${h.table_target || '—'} &nbsp;·&nbsp;
        <span style="color:var(--accent3)">${h.imported_rows || 0} imported</span> &nbsp;·&nbsp;
        <span style="color:${h.failed_rows ? 'var(--warn)' : 'var(--muted)'}">${h.failed_rows || 0} failed</span>
        &nbsp;·&nbsp; ${h.uploaded_at || ''}
      </div>
      ${h.error_log ? `
      <div style="font-size:11px;color:var(--warn);margin-top:3px" title="${h.error_log}">
        ⚠️ Some rows had errors
      </div>` : ''}
    </div>`).join('');
}

document.addEventListener('DOMContentLoaded', loadHistory);
</script>