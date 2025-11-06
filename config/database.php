<?php
// config/database.php
// Database configuration for Music Streaming Platform

// Load config constants if available
if (!defined('DB_HOST')) {
    $config_file = __DIR__ . '/config.php';
    if (file_exists($config_file)) {
        require_once $config_file;
    }
}

// Ensure config is loaded (fallback check)
if (!defined('DB_HOST') && file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset = 'utf8mb4';
    public $conn;

    public function __construct() {
        // Use constants from config.php if available, otherwise use defaults
        $this->host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $this->db_name = defined('DB_NAME') ? DB_NAME : 'music_streaming';
        $this->username = defined('DB_USER') ? DB_USER : 'root';
        $this->password = defined('DB_PASS') ? DB_PASS : '';
    }

    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $exception) {
            // Log error instead of echoing (prevents breaking binary streams)
            error_log("Database connection error: " . $exception->getMessage());
            $this->conn = null; // Ensure conn is null on error
        }
        
        return $this->conn;
    }
}
// NO closing PHP tag - prevents trailing whitespace
