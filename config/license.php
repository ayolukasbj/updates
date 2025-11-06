<?php
// config/license.php
// License Protection System

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

class LicenseManager {
    private $conn;
    private $license_key;
    private $domain;
    private $ip;
    
    public function __construct() {
        try {
            $db = new Database();
            $this->conn = $db->getConnection();
            $this->license_key = $this->getLicenseKey();
            $this->domain = $this->getDomain();
            $this->ip = $this->getIP();
        } catch (Exception $e) {
            error_log("License Manager Error: " . $e->getMessage());
        }
    }
    
    /**
     * Get license key from database or config
     */
    private function getLicenseKey() {
        try {
            if ($this->conn) {
                $stmt = $this->conn->query("SELECT setting_value FROM settings WHERE setting_key = 'license_key' LIMIT 1");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && !empty($result['setting_value'])) {
                    return $result['setting_value'];
                }
            }
        } catch (Exception $e) {
            // Table might not exist
        }
        
        // Fallback to constant if defined
        return defined('LICENSE_KEY') ? LICENSE_KEY : '';
    }
    
    /**
     * Get current domain
     */
    private function getDomain() {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Remove port if present
        $host = preg_replace('/:\d+$/', '', $host);
        return $host;
    }
    
    /**
     * Get server IP
     */
    private function getIP() {
        $ip = $_SERVER['SERVER_ADDR'] ?? '';
        // Try alternative methods
        if (empty($ip)) {
            $ip = $_SERVER['LOCAL_ADDR'] ?? '';
        }
        if (empty($ip) && function_exists('gethostbyname')) {
            $ip = gethostbyname($this->domain);
        }
        return $ip;
    }
    
    /**
     * Verify license validity
     */
    public function verifyLicense() {
        // If no license key, allow for development (grace period)
        if (empty($this->license_key)) {
            // Check if we're in development mode
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                return ['valid' => true, 'message' => 'Development mode'];
            }
            // Allow 7 days grace period for new installations
            return $this->checkGracePeriod();
        }
        
        // Verify license format
        if (!$this->validateLicenseFormat($this->license_key)) {
            return ['valid' => false, 'message' => 'Invalid license key format'];
        }
        
        // Check local validation first (faster)
        $local_check = $this->checkLocalLicense();
        if (!$local_check['valid']) {
            return $local_check;
        }
        
        // Verify with license server (if configured)
        if (defined('LICENSE_SERVER_URL') && !empty(LICENSE_SERVER_URL)) {
            return $this->verifyWithServer();
        }
        
        // If no server, use local validation only
        return $local_check;
    }
    
    /**
     * Validate license key format
     */
    private function validateLicenseFormat($key) {
        // Format: XXXX-XXXX-XXXX-XXXX-XXXX (20 chars + 4 dashes)
        return preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key);
    }
    
    /**
     * Check local license in database
     */
    private function checkLocalLicense() {
        try {
            if (!$this->conn) {
                return ['valid' => false, 'message' => 'Database connection failed'];
            }
            
            // Check if licenses table exists
            $checkTable = $this->conn->query("SHOW TABLES LIKE 'licenses'");
            if ($checkTable->rowCount() == 0) {
                return ['valid' => true, 'message' => 'License table not initialized'];
            }
            
            $stmt = $this->conn->prepare("
                SELECT * FROM licenses 
                WHERE license_key = ? AND status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$this->license_key]);
            $license = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$license) {
                return ['valid' => false, 'message' => 'License not found or inactive'];
            }
            
            // Check expiration
            if (!empty($license['expires_at']) && strtotime($license['expires_at']) < time()) {
                return ['valid' => false, 'message' => 'License has expired'];
            }
            
            // Check domain binding
            if (!empty($license['bound_domain']) && $license['bound_domain'] !== $this->domain) {
                return ['valid' => false, 'message' => 'License is bound to a different domain'];
            }
            
            // Check IP binding (if set)
            if (!empty($license['bound_ip']) && $license['bound_ip'] !== $this->ip) {
                return ['valid' => false, 'message' => 'License is bound to a different IP address'];
            }
            
            // Update last verification
            $updateStmt = $this->conn->prepare("
                UPDATE licenses 
                SET last_verified = NOW(), verification_count = verification_count + 1 
                WHERE id = ?
            ");
            $updateStmt->execute([$license['id']]);
            
            return ['valid' => true, 'license' => $license];
            
        } catch (Exception $e) {
            error_log("License check error: " . $e->getMessage());
            return ['valid' => false, 'message' => 'License verification failed'];
        }
    }
    
    /**
     * Verify license with remote server
     */
    private function verifyWithServer() {
        $server_url = LICENSE_SERVER_URL;
        
        // If no server URL configured, use local validation only
        if (empty($server_url)) {
            return $this->checkLocalLicense();
        }
        
        $data = [
            'license_key' => $this->license_key,
            'domain' => $this->domain,
            'ip' => $this->ip,
            'version' => defined('APP_VERSION') ? APP_VERSION : '1.0.0'
        ];
        
        // Use the license server API endpoint
        $api_url = rtrim($server_url, '/') . '/api/verify.php';
        
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log("License server connection error: " . $curl_error);
            // If server is down, fall back to local check
            return $this->checkLocalLicense();
        }
        
        if ($http_code !== 200) {
            error_log("License server returned HTTP code: " . $http_code);
            // If server is down, fall back to local check
            return $this->checkLocalLicense();
        }
        
        $result = json_decode($response, true);
        if ($result && isset($result['valid']) && $result['valid']) {
            // Update local license record if verification succeeds
            $this->updateLocalLicense($result);
            return ['valid' => true, 'license' => $result];
        }
        
        return ['valid' => false, 'message' => $result['message'] ?? 'License verification failed'];
    }
    
    /**
     * Update local license record after server verification
     */
    private function updateLocalLicense($server_result) {
        try {
            if (!$this->conn) return;
            
            // Check if licenses table exists
            $checkTable = $this->conn->query("SHOW TABLES LIKE 'licenses'");
            if ($checkTable->rowCount() > 0) {
                $stmt = $this->conn->prepare("
                    UPDATE licenses 
                    SET last_verified = NOW(), verification_count = verification_count + 1 
                    WHERE license_key = ?
                ");
                $stmt->execute([$this->license_key]);
            }
        } catch (Exception $e) {
            // Silently fail
        }
    }
    
    /**
     * Check grace period for new installations
     */
    private function checkGracePeriod() {
        try {
            if (!$this->conn) {
                return ['valid' => false, 'message' => 'Database connection failed'];
            }
            
            // Check installation date
            $stmt = $this->conn->query("SELECT setting_value FROM settings WHERE setting_key = 'installation_date' LIMIT 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['setting_value'])) {
                $install_date = strtotime($result['setting_value']);
                $days_passed = (time() - $install_date) / 86400;
                
                if ($days_passed <= 7) {
                    $days_left = 7 - floor($days_passed);
                    return [
                        'valid' => true, 
                        'message' => "Grace period: {$days_left} days remaining",
                        'grace_period' => true
                    ];
                }
            } else {
                // First run - set installation date
                try {
                    $this->conn->exec("
                        CREATE TABLE IF NOT EXISTS settings (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            setting_key VARCHAR(255) UNIQUE,
                            setting_value TEXT
                        )
                    ");
                    $stmt = $this->conn->prepare("
                        INSERT INTO settings (setting_key, setting_value) 
                        VALUES ('installation_date', NOW()) 
                        ON DUPLICATE KEY UPDATE setting_value = NOW()
                    ");
                    $stmt->execute();
                    return ['valid' => true, 'message' => 'Grace period: 7 days remaining', 'grace_period' => true];
                } catch (Exception $e) {
                    // Continue
                }
            }
        } catch (Exception $e) {
            // Fall through
        }
        
        return ['valid' => false, 'message' => 'License key required'];
    }
    
    /**
     * Generate license key
     */
    public static function generateLicenseKey($prefix = '') {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Removed similar chars
        $key = '';
        
        for ($i = 0; $i < 20; $i++) {
            if ($i > 0 && $i % 4 == 0) {
                $key .= '-';
            }
            $key .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return !empty($prefix) ? $prefix . '-' . $key : $key;
    }
    
    /**
     * Encrypt license data
     */
    public static function encrypt($data, $key = null) {
        if ($key === null) {
            $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default-key-change-in-production';
        }
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', hash('sha256', $key), 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt license data
     */
    public static function decrypt($data, $key = null) {
        if ($key === null) {
            $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default-key-change-in-production';
        }
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', hash('sha256', $key), 0, $iv);
    }
}

/**
 * Auto-verify license on include (only in production)
 * Comment out this section if you want manual license checks
 */
function verifyPlatformLicense() {
    // Skip check in development mode
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        return true;
    }
    
    // Skip check for license management pages
    $allowed_paths = ['/admin/license', '/api/license', '/admin/login'];
    $current_path = $_SERVER['REQUEST_URI'] ?? '';
    foreach ($allowed_paths as $path) {
        if (strpos($current_path, $path) !== false) {
            return true;
        }
    }
    
    try {
        $license_manager = new LicenseManager();
        $license_check = $license_manager->verifyLicense();
        
        if (!$license_check['valid']) {
            // Log the attempt
            error_log("License verification failed: " . $license_check['message']);
            
            // Show error page instead of allowing access
            http_response_code(403);
            die('
            <!DOCTYPE html>
            <html>
            <head>
                <title>License Verification Failed</title>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
                    .error-box { background: white; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    h1 { color: #dc3545; }
                    p { color: #666; line-height: 1.6; }
                    .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class="error-box">
                    <h1>License Verification Failed</h1>
                    <p>' . htmlspecialchars($license_check['message']) . '</p>
                    <p>Please contact support or activate your license.</p>
                    <a href="' . (defined('BASE_PATH') ? BASE_PATH : '/') . 'admin/license-management.php" class="btn">Activate License</a>
                </div>
            </body>
            </html>
            ');
        }
        
        return true;
    } catch (Exception $e) {
        error_log("License verification exception: " . $e->getMessage());
        // Allow access but log the error
        return true;
    }
}

// Uncomment the line below to enable automatic license verification
// verifyPlatformLicense();

