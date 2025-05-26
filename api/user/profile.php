<?php
/**
 * Benutzerprofilmanagement API
 * Datei: api/user/profile.php
 */

require_once '../../includes/db_connect.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Anmeldung prüfen
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Nicht angemeldet'
    ]);
    exit;
}

$currentUser = $_SESSION['user'];
$pdo = connectDB();

// GET: Profil abrufen
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Vollständige Benutzerdaten laden
        $stmt = $pdo->prepare("
            SELECT id, username, email, is_teacher, created_at 
            FROM users 
            WHERE id = :id
        ");
        $stmt->bindParam(':id', $currentUser['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode([
                'success' => false,
                'error' => 'Benutzer nicht gefunden'
            ]);
            exit;
        }
        
        // Lernstatistiken laden
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT vi.list_id) as lists_studied,
                SUM(lp.correct_count) as total_correct,
                SUM(lp.wrong_count) as total_wrong,
                MAX(lp.last_seen) as last_activity,
                COUNT(lp.id) as vocab_studied
            FROM learning_progress lp
            JOIN vocabulary_items vi ON lp.vocabulary_id = vi.id
            WHERE lp.user_id = :userId
        ");
        $stmt->bindParam(':userId', $currentUser['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Beste Leaderboard-Ergebnisse
        $stmt = $pdo->prepare("
            SELECT 
                le.score,
                le.streak_best,
                le.time_spent,
                le.completed_at,
                vl.name as list_name
            FROM leaderboard_entries le
            JOIN vocabulary_lists vl ON le.list_id = vl.id
            WHERE le.user_id = :userId
            ORDER BY le.score DESC
            LIMIT 5
        ");
        $stmt->bindParam(':userId', $currentUser['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Lernstreak berechnen
        $stmt = $pdo->prepare("
            SELECT DATE(last_seen) as date
            FROM learning_progress 
            WHERE user_id = :userId 
            AND last_seen >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(last_seen)
            ORDER BY date DESC
        ");
        $stmt->bindParam(':userId', $currentUser['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $learningDays = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Aktuelle Streak berechnen
        $currentStreak = 0;
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        if (!empty($learningDays)) {
            if ($learningDays[0] === $today || $learningDays[0] === $yesterday) {
                $currentDate = $learningDays[0] === $today ? $today : $yesterday;
                
                foreach ($learningDays as $day) {
                    if ($day === $currentDate) {
                        $currentStreak++;
                        $currentDate = date('Y-m-d', strtotime($currentDate . ' -1 day'));
                    } else {
                        break;
                    }
                }
            }
        }
        
        // Gesamtantworten berechnen
        $totalAnswers = ($stats['total_correct'] ?? 0) + ($stats['total_wrong'] ?? 0);
        $accuracyPercentage = $totalAnswers > 0 ? round(($stats['total_correct'] / $totalAnswers) * 100, 1) : 0;
        
        echo json_encode([
            'success' => true,
            'profile' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'is_teacher' => (bool)$user['is_teacher'],
                'created_at' => $user['created_at'],
                'member_since' => date('d.m.Y', strtotime($user['created_at']))
            ],
            'statistics' => [
                'lists_studied' => (int)($stats['lists_studied'] ?? 0),
                'vocab_studied' => (int)($stats['vocab_studied'] ?? 0),
                'total_answers' => $totalAnswers,
                'correct_answers' => (int)($stats['total_correct'] ?? 0),
                'wrong_answers' => (int)($stats['total_wrong'] ?? 0),
                'accuracy_percentage' => $accuracyPercentage,
                'current_streak' => $currentStreak,
                'last_activity' => $stats['last_activity']
            ],
            'achievements' => $achievements,
            'learning_days' => $learningDays
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Fehler beim Laden des Profils'
        ]);
    }
}

// POST/PUT: Profil aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    // JSON-Input lesen
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Fallback für Form-Data
    if (!$input) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? 'update_profile';
    
    switch ($action) {
        case 'update_profile':
            $newUsername = isset($input['username']) ? trim($input['username']) : '';
            $newEmail = isset($input['email']) ? trim($input['email']) : '';
            
            $errors = [];
            
            // Benutzername validieren
            if (empty($newUsername)) {
                $errors[] = 'Benutzername ist erforderlich';
            } elseif (strlen($newUsername) < 3) {
                $errors[] = 'Benutzername muss mindestens 3 Zeichen lang sein';
            } elseif (strlen($newUsername) > 50) {
                $errors[] = 'Benutzername darf maximal 50 Zeichen lang sein';
            } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $newUsername)) {
                $errors[] = 'Benutzername darf nur Buchstaben, Zahlen, Unterstriche und Bindestriche enthalten';
            }
            
            // E-Mail validieren
            if (!empty($newEmail) && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Ungültige E-Mail-Adresse';
            }
            
            // Eindeutigkeit prüfen
            if (empty($errors)) {
                // Benutzername-Eindeutigkeit
                $stmt = $pdo->prepare("
                    SELECT id FROM users 
                    WHERE username = :username AND id != :currentId
                ");
                $stmt->bindParam(':username', $newUsername);
                $stmt->bindParam(':currentId', $currentUser['id'], PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $errors[] = 'Dieser Benutzername ist bereits vergeben';
                }
                
                // E-Mail-Eindeutigkeit
                if (!empty($newEmail)) {
                    $stmt = $pdo->prepare("
                        SELECT id FROM users 
                        WHERE email = :email AND id != :currentId
                    ");
                    $stmt->bindParam(':email', $newEmail);
                    $stmt->bindParam(':currentId', $currentUser['id'], PDO::PARAM_INT);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        $errors[] = 'Diese E-Mail-Adresse ist bereits vergeben';
                    }
                }
            }
            
            if (!empty($errors)) {
                echo json_encode([
                    'success' => false,
                    'errors' => $errors
                ]);
                break;
            }
            
            try {
                // Profil aktualisieren
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET username = :username, email = :email
                    WHERE id = :id
                ");
                $stmt->bindParam(':username', $newUsername);
                $stmt->bindParam(':email', $newEmail);
                $stmt->bindParam(':id', $currentUser['id'], PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    // Session aktualisieren
                    $_SESSION['user']['username'] = $newUsername;
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Profil erfolgreich aktualisiert',
                        'user' => [
                            'id' => $currentUser['id'],
                            'username' => $newUsername,
                            'email' => $newEmail,
                            'is_teacher' => $currentUser['is_teacher']
                        ]
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Fehler beim Aktualisieren des Profils'
                    ]);
                }
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Datenbankfehler beim Aktualisieren des Profils'
                ]);
            }
            break;
            
        case 'change_password':
            $currentPassword = $input['current_password'] ?? '';
            $newPassword = $input['new_password'] ?? '';
            $confirmPassword = $input['confirm_password'] ?? '';
            
            $errors = [];
            
            if (empty($currentPassword)) {
                $errors[] = 'Aktuelles Passwort ist erforderlich';
            }
            
            if (empty($newPassword)) {
                $errors[] = 'Neues Passwort ist erforderlich';
            } elseif (strlen($newPassword) < 6) {
                $errors[] = 'Neues Passwort muss mindestens 6 Zeichen lang sein';
            }
            
            if ($newPassword !== $confirmPassword) {
                $errors[] = 'Neue Passwörter stimmen nicht überein';
            }
            
            if (!empty($errors)) {
                echo json_encode([
                    'success' => false,
                    'errors' => $errors
                ]);
                break;
            }
            
            try {
                // Aktuelles Passwort prüfen
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id");
                $stmt->bindParam(':id', $currentUser['id'], PDO::PARAM_INT);
                $stmt->execute();
                
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$userData || !password_verify($currentPassword, $userData['password'])) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Aktuelles Passwort ist falsch'
                    ]);
                    break;
                }
                
                // Neues Passwort hashen und speichern
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET password = :password 
                    WHERE id = :id
                ");
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->bindParam(':id', $currentUser['id'], PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Passwort erfolgreich geändert'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Fehler beim Ändern des Passworts'
                    ]);
                }
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Datenbankfehler beim Ändern des Passworts'
                ]);
            }
            break;
            
        case 'delete_account':
            $confirmPassword = $input['confirm_password'] ?? '';
            $confirmText = $input['confirm_text'] ?? '';
            
            if (empty($confirmPassword)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Passwort zur Bestätigung erforderlich'
                ]);
                break;
            }
            
            if ($confirmText !== 'ACCOUNT LÖSCHEN') {
                echo json_encode([
                    'success' => false,
                    'error' => 'Bestätigungstext ist falsch'
                ]);
                break;
            }
            
            try {
                // Passwort prüfen
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id");
                $stmt->bindParam(':id', $currentUser['id'], PDO::PARAM_INT);
                $stmt->execute();
                
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$userData || !password_verify($confirmPassword, $userData['password'])) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Passwort ist falsch'
                    ]);
                    break;
                }
                
                // Account löschen (CASCADE löscht automatisch abhängige Datensätze)
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                $stmt->bindParam(':id', $currentUser['id'], PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    // Session zerstören
                    session_destroy();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Account wurde erfolgreich gelöscht'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Fehler beim Löschen des Accounts'
                    ]);
                }
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Datenbankfehler beim Löschen des Accounts'
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Unbekannte Aktion'
            ]);
    }
}

// Andere HTTP-Methoden nicht unterstützt
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST', 'PUT'])) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'HTTP-Methode nicht unterstützt'
    ]);
}
?>