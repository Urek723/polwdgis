<?php
// backend/api/consumer.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

requireAuth();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_consumers':          getConsumers();           break;
    case 'get_consumer':           getConsumer();            break;
    case 'save_consumer':          saveConsumer();           break;
    case 'get_consumption':        getConsumption();         break;
    case 'save_consumption':       saveConsumption();        break;
    case 'consumption_alerts':     getConsumptionAlerts();   break;
    case 'predict_consumption':    predictConsumption();     break;
    case 'get_notifications':      getNotifications();       break;
    case 'mark_notification_read': markNotificationRead();   break;
    case 'send_interruption':      sendInterruptionNotif();  break;
    case 'get_interruptions':      getInterruptions();       break;
    case 'save_interruption':      saveInterruption();       break;
    case 'get_requests':           getRequests();            break;
    case 'save_request':           saveRequest();            break;
    case 'update_request':         updateRequest();          break;
    case 'get_comms':              getCommunicationHistory(); break;
    case 'add_comm':               addCommunication();       break;
    case 'chatbot':                chatbot();                break;
    case 'generate_document':      generateDocument();       break;
    case 'get_documents':          getDocuments();           break;
    case 'get_templates':          getTemplates();           break;
    default: jsonResponse(['error' => 'Unknown action'], 400);
}

function getConsumers(): void {
    $db     = getDB();
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $type   = $_GET['type'] ?? '';
    $page   = max(1, intval($_GET['page'] ?? 1));
    $limit  = 50;
    $offset = ($page - 1) * $limit;

    $sql    = "SELECT * FROM consumers WHERE 1=1";
    $params = [];
    if ($search) {
        $sql .= " AND (name LIKE ? OR account_id LIKE ? OR account_no LIKE ? OR barangay LIKE ?)";
        $s = "%$search%";
        $params = array_merge($params, [$s, $s, $s, $s]);
    }
    if ($status) { $sql .= " AND status=?"; $params[] = $status; }
    if ($type)   { $sql .= " AND type=?";   $params[] = $type; }

    $count = $db->prepare(str_replace('SELECT *', 'SELECT COUNT(*)', $sql));
    $count->execute($params);
    $total = $count->fetchColumn();

    $sql .= " ORDER BY name LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    jsonResponse(['data' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => ceil($total/$limit)]);
}

function getConsumer(): void {
    $id  = intval($_GET['id'] ?? 0) ?: ($_GET['account_id'] ?? '');
    $db  = getDB();

    if (is_int($id) && $id) {
        $stmt = $db->prepare("SELECT * FROM consumers WHERE id=?");
        $stmt->execute([$id]);
    } else {
        $stmt = $db->prepare("SELECT * FROM consumers WHERE account_id=?");
        $stmt->execute([$id]);
    }
    $c = $stmt->fetch();
    if (!$c) jsonResponse(['error' => 'Not found'], 404);

    // latest consumption
    $stmt2 = $db->prepare(
        "SELECT * FROM consumption_records WHERE consumer_id=? ORDER BY billing_month DESC LIMIT 12"
    );
    $stmt2->execute([$c['id']]);
    $c['consumption_history'] = $stmt2->fetchAll();

    jsonResponse(['data' => $c]);
}

