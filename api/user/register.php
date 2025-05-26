<?php
session_start();

// Debugging aktivieren
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Registrierungs API Endpunkt
 * Datei: api/user/register.php
 */

require_once '../../includes/config.php';
require_once '../../includes/debug.php';
require_once '../../includes/users.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
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
$email = trim($_POST['email'] ?? '');
$email = empty($email) ? null : $email;
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$agreeTerms = isset($_POST['agree_terms']);

$errors = [];

// Validierung
if (empty($username)) {
    $errors[] = 'Benutzername ist erforderlich';
} elseif (strlen($username) < 3) {
    $errors[] = 'Benutzername muss mindestens 3 Zeichen lang sein';
} elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
    $errors[] = 'Benutzername darf nur Buchstaben, Zahlen, - und _ enthalten';
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Ungültige E-Mail-Adresse';
}

if (empty($password)) {
    $errors[] = 'Passwort ist erforderlich';
} elseif (strlen($password) < 6) {
    $errors[] = 'Passwort muss mindestens 6 Zeichen lang sein';
}

if ($password !== $confirmPassword) {
    $errors[] = 'Passwörter stimmen nicht überein';
}

if (!$agreeTerms) {
    $errors[] = 'Sie müssen den Nutzungsbedingungen zustimmen';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'errors' => $errors
    ]);
    exit;
}

// Registrierung versuchen
// Debug: Log die Eingabedaten
error_log("Register attempt: username=$username, email=$email");

$result = registerUser($username, $password, $email);

// Debug: Log das Ergebnis
error_log("Register result: " . json_encode($result));

if ($result['success']) {
    // Automatisch anmelden nach Registrierung
    $loginResult = loginUser($username, $password);
    
    if ($loginResult['success']) {
    echo json_encode([
        'success' => true,
        'user' => $loginResult['user'],
        'message' => 'Erfolgreich registriert'
    ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Erfolgreich registriert. Bitte melden Sie sich an.'
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $result['error']
    ]);
}
?>