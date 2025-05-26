<?php

/**
 * Standalone Login-Seite
 * Datei: login.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';
// CSRF-Token für diese Seite generieren
$csrfToken = generateCSRFToken();
require_once 'includes/debug.php';

// Bereits angemeldete Benutzer zur Hauptseite weiterleiten
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// Redirect-Parameter für Weiterleitung nach Login
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
$errorMessage = isset($_GET['error']) ? $_GET['error'] : '';

// Error Messages
$errorMessages = [
    'unauthorized' => 'Sie müssen sich anmelden, um auf diesen Bereich zuzugreifen.',
    'expired' => 'Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.',
    'invalid' => 'Ungültige Anmeldedaten.',
    'required' => 'Anmeldung erforderlich.'
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anmelden - VocaBlitz</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <style>
        /* Zusätzliche Styles für die Login-Seite */
        .auth-page {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-md);
        }

        .auth-container {
            width: 100%;
            max-width: 400px;
        }

        .auth-card {
            background: white;
            border-radius: var(--border-radius-xl);
            box-shadow: var(--shadow-xl);
            padding: var(--spacing-2xl);
            text-align: center;
        }

        .auth-logo {
            margin-bottom: var(--spacing-xl);
        }

        .auth-logo i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: var(--spacing-md);
        }

        .auth-title {
            font-size: var(--font-size-2xl);
            color: var(--primary);
            margin-bottom: var(--spacing-sm);
        }

        .auth-subtitle {
            color: #666;
            margin-bottom: var(--spacing-xl);
        }

        .auth-tabs {
            display: flex;
            margin-bottom: var(--spacing-xl);
            border-radius: var(--border-radius);
            background-color: var(--light);
            padding: var(--spacing-xs);
        }

        .auth-tab {
            flex: 1;
            padding: var(--spacing-sm) var(--spacing-md);
            background: none;
            border: none;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: all var(--transition-fast);
            font-weight: 500;
        }

        .auth-tab.active {
            background: white;
            color: var(--primary);
            box-shadow: var(--shadow-sm);
        }

        .auth-form {
            margin-bottom: var(--spacing-lg);
        }

        .auth-form.hidden {
            display: none;
        }

        .form-group {
            margin-bottom: var(--spacing-lg);
            text-align: left;
        }

        .form-label {
            display: block;
            margin-bottom: var(--spacing-sm);
            font-weight: 500;
            color: var(--dark);
        }

        .form-input {
            width: 100%;
            padding: var(--spacing-md);
            border: 2px solid #e5e7eb;
            border-radius: var(--border-radius);
            font-size: var(--font-size-base);
            transition: border-color var(--transition-fast);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            text-align: left;
        }

        .auth-footer {
            text-align: center;
            padding-top: var(--spacing-lg);
            border-top: 1px solid #e5e7eb;
        }

        .auth-footer a {
            color: var(--primary);
            font-weight: 500;
        }

        .password-strength {
            margin-top: var(--spacing-sm);
            font-size: var(--font-size-sm);
        }

        .strength-bar {
            height: 4px;
            border-radius: 2px;
            background-color: #e5e7eb;
            margin: var(--spacing-xs) 0;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            transition: all var(--transition-normal);
            border-radius: 2px;
        }

        .strength-weak { background-color: var(--error); width: 25%; }
        .strength-fair { background-color: var(--warning); width: 50%; }
        .strength-good { background-color: var(--info); width: 75%; }
        .strength-strong { background-color: var(--success); width: 100%; }

        .back-link {
            position: absolute;
            top: var(--spacing-lg);
            left: var(--spacing-lg);
            color: white;
            font-size: var(--font-size-lg);
            text-decoration: none;
            transition: all var(--transition-fast);
        }

        .back-link:hover {
            color: rgba(255, 255, 255, 0.8);
            transform: translateX(-3px);
        }

        @media (max-width: 480px) {
            .auth-card {
                padding: var(--spacing-xl);
            }
            
            .auth-page {
                padding: var(--spacing-sm);
            }
        }
    </style>
