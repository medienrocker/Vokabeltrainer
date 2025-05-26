<?php
/**
 * Benutzer-Dashboard
 * Datei: dashboard.php
 */

require_once 'includes/config.php';
require_once 'includes/debug.php';
require_once 'includes/db_connect.php';

// Session prüfen
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php?redirect=dashboard&error=required');
    exit;
}

$user = $_SESSION['user'];
$pdo = connectDB();

// Benutzerstatistiken laden
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT vi.list_id) as lists_studied,
        SUM(lp.correct_count) as total_correct,
        SUM(lp.wrong_count) as total_wrong,
        MAX(lp.last_seen) as last_activity
    FROM learning_progress lp
    JOIN vocabulary_items vi ON lp.vocabulary_id = vi.id
    WHERE lp.user_id = :userId
");
$stmt->bindParam(':userId', $user['id'], PDO::PARAM_INT);
$stmt->execute();
$userStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Letzte Listen
$stmt = $pdo->prepare("
    SELECT DISTINCT vl.id, vl.name, MAX(lp.last_seen) as last_studied
    FROM vocabulary_lists vl
    JOIN vocabulary_items vi ON vl.id = vi.list_id
    JOIN learning_progress lp ON vi.id = lp.vocabulary_id
    WHERE lp.user_id = :userId
    GROUP BY vl.id
    ORDER BY last_studied DESC
    LIMIT 5
");
$stmt->bindParam(':userId', $user['id'], PDO::PARAM_INT);
$stmt->execute();
$recentLists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verfügbare Listen
$stmt = $pdo->prepare("
    SELECT vl.*, 
        (SELECT COUNT(*) FROM vocabulary_items WHERE list_id = vl.id) as word_count,
        (SELECT COUNT(*) FROM learning_progress lp 
         JOIN vocabulary_items vi ON lp.vocabulary_id = vi.id 
         WHERE vi.list_id = vl.id AND lp.user_id = :userId) as studied_count
    FROM vocabulary_lists vl
    WHERE vl.is_public = 1 OR vl.created_by = :userId
    ORDER BY vl.name ASC
");
$stmt->bindParam(':userId', $user['id'], PDO::PARAM_INT);
$stmt->execute();
$availableLists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiken berechnen
$totalAnswers = ($userStats['total_correct'] ?? 0) + ($userStats['total_wrong'] ?? 0);
$accuracy = $totalAnswers > 0 ? round(($userStats['total_correct'] / $totalAnswers) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - VocaBlitz</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.3s;
        }
        .list-item:hover {
            background-color: #f9fafb;
            border-color: var(--primary);
        }
        .list-meta {
            font-size: 0.875rem;
            color: #666;
        }
        .progress-ring {
            width: 60px;
            height: 60px;
        }
        .progress-ring circle {
            fill: none;
            stroke-width: 4;
            stroke-linecap: round;
        }
        .progress-bg {
            stroke: #e5e7eb;
        }
        .progress-bar {
            stroke: var(--primary);
            stroke-dasharray: 188;
            stroke-dashoffset: 188;
            transition: stroke-dashoffset 0.5s;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navigation">
        <div class="container nav-container">
            <div class="nav-brand">
                <i class="fas fa-language"></i> VocaBlitz
            </div>
            
            <div class="nav-menu">
                <a href="index.php" class="nav-link">Trainer</a>
                <a href="dashboard.php" class="nav-link active">Dashboard</a>
                <?php if ($user['is_teacher']): ?>
                    <a href="admin/" class="nav-link">Admin</a>
                <?php endif; ?>
            </div>
            
            <div class="user-menu">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <span><?php echo htmlspecialchars($user['username']); ?></span>
                <a href="logout.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Abmelden
                </a>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <header>
        <div class="container">
            <h1>Willkommen zurück, <?php echo htmlspecialchars($user['username']); ?>!</h1>
            <div class="subtitle">Bereit für eine neue Lernrunde?</div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container">
        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stat-value"><?php echo $userStats['lists_studied'] ?? 0; ?></div>
                <div class="stat-label">Listen gelernt</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check"></i>
                </div>
                <div class="stat-value"><?php echo $userStats['total_correct'] ?? 0; ?></div>
                <div class="stat-label">Richtige Antworten</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-value"><?php echo $accuracy; ?>%</div>
                <div class="stat-label">Genauigkeit</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value">
                    <?php 
                        echo $userStats['last_activity'] 
                            ? date('d.m.Y', strtotime($userStats['last_activity']))
                            : 'Noch nie';
                    ?>
                </div>
                <div class="stat-label">Letzte Aktivität</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Recent Lists -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Zuletzt gelernt</div>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentLists)): ?>
                        <?php foreach ($recentLists as $list): ?>
                            <div class="list-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($list['name']); ?></strong>
                                    <div class="list-meta">
                                        Zuletzt: <?php echo date('d.m.Y H:i', strtotime($list['last_studied'])); ?>
                                    </div>
                                </div>
                                <a href="index.php?list=<?php echo $list['id']; ?>" class="btn btn-primary btn-sm">
                                    Weiterlernen
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Noch keine Listen gelernt. Zeit anzufangen!</p>
                        <a href="index.php" class="btn btn-primary">Erste Liste wählen</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Available Lists -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Verfügbare Listen</div>
                </div>
                <div class="card-body">
                    <?php foreach ($availableLists as $list): ?>
                        <div class="list-item">
                            <div>
                                <strong><?php echo htmlspecialchars($list['name']); ?></strong>
                                <div class="list-meta">
                                    <?php echo $list['word_count']; ?> Vokabeln
                                    <?php if ($list['studied_count'] > 0): ?>
                                        · <?php echo $list['studied_count']; ?> gelernt
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <?php if ($list['studied_count'] > 0): ?>
                                    <svg class="progress-ring" viewBox="0 0 68 68">
                                        <circle class="progress-bg" cx="34" cy="34" r="30"/>
                                        <circle class="progress-bar" cx="34" cy="34" r="30" 
                                                style="stroke-dashoffset: <?php echo 188 - (188 * $list['studied_count'] / $list['word_count']); ?>"/>
                                    </svg>
                                <?php endif; ?>
                                <a href="index.php?list=<?php echo $list['id']; ?>" class="btn btn-primary btn-sm">
                                    <?php echo $list['studied_count'] > 0 ? 'Fortsetzen' : 'Starten'; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Schnellzugriff</div>
            </div>
            <div class="card-body">
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-play"></i> Lernen starten
                    </a>
                    <?php if ($user['is_teacher']): ?>
                        <a href="admin/" class="btn btn-secondary">
                            <i class="fas fa-cog"></i> Administration
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-ghost">
                        <i class="fas fa-sign-out-alt"></i> Abmelden
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-links">
                    <a href="https://bildungssprit.de" target="_blank" class="footer-link">bildungssprit.de</a>
                    <a href="#" class="footer-link">Datenschutz</a>
                    <a href="#" class="footer-link">Impressum</a>
                </div>
                <div class="footer-copyright">
                    <p>&copy; <?php echo date('Y'); ?> VocaBlitz - Französisch Vokabeltrainer 
                    <a href="https://bildungssprit.de" target="_blank" rel="noopener noreferrer">@medienrocker</a></p>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>