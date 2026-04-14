<?php
$pageTitle = 'Infrastructure';
require_once 'layout.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: infrastructure-list.php');
    exit;
}

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM infrastructure WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$infra = $stmt->fetch();
if (!$infra) {
    header('Location: infrastructure-list.php');
    exit;
}

$pageTitle = htmlspecialchars($infra['name'] ?: $infra['type']);

// Pull maintenance/equipment history for this infrastructure
$histStmt = $db->prepare(
    "SELECT eh.*, 'equipment_history' AS source_table
     FROM equipment_history eh
     WHERE eh.equipment_id = ?
     ORDER BY eh.date DESC, eh.id DESC
     LIMIT 50"
);
$histStmt->execute([$id]);
$history = $histStmt->fetchAll();

// Also pull related work orders
$woStmt = $db->prepare(
    "SELECT wo.id, wo.title, wo.type, wo.priority, wo.status,
            wo.scheduled_date, wo.completed_at, wo.downtime_minutes,
            u.name AS assigned_name
     FROM work_orders wo
     LEFT JOIN users u ON u.id = wo.assigned_to
     WHERE LOWER(wo.location) LIKE ?
     ORDER BY wo.created_at DESC
     LIMIT 20"
);
$woStmt->execute(['%' . strtolower($infra['barangay'] ?? '') . '%']);
$workOrders = $woStmt->fetchAll();

// Type metadata
$typeEmojis = [
    'pumping_station' => '🏗️', 'reservoir' => '🗄️', 'valve' => '🔧',
    'hydrant' => '🚒', 'blowoff' => '💨', 'meter_chamber' => '📊', 'other' => '📌',
];
$emoji = $typeEmojis[$infra['type']] ?? '📌';
$typeLabel = ucwords(str_replace('_', ' ', $infra['type']));
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

<style>
.detail-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.detail-icon {
  width: 60px; height: 60px;
  background: rgba(0,87,255,0.15);
  border: 1px solid rgba(0,87,255,0.3);
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  font-size: 28px;
  flex-shrink: 0;
}

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
  padding: 12px 14px;
}
.info-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; margin-bottom: 4px; }
.info-value { font-size: 14px; font-weight: 600; }

/* History table */
.hist-table-wrap { overflow-x: auto; }
.action-installed { color: var(--accent3); }
.action-repaired  { color: var(--accent); }
.action-replaced  { color: var(--warn); }
.action-inspected { color: var(--text2); }

/* Work orders summary */
.wo-mini {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px 14px;
  margin-bottom: 6px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  font-size: 13px;
}

/* Mini map */
#detail-map {
  height: 280px;
  width: 100%;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--border);
}