</head>
<body>
    <div class="auth-page">
        <a href="index.php" class="back-link" title="Zurück zur Hauptseite">
            <i class="fas fa-arrow-left"></i>
        </a>

        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-logo">
                    <i class="fas fa-language"></i>
                    <h1 class="auth-title">VocaBlitz</h1>
                    <p class="auth-subtitle">Französisch Vokabeltrainer</p>
                </div>

                <?php if ($errorMessage && isset($errorMessages[$errorMessage])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $errorMessages[$errorMessage]; ?>
                </div>
                <?php endif; ?>

                <!-- Tab Navigation -->
                <div class="auth-tabs">
                    <button class="auth-tab active" data-tab="login">Anmelden</button>
                    <button class="auth-tab" data-tab="register">Registrieren</button>
                </div>

                <!-- Login Form -->
                <form class="auth-form" id="loginForm" data-tab-content="login">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <div class="form-group">
                        <label class="form-label" for="login-username">
                            <i class="fas fa-user"></i> Benutzername
                        </label>
                        <input type="text" id="login-username" name="username" class="form-input" required autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="login-password">
                            <i class="fas fa-lock"></i> Passwort
                        </label>
                        <input type="password" id="login-password" name="password" class="form-input" required autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Angemeldet bleiben</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Anmelden
                    </button>

                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                </form>

                <!-- Register Form -->
                <form class="auth-form hidden" id="registerForm" data-tab-content="register">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <div class="form-group">
                        <label class="form-label" for="register-username">
                            <i class="fas fa-user"></i> Benutzername
                        </label>
                        <input type="text" id="register-username" name="username" class="form-input" required autocomplete="username">
                        <div class="form-help">Mindestens 3 Zeichen, nur Buchstaben, Zahlen, - und _</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="register-email">
                            <i class="fas fa-envelope"></i> E-Mail (optional)
                        </label>
                        <input type="email" id="register-email" name="email" class="form-input" autocomplete="email">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="register-password">
                            <i class="fas fa-lock"></i> Passwort
                        </label>
                        <input type="password" id="register-password" name="password" class="form-input" required autocomplete="new-password">
                        
                        <!-- Password Strength Indicator -->
                        <div class="password-strength" id="passwordStrength">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <div class="strength-text" id="strengthText">Passwort eingeben</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="register-confirm-password">
                            <i class="fas fa-lock"></i> Passwort bestätigen
                        </label>
                        <input type="password" id="register-confirm-password" name="confirm_password" class="form-input" required autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="agree-terms" name="agree_terms" required>
                            <label for="agree-terms">Ich stimme den <a href="#" target="_blank">Nutzungsbedingungen</a> zu</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus"></i> Registrieren
                    </button>
                </form>

                <!-- Alternative Actions -->
                <div class="auth-footer">
                    <p>Ohne Anmeldung weiter?</p>
                    <a href="index.php" class="btn btn-ghost">
                        <i class="fas fa-play"></i> Als Gast starten
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Container für Nachrichten -->
    <div id="alert-container"></div>

    <script>
        class AuthPage {
            constructor() {
                this.init();
            }

            init() {
                this.setupTabs();
                this.setupPasswordStrength();
                this.setupForms();
            }

            setupTabs() {
                const tabs = document.querySelectorAll('.auth-tab');
                const forms = document.querySelectorAll('.auth-form');

                tabs.forEach(tab => {
                    tab.addEventListener('click', () => {
                        // Tab-Styling
                        tabs.forEach(t => t.classList.remove('active'));
                        tab.classList.add('active');

                        // Form anzeigen
                        const targetTab = tab.dataset.tab;
                        forms.forEach(form => {
                            form.classList.add('hidden');
                        });
                        document.querySelector(`[data-tab-content="${targetTab}"]`).classList.remove('hidden');
                    });
                });
            }

            setupPasswordStrength() {
                const passwordInput = document.getElementById('register-password');
                const strengthFill = document.getElementById('strengthFill');
                const strengthText = document.getElementById('strengthText');

                if (!passwordInput) return;

                passwordInput.addEventListener('input', () => {
                    const password = passwordInput.value;
                    const strength = this.calculatePasswordStrength(password);
                    
                    strengthFill.className = 'strength-fill';
                    
                    if (password.length === 0) {
                        strengthText.textContent = 'Passwort eingeben';
                        return;
                    }

                    switch (strength.level) {
                        case 1:
                            strengthFill.classList.add('strength-weak');
                            strengthText.textContent = 'Schwach';
                            break;
                        case 2:
                            strengthFill.classList.add('strength-fair');
                            strengthText.textContent = 'Mittel';
                            break;
                        case 3:
                            strengthFill.classList.add('strength-good');
                            strengthText.textContent = 'Gut';
                            break;
                        case 4:
                            strengthFill.classList.add('strength-strong');
                            strengthText.textContent = 'Stark';
                            break;
                    }

                    if (strength.feedback.length > 0) {
                        strengthText.textContent += ' - ' + strength.feedback.join(', ');
                    }
                });
            }

            calculatePasswordStrength(password) {
                let score = 0;
                const feedback = [];

                if (password.length >= 8) score++;
                else feedback.push('mindestens 8 Zeichen');

                if (/[a-z]/.test(password)) score++;
                else feedback.push('Kleinbuchstaben');

                if (/[A-Z]/.test(password)) score++;
                else feedback.push('Großbuchstaben');

                if (/[0-9]/.test(password)) score++;
                else feedback.push('Zahlen');

                if (/[^a-zA-Z0-9]/.test(password)) score++;
                else feedback.push('Sonderzeichen');

                return {
                    level: Math.min(score, 4),
                    feedback: feedback
                };
            }

            setupForms() {
                // Login Form
                document.getElementById('loginForm').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    await this.handleLogin(new FormData(e.target));
                });

                // Register Form
                document.getElementById('registerForm').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    await this.handleRegister(new FormData(e.target));
                });
            }

            async handleLogin(formData) {
                const submitBtn = document.querySelector('#loginForm button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                try {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Anmelden...';
                    submitBtn.disabled = true;

                    const response = await fetch('api/user/login.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showAlert('Erfolgreich angemeldet!', 'success');
                        
                        // Weiterleitung
                        const redirect = formData.get('redirect') || 'index.php';
                        setTimeout(() => {
                            window.location.href = redirect;
                        }, 1000);
                    } else {
                        this.showAlert(result.error || 'Anmeldung fehlgeschlagen', 'error');
                        
                        if (result.remaining_attempts) {
                            this.showAlert(`Noch ${result.remaining_attempts} Versuche übrig`, 'warning');
                        }
                    }
                } catch (error) {
                    console.error('Login error:', error);
                    this.showAlert('Netzwerkfehler bei der Anmeldung', 'error');
                } finally {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            }

            async handleRegister(formData) {
                const submitBtn = document.querySelector('#registerForm button[type="submit"]') || 
                  document.querySelector('button[form="registerForm"]');
                const originalText = submitBtn.innerHTML;
                
                // Client-side Validierung
                const password = formData.get('password');
                const confirmPassword = formData.get('confirm_password');
                
                if (password !== confirmPassword) {
                    this.showAlert('Passwörter stimmen nicht überein', 'error');
                    return;
                }
                
                try {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrieren...';
                    submitBtn.disabled = true;

                    const response = await fetch('api/user/register.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showAlert('Erfolgreich registriert!', 'success');
                        
                        // Automatische Weiterleitung oder Login-Tab anzeigen
                        if (result.user) {
                            setTimeout(() => {
                                window.location.href = 'index.php';
                            }, 1000);
                        } else {
                            setTimeout(() => {
                                document.querySelector('[data-tab="login"]').click();
                                this.showAlert('Bitte melden Sie sich jetzt an', 'info');
                            }, 1500);
                        }
                    } else {
                        if (result.errors && Array.isArray(result.errors)) {
                            result.errors.forEach(error => this.showAlert(error, 'error'));
                        } else {
                            this.showAlert(result.error || 'Registrierung fehlgeschlagen', 'error');
                        }
                    }
                } catch (error) {
                    console.error('Register error:', error);
                    this.showAlert('Netzwerkfehler bei der Registrierung', 'error');
                } finally {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            }

            showAlert(message, type = 'info', duration = 5000) {
                const alertId = 'alert-' + Date.now();
                const icons = {
                    success: 'check-circle',
                    error: 'exclamation-circle',
                    warning: 'exclamation-triangle',
                    info: 'info-circle'
                };
                
                const alertHtml = `
                    <div class="alert alert-${type}" id="${alertId}" style="
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        z-index: 1100;
                        max-width: 400px;
                        padding: 1rem;
                        border-radius: 8px;
                        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                        animation: slideInRight 0.3s ease;
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                    ">
                        <i class="fas fa-${icons[type]}"></i>
                        <span>${message}</span>
                        <button onclick="document.getElementById('${alertId}').remove()" style="
                            background: none;
                            border: none;
                            color: inherit;
                            cursor: pointer;
                            margin-left: auto;
                            opacity: 0.7;
                        ">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;

                let container = document.getElementById('alert-container');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'alert-container';
                    document.body.appendChild(container);
                }

                container.insertAdjacentHTML('beforeend', alertHtml);

                // Auto-hide
                if (duration > 0) {
                    setTimeout(() => {
                        const alert = document.getElementById(alertId);
                        if (alert) {
                            alert.style.animation = 'slideOutRight 0.3s ease';
                            setTimeout(() => alert.remove(), 300);
                        }
                    }, duration);
                }
            }
        }

        // CSS für Animationen hinzufügen
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100%);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            
            @keyframes slideOutRight {
                from {
                    opacity: 1;
                    transform: translateX(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(100%);
                }
            }
            
            .alert-success {
                background-color: rgba(74, 222, 128, 0.1);
                border-left: 4px solid #4ade80;
                color: #16a34a;
            }
            
            .alert-error {
                background-color: rgba(248, 113, 113, 0.1);
                border-left: 4px solid #f87171;
                color: #dc2626;
            }
            
            .alert-warning {
                background-color: rgba(251, 191, 36, 0.1);
                border-left: 4px solid #fbbf24;
                color: #d97706;
            }
            
            .alert-info {
                background-color: rgba(96, 165, 250, 0.1);
                border-left: 4px solid #60a5fa;
                color: #2563eb;
            }
        `;
        document.head.appendChild(style);

        // Initialize auth page
        new AuthPage();
    </script>
</body>
</html>