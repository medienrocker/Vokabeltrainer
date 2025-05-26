<?php
require_once '../includes/debug.php';
require_once '../includes/vocabulary_lists.php';
require_once '../includes/spaced_repetition.php';
header('Content-Type: application/json');

session_start();
$userId = isset($_SESSION['user']) ? $_SESSION['user']['id'] : null;
$listId = isset($_GET['list_id']) ? (int)$_GET['list_id'] : 0;

if ($listId <= 0) {
    echo json_encode(['error' => 'Ungültige Listen-ID']);
    exit;
}

// Wenn ein Benutzer angemeldet ist, den Spaced-Repetition-Algorithmus verwenden
if ($userId) {
    $spacedRep = new SpacedRepetition();
    $items = $spacedRep->loadProgress($userId, $listId);
} else {
    // Andernfalls einfach alle Vokabeln laden
    $items = getVocabularyItems($listId);
}

echo json_encode($items);
?>