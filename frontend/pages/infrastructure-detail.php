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

// Pull related work orders by location or barangay match
$histStmt = $db->prepare(
    "SELECT wo.id, wo.title, wo.type, wo.status, wo.priority,
            wo.scheduled_date, wo.completed_at, wo.downtime_minutes,
            wo.cause, wo.resolution,
            u.name AS assigned_name
     FROM work_orders wo
     LEFT JOIN users u ON u.id = wo.assigned_to
     WHERE wo.location LIKE ?
        OR wo.location LIKE ?
     ORDER BY wo.created_at DESC
     LIMIT 50"
);
$barangaySearch = '%' . ($infra['barangay'] ?? '') . '%';
$nameSearch     = '%' . ($infra['name'] ?? '') . '%';
$histStmt->execute([$barangaySearch, $nameSearch]);
$history = $histStmt->fetchAll();

$workOrders = $history;

// Type metadata
$typeEmojis = [
    'pumping_station' => '🏗️', 'reservoir' => '🗄️', 'valve' => '🔧',
    'hydrant' => '🚒', 'blowoff' => '💨', 'meter_chamber' => '📊', 'other' => '📌',
];
$emoji     = $typeEmojis[$infra['type']] ?? '📌';
$typeLabel = ucwords(str_replace('_', ' ', $infra['type']));
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

<style>
.detail-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.detail-icon {
  width: 60px; 
  height: 60px;
  background: rgba(0,87,255,0.15);
  border: 1px solid rgba(0,87,255,0.3);
  border-radius: 14px;
  display: flex; 
  align-items: center; 
  justify-content: center;
  font-size: 28px;
  flex-shrink: 0;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
  margin-bottom: 24px;
}

.info-item {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 12px 14px;
}

.info-label { 
  font-size: 11px; 
  color: var(--muted); 
  text-transform: uppercase; 
  letter-spacing: .08em; 
  margin-bottom: 4px; 
}

.info-value { 
  font-size: 14px; 
  font-weight: 600; 
}

.wo-mini {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px 14px;
  margin-bottom: 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  font-size: 13px;
}

#detail-map {
  height: 280px;
  width: 100%;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--border);
}

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

/* Improved header responsiveness */
@media (max-width: 992px) {
  .detail-header {
    gap: 12px;
  }
}

@media (max-width: 768px) {
  .detail-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .detail-header > div[style*="margin-left:auto"] {
    margin-left: 0 !important;
    width: 100%;
    justify-content: flex-start;
  }
}
</style>

<main class="main">

<!-- Header -->
<div class="detail-header">
  <a href="infrastructure-list.php" class="btn-secondary" 
     style="font-size:13px;text-decoration:none;padding:7px 14px;border-radius:8px;border:1px solid var(--border);color:var(--text)">
    ← All Infrastructure
  </a>
  
  <div class="detail-icon"><?= $emoji ?></div>
  
  <div style="flex: 1; min-width: 240px;">
    <h1 style="font-size:22px;font-weight:700;line-height:1.2;margin:0">
      <?= htmlspecialchars($infra['name'] ?: $typeLabel) ?>
    </h1>
    <div style="font-size:13px;color:var(--text2);margin-top:3px">
      <?= $typeLabel ?>
    </div>
  </div>

  <span class="badge badge-<?= htmlspecialchars($infra['status']) ?>" 
        style="font-size:12px;padding:4px 10px;white-space:nowrap">
    <?= ucfirst($infra['status']) ?>
  </span>

  <?php if (in_array($_SESSION['role'], ['Admin', 'Staff'])): ?>
  <div style="margin-left:auto; display:flex; gap:8px; flex-shrink:0;">
    <a href="infrastructure-add.php?edit=<?= $id ?>" 
       class="btn-secondary" 
       style="font-size:13px;text-decoration:none;padding:8px 14px;border-radius:8px">
      ✏️ Edit
    </a>
    <button onclick="openModal('mAddHistory')" 
            class="btn-primary" 
            style="font-size:13px; white-space:nowrap">
      + Add Work Order
    </button>
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
    <div class="info-value" style="font-size:13px;font-weight:400">
      <?= htmlspecialchars($infra['address'] ?: $infra['notes'] ?: '—') ?>
    </div>
  </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 380px; gap: 24px; align-items: start;">

  <!-- LEFT: Tabs -->
  <div>
    <div class="card">
      <div class="tab-bar">
        <button class="tab-btn active" onclick="showTab('history', this)">🔧 Work Orders</button>
        <button class="tab-btn" onclick="showTab('workorders', this)">📋 Details</button>
      </div>

      <!-- Work Orders Tab -->
      <div class="tab-pane active" id="tab-history">
        <?php if (empty($history)): ?>
          <p style="color:var(--muted);font-size:13px;text-align:center;padding:40px 0">
            No related work orders found.
          </p>
        <?php else: ?>
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
              <tr>
                <th style="padding:8px 12px;text-align:left;font-size:11px;color:var(--muted);text-transform:uppercase;border-bottom:1px solid var(--border)">Title</th>
                <th style="padding:8px 12px;text-align:left;font-size:11px;color:var(--muted);text-transform:uppercase;border-bottom:1px solid var(--border)">Type</th>
                <th style="padding:8px 12px;text-align:left;font-size:11px;color:var(--muted);text-transform:uppercase;border-bottom:1px solid var(--border)">Status</th>
                <th style="padding:8px 12px;text-align:left;font-size:11px;color:var(--muted);text-transform:uppercase;border-bottom:1px solid var(--border)">Date</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $h): ?>
            <tr style="border-bottom:1px solid rgba(255,255,255,0.04)">
              <td style="padding:10px 12px;color:var(--text)"><?= htmlspecialchars($h['title']) ?></td>
              <td style="padding:10px 12px;color:var(--text2)"><?= htmlspecialchars($h['type']) ?></td>
              <td style="padding:10px 12px">
                <span class="badge badge-<?= strtolower(str_replace(' ', '-', $h['status'])) ?>">
                  <?= htmlspecialchars($h['status']) ?>
                </span>
              </td>
              <td style="padding:10px 12px;font-size:12px;color:var(--muted)">
                <?= htmlspecialchars($h['scheduled_date'] ?? $h['completed_at'] ?? '—') ?>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- Details Tab -->
      <div class="tab-pane" id="tab-workorders">
        <?php if (empty($workOrders)): ?>
          <p style="color:var(--muted);font-size:13px;text-align:center;padding:40px 0">
            No related work orders found.
          </p>
        <?php else: ?>
          <?php foreach ($workOrders as $wo): ?>
          <div class="wo-mini">
            <div style="flex:1">
              <div style="font-weight:600"><?= htmlspecialchars($wo['title']) ?></div>
              <div style="font-size:11px;color:var(--muted)">
                <?= htmlspecialchars($wo['type']) ?> · <?= htmlspecialchars($wo['assigned_name'] ?? '—') ?>
              </div>
              <?php if (!empty($wo['cause'])): ?>
              <div style="font-size:11px;color:var(--warn);margin-top:2px">⚠️ <?= htmlspecialchars($wo['cause']) ?></div>
              <?php endif; ?>
              <?php if (!empty($wo['resolution'])): ?>
              <div style="font-size:11px;color:var(--accent3);margin-top:2px">✅ <?= htmlspecialchars($wo['resolution']) ?></div>
              <?php endif; ?>
            </div>
            <div style="display:flex;gap:6px;align-items:center;flex-shrink:0">
              <span class="badge badge-<?= strtolower(str_replace(' ', '-', $wo['priority'])) ?>"><?= htmlspecialchars($wo['priority']) ?></span>
              <span class="badge badge-<?= strtolower(str_replace(' ', '-', $wo['status'])) ?>"><?= htmlspecialchars($wo['status']) ?></span>
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

