<?php
// frontend/consumer/auth_guard.php
// Include this at the top of every consumer portal page

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

function requireConsumerAuth(): void {
    startSession();
    if (empty($_SESSION['consumer_auth_id'])) {
        header('Location: ' . APP_URL . '/frontend/consumer/login.php');
        exit;
    }
}

function requireConsumerGuest(): void {
    startSession();
    if (!empty($_SESSION['consumer_auth_id'])) {
        header('Location: ' . APP_URL . '/frontend/consumer/dashboard.php');
        exit;
    }
}

function getConsumerSession(): array {
    startSession();
    return [
        'id'             => $_SESSION['consumer_auth_id'] ?? null,
        'name'           => $_SESSION['consumer_name'] ?? '',
        'account_number' => $_SESSION['consumer_account_number'] ?? '',
        'contact_number' => $_SESSION['consumer_contact_number'] ?? '',
    ];
}