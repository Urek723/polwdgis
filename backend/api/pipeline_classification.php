<?php
// backend/api/pipeline_classification.php
// Water Pipeline Classification System — Full API
// Handles CRUD, history tracking, forecasting, and GIS data

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

requireAuth();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    // ── Pipeline CRUD ──────────────────────────────────────────────
    case 'get_pipelines':          getPipelines();          break;
    case 'get_pipeline':           getPipeline();           break;
    case 'save_pipeline':          savePipeline();          break;
    case 'delete_pipeline':        deletePipeline();        break;

    // ── History & Audit ────────────────────────────────────────────
    case 'get_history':            getPipelineHistory();    break;
    case 'log_change':             logManualChange();       break;

    // ── Forecasting & Alerts ───────────────────────────────────────
    case 'risk_assessment':        getRiskAssessment();     break;
    case 'generate_alerts':        generateAlerts();        break;
    case 'get_flagged':            getFlaggedPipelines();   break;
    case 'unflag':                 unflagPipeline();        break;
    case 'forecasting_summary':    getForecastingSummary(); break;

    // ── Maintenance Events ─────────────────────────────────────────
    case 'get_maintenance':        getMaintenanceEvents();  break;
    case 'save_maintenance':       saveMaintenanceEvent();  break;

    // ── Lookup Data ────────────────────────────────────────────────
    case 'get_zones':              getZones();              break;
    case 'get_stats':              getPipelineStats();      break;
    case 'get_geojson':            getPipelinesGeoJSON();   break;

    default: jsonResponse(['error' => 'Unknown action'], 400);
}

// ══════════════════════════════════════════════════════════════
// PIPELINE CRUD
// ══════════════════════════════════════════════════════════════

function getPipelines(): void {
    $db   = getDB();
    $type     = $_GET['pipeline_type']   ?? '';
    $material = $_GET['material']        ?? '';
    $status   = $_GET['status']          ?? '';
    $flagged  = $_GET['flagged']         ?? '';
    $barangay = $_GET['barangay']        ?? '';
    $zone_id  = intval($_GET['zone_id']  ?? 0);
    $search   = $_GET['search']          ?? '';

    $sql    = "SELECT p.*,
                      YEAR(NOW()) - YEAR(p.installation_date) AS age_years,
                      (SELECT COUNT(*) FROM pipeline_history ph 
                       WHERE ph.pipeline_id = p.id) AS history_count,
                      (SELECT COUNT(*) FROM pipeline_history ph2
                       WHERE ph2.pipeline_id = p.id 
                         AND ph2.change_type = 'status_change'
                         AND ph2.changed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                      ) AS status_changes_6mo
               FROM pipelines p
               WHERE 1=1";
    $params = [];

    if ($type)     { $sql .= " AND p.pipeline_type = ?"; $params[] = $type; }
    if ($material) { $sql .= " AND p.material = ?";      $params[] = $material; }
    if ($status)   { $sql .= " AND p.status = ?";        $params[] = $status; }
    if ($flagged)  { $sql .= " AND p.is_flagged = ?";    $params[] = intval($flagged); }
    if ($barangay) { $sql .= " AND p.barangay LIKE ?";   $params[] = "%$barangay%"; }
    if ($zone_id)  { $sql .= " AND p.zone_id = ?";       $params[] = $zone_id; }
    if ($search)   { $sql .= " AND (p.name LIKE ? OR p.barangay LIKE ? OR p.notes LIKE ?)";
                     $s = "%$search%"; $params = array_merge($params, [$s, $s, $s]); }

    $sql .= " ORDER BY p.is_flagged DESC, p.status_change_count DESC, p.name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Decode GeoJSON
    foreach ($rows as &$r) {
        if ($r['path_geojson'] && is_string($r['path_geojson'])) {
            $r['path_geojson'] = json_decode($r['path_geojson'], true);
        }
    }

    jsonResponse(['data' => $rows, 'total' => count($rows)]);
}