function saveConsumer(): void {
    requireRole('Admin', 'Staff');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    $required = ['name', 'account_id', 'type'];
    foreach ($required as $f) {
        if (empty($data[$f])) jsonResponse(['error' => "Field '$f' required"], 422);
    }

    if (!empty($data['id'])) {
        $stmt = $db->prepare(
            "UPDATE consumers SET account_id=?,account_no=?,name=?,type=?,status=?,address=?,
             barangay=?,zone=?,book=?,service_connection_no=?,contact_no=?,secondary_no=?,
             email=?,male_users=?,female_users=?,total_users=?,is_senior=?,
             latitude=?,longitude=? WHERE id=?"
        );
        $stmt->execute([
            $data['account_id'], $data['account_no'] ?? '', $data['name'],
            $data['type'], $data['status'] ?? 'Active', $data['address'] ?? '',
            $data['barangay'] ?? '', $data['zone'] ?? '', $data['book'] ?? '',
            $data['service_connection_no'] ?? '', $data['contact_no'] ?? '',
            $data['secondary_no'] ?? '', $data['email'] ?? '',
            $data['male_users'] ?? 0, $data['female_users'] ?? 0, $data['total_users'] ?? 0,
            $data['is_senior'] ?? 0, $data['latitude'] ?? null, $data['longitude'] ?? null,
            $data['id']
        ]);
        logActivity($_SESSION['user_id'], 'update_consumer', 'consumers', $data['id'], $data['name']);
    } else {
        $stmt = $db->prepare(
            "INSERT INTO consumers
             (account_id,account_no,name,type,status,address,barangay,zone,book,
              service_connection_no,contact_no,secondary_no,email,
              male_users,female_users,total_users,is_senior,latitude,longitude)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $data['account_id'], $data['account_no'] ?? '', $data['name'],
            $data['type'], $data['status'] ?? 'Active', $data['address'] ?? '',
            $data['barangay'] ?? '', $data['zone'] ?? '', $data['book'] ?? '',
            $data['service_connection_no'] ?? '', $data['contact_no'] ?? '',
            $data['secondary_no'] ?? '', $data['email'] ?? '',
            $data['male_users'] ?? 0, $data['female_users'] ?? 0, $data['total_users'] ?? 0,
            $data['is_senior'] ?? 0, $data['latitude'] ?? null, $data['longitude'] ?? null
        ]);
        logActivity($_SESSION['user_id'], 'add_consumer', 'consumers', $db->lastInsertId(), $data['name']);
    }
    jsonResponse(['success' => true]);
}

