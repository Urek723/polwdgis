<?php
// backend/api/utilities.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

requireAuth();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'export_csv':      exportCsv();       break;
    case 'import_csv':      importCsv();       break;
    case 'get_logs':        getLogs();         break;
    case 'get_imports':     getImports();      break;
    case 'change_password': changePassword();  break;
    case 'get_users':       getUsers();        break;
    case 'save_user':       saveUser();        break;
    default: jsonResponse(['error' => 'Unknown action'], 400);
}

function exportCsv(): void {
    $table = $_GET['table'] ?? '';
    $db    = getDB();

    $allowed = [
        // ── consumers: full GIS export including UTM + WGS84 ──────────────────
        'consumers' =>
            "SELECT
                account_id, account_no, name, type, status,
                address, barangay, municipal, zone, book,
                contact_no, email,
                meter_brand, meter_number,
                x_utm, y_utm, elevation,
                latitude, longitude,
                created_at
             FROM consumers
             ORDER BY name",

        'consumption_records' =>
            "SELECT c.account_id, c.name, cr.billing_month,
                    cr.reading_prev, cr.reading_curr, cr.consumption_m3, cr.is_alert
             FROM consumption_records cr
             JOIN consumers c ON c.id = cr.consumer_id",

        'pipelines' =>
            "SELECT id, name, material, diameter_mm, status,
                    installation_date, barangay, notes
             FROM pipelines",

        'infrastructure' =>
            "SELECT id, type, name, latitude, longitude,
                    address, barangay, status, installation_date
             FROM infrastructure",

        'work_orders' =>
            "SELECT id, title, type, priority, status, location,
                    scheduled_date, completed_at, downtime_minutes
             FROM work_orders",

        'inventory_items' =>
            "SELECT id, name, category, unit, quantity_in_stock,
                    reorder_level, unit_cost, supplier
             FROM inventory_items",
    ];

    if (!isset($allowed[$table])) {
        jsonResponse(['error' => 'Invalid table'], 400);
    }

    $stmt = $db->query($allowed[$table]);
    $rows = $stmt->fetchAll();

    if (!$rows) {
        jsonResponse(['success' => true, 'csv_base64' => base64_encode(''), 'row_count' => 0,
                      'filename' => $table . '_' . date('Ymd_His') . '.csv']);
    }

    $headers = array_keys($rows[0]);
    $csv     = implode(',', $headers) . "\n";
    foreach ($rows as $row) {
        $escaped = array_map(function ($v) {
            return '"' . str_replace('"', '""', (string) $v) . '"';
        }, $row);
        $csv .= implode(',', $escaped) . "\n";
    }

    jsonResponse([
        'success'    => true,
        'filename'   => $table . '_' . date('Ymd_His') . '.csv',
        'csv_base64' => base64_encode($csv),
        'row_count'  => count($rows),
    ]);
}

function importCsv(): void {
    requireRole('Admin');

    if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'No file uploaded or upload error'], 422);
    }
    if (!in_array($_FILES['csv_file']['type'], ['text/csv', 'application/csv', 'text/plain'])) {
        jsonResponse(['error' => 'Only CSV files are accepted'], 422);
    }

    $table   = $_POST['table'] ?? '';
    $allowed = ['consumers', 'consumption_records', 'pipelines', 'inventory_items'];
    if (!in_array($table, $allowed)) {
        jsonResponse(['error' => 'Invalid target table'], 400);
    }

    $tmpPath  = $_FILES['csv_file']['tmp_name'];
    $filename = basename($_FILES['csv_file']['name']);

    $db   = getDB();
    $stmt = $db->prepare(
        "INSERT INTO csv_imports (filename, table_target, status, uploaded_by) VALUES (?,?,?,?)"
    );
    $stmt->execute([$filename, $table, 'Processing', $_SESSION['user_id']]);
    $importId = $db->lastInsertId();

    $handle  = fopen($tmpPath, 'r');
    $headers = fgetcsv($handle);
    if (!$headers) {
        jsonResponse(['error' => 'Empty or invalid CSV'], 422);
    }
    $headers = array_map('trim', $headers);

    $total    = 0;
    $imported = 0;
    $errors   = [];

    while (($row = fgetcsv($handle)) !== false) {
        $total++;
        if (count($row) !== count($headers)) {
            $errors[] = "Row $total: column count mismatch";
            continue;
        }
        $data = array_combine($headers, $row);
        try {
            switch ($table) {
                case 'consumers':     importConsumerRow($db, $data);   break;
                case 'inventory_items': importInventoryRow($db, $data); break;
            }
            $imported++;
        } catch (Exception $e) {
            $errors[] = "Row $total: " . $e->getMessage();
        }
    }
    fclose($handle);

    $errText = implode('; ', array_slice($errors, 0, 20));
    $db->prepare(
        "UPDATE csv_imports SET total_rows=?, imported_rows=?, failed_rows=?, status=?, error_log=? WHERE id=?"
    )->execute([$total, $imported, $total - $imported, 'Completed', $errText, $importId]);

    logActivity($_SESSION['user_id'], 'csv_import', $table, (string)$importId, "Imported $imported/$total rows");
    jsonResponse([
        'success'    => true,
        'import_id'  => $importId,
        'total_rows' => $total,
        'imported'   => $imported,
        'failed'     => $total - $imported,
        'errors'     => array_slice($errors, 0, 10),
    ]);
}

