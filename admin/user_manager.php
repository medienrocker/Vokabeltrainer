<?php
/**
 * Benutzerverwaltung
 * Datei: admin/user_manager.php
 */

// Session starten und Zugriffsrechte prüfen
session_start();
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_teacher']) {
    header('Location: ../login.php?redirect=admin&error=unauthorized');
    exit;
}

require_once '../includes/db_connect.php';
require_once '../includes/users.php';
$pdo = connectDB();

// Parameter abrufen
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Formular-Daten verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Benutzer erstellen/bearbeiten
    if (isset($_POST['save_user'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $isTeacher = isset($_POST['is_teacher']) ? 1 : 0;
        $password = $_POST['password'] ?? '';
        
        // Validierung
        $error = null;
        
        if (empty($username)) {
            $error = 'Benutzername darf nicht leer sein.';
        }
        
        // Auf Eindeutigkeit prüfen
        if (!$error) {
            $stmt = $pdo->prepare("
                SELECT id FROM users 
                WHERE username = :username AND id != :userId
            ");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error = 'Dieser Benutzername ist bereits vergeben.';
            }
        }
        
        if (!$error && !empty($email)) {
            $stmt = $pdo->prepare("
                SELECT id FROM users 
                WHERE email = :email AND id != :userId
            ");
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error = 'Diese E-Mail-Adresse ist bereits vergeben.';
            }
        }
        
        // Benutzer aktualisieren oder erstellen
        if (!$error) {
            // Bestehenden Benutzer aktualisieren
            if ($userId > 0) {
                $query = "
                    UPDATE users 
                    SET username = :username, 
                        email = :email, 
                        is_teacher = :isTeacher";
                
                // Passwort nur aktualisieren, wenn eines eingegeben wurde
                if (!empty($password)) {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $query .= ", password = :password";
                }
                
                $query .= " WHERE id = :userId";
                
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':isTeacher', $isTeacher, PDO::PARAM_INT);
                
                if (!empty($password)) {
                    $stmt->bindParam(':password', $passwordHash);
                }
                
                $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $success = 'Benutzer wurde erfolgreich aktualisiert.';
                } else {
                    $error = 'Fehler beim Aktualisieren des Benutzers.';
                }
            }
            // Neuen Benutzer erstellen
            else {
                if (empty($password)) {
                    $error = 'Für einen neuen Benutzer muss ein Passwort festgelegt werden.';
                } else {
                    $result = registerUser($username, $password, $email, $isTeacher);
                    
                    if ($result['success']) {
                        $userId = $result['userId'];
                        header("Location: user_manager.php?action=edit&id=$userId&success=created");
                        exit;
                    } else {
                        $error = $result['error'];
                    }
                }
            }
        }
    }
    
    // Benutzer löschen
    else if (isset($_POST['delete_user']) && $userId > 0) {
        // Prüfen, ob es der letzte Admin/Lehrer ist
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_teacher = 1");
        $teacherCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $stmt = $pdo->prepare("SELECT is_teacher FROM users WHERE id = :userId");
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $userIsTeacher = $stmt->fetch(PDO::FETCH_ASSOC)['is_teacher'];
        
        if ($userIsTeacher && $teacherCount <= 1) {
            $error = 'Der letzte Lehrer kann nicht gelöscht werden.';
        } else {
            // Lernfortschritt löschen
            $stmt = $pdo->prepare("DELETE FROM learning_progress WHERE user_id = :userId");
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Leaderboard-Einträge löschen
            $stmt = $pdo->prepare("DELETE FROM leaderboard_entries WHERE user_id = :userId");
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Benutzer löschen
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :userId");
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                header('Location: user_manager.php?success=deleted');
                exit;
            } else {
                $error = 'Fehler beim Löschen des Benutzers.';
            }
        }
    }
    
    // Benutzerstatistik zurücksetzen
    else if (isset($_POST['reset_stats']) && $userId > 0) {
        // Lernfortschritt zurücksetzen
        $stmt = $pdo->prepare("DELETE FROM learning_progress WHERE user_id = :userId");
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            // Leaderboard-Einträge zurücksetzen
            $stmt = $pdo->prepare("DELETE FROM leaderboard_entries WHERE user_id = :userId");
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $success = 'Statistik des Benutzers wurde zurückgesetzt.';
        } else {
            $error = 'Fehler beim Zurücksetzen der Statistik.';
        }
    }
}

