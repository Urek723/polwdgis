<?php
// backend/api/consumer_auth.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

startSession();
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'register': handleRegister(); break;
    case 'login':    handleLogin();    break;
    case 'logout':   handleLogout();   break;
    case 'me':       handleMe();       break;
    default: jsonResponse(['error' => 'Unknown action'], 400);
}

function handleRegister(): void {
    $name           = trim($_POST['name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $password       = $_POST['password'] ?? '';

    // ── Validation ─────────────────────────────
    if (!$name || !$account_number || !$contact_number || !$email || !$password) {
        jsonResponse(['error' => 'All fields are required'], 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Invalid email address'], 422);
    }

    if (strlen($password) < 6) {
        jsonResponse(['error' => 'Password must be at least 6 characters'], 422);
    }

    $db = getDB();

    // ── Check duplicate account number ────────
    $check = $db->prepare("SELECT id FROM consumers_auth WHERE account_number = ?");
    $check->execute([$account_number]);
    if ($check->fetch()) {
        jsonResponse(['error' => 'Account number already registered'], 409);
    }

    // ── OPTIONAL: Check duplicate email ───────
    $checkEmail = $db->prepare("SELECT id FROM consumers_auth WHERE email = ?");
    $checkEmail->execute([$email]);
    if ($checkEmail->fetch()) {
        jsonResponse(['error' => 'Email already registered'], 409);
    }

    // ── INSERT USER ───────────────────────────
    $stmt = $db->prepare(
        "INSERT INTO consumers_auth 
        (name, account_number, contact_number, email, password_hash)
        VALUES (?, ?, ?, ?, ?)"
    );

    $stmt->execute([
        $name,
        $account_number,
        $contact_number,
        $email,
        password_hash($password, PASSWORD_BCRYPT),
    ]);

    jsonResponse(['success' => true]);
}

function handleLogin(): void {
    $account_number = trim($_POST['account_number'] ?? '');
    $password       = $_POST['password'] ?? '';

    if (!$account_number || !$password) {
        jsonResponse(['error' => 'Account number and password are required'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM consumers_auth WHERE account_number = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$account_number]);
    $consumer = $stmt->fetch();

    if (!$consumer || !password_verify($password, $consumer['password_hash'])) {
        jsonResponse(['error' => 'Invalid account number or password'], 401);
    }

    $_SESSION['consumer_auth_id']      = $consumer['id'];
    $_SESSION['consumer_name']         = $consumer['name'];
    $_SESSION['consumer_account_number'] = $consumer['account_number'];
    $_SESSION['consumer_contact_number'] = $consumer['contact_number'];

    // Explicitly unset admin session keys so there's no cross-contamination
    unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);

    jsonResponse([
        'success' => true,
        'consumer' => [
            'id'             => $consumer['id'],
            'name'           => $consumer['name'],
            'account_number' => $consumer['account_number'],
        ],
    ]);
}

function handleLogout(): void {
    startSession();
    session_destroy();
    jsonResponse(['success' => true]);
}

function handleMe(): void {
    startSession();
    if (empty($_SESSION['consumer_auth_id'])) {
        jsonResponse(['error' => 'Not authenticated'], 401);
    }
    jsonResponse([
        'id'             => $_SESSION['consumer_auth_id'],
        'name'           => $_SESSION['consumer_name'],
        'account_number' => $_SESSION['consumer_account_number'],
        'contact_number' => $_SESSION['consumer_contact_number'],
    ]);
}