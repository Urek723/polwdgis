<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';


requireAuth();


$wo_id       = intval($_GET['id']          ?? 0);
$staffInput  = trim(urldecode($_GET['staff']        ?? ''));
$teamLeader  = trim(urldecode($_GET['team_leader']  ?? ''));
$preparedBy  = trim(urldecode($_GET['prepared_by']  ?? ($_SESSION['name'] ?? '')));
$approvedBy  = trim(urldecode($_GET['approved_by']  ?? ''));
$instructions = trim(urldecode($_GET['instructions'] ?? ''));

if (!$wo_id) {
    header('Location: work-orders.php');
    exit;
}

// ── Database fetch ────────────────────────────────────────────
$db   = getDB();
$stmt = $db->prepare(
    "SELECT wo.*, u.name AS assigned_name
     FROM work_orders wo
     LEFT JOIN users u ON u.id = wo.assigned_to
     WHERE wo.id = ?"
);
$stmt->execute([$wo_id]);
$wo = $stmt->fetch();

if (!$wo) {
    header('Location: work-orders.php');
    exit;
}

// Checklist
$clStmt = $db->prepare(
    "SELECT * FROM work_order_checklist WHERE work_order_id = ? ORDER BY id"
);
$clStmt->execute([$wo_id]);
$checklist = $clStmt->fetchAll();

// Updates
$updStmt = $db->prepare(
    "SELECT wou.*, u.name AS updated_by_name
     FROM work_order_updates wou
     LEFT JOIN users u ON u.id = wou.updated_by
     WHERE wou.work_order_id = ?
     ORDER BY wou.updated_at ASC"
);
$updStmt->execute([$wo_id]);
$updates = $updStmt->fetchAll();

// ── Derived values ────────────────────────────────────────────
$generatedDate = date('F j, Y');
$generatedTime = date('g:i A');
$docNumber     = 'WO-' . str_pad($wo_id, 5, '0', STR_PAD_LEFT) . '-' . date('Y');

// Build staff list — merge URL param with system-assigned name
$staffList = array_filter(array_map('trim', explode(',', $staffInput)));
if (
    !empty($wo['assigned_name']) &&
    !in_array(trim($wo['assigned_name']), $staffList)
) {
    array_unshift($staffList, trim($wo['assigned_name']));
}

