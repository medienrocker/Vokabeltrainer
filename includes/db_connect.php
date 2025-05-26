<?php
// includes/db_connect.php
require_once 'config.php';

function connectDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::ATTR_TIMEOUT => 10,
                PDO::ATTR_PERSISTENT => false,
            ];
            
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=utf8mb4;port=3306",
                DB_HOST,
                DB_NAME
            );
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // IONOS-spezifische MySQL-Einstellungen
            $pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
            
        } catch (PDOException $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                die("Verbindungsfehler: " . $e->getMessage());
            } else {
                die("Datenbankverbindung fehlgeschlagen");
            }
        }
    }
    
    return $pdo;
}
?>