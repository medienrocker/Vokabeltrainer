<?php
/**
 * Admin-Header
 * Datei: includes/admin_header.php
 */

// Sicherstellen, dass der Benutzer angemeldet ist
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_teacher']) {
    header('Location: ../login.php?redirect=admin');
    exit;
}

// Aktuelle Seite ermitteln
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VocaBlitz Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-sidebar">
            <div class="admin-logo">
                <a href="index.php">
                    <i class="fas fa-language"></i>
                    <span>VocaBlitz</span>
                </a>
            </div>
            
            <nav class="admin-nav">
                <ul>
                    <li>
                        <a href="index.php" class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="list_manager.php" class="<?php echo $current_page === 'list_manager.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i> Vokabellisten
                        </a>
                    </li>
                    <li>
                        <a href="user_manager.php" class="<?php echo $current_page === 'user_manager.php' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i> Benutzer
                        </a>
                    </li>
                    <li>
                        <a href="stats.php" class="<?php echo $current_page === 'stats.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i> Statistiken
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="admin-sidebar-footer">
                <a href="../index.php" title="Zur Hauptseite">
                    <i class="fas fa-home"></i>
                </a>
                <a href="../logout.php" title="Abmelden">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
        
        <div class="admin-main">
            <div class="admin-topbar">
                <div class="admin-breadcrumb">
                    <a href="index.php">Admin</a>
                    <?php if ($current_page !== 'index.php'): ?>
                        <span class="separator">/</span>
                        <span>
                            <?php
                                $page_titles = [
                                    'list_manager.php' => 'Vokabellisten',
                                    'user_manager.php' => 'Benutzer',
                                    'stats.php' => 'Statistiken'
                                ];
                                echo $page_titles[$current_page] ?? ucfirst(str_replace('.php', '', $current_page));
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="admin-user">
                    <span class="admin-username"><?php echo htmlspecialchars($_SESSION['user']['username']); ?></span>
                    <span class="admin-avatar">
                        <i class="fas fa-user-circle"></i>
                    </span>
                </div>
            </div>