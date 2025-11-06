<?php
// Start output buffering to prevent WSOD
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Initialize variables
$page_title = 'License Management';
$success = '';
$error = '';
$db = null;
$conn = null;
$current_license_key = '';
$license_status = ['valid' => false, 'message' => 'License not found'];
$licenses = [];

try {
    // Require files
    if (!file_exists('auth-check.php')) {
        throw new Exception('auth-check.php not found');
    }
    require_once 'auth-check.php';
    
    if (!file_exists('../config/database.php')) {
        throw new Exception('database.php not found');
    }
    require_once '../config/database.php';
    
    if (!file_exists('../config/license.php')) {
        throw new Exception('license.php not found');
    }
    require_once '../config/license.php';

    // Initialize database
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed. Please check your database configuration.');
    }
} catch (Exception $e) {
    ob_clean();
    die('Error initializing: ' . htmlspecialchars($e->getMessage()));
}

// Create licenses table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS licenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            license_key VARCHAR(255) NOT NULL UNIQUE,
            customer_name VARCHAR(255),
            customer_email VARCHAR(255),
            domain VARCHAR(255),
            bound_domain VARCHAR(255),
            bound_ip VARCHAR(45),
            status ENUM('active', 'inactive', 'suspended', 'expired') DEFAULT 'active',
            license_type ENUM('trial', 'standard', 'premium', 'lifetime') DEFAULT 'standard',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            last_verified TIMESTAMP NULL,
            verification_count INT DEFAULT 0,
            notes TEXT,
            INDEX idx_license_key (license_key),
            INDEX idx_status (status),
            INDEX idx_domain (bound_domain)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create settings table if it doesn't exist
    $conn->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(255) UNIQUE,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
    error_log("License table creation error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF Protection
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = 'Invalid security token. Please refresh the page and try again.';
        } else {
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'activate_license':
                    $license_key = trim($_POST['license_key'] ?? '');
                    
                    if (empty($license_key)) {
                        $error = 'License key is required!';
                        break;
                    }
                    
                    // Validate format
                    if (!preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $license_key)) {
                        $error = 'Invalid license key format! Format should be: XXXX-XXXX-XXXX-XXXX-XXXX';
                        break;
                    }
                    
                    // Get current domain/IP
                    $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $domain = preg_replace('/:\d+$/', '', $domain);
                    $ip = $_SERVER['SERVER_ADDR'] ?? $_SERVER['LOCAL_ADDR'] ?? '';
                    
                    // Auto-detect license server URL
                    $is_local = (
                        $domain === 'localhost' || 
                        strpos($domain, '127.0.0.1') !== false ||
                        strpos($domain, 'localhost') !== false ||
                        strpos($domain, '.local') !== false
                    );
                    
                    $license_server_url = defined('LICENSE_SERVER_URL') ? LICENSE_SERVER_URL : '';
                    if (empty($license_server_url)) {
                        $license_server_url = $is_local ? 'http://localhost/license-server' : 'https://hylinktech.com/server';
                    }
                    
                    $api_url = rtrim($license_server_url, '/') . '/api/verify.php';
                    
                    // Verify with license server
                    $verify_data = [
                        'license_key' => $license_key,
                        'domain' => $domain,
                        'ip' => $ip,
                        'version' => defined('APP_VERSION') ? APP_VERSION : '1.0.0'
                    ];
                    
                    $ch = curl_init($api_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($verify_data));
                    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'MusicPlatform/1.0');
                    
                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curl_error = curl_error($ch);
                    curl_close($ch);
                    
                    if ($curl_error) {
                        $error = 'Failed to connect to license server: ' . htmlspecialchars($curl_error);
                        error_log("License activation error: " . $curl_error);
                        break;
                    }
                    
                    if ($http_code !== 200) {
                        $error = 'License server returned error (HTTP ' . $http_code . '). Please check your license key and try again.';
                        error_log("License server HTTP error: " . $http_code);
                        break;
                    }
                    
                    $result = json_decode($response, true);
                    
                    if (!$result || !isset($result['valid']) || !$result['valid']) {
                        $error = $result['message'] ?? 'License verification failed. Please check your license key.';
                        error_log("License verification failed: " . ($result['message'] ?? 'Unknown error'));
                        break;
                    }
                    
                    // License is valid - save it
                    try {
                        // Save to database settings
                        $saveStmt = $conn->prepare("
                            INSERT INTO settings (setting_key, setting_value) 
                            VALUES ('license_key', ?) 
                            ON DUPLICATE KEY UPDATE setting_value = ?
                        ");
                        $saveStmt->execute([$license_key, $license_key]);
                        
                        // Also save domain binding
                        $saveStmt = $conn->prepare("
                            INSERT INTO settings (setting_key, setting_value) 
                            VALUES ('license_domain', ?) 
                            ON DUPLICATE KEY UPDATE setting_value = ?
                        ");
                        $saveStmt->execute([$domain, $domain]);
                        
                        $success = 'License activated successfully! Your license is now active and verified.';
                        
                        if (function_exists('logAdminActivity')) {
                            logAdminActivity($_SESSION['user_id'] ?? 0, 'activate_license', 'license', 0, "Activated license: " . substr($license_key, 0, 10) . "...");
                        }
                    } catch (Exception $e) {
                        $error = 'License verified but failed to save: ' . $e->getMessage();
                        error_log("License save error: " . $e->getMessage());
                    }
                    break;
                
                case 'deactivate_license':
                    // Remove license key from settings
                    try {
                        $removeStmt = $conn->prepare("DELETE FROM settings WHERE setting_key = 'license_key'");
                        $removeStmt->execute();
                        
                        $success = 'License deactivated successfully. Please activate a new license to continue.';
                        if (function_exists('logAdminActivity')) {
                            logAdminActivity($_SESSION['user_id'] ?? 0, 'deactivate_license', 'license', 0, "Deactivated license");
                        }
                    } catch (Exception $e) {
                        $error = 'Error deactivating license: ' . $e->getMessage();
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
        error_log("License action error: " . $e->getMessage());
    }
}

// Get current license (check database)
try {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'license_key' LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && !empty($result['setting_value'])) {
        $current_license_key = $result['setting_value'];
    }
} catch (Exception $e) {
    // Settings table might not exist
}

