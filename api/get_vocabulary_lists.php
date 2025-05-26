<?php
require_once '../includes/debug.php';
require_once '../includes/vocabulary_lists.php';

header('Content-Type: application/json');

session_start();
$userId = isset($_SESSION['user']) ? $_SESSION['user']['id'] : null;
$includePrivate = isset($_SESSION['user']) && $_SESSION['user']['is_teacher'];

$lists = getVocabularyLists($userId, $includePrivate);
echo json_encode($lists);
?>