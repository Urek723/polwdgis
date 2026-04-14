<?php
// backend/chatbot/send_message.php
// Rule-based chatbot endpoint for the consumer portal

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

startSession();
header('Content-Type: application/json');

// Only authenticated consumers may use this endpoint
if (empty($_SESSION['consumer_auth_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$consumer_auth_id = (int)$_SESSION['consumer_auth_id'];

$data    = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$message = trim($data['message'] ?? '');
$session = trim($data['session_token'] ?? '');

if ($message === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

// Sanitize
$message = htmlspecialchars(strip_tags($message), ENT_QUOTES, 'UTF-8');

$db = getDB();

// ── Get or create chat session ────────────────────────────────
if ($session) {
    $sStmt = $db->prepare(
        "SELECT id FROM chatbot_sessions WHERE session_token = ? AND consumer_id = ?"
    );
    $sStmt->execute([$session, $consumer_auth_id]);
    $sess = $sStmt->fetch();
} else {
    $sess = null;
}

if (!$sess) {
    $session = bin2hex(random_bytes(16));
    $db->prepare(
        "INSERT INTO chatbot_sessions (session_token, consumer_id) VALUES (?, ?)"
    )->execute([$session, $consumer_auth_id]);
    $sStmt = $db->prepare("SELECT id FROM chatbot_sessions WHERE session_token = ?");
    $sStmt->execute([$session]);
    $sess = $sStmt->fetch();
}

$sessionId = (int)$sess['id'];

// ── Save user message ─────────────────────────────────────────
$db->prepare(
    "INSERT INTO chatbot_messages (session_id, sender, message) VALUES (?, 'user', ?)"
)->execute([$sessionId, $message]);

// ── Generate bot reply ────────────────────────────────────────
$reply = generateBotReply(mb_strtolower($message, 'UTF-8'), $db, $consumer_auth_id);

// ── Save bot reply ────────────────────────────────────────────
$db->prepare(
    "INSERT INTO chatbot_messages (session_id, sender, message) VALUES (?, 'bot', ?)"
)->execute([$sessionId, $reply]);

echo json_encode([
    'success'       => true,
    'reply'         => $reply,
    'session_token' => $session,
]);
exit;

// ── Rule-based response engine ────────────────────────────────

function generateBotReply(string $msg, PDO $db, int $consumerId): string
{
    // ── Greeting ──────────────────────────────────────────────
    if (preg_match('/\b(hello|hi|hey|good (morning|afternoon|evening|day)|kumusta|musta)\b/i', $msg)) {
        return "Hello! Welcome to the Polomolok Water District support chat. 😊\n\n"
             . "I can help you with:\n"
             . "• 💧 Water service inquiries\n"
             . "• 📄 Billing and payment questions\n"
             . "• 🔧 Reporting leaks or problems\n"
             . "• 📋 Request status updates\n"
             . "• 📞 Contact information\n\n"
             . "How may I assist you today?";
    }

    // ── Goodbye ───────────────────────────────────────────────
    if (preg_match('/\b(bye|goodbye|thank you|thanks|salamat|ok lang|sige)\b/i', $msg)) {
        return "Thank you for reaching out to Polomolok Water District! 😊 "
             . "If you need further assistance, feel free to chat again anytime. "
             . "Have a great day!";
    }

    // ── Billing / Payment ──────────────────────────────────────
    if (preg_match('/\b(bill|billing|payment|bayad|charge|invoice|monthly|statement|overcharg|dispute)\b/i', $msg)) {
        if (preg_match('/\b(dispute|overcharg|wrong|incorrect|error|mali)\b/i', $msg)) {
            return "For billing disputes, please:\n\n"
                 . "1. Submit a **Billing Dispute** request through the Request Portal\n"
                 . "2. Include your account number and the specific concern\n"
                 . "3. Attach any supporting documents if available\n\n"
                 . "Our billing team will review your concern within 3-5 business days.\n\n"
                 . "📞 You may also call us at **(083) 123-4567** during office hours.";
        }
        return "For billing inquiries:\n\n"
             . "• Bills are generated monthly and sent to your registered address\n"
             . "• Payment can be made at the Water District office\n"
             . "• Office hours: Monday–Friday, 8:00 AM – 5:00 PM\n\n"
             . "📍 Address: Municipal Compound, Polomolok, South Cotabato\n"
             . "📞 Hotline: **(083) 123-4567**\n\n"
             . "For billing disputes, you can submit a request through the Request Portal.";
    }

    // ── Leak / Burst pipe / Damage ────────────────────────────
    if (preg_match('/\b(leak|leaking|burst|broken pipe|damage|tubo|tubig na wala|tubig butas|busted)\b/i', $msg)) {
        return "⚠️ **Please report pipe leaks or bursts immediately!**\n\n"
             . "**Emergency Hotline: (083) 999-0000** ← Available 24/7\n\n"
             . "You may also:\n"
             . "1. Submit a **Repair Request** in the Request Portal\n"
             . "2. Include your location so our team can respond quickly\n"
             . "3. Drop a map pin to show the exact problem location\n\n"
             . "Our field team will be dispatched as soon as possible.";
    }

    // ── No water / Low pressure ────────────────────────────────
    if (preg_match('/\b(no water|walang tubig|wala tubig|low pressure|mababa|weak|pressure|supply interrupted|nawalan)\b/i', $msg)) {
        // Check active interruptions
        $stmt = $db->query(
            "SELECT title, affected_barangays, start_datetime, end_datetime
             FROM water_interruptions
             WHERE status IN ('Ongoing','Scheduled')
             ORDER BY start_datetime DESC
             LIMIT 3"
        );
        $interruptions = $stmt->fetchAll();

        if ($interruptions) {
            $list = '';
            foreach ($interruptions as $intr) {
                $end   = $intr['end_datetime'] ? " → " . $intr['end_datetime'] : '';
                $list .= "• **{$intr['title']}**\n"
                       . "  Barangays: {$intr['affected_barangays']}\n"
                       . "  {$intr['start_datetime']}{$end}\n\n";
            }
            return "There are currently active water interruptions:\n\n{$list}"
                 . "We apologize for the inconvenience. Supply will be restored as scheduled.\n\n"
                 . "For emergencies: **(083) 999-0000**";
        }

        return "There are no reported water interruptions at this time.\n\n"
             . "If you're experiencing low pressure or no water, it may be due to:\n"
             . "• Internal plumbing issues in your property\n"
             . "• A local pipe problem in your area\n\n"
             . "Please **submit a Repair Request** through the portal or call:\n"
             . "📞 **(083) 123-4567** (office hours)\n"
             . "📞 **(083) 999-0000** (emergency, 24/7)";
    }

    // ── Request status ─────────────────────────────────────────
    if (preg_match('/\b(request|status|update|track|follow.?up|application|concern|sulat|reklamo)\b/i', $msg)) {
        // Look up the consumer's recent requests
        $cStmt = $db->prepare(
            "SELECT cr.id, cr.request_type, cr.status, cr.created_at
             FROM consumer_requests cr
             WHERE cr.consumer_auth_id = ?
             ORDER BY cr.created_at DESC
             LIMIT 3"
        );
        $cStmt->execute([$consumerId]);
        $requests = $cStmt->fetchAll();

        if ($requests) {
            $list = '';
            foreach ($requests as $req) {
                $list .= "• **#{$req['id']}** — {$req['request_type']} | Status: **{$req['status']}** | {$req['created_at']}\n";
            }
            return "Here are your recent requests:\n\n{$list}\n"
                 . "Visit the **Track Requests** page to see full details and updates.";
        }

        return "You haven't submitted any requests yet.\n\n"
             . "You can submit service requests from the **Report** page for issues like:\n"
             . "• Leaks or low water pressure\n"
             . "• New connections or disconnections\n"
             . "• General inquiries\n\n"
             . "All requests can be tracked in the **Track Requests** section.";
    }

    // ── New connection / disconnection / reconnection ──────────
    if (preg_match('/\b(new connection|apply|application|reconnect|disconnect|bagong koneksyon|kumonek)\b/i', $msg)) {
        if (preg_match('/\b(reconnect|ibalik|balik|restore)\b/i', $msg)) {
            return "To request a **Reconnection** of your water service:\n\n"
                 . "1. Submit a Reconnection request through the **Request Portal**\n"
                 . "2. Settle any outstanding balance at the office first\n"
                 . "3. Our team will process your reconnection within 3–5 working days\n\n"
                 . "📞 (083) 123-4567 for faster processing";
        }
        if (preg_match('/\b(disconnect|tanggal|tanggalin|cut)\b/i', $msg)) {
            return "To request a **Disconnection** of your water service:\n\n"
                 . "1. Submit a Disconnection request through the **Request Portal**\n"
                 . "2. Required: Valid ID and a written request letter\n"
                 . "3. Processing time: 3–5 working days\n\n"
                 . "📍 You may also visit our office for in-person assistance.";
        }
        return "To apply for a **New Water Connection**:\n\n"
             . "**Requirements:**\n"
             . "• Valid government-issued ID\n"
             . "• Proof of property ownership or occupancy (title, lease contract)\n"
             . "• Sketch plan of your property\n"
             . "• Barangay clearance\n\n"
             . "**Steps:**\n"
             . "1. Submit a New Connection request through the **Request Portal**\n"
             . "2. Pay the connection fee at the office\n"
             . "3. Inspection and installation by our team\n\n"
             . "📞 Call (083) 123-4567 for the current connection fee schedule.";
    }

    // ── Meter / Meter reading ──────────────────────────────────
    if (preg_match('/\b(meter|water meter|reading|consumption|usage|sukat|gamit)\b/i', $msg)) {
        return "Regarding your water meter:\n\n"
             . "• Meters are read monthly by our assigned meter readers\n"
             . "• Your consumption history is viewable in your account dashboard\n"
             . "• If you suspect a faulty meter, submit a **Repair Request**\n\n"
             . "**Excessive consumption tips:**\n"
             . "• Check for leaking faucets or toilets\n"
             . "• Inspect water pipes inside your property\n"
             . "• Request a meter inspection if abnormally high\n\n"
             . "📞 (083) 123-4567 for meter-related concerns";
    }

    // ── Office location / hours ────────────────────────────────
    if (preg_match('/\b(office|location|address|hours|open|saan|nasaan|oras|araw)\b/i', $msg)) {
        return "**Polomolok Water District Office:**\n\n"
             . "📍 Municipal Compound, Polomolok, South Cotabato\n"
             . "🕐 Monday–Friday: 8:00 AM – 5:00 PM\n"
             . "📞 Office: (083) 123-4567\n"
             . "🚨 Emergency (24/7): (083) 999-0000\n"
             . "📧 Email: support@polomolok.gov.ph\n"
             . "🌐 Website: https://polomolok.gov.ph/\n\n"
             . "We are closed on weekends and public holidays.";
    }

    // ── Interruption / Advisory ────────────────────────────────
    if (preg_match('/\b(interruption|advisory|maintenance|scheduled|planned|schedule|maintenance work)\b/i', $msg)) {
        $stmt = $db->query(
            "SELECT title, affected_barangays, start_datetime, end_datetime, status
             FROM water_interruptions
             WHERE status != 'Resolved'
             ORDER BY start_datetime ASC
             LIMIT 5"
        );
        $interruptions = $stmt->fetchAll();

        if ($interruptions) {
            $list = '';
            foreach ($interruptions as $intr) {
                $end = $intr['end_datetime'] ? " → {$intr['end_datetime']}" : '';
                $list .= "• [{$intr['status']}] **{$intr['title']}**\n"
                       . "  Areas: {$intr['affected_barangays']}\n"
                       . "  {$intr['start_datetime']}{$end}\n\n";
            }
            return "Current/Upcoming Water Interruptions:\n\n{$list}"
                 . "Check the **Interruptions** page for full details.";
        }

        return "No scheduled water interruptions at this time. ✅\n\n"
             . "We will notify you via in-app notification and email "
             . "if there are any planned interruptions in your area.";
    }

    // ── Help / commands ────────────────────────────────────────
    if (preg_match('/\b(help|what can you do|commands|guide|tulong|paano)\b/i', $msg)) {
        return "Here's what I can help you with:\n\n"
             . "💧 **Water Issues** — Type: *\"no water\"*, *\"low pressure\"*, *\"leak\"*\n"
             . "📄 **Billing** — Type: *\"billing\"*, *\"payment\"*, *\"dispute\"*\n"
             . "🔌 **Connection** — Type: *\"new connection\"*, *\"reconnect\"*, *\"disconnect\"*\n"
             . "📋 **My Requests** — Type: *\"request status\"*, *\"track request\"*\n"
             . "📅 **Interruptions** — Type: *\"interruption\"*, *\"advisory\"*\n"
             . "📍 **Office Info** — Type: *\"office\"*, *\"location\"*, *\"hours\"*\n"
             . "📞 **Emergency** — Type: *\"emergency\"* or call **(083) 999-0000**\n\n"
             . "Just type your question naturally — I'll do my best to assist!";
    }

    // ── Emergency ─────────────────────────────────────────────
    if (preg_match('/\b(emergency|urgent|agahan|asap|tulong|help me|SOS)\b/i', $msg)) {
        return "🚨 **EMERGENCY CONTACT**\n\n"
             . "**Hotline: (083) 999-0000** ← Available 24 hours, 7 days a week\n\n"
             . "For non-emergency concerns:\n"
             . "📞 Office: (083) 123-4567 (Mon–Fri, 8AM–5PM)\n\n"
             . "You can also submit an emergency report through the **Report** page "
             . "and pin the exact location on the map.";
    }

    // ── Default fallback ───────────────────────────────────────
    return "I'm sorry, I didn't quite understand your message. 🤔\n\n"
         . "You can ask me about:\n"
         . "• Water supply issues (leaks, no water, low pressure)\n"
         . "• Billing and payments\n"
         . "• New connections or reconnections\n"
         . "• My request status\n"
         . "• Office location and contact numbers\n\n"
         . "Type **\"help\"** to see all available topics, or call us at **(083) 123-4567**.";
}