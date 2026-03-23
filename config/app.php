<?php
// config/app.php

define('APP_NAME', 'Pol Web GIS');
define('APP_URL', 'http://localhost/polwdgis');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('CSV_EXPORT_DIR', __DIR__ . '/../uploads/exports/');

// Session helper
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function requireAuth(): void {
    startSession();
    if (empty($_SESSION['user_id'])) {
        if (isAjax()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        header('Location: ' . APP_URL . '/frontend/pages/login.php');
        exit;
    }

    // Block consumer portal users from accessing admin/staff pages
    if (!empty($_SESSION['consumer_auth_id']) && empty($_SESSION['user_id'])) {
        if (isAjax()) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
        header('Location: ' . APP_URL . '/frontend/consumer/dashboard.php');
        exit;
    }
}

function requireRole(string ...$roles): void {
    requireAuth();
    if (!in_array($_SESSION['role'] ?? '', $roles)) {
        if (isAjax()) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
        header('Location: ' . APP_URL . '/frontend/pages/dashboard.php');
        exit;
    }
}

function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function jsonResponse(array $data, int $status = 200): void {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function logActivity(int $userId, string $action, string $table = '', string $recordId = '', string $details = ''): void {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO activity_logs (user_id, action, table_name, record_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $action, $table, $recordId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {
        // Logging failure is non-fatal
    }
}

function sanitize(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    startSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
