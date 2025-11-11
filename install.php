<?php
// install.php
// Platform Installation Wizard with License Verification

// Start session with proper configuration
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters to ensure cookie is sent correctly
    // Calculate cookie path from script location
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '/install.php';
    $script_dir = dirname($script_name);
    
    // Normalize the path
    // If script is in root, use '/', otherwise use the directory path
    if ($script_dir === '/' || $script_dir === '\\' || $script_dir === '.') {
        $cookie_path = '/';
    } else {
        // Ensure path starts with / and ends with /
        $cookie_path = '/' . trim($script_dir, '/\\') . '/';
    }
    
    // Set cookie parameters using session_set_cookie_params (more reliable than ini_set)
    $cookie_secure = false;
    if (isset($_SERVER['HTTPS'])) {
        $cookie_secure = ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1');
    }
    
    // Use a more permissive cookie path - use root '/' for maximum compatibility
    // This ensures the cookie is sent with all requests to the domain
    session_set_cookie_params([
        'lifetime' => 7200, // 2 hours (increased from 1 hour)
        'path' => '/', // Use root path for maximum compatibility
        'domain' => '', // Empty means current domain
        'secure' => $cookie_secure, // true for HTTPS, false for HTTP
        'httponly' => true,
        'samesite' => 'Lax' // Helps with cross-site request protection
    ]);
    
    ini_set('session.use_only_cookies', 1);
    
    // Debug: Log cookie path for troubleshooting
    if (isset($_GET['step']) && ($_GET['step'] == 3 || $_GET['step'] == 4)) {
        error_log('Session Cookie Config: path=/, domain=, secure=' . ($cookie_secure ? 'true' : 'false') . ', script_dir=' . $script_dir);
    }
    
    session_start();
    
    // Debug: Log session restoration (only in debug mode or for step 4)
    if (isset($_GET['step']) && $_GET['step'] == 4) {
        error_log('Step 4: Session started. Session ID: ' . session_id());
        error_log('Step 4: Cookie path: ' . $cookie_path);
        error_log('Step 4: Session data - License: ' . (isset($_SESSION['install_license_data']) ? 'Yes' : 'No') . 
                  ', Site: ' . (isset($_SESSION['install_site_config']) ? 'Yes' : 'No') . 
                  ', DB: ' . (isset($_SESSION['install_db_config']) ? 'Yes' : 'No'));
    }
}

