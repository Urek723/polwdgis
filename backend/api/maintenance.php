<?php
// backend/api/maintenance.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

requireAuth();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_work_orders':      getWorkOrders();       break;
    case 'get_work_order':       getWorkOrder();        break;
    case 'save_work_order':      saveWorkOrder();       break;
    case 'update_status':        updateWorkOrderStatus(); break;
    case 'add_update':           addWorkOrderUpdate();  break;
    case 'get_checklist':        getChecklist();        break;
    case 'toggle_checklist':     toggleChecklist();     break;
    case 'get_schedules':        getSchedules();        break;
    case 'save_schedule':        saveSchedule();        break;
    case 'get_alerts':           getDeteriorationAlerts(); break;
    case 'generate_alerts':      generateDeteriorationAlerts(); break;
    case 'resolve_alert':        resolveAlert();        break;
    case 'get_inventory':        getInventory();        break;
    case 'save_inventory_item':  saveInventoryItem();   break;
    case 'inventory_transaction': inventoryTransaction(); break;
    case 'downtime_summary':     downtimeSummary();     break;
    default: jsonResponse(['error' => 'Unknown action'], 400);
}

function getWorkOrders(): void {
    $db     = getDB();
    $status = $_GET['status'] ?? '';
    $prio   = $_GET['priority'] ?? '';

    $sql    = "SELECT wo.*, u.name as assigned_name, c.name as creator_name
               FROM work_orders wo
               LEFT JOIN users u ON u.id = wo.assigned_to
               LEFT JOIN users c ON c.id = wo.created_by
               WHERE 1=1";
    $params = [];

    if ($status) { $sql .= " AND wo.status = ?"; $params[] = $status; }
    if ($prio)   { $sql .= " AND wo.priority = ?"; $params[] = $prio; }

    $sql .= " ORDER BY
              FIELD(wo.priority,'Critical','High','Medium','Low'),
              FIELD(wo.status,'Pending','In Progress','Completed','Cancelled'),
              wo.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function getWorkOrder(): void {
    $id   = intval($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID required'], 422);
    $db   = getDB();

    $stmt = $db->prepare(
        "SELECT wo.*, u.name as assigned_name
         FROM work_orders wo LEFT JOIN users u ON u.id=wo.assigned_to WHERE wo.id=?"
    );
    $stmt->execute([$id]);
    $wo = $stmt->fetch();
    if (!$wo) jsonResponse(['error' => 'Not found'], 404);

    // checklist
    $stmt2 = $db->prepare("SELECT * FROM work_order_checklist WHERE work_order_id=? ORDER BY id");
    $stmt2->execute([$id]);
    $wo['checklist'] = $stmt2->fetchAll();

    // updates
    $stmt3 = $db->prepare(
        "SELECT wou.*, u.name as updated_by_name
         FROM work_order_updates wou LEFT JOIN users u ON u.id=wou.updated_by
         WHERE wou.work_order_id=? ORDER BY wou.updated_at DESC"
    );
    $stmt3->execute([$id]);
    $wo['updates'] = $stmt3->fetchAll();

    jsonResponse(['data' => $wo]);
}

function saveWorkOrder(): void {
    requireRole('Admin', 'Staff');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    $required = ['title', 'type', 'priority'];
    foreach ($required as $f) {
        if (empty($data[$f])) jsonResponse(['error' => "Field '$f' required"], 422);
    }

    if (!empty($data['id'])) {
        $stmt = $db->prepare(
            "UPDATE work_orders SET title=?,description=?,type=?,priority=?,location=?,
             latitude=?,longitude=?,assigned_to=?,scheduled_date=?,cause=?,resolution=?
             WHERE id=?"
        );
        $stmt->execute([
            $data['title'], $data['description'] ?? '', $data['type'], $data['priority'],
            $data['location'] ?? '', $data['latitude'] ?? null, $data['longitude'] ?? null,
            $data['assigned_to'] ?? null, $data['scheduled_date'] ?? null,
            $data['cause'] ?? '', $data['resolution'] ?? '', $data['id']
        ]);
        $id = $data['id'];
    } else {
        $stmt = $db->prepare(
            "INSERT INTO work_orders
             (title,description,type,priority,status,location,latitude,longitude,
              assigned_to,scheduled_date,cause,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $data['title'], $data['description'] ?? '', $data['type'], $data['priority'],
            'Pending', $data['location'] ?? '', $data['latitude'] ?? null,
            $data['longitude'] ?? null, $data['assigned_to'] ?? null,
            $data['scheduled_date'] ?? null, $data['cause'] ?? '', $_SESSION['user_id']
        ]);
        $id = $db->lastInsertId();

        // Save checklist items if provided
        if (!empty($data['checklist']) && is_array($data['checklist'])) {
            $ci = $db->prepare("INSERT INTO work_order_checklist (work_order_id, item) VALUES (?,?)");
            foreach ($data['checklist'] as $item) {
                if (trim($item)) $ci->execute([$id, trim($item)]);
            }
        }
    }

    logActivity($_SESSION['user_id'], 'save_work_order', 'work_orders', (string)$id, $data['title']);
    jsonResponse(['success' => true, 'id' => $id]);
}

function updateWorkOrderStatus(): void {
    requireRole('Admin', 'Staff');
    $id     = intval($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (!$id || !$status) jsonResponse(['error' => 'id and status required'], 422);

    $db     = getDB();
    $extra  = '';
    $params = [$status];

    if ($status === 'In Progress') {
        $extra = ', started_at = NOW()';
    } elseif ($status === 'Completed') {
        $extra = ', completed_at = NOW()';
        // calculate downtime
        $wo = $db->prepare("SELECT started_at FROM work_orders WHERE id=?");
        $wo->execute([$id]);
        $row = $wo->fetch();
        if ($row && $row['started_at']) {
            $minutes = (time() - strtotime($row['started_at'])) / 60;
            $extra  .= ', downtime_minutes = ' . intval($minutes);
        }
    }

    $params[] = $id;
    $stmt = $db->prepare("UPDATE work_orders SET status=? $extra WHERE id=?");
    $stmt->execute($params);
    logActivity($_SESSION['user_id'], 'update_wo_status', 'work_orders', (string)$id, "Status: $status");
    jsonResponse(['success' => true]);
}

function addWorkOrderUpdate(): void {
    requireRole('Admin', 'Staff');
    $id   = intval($_POST['id'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    if (!$id || !$note) jsonResponse(['error' => 'id and note required'], 422);

    $db   = getDB();
    $stmt = $db->prepare(
        "INSERT INTO work_order_updates (work_order_id, note, status_change, updated_by)
         VALUES (?,?,?,?)"
    );
    $stmt->execute([$id, $note, $_POST['status_change'] ?? null, $_SESSION['user_id']]);
    jsonResponse(['success' => true]);
}

function getChecklist(): void {
    $id   = intval($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'work_order_id required'], 422);
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM work_order_checklist WHERE work_order_id=? ORDER BY id");
    $stmt->execute([$id]);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function toggleChecklist(): void {
    requireRole('Admin', 'Staff');
    $itemId = intval($_POST['item_id'] ?? 0);
    if (!$itemId) jsonResponse(['error' => 'item_id required'], 422);

    $db     = getDB();
    $stmt   = $db->prepare("SELECT is_done FROM work_order_checklist WHERE id=?");
    $stmt->execute([$itemId]);
    $row    = $stmt->fetch();
    if (!$row) jsonResponse(['error' => 'Not found'], 404);

    $isDone = $row['is_done'] ? 0 : 1;
    $stmt2  = $db->prepare("UPDATE work_order_checklist SET is_done=?, done_by=?, done_at=? WHERE id=?");
    $stmt2->execute([$isDone, $isDone ? $_SESSION['user_id'] : null, $isDone ? date('Y-m-d H:i:s') : null, $itemId]);
    jsonResponse(['success' => true, 'is_done' => $isDone]);
}

function getSchedules(): void {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT ms.*, u.name as assigned_name
         FROM maintenance_schedule ms
         LEFT JOIN users u ON u.id = ms.assigned_to
         WHERE ms.is_active = 1
         ORDER BY ms.next_due ASC"
    );
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function saveSchedule(): void {
    requireRole('Admin');
    $data     = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db       = getDB();

    if (!empty($data['id'])) {
        $stmt = $db->prepare(
            "UPDATE maintenance_schedule SET title=?,type=?,frequency=?,next_due=?,
             infrastructure_id=?,assigned_to=?,notes=? WHERE id=?"
        );
        $stmt->execute([
            $data['title'], $data['type'] ?? '', $data['frequency'], $data['next_due'],
            $data['infrastructure_id'] ?? null, $data['assigned_to'] ?? null,
            $data['notes'] ?? '', $data['id']
        ]);
    } else {
        $stmt = $db->prepare(
            "INSERT INTO maintenance_schedule
             (title,type,frequency,next_due,infrastructure_id,assigned_to,notes,created_by)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $data['title'], $data['type'] ?? '', $data['frequency'], $data['next_due'],
            $data['infrastructure_id'] ?? null, $data['assigned_to'] ?? null,
            $data['notes'] ?? '', $_SESSION['user_id']
        ]);
    }
    jsonResponse(['success' => true]);
}

function getDeteriorationAlerts(): void {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT da.*, p.name as pipeline_name, i.name as infra_name
         FROM deterioration_alerts da
         LEFT JOIN pipelines p ON p.id = da.pipeline_id
         LEFT JOIN infrastructure i ON i.id = da.infrastructure_id
         WHERE da.is_resolved = 0
         ORDER BY FIELD(da.severity,'Critical','High','Medium','Low'), da.created_at DESC"
    );
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function generateDeteriorationAlerts(): void {
    requireRole('Admin');
    $db      = getDB();
    $yearCut = intval(date('Y')) - 20; // flag pipelines older than 20 years

    // Pipelines
    $stmt = $db->prepare(
        "SELECT id, name, material, installation_date,
                YEAR(NOW()) - YEAR(installation_date) AS age_years
         FROM pipelines
         WHERE installation_date IS NOT NULL
           AND YEAR(NOW()) - YEAR(installation_date) >= 15
           AND id NOT IN (SELECT pipeline_id FROM deterioration_alerts WHERE is_resolved=0 AND pipeline_id IS NOT NULL)"
    );
    $stmt->execute();
    $pipes   = $stmt->fetchAll();
    $created = 0;
    foreach ($pipes as $p) {
        $severity = $p['age_years'] >= 25 ? 'Critical' : ($p['age_years'] >= 20 ? 'High' : 'Medium');
        $ins = $db->prepare(
            "INSERT INTO deterioration_alerts (pipeline_id,alert_type,severity,description,installation_date,age_years)
             VALUES (?,?,?,?,?,?)"
        );
        $ins->execute([
            $p['id'], 'Age Deterioration', $severity,
            "Pipeline '{$p['name']}' ({$p['material']}) is {$p['age_years']} years old and may need inspection.",
            $p['installation_date'], $p['age_years']
        ]);
        $created++;
    }

    // Infrastructure
    $stmt2 = $db->prepare(
        "SELECT id, name, type, installation_date,
                YEAR(NOW()) - YEAR(installation_date) AS age_years
         FROM infrastructure
         WHERE installation_date IS NOT NULL
           AND YEAR(NOW()) - YEAR(installation_date) >= 10
           AND id NOT IN (SELECT infrastructure_id FROM deterioration_alerts WHERE is_resolved=0 AND infrastructure_id IS NOT NULL)"
    );
    $stmt2->execute();
    foreach ($stmt2->fetchAll() as $infra) {
        $severity = $infra['age_years'] >= 20 ? 'High' : 'Medium';
        $ins2 = $db->prepare(
            "INSERT INTO deterioration_alerts (infrastructure_id,alert_type,severity,description,installation_date,age_years)
             VALUES (?,?,?,?,?,?)"
        );
        $ins2->execute([
            $infra['id'], 'Age Deterioration', $severity,
            "{$infra['type']} '{$infra['name']}' is {$infra['age_years']} years old.",
            $infra['installation_date'], $infra['age_years']
        ]);
        $created++;
    }

    jsonResponse(['success' => true, 'alerts_created' => $created]);
}

function resolveAlert(): void {
    requireRole('Admin', 'Staff');
    $id   = intval($_POST['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID required'], 422);
    $db   = getDB();
    $stmt = $db->prepare(
        "UPDATE deterioration_alerts SET is_resolved=1, resolved_by=?, resolved_at=NOW() WHERE id=?"
    );
    $stmt->execute([$_SESSION['user_id'], $id]);
    jsonResponse(['success' => true]);
}

function getInventory(): void {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT i.*,
                (SELECT SUM(CASE WHEN t.transaction_type='In' THEN t.quantity ELSE -t.quantity END)
                 FROM inventory_transactions t WHERE t.item_id=i.id) AS calc_qty
         FROM inventory_items i ORDER BY i.category, i.name"
    );
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function saveInventoryItem(): void {
    requireRole('Admin');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    if (!empty($data['id'])) {
        $stmt = $db->prepare(
            "UPDATE inventory_items SET name=?,category=?,unit=?,quantity_in_stock=?,
             reorder_level=?,unit_cost=?,supplier=?,notes=? WHERE id=?"
        );
        $stmt->execute([
            $data['name'], $data['category'] ?? '', $data['unit'] ?? '',
            $data['quantity_in_stock'] ?? 0, $data['reorder_level'] ?? 0,
            $data['unit_cost'] ?? 0, $data['supplier'] ?? '', $data['notes'] ?? '',
            $data['id']
        ]);
    } else {
        $stmt = $db->prepare(
            "INSERT INTO inventory_items (name,category,unit,quantity_in_stock,reorder_level,unit_cost,supplier,notes)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $data['name'], $data['category'] ?? '', $data['unit'] ?? '',
            $data['quantity_in_stock'] ?? 0, $data['reorder_level'] ?? 0,
            $data['unit_cost'] ?? 0, $data['supplier'] ?? '', $data['notes'] ?? ''
        ]);
    }
    jsonResponse(['success' => true]);
}

function inventoryTransaction(): void {
    requireRole('Admin', 'Staff');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    if (empty($data['item_id']) || empty($data['transaction_type']) || empty($data['quantity'])) {
        jsonResponse(['error' => 'item_id, transaction_type, quantity required'], 422);
    }

    $stmt = $db->prepare(
        "INSERT INTO inventory_transactions (item_id,transaction_type,quantity,reference,notes,transacted_by)
         VALUES (?,?,?,?,?,?)"
    );
    $stmt->execute([
        $data['item_id'], $data['transaction_type'], $data['quantity'],
        $data['reference'] ?? '', $data['notes'] ?? '', $_SESSION['user_id']
    ]);

    // Update stock
    $sign = $data['transaction_type'] === 'In' ? '+' : '-';
    if ($data['transaction_type'] === 'Adjustment') {
        $db->prepare("UPDATE inventory_items SET quantity_in_stock=? WHERE id=?")
           ->execute([$data['quantity'], $data['item_id']]);
    } else {
        $db->prepare("UPDATE inventory_items SET quantity_in_stock=quantity_in_stock $sign ? WHERE id=?")
           ->execute([$data['quantity'], $data['item_id']]);
    }

    jsonResponse(['success' => true]);
}

function downtimeSummary(): void {
    $db   = getDB();
    $year = intval($_GET['year'] ?? date('Y'));
    $stmt = $db->prepare(
        "SELECT
            MONTH(completed_at) AS month,
            COUNT(*) AS total_orders,
            SUM(downtime_minutes) AS total_downtime_min,
            AVG(downtime_minutes) AS avg_downtime_min,
            type
         FROM work_orders
         WHERE YEAR(completed_at)=? AND status='Completed'
         GROUP BY MONTH(completed_at), type
         ORDER BY month"
    );
    $stmt->execute([$year]);
    jsonResponse(['data' => $stmt->fetchAll(), 'year' => $year]);
}
