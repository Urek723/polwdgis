<?php
// backend/api/high_consumption_api.php
// Serves high-consumption records for the frontend page

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

requireAuth();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_all':   getAllHighConsumption(); break;
    default: jsonResponse(['error' => 'Unknown action'], 400);
}

function getAllHighConsumption(): void {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT cr.id, cr.consumer_id, cr.billing_month,
                cr.consumption_m3, cr.reading_prev, cr.reading_curr,
                c.name AS consumer_name, c.account_no, c.account_id,
                c.barangay, c.status AS consumer_status
         FROM consumption_records cr
         JOIN consumers c ON c.id = cr.consumer_id
         WHERE cr.consumption_m3 > 10
         ORDER BY cr.consumption_m3 DESC, cr.billing_month DESC
         LIMIT 500"
    );
    jsonResponse(['data' => $stmt->fetchAll()]);
}