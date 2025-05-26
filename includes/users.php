<?php
require_once 'debug.php';
require_once 'db_connect.php';

// Benutzerregistrierung
function registerUser($username, $password, $email = null, $isTeacher = false) {
    $pdo = connectDB();
    
    /// Prüfen, ob Benutzername bereits existiert
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        return [
            'success' => false,
            'error' => 'Benutzername bereits vergeben'
        ];
    }

    // Prüfen, ob E-Mail bereits existiert (falls angegeben)
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email === '' ? null : $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return [
                'success' => false,
                'error' => 'E-Mail-Adresse bereits vergeben'
            ];
        }
    }
    
    // Hash des Passworts erstellen
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Benutzer erstellen
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password, email, is_teacher) 
        VALUES (:username, :password, :email, :isTeacher)
    ");
    
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $passwordHash);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':isTeacher', $isTeacher, PDO::PARAM_BOOL);
    
    try {
        $stmt->execute();
        
        return [
            'success' => true,
            'userId' => $pdo->lastInsertId()
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Fehler bei der Registrierung: ' . $e->getMessage()
        ];
    }
}

// Benutzeranmeldung
function loginUser($username, $password) {
    $pdo = connectDB();
    
    $stmt = $pdo->prepare("SELECT id, username, password, is_teacher FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($password, $user['password'])) {
        return [
            'success' => false,
            'error' => 'Ungültiger Benutzername oder Passwort'
        ];
    }
    
    // Passwort aus dem Ergebnis entfernen
    unset($user['password']);
    
    // Session starten und Benutzer speichern (falls noch nicht aktiv)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user'] = $user;
    
    return [
        'success' => true,
        'user' => $user
    ];
}

// Leaderboard-Eintrag speichern
function saveLeaderboardEntry($userId, $listId, $score, $streakBest, $timeSpent) {
    $pdo = connectDB();
    
    // Prüfen, ob bereits ein Eintrag existiert
    $stmt = $pdo->prepare("
        SELECT id, score, streak_best, time_spent 
        FROM leaderboard_entries 
        WHERE user_id = :userId AND list_id = :listId
    ");
    
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':listId', $listId, PDO::PARAM_INT);
    $stmt->execute();
    
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($entry) {
        // Nur aktualisieren, wenn besser als bestehender Eintrag
        $updateNeeded = false;
        
        if ($score > $entry['score']) $updateNeeded = true;
        if ($streakBest > $entry['streak_best']) $updateNeeded = true;
        
        if ($updateNeeded) {
            $stmt = $pdo->prepare("
                UPDATE leaderboard_entries 
                SET score = :score,
                    streak_best = :streakBest,
                    time_spent = :timeSpent,
                    completed_at = NOW()
                WHERE id = :id
            ");
            
            $stmt->bindParam(':score', $score, PDO::PARAM_INT);
            $stmt->bindParam(':streakBest', $streakBest, PDO::PARAM_INT);
            $stmt->bindParam(':timeSpent', $timeSpent, PDO::PARAM_INT);
            $stmt->bindParam(':id', $entry['id'], PDO::PARAM_INT);
            
            $stmt->execute();
        }
    } else {
        // Neuen Eintrag erstellen
        $stmt = $pdo->prepare("
            INSERT INTO leaderboard_entries (user_id, list_id, score, streak_best, time_spent) 
            VALUES (:userId, :listId, :score, :streakBest, :timeSpent)
        ");
        
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':listId', $listId, PDO::PARAM_INT);
        $stmt->bindParam(':score', $score, PDO::PARAM_INT);
        $stmt->bindParam(':streakBest', $streakBest, PDO::PARAM_INT);
        $stmt->bindParam(':timeSpent', $timeSpent, PDO::PARAM_INT);
        
        $stmt->execute();
    }
    
    return [
        'success' => true
    ];
}

// Leaderboard abrufen
function getLeaderboard($listId, $limit = 10) {
    $pdo = connectDB();
    
    $stmt = $pdo->prepare("
        SELECT le.*, u.username 
        FROM leaderboard_entries le
        JOIN users u ON le.user_id = u.id
        WHERE le.list_id = :listId
        ORDER BY le.score DESC, le.streak_best DESC, le.time_spent ASC
        LIMIT :limit
    ");
    
    $stmt->bindParam(':listId', $listId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>