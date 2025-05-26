<?php
require_once '../includes/debug.php';
require_once '../includes/spaced_repetition.php';
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Nicht angemeldet']);
    exit;
}

$userId = $_SESSION['user']['id'];
$vocabId = isset($_POST['vocab_id']) ? (int)$_POST['vocab_id'] : 0;
$isCorrect = isset($_POST['is_correct']) ? (bool)$_POST['is_correct'] : false;

if ($vocabId <= 0) {
    echo json_encode(['error' => 'Ungültige Vokabel-ID']);
    exit;
}

$spacedRep = new SpacedRepetition();
$result = $spacedRep->processAnswer($userId, $vocabId, $isCorrect);

echo json_encode($result);
?>