function getConsumption(): void {
    $cid   = intval($_GET['consumer_id'] ?? 0);
    if (!$cid) jsonResponse(['error' => 'consumer_id required'], 422);
    $db    = getDB();
    $stmt  = $db->prepare(
        "SELECT * FROM consumption_records WHERE consumer_id=? ORDER BY billing_month DESC LIMIT 24"
    );
    $stmt->execute([$cid]);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function saveConsumption(): void {
    requireRole('Admin', 'Staff');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    if (empty($data['consumer_id']) || empty($data['billing_month'])) {
        jsonResponse(['error' => 'consumer_id and billing_month required'], 422);
    }

    // Check threshold
    $consumption = floatval($data['reading_curr'] ?? 0) - floatval($data['reading_prev'] ?? 0);
    $threshold   = floatval($data['alert_threshold'] ?? 50);
    $isAlert     = $consumption > $threshold ? 1 : 0;

    $stmt = $db->prepare(
        "INSERT INTO consumption_records
         (consumer_id,meter_id,billing_month,reading_prev,reading_curr,is_alert,alert_threshold,recorded_by)
         VALUES (?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE reading_prev=VALUES(reading_prev), reading_curr=VALUES(reading_curr),
         is_alert=VALUES(is_alert), recorded_by=VALUES(recorded_by)"
    );
    $stmt->execute([
        $data['consumer_id'], $data['meter_id'] ?? null,
        $data['billing_month'], $data['reading_prev'] ?? 0, $data['reading_curr'] ?? 0,
        $isAlert, $threshold, $_SESSION['user_id']
    ]);

    // Send alert notification if exceeded
    if ($isAlert) {
        $notifStmt = $db->prepare(
            "INSERT INTO notifications (consumer_id,type,title,message)
             VALUES (?,?,?,?)"
        );
        $notifStmt->execute([
            $data['consumer_id'], 'alert',
            'High Consumption Alert',
            "Your water consumption of {$consumption} m³ exceeds the threshold of {$threshold} m³ for " . date('F Y', strtotime($data['billing_month']))
        ]);
    }

    jsonResponse(['success' => true, 'is_alert' => $isAlert, 'consumption' => $consumption]);
}

function getConsumptionAlerts(): void {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT cr.*, c.name as consumer_name, c.account_id, c.barangay
         FROM consumption_records cr
         JOIN consumers c ON c.id = cr.consumer_id
         WHERE cr.is_alert = 1
         ORDER BY cr.billing_month DESC, cr.consumption_m3 DESC
         LIMIT 100"
    );
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function predictConsumption(): void {
    $cid  = intval($_GET['consumer_id'] ?? 0);
    if (!$cid) jsonResponse(['error' => 'consumer_id required'], 422);
    $db   = getDB();

    $stmt = $db->prepare(
        "SELECT billing_month, consumption_m3
         FROM consumption_records
         WHERE consumer_id=? AND consumption_m3 > 0
         ORDER BY billing_month DESC LIMIT 12"
    );
    $stmt->execute([$cid]);
    $records = $stmt->fetchAll();

    if (count($records) < 3) {
        jsonResponse(['error' => 'Insufficient data for prediction (need at least 3 months)'], 422);
    }

    $values = array_column($records, 'consumption_m3');
    $n      = count($values);

    // Simple linear regression
    $sumX  = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
    foreach ($values as $i => $v) {
        $x = $i + 1;
        $sumX  += $x; $sumY  += $v;
        $sumXY += $x * $v; $sumX2 += $x * $x;
    }
    $slope     = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    $intercept = ($sumY - $slope * $sumX) / $n;
    $predicted = round($intercept + $slope * ($n + 1), 2);
    $predicted = max(0, $predicted); // no negative

    // Moving average baseline
    $avg3 = round(array_sum(array_slice($values, 0, 3)) / 3, 2);

    jsonResponse([
        'consumer_id'        => $cid,
        'predicted_m3'       => $predicted,
        'avg_last_3_months'  => $avg3,
        'trend'              => $slope > 0 ? 'increasing' : ($slope < 0 ? 'decreasing' : 'stable'),
        'history'            => array_reverse($records),
        'method'             => 'linear_regression',
    ]);
}

function getNotifications(): void {
    $uid   = $_SESSION['user_id'];
    $cid   = intval($_GET['consumer_id'] ?? 0);
    $db    = getDB();

    if ($cid) {
        $stmt = $db->prepare(
            "SELECT * FROM notifications WHERE consumer_id=? ORDER BY created_at DESC LIMIT 50"
        );
        $stmt->execute([$cid]);
    } else {
        $stmt = $db->prepare(
            "SELECT * FROM notifications WHERE user_id=? OR user_id IS NULL
             ORDER BY created_at DESC LIMIT 50"
        );
        $stmt->execute([$uid]);
    }
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function markNotificationRead(): void {
    $id   = intval($_POST['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID required'], 422);
    $db   = getDB();
    $db->prepare("UPDATE notifications SET is_read=1 WHERE id=?")->execute([$id]);
    jsonResponse(['success' => true]);
}

function sendInterruptionNotif(): void {
    requireRole('Admin', 'Staff');
    $id   = intval($_POST['interruption_id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'interruption_id required'], 422);
    $db   = getDB();

    $stmt = $db->prepare("SELECT * FROM water_interruptions WHERE id=?");
    $stmt->execute([$id]);
    $intr = $stmt->fetch();
    if (!$intr) jsonResponse(['error' => 'Not found'], 404);

    // Get affected consumers by barangay
    $barangays = array_map('trim', explode(',', $intr['affected_barangays'] ?? ''));
    $placeholders = implode(',', array_fill(0, count($barangays), '?'));

    $cStmt = $db->prepare("SELECT id FROM consumers WHERE barangay IN ($placeholders) AND status='Active'");
    $cStmt->execute($barangays);
    $consumers = $cStmt->fetchAll();

    $count = 0;
    foreach ($consumers as $c) {
        $nStmt = $db->prepare(
            "INSERT INTO notifications (consumer_id,type,title,message)
             VALUES (?,?,?,?)"
        );
        $nStmt->execute([
            $c['id'], 'interruption',
            'Water Interruption: ' . $intr['title'],
            "A water interruption is scheduled from " .
            date('M j, Y g:i A', strtotime($intr['start_datetime'])) . " to " .
            date('M j, Y g:i A', strtotime($intr['end_datetime'] ?? $intr['start_datetime'])) .
            ". " . ($intr['description'] ?? '')
        ]);
        $count++;
    }

    $db->prepare("UPDATE water_interruptions SET notification_sent=1 WHERE id=?")->execute([$id]);
    logActivity($_SESSION['user_id'], 'send_interruption_notif', 'water_interruptions', (string)$id, "Sent to $count consumers");
    jsonResponse(['success' => true, 'notified_count' => $count]);
}

function getInterruptions(): void {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT wi.*, u.name as creator_name
         FROM water_interruptions wi LEFT JOIN users u ON u.id=wi.created_by
         ORDER BY wi.start_datetime DESC LIMIT 50"
    );
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function saveInterruption(): void {
    requireRole('Admin', 'Staff');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    $required = ['title', 'start_datetime', 'affected_barangays'];
    foreach ($required as $f) {
        if (empty($data[$f])) jsonResponse(['error' => "Field '$f' required"], 422);
    }

    if (!empty($data['id'])) {
        $stmt = $db->prepare(
            "UPDATE water_interruptions SET title=?,description=?,affected_barangays=?,
             start_datetime=?,end_datetime=?,status=? WHERE id=?"
        );
        $stmt->execute([
            $data['title'], $data['description'] ?? '', $data['affected_barangays'],
            $data['start_datetime'], $data['end_datetime'] ?? null,
            $data['status'] ?? 'Scheduled', $data['id']
        ]);
    } else {
        $stmt = $db->prepare(
            "INSERT INTO water_interruptions
             (title,description,affected_barangays,start_datetime,end_datetime,status,created_by)
             VALUES (?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $data['title'], $data['description'] ?? '', $data['affected_barangays'],
            $data['start_datetime'], $data['end_datetime'] ?? null,
            $data['status'] ?? 'Scheduled', $_SESSION['user_id']
        ]);
    }
    jsonResponse(['success' => true]);
}

function getRequests(): void {
    $db     = getDB();
    $cid    = intval($_GET['consumer_id'] ?? 0);
    $status = $_GET['status'] ?? '';
    $type   = $_GET['type'] ?? '';
    $priority = $_GET['priority'] ?? '';

    $sql    = "SELECT r.*,
                      COALESCE(c.name, ca.name, 'Portal User') as consumer_name,
                      COALESCE(c.account_id, ca.account_number, '—') as account_id
               FROM consumer_requests r
               LEFT JOIN consumers c ON c.id = r.consumer_id
               LEFT JOIN consumers_auth ca ON ca.id = r.consumer_auth_id
               WHERE 1=1";
    $params = [];

    if ($cid)     { $sql .= " AND r.consumer_id=?";   $params[] = $cid; }
    if ($status)  { $sql .= " AND r.status=?";         $params[] = $status; }
    if ($type)    { $sql .= " AND r.request_type=?";   $params[] = $type; }
    if ($priority){ $sql .= " AND r.priority=?";       $params[] = $priority; }

    $sql .= " ORDER BY r.created_at DESC LIMIT 200";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function saveRequest(): void {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    $required = ['consumer_id', 'request_type'];
    foreach ($required as $f) {
        if (empty($data[$f])) jsonResponse(['error' => "Field '$f' required"], 422);
    }

    $stmt = $db->prepare(
        "INSERT INTO consumer_requests (consumer_id,request_type,subject,details,priority)
         VALUES (?,?,?,?,?)"
    );
    $stmt->execute([
        $data['consumer_id'], $data['request_type'],
        $data['subject'] ?? '', $data['details'] ?? '',
        $data['priority'] ?? 'Normal'
    ]);
    $rid = $db->lastInsertId();
    logActivity($_SESSION['user_id'], 'new_request', 'consumer_requests', (string)$rid, $data['request_type']);

    // Notify consumer
    $notif = $db->prepare(
        "INSERT INTO notifications (consumer_id,type,title,message) VALUES (?,?,?,?)"
    );
    $notif->execute([
        $data['consumer_id'], 'message',
        'Request #' . $rid . ' Submitted',
        'Your ' . $data['request_type'] . ' request has been received and is under review.'
    ]);

    jsonResponse(['success' => true, 'id' => $rid]);
}

function updateRequest(): void {
    requireRole('Admin', 'Staff');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    $stmt = $db->prepare(
        "UPDATE consumer_requests SET status=?,assigned_to=?,resolution_notes=?,
         resolved_at=IF(status='Resolved',NOW(),resolved_at) WHERE id=?"
    );
    $stmt->execute([
        $data['status'], $data['assigned_to'] ?? null,
        $data['resolution_notes'] ?? '', $data['id']
    ]);
    jsonResponse(['success' => true]);
}

function getCommunicationHistory(): void {
    $cid  = intval($_GET['consumer_id'] ?? 0);
    $db   = getDB();

    $stmt = $db->prepare(
        "SELECT ch.*, u.name as staff_name
         FROM communication_history ch
         LEFT JOIN users u ON u.id = ch.user_id
         WHERE ch.consumer_id=?
         ORDER BY ch.created_at DESC LIMIT 50"
    );
    $stmt->execute([$cid]);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function addCommunication(): void {
    requireRole('Admin', 'Staff');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    $stmt = $db->prepare(
        "INSERT INTO communication_history
         (consumer_id,user_id,channel,direction,subject,message,related_request_id)
         VALUES (?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        $data['consumer_id'], $_SESSION['user_id'],
        $data['channel'] ?? 'Portal', $data['direction'] ?? 'Outbound',
        $data['subject'] ?? '', $data['message'] ?? '',
        $data['related_request_id'] ?? null
    ]);
    jsonResponse(['success' => true]);
}

function chatbot(): void {
    $message = trim($_POST['message'] ?? '');
    $session = trim($_POST['session_token'] ?? '');
    if (!$message) jsonResponse(['error' => 'Message required'], 422);

    $db  = getDB();
    $cid = $_SESSION['consumer_id'] ?? null;

    // Get or create session
    if (!$session) {
        $session = bin2hex(random_bytes(16));
        $db->prepare("INSERT INTO chatbot_sessions (session_token,consumer_id) VALUES (?,?)")
           ->execute([$session, $cid]);
    }
    $sStmt = $db->prepare("SELECT id FROM chatbot_sessions WHERE session_token=?");
    $sStmt->execute([$session]);
    $sess = $sStmt->fetch();
    if (!$sess) {
        $db->prepare("INSERT INTO chatbot_sessions (session_token,consumer_id) VALUES (?,?)")
           ->execute([$session, $cid]);
        $sStmt->execute([$session]);
        $sess = $sStmt->fetch();
    }
    $sid = $sess['id'];

    // Save user message
    $db->prepare("INSERT INTO chatbot_messages (session_id,sender,message) VALUES (?,?,?)")
       ->execute([$sid, 'user', $message]);

    // Generate bot response (rule-based)
    $response = generateBotResponse(strtolower($message), $db, $cid);

    // Save bot response
    $db->prepare("INSERT INTO chatbot_messages (session_id,sender,message) VALUES (?,?,?)")
       ->execute([$sid, 'bot', $response]);

    jsonResponse(['response' => $response, 'session_token' => $session]);
}

function generateBotResponse(string $msg, PDO $db, ?int $cid): string {
    if (str_contains($msg, 'bill') || str_contains($msg, 'payment')) {
        return "For billing inquiries, please visit our office at the Water District Building or call (083) 123-4567. You can also submit a billing dispute through our Request Portal.";
    }
    if (str_contains($msg, 'leak') || str_contains($msg, 'burst') || str_contains($msg, 'broken')) {
        return "Please report leaks or pipe bursts immediately by calling our emergency hotline: (083) 999-0000, available 24/7. You may also submit a repair request through the Request Portal.";
    }
    if (str_contains($msg, 'connect') && str_contains($msg, 'new')) {
        return "To apply for a new water connection, please submit a New Connection request through the Request Portal. Required documents: valid ID, proof of ownership/occupancy, sketch plan.";
    }
    if (str_contains($msg, 'disconnect') || str_contains($msg, 'disconnected')) {
        return "If your water supply has been disconnected, it may be due to unpaid bills or pending maintenance. Please contact our office or submit a Reconnection request through the portal.";
    }
    if (str_contains($msg, 'interrupt') || str_contains($msg, 'no water')) {
        // Check active interruptions
        $stmt = $db->query(
            "SELECT title, affected_barangays, start_datetime, end_datetime
             FROM water_interruptions WHERE status='Ongoing'
             ORDER BY start_datetime DESC LIMIT 3"
        );
        $interruptions = $stmt->fetchAll();
        if ($interruptions) {
            $list = implode('; ', array_map(fn($i) => $i['title'] . " (Barangays: {$i['affected_barangays']})", $interruptions));
            return "Active water interruptions: $list. We apologize for the inconvenience. Normal supply will resume as scheduled.";
        }
        return "There are currently no reported water interruptions. If you're experiencing low pressure or no water, please contact us at (083) 123-4567.";
    }
    if (str_contains($msg, 'consumption') || str_contains($msg, 'usage')) {
        return "You can view your water consumption history in your account dashboard. Excessive consumption may be due to leaks inside your property. Contact us for an inspection.";
    }
    if (str_contains($msg, 'request') || str_contains($msg, 'portal')) {
        return "You can submit service requests through the Request Portal on the left sidebar. Available request types: New Connection, Disconnection, Reconnection, Repair, and Billing Dispute.";
    }
    if (str_contains($msg, 'hello') || str_contains($msg, 'hi') || str_contains($msg, 'good')) {
        return "Hello! Welcome to Polomolok Water District support. How can I help you today? You can ask about your bill, water connections, reports, or current service interruptions.";
    }
    if (str_contains($msg, 'office') || str_contains($msg, 'hours') || str_contains($msg, 'address')) {
        return "Polomolok Water District Office is located at [Your Address]. Office hours: Monday-Friday, 8:00 AM - 5:00 PM. Emergency hotline: (083) 999-0000 (24/7).";
    }
    return "I'm sorry, I didn't quite understand that. You can ask me about: water bills, new connections, disconnections, repairs, service interruptions, or contact information. How can I assist you?";
}

function generateDocument(): void {
    requireRole('Admin', 'Staff');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    if (empty($data['template_id']) || empty($data['consumer_id'])) {
        jsonResponse(['error' => 'template_id and consumer_id required'], 422);
    }

    $tStmt = $db->prepare("SELECT * FROM document_templates WHERE id=? AND is_active=1");
    $tStmt->execute([$data['template_id']]);
    $template = $tStmt->fetch();
    if (!$template) jsonResponse(['error' => 'Template not found'], 404);

    $cStmt = $db->prepare("SELECT * FROM consumers WHERE id=?");
    $cStmt->execute([$data['consumer_id']]);
    $consumer = $cStmt->fetch();
    if (!$consumer) jsonResponse(['error' => 'Consumer not found'], 404);

    // Get latest consumption
    $consStmt = $db->prepare(
        "SELECT * FROM consumption_records WHERE consumer_id=? ORDER BY billing_month DESC LIMIT 1"
    );
    $consStmt->execute([$data['consumer_id']]);
    $consumption = $consStmt->fetch();

    // Replace placeholders
    $content   = $template['template_content'];
    $variables = [
        '{{name}}'         => $consumer['name'],
        '{{account_no}}'   => $consumer['account_no'] ?? '',
        '{{account_id}}'   => $consumer['account_id'],
        '{{address}}'      => $consumer['address'] ?? '',
        '{{barangay}}'     => $consumer['barangay'] ?? '',
        '{{type}}'         => $consumer['type'],
        '{{status}}'       => $consumer['status'],
        '{{month}}'        => $consumption ? date('F Y', strtotime($consumption['billing_month'])) : date('F Y'),
        '{{consumption}}'  => $consumption ? $consumption['consumption_m3'] : '0',
        '{{date}}'         => date('F j, Y'),
        '{{request_id}}'   => $data['reference_id'] ?? 'N/A',
        '{{barangays}}'    => $data['barangays'] ?? '',
        '{{start}}'        => $data['start'] ?? '',
        '{{end}}'          => $data['end'] ?? '',
    ];
    foreach ($variables as $placeholder => $value) {
        $content = str_replace($placeholder, $value, $content);
    }

    // Save
    $saveStmt = $db->prepare(
        "INSERT INTO generated_documents (template_id,consumer_id,title,content,generated_by)
         VALUES (?,?,?,?,?)"
    );
    $saveStmt->execute([
        $data['template_id'], $data['consumer_id'],
        $template['name'] . ' - ' . $consumer['name'],
        $content, $_SESSION['user_id']
    ]);
    $docId = $db->lastInsertId();

    jsonResponse(['success' => true, 'id' => $docId, 'content' => $content, 'title' => $template['name']]);
}

function getDocuments(): void {
    $cid  = intval($_GET['consumer_id'] ?? 0);
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT gd.id, gd.title, gd.generated_at, u.name as generated_by_name
         FROM generated_documents gd LEFT JOIN users u ON u.id=gd.generated_by
         WHERE gd.consumer_id=?
         ORDER BY gd.generated_at DESC"
    );
    $stmt->execute([$cid]);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

function getTemplates(): void {
    $db   = getDB();
    $stmt = $db->query("SELECT id, name, type FROM document_templates WHERE is_active=1 ORDER BY name");
    jsonResponse(['data' => $stmt->fetchAll()]);
}
