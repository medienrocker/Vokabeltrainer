<?php
/**
 * Benutzerabmeldung
 * Datei: logout.php (im Hauptverzeichnis)
 */

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Remember-Me-Cookie löschen
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// Session zerstören
session_destroy();

// Redirect-Parameter prüfen
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';

// Sicherheitscheck für Redirect
$allowedRedirects = ['index.php', 'login.php'];
if (!in_array($redirect, $allowedRedirects)) {
    $redirect = 'index.php';
}

// Weiterleitung
header("Location: $redirect?logged_out=1");
exit;
?>