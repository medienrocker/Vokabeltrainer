<?php
/**
 * Lädt das Leaderboard für eine Vokabelliste
 * Datei: api/get_leaderboard.php
 */

require_once '../includes/debug.php';
require_once '../includes/users.php';
header('Content-Type: application/json');

$listId = isset($_GET['list_id']) ? (int)$_GET['list_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

if ($listId <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Ungültige Listen-ID'
    ]);
    exit;
}

// Begrenze die maximale Anzahl
if ($limit > 50) $limit = 50;

// Lade das Leaderboard
$leaderboard = getLeaderboard($listId, $limit);

echo json_encode($leaderboard);
?>