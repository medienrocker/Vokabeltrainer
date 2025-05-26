<?php
require_once 'db_connect.php';
require_once 'debug.php';
// Basierend auf SuperMemo-2-Algorithmus
class SpacedRepetition {
    private $pdo;
    
    public function __construct() {
        $this->pdo = connectDB();
    }
    
    // Lernfortschritt für einen Benutzer und eine Vokabelliste laden
    public function loadProgress($userId, $listId) {
        $stmt = $this->pdo->prepare("
            SELECT v.*, lp.correct_count, lp.wrong_count, lp.last_seen, 
                   lp.next_review, lp.difficulty_factor
            FROM vocabulary_items v
            LEFT JOIN learning_progress lp ON v.id = lp.vocabulary_id AND lp.user_id = :userId
            WHERE v.list_id = :listId
            ORDER BY 
                CASE 
                    WHEN lp.next_review IS NULL THEN 0  -- Noch nie gesehen
                    WHEN lp.next_review <= NOW() THEN 1 -- Zur Wiederholung fällig
                    ELSE 2                              -- Später wiederholen
                END,
                lp.difficulty_factor DESC,
                lp.wrong_count DESC
        ");
        
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':listId', $listId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Antwort verarbeiten und nächstes Datum berechnen
    public function processAnswer($userId, $vocabId, $isCorrect) {
        // Zuerst prüfen, ob ein Eintrag existiert
        $stmt = $this->pdo->prepare("
            SELECT * FROM learning_progress 
            WHERE user_id = :userId AND vocabulary_id = :vocabId
        ");
        
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':vocabId', $vocabId, PDO::PARAM_INT);
        $stmt->execute();
        
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $now = new DateTime();
        
        if (!$progress) {
            // Neuer Eintrag
            $correctCount = $isCorrect ? 1 : 0;
            $wrongCount = $isCorrect ? 0 : 1;
            $difficultyFactor = 2.5; // Standardwert
            
            // Erste Wiederholung
            $nextReview = $this->calculateNextReview(0, $isCorrect, $difficultyFactor);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO learning_progress 
                (user_id, vocabulary_id, correct_count, wrong_count, last_seen, next_review, difficulty_factor)
                VALUES (:userId, :vocabId, :correctCount, :wrongCount, NOW(), :nextReview, :difficultyFactor)
            ");
            
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':vocabId', $vocabId, PDO::PARAM_INT);
            $stmt->bindParam(':correctCount', $correctCount, PDO::PARAM_INT);
            $stmt->bindParam(':wrongCount', $wrongCount, PDO::PARAM_INT);
            $stmt->bindParam(':nextReview', $nextReview->format('Y-m-d H:i:s'));
            $stmt->bindParam(':difficultyFactor', $difficultyFactor);
            
            $stmt->execute();
        } else {
            // Bestehenden Eintrag aktualisieren
            $correctCount = $progress['correct_count'] + ($isCorrect ? 1 : 0);
            $wrongCount = $progress['wrong_count'] + ($isCorrect ? 0 : 1);
            
            // Schwierigkeitsfaktor anpassen
            $quality = $isCorrect ? 5 : 2; // 5=perfekt, 2=schwierig
            $difficultyFactor = $progress['difficulty_factor'];
            $difficultyFactor = $difficultyFactor + (0.1 - (5 - $quality) * 0.08);
            
            if ($difficultyFactor < 1.3) $difficultyFactor = 1.3;
            
            // Interval berechnen
            $repetitionNumber = $correctCount;
            if (!$isCorrect) $repetitionNumber = 0; // Bei falscher Antwort zurücksetzen
            
            $nextReview = $this->calculateNextReview($repetitionNumber, $isCorrect, $difficultyFactor);
            
            $stmt = $this->pdo->prepare("
                UPDATE learning_progress 
                SET correct_count = :correctCount,
                    wrong_count = :wrongCount,
                    last_seen = NOW(),
                    next_review = :nextReview,
                    difficulty_factor = :difficultyFactor
                WHERE user_id = :userId AND vocabulary_id = :vocabId
            ");
            
            $stmt->bindParam(':correctCount', $correctCount, PDO::PARAM_INT);
            $stmt->bindParam(':wrongCount', $wrongCount, PDO::PARAM_INT);
            $stmt->bindParam(':nextReview', $nextReview->format('Y-m-d H:i:s'));
            $stmt->bindParam(':difficultyFactor', $difficultyFactor);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':vocabId', $vocabId, PDO::PARAM_INT);
            
            $stmt->execute();
        }
        
        return [
            'success' => true,
            'next_review' => $nextReview->format('Y-m-d H:i:s')
        ];
    }
    
    // SuperMemo-2-Algorithmus Implementierung
    private function calculateNextReview($repetitionNumber, $isCorrect, $difficultyFactor) {
        $now = new DateTime();
        
        if (!$isCorrect) {
            // Bei falscher Antwort: in einer Minute wieder abfragen
            return $now->add(new DateInterval('PT1M'));
        }
        
        if ($repetitionNumber == 0) {
            // Erste richtige Antwort: in 1 Tag
            return $now->add(new DateInterval('P1D'));
        }
        else if ($repetitionNumber == 1) {
            // Zweite richtige Antwort: in 6 Tagen
            return $now->add(new DateInterval('P6D'));
        }
        else {
            // Weitere richtige Antworten: Intervallfaktor anwenden
            $days = ceil(6 * $difficultyFactor * ($repetitionNumber - 1));
            return $now->add(new DateInterval("P{$days}D"));
        }
    }
}
?>