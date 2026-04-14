<?php
// backend/api/consumer_request_hooks.php
// Shared notification hooks triggered when request status changes
// Included by consumer.php

if (!defined('HOOKS_LOADED')) {
    define('HOOKS_LOADED', true);
}

// Load email helper (non-fatal)
$emailHelper = __DIR__ . '/../notifications/send_email.php';
if (file_exists($emailHelper)) {
    require_once $emailHelper;
}

/**
 * Fire in-app + email notifications when a consumer request is updated.
 *
 * @param PDO    $db
 * @param int    $requestId
 * @param string $newStatus
 * @param string $resolutionNotes
 */
function fireRequestUpdateNotifications(
    PDO    $db,
    int    $requestId,
    string $newStatus,
    string $resolutionNotes = ''
): void {
    // Fetch request + consumer info
    $stmt = $db->prepare(
        "SELECT cr.*,
                COALESCE(ca.name, c.name, 'Consumer') AS consumer_name,
                ca.id   AS consumer_auth_id,
                c.id    AS consumer_id,
                c.email AS consumer_email
         FROM consumer_requests cr
         LEFT JOIN consumers_auth ca ON ca.id = cr.consumer_auth_id
         LEFT JOIN consumers      c  ON c.id  = cr.consumer_id
         WHERE cr.id = ?
         LIMIT 1"
    );
    $stmt->execute([$requestId]);
    $req = $stmt->fetch();

    if (!$req) return;

    $consumerName = $req['consumer_name'] ?? 'Consumer';
    $requestType  = $req['request_type'] ?? 'Service Request';

    // ── In-app notification ───────────────────────────────────
    $notifMsg = "Your {$requestType} request (#{$requestId}) status has been updated to: {$newStatus}.";
    if ($resolutionNotes) $notifMsg .= " Notes: " . mb_substr($resolutionNotes, 0, 200);

    // Determine target column
    try {
        $cols = $db->query("SHOW COLUMNS FROM notifications")->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($req['consumer_auth_id']) && in_array('consumer_auth_id', $cols)) {
            $db->prepare(
                "INSERT INTO notifications (consumer_auth_id, type, title, message, is_read)
                 VALUES (?, 'message', ?, ?, 0)"
            )->execute([
                (int)$req['consumer_auth_id'],
                "Request #{$requestId} — {$newStatus}",
                $notifMsg,
            ]);
        } elseif (!empty($req['consumer_id'])) {
            $db->prepare(
                "INSERT INTO notifications (consumer_id, type, title, message, is_read)
                 VALUES (?, 'message', ?, ?, 0)"
            )->execute([
                (int)$req['consumer_id'],
                "Request #{$requestId} — {$newStatus}",
                $notifMsg,
            ]);
        }
    } catch (PDOException $e) {
        error_log('[Hooks] Notification insert failed: ' . $e->getMessage());
    }

    // ── Email notification (non-fatal) ────────────────────────
    if (!function_exists('sendRequestStatusEmail')) return;
    if (empty($req['consumer_email'])) return;

    try {
        sendRequestStatusEmail(
            $req['consumer_email'],
            $consumerName,
            $requestId,
            $requestType,
            $newStatus,
            $resolutionNotes
        );
    } catch (Throwable $e) {
        error_log('[Hooks] Email notification failed: ' . $e->getMessage());
    }
}

/**
 * Fire in-app + email notifications for water interruptions.
 * Called after sendInterruptionNotif() in consumer.php.
 *
 * @param PDO    $db
 * @param array  $interruption  Row from water_interruptions
 * @param array  $consumers     Array of consumer rows with email, name, id
 */
function fireInterruptionNotifications(PDO $db, array $interruption, array $consumers): void {
    if (!function_exists('sendInterruptionEmail')) return;

    foreach ($consumers as $c) {
        if (empty($c['email'])) continue;
        try {
            sendInterruptionEmail(
                $c['email'],
                $c['name'] ?? 'Consumer',
                $interruption['title'] ?? 'Water Interruption',
                $interruption['description'] ?? '',
                $interruption['affected_barangays'] ?? '',
                $interruption['start_datetime'] ?? '',
                $interruption['end_datetime'] ?? ''
            );
        } catch (Throwable $e) {
            error_log('[Hooks] Interruption email failed for ' . ($c['email'] ?? 'unknown') . ': ' . $e->getMessage());
        }
    }
}