function importConsumerRow(PDO $db, array $data): void {
    $stmt = $db->prepare(
        "INSERT INTO consumers (account_id, account_no, name, type, status, address, barangay, zone, book, contact_no, email)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           name=VALUES(name), status=VALUES(status), address=VALUES(address)"
    );
    $stmt->execute([
        $data['account_id'] ?? '', $data['account_no'] ?? '',
        $data['name'] ?? 'Unknown', $data['type'] ?? 'Residential',
        $data['status'] ?? 'Active', $data['address'] ?? '',
        $data['barangay'] ?? '', $data['zone'] ?? '', $data['book'] ?? '',
        $data['contact_no'] ?? '', $data['email'] ?? '',
    ]);
}

function importInventoryRow(PDO $db, array $data): void {
    $stmt = $db->prepare(
        "INSERT INTO inventory_items (name, category, unit, quantity_in_stock, reorder_level, unit_cost, supplier)
         VALUES (?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE quantity_in_stock=VALUES(quantity_in_stock)"
    );
    $stmt->execute([
        $data['name'] ?? '', $data['category'] ?? '', $data['unit'] ?? '',
        floatval($data['quantity_in_stock'] ?? 0), floatval($data['reorder_level'] ?? 0),
        floatval($data['unit_cost'] ?? 0), $data['supplier'] ?? '',
    ]);
}

function getLogs(): void {
    requireRole('Admin');
    $db     = getDB();
    $page   = max(1, intval($_GET['page'] ?? 1));
    $limit  = 100;
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    $action = $_GET['action_filter'] ?? '';

    $sql    = "SELECT al.*, u.name as user_name FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id WHERE 1=1";
    $params = [];
    if ($search) {
        $sql .= " AND (al.action LIKE ? OR al.details LIKE ? OR al.table_name LIKE ?)";
        $s = "%$search%";
        $params = [$s, $s, $s];
    }
    if ($action) { $sql .= " AND al.action=?"; $params[] = $action; }
    $sql .= " ORDER BY al.created_at DESC LIMIT $limit OFFSET $offset";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['data' => $stmt->fetchAll(), 'page' => $page]);
}

function getImports(): void {
    requireRole('Admin');
    $db   = getDB();
    $stmt = $db->query(
        "SELECT ci.*, u.name as uploader_name
         FROM csv_imports ci
         LEFT JOIN users u ON u.id = ci.uploaded_by
         ORDER BY ci.uploaded_at DESC
         LIMIT 50"
    );
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function changePassword(): void {
    $currentPw = $_POST['current_password'] ?? '';
    $newPw     = $_POST['new_password']     ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    if (!$currentPw || !$newPw || !$confirmPw) {
        jsonResponse(['error' => 'All password fields are required'], 422);
    }
    if ($newPw !== $confirmPw) {
        jsonResponse(['error' => 'New passwords do not match'], 422);
    }
    if (strlen($newPw) < 8) {
        jsonResponse(['error' => 'Password must be at least 8 characters'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id=?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPw, $user['password_hash'])) {
        jsonResponse(['error' => 'Current password is incorrect'], 401);
    }

    $db->prepare("UPDATE users SET password_hash=? WHERE id=?")
       ->execute([password_hash($newPw, PASSWORD_BCRYPT), $_SESSION['user_id']]);

    logActivity($_SESSION['user_id'], 'change_password', 'users', (string)$_SESSION['user_id'], 'Password changed');
    jsonResponse(['success' => true]);
}

function getUsers(): void {
    requireRole('Admin');
    $db   = getDB();
    $stmt = $db->query("SELECT id, username, name, role, section, email, is_active, created_at FROM users ORDER BY name");
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function saveUser(): void {
    requireRole('Admin');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    if (!empty($data['id'])) {
        $stmt = $db->prepare(
            "UPDATE users SET username=?, name=?, role=?, section=?, email=?, is_active=? WHERE id=?"
        );
        $stmt->execute([
            $data['username'], $data['name'], $data['role'],
            $data['section'] ?? null, $data['email'] ?? null,
            $data['is_active'] ?? 1, $data['id']
        ]);
        if (!empty($data['password'])) {
            $db->prepare("UPDATE users SET password_hash=? WHERE id=?")
               ->execute([password_hash($data['password'], PASSWORD_BCRYPT), $data['id']]);
        }
    } else {
        if (empty($data['password'])) jsonResponse(['error' => 'Password required for new users'], 422);
        $stmt = $db->prepare(
            "INSERT INTO users (username, password_hash, name, role, section, email) VALUES (?,?,?,?,?,?)"
        );
        $stmt->execute([
            $data['username'], password_hash($data['password'], PASSWORD_BCRYPT),
            $data['name'], $data['role'], $data['section'] ?? null, $data['email'] ?? null
        ]);
    }
    jsonResponse(['success' => true]);
}
