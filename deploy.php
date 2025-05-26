<?php
// 6. IONOS DEPLOYMENT SCRIPT
// Erstelle deploy.php für einfaches Deployment


class IONOSDeployer {
    private $config;
    
    public function __construct() {
        $this->config = [
            'backup_dir' => './backups/',
            'temp_dir' => './temp/',
            'required_dirs' => [
                'uploads', 'logs', 'cache', 'temp', 'backups'
            ],
            'protected_files' => [
                'config.php', '.htaccess'
            ]
        ];
    }
    
    public function deploy() {
        echo "🚀 IONOS VocaBlitz Deployment gestartet...\n";
        
        try {
            $this->checkRequirements();
            $this->createDirectories();
            $this->setPermissions();
            $this->createProtectedFiles();
            $this->testDatabase();
            $this->optimizeDatabase();
            
            echo "✅ Deployment erfolgreich abgeschlossen!\n";
            echo "🌐 Ihre VocaBlitz App ist bereit unter: " . $this->getAppUrl() . "\n";
            
        } catch (Exception $e) {
            echo "❌ Deployment fehlgeschlagen: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    private function checkRequirements() {
        echo "📋 Prüfe Systemanforderungen...\n";
        
        // PHP Version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            throw new Exception('PHP 7.4+ erforderlich, aktuell: ' . PHP_VERSION);
        }
        
        // Erforderliche Extensions
        $required = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'session'];
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                throw new Exception("PHP Extension '$ext' nicht gefunden");
            }
        }
        
        echo "✅ Systemanforderungen erfüllt\n";
    }
    
    private function createDirectories() {
        echo "📁 Erstelle Verzeichnisstruktur...\n";
        
        foreach ($this->config['required_dirs'] as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new Exception("Konnte Verzeichnis '$dir' nicht erstellen");
                }
                echo "  ✓ $dir erstellt\n";
            }
        }
    }
    
    private function setPermissions() {
        echo "🔒 Setze Berechtigungen...\n";
        
        // Schreibrechte für Upload-Verzeichnisse
        $writableDirs = ['uploads', 'logs', 'cache', 'temp'];
        foreach ($writableDirs as $dir) {
            if (is_dir($dir)) {
                chmod($dir, 0755);
                echo "  ✓ $dir: 755\n";
            }
        }
        
        // Ausführrechte für PHP-Dateien
        chmod('index.php', 0644);
        chmod('login.php', 0644);
        chmod('dashboard.php', 0644);
    }
    
    private function createProtectedFiles() {
        echo "🛡️ Erstelle Schutz-Dateien...\n";
        
        // .htaccess für includes
        $includesHtaccess = "includes/.htaccess";
        if (!file_exists($includesHtaccess)) {
            file_put_contents($includesHtaccess, "Require all denied\n");
            echo "  ✓ includes/.htaccess\n";
        }
        
        // .htaccess für uploads
        $uploadsHtaccess = "uploads/.htaccess";
        if (!file_exists($uploadsHtaccess)) {
            file_put_contents($uploadsHtaccess, 
                "<Files \"*.php\">\n    Require all denied\n</Files>\n"
            );
            echo "  ✓ uploads/.htaccess\n";
        }
        
        // index.php für Verzeichnisse
        $protectedDirs = ['logs', 'cache', 'temp', 'backups'];
        foreach ($protectedDirs as $dir) {
            $indexFile = "$dir/index.php";
            if (!file_exists($indexFile)) {
                file_put_contents($indexFile, "<?php http_response_code(403); ?>");
                echo "  ✓ $dir/index.php\n";
            }
        }
    }
    
    private function testDatabase() {
        echo "🗄️ Teste Datenbankverbindung...\n";
        
        if (!file_exists('includes/config.php')) {
            throw new Exception('config.php nicht gefunden. Bitte zuerst konfigurieren.');
        }
        
        require_once 'includes/config.php';
        
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS
            );
            echo "✅ Datenbankverbindung erfolgreich\n";
        } catch (PDOException $e) {
            throw new Exception('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage());
        }
    }
    
    private function optimizeDatabase() {
        echo "⚡ Optimiere Datenbank...\n";
        
        require_once 'includes/config.php';
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS
        );
        
        // Prüfe ob Tabellen existieren
        $tables = ['users', 'vocabulary_lists', 'vocabulary_items', 'learning_progress'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() === 0) {
                echo "  ⚠️ Tabelle '$table' nicht gefunden\n";
            } else {
                echo "  ✓ Tabelle '$table' vorhanden\n";
            }
        }
        
        // Erstelle fehlende Indizes
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)",
            "CREATE INDEX IF NOT EXISTS idx_vocabulary_items_list ON vocabulary_items(list_id)",
            "CREATE INDEX IF NOT EXISTS idx_learning_progress_user ON learning_progress(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_learning_progress_next_review ON learning_progress(next_review)"
        ];
        
        foreach ($indexes as $sql) {
            try {
                $pdo->exec($sql);
                echo "  ✓ Index erstellt\n";
            } catch (PDOException $e) {
                echo "  ⚠️ Index-Erstellung übersprungen: " . $e->getMessage() . "\n";
            }
        }
    }
    
    private function getAppUrl() {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['REQUEST_URI'] ?? '');
        return $protocol . $host . $path;
    }
}