// Check if already installed
$config_file = __DIR__ . '/config/config.php';
if (file_exists($config_file)) {
    require_once $config_file;
    if (defined('SITE_INSTALLED') && SITE_INSTALLED === true) {
        die('
        <!DOCTYPE html>
        <html>
        <head>
            <title>Already Installed</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
                .box { background: white; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { color: #dc3545; }
                .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="box">
                <h1>Already Installed</h1>
                <p>This platform has already been installed.</p>
                <p>To reinstall, delete the <code>config/config.php</code> file or set <code>SITE_INSTALLED = false</code></p>
                <a href="index.php" class="btn">Go to Homepage</a>
            </div>
        </body>
        </html>
        ');
    }
}

// Initialize step from GET parameter, but also check session for step continuation
$step = (int)($_GET['step'] ?? 1);
$errors = [];
$success = [];

// Only validate session on GET requests (not POST, as POST handlers will process first)
// This prevents session validation from interfering with form submissions
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // If on step 2 or 3, verify session data exists, otherwise redirect to step 1
    if ($step === 2 && !isset($_SESSION['install_license_data'])) {
        $step = 1;
        $errors[] = 'Please complete Step 1 (License Verification) first.';
    }
    if ($step === 3 && (!isset($_SESSION['install_license_data']) || !isset($_SESSION['install_site_config']))) {
        $step = 1;
        $errors[] = 'Please complete Steps 1 and 2 first.';
    }
    // Validate step 4: require all previous session data
    if ($step === 4 && !isset($_GET['install'])) {
        // Only validate when NOT running installation (to avoid interfering with install process)
        if (!isset($_SESSION['install_license_data']) || !isset($_SESSION['install_site_config']) || !isset($_SESSION['install_db_config'])) {
            $step = 1;
            $errors[] = 'Installation session expired. Please complete Steps 1, 2, and 3 first.';
            $errors[] = 'Session Debug: License=' . (isset($_SESSION['install_license_data']) ? 'Yes' : 'No') . 
                        ', Site=' . (isset($_SESSION['install_site_config']) ? 'Yes' : 'No') . 
                        ', DB=' . (isset($_SESSION['install_db_config']) ? 'Yes' : 'No');
        }
    }
}

// License server configuration
// Auto-detect based on environment or use production defaults
$is_local = (
    $_SERVER['HTTP_HOST'] === 'localhost' || 
    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
    strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'], '.local') !== false
);

if ($is_local) {
    // Local development
    $license_server_url = 'http://localhost/license-server';
    $license_api_url = 'http://localhost/license-server/api/verify.php';
} else {
    // Production - use your license server domain
    $license_server_url = 'https://hylinktech.com/server';
    $license_api_url = 'https://hylinktech.com/server/api/verify.php';
}

// Step 1: License Verification
if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $license_key = trim($_POST['license_key'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    
    if (empty($license_key)) {
        $errors[] = 'License key is required';
    } elseif (empty($domain)) {
        $errors[] = 'Domain is required';
    } else {
        // Verify license with server
        $data = [
            'license_key' => $license_key,
            'domain' => $domain,
            'ip' => $_SERVER['SERVER_ADDR'] ?? ''
        ];
        
        $ch = curl_init($license_api_url);
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
            $errors[] = 'Failed to connect to license server: ' . $curl_error;
            $errors[] = 'Make sure license server is accessible at: ' . $license_server_url;
            $errors[] = 'API Endpoint: ' . $license_api_url;
        } elseif ($http_code !== 200) {
            $errors[] = 'License server returned error (HTTP ' . $http_code . ')';
            $errors[] = 'API Endpoint: ' . $license_api_url;
            if ($http_code === 404) {
                $errors[] = 'The API endpoint was not found. Please verify the license server is installed correctly.';
            }
            $errors[] = 'Please check your license key and domain, and ensure the license server is running.';
        } else {
            $result = json_decode($response, true);
            if ($result && isset($result['valid']) && $result['valid']) {
                // Store license info in session
                $_SESSION['install_license_key'] = $license_key;
                $_SESSION['install_domain'] = $domain;
                $_SESSION['install_license_data'] = $result;
                
                // Ensure session is saved before redirect (PHP saves automatically, but force it)
                session_write_close();
                
                // Redirect to step 2 to avoid form resubmission
                header('Location: ?step=2');
                exit;
            } else {
                $errors[] = $result['message'] ?? 'License verification failed';
                $errors[] = 'Please ensure your license key is correct and the domain matches.';
            }
        }
    }
}

// Step 2: Site Configuration
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // First check if license session exists - if not, try to re-verify with hidden fields
    if (!isset($_SESSION['install_license_data']) || !isset($_SESSION['install_license_key'])) {
        // Try to re-verify license from hidden fields
        $license_key = trim($_POST['license_key'] ?? '');
        $domain = trim($_POST['domain'] ?? '');
        
        if (!empty($license_key) && !empty($domain)) {
            // Re-verify license
            $data = [
                'license_key' => $license_key,
                'domain' => $domain,
                'ip' => $_SERVER['SERVER_ADDR'] ?? ''
            ];
            
            $ch = curl_init($license_api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            if ($result && isset($result['valid']) && $result['valid']) {
                // Restore session
                $_SESSION['install_license_key'] = $license_key;
                $_SESSION['install_domain'] = $domain;
                $_SESSION['install_license_data'] = $result;
            } else {
                $errors[] = 'License session expired. Please start over from Step 1.';
                $step = 1; // Force back to step 1
            }
        } else {
            $errors[] = 'License session expired. Please start over from Step 1.';
            $step = 1; // Force back to step 1
        }
    }
    
    // If we have valid session now, process the form
    if (isset($_SESSION['install_license_data']) && isset($_SESSION['install_license_key'])) {
        $site_name = trim($_POST['site_name'] ?? '');
        $site_slogan = trim($_POST['site_slogan'] ?? '');
        $site_description = trim($_POST['site_description'] ?? '');
        $admin_username = trim($_POST['admin_username'] ?? '');
        $admin_email = trim($_POST['admin_email'] ?? '');
        $admin_password = $_POST['admin_password'] ?? '';
        $admin_confirm_password = $_POST['admin_confirm_password'] ?? '';
        
        // Validate
        if (empty($site_name)) $errors[] = 'Site name is required';
        if (empty($admin_username)) $errors[] = 'Admin username is required';
        if (empty($admin_email) || !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid admin email is required';
        if (empty($admin_password)) $errors[] = 'Admin password is required';
        if ($admin_password !== $admin_confirm_password) $errors[] = 'Passwords do not match';
        if (strlen($admin_password) < 8) $errors[] = 'Password must be at least 8 characters';
        
        // Verify license details match
        $license_data = $_SESSION['install_license_data'];
        
        if (empty($errors)) {
            $_SESSION['install_site_config'] = [
                'site_name' => $site_name,
                'site_slogan' => $site_slogan,
                'site_description' => $site_description,
                'admin_username' => $admin_username,
                'admin_email' => $admin_email,
                'admin_password' => $admin_password
            ];
            
            // Ensure session is saved before redirect
            session_write_close();
            
            // Redirect to step 3 to avoid form resubmission
            header('Location: ?step=3');
            exit;
        } else {
            // Keep step at 2 if there are validation errors
            $step = 2;
        }
    }
}

// Step 3: Database Configuration
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Check session status
    $session_id = session_id();
    $session_status = session_status();
    $has_license = isset($_SESSION['install_license_data']);
    $has_site_config = isset($_SESSION['install_site_config']);
    
    // Check if previous steps data exists
    if (!$has_license || !$has_site_config) {
        $errors[] = 'Installation session expired. Please start over from Step 1.';
        $errors[] = 'Session ID: ' . ($session_id ?: 'Not set');
        $errors[] = 'Session Status: ' . ($session_status === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive');
        $errors[] = 'License data present: ' . ($has_license ? 'Yes' : 'No');
        $errors[] = 'Site config present: ' . ($has_site_config ? 'Yes' : 'No');
        
        // Try to restore from step 2 if we have hidden fields (for debugging)
        if (isset($_POST['license_key']) && isset($_POST['domain'])) {
            $errors[] = 'Note: Hidden fields detected. Session may not be persisting between pages.';
        }
        
        $step = 1;
    } else {
        $db_host = trim($_POST['db_host'] ?? 'localhost');
        $db_name = trim($_POST['db_name'] ?? '');
        $db_user = trim($_POST['db_user'] ?? 'root');
        $db_pass = $_POST['db_pass'] ?? '';
        
        if (empty($db_name)) {
            $errors[] = 'Database name is required';
            $step = 3; // Stay on step 3
        } else {
            // Test database connection
            try {
                $test_conn = new PDO(
                    "mysql:host=$db_host;charset=utf8mb4",
                    $db_user,
                    $db_pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 5
                    ]
                );
                
                // Create database if it doesn't exist
                $test_conn->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                // Store database config in session
                $_SESSION['install_db_config'] = [
                    'db_host' => $db_host,
                    'db_name' => $db_name,
                    'db_user' => $db_user,
                    'db_pass' => $db_pass
                ];
                
                // Debug: Log session data
                error_log('Step 3: Session data stored. Session ID: ' . session_id());
                error_log('Step 3: DB config stored: ' . (isset($_SESSION['install_db_config']) ? 'Yes' : 'No'));
                error_log('Step 3: Cookie path: ' . ini_get('session.cookie_path'));
                
                // IMPORTANT: Don't close the session here - let PHP save it automatically
                // Closing the session manually can cause cookie issues on some servers
                // PHP will automatically save the session when the script ends
                
                // Redirect to step 4 to avoid form resubmission
                // Use relative URL for simplicity and reliability
                header('Location: ?step=4', true, 302);
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Database connection failed: ' . $e->getMessage();
                $errors[] = 'Please verify your database credentials and ensure the database server is running.';
                $step = 3; // Stay on step 3 - session remains active for error display
            }
        }
    }
}

// Step 4: Run Installation
if ($step === 4 && isset($_GET['install'])) {
    // CRITICAL: Ensure session is active before checking session data
    if (session_status() === PHP_SESSION_NONE) {
        // Session wasn't started - try to start it
        session_start();
        error_log('Step 4 Install: Session was not active, restarted it. Session ID: ' . session_id());
    }
    
    // Debug: Log session status and data immediately
    $session_status = session_status();
    $session_id = session_id();
    $has_license = isset($_SESSION['install_license_data']);
    $has_site = isset($_SESSION['install_site_config']);
    $has_db = isset($_SESSION['install_db_config']);
    
    error_log('Step 4 Install: Session Status=' . ($session_status === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . 
              ', Session ID=' . ($session_id ?: 'None') . 
              ', License=' . ($has_license ? 'Yes' : 'No') . 
              ', Site=' . ($has_site ? 'Yes' : 'No') . 
              ', DB=' . ($has_db ? 'Yes' : 'No'));
    
    // Increase execution time and memory limit for installation
    @set_time_limit(300); // 5 minutes
    @ini_set('max_execution_time', '300');
    @ini_set('memory_limit', '256M');
    
    // Enable error reporting for installation
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Don't display, but log
    ini_set('log_errors', 1);
    
    // Register shutdown function to catch fatal errors
    // Store error info in session so we can display it after redirect
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $error_msg = 'Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'];
            error_log('Installation fatal shutdown error: ' . $error_msg);
            // Store in session for display
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['install_fatal_error'] = [
                    'message' => $error['message'],
                    'file' => $error['file'],
                    'line' => $error['line']
                ];
            }
        }
    });
    
    // Start output buffering to catch any errors
    ob_start();
    
    // Check for fatal error from previous run
    if (isset($_SESSION['install_fatal_error'])) {
        $fatal_error = $_SESSION['install_fatal_error'];
        unset($_SESSION['install_fatal_error']);
        ob_end_clean();
        $errors[] = 'Fatal error: ' . $fatal_error['message'];
        $errors[] = 'File: ' . $fatal_error['file'] . ' Line: ' . $fatal_error['line'];
        $step = 4;
    }
    
    // Check PHP requirements before starting
    $php_errors = [];
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        $php_errors[] = 'PHP 7.4 or higher is required. You are running PHP ' . PHP_VERSION;
    }
    if (!extension_loaded('pdo')) {
        $php_errors[] = 'PDO extension is required but not installed.';
    }
    if (!extension_loaded('pdo_mysql')) {
        $php_errors[] = 'PDO MySQL extension is required but not installed.';
    }
    if (!function_exists('password_hash')) {
        $php_errors[] = 'password_hash function is required (PHP 5.5+).';
    }
    
    if (!empty($php_errors)) {
        ob_end_clean();
        $errors = array_merge($errors, $php_errors);
        $step = 4;
    } elseif (!$has_license || !$has_site || !$has_db) {
        ob_end_clean();
        $errors[] = 'Installation session expired. Please start over from Step 1.';
        $errors[] = 'Session Debug Information:';
        $errors[] = 'Session Status: ' . ($session_status === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive');
        $errors[] = 'Session ID: ' . ($session_id ?: 'Not set');
        $errors[] = 'License data present: ' . ($has_license ? 'Yes' : 'No');
        $errors[] = 'Site config present: ' . ($has_site ? 'Yes' : 'No');
        $errors[] = 'Database config present: ' . ($has_db ? 'Yes' : 'No');
        $errors[] = 'Cookie Path: ' . ini_get('session.cookie_path');
        $errors[] = 'Cookie Domain: ' . ini_get('session.cookie_domain');
        $errors[] = 'Current URL: ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'Unknown');
        $errors[] = 'Script Name: ' . (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'Unknown');
        $step = 1;
    } else {
        try {
            // Check if install-database.php exists
            $install_file = __DIR__ . '/install/install-database.php';
            if (!file_exists($install_file)) {
                throw new Exception('Installation file not found: ' . $install_file);
            }
            
            // Run installation with error handling
            require_once $install_file;
            
            // Verify function exists
            if (!function_exists('runInstallation')) {
                throw new Exception('runInstallation function not found in install-database.php');
            }
            
            // Verify function exists
            if (!function_exists('createConfigFile')) {
                throw new Exception('createConfigFile function not found in install-database.php');
            }
            
            // Capture any output/errors before running installation
            $pre_install_output = ob_get_contents();
            if (!empty($pre_install_output)) {
                error_log('Unexpected output before installation: ' . $pre_install_output);
                ob_clean();
            }
            
            // Run installation
            $install_result = runInstallation(
                $_SESSION['install_db_config'], 
                $_SESSION['install_site_config'], 
                $_SESSION['install_license_data']
            );
            
            // Capture any output during installation
            $install_output = ob_get_contents();
            if (!empty($install_output) && !empty(trim($install_output))) {
                error_log('Unexpected output during installation: ' . $install_output);
                // Don't clean it yet, we might need to see it
            }
            
            if (!$install_result || !is_array($install_result)) {
                throw new Exception('Installation function returned invalid result. Output: ' . substr($install_output, 0, 200));
            }
            
            if ($install_result['success']) {
                // Pass session data to global for config file creation
                $GLOBALS['install_license_key'] = $_SESSION['install_license_key'] ?? '';
                $GLOBALS['install_domain'] = $_SESSION['install_domain'] ?? '';
                $GLOBALS['install_license_server_url'] = $license_server_url;
                
                // Check if config directory is writable
                $config_dir = __DIR__ . '/config';
                if (!is_dir($config_dir)) {
                    if (!mkdir($config_dir, 0755, true)) {
                        throw new Exception('Cannot create config directory. Please check file permissions.');
                    }
                }
                
                if (!is_writable($config_dir)) {
                    throw new Exception('Config directory is not writable. Please set permissions to 755 or 775.');
                }
                
                // Create config file
                try {
                    createConfigFile(
                        $_SESSION['install_db_config'], 
                        $_SESSION['install_site_config'], 
                        $_SESSION['install_license_data'], 
                        $license_server_url, 
                        $_SESSION['install_license_key'] ?? ''
                    );
                    
                    // Verify config file was created
                    $config_file = $config_dir . '/config.php';
                    if (!file_exists($config_file)) {
                        throw new Exception('Config file was not created. Please check file permissions.');
                    }
                    
                    // Clear output buffer
                    ob_end_clean();
                    
                    $step = 5; // Installation complete
                    session_destroy();
                } catch (Exception $e) {
                    ob_end_clean();
                    $errors[] = 'Error creating config file: ' . $e->getMessage();
                    error_log('Config file creation error: ' . $e->getMessage());
                }
            } else {
                ob_end_clean();
                $errors = $install_result['errors'] ?? ['Installation failed'];
                if (empty($errors)) {
                    $errors[] = 'Installation failed with no error message. Please check error logs.';
                }
                error_log('Installation failed: ' . implode(', ', $errors));
            }
        } catch (PDOException $e) {
            ob_end_clean();
            $errors[] = 'Database error: ' . $e->getMessage();
            $errors[] = 'Please check your database credentials and ensure the database server is running.';
            error_log('Installation PDO exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        } catch (Exception $e) {
            ob_end_clean();
            $errors[] = 'Installation error: ' . $e->getMessage();
            $errors[] = 'File: ' . $e->getFile() . ' Line: ' . $e->getLine();
            error_log('Installation exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        } catch (Error $e) {
            ob_end_clean();
            $errors[] = 'Fatal error: ' . $e->getMessage();
            $errors[] = 'File: ' . $e->getFile() . ' Line: ' . $e->getLine();
            $errors[] = 'This is usually caused by a PHP syntax error or missing dependency.';
            error_log('Installation fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        } catch (Throwable $e) {
            ob_end_clean();
            $errors[] = 'Unexpected error: ' . $e->getMessage();
            $errors[] = 'File: ' . $e->getFile() . ' Line: ' . $e->getLine();
            error_log('Installation throwable error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        } finally {
            // Ensure output buffer is cleaned if not already
            if (ob_get_level() > 0) {
                $remaining_output = ob_get_contents();
                if (!empty($remaining_output) && !empty(trim($remaining_output))) {
                    error_log('Remaining output buffer content: ' . $remaining_output);
                }
                @ob_end_clean();
            }
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Platform Installation</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .install-container { max-width: 800px; margin: 0 auto; }
        .install-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        h1 { color: #1f2937; margin-bottom: 10px; }
        .step-indicator { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .step { flex: 1; text-align: center; padding: 10px; }
        .step.active { color: #3b82f6; font-weight: 600; }
        .step.completed { color: #10b981; }
        .step-number { width: 30px; height: 30px; border-radius: 50%; background: #e5e7eb; color: #6b7280; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 5px; }
        .step.active .step-number { background: #3b82f6; color: white; }
        .step.completed .step-number { background: #10b981; color: white; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #374151; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #3b82f6; }
        .btn { padding: 12px 24px; background: #3b82f6; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; width: 100%; }
        .btn:hover { background: #2563eb; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .info-box { background: #dbeafe; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .license-info { background: #f3f4f6; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-box">
            <h1><i class="fas fa-rocket"></i> Platform Installation</h1>
            
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">
                    <div class="step-number">1</div>
                    <div>License</div>
                </div>
                <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">
                    <div class="step-number">2</div>
                    <div>Site Config</div>
                </div>
                <div class="step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">
                    <div class="step-number">3</div>
                    <div>Database</div>
                </div>
                <div class="step <?php echo $step >= 4 ? ($step > 4 ? 'completed' : 'active') : ''; ?>">
                    <div class="step-number">4</div>
                    <div>Install</div>
                </div>
                <div class="step <?php echo $step >= 5 ? 'completed' : ''; ?>">
                    <div class="step-number">✓</div>
                    <div>Complete</div>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
            <!-- Step 1: License Verification -->
            <h2>Step 1: License Verification</h2>
            <div class="info-box">
                <strong>License Server:</strong> <?php echo htmlspecialchars($license_server_url); ?><br>
                Please enter your license key to verify and begin installation.
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>License Key *</label>
                    <input type="text" name="license_key" required 
                        placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" 
                        style="font-family: monospace; letter-spacing: 2px;">
                </div>
                <div class="form-group">
                    <label>Domain *</label>
                    <input type="text" name="domain" required 
                        value="<?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? ''); ?>" 
                        placeholder="example.com">
                    <small style="color: #666; display: block; margin-top: 5px;">Domain where this platform will be installed</small>
                </div>
                <button type="submit" class="btn">Verify License & Continue</button>
            </form>
            
            <?php elseif ($step === 2): ?>
            <!-- Step 2: Site Configuration -->
            <h2>Step 2: Site Configuration</h2>
            <?php if (isset($_SESSION['install_license_data']) && isset($_SESSION['install_license_key'])): 
                $license_data = $_SESSION['install_license_data'];
            ?>
            <div class="license-info">
                <strong>License Verified Successfully!</strong><br>
                <strong>License Key:</strong> <?php echo htmlspecialchars($_SESSION['install_license_key']); ?><br>
                <strong>License Type:</strong> <?php echo htmlspecialchars(ucfirst($license_data['license']['type'] ?? 'Lifetime')); ?><br>
                <strong>Domain:</strong> <?php echo htmlspecialchars($_SESSION['install_domain'] ?? ''); ?><br>
                <strong>Status:</strong> <span style="color: #10b981;">Lifetime License</span><br>
            </div>
            
            <div class="info-box">
                <strong>License Information:</strong><br>
                Please fill in the site details below. These should match your license information.
            </div>
            <?php else: ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> License session expired. Please <a href="?step=1" style="color: #991b1b; text-decoration: underline;">go back to Step 1</a> to verify your license.
            </div>
            <?php endif; ?>
            
            <form method="POST" action="?step=2">
                <!-- Preserve license key in hidden field -->
                <input type="hidden" name="license_key" value="<?php echo htmlspecialchars($_SESSION['install_license_key'] ?? ''); ?>">
                <input type="hidden" name="domain" value="<?php echo htmlspecialchars($_SESSION['install_domain'] ?? ''); ?>">
                
                <div class="form-group">
                    <label>Site Name *</label>
                    <input type="text" name="site_name" required 
                        value="<?php echo htmlspecialchars($_POST['site_name'] ?? ($_SESSION['install_site_config']['site_name'] ?? '')); ?>"
                        placeholder="Your Site Name">
                </div>
                <div class="form-group">
                    <label>Site Slogan</label>
                    <input type="text" name="site_slogan" 
                        value="<?php echo htmlspecialchars($_POST['site_slogan'] ?? ($_SESSION['install_site_config']['site_slogan'] ?? '')); ?>"
                        placeholder="Your catchy slogan">
                </div>
                <div class="form-group">
                    <label>Site Description</label>
                    <textarea name="site_description" rows="3" 
                        placeholder="Brief description of your platform"><?php echo htmlspecialchars($_POST['site_description'] ?? ($_SESSION['install_site_config']['site_description'] ?? '')); ?></textarea>
                </div>
                
                <h3 style="margin-top: 30px; margin-bottom: 15px;">Admin Account</h3>
                <div class="form-group">
                    <label>Admin Username *</label>
                    <input type="text" name="admin_username" required 
                        value="<?php echo htmlspecialchars($_POST['admin_username'] ?? ($_SESSION['install_site_config']['admin_username'] ?? '')); ?>"
                        placeholder="admin">
                </div>
                <div class="form-group">
                    <label>Admin Email *</label>
                    <input type="email" name="admin_email" required 
                        value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ($_SESSION['install_site_config']['admin_email'] ?? '')); ?>"
                        placeholder="admin@example.com">
                </div>
                <div class="form-group">
                    <label>Admin Password *</label>
                    <input type="password" name="admin_password" required minlength="8"
                        placeholder="Minimum 8 characters" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Confirm Password *</label>
                    <input type="password" name="admin_confirm_password" required minlength="8"
                        placeholder="Re-enter password" autocomplete="new-password">
                </div>
                
                <button type="submit" class="btn">Continue to Database Setup</button>
            </form>
            
            <?php elseif ($step === 3): ?>
            <!-- Step 3: Database Configuration -->
            <h2>Step 3: Database Configuration</h2>
            
            <?php if (!isset($_SESSION['install_license_data']) || !isset($_SESSION['install_site_config'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> Installation session expired. Please <a href="?step=1" style="color: #991b1b; text-decoration: underline;">start over from Step 1</a>.
            </div>
            <?php else: ?>
            
            <form method="POST" action="?step=3">
                <!-- Preserve session data in hidden fields as backup -->
                <input type="hidden" name="session_check" value="<?php echo htmlspecialchars(session_id()); ?>">
                <?php if (isset($_SESSION['install_license_key'])): ?>
                <input type="hidden" name="license_key_backup" value="<?php echo htmlspecialchars($_SESSION['install_license_key']); ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Database Host *</label>
                    <input type="text" name="db_host" required 
                        value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>">
                </div>
                <div class="form-group">
                    <label>Database Name *</label>
                    <input type="text" name="db_name" required 
                        value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>"
                        placeholder="music_streaming">
                </div>
                <div class="form-group">
                    <label>Database Username *</label>
                    <input type="text" name="db_user" required 
                        value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>">
                </div>
                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="db_pass" 
                        value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>"
                        placeholder="Leave empty if no password">
                </div>
                
                <button type="submit" class="btn">Test Connection & Continue</button>
            </form>
            <?php endif; ?>
            
            <?php elseif ($step === 4): ?>
            <!-- Step 4: Installation in Progress or Error -->
            <?php if (!empty($errors)): ?>
                <!-- Show errors if installation failed -->
                <h2>Step 4: Installation Error</h2>
                <div class="alert alert-error">
                    <h3><i class="fas fa-exclamation-triangle"></i> Installation Failed</h3>
                    <p>The installation encountered errors. Please review the errors below and try again.</p>
                </div>
                <div style="background: #fee2e2; padding: 15px; border-radius: 6px; margin: 20px 0;">
                    <h4>Error Details:</h4>
                    <ul style="margin-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div style="background: #fff3cd; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #ffc107;">
                    <h4><i class="fas fa-info-circle"></i> Troubleshooting Tips:</h4>
                    <ul style="margin-left: 20px;">
                        <li>Check that your database credentials are correct</li>
                        <li>Ensure your database user has CREATE, INSERT, UPDATE, and DELETE permissions</li>
                        <li>Verify that PHP version is 7.4 or higher: <strong><?php echo PHP_VERSION; ?></strong></li>
                        <li>Check that PDO and PDO_MySQL extensions are installed</li>
                        <li>Ensure the <code>config</code> directory is writable (permissions 755 or 775)</li>
                        <li>Check your server's PHP error logs for more details</li>
                        <li>If the error mentions "memory" or "timeout", contact your hosting provider to increase limits</li>
                    </ul>
                </div>
                <div style="margin-top: 20px;">
                    <a href="?step=3" class="btn" style="text-decoration: none; display: inline-block; background: #6b7280;">
                        <i class="fas fa-arrow-left"></i> Go Back to Database Setup
                    </a>
                    <a href="?step=1" class="btn" style="text-decoration: none; display: inline-block; background: #dc3545; margin-left: 10px;">
                        <i class="fas fa-redo"></i> Start Over
                    </a>
                </div>
            <?php elseif (isset($_GET['install'])): ?>
                <!-- Installation is running -->
                <h2>Step 4: Installing...</h2>
                <p>Please wait while we install the platform. This may take a few moments...</p>
                <div style="text-align: center; padding: 20px;">
                    <div style="display: inline-block; width: 50px; height: 50px; border: 4px solid #f3f3f3; border-top: 4px solid #3b82f6; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                </div>
                <style>
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                </style>
            <?php else: ?>
                <!-- Ready to install - show install button -->
                <h2>Step 4: Ready to Install</h2>
                <div class="info-box">
                    <strong>Installation Summary:</strong><br>
                    <strong>Site Name:</strong> <?php echo htmlspecialchars($_SESSION['install_site_config']['site_name'] ?? ''); ?><br>
                    <strong>Database:</strong> <?php echo htmlspecialchars($_SESSION['install_db_config']['db_name'] ?? ''); ?><br>
                    <strong>Admin Email:</strong> <?php echo htmlspecialchars($_SESSION['install_site_config']['admin_email'] ?? ''); ?><br>
                </div>
                <p>Click the button below to start the installation process.</p>
                <?php 
                // Verify session data is present before showing the form
                $session_ok = isset($_SESSION['install_license_data']) && 
                             isset($_SESSION['install_site_config']) && 
                             isset($_SESSION['install_db_config']);
                if (!$session_ok): ?>
                    <div class="alert alert-error" style="margin: 20px 0;">
                        <strong>Warning:</strong> Session data appears to be missing. This may cause the installation to fail.
                        <br>Session ID: <?php echo htmlspecialchars(session_id() ?: 'Not set'); ?>
                        <br>Please try refreshing this page or go back to Step 3.
                    </div>
                <?php endif; ?>
                <form method="GET" action="install.php" id="installForm">
                    <input type="hidden" name="step" value="4">
                    <input type="hidden" name="install" value="1">
                    <input type="hidden" name="session_id" value="<?php echo htmlspecialchars(session_id()); ?>">
                    <button type="submit" class="btn" id="installBtn">
                        <i class="fas fa-rocket"></i> Start Installation
                    </button>
                </form>
                <script>
                    // Verify session cookie exists before form submission
                    document.getElementById('installForm').addEventListener('submit', function(e) {
                        var sessionId = document.querySelector('input[name="session_id"]').value;
                        if (!sessionId || sessionId === '') {
                            alert('Session ID is missing. Please refresh the page and try again.');
                            e.preventDefault();
                            return false;
                        }
                        console.log('Starting installation with session ID: ' + sessionId);
                        return true;
                    });
                </script>
            <?php endif; ?>
            
            <?php elseif ($step === 5): ?>
            <!-- Step 5: Installation Complete -->
            <div class="alert alert-success">
                <h2><i class="fas fa-check-circle"></i> Installation Complete!</h2>
                <p style="margin-top: 15px;">Your platform has been successfully installed.</p>
            </div>
            
            <div style="margin-top: 30px;">
                <a href="admin/login.php" class="btn" style="text-decoration: none; display: inline-block; text-align: center;">
                    <i class="fas fa-sign-in-alt"></i> Go to Admin Panel
                </a>
                <a href="index.php" class="btn" style="text-decoration: none; display: inline-block; text-align: center; background: #6b7280; margin-top: 10px;">
                    <i class="fas fa-home"></i> Go to Homepage
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
