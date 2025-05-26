<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Login API Endpunkt
 * Datei: api/user/login.php
 */

require_once '../../includes/config.php';
require_once '../../includes/debug.php';
require_once '../../includes/users.php';
require_once '../../includes/functions.php';

// Fallback für MAX_LOGIN_ATTEMPTS falls nicht in config.php definiert
if (!defined('MAX_LOGIN_ATTEMPTS')) {
    define('MAX_LOGIN_ATTEMPTS', 5);
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Rate Limiting (einfach)
$attempts_key = 'login_attempts_' . $_SERVER['REMOTE_ADDR'];
$attempts = $_SESSION[$attempts_key] ?? 0;
$last_attempt = $_SESSION[$attempts_key . '_time'] ?? 0;

// Reset nach 15 Minuten
if (time() - $last_attempt > 900) {
    $attempts = 0;
}

if ($attempts >= MAX_LOGIN_ATTEMPTS) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Zu viele Anmeldeversuche. Bitte warten Sie 15 Minuten.',
        'remaining_attempts' => 0
    ]);
    exit;
}

// CSRF-Token validieren
// $csrfToken = $_POST['csrf_token'] ?? '';
// if (!validateCSRFToken($csrfToken)) {
//     http_response_code(403);
//     echo json_encode(['success' => false, 'error' => 'Invalid security token']);
//     exit;
// }
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode([
        'success' => false,
        'error' => 'Benutzername und Passwort sind erforderlich'
    ]);
    exit;
}

$result = loginUser($username, $password);
// Debug Login
error_log("Login result: " . json_encode($result));

if ($result['success']) {
    // Login erfolgreich - Attempts zurücksetzen
    unset($_SESSION[$attempts_key], $_SESSION[$attempts_key . '_time']);
    
    echo json_encode([
        'success' => true,
        'user' => $result['user'],
        'message' => 'Erfolgreich angemeldet'
    ]);
} else {
    // Login fehlgeschlagen - Attempts erhöhen
    $_SESSION[$attempts_key] = $attempts + 1;
    $_SESSION[$attempts_key . '_time'] = time();
    
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => $result['error'],
        'remaining_attempts' => MAX_LOGIN_ATTEMPTS - ($attempts + 1)
    ]);
}
?>