function getPipeline(): void {
    $id  = intval($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID required'], 422);

    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT p.*,
                YEAR(NOW()) - YEAR(p.installation_date) AS age_years,
                pz.zone_name
         FROM pipelines p
         LEFT JOIN pipeline_zones pz ON pz.id = p.zone_id
         WHERE p.id = ?"
    );
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if (!$p) jsonResponse(['error' => 'Not found'], 404);

    if ($p['path_geojson'] && is_string($p['path_geojson'])) {
        $p['path_geojson'] = json_decode($p['path_geojson'], true);
    }

    // Attach maintenance events
    $me = $db->prepare(
        "SELECT pme.*, u.name AS performed_by_name
         FROM pipeline_maintenance_events pme
         LEFT JOIN users u ON u.id = pme.performed_by
         WHERE pme.pipeline_id = ?
         ORDER BY pme.event_date DESC"
    );
    $me->execute([$id]);
    $p['maintenance_events'] = $me->fetchAll();

    jsonResponse(['data' => $p]);
}

function savePipeline(): void {
    requireRole('Admin', 'Staff');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    // Validate required
    $required = ['name', 'pipeline_type', 'material', 'status'];
    foreach ($required as $f) {
        if (empty($data[$f])) jsonResponse(['error' => "Field '$f' is required"], 422);
    }

    // Validate enums
    $validTypes     = ['Transmission', 'Distribution', 'Service Line'];
    $validMaterials = ['PVC', 'HDPE', 'Steel', 'GI', 'GIP', 'PE', 'SSP', 'CLCC Steel', 'PVC-O', 'UPBC', 'other'];
    $validStatuses  = ['active', 'inactive', 'rehabilitation', 'new'];
    $validPressure  = ['Low', 'Medium', 'High', 'Very High'];
    $validCondition = ['Excellent', 'Good', 'Fair', 'Poor', 'Critical'];

    if (!in_array($data['pipeline_type'], $validTypes)) {
        jsonResponse(['error' => 'Invalid pipeline type'], 422);
    }
    if (!in_array($data['material'], $validMaterials)) {
        jsonResponse(['error' => 'Invalid material'], 422);
    }
    if (!in_array($data['status'], $validStatuses)) {
        jsonResponse(['error' => 'Invalid status'], 422);
    }

    // Build common fields
    $fields = [
        'name'                    => $data['name'],
        'pipeline_type'           => $data['pipeline_type'],
        'material'                => $data['material'],
        'diameter_mm'             => $data['diameter_mm']             ?? null,
        'pressure_class'          => in_array($data['pressure_class'] ?? '', $validPressure)
                                     ? $data['pressure_class'] : 'Medium',
        'length_m'                => $data['length_m']                ?? null,
        'status'                  => $data['status'],
        'installation_date'       => $data['installation_date']       ?? null,
        'last_inspection_date'    => $data['last_inspection_date']    ?? null,
        'condition_rating'        => in_array($data['condition_rating'] ?? '', $validCondition)
                                     ? $data['condition_rating'] : 'Good',
        'flow_rate_lps'           => $data['flow_rate_lps']           ?? null,
        'operating_pressure_bar'  => $data['operating_pressure_bar']  ?? null,
        'max_pressure_bar'        => $data['max_pressure_bar']        ?? null,
        'coating'                 => $data['coating']                 ?? null,
        'joint_type'              => $data['joint_type']              ?? null,
        'zone_id'                 => $data['zone_id']                 ?? null,
        'barangay'                => $data['barangay']                ?? '',
        'notes'                   => $data['notes']                   ?? '',
        'path_geojson'            => isset($data['path_geojson'])
                                     ? json_encode($data['path_geojson']) : null,
    ];

    if (!empty($data['id'])) {
        // ── UPDATE ────────────────────────────────────────────────
        $id = intval($data['id']);

        // Snapshot BEFORE update for diff logging
        $before = $db->prepare("SELECT * FROM pipelines WHERE id = ?");
        $before->execute([$id]);
        $old = $before->fetch();

        $setClauses = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($fields)));
        $stmt = $db->prepare("UPDATE pipelines SET $setClauses WHERE id = ?");
        $stmt->execute([...array_values($fields), $id]);

        // Manual change log (in addition to trigger)
        $changedFields = [];
        foreach (['name', 'pipeline_type', 'material', 'diameter_mm', 'status', 'pressure_class', 'condition_rating'] as $f) {
            $oldVal = (string)($old[$f] ?? '');
            $newVal = (string)($data[$f] ?? '');
            if ($oldVal !== $newVal && $newVal !== '') {
                $changedFields[] = "$f: $oldVal → $newVal";
                // Explicit PHP-level log (trigger also fires)
                $h = $db->prepare(
                    "INSERT INTO pipeline_history 
                     (pipeline_id, changed_by, change_type, field_changed, old_value, new_value, reason, ip_address)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $h->execute([
                    $id, $_SESSION['user_id'],
                    match($f) {
                        'status'   => 'status_change',
                        'material' => 'material_change',
                        'diameter_mm' => 'diameter_change',
                        'path_geojson' => 'path_change',
                        default    => 'other',
                    },
                    $f, $oldVal, $newVal,
                    $data['reason'] ?? 'Updated via pipeline manager',
                    $_SERVER['REMOTE_ADDR'] ?? ''
                ]);
            }
        }

        logActivity(
            $_SESSION['user_id'], 'update_pipeline', 'pipelines', (string)$id,
            implode('; ', $changedFields) ?: 'Pipeline updated'
        );

        jsonResponse(['success' => true, 'id' => $id]);

    } else {
        // ── INSERT ────────────────────────────────────────────────
        $fields['created_by'] = $_SESSION['user_id'];
        $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($fields)));
        $phs  = implode(', ', array_fill(0, count($fields), '?'));
        $stmt = $db->prepare("INSERT INTO pipelines ($cols) VALUES ($phs)");
        $stmt->execute(array_values($fields));
        $id = $db->lastInsertId();

        // Log creation
        $h = $db->prepare(
            "INSERT INTO pipeline_history
             (pipeline_id, changed_by, change_type, field_changed, old_value, new_value, reason, ip_address)
             VALUES (?, ?, 'other', 'creation', '', ?, 'New pipeline created', ?)"
        );
        $h->execute([$id, $_SESSION['user_id'], $data['name'], $_SERVER['REMOTE_ADDR'] ?? '']);

        logActivity($_SESSION['user_id'], 'create_pipeline', 'pipelines', (string)$id, $data['name']);

        jsonResponse(['success' => true, 'id' => $id]);
    }
}

