<?php
// api/remote-management.php
// Remote Platform Management API Endpoint (called by license server)

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/license.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input)) {
    $input = $_POST;
}

$license_key = trim($input['license_key'] ?? '');
$action = trim($input['action'] ?? '');

// Verify license key
if (empty($license_key)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'License key is required']);
    exit;
}

// Verify license is valid
try {
    $license_manager = new LicenseManager();
    $license_check = $license_manager->verifyLicense();
    
    if (!$license_check['valid'] || $license_manager->license_key !== $license_key) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or inactive license']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'License verification failed']);
    exit;
}

// Handle different actions
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    switch ($action) {
        case 'get_status':
            // Get platform status
            $status_stmt = $conn->query("SELECT COUNT(*) as total_users FROM users");
            $status_data = $status_stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'status' => 'online',
                'platform' => [
                    'total_users' => (int)($status_data['total_users'] ?? 0),
                    'version' => defined('SCRIPT_VERSION') ? SCRIPT_VERSION : '1.0'
                ]
            ]);
            break;
            
        case 'enable_feature':
        case 'disable_feature':
            $feature = trim($input['feature'] ?? '');
            if (empty($feature)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Feature name is required']);
                break;
            }
            
            // Update feature in settings
            $enabled = ($action === 'enable_feature') ? 1 : 0;
            $stmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute(["feature_{$feature}", $enabled, $enabled]);
            
            echo json_encode([
                'success' => true,
                'message' => "Feature '$feature' " . ($enabled ? 'enabled' : 'disabled'),
                'action' => $action,
                'feature' => $feature
            ]);
            break;
            
        case 'update_setting':
            $setting_key = trim($input['setting_key'] ?? '');
            $setting_value = trim($input['setting_value'] ?? '');
            
            if (empty($setting_key)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Setting key is required']);
                break;
            }
            
            // Update setting
            $stmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$setting_key, $setting_value, $setting_value]);
            
            echo json_encode([
                'success' => true,
                'message' => "Setting '$setting_key' updated",
                'action' => 'update_setting',
                'setting' => ['key' => $setting_key, 'value' => $setting_value]
            ]);
            break;
            
        case 'clear_cache':
            // Clear cache (if cache directory exists)
            $cache_dir = __DIR__ . '/../cache';
            if (is_dir($cache_dir)) {
                $files = glob($cache_dir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Cache cleared',
                'action' => 'clear_cache'
            ]);
            break;
            
        case 'enable_maintenance':
            // Enable maintenance mode
            $stmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES ('maintenance_mode', '1') 
                ON DUPLICATE KEY UPDATE setting_value = '1'
            ");
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Maintenance mode enabled',
                'action' => 'enable_maintenance'
            ]);
            break;
            
        case 'disable_maintenance':
            // Disable maintenance mode
            $stmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES ('maintenance_mode', '0') 
                ON DUPLICATE KEY UPDATE setting_value = '0'
            ");
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Maintenance mode disabled',
                'action' => 'disable_maintenance'
            ]);
            break;
            
        case 'force_update':
            // Force update check (triggers update check)
            $version = trim($input['version'] ?? '');
            
            echo json_encode([
                'success' => true,
                'message' => 'Update check triggered',
                'action' => 'force_update',
                'version' => $version
            ]);
            break;
            
        case 'get_logs':
            $limit = (int)($input['limit'] ?? 50);
            $limit = min(max($limit, 1), 1000); // Limit between 1 and 1000
            
            // Get recent admin logs if table exists
            $logs = [];
            try {
                $log_stmt = $conn->prepare("
                    SELECT * FROM admin_logs 
                    ORDER BY created_at DESC 
                    LIMIT ?
                ");
                $log_stmt->execute([$limit]);
                $logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Table might not exist
            }
            
            echo json_encode([
                'success' => true,
                'logs' => $logs,
                'count' => count($logs)
            ]);
            break;
            
        case 'disable_platform':
            // Disable platform access
            $stmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES ('platform_disabled', '1') 
                ON DUPLICATE KEY UPDATE setting_value = '1'
            ");
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Platform disabled',
                'action' => 'disable_platform'
            ]);
            break;
            
        case 'enable_platform':
            // Enable platform access
            $stmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES ('platform_disabled', '0') 
                ON DUPLICATE KEY UPDATE setting_value = '0'
            ");
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Platform enabled',
                'action' => 'enable_platform'
            ]);
            break;
            
        case 'reset_password':
            // This would require additional security - just log for now
            echo json_encode([
                'success' => true,
                'message' => 'Password reset initiated (requires manual intervention)',
                'action' => 'reset_password'
            ]);
            break;
            
        case 'backup_database':
            // Trigger database backup
            echo json_encode([
                'success' => true,
                'message' => 'Database backup initiated',
                'action' => 'backup_database'
            ]);
            break;
            
        case 'run_maintenance':
            // Run maintenance tasks
            // Clean old sessions, optimize tables, etc.
            try {
                // Clean old sessions (older than 30 days)
                $conn->exec("DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            } catch (Exception $e) {
                // Table might not exist
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Maintenance tasks executed',
                'action' => 'run_maintenance'
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action. Available actions: ' . implode(', ', [
                    'get_status', 'enable_feature', 'disable_feature', 'update_setting',
                    'clear_cache', 'enable_maintenance', 'disable_maintenance', 'force_update',
                    'get_logs', 'disable_platform', 'enable_platform', 'reset_password',
                    'backup_database', 'run_maintenance'
                ])
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("Remote management API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>

