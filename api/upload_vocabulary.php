<?php
/**
 * CSV-Upload-Endpunkt
 * Datei: api/upload_vocabulary.php
 */

require_once '../includes/debug.php';
require_once '../includes/vocabulary_lists.php';
header('Content-Type: application/json');

// Überprüfen, ob der Benutzer angemeldet ist und Lehrer-Rechte hat
session_start();
$userId = isset($_SESSION['user']) ? $_SESSION['user']['id'] : null;
$isTeacher = isset($_SESSION['user']) ? $_SESSION['user']['is_teacher'] : false;

// Nur Lehrer dürfen Vokabellisten hochladen
if (!$userId || !$isTeacher) {
    echo json_encode([
        'success' => false,
        'error' => 'Keine Berechtigung zum Hochladen von Vokabellisten'
    ]);
    exit;
}

// Prüfen, ob eine Datei hochgeladen wurde
if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'Die hochgeladene Datei überschreitet die upload_max_filesize Direktive in der php.ini',
        UPLOAD_ERR_FORM_SIZE => 'Die hochgeladene Datei überschreitet die MAX_FILE_SIZE Direktive im HTML-Formular',
        UPLOAD_ERR_PARTIAL => 'Die Datei wurde nur teilweise hochgeladen',
        UPLOAD_ERR_NO_FILE => 'Es wurde keine Datei hochgeladen',
        UPLOAD_ERR_NO_TMP_DIR => 'Kein temporäres Verzeichnis gefunden',
        UPLOAD_ERR_CANT_WRITE => 'Fehler beim Schreiben der Datei auf die Festplatte',
        UPLOAD_ERR_EXTENSION => 'Eine PHP-Erweiterung hat den Upload gestoppt'
    ];
    
    $errorCode = $_FILES['csvFile']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errorMessage = $errorMessages[$errorCode] ?? 'Unbekannter Fehler beim Hochladen';
    
    echo json_encode([
        'success' => false,
        'error' => $errorMessage
    ]);
    exit;
}

// Dateiformat überprüfen
$fileType = mime_content_type($_FILES['csvFile']['tmp_name']);
if ($fileType !== 'text/csv' && $fileType !== 'text/plain') {
    echo json_encode([
        'success' => false,
        'error' => 'Ungültiges Dateiformat. Bitte laden Sie eine CSV-Datei hoch.'
    ]);
    exit;
}

// Uploads-Verzeichnis erstellen, falls nicht vorhanden
$uploadDir = '../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Dateinamen generieren
$timestamp = time();
$originalName = pathinfo($_FILES['csvFile']['name'], PATHINFO_FILENAME);
$safeFilename = preg_replace('/[^a-z0-9_-]/i', '_', $originalName);
$uploadFile = $uploadDir . $safeFilename . '_' . $timestamp . '.csv';

// Datei in den Upload-Ordner verschieben
if (!move_uploaded_file($_FILES['csvFile']['tmp_name'], $uploadFile)) {
    echo json_encode([
        'success' => false,
        'error' => 'Fehler beim Speichern der Datei'
    ]);
    exit;
}

// CSV-Header überprüfen
$handle = fopen($uploadFile, "r");
$headerLine = fgets($handle);
fclose($handle);

// Prüfen, ob die Datei das richtige Format hat
$delimiter = ';';
if (strpos($headerLine, $delimiter) === false) {
    // Versuche Komma als Trennzeichen
    $delimiter = ',';
    if (strpos($headerLine, $delimiter) === false) {
        // Weder Semikolon noch Komma gefunden
        unlink($uploadFile); // Datei löschen
        echo json_encode([
            'success' => false,
            'error' => 'Ungültiges CSV-Format. Bitte verwenden Sie Semikolon (;) oder Komma (,) als Trennzeichen.'
        ]);
        exit;
    }
}

// Öffne die Datei erneut und prüfe die Header
$handle = fopen($uploadFile, "r");
$headers = fgetcsv($handle, 0, $delimiter);
fclose($handle);

// Minimale Anforderungen: german und french Spalten
$requiredColumns = ['german', 'french'];
$headerMap = array_flip(array_map('strtolower', $headers));

foreach ($requiredColumns as $column) {
    if (!isset($headerMap[strtolower($column)])) {
        unlink($uploadFile); // Datei löschen
        echo json_encode([
            'success' => false,
            'error' => "Die Spalte '$column' fehlt in der CSV-Datei. Benötigte Spalten: " . implode(', ', $requiredColumns)
        ]);
        exit;
    }
}

// Datei in die Datenbank importieren
$result = importVocabularyFromCSV($uploadFile, $userId);

// Datei nach dem Import löschen
unlink($uploadFile);

// Ergebnis zurückgeben
echo json_encode($result);
?>