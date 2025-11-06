<?php
// install.php
// Platform Installation Wizard with License Verification

// Start session with proper configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
    session_start();
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

// If on step 2 or 3, verify session data exists, otherwise redirect to step 1
if ($step === 2 && !isset($_SESSION['install_license_data'])) {
    $step = 1;
    $errors[] = 'Please complete Step 1 (License Verification) first.';
}
if ($step === 3 && (!isset($_SESSION['install_license_data']) || !isset($_SESSION['install_site_config']))) {
    $step = 1;
    $errors[] = 'Please complete Steps 1 and 2 first.';
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
                $step = 2; // Move to next step
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
            $step = 3; // Move to database setup
        } else {
            // Keep step at 2 if there are validation errors
            $step = 2;
        }
    }
}

// Step 3: Database Configuration
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if previous steps data exists
    if (!isset($_SESSION['install_license_data']) || !isset($_SESSION['install_site_config'])) {
        $errors[] = 'Installation session expired. Please start over from Step 1.';
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
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                
                // Create database if it doesn't exist
                $test_conn->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                $_SESSION['install_db_config'] = [
                    'db_host' => $db_host,
                    'db_name' => $db_name,
                    'db_user' => $db_user,
                    'db_pass' => $db_pass
                ];
                $step = 4; // Move to installation
            } catch (PDOException $e) {
                $errors[] = 'Database connection failed: ' . $e->getMessage();
                $step = 3; // Stay on step 3
            }
        }
    }
}

// Step 4: Run Installation
if ($step === 4 && isset($_GET['install'])) {
    if (!isset($_SESSION['install_license_data']) || !isset($_SESSION['install_site_config']) || !isset($_SESSION['install_db_config'])) {
        $errors[] = 'Installation session expired. Please start over.';
        $step = 1;
    } else {
        // Run installation
        require_once __DIR__ . '/install/install-database.php';
        $install_result = runInstallation($_SESSION['install_db_config'], $_SESSION['install_site_config'], $_SESSION['install_license_data']);
        
        if ($install_result['success']) {
            // Pass session data to global for config file creation
            $GLOBALS['install_license_key'] = $_SESSION['install_license_key'];
            $GLOBALS['install_domain'] = $_SESSION['install_domain'];
            
            // Create config file
            createConfigFile($_SESSION['install_db_config'], $_SESSION['install_site_config'], $_SESSION['install_license_data'], $license_server_url, $_SESSION['install_license_key']);
            $step = 5; // Installation complete
            session_destroy();
        } else {
            $errors = $install_result['errors'] ?? ['Installation failed'];
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
            <!-- Step 4: Installation in Progress -->
            <h2>Step 4: Installing...</h2>
            <p>Please wait while we install the platform...</p>
            <div style="text-align: center; padding: 20px;">
                <div style="display: inline-block; width: 50px; height: 50px; border: 4px solid #f3f3f3; border-top: 4px solid #3b82f6; border-radius: 50%; animation: spin 1s linear infinite;"></div>
            </div>
            <style>
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
            <script>
                // Auto-submit to run installation
                window.onload = function() {
                    setTimeout(function() {
                        window.location.href = '?step=4&install=1';
                    }, 1000);
                };
            </script>
            
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