<!-- Add Work Order Modal -->
<?php if (in_array($_SESSION['role'], ['Admin', 'Staff'])): ?>
<div id="mAddHistory" class="modal-overlay">
  <div class="modal-box" style="max-width:440px">
    <div class="modal-header">
      <h3>Add Work Order</h3>
      <button onclick="closeModal('mAddHistory')">✕</button>
    </div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
      <div>
        <label class="form-label">Title *</label>
        <input type="text" id="woTitle" class="form-input" placeholder="e.g. Valve inspection">
      </div>
      <div>
        <label class="form-label">Type</label>
        <select id="woType" class="form-input">
          <option>Valve</option>
          <option>Pump</option>
          <option>Reservoir</option>
          <option>Mainline</option>
          <option>Serviceline</option>
          <option>Electrical</option>
          <option>Other</option>
        </select>
      </div>
      <div>
        <label class="form-label">Priority</label>
        <select id="woPriority" class="form-input">
          <option>Medium</option>
          <option>Low</option>
          <option>High</option>
          <option>Critical</option>
        </select>
      </div>
      <div>
        <label class="form-label">Scheduled Date</label>
        <input type="date" id="woDate" class="form-input">
      </div>
      <div>
        <label class="form-label">Description</label>
        <textarea id="woDesc" class="form-input" rows="3" placeholder="Details about the work to be done…"></textarea>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal('mAddHistory')" class="btn-secondary">Cancel</button>
        <button onclick="submitWorkOrder()" class="btn-primary">Create Work Order</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// Tab switching
function showTab(id, btn) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + id).classList.add('active');
  btn.classList.add('active');
}

// Mini map
<?php if ($infra['latitude'] && $infra['longitude']): ?>
(function () {
  const lat = <?= (float)$infra['latitude'] ?>;
  const lng = <?= (float)$infra['longitude'] ?>;
  
  const m = L.map('detail-map', {
    center: [lat, lng],
    zoom: 16,
    zoomControl: true,
    attributionControl: false,
  });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
    maxZoom: 20 
  }).addTo(m);

  const icon = L.divIcon({
    className: '',
    html: '<div style="font-size:28px;filter:drop-shadow(0 3px 5px rgba(0,0,0,.6));"><?= $emoji ?></div>',
    iconSize: [28, 28],
    iconAnchor: [14, 28],
  });

  L.marker([lat, lng], { icon })
   .addTo(m)
   .bindPopup('<?= addslashes(htmlspecialchars($infra['name'] ?: $typeLabel)) ?>')
   .openPopup();
})();
<?php endif; ?>

// Submit work order
async function submitWorkOrder() {
  const title = document.getElementById('woTitle').value.trim();
  if (!title) {
    showToast('Title is required', 'error');
    return;
  }

  const r = await apiPost('maintenance.php', {
    action:         'save_work_order',
    title,
    description:    document.getElementById('woDesc').value,
    type:           document.getElementById('woType').value,
    priority:       document.getElementById('woPriority').value,
    scheduled_date: document.getElementById('woDate').value,
    location:       <?= json_encode($infra['name'] . ' — ' . ($infra['barangay'] ?? '')) ?>,
    latitude:       <?= json_encode($infra['latitude'] ?? '') ?>,
    longitude:      <?= json_encode($infra['longitude'] ?? '') ?>,
  });

  if (r.success) {
    showToast('Work order created successfully', 'success');
    closeModal('mAddHistory');
    setTimeout(() => window.location.reload(), 800);
  } else {
    showToast(r.error || 'Failed to create work order', 'error');
  }
}
</script>
</main>