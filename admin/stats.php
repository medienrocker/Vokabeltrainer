<?php
/**
 * Statistiken
 * Datei: admin/stats.php
 */

// Session starten und Zugriffsrechte prüfen
session_start();
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_teacher']) {
    header('Location: ../login.php?redirect=admin&error=unauthorized');
    exit;
}

require_once '../includes/db_connect.php';
$pdo = connectDB();

// Timeframe festlegen
$timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : 'month';

$startDate = '';
$endDate = date('Y-m-d');

switch ($timeframe) {
    case 'week':
        $startDate = date('Y-m-d', strtotime('-1 week'));
        $chartTitle = 'Letzte Woche';
        break;
    case 'month':
        $startDate = date('Y-m-d', strtotime('-1 month'));
        $chartTitle = 'Letzter Monat';
        break;
    case 'year':
        $startDate = date('Y-m-d', strtotime('-1 year'));
        $chartTitle = 'Letztes Jahr';
        break;
    case 'all':
        $startDate = '2000-01-01'; // Weit in der Vergangenheit
        $chartTitle = 'Alle Zeiten';
        break;
    default:
        $startDate = date('Y-m-d', strtotime('-1 month'));
        $chartTitle = 'Letzter Monat';
}

// Allgemeine Statistiken
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_teacher = 1");
$totalTeachers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM vocabulary_lists");
$totalLists = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM vocabulary_items");
$totalVocabs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Lernstatistiken
$stmt = $pdo->prepare("
    SELECT 
        SUM(correct_count) as total_correct,
        SUM(wrong_count) as total_wrong
    FROM learning_progress
    WHERE last_seen >= :startDate
");
$stmt->bindParam(':startDate', $startDate);
$stmt->execute();

$learningStats = $stmt->fetch(PDO::FETCH_ASSOC);
$totalCorrect = $learningStats['total_correct'] ?? 0;
$totalWrong = $learningStats['total_wrong'] ?? 0;
$totalAnswers = $totalCorrect + $totalWrong;
$correctPercentage = $totalAnswers > 0 ? round(($totalCorrect / $totalAnswers) * 100, 1) : 0;

// Aktivität pro Tag
if ($timeframe == 'week' || $timeframe == 'month') {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(last_seen) as date,
            COUNT(*) as count,
            SUM(correct_count) as correct,
            SUM(wrong_count) as wrong
        FROM learning_progress
        WHERE last_seen >= :startDate
        GROUP BY DATE(last_seen)
        ORDER BY date
    ");
    $stmt->bindParam(':startDate', $startDate);
    $stmt->execute();

    $activityByDay = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Bei längeren Zeiträumen nach Monat gruppieren
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(last_seen, '%Y-%m') as month,
            COUNT(*) as count,
            SUM(correct_count) as correct,
            SUM(wrong_count) as wrong
        FROM learning_progress
        WHERE last_seen >= :startDate
        GROUP BY DATE_FORMAT(last_seen, '%Y-%m')
        ORDER BY month
    ");
    $stmt->bindParam(':startDate', $startDate);
    $stmt->execute();

    $activityByMonth = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Beliebteste Listen
$stmt = $pdo->prepare("
    SELECT 
        vl.id,
        vl.name,
        COUNT(DISTINCT lp.user_id) as user_count,
        SUM(lp.correct_count + lp.wrong_count) as total_answers
    FROM vocabulary_lists vl
    JOIN vocabulary_items vi ON vl.id = vi.list_id
    JOIN learning_progress lp ON vi.id = lp.vocabulary_id
    WHERE lp.last_seen >= :startDate
    GROUP BY vl.id
    ORDER BY user_count DESC, total_answers DESC
    LIMIT 10
");
$stmt->bindParam(':startDate', $startDate);
$stmt->execute();

$popularLists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aktivste Benutzer
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.username,
        SUM(lp.correct_count + lp.wrong_count) as total_answers,
        SUM(lp.correct_count) as correct_answers,
        COUNT(DISTINCT vi.list_id) as list_count
    FROM users u
    JOIN learning_progress lp ON u.id = lp.user_id
    JOIN vocabulary_items vi ON lp.vocabulary_id = vi.id
    WHERE lp.last_seen >= :startDate
    GROUP BY u.id
    ORDER BY total_answers DESC
    LIMIT 10
");
$stmt->bindParam(':startDate', $startDate);
$stmt->execute();

$activeUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Schwierigste Vokabeln
$stmt = $pdo->prepare("
    SELECT 
        vi.id,
        vi.term_from,
        vi.term_to,
        vl.name as list_name,
        COUNT(lp.id) as attempt_count,
        SUM(lp.wrong_count) as wrong_count,
        ROUND(SUM(lp.wrong_count) / (SUM(lp.correct_count) + SUM(lp.wrong_count)) * 100, 1) as error_rate
    FROM vocabulary_items vi
    JOIN vocabulary_lists vl ON vi.list_id = vl.id
    JOIN learning_progress lp ON vi.id = lp.vocabulary_id
    WHERE lp.last_seen >= :startDate
    GROUP BY vi.id
    HAVING attempt_count > 5
    ORDER BY error_rate DESC
    LIMIT 10
");
$stmt->bindParam(':startDate', $startDate);
$stmt->execute();

$difficultVocabs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Seite anzeigen
include '../includes/admin_header.php';
?>

<div class="admin-content">
    <div class="admin-header">
        <h1>Statistik & Analysen</h1>
        <div class="timeframe-selector">
            <a href="stats.php?timeframe=week" class="btn-filter <?php echo $timeframe == 'week' ? 'active' : ''; ?>">Woche</a>
            <a href="stats.php?timeframe=month" class="btn-filter <?php echo $timeframe == 'month' ? 'active' : ''; ?>">Monat</a>
            <a href="stats.php?timeframe=year" class="btn-filter <?php echo $timeframe == 'year' ? 'active' : ''; ?>">Jahr</a>
            <a href="stats.php?timeframe=all" class="btn-filter <?php echo $timeframe == 'all' ? 'active' : ''; ?>">Alle</a>
        </div>
    </div>
    
    <div class="stats-overview">
        <div class="stats-card">
            <div class="stats-icon"><i class="fas fa-users"></i></div>
            <div class="stats-value"><?php echo $totalUsers; ?></div>
            <div class="stats-label">Benutzer</div>
            <div class="stats-sublabel">(davon <?php echo $totalTeachers; ?> Lehrer)</div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon"><i class="fas fa-list"></i></div>
            <div class="stats-value"><?php echo $totalLists; ?></div>
            <div class="stats-label">Vokabellisten</div>
            <div class="stats-sublabel">(<?php echo $totalVocabs; ?> Vokabeln)</div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon"><i class="fas fa-tasks"></i></div>
            <div class="stats-value"><?php echo number_format($totalAnswers); ?></div>
            <div class="stats-label">Beantwortete Fragen</div>
            <div class="stats-sublabel">(im gewählten Zeitraum)</div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon"><i class="fas fa-chart-pie"></i></div>
            <div class="stats-value"><?php echo $correctPercentage; ?>%</div>
            <div class="stats-label">Richtige Antworten</div>
            <div class="stats-sublabel">(<?php echo number_format($totalCorrect); ?> von <?php echo number_format($totalAnswers); ?>)</div>
        </div>
    </div>
    
    <div class="stats-grid">
        <div class="stats-chart-container">
            <h2>Aktivität (<?php echo $chartTitle; ?>)</h2>
            <canvas id="activityChart"></canvas>
        </div>
        
        <div class="stats-chart-container">
            <h2>Antworten (<?php echo $chartTitle; ?>)</h2>
            <canvas id="answersChart"></canvas>
        </div>
    </div>
    
    <div class="stats-tables">
        <div class="stats-table-container">
            <h2>Beliebteste Vokabellisten</h2>
            <?php if (count($popularLists) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Liste</th>
                            <th>Benutzer</th>
                            <th>Antworten</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($popularLists as $list): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($list['name']); ?></td>
                                <td><?php echo $list['user_count']; ?></td>
                                <td><?php echo number_format($list['total_answers']); ?></td>
                                <td>
                                    <a href="list_manager.php?action=view&id=<?php echo $list['id']; ?>" class="btn-icon">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">Keine Daten verfügbar.</div>
            <?php endif; ?>
        </div>
        
        <div class="stats-table-container">
            <h2>Aktivste Benutzer</h2>
            <?php if (count($activeUsers) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Benutzer</th>
                            <th>Antworten</th>
                            <th>Richtig</th>
                            <th>Listen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeUsers as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo number_format($user['total_answers']); ?></td>
                                <td>
                                    <?php 
                                        $correctPercent = $user['total_answers'] > 0 
                                            ? round(($user['correct_answers'] / $user['total_answers']) * 100, 1) 
                                            : 0;
                                        echo $correctPercent . '%';
                                    ?>
                                </td>
                                <td><?php echo $user['list_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">Keine Daten verfügbar.</div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="stats-tables">
        <div class="stats-table-container full-width">
            <h2>Schwierigste Vokabeln</h2>
            <?php if (count($difficultVocabs) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Deutsch</th>
                            <th>Französisch</th>
                            <th>Liste</th>
                            <th>Versuche</th>
                            <th>Falsch</th>
                            <th>Fehlerrate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($difficultVocabs as $vocab): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($vocab['term_from']); ?></td>
                                <td><?php echo htmlspecialchars($vocab['term_to']); ?></td>
                                <td><?php echo htmlspecialchars($vocab['list_name']); ?></td>
                                <td><?php echo $vocab['attempt_count']; ?></td>
                                <td><?php echo $vocab['wrong_count']; ?></td>
                                <td>
                                    <div class="error-rate-bar">
                                        <div class="error-rate-progress" style="width: <?php echo $vocab['error_rate']; ?>%"></div>
                                        <span class="error-rate-text"><?php echo $vocab['error_rate']; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">Keine Daten verfügbar.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Activity Chart
    const activityCtx = document.getElementById('activityChart').getContext('2d');
    
    let activityLabels = [];
    let activityDataValues = [];
    
    <?php if ($timeframe == 'week' || $timeframe == 'month'): ?>
        // Tägliche Aktivität
        activityLabels = <?php 
            $labels = [];
            foreach ($activityByDay as $day) {
                $date = new DateTime($day['date']);
                $labels[] = $date->format('d.m.');
            }
            echo json_encode($labels);
        ?>;
        
        activityDataValues = <?php 
            $data = [];
            foreach ($activityByDay as $day) {
                $data[] = $day['count'];
            }
            echo json_encode($data);
        ?>;
    <?php else: ?>
        // Monatliche Aktivität
        activityLabels = <?php 
            $labels = [];
            foreach ($activityByMonth as $month) {
                $date = DateTime::createFromFormat('Y-m', $month['month']);
                $labels[] = $date->format('M Y');
            }
            echo json_encode($labels);
        ?>;
        
        activityDataValues = <?php 
            $data = [];
            foreach ($activityByMonth as $month) {
                $data[] = $month['count'];
            }
            echo json_encode($data);
        ?>;
    <?php endif; ?>
    
    const activityData = {
        labels: activityLabels,
        datasets: [{
            label: 'Aktivität',
            data: activityDataValues,
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 2,
            tension: 0.3
        }]
    };
    
    const activityChart = new Chart(activityCtx, {
        type: 'line',
        data: activityData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Answers Chart
    const answersCtx = document.getElementById('answersChart').getContext('2d');
    
    let answersLabels = [];
    let correctDataValues = [];
    let wrongDataValues = [];
    
    <?php if ($timeframe == 'week' || $timeframe == 'month'): ?>
        // Tägliche Antworten
        answersLabels = <?php 
            $labels = [];
            foreach ($activityByDay as $day) {
                $date = new DateTime($day['date']);
                $labels[] = $date->format('d.m.');
            }
            echo json_encode($labels);
        ?>;
        
        correctDataValues = <?php 
            $data = [];
            foreach ($activityByDay as $day) {
                $data[] = $day['correct'];
            }
            echo json_encode($data);
        ?>;
        
        wrongDataValues = <?php 
            $data = [];
            foreach ($activityByDay as $day) {
                $data[] = $day['wrong'];
            }
            echo json_encode($data);
        ?>;
    <?php else: ?>
        // Monatliche Antworten
        answersLabels = <?php 
            $labels = [];
            foreach ($activityByMonth as $month) {
                $date = DateTime::createFromFormat('Y-m', $month['month']);
                $labels[] = $date->format('M Y');
            }
            echo json_encode($labels);
        ?>;
        
        correctDataValues = <?php 
            $data = [];
            foreach ($activityByMonth as $month) {
                $data[] = $month['correct'];
            }
            echo json_encode($data);
        ?>;
        
        wrongDataValues = <?php 
            $data = [];
            foreach ($activityByMonth as $month) {
                $data[] = $month['wrong'];
            }
            echo json_encode($data);
        ?>;
    <?php endif; ?>
    
    const answersData = {
        labels: answersLabels,
        datasets: [
            {
                label: 'Richtig',
                data: correctDataValues,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2
            },
            {
                label: 'Falsch',
                data: wrongDataValues,
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 2
            }
        ]
    };
    
    const answersChart = new Chart(answersCtx, {
        type: 'bar',
        data: answersData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
});
</script>

<?php include '../includes/admin_footer.php'; ?>