<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

requireAuth();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_history':  getHistory();  break;
    case 'save_history': saveHistory(); break;
    default: jsonResponse(['error' => 'Unknown action'], 400);
}

function getHistory(): void {
    $db     = getDB();
    $filter = $_GET['filter_action'] ?? '';
    $from   = $_GET['from_date'] ?? '';
    $to     = $_GET['to_date'] ?? '';
    $search = $_GET['search'] ?? '';

    $sql    = "SELECT * FROM equipment_history WHERE 1=1";
    $params = [];

    if ($filter) { $sql .= " AND action = ?"; $params[] = $filter; }
    if ($from)   { $sql .= " AND date >= ?";  $params[] = $from; }
    if ($to)     { $sql .= " AND date <= ?";  $params[] = $to; }
    if ($search) { $sql .= " AND notes LIKE ?"; $params[] = "%$search%"; }

    $sql .= " ORDER BY date DESC, id DESC LIMIT 200";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function saveHistory(): void {
    requireRole('Admin', 'Staff');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    if (empty($data['equipment_id'])) {
        jsonResponse(['error' => 'equipment_id required'], 422);
    }

    $stmt = $db->prepare(
        "INSERT INTO equipment_history (equipment_id, action, date, notes)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([
        $data['equipment_id'],
        $data['action_type'] ?? 'inspected',
        $data['date'] ?: date('Y-m-d'),
        $data['notes'] ?? '',
    ]);

    logActivity($_SESSION['user_id'], 'add_equipment_history', 'equipment_history', $db->lastInsertId(), 'Action: ' . ($data['action_type'] ?? ''));
    jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
}