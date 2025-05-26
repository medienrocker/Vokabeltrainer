<?php
require_once 'includes/debug.php';
// Session für Benutzeranmeldung
session_start();

// Prüfen, ob Benutzer angemeldet ist
$isLoggedIn = isset($_SESSION['user']);
$currentUser = $isLoggedIn ? $_SESSION['user'] : null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VocaBlitz: Französisch Vokabeltrainer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navigation">
        <div class="container nav-container">
            <div class="nav-brand">
                <i class="fas fa-language"></i> VocaBlitz
            </div>
            
            <div class="nav-menu" id="navMenu">
                <a href="#" class="nav-link active" data-section="trainer">Trainer</a>
                <a href="#" class="nav-link" data-section="statistics">Statistiken</a>
                <a href="#" class="nav-link" data-section="lists">Listen</a>
                <?php if ($currentUser && $currentUser['is_teacher']): ?>
                    <a href="admin/" class="nav-link">Admin</a>
                <?php endif; ?>
            </div>
            
            <div class="nav-actions">
                <?php if ($isLoggedIn): ?>
                    <div class="user-menu">
                        <button class="user-menu-toggle" id="userMenuToggle">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?>
                            </div>
                            <span><?php echo htmlspecialchars($currentUser['username']); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="user-menu-dropdown">
                            <a href="#" class="user-menu-item" data-action="profile">
                                <i class="fas fa-user"></i> Profil
                            </a>
                            <a href="#" class="user-menu-item" data-action="settings">
                                <i class="fas fa-cog"></i> Einstellungen
                            </a>
                            <div class="user-menu-divider"></div>
                            <a href="logout.php" class="user-menu-item">
                                <i class="fas fa-sign-out-alt"></i> Abmelden
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <button class="btn btn-primary" id="loginBtn">
                        <i class="fas fa-sign-in-alt"></i> Anmelden
                    </button>
                <?php endif; ?>
            </div>
            
            <button class="nav-toggle" id="navToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Header -->
    <header>
        <div class="container">
            <h1>VocaBlitz</h1>
            <div class="subtitle">Französisch Vokabeltrainer</div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container">
        <!-- Loading State -->
        <div class="loading-container" id="loadingContainer">
            <div class="spinner"></div>
            <p>App wird geladen...</p>
        </div>

        <!-- Content Sections -->
        <div class="app-content hidden" id="appContent">
            
            <!-- Trainer Section -->
            <section class="app-section active" id="trainer-section">
                <div class="section-header">
                    <h2>Vokabeltrainer</h2>
                    <div class="section-actions">
                        <button class="btn btn-secondary" id="selectListBtn">
                            <i class="fas fa-list"></i> Liste wählen
                        </button>
                    </div>
                </div>

                <!-- Current List Info -->
                <div class="current-list-info hidden" id="currentListInfo">
                    <div class="card">
                        <div class="card-body">
                            <h3 id="currentListName">Keine Liste ausgewählt</h3>
                            <p id="currentListStats">0 Vokabeln</p>
                        </div>
                    </div>
                </div>

                <!-- Learning Modes -->
                <div class="learning-modes hidden" id="learningModes">
                    <div class="modes">
                        <button class="mode-btn active" data-mode="flashcard">
                            <i class="fas fa-copy"></i> Flashcards
                        </button>
                        <button class="mode-btn" data-mode="multiple-choice">
                            <i class="fas fa-tasks"></i> Multiple Choice
                        </button>
                        <button class="mode-btn" data-mode="writing">
                            <i class="fas fa-pen"></i> Schreibmodus
                        </button>
                    </div>

                    <div class="direction-toggle">
                        <button class="direction-btn active" data-direction="de-fr">Deutsch → Französisch</button>
                        <button class="direction-btn" data-direction="fr-de">Französisch → Deutsch</button>
                    </div>

                    <!-- Flashcard Mode -->
                    <div class="mode-container active" id="flashcard-mode">
                        <div class="flashcard-container">
                            <div class="flashcard" id="flashcard">
                                <div class="flashcard-front">
                                    <div class="word" id="flashcard-front-text">Bereit?</div>
                                    <div class="instruction">Klicke, um die Übersetzung zu sehen</div>
                                </div>
                                <div class="flashcard-back">
                                    <div class="word" id="flashcard-back-text">Los geht's!</div>
                                    <div class="instruction">Klicke auf die Karte, um sie umzudrehen</div>
                                </div>
                            </div>
                        </div>
                        <div class="progress-container">
                            <div class="progress-bar" id="flashcard-progress"></div>
                        </div>
                    </div>

                    <!-- Multiple Choice Mode -->
                    <div class="mode-container" id="multiple-choice-mode">
                        <div class="question-container">
                            <div class="question" id="mc-question">Bereit?</div>
                            <div class="language-hint" id="mc-hint">Wähle eine Antwort</div>
                        </div>
                        <div class="options" id="mc-options">
                            <div class="option">Option 1</div>
                            <div class="option">Option 2</div>
                            <div class="option">Option 3</div>
                            <div class="option">Option 4</div>
                        </div>
                        <div class="progress-container">
                            <div class="progress-bar" id="mc-progress"></div>
                        </div>
                    </div>

                    <!-- Writing Mode -->
                    <div class="mode-container" id="writing-mode">
                        <div class="writing-container">
                            <div class="question" id="writing-question">Bereit?</div>
                            <div class="language-hint" id="writing-hint">Schreibe deine Antwort</div>
                            <input type="text" class="writing-input" id="writing-input" placeholder="Deine Antwort...">
                            <button class="btn btn-primary" id="check-answer">Überprüfen</button>
                        </div>
                        <div class="progress-container">
                            <div class="progress-bar" id="writing-progress"></div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="stats-container">
                        <h3>Deine Statistik</h3>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="stat-value" id="correct-count">0</div>
                                <div class="stat-label">Richtig</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-fire"></i>
                                </div>
                                <div class="stat-value" id="current-streak">0</div>
                                <div class="stat-label">Aktuelle Serie</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-trophy"></i>
                                </div>
                                <div class="stat-value" id="best-streak">0</div>
                                <div class="stat-label">Beste Serie</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-value" id="time-spent">0:00</div>
                                <div class="stat-label">Lernzeit</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div class="empty-state" id="noListSelected">
                    <div class="empty-state-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="empty-state-title">Keine Vokabelliste ausgewählt</div>
                    <div class="empty-state-description">
                        Wähle eine Vokabelliste aus, um mit dem Lernen zu beginnen.
                    </div>
                    <button class="btn btn-primary" id="selectListBtnEmpty">
                        <i class="fas fa-plus"></i> Liste auswählen
                    </button>
                </div>
            </section>

            <!-- Statistics Section -->
            <section class="app-section" id="statistics-section">
                <h2>Statistiken</h2>
                <div id="statisticsContent">
                    <p>Statistiken werden geladen...</p>
                </div>
            </section>

            <!-- Lists Section -->
            <section class="app-section" id="lists-section">
                <h2>Vokabellisten</h2>
                <div id="listsContent">
                    <p>Listen werden geladen...</p>
                </div>
            </section>
        </div>
    </main>

    <!-- Modals -->
    <!-- Login Modal -->
    <div class="modal" id="loginModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Anmelden</h2>
                <button class="modal-close" id="loginModalClose">&times;</button>
            </div>
            <div class="modal-body">
                <form id="loginForm">
                    <div class="form-group">
                        <label class="form-label">Benutzername</label>
                        <input type="text" class="form-input" name="username" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Passwort</label>
                        <input type="password" class="form-input" name="password" required>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember"> Angemeldet bleiben
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="showRegisterBtn">Registrieren</button>
                <button type="submit" form="loginForm" class="btn btn-primary">Anmelden</button>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <div class="modal" id="registerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Registrieren</h2>
                <button class="modal-close" id="registerModalClose">&times;</button>
            </div>
            <div class="modal-body">
                <form id="registerForm">
                    <div class="form-group">
                        <label class="form-label">Benutzername</label>
                        <input type="text" class="form-input" name="username" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">E-Mail (optional)</label>
                        <input type="email" class="form-input" name="email">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Passwort</label>
                        <input type="password" class="form-input" name="password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Passwort bestätigen</label>
                        <input type="password" class="form-input" name="confirm_password" required>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="agree_terms" required> Ich stimme den Nutzungsbedingungen zu
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="showLoginBtn">Anmelden</button>
                <button type="submit" form="registerForm" class="btn btn-primary">Registrieren</button>
            </div>
        </div>
    </div>

    <!-- Feedback Overlay -->
    <div class="feedback-overlay" id="feedbackOverlay">
        <div class="feedback-emoji" id="feedbackEmoji">😊</div>
        <div class="feedback-message" id="feedbackMessage">Super!</div>
        <button class="btn btn-primary" id="nextBtn">Nächste Vokabel</button>
    </div>

    <!-- Global overlays -->
    <div class="overlay" id="overlay"></div>
    <div class="reward-animation" id="rewardAnimation"></div>

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
                <p>&copy; <?php echo date('Y'); ?> VocaBlitz - Französisch Vokabeltrainer <a href="https://bildungssprit.de" target="_blank" rel="noopener noreferrer">@medienrocker</a></p>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // Globale Konfiguration
        window.VocaBlitz = {
            isLoggedIn: <?php echo $isLoggedIn ? 'true' : 'false'; ?>,
            currentUser: <?php echo $isLoggedIn ? json_encode($currentUser) : 'null'; ?>,
            apiBase: 'api/',
            debug: true
        };
    </script>
    <script src="assets/js/ui.js"></script>
    <script src="assets/js/trainer.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>