// Safe fallback for preparedBy
if ($preparedBy === '') {
    $preparedBy = $_SESSION['name'] ?? 'System';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Work Order Document — <?= htmlspecialchars($docNumber) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════════
   SCREEN STYLES
══════════════════════════════════════════════════ */
@media screen {
  body {
    background: #0c1120;
    font-family: 'Sora', sans-serif;
    margin: 0;
    padding: 0;
  }
  .screen-controls {
    position: fixed;
    top: 0; left: 0; right: 0;
    background: #111827;
    border-bottom: 1px solid #1e2d40;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 100;
    flex-wrap: wrap;
  }
  .ctrl-btn {
    padding: 8px 18px;
    border-radius: 9px;
    border: none;
    font-family: 'Sora', sans-serif;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity .15s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .ctrl-primary   { background: linear-gradient(135deg,#0057ff,#00d4ff); color: #fff; }
  .ctrl-secondary { background: #1a2436; border: 1px solid #1e2d40; color: #e2eaf4; }
  .ctrl-btn:hover { opacity: .85; }
  .ctrl-info {
    font-size: 12px;
    color: #4a5a72;
    margin-left: auto;
  }
  .doc-wrapper {
    margin-top: 70px;
    padding: 32px 24px 60px;
    display: flex;
    justify-content: center;
  }
  .document {
    width: 210mm;
    min-height: 297mm;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 8px 40px rgba(0,0,0,.5);
    padding: 20mm 18mm;
    color: #111;
    box-sizing: border-box;
  }
}

/* ══════════════════════════════════════════════════
   PRINT STYLES
══════════════════════════════════════════════════ */
@media print {
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    box-sizing: border-box !important;
  }

  .screen-controls,
  .no-print {
    display: none !important;
    visibility: hidden !important;
    height: 0 !important;
    overflow: hidden !important;
    position: absolute !important;
    left: -9999px !important;
  }

  /* ── Page setup ── */
  @page {
    size: A4 portrait;
    margin: 8mm 15mm;
  }

  html, body {
    width: 210mm;
    margin: 0 !important;
    padding: 0 !important;
    background: #fff !important;
    font-family: 'Sora', Arial, sans-serif !important;
    color: #000 !important;
    font-size: 9pt !important;
    /* Prevent browser scaling that causes reflow */
    -webkit-text-size-adjust: none !important;
    text-size-adjust: none !important;
  }

  .doc-wrapper {
    margin: 0 !important;
    padding: 0 !important;
    display: block !important;
    width: 100% !important;
  }

  .document {
    width: 100% !important;
    /* Remove fixed height — let content determine height naturally */
    min-height: unset !important;
    height: auto !important;
    padding: 0 !important;
    margin: 0 !important;
    box-shadow: none !important;
    border-radius: 0 !important;
    color: #000 !important;
    background: #fff !important;
  }

  /* ── Scale down text slightly so everything fits ── */
  .doc-org        { font-size: 14pt !important; }
  .doc-org-sub    { font-size: 8pt  !important; }
  .doc-title      { font-size: 12pt !important; }
  .doc-number-row { font-size: 8pt  !important; }

  .doc-section-title { font-size: 7.5pt !important; margin-bottom: 6px !important; }
  .doc-section       { margin-bottom: 10px !important; }

  .field-label  { font-size: 7pt   !important; }
  .field-value  { font-size: 8.5pt !important; min-height: 12px !important; }

  .desc-box {
    font-size: 8.5pt !important;
    padding: 5px 8px !important;
    min-height: unset !important;
  }

  .checklist-item { font-size: 8pt !important; padding: 3px 0 !important; }
  .update-item    { font-size: 8pt !important; padding: 3px 0 !important; }
  .update-meta    { font-size: 7pt !important; }

  .staff-pill { font-size: 8pt !important; padding: 1px 7px !important; }

  .field-grid   { gap: 4px 16px !important; }
  .field-grid-3 { gap: 4px 12px !important; }

  /* ── Signature section ── */
  .sig-grid  { gap: 12px  !important; margin-top: 6px !important; }
  .sig-line  { height: 28px !important; }
  .sig-label { font-size: 7pt !important; }
  .sig-name  { font-size: 8pt !important; }

  /* ── Footer ── */
  .doc-footer {
    margin-top: 12px !important;
    font-size: 7pt  !important;
  }

  /* ── Badge sizes ── */
  .badge-doc { font-size: 7.5pt !important; padding: 1px 6px !important; }

  .page-break { page-break-before: always; }

  a { color: inherit !important; text-decoration: none !important; }
}

/* ══════════════════════════════════════════════════
   DOCUMENT CONTENT STYLES (screen + print)
══════════════════════════════════════════════════ */

/* Header */
.doc-header {
  text-align: center;
  border-bottom: 3px solid #0057ff;
  padding-bottom: 14px;
  margin-bottom: 20px;
}
.doc-org {
  font-size: 18pt;
  font-weight: 700;
  color: #0057ff;
  letter-spacing: -.01em;
  line-height: 1.2;
}
.doc-org-sub {
  font-size: 9pt;
  color: #555;
  margin-top: 2px;
  letter-spacing: .04em;
}
.doc-title {
  font-size: 15pt;
  font-weight: 700;
  color: #111;
  margin-top: 10px;
  text-transform: uppercase;
  letter-spacing: .08em;
}
.doc-number-row {
  display: flex;
  justify-content: space-between;
  font-size: 9pt;
  color: #555;
  margin-top: 6px;
}
.doc-number-row span { font-weight: 600; color: #222; }

/* Sections */
.doc-section { margin-bottom: 16px; }
.doc-section-title {
  font-size: 8.5pt;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .12em;
  color: #0057ff;
  border-bottom: 1px solid #c8d8f0;
  padding-bottom: 4px;
  margin-bottom: 10px;
}

/* Field grid */
.field-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px 20px;
}
.field-grid-3 {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 6px 16px;
}
.field-item {}
.field-label {
  font-size: 7.5pt;
  color: #666;
  text-transform: uppercase;
  letter-spacing: .06em;
  margin-bottom: 2px;
}
.field-value {
  font-size: 10pt;
  font-weight: 600;
  color: #111;
  border-bottom: 1px solid #ddd;
  padding-bottom: 2px;
  min-height: 16px;
  word-break: break-word;
}
.field-value.blank {
  color: #bbb;
  font-weight: 400;
  font-style: italic;
}

/* Priority / status badges */
.badge-doc {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 8.5pt;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
}
.badge-Critical    { background: #dc262622; color: #dc2626; border: 1px solid #dc2626; }
.badge-High        { background: #ea580c22; color: #c2410c; border: 1px solid #c2410c; }
.badge-Medium      { background: #ca8a0422; color: #92400e; border: 1px solid #b45309; }
.badge-Low         { background: #16a34a22; color: #15803d; border: 1px solid #16a34a; }
.badge-Pending     { background: #71717a22; color: #52525b; border: 1px solid #71717a; }
.badge-In-Progress { background: #0ea5e922; color: #0369a1; border: 1px solid #0ea5e9; }
.badge-Completed   { background: #16a34a22; color: #15803d; border: 1px solid #16a34a; }
.badge-Cancelled   { background: #dc262622; color: #dc2626; border: 1px solid #dc2626; }

/* Description box */
.desc-box {
  background: #f7f9fc;
  border: 1px solid #d8e4f0;
  border-radius: 4px;
  padding: 8px 10px;
  font-size: 10pt;
  color: #222;
  line-height: 1.55;
  min-height: 40px;
}

/* Checklist */
.checklist-item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 5px 0;
  border-bottom: 1px solid #eee;
  font-size: 9.5pt;
}
.cl-box {
  width: 12px; height: 12px;
  border: 1.5px solid #555;
  border-radius: 2px;
  flex-shrink: 0;
  margin-top: 1px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 9px;
}
.cl-done { background: #0057ff; border-color: #0057ff; color: #fff; }

/* Updates */
.update-item {
  padding: 5px 0;
  border-bottom: 1px solid #eee;
  font-size: 9pt;
}
.update-meta { font-size: 8pt; color: #888; margin-top: 2px; }

/* Staff pills */
.staff-list {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 4px;
}
.staff-pill {
  background: #e8effe;
  color: #0057ff;
  border: 1px solid #c5d6fb;
  border-radius: 20px;
  padding: 2px 10px;
  font-size: 9pt;
  font-weight: 600;
}

/* Coordinates */
.coord-box {
  font-size: 8.5pt;
  color: #555;
  font-family: 'Courier New', monospace;
  background: #f4f6fb;
  border: 1px solid #d8e4f0;
  border-radius: 3px;
  padding: 2px 6px;
  display: inline-block;
  margin-top: 2px;
}

/* Signature section */
.sig-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 20px;
  margin-top: 8px;
}
.sig-box { text-align: center; }
.sig-line {
  border-bottom: 1.5px solid #333;
  margin-bottom: 4px;
  height: 40px;
}
.sig-label {
  font-size: 8pt;
  color: #555;
  text-transform: uppercase;
  letter-spacing: .06em;
}
.sig-name {
  font-size: 9pt;
  font-weight: 600;
  color: #111;
  margin-top: 2px;
}

/* Footer */
.doc-footer {
  border-top: 2px solid #0057ff;
  margin-top: 24px;
  padding-top: 8px;
  display: flex;
  justify-content: space-between;
  font-size: 7.5pt;
  color: #888;
}
</style>
</head>
<body>

<!-- ── SCREEN CONTROLS ──────────────────────────────────────── -->
<div class="screen-controls">
  <a href="work-orders.php" class="ctrl-btn ctrl-secondary">← Back</a>
  <button class="ctrl-btn ctrl-primary"   onclick="window.print()">🖨 Print Document</button>
  <button class="ctrl-btn ctrl-secondary" onclick="downloadHTML()">⬇ Save as HTML</button>
  <span class="ctrl-info">
    Document No: <strong><?= htmlspecialchars($docNumber) ?></strong>
    &nbsp;·&nbsp;
    Generated: <?= $generatedDate ?> <?= $generatedTime ?>
  </span>
</div>

<!-- ── DOCUMENT ─────────────────────────────────────────────── -->
<div class="doc-wrapper">
<div class="document" id="printDocument">

  <!-- HEADER -->
  <div class="doc-header">
    <div class="doc-org">Polomolok Water District</div>
    <div class="doc-org-sub">
      Municipal Compound, Polomolok, South Cotabato
      &nbsp;|&nbsp; (083) 123-4567
    </div>
    <div class="doc-title">Work Order / Service Order</div>
    <div class="doc-number-row">
      <div>Document No: <span><?= htmlspecialchars($docNumber) ?></span></div>
      <div>Date: <span><?= $generatedDate ?></span></div>
      <div>Time: <span><?= $generatedTime ?></span></div>
    </div>
  </div>

  <!-- WORK ORDER DETAILS -->
  <div class="doc-section">
    <div class="doc-section-title">Work Order Details</div>
    <div class="field-grid-3" style="margin-bottom:10px">
      <div class="field-item">
        <div class="field-label">Work Order ID</div>
        <div class="field-value">#<?= str_pad($wo['id'], 5, '0', STR_PAD_LEFT) ?></div>
      </div>
      <div class="field-item">
        <div class="field-label">Priority</div>
        <div class="field-value">
          <span class="badge-doc badge-<?= htmlspecialchars($wo['priority']) ?>">
            <?= htmlspecialchars($wo['priority']) ?>
          </span>
        </div>
      </div>
      <div class="field-item">
        <div class="field-label">Status</div>
        <div class="field-value">
          <?php $statusClass = str_replace(' ', '-', $wo['status']); ?>
          <span class="badge-doc badge-<?= htmlspecialchars($statusClass) ?>">
            <?= htmlspecialchars($wo['status']) ?>
          </span>
        </div>
      </div>
    </div>

    <div class="field-item" style="margin-bottom:10px">
      <div class="field-label">Work Order Title</div>
      <div class="field-value" style="font-size:12pt">
        <?= htmlspecialchars($wo['title']) ?>
      </div>
    </div>

    <?php if (!empty($wo['description'])): ?>
    <div class="field-item" style="margin-bottom:10px">
      <div class="field-label">Description / Scope of Work</div>
      <div class="desc-box"><?= nl2br(htmlspecialchars($wo['description'])) ?></div>
    </div>
    <?php endif; ?>

    <div class="field-grid">
      <div class="field-item">
        <div class="field-label">Work Type</div>
        <div class="field-value"><?= htmlspecialchars($wo['type'] ?? '—') ?></div>
      </div>
      <div class="field-item">
        <div class="field-label">Scheduled Date</div>
        <div class="field-value <?= empty($wo['scheduled_date']) ? 'blank' : '' ?>">
          <?= !empty($wo['scheduled_date'])
            ? date('F j, Y', strtotime($wo['scheduled_date']))
            : 'Not scheduled' ?>
        </div>
      </div>
    </div>
  </div>

  <!-- LOCATION -->
  <div class="doc-section">
    <div class="doc-section-title">Location</div>
    <div class="field-grid">
      <div class="field-item">
        <div class="field-label">Address / Description</div>
        <div class="field-value <?= empty($wo['location']) ? 'blank' : '' ?>">
          <?= !empty($wo['location']) ? htmlspecialchars($wo['location']) : 'Not specified' ?>
        </div>
      </div>
      <div class="field-item">
        <div class="field-label">GPS Coordinates</div>
        <div class="field-value">
          <?php if (!empty($wo['latitude']) && !empty($wo['longitude'])): ?>
            <span class="coord-box">
              <?= number_format((float)$wo['latitude'], 6) ?>,
              <?= number_format((float)$wo['longitude'], 6) ?>
            </span>
          <?php else: ?>
            <span class="blank">No coordinates recorded</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- TEAM ASSIGNMENT -->
  <div class="doc-section">
    <div class="doc-section-title">Team Assignment</div>
    <div class="field-grid">
      <div class="field-item">
        <div class="field-label">Assigned Staff / Team Members</div>
        <?php if (!empty($staffList)): ?>
          <div class="staff-list">
            <?php foreach ($staffList as $s): ?>
              <span class="staff-pill"><?= htmlspecialchars($s) ?></span>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="field-value blank">— Not assigned —</div>
        <?php endif; ?>
      </div>
      <div class="field-item">
        <div class="field-label">Team Leader / Supervisor</div>
        <div class="field-value <?= empty($teamLeader) ? 'blank' : '' ?>">
          <?= !empty($teamLeader) ? htmlspecialchars($teamLeader) : '—' ?>
        </div>
      </div>
    </div>
  </div>

  <!-- INSTRUCTIONS -->
  <?php if (!empty($instructions) || !empty($wo['cause'])): ?>
  <div class="doc-section">
    <div class="doc-section-title">Instructions / Remarks</div>
    <?php if (!empty($instructions)): ?>
      <div class="desc-box" style="margin-bottom:8px">
        <?= nl2br(htmlspecialchars($instructions)) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($wo['cause'])): ?>
      <div class="field-item" style="margin-top:6px">
        <div class="field-label">Root Cause</div>
        <div class="desc-box"><?= nl2br(htmlspecialchars($wo['cause'])) ?></div>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- CHECKLIST -->
  <?php if (!empty($checklist)): ?>
  <div class="doc-section">
    <div class="doc-section-title">Work Checklist</div>
    <?php foreach ($checklist as $item): ?>
    <div class="checklist-item">
      <div class="cl-box <?= $item['is_done'] ? 'cl-done' : '' ?>">
        <?= $item['is_done'] ? '✓' : '' ?>
      </div>
      <span style="<?= $item['is_done'] ? 'text-decoration:line-through;color:#999' : '' ?>">
        <?= htmlspecialchars($item['item']) ?>
      </span>
      <?php if ($item['is_done'] && !empty($item['done_by'])): ?>
        <span style="margin-left:auto;font-size:8pt;color:#888">
          Done by: <?= htmlspecialchars($item['done_by']) ?>
        </span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- UPDATES / FIELD NOTES -->
  <?php if (!empty($updates)): ?>
  <div class="doc-section">
    <div class="doc-section-title">Field Updates / Notes</div>
    <?php foreach ($updates as $u): ?>
    <div class="update-item">
      <div><?= nl2br(htmlspecialchars($u['note'])) ?></div>
      <div class="update-meta">
        <?= htmlspecialchars($u['updated_by_name'] ?? 'System') ?>
        &nbsp;·&nbsp;
        <?= htmlspecialchars($u['updated_at'] ?? '') ?>
        <?php if (!empty($u['status_change'])): ?>
          &nbsp;·&nbsp; Status changed to:
          <strong><?= htmlspecialchars($u['status_change']) ?></strong>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- TIME RECORD -->
  <?php if (!empty($wo['started_at']) || !empty($wo['completed_at']) || !empty($wo['downtime_minutes'])): ?>
  <div class="doc-section">
    <div class="doc-section-title">Time Record</div>
    <div class="field-grid-3">
      <div class="field-item">
        <div class="field-label">Date Started</div>
        <div class="field-value <?= empty($wo['started_at']) ? 'blank' : '' ?>">
          <?= !empty($wo['started_at'])
            ? date('M j, Y g:i A', strtotime($wo['started_at']))
            : '—' ?>
        </div>
      </div>
      <div class="field-item">
        <div class="field-label">Date Completed</div>
        <div class="field-value <?= empty($wo['completed_at']) ? 'blank' : '' ?>">
          <?= !empty($wo['completed_at'])
            ? date('M j, Y g:i A', strtotime($wo['completed_at']))
            : '—' ?>
        </div>
      </div>
      <div class="field-item">
        <div class="field-label">Total Downtime</div>
        <div class="field-value <?= empty($wo['downtime_minutes']) ? 'blank' : '' ?>">
          <?= !empty($wo['downtime_minutes'])
            ? intval($wo['downtime_minutes']) . ' minutes'
            : '—' ?>
        </div>
      </div>
    </div>
    <?php if (!empty($wo['resolution'])): ?>
    <div class="field-item" style="margin-top:8px">
      <div class="field-label">Resolution / Work Done</div>
      <div class="desc-box"><?= nl2br(htmlspecialchars($wo['resolution'])) ?></div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- CERTIFICATION & APPROVAL -->
  <div class="doc-section" style="margin-top:28px">
    <div class="doc-section-title">Certification &amp; Approval</div>
    <p style="font-size:8.5pt;color:#555;margin-bottom:14px;line-height:1.5">
      I/We hereby certify that the work described in this document has been carried out
      in accordance with the standards and procedures of Polomolok Water District.
    </p>
    <div class="sig-grid">
      <div class="sig-box">
        <div class="sig-line"></div>
        <div class="sig-label">Prepared By</div>
        <div class="sig-name"><?= htmlspecialchars($preparedBy) ?></div>
        <div style="font-size:7.5pt;color:#999">Date: _______________</div>
      </div>
      <div class="sig-box">
        <div class="sig-line"></div>
        <div class="sig-label">Team Leader / Field Supervisor</div>
        <div class="sig-name">
          <?= htmlspecialchars($teamLeader ?: '___________________') ?>
        </div>
        <div style="font-size:7.5pt;color:#999">Date: _______________</div>
      </div>
      <div class="sig-box">
        <div class="sig-line"></div>
        <div class="sig-label">Approved By</div>
        <div class="sig-name">
          <?= htmlspecialchars($approvedBy ?: '___________________') ?>
        </div>
        <div style="font-size:7.5pt;color:#999">Date: _______________</div>
      </div>
    </div>
  </div>

  <!-- DOCUMENT FOOTER -->
  <div class="doc-footer">
    <span>Polomolok Water District — Internal Document</span>
    <span>
      Document No: <?= htmlspecialchars($docNumber) ?>
      &nbsp;|&nbsp;
      Generated: <?= $generatedDate ?> <?= $generatedTime ?>
    </span>
    <span>Page 1 of 1</span>
  </div>

</div><!-- /.document -->
</div><!-- /.doc-wrapper -->

<script>
function downloadHTML() {
  const content = document.getElementById('printDocument').outerHTML;
  const styles  = [...document.querySelectorAll('style')]
                    .map(s => s.outerHTML).join('');
  const full = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Work Order <?= htmlspecialchars($docNumber, ENT_QUOTES) ?></title>
  ${styles}
</head>
<body>
  <div class="doc-wrapper">${content}</div>
</body>
</html>`;
  const blob = new Blob([full], { type: 'text/html;charset=utf-8' });
  const a    = document.createElement('a');
  a.href     = URL.createObjectURL(blob);
  a.download = '<?= htmlspecialchars($docNumber, ENT_QUOTES) ?>.html';
  a.click();
}
</script>
</body>
</html>