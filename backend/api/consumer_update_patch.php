<?php
/**
 * This file documents the patch to apply to backend/api/consumer.php.
 *
 * CHANGE 1: Add after the require_once lines at the top of consumer.php:
 *
 *   require_once __DIR__ . '/consumer_request_hooks.php';
 *
 * CHANGE 2: Replace the updateRequest() function body with the version below.
 *
 * Since we cannot modify consumer.php directly in this delivery without
 * replacing the entire file, the patched updateRequest() is shown here
 * and the full consumer.php with the patch applied follows.
 */

function updateRequest_PATCHED(): void {
    requireRole('Admin', 'Staff');
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $db   = getDB();

    $requestId       = (int)($data['id'] ?? 0);
    $newStatus       = $data['status'] ?? '';
    $resolutionNotes = $data['resolution_notes'] ?? '';
    $assignedTo      = $data['assigned_to'] ?? null;

    if (!$requestId || !$newStatus) {
        jsonResponse(['error' => 'id and status required'], 422);
    }

    $stmt = $db->prepare(
        "UPDATE consumer_requests
         SET status            = ?,
             assigned_to       = ?,
             resolution_notes  = ?,
             resolved_at       = IF(status = 'Resolved', NOW(), resolved_at)
         WHERE id = ?"
    );
    $stmt->execute([$newStatus, $assignedTo, $resolutionNotes, $requestId]);

    // ── Trigger notifications ─────────────────────────────────
    if (function_exists('fireRequestUpdateNotifications')) {
        fireRequestUpdateNotifications($db, $requestId, $newStatus, $resolutionNotes);
    }

    jsonResponse(['success' => true]);
}