// Fallback to config constant if database doesn't have it
if (empty($current_license_key) && defined('LICENSE_KEY')) {
    $current_license_key = LICENSE_KEY;
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verify current license
try {
    $license_manager = new LicenseManager();
    $license_status = $license_manager->verifyLicense();
} catch (Exception $e) {
    error_log("License verification error: " . $e->getMessage());
    $license_status = ['valid' => false, 'message' => 'License verification error'];
}

// Get all licenses
try {
    $stmt = $conn->query("SELECT * FROM licenses ORDER BY created_at DESC");
    $licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $licenses = [];
    error_log("License fetch error: " . $e->getMessage());
}

// Include header
try {
    if (file_exists('includes/header.php')) {
        include 'includes/header.php';
    } else {
        throw new Exception('Header file not found');
    }
} catch (Exception $e) {
    ob_clean();
    die('Error loading header: ' . htmlspecialchars($e->getMessage()));
}
?>

<div class="page-header">
    <h1>License Management</h1>
    <p>Manage software licenses and activation</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<!-- Current License Status -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Current License Status</h2>
    </div>
    <div class="card-body">
        <?php if ($license_status['valid'] ?? false): ?>
        <div style="padding: 20px; background: #d1e7dd; border-radius: 6px; margin-bottom: 15px;">
            <h3 style="color: #0f5132; margin: 0 0 10px 0;">
                <i class="fas fa-check-circle"></i> License Active
            </h3>
            <p style="color: #0f5132; margin: 0;">
                <?php echo htmlspecialchars($license_status['message'] ?? 'License is valid and active'); ?>
            </p>
        </div>
        <?php else: ?>
        <div style="padding: 20px; background: #f8d7da; border-radius: 6px; margin-bottom: 15px;">
            <h3 style="color: #842029; margin: 0 0 10px 0;">
                <i class="fas fa-exclamation-triangle"></i> License Invalid
            </h3>
            <p style="color: #842029; margin: 0;">
                <?php echo htmlspecialchars($license_status['message'] ?? 'License verification failed'); ?>
            </p>
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 15px;">
            <strong>Current License Key:</strong> 
            <?php if (!empty($current_license_key)): ?>
                <code style="background: #f5f5f5; padding: 5px 10px; border-radius: 4px; font-size: 14px;">
                    <?php echo htmlspecialchars(substr($current_license_key, 0, 15)) . '...'; ?>
                </code>
                <button type="button" class="btn btn-sm btn-secondary" onclick="copyLicenseKey()" style="margin-left: 10px;">
                    <i class="fas fa-copy"></i> Copy Full Key
                </button>
            <?php else: ?>
                <span style="color: #999;">Not activated</span>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 10px;">
            <strong>Domain:</strong> 
            <code><?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost'); ?></code>
        </div>
        
        <div style="margin-top: 10px;">
            <strong>License Server:</strong> 
            <code><?php 
                $server_url = defined('LICENSE_SERVER_URL') ? LICENSE_SERVER_URL : 'Not configured';
                echo htmlspecialchars($server_url); 
            ?></code>
        </div>
        
        <?php if (!empty($current_license_key)): ?>
        <div style="margin-top: 15px;">
            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to deactivate this license? You will need to reactivate it to continue using the platform.');">
                <input type="hidden" name="action" value="deactivate_license">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <button type="submit" class="btn btn-sm btn-warning">
                    <i class="fas fa-ban"></i> Deactivate License
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <script>
        function copyLicenseKey() {
            const key = '<?php echo htmlspecialchars($current_license_key ?? ''); ?>';
            if (navigator.clipboard) {
                navigator.clipboard.writeText(key).then(function() {
                    alert('License key copied to clipboard!');
                });
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = key;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('License key copied to clipboard!');
            }
        }
        </script>
    </div>
</div>

<!-- Activate License -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Activate License</h2>
    </div>
    <div class="card-body">
        <form method="POST" id="activateLicenseForm">
            <input type="hidden" name="action" value="activate_license">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <div class="form-group">
                <label>License Key <span style="color: red;">*</span></label>
                <input type="text" name="license_key" id="license_key_input" class="form-control" required 
                    placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" 
                    style="font-family: monospace; letter-spacing: 2px; text-transform: uppercase;"
                    pattern="[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}"
                    maxlength="29">
                <small class="text-muted">Enter your license key to activate. The key will be verified with the license server.</small>
            </div>
            <button type="submit" class="btn btn-primary" id="activateBtn">
                <i class="fas fa-key"></i> Activate License
            </button>
        </form>
        
        <script>
        // Auto-format license key input
        document.getElementById('license_key_input').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^A-Z0-9]/g, '').toUpperCase();
            let formatted = '';
            for (let i = 0; i < value.length && i < 20; i++) {
                if (i > 0 && i % 4 === 0) {
                    formatted += '-';
                }
                formatted += value[i];
            }
            e.target.value = formatted;
        });
        
        // Show loading state on submit
        document.getElementById('activateLicenseForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('activateBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
        });
        </script>
    </div>
</div>

<?php 
try {
    if (file_exists('includes/footer.php')) {
        include 'includes/footer.php';
    }
} catch (Exception $e) {
    error_log("Footer include error: " . $e->getMessage());
}
ob_end_flush();
?>

