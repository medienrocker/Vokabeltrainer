<?php
/**
 * Konfigurationsdatei für VocaBlitz
 * Datei: includes/config.php
 */

// Datenbankeinstellungen für IONOS
define('DB_HOST', 'db5017885861.hosting-data.io');
define('DB_NAME', 'dbs14248341');
define('DB_USER', 'dbu3370131');
define('DB_PASS', '#Trugwerk12');

// Anwendungseinstellungen
define('APP_URL', 'https://medienrocker.com/games/vokabeltrainer/');
define('APP_NAME', 'VocaBlitz');
define('APP_VERSION', '1.0.0');

// Sicherheitseinstellungen
define('SESSION_TIMEOUT', 3600); // 1 Stunde
define('BCRYPT_ROUNDS', 12);
define('MAX_LOGIN_ATTEMPTS', 5);

// Upload-Einstellungen
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_EXTENSIONS', ['csv']);

// Debugging (für Produktion auf false setzen)
define('DEBUG_MODE', false);

// Timezone
date_default_timezone_set('Europe/Berlin');

// Session-Konfiguration (nur wenn Session noch nicht aktiv)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_strict_mode', 1);
}
?>