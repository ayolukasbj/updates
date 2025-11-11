<?php
// includes/settings.php
// Centralized settings system

require_once __DIR__ . '/../config/database.php';

class SettingsManager {
    private static $settings = null;
    private static $conn = null;
    
    /**
     * Get database connection
     */
    private static function getConnection() {
        if (self::$conn === null) {
            try {
                $db = new Database();
                self::$conn = $db->getConnection();
            } catch (Exception $e) {
                error_log("Settings Manager DB Error: " . $e->getMessage());
            }
        }
        return self::$conn;
    }
    
    /**
     * Load all settings from database
     */
    private static function loadSettings() {
        if (self::$settings !== null) {
            return self::$settings;
        }
        
        self::$settings = [];
        
        // Start with config constants as defaults
        self::$settings = [
            'site_name' => defined('SITE_NAME') ? SITE_NAME : 'Music Platform',
            'site_slogan' => defined('SITE_SLOGAN') ? SITE_SLOGAN : '',
            'site_description' => defined('SITE_DESCRIPTION') ? SITE_DESCRIPTION : '',
            'site_logo' => '',
            'site_favicon' => '',
            'license_key' => defined('LICENSE_KEY') ? LICENSE_KEY : '',
            'license_domain' => '',
            'license_type' => 'standard'
        ];
        
        // Load from database
        try {
            $conn = self::getConnection();
            if ($conn) {
                // Ensure settings table exists
                $conn->exec("
                    CREATE TABLE IF NOT EXISTS settings (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        setting_key VARCHAR(255) UNIQUE,
                        setting_value TEXT,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                
                $stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($results as $row) {
                    self::$settings[$row['setting_key']] = $row['setting_value'];
                }
            }
        } catch (Exception $e) {
            error_log("Settings load error: " . $e->getMessage());
        }
        
        return self::$settings;
    }
    
    /**
     * Get a setting value
     */
    public static function get($key, $default = '') {
        $settings = self::loadSettings();
        return $settings[$key] ?? $default;
    }
    
    /**
     * Set a setting value
     */
    public static function set($key, $value) {
        try {
            $conn = self::getConnection();
            if ($conn) {
                $stmt = $conn->prepare("
                    INSERT INTO settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
                
                // Update cache
                if (self::$settings !== null) {
                    self::$settings[$key] = $value;
                }
                
                return true;
            }
        } catch (Exception $e) {
            error_log("Settings set error: " . $e->getMessage());
        }
        return false;
    }
    
    /**
     * Get all settings
     */
    public static function getAll() {
        return self::loadSettings();
    }
    
    /**
     * Get site name (with fallback)
     */
    public static function getSiteName() {
        return self::get('site_name', defined('SITE_NAME') ? SITE_NAME : 'Music Platform');
    }
    
    /**
     * Get site slogan
     */
    public static function getSiteSlogan() {
        return self::get('site_slogan', '');
    }
    
    /**
     * Get site logo
     */
    public static function getSiteLogo() {
        $logo = self::get('site_logo', '');
        if (empty($logo)) {
            return 'assets/images/logo.png'; // Default logo path
        }
        return $logo;
    }
}