/* Tabs */
.tab-bar {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 16px;
}
.tab-btn {
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
.tab-btn.active {
  color: var(--accent);
  border-bottom-color: var(--accent);
}
.tab-pane { display: none; }
.tab-pane.active { display: block; }
</style>

<main class="main">

<!-- Header -->
<div class="detail-header">
  <a href="infrastructure-list.php" class="btn-secondary" style="font-size:13px;text-decoration:none;padding:7px 14px;border-radius:8px;border:1px solid var(--border);color:var(--text)">← All Infrastructure</a>
  <div class="detail-icon"><?= $emoji ?></div>
  <div>
    <h1 style="font-size:22px;font-weight:700;line-height:1.2"><?= htmlspecialchars($infra['name'] ?: $typeLabel) ?></h1>
    <div style="font-size:13px;color:var(--text2);margin-top:3px"><?= $typeLabel ?></div>
  </div>
  <span class="badge badge-<?= htmlspecialchars($infra['status']) ?>" style="font-size:12px;padding:4px 10px"><?= ucfirst($infra['status']) ?></span>
  <?php if (in_array($_SESSION['role'], ['Admin', 'Staff'])): ?>
  <div style="margin-left:auto;display:flex;gap:8px">
    <a href="infrastructure-add.php?edit=<?= $id ?>" class="btn-secondary" style="font-size:13px;text-decoration:none;padding:8px 14px;border-radius:8px">✏️ Edit</a>
    <button onclick="openModal('mAddHistory')" class="btn-primary" style="font-size:13px">+ Add History</button>
  </div>
  <?php endif; ?>
</div>

<!-- Info grid -->
<div class="info-grid">
  <div class="info-item">
    <div class="info-label">Barangay</div>
    <div class="info-value"><?= htmlspecialchars($infra['barangay'] ?: '—') ?></div>
  </div>
  <div class="info-item">
    <div class="info-label">Installed</div>
    <div class="info-value"><?= $infra['installation_date'] ? date('M j, Y', strtotime($infra['installation_date'])) : '—' ?></div>
  </div>
  <div class="info-item">
    <div class="info-label">Last Inspection</div>
    <div class="info-value"><?= $infra['last_inspection'] ? date('M j, Y', strtotime($infra['last_inspection'])) : '—' ?></div>
  </div>
  <div class="info-item">
    <div class="info-label">Coordinates</div>
    <div class="info-value" style="font-size:12px;font-family:'Space Mono',monospace">
      <?= ($infra['latitude'] && $infra['longitude'])
        ? number_format($infra['latitude'], 6) . ', ' . number_format($infra['longitude'], 6)
        : '—' ?>
    </div>
  </div>
  <div class="info-item" style="grid-column:1/-1">
    <div class="info-label">Address / Notes</div>
    <div class="info-value" style="font-size:13px;font-weight:400"><?= htmlspecialchars($infra['address'] ?: $infra['notes'] ?: '—') ?></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

  <!-- LEFT: Tabs -->
  <div>
    <div class="card">
      <div class="tab-bar">
        <button class="tab-btn active" onclick="showTab('history', this)">🔧 History Log</button>
        <button class="tab-btn" onclick="showTab('workorders', this)">📋 Work Orders</button>
      </div>

      <!-- History Tab -->
      <div class="tab-pane active" id="tab-history">
        <?php if (empty($history)): ?>
          <p style="color:var(--muted);font-size:13px;text-align:center;padding:20px 0">No maintenance history recorded yet.</p>
        <?php else: ?>
        <div class="hist-table-wrap">
          <table>
            <thead>
              <tr>
                <th>Action</th>
                <th>Date</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody>
            <?php
            $actionIcons = ['installed'=>'🔧','repaired'=>'🛠️','replaced'=>'🔄','inspected'=>'🔍'];
            foreach ($history as $h):
              $icon = $actionIcons[$h['action']] ?? '📋';
            ?>
            <tr>
              <td>
                <span class="action-<?= htmlspecialchars($h['action']) ?>" style="font-weight:600">
                  <?= $icon ?> <?= ucfirst(htmlspecialchars($h['action'])) ?>
                </span>
              </td>
              <td style="white-space:nowrap;color:var(--muted);font-size:12px"><?= htmlspecialchars($h['date'] ?? '—') ?></td>
              <td style="font-size:12px;color:var(--text2)"><?= htmlspecialchars($h['notes'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- Work Orders Tab -->
      <div class="tab-pane" id="tab-workorders">
        <?php if (empty($workOrders)): ?>
          <p style="color:var(--muted);font-size:13px;text-align:center;padding:20px 0">No related work orders found.</p>
        <?php else: ?>
          <?php foreach ($workOrders as $wo): ?>
          <div class="wo-mini">
            <div>
              <div style="font-weight:600"><?= htmlspecialchars($wo['title']) ?></div>
              <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($wo['type']) ?> · <?= htmlspecialchars($wo['assigned_name'] ?? '—') ?></div>
            </div>
            <div style="display:flex;gap:6px;align-items:center;flex-shrink:0">
              <span class="badge badge-<?= strtolower(str_replace(' ','-',$wo['priority'])) ?>"><?= htmlspecialchars($wo['priority']) ?></span>
              <span class="badge badge-<?= strtolower(str_replace(' ','-',$wo['status'])) ?>"><?= htmlspecialchars($wo['status']) ?></span>
              <a href="work-orders.php?id=<?= $wo['id'] ?>" style="font-size:11px;color:var(--accent);text-decoration:none">View →</a>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- RIGHT: Mini map -->
  <div>
    <div class="card">
      <div class="card-title">📍 Location</div>
      <?php if ($infra['latitude'] && $infra['longitude']): ?>
      <div id="detail-map"></div>
      <div style="font-size:11px;color:var(--muted);margin-top:8px;font-family:'Space Mono',monospace">
        <?= number_format($infra['latitude'], 7) ?>, <?= number_format($infra['longitude'], 7) ?>
      </div>
      <?php else: ?>
      <div style="height:280px;display:flex;align-items:center;justify-content:center;background:var(--surface2);border-radius:10px;border:1px solid var(--border)">
        <p style="color:var(--muted);font-size:13px">No coordinates recorded</p>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Add History Modal -->
<?php if (in_array($_SESSION['role'], ['Admin', 'Staff'])): ?>
<div id="mAddHistory" class="modal-overlay">
  <div class="modal-box" style="max-width:440px">
    <div class="modal-header">
      <h3>Add History Entry</h3>
      <button onclick="closeModal('mAddHistory')">✕</button>
    </div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
      <div>
        <label class="form-label">Action *</label>
        <select id="hAction" class="form-input">
          <option value="installed">🔧 Installed</option>
          <option value="repaired">🛠️ Repaired</option>
          <option value="replaced">🔄 Replaced</option>
          <option value="inspected">🔍 Inspected</option>
        </select>
      </div>
      <div>
        <label class="form-label">Date *</label>
        <input type="date" id="hDate" class="form-input" value="<?= date('Y-m-d') ?>">
      </div>
      <div>
        <label class="form-label">Notes</label>
        <textarea id="hNotes" class="form-input" rows="3" placeholder="What was done? Parts replaced? Findings?"></textarea>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal('mAddHistory')" class="btn-secondary">Cancel</button>
        <button onclick="submitHistory()" class="btn-primary">Save Entry</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// ── Tab switching ─────────────────────────────────────────────
function showTab(id, btn) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + id).classList.add('active');
  btn.classList.add('active');
}

// ── Mini map (only when coords exist) ────────────────────────
<?php if ($infra['latitude'] && $infra['longitude']): ?>
(function() {
  const lat  = <?= (float)$infra['latitude'] ?>;
  const lng  = <?= (float)$infra['longitude'] ?>;
  const m    = L.map('detail-map', { center: [lat, lng], zoom: 16, zoomControl: true, attributionControl: false });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20 }).addTo(m);
  const icon = L.divIcon({
    className: '',
    html: '<div style="font-size:28px;filter:drop-shadow(0 3px 5px rgba(0,0,0,.6));"><?= $emoji ?></div>',
    iconSize:   [28, 28],
    iconAnchor: [14, 28],
  });
  L.marker([lat, lng], { icon })
   .addTo(m)
   .bindPopup('<?= addslashes($infra['name'] ?: $typeLabel) ?>')
   .openPopup();
})();
<?php endif; ?>

// ── Add history entry ─────────────────────────────────────────
async function submitHistory() {
  const action = document.getElementById('hAction').value;
  const date   = document.getElementById('hDate').value;
  const notes  = document.getElementById('hNotes').value;
  if (!date) { showToast('Date is required', 'error'); return; }

  const r = await apiPost('equipment_history.php', {
    action:       'save_history',
    equipment_id: <?= $id ?>,
    action_type:  action,
    date,
    notes,
  });

  if (r.success) {
    showToast('History entry saved', 'success');
    closeModal('mAddHistory');
    setTimeout(() => window.location.reload(), 800);
  } else {
    showToast(r.error || 'Failed', 'error');
  }
}
</script>