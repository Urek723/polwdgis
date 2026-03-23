<?php
// backend/api/auth.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

startSession();
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'me':
        handleMe();
        break;
    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}

function handleLogin(): void {
    $username = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';

    if (!$username || !$password) {
        jsonResponse(['error' => 'Username and password are required'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        logActivity(0, 'login_failed', 'users', $username, 'Invalid credentials');
        jsonResponse(['error' => 'Invalid username or password'], 401);
    }

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['name']     = $user['name'];
    $_SESSION['role']     = $user['role'];
    $_SESSION['section']  = $user['section'];

    logActivity($user['id'], 'login', 'users', (string)$user['id'], 'User logged in');

    jsonResponse([
        'success' => true,
        'user' => [
            'id'       => $user['id'],
            'username' => $user['username'],
            'name'     => $user['name'],
            'role'     => $user['role'],
            'section'  => $user['section'],
        ]
    ]);
}

function handleLogout(): void {
    startSession();
    if (!empty($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'logout', 'users', (string)$_SESSION['user_id'], 'User logged out');
    }
    session_destroy();
    jsonResponse(['success' => true]);
}

function handleMe(): void {
    startSession();
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['error' => 'Not authenticated'], 401);
    }
    jsonResponse([
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'name'     => $_SESSION['name'],
        'role'     => $_SESSION['role'],
        'section'  => $_SESSION['section'],
    ]);
}
