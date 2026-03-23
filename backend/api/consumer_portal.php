<?php
// backend/api/consumer_portal.php
// API for consumer portal actions (submit request, track, inquiry)

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

startSession();
header('Content-Type: application/json');

// Guard: only authenticated consumers
if (empty($_SESSION['consumer_auth_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$consumer_auth_id = (int) $_SESSION['consumer_auth_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'submit_request':  submitRequest($consumer_auth_id);  break;
    case 'get_my_requests': getMyRequests($consumer_auth_id);  break;
    case 'submit_inquiry':  submitInquiry($consumer_auth_id);  break;
    case 'get_my_inquiries': getMyInquiries($consumer_auth_id); break;
    case 'get_interruptions': getInterruptions(); break;
    default: jsonResponse(['error' => 'Unknown action'], 400);
}

function submitRequest(int $consumer_auth_id): void {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    $issue_type  = $data['issue_type'] ?? '';
    $description = trim($data['description'] ?? '');
    $contact     = trim($data['contact'] ?? '');
    $lat         = $data['latitude'] ?? null;
    $lng         = $data['longitude'] ?? null;
    $loc_text    = trim($data['location_text'] ?? '');

    $allowed_types = ['Leak', 'Low Pressure', 'No Water', 'General Inquiry'];
    if (!in_array($issue_type, $allowed_types)) {
        jsonResponse(['error' => 'Invalid issue type'], 422);
    }
    if (!$description) {
        jsonResponse(['error' => 'Description is required'], 422);
    }
    if ($lat === null || $lng === null || $lat === '' || $lng === '') {
        jsonResponse(['error' => 'Location pin is required'], 422);
    }

    $stmt = $db->prepare(
        "INSERT INTO consumer_requests
         (consumer_auth_id, request_type, subject, details, latitude, longitude, location_text, status, priority)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'Submitted', 'Normal')"
    );
    $stmt->execute([
        $consumer_auth_id,
        $issue_type,
        $issue_type . ' - ' . substr($description, 0, 80),
        $description . ($contact ? "\n\nContact: $contact" : ''),
        $lat,
        $lng,
        $loc_text,
    ]);

    jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
}

function getMyRequests(int $consumer_auth_id): void {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT id, request_type, subject, details, status, latitude, longitude,
                location_text, created_at, resolved_at, resolution_notes
         FROM consumer_requests
         WHERE consumer_auth_id = ?
         ORDER BY created_at DESC"
    );
    $stmt->execute([$consumer_auth_id]);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function submitInquiry(int $consumer_auth_id): void {
    $data    = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db      = getDB();
    $subject = trim($data['subject'] ?? '');
    $message = trim($data['message'] ?? '');

    if (!$subject || !$message) {
        jsonResponse(['error' => 'Subject and message are required'], 422);
    }

    // Store inquiry as a consumer_request with type "Other"
    $stmt = $db->prepare(
        "INSERT INTO consumer_requests
         (consumer_auth_id, request_type, subject, details, status, priority)
         VALUES (?, 'Other', ?, ?, 'Submitted', 'Normal')"
    );
    $stmt->execute([$consumer_auth_id, $subject, $message]);
    $rid = $db->lastInsertId();

    // Also store in communication_history so staff can see it
    $stmt2 = $db->prepare(
        "INSERT INTO communication_history
         (channel, direction, subject, message, related_request_id)
         VALUES ('Portal', 'Inbound', ?, ?, ?)"
    );
    $stmt2->execute([$subject, $message, $rid]);

    jsonResponse(['success' => true, 'id' => $rid]);
}

function getMyInquiries(int $consumer_auth_id): void {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT cr.id, cr.subject, cr.details, cr.status, cr.created_at,
                cr.resolution_notes,
                ch.message as staff_reply, ch.created_at as reply_at
         FROM consumer_requests cr
         LEFT JOIN communication_history ch
               ON ch.related_request_id = cr.id AND ch.direction = 'Outbound'
         WHERE cr.consumer_auth_id = ? AND cr.request_type = 'Other'
         ORDER BY cr.created_at DESC"
    );
    $stmt->execute([$consumer_auth_id]);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function getInterruptions(): void {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT title, description, affected_barangays, start_datetime, end_datetime, status
         FROM water_interruptions
         WHERE status != 'Resolved'
         ORDER BY start_datetime DESC
         LIMIT 10"
    );
    jsonResponse(['data' => $stmt->fetchAll()]);
}