function deletePipeline(): void {
    requireRole('Admin');
    $id = intval($_POST['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID required'], 422);

    $db = getDB();
    // Log before delete
    $h = $db->prepare(
        "INSERT INTO pipeline_history
         (pipeline_id, changed_by, change_type, field_changed, old_value, new_value, reason)
         VALUES (?, ?, 'other', 'deletion', 'active', 'deleted', 'Deleted by administrator')"
    );
    $h->execute([$id, $_SESSION['user_id']]);

    $db->prepare("DELETE FROM pipelines WHERE id = ?")->execute([$id]);
    logActivity($_SESSION['user_id'], 'delete_pipeline', 'pipelines', (string)$id, 'Pipeline deleted');
    jsonResponse(['success' => true]);
}

// ══════════════════════════════════════════════════════════════
// HISTORY & AUDIT
// ══════════════════════════════════════════════════════════════

function getPipelineHistory(): void {
    $pid      = intval($_GET['pipeline_id'] ?? 0);
    $field    = $_GET['field'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo   = $_GET['date_to']   ?? '';
    $limit    = min(500, intval($_GET['limit'] ?? 200));

    $db  = getDB();
    $sql = "SELECT ph.*,
                   u.name  AS changed_by_name,
                   u.role  AS changed_by_role
            FROM pipeline_history ph
            LEFT JOIN users u ON u.id = ph.changed_by
            WHERE 1=1";
    $params = [];

    if ($pid)     { $sql .= " AND ph.pipeline_id = ?";  $params[] = $pid; }
    if ($field)   { $sql .= " AND ph.field_changed = ?"; $params[] = $field; }
    if ($dateFrom){ $sql .= " AND ph.changed_at >= ?";  $params[] = $dateFrom . ' 00:00:00'; }
    if ($dateTo)  { $sql .= " AND ph.changed_at <= ?";  $params[] = $dateTo   . ' 23:59:59'; }

    $sql .= " ORDER BY ph.changed_at DESC LIMIT $limit";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function logManualChange(): void {
    requireRole('Admin', 'Staff');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    if (empty($data['pipeline_id'])) jsonResponse(['error' => 'pipeline_id required'], 422);
    if (empty($data['field_changed'])) jsonResponse(['error' => 'field_changed required'], 422);

    $stmt = $db->prepare(
        "INSERT INTO pipeline_history
         (pipeline_id, changed_by, change_type, field_changed, old_value, new_value, reason, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $data['pipeline_id'],
        $_SESSION['user_id'],
        $data['change_type'] ?? 'other',
        $data['field_changed'],
        $data['old_value']   ?? '',
        $data['new_value']   ?? '',
        $data['reason']      ?? '',
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
}

// ══════════════════════════════════════════════════════════════
// FORECASTING & RISK ASSESSMENT
// ══════════════════════════════════════════════════════════════

function getRiskAssessment(): void {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT * FROM v_pipeline_risk_assessment
         ORDER BY risk_score DESC
         LIMIT 100"
    );
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function generateAlerts(): void {
    requireRole('Admin', 'Staff');
    $db = getDB();
    $db->exec("CALL sp_generate_pipeline_alerts()");

    // Also create deterioration_alerts for flagged pipelines
    $stmt = $db->query(
        "SELECT p.id, p.name, p.material, p.installation_date, p.flag_reason,
                p.status_change_count,
                YEAR(NOW()) - YEAR(p.installation_date) AS age_years
         FROM pipelines p
         WHERE p.is_flagged = 1
           AND p.id NOT IN (
             SELECT pipeline_id FROM deterioration_alerts
             WHERE is_resolved = 0 AND pipeline_id IS NOT NULL
           )"
    );
    $flagged = $stmt->fetchAll();
    $created = 0;

    foreach ($flagged as $p) {
        $severity = 'Medium';
        if ($p['status_change_count'] >= 5 || ($p['age_years'] ?? 0) >= 25) $severity = 'Critical';
        elseif ($p['status_change_count'] >= 3 || ($p['age_years'] ?? 0) >= 20) $severity = 'High';

        $ins = $db->prepare(
            "INSERT INTO deterioration_alerts
             (pipeline_id, alert_type, severity, description, installation_date, age_years)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $ins->execute([
            $p['id'],
            'Pipeline Classification Alert',
            $severity,
            $p['flag_reason'] ?? 'Pipeline requires inspection',
            $p['installation_date'],
            $p['age_years']
        ]);
        $created++;
    }

    logActivity($_SESSION['user_id'], 'generate_pipeline_alerts', 'pipelines', '', "Generated $created alerts");
    jsonResponse(['success' => true, 'alerts_created' => $created, 'flagged_count' => count($flagged)]);
}

function getFlaggedPipelines(): void {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT p.*,
                YEAR(NOW()) - YEAR(p.installation_date) AS age_years,
                (SELECT COUNT(*) FROM pipeline_history ph
                 WHERE ph.pipeline_id = p.id
                   AND ph.change_type = 'status_change'
                   AND ph.changed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                ) AS status_changes_6mo
         FROM pipelines p
         WHERE p.is_flagged = 1
         ORDER BY p.status_change_count DESC, p.id ASC"
    );
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function unflagPipeline(): void {
    requireRole('Admin', 'Staff');
    $id = intval($_POST['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID required'], 422);

    $db = getDB();
    $db->prepare("UPDATE pipelines SET is_flagged = 0, flag_reason = NULL WHERE id = ?")
       ->execute([$id]);

    $h = $db->prepare(
        "INSERT INTO pipeline_history
         (pipeline_id, changed_by, change_type, field_changed, old_value, new_value, reason)
         VALUES (?, ?, 'other', 'is_flagged', '1', '0', 'Flag cleared by staff')"
    );
    $h->execute([$id, $_SESSION['user_id']]);

    logActivity($_SESSION['user_id'], 'unflag_pipeline', 'pipelines', (string)$id, 'Flag cleared');
    jsonResponse(['success' => true]);
}

function getForecastingSummary(): void {
    $db = getDB();

    // Age distribution by material
    $ageStmt = $db->query(
        "SELECT material,
                COUNT(*) AS count,
                AVG(YEAR(NOW()) - YEAR(installation_date)) AS avg_age,
                MAX(YEAR(NOW()) - YEAR(installation_date)) AS max_age,
                SUM(CASE WHEN YEAR(NOW()) - YEAR(installation_date) >= 20 THEN 1 ELSE 0 END) AS critical_age_count
         FROM pipelines
         WHERE installation_date IS NOT NULL
         GROUP BY material"
    );

    // Status change frequency — top pipelines
    $freqStmt = $db->query(
        "SELECT p.id, p.name, p.pipeline_type, p.material, p.status,
                p.status_change_count,
                COUNT(ph.id) AS changes_6mo
         FROM pipelines p
         JOIN pipeline_history ph ON ph.pipeline_id = p.id
           AND ph.change_type = 'status_change'
           AND ph.changed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY p.id
         ORDER BY changes_6mo DESC
         LIMIT 10"
    );

    // Condition summary
    $condStmt = $db->query(
        "SELECT condition_rating, COUNT(*) AS count
         FROM pipelines
         GROUP BY condition_rating"
    );

    // Type + material matrix
    $matrixStmt = $db->query(
        "SELECT pipeline_type, material, COUNT(*) AS count,
                AVG(diameter_mm) AS avg_diameter
         FROM pipelines
         GROUP BY pipeline_type, material
         ORDER BY pipeline_type, count DESC"
    );

    jsonResponse([
        'age_by_material'         => $ageStmt->fetchAll(),
        'high_frequency_changes'  => $freqStmt->fetchAll(),
        'condition_summary'       => $condStmt->fetchAll(),
        'type_material_matrix'    => $matrixStmt->fetchAll(),
    ]);
}

// ══════════════════════════════════════════════════════════════
// MAINTENANCE EVENTS
// ══════════════════════════════════════════════════════════════

function getMaintenanceEvents(): void {
    $pid  = intval($_GET['pipeline_id'] ?? 0);
    $db   = getDB();
    $sql  = "SELECT pme.*, u.name AS performed_by_name,
                    p.name AS pipeline_name
             FROM pipeline_maintenance_events pme
             LEFT JOIN users u ON u.id = pme.performed_by
             LEFT JOIN pipelines p ON p.id = pme.pipeline_id
             WHERE 1=1";
    $params = [];
    if ($pid) { $sql .= " AND pme.pipeline_id = ?"; $params[] = $pid; }
    $sql .= " ORDER BY pme.event_date DESC LIMIT 200";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function saveMaintenanceEvent(): void {
    requireRole('Admin', 'Staff');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    if (empty($data['pipeline_id'])) jsonResponse(['error' => 'pipeline_id required'], 422);
    if (empty($data['event_type']))  jsonResponse(['error' => 'event_type required'], 422);
    if (empty($data['event_date']))  jsonResponse(['error' => 'event_date required'], 422);

    $stmt = $db->prepare(
        "INSERT INTO pipeline_maintenance_events
         (pipeline_id, event_type, event_date, description, cost_php, work_order_id,
          findings, performed_by, next_due_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $data['pipeline_id'],
        $data['event_type'],
        $data['event_date'],
        $data['description']  ?? '',
        $data['cost_php']     ?? null,
        $data['work_order_id'] ?? null,
        $data['findings']     ?? '',
        $_SESSION['user_id'],
        $data['next_due_date'] ?? null,
    ]);

    // Update last inspection date on pipeline
    if ($data['event_type'] === 'Inspection') {
        $db->prepare(
            "UPDATE pipelines SET last_inspection_date = ? WHERE id = ?"
        )->execute([$data['event_date'], $data['pipeline_id']]);
    }

    // Log to pipeline history
    $h = $db->prepare(
        "INSERT INTO pipeline_history
         (pipeline_id, changed_by, change_type, field_changed, new_value, reason)
         VALUES (?, ?, 'other', 'maintenance_event', ?, ?)"
    );
    $h->execute([
        $data['pipeline_id'],
        $_SESSION['user_id'],
        $data['event_type'],
        $data['description'] ?? ''
    ]);

    jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
}

// ══════════════════════════════════════════════════════════════
// LOOKUP & GIS
// ══════════════════════════════════════════════════════════════

function getZones(): void {
    $db   = getDB();
    $stmt = $db->query("SELECT * FROM pipeline_zones ORDER BY zone_code");
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function getPipelineStats(): void {
    $db   = getDB();

    $totals = $db->query(
        "SELECT
           COUNT(*)                                                   AS total,
           SUM(status = 'active')                                     AS active,
           SUM(status = 'inactive')                                   AS inactive,
           SUM(status = 'rehabilitation')                             AS rehabilitation,
           SUM(status = 'new')                                        AS new_pipelines,
           SUM(is_flagged = 1)                                        AS flagged,
           SUM(pipeline_type = 'Transmission')                        AS transmission,
           SUM(pipeline_type = 'Distribution')                        AS distribution,
           SUM(pipeline_type = 'Service Line')                        AS service_line,
           ROUND(AVG(diameter_mm), 1)                                 AS avg_diameter_mm,
           ROUND(SUM(length_m) / 1000, 2)                             AS total_length_km,
           SUM(YEAR(NOW()) - YEAR(installation_date) >= 20)           AS aging_count
         FROM pipelines"
    )->fetch();

    $materialBreakdown = $db->query(
        "SELECT material, COUNT(*) AS count,
                ROUND(AVG(diameter_mm), 1) AS avg_diameter
         FROM pipelines GROUP BY material ORDER BY count DESC"
    )->fetchAll();

    $statusTrend = $db->query(
        "SELECT
           DATE_FORMAT(changed_at, '%Y-%m') AS month,
           change_type,
           COUNT(*) AS changes
         FROM pipeline_history
         WHERE changed_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
         GROUP BY month, change_type
         ORDER BY month"
    )->fetchAll();

    jsonResponse([
        'totals'             => $totals,
        'material_breakdown' => $materialBreakdown,
        'status_trend'       => $statusTrend,
    ]);
}

function getPipelinesGeoJSON(): void {
    $type     = $_GET['pipeline_type'] ?? '';
    $status   = $_GET['status']        ?? '';
    $material = $_GET['material']      ?? '';
    $flagged  = $_GET['flagged']       ?? '';

    $db   = getDB();
    $sql  = "SELECT id, name, pipeline_type, material, diameter_mm, status,
                    pressure_class, condition_rating, is_flagged, barangay,
                    path_geojson, installation_date,
                    YEAR(NOW()) - YEAR(installation_date) AS age_years
             FROM pipelines
             WHERE path_geojson IS NOT NULL";
    $params = [];

    if ($type)     { $sql .= " AND pipeline_type = ?"; $params[] = $type; }
    if ($status)   { $sql .= " AND status = ?";         $params[] = $status; }
    if ($material) { $sql .= " AND material = ?";       $params[] = $material; }
    if ($flagged)  { $sql .= " AND is_flagged = ?";     $params[] = intval($flagged); }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $features = [];
    foreach ($rows as $r) {
        $geom = $r['path_geojson'] ? json_decode($r['path_geojson'], true) : null;
        if (!$geom) continue;
        $features[] = [
            'type'       => 'Feature',
            'geometry'   => $geom,
            'properties' => [
                'id'              => $r['id'],
                'name'            => $r['name'],
                'pipeline_type'   => $r['pipeline_type'],
                'material'        => $r['material'],
                'diameter_mm'     => $r['diameter_mm'],
                'status'          => $r['status'],
                'pressure_class'  => $r['pressure_class'],
                'condition_rating'=> $r['condition_rating'],
                'is_flagged'      => (bool)$r['is_flagged'],
                'barangay'        => $r['barangay'],
                'age_years'       => $r['age_years'],
            ],
        ];
    }

    jsonResponse([
        'type'     => 'FeatureCollection',
        'features' => $features,
    ]);
}