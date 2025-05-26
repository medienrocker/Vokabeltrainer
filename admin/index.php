<?php
/**
 * Admin-Dashboard
 * Datei: admin/index.php
 */

// Session starten und Zugriffsrechte prüfen
session_start();
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_teacher']) {
    header('Location: ../login.php?redirect=admin&error=unauthorized');
    exit;
}

$user = $_SESSION['user'];
require_once '../includes/db_connect.php';
$pdo = connectDB();

// Statistiken abrufen
// Gesamtzahl der Vokabellisten
$stmt = $pdo->query("SELECT COUNT(*) as list_count FROM vocabulary_lists");
$listCount = $stmt->fetch(PDO::FETCH_ASSOC)['list_count'];

// Gesamtzahl der Vokabeln
$stmt = $pdo->query("SELECT COUNT(*) as vocab_count FROM vocabulary_items");
$vocabCount = $stmt->fetch(PDO::FETCH_ASSOC)['vocab_count'];

// Gesamtzahl der Benutzer
$stmt = $pdo->query("SELECT COUNT(*) as user_count FROM users");
$userCount = $stmt->fetch(PDO::FETCH_ASSOC)['user_count'];

// Anzahl der Lehrer
$stmt = $pdo->query("SELECT COUNT(*) as teacher_count FROM users WHERE is_teacher = 1");
$teacherCount = $stmt->fetch(PDO::FETCH_ASSOC)['teacher_count'];

// Lernstatistiken
$stmt = $pdo->query("SELECT 
                        SUM(correct_count) as total_correct,
                        SUM(wrong_count) as total_wrong
                     FROM learning_progress");
$learningStats = $stmt->fetch(PDO::FETCH_ASSOC);
$totalAnswers = ($learningStats['total_correct'] ?? 0) + ($learningStats['total_wrong'] ?? 0);
$correctPercentage = $totalAnswers > 0 ? round(($learningStats['total_correct'] / $totalAnswers) * 100, 1) : 0;

// Kürzlich hinzugefügte Listen
$stmt = $pdo->prepare("
    SELECT vl.id, vl.name, vl.created_at, u.username,
        (SELECT COUNT(*) FROM vocabulary_items WHERE list_id = vl.id) as word_count 
    FROM vocabulary_lists vl
    LEFT JOIN users u ON vl.created_by = u.id
    ORDER BY vl.created_at DESC 
    LIMIT 5
");
$stmt->execute();
$recentLists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aktivste Benutzer
$stmt = $pdo->prepare("
    SELECT u.id, u.username, COUNT(lp.id) as activity
    FROM users u
    JOIN learning_progress lp ON u.id = lp.user_id
    GROUP BY u.id
    ORDER BY activity DESC
    LIMIT 5
");
$stmt->execute();
$activeUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Seite anzeigen
include '../includes/admin_header.php';
?>

<div class="admin-dashboard">
    <h1>Admin-Dashboard</h1>
    <p>Willkommen, <?php echo htmlspecialchars($user['username']); ?>!</p>

    <div class="stats-cards">
        <div class="stats-card">
            <div class="stats-icon"><i class="fas fa-list"></i></div>
            <div class="stats-value"><?php echo $listCount; ?></div>
            <div class="stats-label">Vokabellisten</div>
        </div>
        <div class="stats-card">
            <div class="stats-icon"><i class="fas fa-language"></i></div>
            <div class="stats-value"><?php echo $vocabCount; ?></div>
            <div class="stats-label">Vokabeln</div>
        </div>
        <div class="stats-card">
            <div class="stats-icon"><i class="fas fa-users"></i></div>
            <div class="stats-value"><?php echo $userCount; ?></div>
            <div class="stats-label">Benutzer</div>
            <div class="stats-sublabel">(davon <?php echo $teacherCount; ?> Lehrer)</div>
        </div>
        <div class="stats-card">
            <div class="stats-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stats-value"><?php echo $correctPercentage; ?>%</div>
            <div class="stats-label">Richtige Antworten</div>
            <div class="stats-sublabel">(<?php echo number_format($totalAnswers); ?> insgesamt)</div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h2>Kürzlich hinzugefügte Listen</h2>
            <?php if (count($recentLists) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Vokabeln</th>
                            <th>Erstellt von</th>
                            <th>Datum</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLists as $list): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($list['name']); ?></td>
                                <td><?php echo $list['word_count']; ?></td>
                                <td><?php echo htmlspecialchars($list['username'] ?? 'Unbekannt'); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($list['created_at'])); ?></td>
                                <td>
                                    <a href="list_manager.php?action=view&id=<?php echo $list['id']; ?>" class="btn-icon" title="Anzeigen">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="list_manager.php?action=edit&id=<?php echo $list['id']; ?>" class="btn-icon" title="Bearbeiten">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="card-action">
                    <a href="list_manager.php" class="btn-link">Alle Listen anzeigen</a>
                </div>
            <?php else: ?>
                <p class="no-data">Keine Listen vorhanden.</p>
            <?php endif; ?>
        </div>

        <div class="dashboard-card">
            <h2>Aktivste Benutzer</h2>
            <?php if (count($activeUsers) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Benutzername</th>
                            <th>Aktivität</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeUsers as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo $user['activity']; ?> Antworten</td>
                                <td>
                                    <a href="user_manager.php?action=view&id=<?php echo $user['id']; ?>" class="btn-icon" title="Anzeigen">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="card-action">
                    <a href="user_manager.php" class="btn-link">Alle Benutzer anzeigen</a>
                </div>
            <?php else: ?>
                <p class="no-data">Keine Benutzeraktivität vorhanden.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-actions">
        <a href="list_manager.php?action=new" class="btn-primary">
            <i class="fas fa-plus"></i> Neue Vokabelliste
        </a>
        <a href="user_manager.php?action=new" class="btn-primary">
            <i class="fas fa-user-plus"></i> Neuen Benutzer anlegen
        </a>
        <a href="../components/csv_uploader.php" class="btn-primary" id="openUploaderBtn">
            <i class="fas fa-upload"></i> CSV-Datei hochladen
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // CSV-Uploader in einem Modal öffnen
    const openUploaderBtn = document.getElementById('openUploaderBtn');
    if (openUploaderBtn) {
        openUploaderBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Modal erstellen
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <span class="modal-close">&times;</span>
                    <div class="modal-body">
                        <div class="loading">Lade Uploader...</div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Modal anzeigen
            modal.style.display = 'block';
            
            // Schließen-Button
            const closeBtn = modal.querySelector('.modal-close');
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
                modal.remove();
            });
            
            // Klick außerhalb schließt Modal
            window.addEventListener('click', function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                    modal.remove();
                }
            });
            
            // Uploader-Komponente laden
            fetch(openUploaderBtn.href)
                .then(response => response.text())
                .then(html => {
                    modal.querySelector('.modal-body').innerHTML = html;
                })
                .catch(error => {
                    modal.querySelector('.modal-body').innerHTML = `
                        <div class="error-message">
                            Fehler beim Laden des Uploaders: ${error.message}
                        </div>
                    `;
                });
        });
    }
});
</script>

<?php include '../includes/admin_footer.php'; ?>