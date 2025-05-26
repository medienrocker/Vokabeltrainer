<?php
require_once 'debug.php';
require_once 'db_connect.php';

// Alle verfügbaren Listen abrufen
function getVocabularyLists($userId = null, $includePrivate = false) {
    $pdo = connectDB();
    
    $sql = "SELECT vl.*, 
            (SELECT COUNT(*) FROM vocabulary_items WHERE list_id = vl.id) as word_count
            FROM vocabulary_lists vl
            WHERE vl.is_public = TRUE";
    
    if ($userId && $includePrivate) {
        $sql .= " OR vl.created_by = :userId";
    }
    
    $sql .= " ORDER BY vl.name ASC";
    
    $stmt = $pdo->prepare($sql);
    
    if ($userId && $includePrivate) {
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Liste mit Vokabeln laden
function getVocabularyItems($listId) {
    $pdo = connectDB();
    
    $stmt = $pdo->prepare("SELECT * FROM vocabulary_items WHERE list_id = :listId");
    $stmt->bindParam(':listId', $listId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// CSV-Datei importieren
function importVocabularyFromCSV($file, $userId = null) {
    if (!file_exists($file) || !is_readable($file)) {
        return false;
    }
    
    $pdo = connectDB();
    $pdo->beginTransaction();
    
    try {
        // Dateiname als Listenname verwenden
        $basename = basename($file, '.csv');
        $listName = str_replace('_', ' ', $basename);
        
        // Liste erstellen
        $stmt = $pdo->prepare("INSERT INTO vocabulary_lists (name, created_by) VALUES (:name, :userId)");
        $stmt->bindParam(':name', $listName);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        $listId = $pdo->lastInsertId();
        
        // CSV parsen und Vokabeln einfügen
        $handle = fopen($file, "r");
        
        // Header-Zeile lesen und verarbeiten
        $header = fgetcsv($handle, 0, ";");
        $headerMap = array_flip($header);
        
        $requiredFields = ['german', 'french'];
        foreach ($requiredFields as $field) {
            if (!isset($headerMap[$field])) {
                throw new Exception("CSV muss die Spalten 'german' und 'french' enthalten");
            }
        }
        
        // Vokabeln einfügen
        $insertStmt = $pdo->prepare("INSERT INTO vocabulary_items (list_id, term_from, term_to, category, difficulty) 
                                    VALUES (:listId, :termFrom, :termTo, :category, :difficulty)");
        
        while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
            $german = isset($data[$headerMap['german']]) ? trim($data[$headerMap['german']]) : '';
            $french = isset($data[$headerMap['french']]) ? trim($data[$headerMap['french']]) : '';
            
            if (empty($german) || empty($french)) continue;
            
            $category = isset($headerMap['category']) && isset($data[$headerMap['category']]) ? 
                        trim($data[$headerMap['category']]) : 'Allgemein';
                        
            $difficulty = isset($headerMap['level']) && isset($data[$headerMap['level']]) ? 
                          (int)trim($data[$headerMap['level']]) : 1;
            
            $insertStmt->bindParam(':listId', $listId);
            $insertStmt->bindParam(':termFrom', $german);
            $insertStmt->bindParam(':termTo', $french);
            $insertStmt->bindParam(':category', $category);
            $insertStmt->bindParam(':difficulty', $difficulty);
            $insertStmt->execute();
        }
        
        fclose($handle);
        $pdo->commit();
        
        return [
            'success' => true,
            'listId' => $listId,
            'name' => $listName
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
?>