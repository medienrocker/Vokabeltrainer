<?php
/**
 * Globale Hilfsfunktionen für VocaBlitz
 * Datei: includes/functions.php
 */

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    return rtrim($protocol . $host . $path, '/') . '/';
}

function getAssetUrl($path) {
    return getBaseUrl() . 'assets/' . ltrim($path, '/');
}

function getApiUrl($endpoint) {
    return getBaseUrl() . 'api/' . ltrim($endpoint, '/');
}

// Sichere Include-Funktion
function secureInclude($file) {
    $basePath = __DIR__;
    $fullPath = realpath($basePath . '/' . $file);
    
    if ($fullPath && strpos($fullPath, $basePath) === 0 && file_exists($fullPath)) {
        return include $fullPath;
    }
    throw new Exception("File not found or access denied: " . $file);
}

// CSRF-Token Funktionen
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function renderCSRFField() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

// Input-Validierung
function validateAndSanitize($input, $type = 'string', $maxLength = 255) {
    $input = trim($input);
    
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) ?: false;
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT) ?: 0;
        case 'string':
            $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            return strlen($input) <= $maxLength ? $input : false;
        case 'username':
            return preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $input) ? $input : false;
        default:
            return false;
    }
}
?>