// Benutzer-Details laden
$user = null;
$userStats = null;

if ($userId > 0 && ($action === 'view' || $action === 'edit')) {
    // Benutzer laden
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Location: user_manager.php?error=not_found');
        exit;
    }
    
    // Benutzerstatistik laden
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT vi.list_id) as list_count,
            SUM(lp.correct_count) as correct_count,
            SUM(lp.wrong_count) as wrong_count,
            MAX(lp.last_seen) as last_activity
        FROM learning_progress lp
        JOIN vocabulary_items vi ON lp.vocabulary_id = vi.id
        WHERE lp.user_id = :userId
    ");
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Beste Leaderboard-Ergebnisse
    $stmt = $pdo->prepare("
        SELECT 
            le.*, 
            vl.name as list_name
        FROM leaderboard_entries le
        JOIN vocabulary_lists vl ON le.list_id = vl.id
        WHERE le.user_id = :userId
        ORDER BY le.score DESC
        LIMIT 5
    ");
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $userLeaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Alle Benutzer anzeigen
$allUsers = [];
if ($action === 'list') {
    $stmt = $pdo->query("
        SELECT u.*, 
            (SELECT COUNT(*) FROM learning_progress WHERE user_id = u.id) as activity_count,
            (SELECT MAX(last_seen) FROM learning_progress WHERE user_id = u.id) as last_activity
        FROM users u
        ORDER BY u.username ASC
    ");
    
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include '../includes/admin_header.php';
?>

<div class="admin-content">
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
        <div class="alert alert-success">
            Der Benutzer wurde erfolgreich gelöscht.
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success']) && $_GET['success'] === 'created'): ?>
        <div class="alert alert-success">
            Der Benutzer wurde erfolgreich erstellt.
        </div>
    <?php endif; ?>
    
    <!-- Benutzerübersicht -->
    <?php if ($action === 'list'): ?>
        <div class="admin-header">
            <h1>Benutzer</h1>
            <div class="admin-actions">
                <a href="user_manager.php?action=new" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Neuer Benutzer
                </a>
            </div>
        </div>
        
        <?php if (count($allUsers) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Benutzername</th>
                        <th>E-Mail</th>
                        <th>Rolle</th>
                        <th>Aktivität</th>
                        <th>Letzte Aktivität</th>
                        <th>Erstellt am</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allUsers as $u): ?>
                        <tr>
                            <td><?php echo $u['id']; ?></td>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td><?php echo htmlspecialchars($u['email'] ?? '-'); ?></td>
                            <td>
                                <?php if ($u['is_teacher']): ?>
                                    <span class="badge badge-primary">Lehrer</span>
                                <?php else: ?>
                                    <span class="badge">Schüler</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $u['activity_count'] ?? 0; ?></td>
                            <td>
                                <?php 
                                    echo $u['last_activity'] 
                                        ? date('d.m.Y H:i', strtotime($u['last_activity'])) 
                                        : '-';
                                ?>
                            </td>
                            <td><?php echo date('d.m.Y', strtotime($u['created_at'])); ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="user_manager.php?action=view&id=<?php echo $u['id']; ?>" class="btn-icon" title="Anzeigen">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="user_manager.php?action=edit&id=<?php echo $u['id']; ?>" class="btn-icon" title="Bearbeiten">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn-icon delete-user-btn" 
                                            data-id="<?php echo $u['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($u['username']); ?>"
                                            title="Löschen">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">
                <p>Keine Benutzer vorhanden.</p>
                <a href="user_manager.php?action=new" class="btn-primary">Ersten Benutzer erstellen</a>
            </div>
        <?php endif; ?>
        
        <!-- Lösch-Bestätigungsdialog -->
        <div id="deleteConfirmModal" class="modal">
            <div class="modal-content">
                <h2>Benutzer löschen</h2>
                <p>Möchten Sie den Benutzer "<span id="deleteUserName"></span>" wirklich löschen?</p>
                <p class="warning">Diese Aktion kann nicht rückgängig gemacht werden!</p>
                
                <form method="post" id="deleteUserForm">
                    <input type="hidden" name="delete_user" value="1">
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" id="cancelDeleteBtn">Abbrechen</button>
                        <button type="submit" class="btn-danger">Löschen</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Neuen Benutzer erstellen -->
    <?php if ($action === 'new'): ?>
        <div class="admin-header">
            <h1>Neuen Benutzer erstellen</h1>
            <div class="admin-actions">
                <a href="user_manager.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
                </a>
            </div>
        </div>
        
        <form method="post" class="admin-form">
            <div class="form-group">
                <label for="username">Benutzername *</label>
                <input type="text" id="username" name="username" required 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="email">E-Mail</label>
                <input type="email" id="email" name="email"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Passwort *</label>
                <input type="password" id="password" name="password" required>
                <div class="help-text">Mindestens 6 Zeichen empfohlen.</div>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_teacher" value="1" 
                           <?php echo (isset($_POST['is_teacher'])) ? 'checked' : ''; ?>>
                    Lehrer-Rechte (kann Listen erstellen und alle Benutzer verwalten)
                </label>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_user" class="btn-primary">Benutzer erstellen</button>
                <a href="user_manager.php" class="btn-secondary">Abbrechen</a>
            </div>
        </form>
    <?php endif; ?>
    
    <!-- Benutzer anzeigen -->
    <?php if ($action === 'view' && $user): ?>
        <div class="admin-header">
            <h1>Benutzer: <?php echo htmlspecialchars($user['username']); ?></h1>
            <div class="admin-actions">
                <a href="user_manager.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
                </a>
                <a href="user_manager.php?action=edit&id=<?php echo $user['id']; ?>" class="btn-primary">
                    <i class="fas fa-edit"></i> Bearbeiten
                </a>
            </div>
        </div>
        
        <div class="user-profile">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
                <div class="user-role">
                    <?php if ($user['is_teacher']): ?>
                        <span class="badge badge-primary">Lehrer</span>
                    <?php else: ?>
                        <span class="badge">Schüler</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="user-details">
                <div class="detail-row">
                    <div class="detail-label">Benutzername:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($user['username']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">E-Mail:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($user['email'] ?? '-'); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Erstellt am:</div>
                    <div class="detail-value"><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Letzte Aktivität:</div>
                    <div class="detail-value">
                        <?php 
                            echo isset($userStats['last_activity']) 
                                ? date('d.m.Y H:i', strtotime($userStats['last_activity'])) 
                                : 'Keine Aktivität';
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="user-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $userStats['list_count'] ?? 0; ?></div>
                    <div class="stat-label">Listen</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-value"><?php echo ($userStats['correct_count'] ?? 0) + ($userStats['wrong_count'] ?? 0); ?></div>
                    <div class="stat-label">Antworten</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-value">
                        <?php 
                            $totalAnswers = ($userStats['correct_count'] ?? 0) + ($userStats['wrong_count'] ?? 0);
                            echo $totalAnswers > 0 
                                ? round(($userStats['correct_count'] / $totalAnswers) * 100) . '%' 
                                : '-';
                        ?>
                    </div>
                    <div class="stat-label">Korrekt</div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($userLeaderboard)): ?>
            <div class="user-leaderboard">
                <h2>Beste Ergebnisse</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Liste</th>
                            <th>Punkte</th>
                            <th>Beste Serie</th>
                            <th>Zeit</th>
                            <th>Datum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userLeaderboard as $entry): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($entry['list_name']); ?></td>
                                <td><?php echo $entry['score']; ?></td>
                                <td><?php echo $entry['streak_best']; ?></td>
                                <td><?php echo formatTime($entry['time_spent']); ?></td>
                                <td><?php echo formatTime($entry['time_spent']); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($entry['completed_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <div class="user-actions">
            <form method="post" id="resetStatsForm">
                <input type="hidden" name="reset_stats" value="1">
                <button type="button" class="btn-warning" id="resetStatsBtn">
                    <i class="fas fa-redo"></i> Statistik zurücksetzen
                </button>
            </form>
        </div>
        
        <!-- Reset-Bestätigungsdialog -->
        <div id="resetConfirmModal" class="modal">
            <div class="modal-content">
                <h2>Statistik zurücksetzen</h2>
                <p>Möchten Sie die Statistik von "<?php echo htmlspecialchars($user['username']); ?>" wirklich zurücksetzen?</p>
                <p class="warning">Diese Aktion kann nicht rückgängig gemacht werden!</p>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" id="cancelResetBtn">Abbrechen</button>
                    <button type="button" class="btn-warning" id="confirmResetBtn">Zurücksetzen</button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Benutzer bearbeiten -->
    <?php if ($action === 'edit' && $user): ?>
        <div class="admin-header">
            <h1>Benutzer bearbeiten: <?php echo htmlspecialchars($user['username']); ?></h1>
            <div class="admin-actions">
                <a href="user_manager.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
                </a>
            </div>
        </div>
        
        <form method="post" class="admin-form">
            <div class="form-group">
                <label for="username">Benutzername *</label>
                <input type="text" id="username" name="username" required 
                       value="<?php echo htmlspecialchars($user['username']); ?>">
            </div>
            
            <div class="form-group">
                <label for="email">E-Mail</label>
                <input type="email" id="email" name="email"
                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password">
                <div class="help-text">Leer lassen, um das Passwort nicht zu ändern.</div>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_teacher" value="1" 
                           <?php echo $user['is_teacher'] ? 'checked' : ''; ?>>
                    Lehrer-Rechte (kann Listen erstellen und alle Benutzer verwalten)
                </label>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_user" class="btn-primary">Änderungen speichern</button>
                <button type="button" class="btn-danger" id="deleteUserBtn">Benutzer löschen</button>
            </div>
        </form>
        
        <!-- Lösch-Bestätigungsdialog -->
        <div id="deleteConfirmModal" class="modal">
            <div class="modal-content">
                <h2>Benutzer löschen</h2>
                <p>Möchten Sie den Benutzer "<?php echo htmlspecialchars($user['username']); ?>" wirklich löschen?</p>
                <p class="warning">Diese Aktion kann nicht rückgängig gemacht werden!</p>
                
                <form method="post">
                    <input type="hidden" name="delete_user" value="1">
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" id="cancelDeleteBtn">Abbrechen</button>
                        <button type="submit" class="btn-danger">Löschen</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Benutzerlöschung Funktionalität
    const deleteUserBtns = document.querySelectorAll('.delete-user-btn');
    const deleteUserBtn = document.getElementById('deleteUserBtn');
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    const deleteUserForm = document.getElementById('deleteUserForm');
    const deleteUserName = document.getElementById('deleteUserName');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    
    // Für die Benutzerübersicht
    if (deleteUserBtns.length > 0 && deleteConfirmModal) {
        deleteUserBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                deleteUserForm.action = `user_manager.php?action=edit&id=${userId}`;
                deleteUserName.textContent = name;
                deleteConfirmModal.style.display = 'block';
            });
        });
    }
    
    // Für die Bearbeiten-Seite
    if (deleteUserBtn && deleteConfirmModal) {
        deleteUserBtn.addEventListener('click', function() {
            deleteConfirmModal.style.display = 'block';
        });
    }
    
    // Schließen-Buttons
    if (cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener('click', function() {
            deleteConfirmModal.style.display = 'none';
        });
    }
    
    window.addEventListener('click', function(event) {
        if (event.target == deleteConfirmModal) {
            deleteConfirmModal.style.display = 'none';
        }
    });
    
    // Statistik zurücksetzen
    const resetStatsBtn = document.getElementById('resetStatsBtn');
    const resetConfirmModal = document.getElementById('resetConfirmModal');
    const resetStatsForm = document.getElementById('resetStatsForm');
    const confirmResetBtn = document.getElementById('confirmResetBtn');
    const cancelResetBtn = document.getElementById('cancelResetBtn');
    
    if (resetStatsBtn && resetConfirmModal) {
        resetStatsBtn.addEventListener('click', function() {
            resetConfirmModal.style.display = 'block';
        });
        
        confirmResetBtn.addEventListener('click', function() {
            resetStatsForm.submit();
        });
        
        cancelResetBtn.addEventListener('click', function() {
            resetConfirmModal.style.display = 'none';
        });
        
        window.addEventListener('click', function(event) {
            if (event.target == resetConfirmModal) {
                resetConfirmModal.style.display = 'none';
            }
        });
    }
});
</script>

<?php
// Hilfsfunktion zum Formatieren der Zeit
function formatTime($seconds) {
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    
    if ($minutes < 60) {
        return sprintf('%d:%02d', $minutes, $seconds);
    } else {
        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;
        return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
    }
}
?>

<?php include '../includes/admin_footer.php'; ?>