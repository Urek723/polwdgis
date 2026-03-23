<?php
$pageTitle = 'Equipment History';
require_once 'layout.php';
?>
<style>
.hist-row { background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; padding: 14px; margin-bottom: 8px; }
.action-installed  { color: var(--accent3); }
.action-repaired   { color: var(--accent); }
.action-replaced   { color: var(--warn); }
.action-inspected  { color: var(--text2); }
</style>

<main class="main">

<div style="display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
  <?php if(in_array($_SESSION['role'], ['Admin', 'Staff'])): ?>
  <button class="btn-primary" onclick="openModal('mhist')">+ Add History Entry</button>
  <?php endif; ?>
  <select id="fAction" onchange="load()" class="filter-input">
    <option value="">All Actions</option>
    <option>installed</option><option>repaired</option><option>replaced</option><option>inspected</option>
  </select>
  <input id="fFrom" type="date" onchange="load()" class="filter-input">
  <input id="fTo"   type="date" onchange="load()" class="filter-input">
  <input id="fq" placeholder="Search equipment, notes…" oninput="load()" class="filter-input" style="flex:1;min-width:180px">
</div>

<div id="histList"><div class="spinner"></div></div>

<!-- Add History Modal -->
<div id="mhist" class="modal-overlay">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header"><h3>Add Equipment History</h3><button onclick="closeModal('mhist')">✕</button></div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
      <input id="hEquipId" type="number" placeholder="Equipment ID *" class="form-input">
      <select id="hAction" class="form-input">
        <option value="installed">Installed</option>
        <option value="repaired">Repaired</option>
        <option value="replaced">Replaced</option>
        <option value="inspected">Inspected</option>
      </select>
      <input id="hDate" type="date" class="form-input">
      <textarea id="hNotes" placeholder="Notes" class="form-input" rows="3"></textarea>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal('mhist')" class="btn-secondary">Cancel</button>
        <button onclick="submitHistory()" class="btn-primary">Save</button>
      </div>
    </div>
  </div>
</div>

</main>

<script>
async function load() {
  const action = document.getElementById('fAction').value;
  const from   = document.getElementById('fFrom').value;
  const to     = document.getElementById('fTo').value;
  const q      = document.getElementById('fq').value;

  const d    = await apiGet('equipment_history.php', { action: 'get_history', filter_action: action, from_date: from, to_date: to, search: q });
  const list = d?.data || [];
  const el   = document.getElementById('histList');

  if (!list.length) {
    el.innerHTML = '<p style="color:var(--muted);padding:20px 0">No equipment history found.</p>';
    return;
  }

  const icons = { installed: '🔧', repaired: '🛠️', replaced: '🔄', inspected: '🔍' };
  el.innerHTML = list.map(h => `
    <div class="hist-row">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
        <div style="display:flex;align-items:center;gap:8px">
          <span style="font-size:18px">${icons[h.action] || '📋'}</span>
          <span class="action-${h.action}" style="font-weight:600;text-transform:capitalize">${h.action}</span>
          <span style="font-size:12px;color:var(--muted)">Equipment #${h.equipment_id}</span>
        </div>
        <span style="font-size:12px;color:var(--muted)">${h.date || '—'}</span>
      </div>
      ${h.notes ? `<p style="font-size:13px;color:var(--text2);line-height:1.5">${h.notes}</p>` : ''}
    </div>`).join('');
}

async function submitHistory() {
  const equipId = document.getElementById('hEquipId').value;
  const action  = document.getElementById('hAction').value;
  const date    = document.getElementById('hDate').value;
  const notes   = document.getElementById('hNotes').value;

  if (!equipId) { showToast('Equipment ID is required', 'error'); return; }

  const r = await apiPost('equipment_history.php', { action: 'save_history', equipment_id: equipId, action_type: action, date, notes });
  if (r?.success) { closeModal('mhist'); load(); showToast('History entry saved', 'success'); }
  else showToast(r?.error || 'Failed', 'error');
}

document.addEventListener('DOMContentLoaded', load);
</script>