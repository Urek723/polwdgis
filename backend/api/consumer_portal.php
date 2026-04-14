<?php
// backend/api/consumer_portal.php
// API for consumer portal actions: requests, inquiries, notifications

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

// Email notification helper (graceful — won't break if PHPMailer missing)
$emailHelperPath = __DIR__ . '/../notifications/send_email.php';
if (file_exists($emailHelperPath)) {
    require_once $emailHelperPath;
}

startSession();
header('Content-Type: application/json');

// Guard: only authenticated consumers
if (empty($_SESSION['consumer_auth_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$consumer_auth_id = (int) $_SESSION['consumer_auth_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'submit_request':            submitRequest($consumer_auth_id);            break;
    case 'get_my_requests':           getMyRequests($consumer_auth_id);            break;
    case 'submit_inquiry':            submitInquiry($consumer_auth_id);            break;
    case 'get_my_inquiries':          getMyInquiries($consumer_auth_id);           break;
    case 'get_interruptions':         getInterruptions();                          break;
    case 'get_my_notifications':      getMyNotifications($consumer_auth_id);       break;
    case 'mark_notification_read':    markNotificationRead($consumer_auth_id);     break;
    case 'mark_all_notifications_read': markAllNotificationsRead($consumer_auth_id); break;
    case 'get_unread_count':          getUnreadCount($consumer_auth_id);           break;
    default: jsonResponse(['error' => 'Unknown action'], 400);
}

// ── Submit Request ────────────────────────────────────────────
function submitRequest(int $consumer_auth_id): void {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    $issue_type   = trim($data['issue_type'] ?? '');
    $description  = trim($data['description'] ?? '');
    $contact      = trim($data['contact'] ?? '');
    $lat          = isset($data['latitude'])  && $data['latitude']  !== '' ? $data['latitude']  : null;
    $lng          = isset($data['longitude']) && $data['longitude'] !== '' ? $data['longitude'] : null;
    $loc_text     = trim($data['location_text'] ?? '');

    $allowed_types = ['Leak', 'Low Pressure', 'No Water', 'General Inquiry'];
    if (!in_array($issue_type, $allowed_types)) {
        jsonResponse(['error' => 'Invalid issue type'], 422);
    }
    if (!$description) {
        jsonResponse(['error' => 'Description is required'], 422);
    }
    if ($lat === null || $lng === null) {
        jsonResponse(['error' => 'Location pin is required'], 422);
    }

    $details = $description;
    if ($contact) $details .= "\n\nContact: $contact";

    $stmt = $db->prepare(
        "INSERT INTO consumer_requests
         (consumer_auth_id, request_type, subject, details, latitude, longitude,
          location_text, status, priority)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'Submitted', 'Normal')"
    );
    $stmt->execute([
        $consumer_auth_id,
        $issue_type,
        $issue_type . ' - ' . mb_substr($description, 0, 80),
        $details,
        $lat,
        $lng,
        $loc_text,
    ]);
    $requestId = (int) $db->lastInsertId();

    // ── In-app notification ───────────────────────────────────
    insertConsumerNotification($db, $consumer_auth_id, 'message',
        "Request #{$requestId} Submitted",
        "Your {$issue_type} request has been received and is under review. Reference: #" . str_pad($requestId, 5, '0', STR_PAD_LEFT)
    );

    // ── Email notification (non-fatal) ────────────────────────
    sendRequestEmailIfPossible(
        $db, $consumer_auth_id, $requestId, $issue_type,
        $issue_type . ' - ' . mb_substr($description, 0, 80),
        $details
    );

    jsonResponse(['success' => true, 'id' => $requestId]);
}

// ── Get My Requests ───────────────────────────────────────────
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

// ── Submit Inquiry ────────────────────────────────────────────
function submitInquiry(int $consumer_auth_id): void {
    $data    = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db      = getDB();
    $subject = trim($data['subject'] ?? '');
    $message = trim($data['message'] ?? '');

    if (!$subject || !$message) {
        jsonResponse(['error' => 'Subject and message are required'], 422);
    }

    $stmt = $db->prepare(
        "INSERT INTO consumer_requests
         (consumer_auth_id, request_type, subject, details, status, priority)
         VALUES (?, 'Other', ?, ?, 'Submitted', 'Normal')"
    );
    $stmt->execute([$consumer_auth_id, $subject, $message]);
    $rid = (int) $db->lastInsertId();

    // Store in communication_history for staff visibility
    $stmt2 = $db->prepare(
        "INSERT INTO communication_history
         (channel, direction, subject, message, related_request_id)
         VALUES ('Portal', 'Inbound', ?, ?, ?)"
    );
    $stmt2->execute([$subject, $message, $rid]);

    // In-app notification
    insertConsumerNotification($db, $consumer_auth_id, 'message',
        "Inquiry #{$rid} Submitted",
        "Your inquiry \"" . mb_substr($subject, 0, 60) . "\" has been received. We'll respond soon."
    );

    jsonResponse(['success' => true, 'id' => $rid]);
}

// ── Get My Inquiries ──────────────────────────────────────────
function getMyInquiries(int $consumer_auth_id): void {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT cr.id, cr.subject, cr.details, cr.status, cr.created_at,
                cr.resolution_notes,
                ch.message   AS staff_reply,
                ch.created_at AS reply_at
         FROM consumer_requests cr
         LEFT JOIN communication_history ch
               ON ch.related_request_id = cr.id
              AND ch.direction = 'Outbound'
         WHERE cr.consumer_auth_id = ?
           AND cr.request_type = 'Other'
         ORDER BY cr.created_at DESC"
    );
    $stmt->execute([$consumer_auth_id]);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

// ── Get Active Interruptions ──────────────────────────────────
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

// ── Get Consumer Notifications ────────────────────────────────
function getMyNotifications(int $consumer_auth_id): void {
    $db   = getDB();
    $type    = $_GET['type']    ?? '';
    $is_read = $_GET['is_read'] ?? '';

    $sql    = "SELECT * FROM notifications WHERE consumer_auth_id = ?";
    $params = [$consumer_auth_id];

    if ($type)         { $sql .= " AND type = ?";      $params[] = $type; }
    if ($is_read !== '') { $sql .= " AND is_read = ?"; $params[] = (int)$is_read; }

    $sql .= " ORDER BY created_at DESC LIMIT 100";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

// ── Mark single notification read ─────────────────────────────
function markNotificationRead(int $consumer_auth_id): void {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id   = (int)($data['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID required'], 422);

    $db = getDB();
    $db->prepare(
        "UPDATE notifications SET is_read = 1
         WHERE id = ? AND consumer_auth_id = ?"
    )->execute([$id, $consumer_auth_id]);

    jsonResponse(['success' => true]);
}

// ── Mark all notifications read ───────────────────────────────
function markAllNotificationsRead(int $consumer_auth_id): void {
    $db = getDB();
    $db->prepare(
        "UPDATE notifications SET is_read = 1
         WHERE consumer_auth_id = ? AND is_read = 0"
    )->execute([$consumer_auth_id]);
    jsonResponse(['success' => true]);
}

// ── Get unread notification count ─────────────────────────────
function getUnreadCount(int $consumer_auth_id): void {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM notifications
         WHERE consumer_auth_id = ? AND is_read = 0"
    );
    $stmt->execute([$consumer_auth_id]);
    jsonResponse(['count' => (int)$stmt->fetchColumn()]);
}

// ── Helpers ───────────────────────────────────────────────────

/**
 * Insert a notification for a consumer (by consumer_auth_id).
 * Uses consumer_auth_id column — add it to notifications table if missing.
 */
function insertConsumerNotification(
    PDO    $db,
    int    $consumerAuthId,
    string $type,
    string $title,
    string $message
): void {
    try {
        // Check if consumer_auth_id column exists; fall back to consumer_id if not
        $cols = $db->query("SHOW COLUMNS FROM notifications")->fetchAll(PDO::FETCH_COLUMN);

        if (in_array('consumer_auth_id', $cols)) {
            $db->prepare(
                "INSERT INTO notifications (consumer_auth_id, type, title, message, is_read)
                 VALUES (?, ?, ?, ?, 0)"
            )->execute([$consumerAuthId, $type, $title, $message]);
        } else {
            // Fallback: store with consumer_id = null (visible to staff)
            $db->prepare(
                "INSERT INTO notifications (type, title, message, is_read)
                 VALUES (?, ?, ?, 0)"
            )->execute([$type, $title, $message]);
        }
    } catch (PDOException $e) {
        error_log('[Notification] Insert failed: ' . $e->getMessage());
    }
}

/**
 * Look up consumer's email and send request submitted notification.
 * Entirely non-fatal — errors are logged, never thrown.
 */
function sendRequestEmailIfPossible(
    PDO    $db,
    int    $consumerAuthId,
    int    $requestId,
    string $requestType,
    string $subject,
    string $details
): void {
    if (!function_exists('sendRequestSubmittedEmail')) return;

    try {
        // Try consumers_auth first
        $stmt = $db->prepare(
            "SELECT name, NULL AS email FROM consumers_auth WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$consumerAuthId]);
        $row = $stmt->fetch();
        if (!$row || empty($row['email'])) return;

        sendRequestSubmittedEmail(
            $row['email'],
            $row['name'] ?? 'Consumer',
            $requestId,
            $requestType,
            $subject,
            $details
        );
    } catch (Throwable $e) {
        error_log('[EmailNotify] sendRequestEmailIfPossible failed: ' . $e->getMessage());
    }
}