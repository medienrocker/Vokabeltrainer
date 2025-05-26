<?php
/**
 * Speichert einen Leaderboard-Eintrag
 * Datei: api/save_leaderboard.php
 */

require_once '../includes/debug.php';
require_once '../includes/users.php';
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Nicht angemeldet'
    ]);
    exit;
}

$userId = $_SESSION['user']['id'];
$listId = isset($_POST['list_id']) ? (int)$_POST['list_id'] : 0;
$score = isset($_POST['score']) ? (int)$_POST['score'] : 0;
$streakBest = isset($_POST['streak_best']) ? (int)$_POST['streak_best'] : 0;
$timeSpent = isset($_POST['time_spent']) ? (int)$_POST['time_spent'] : 0;

if ($listId <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Ungültige Listen-ID'
    ]);
    exit;
}

// Speichere den Leaderboard-Eintrag
$result = saveLeaderboardEntry($userId, $listId, $score, $streakBest, $timeSpent);

echo json_encode($result);
?>