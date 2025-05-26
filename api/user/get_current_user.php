<?php
/**
 * Gibt den aktuell angemeldeten Benutzer zurück
 * Datei: api/user/get_current_user.php
 */

header('Content-Type: application/json');

session_start();
if (isset($_SESSION['user'])) {
    echo json_encode([
        'success' => true,
        'user' => $_SESSION['user']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Nicht angemeldet'
    ]);
}
?>