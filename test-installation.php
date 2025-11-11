<?php
// test-installation.php
// Quick test script to verify installation setup

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Installation Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .test-box { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        h2 { margin-top: 0; }
        ul { margin: 10px 0; padding-left: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>🧪 Installation Test</h1>
    
    <?php
    $tests = [];
    $all_passed = true;
    
    // Test 1: Check if config exists
    $config_file = __DIR__ . '/config/config.php';
    if (file_exists($config_file)) {
        require_once $config_file;
        $tests[] = ['name' => 'Config file exists', 'status' => 'success'];
    } else {
        $tests[] = ['name' => 'Config file exists', 'status' => 'error', 'message' => 'Config file not found - ready for installation'];
        $all_passed = false;
    }
    
    // Test 2: Check if installed
    if (defined('SITE_INSTALLED')) {
        if (SITE_INSTALLED === true) {
            $tests[] = ['name' => 'Site installation status', 'status' => 'success', 'message' => 'Site is already installed'];
        } else {
            $tests[] = ['name' => 'Site installation status', 'status' => 'warning', 'message' => 'Site marked as not installed - ready for installation'];
        }
    } else {
        $tests[] = ['name' => 'Site installation status', 'status' => 'warning', 'message' => 'SITE_INSTALLED not defined - ready for installation'];
    }
    
    // Test 3: Check database connection
    if (file_exists($config_file)) {
        try {
            require_once __DIR__ . '/config/database.php';
            $db = new Database();
            $conn = $db->getConnection();
            if ($conn) {
                $tests[] = ['name' => 'Database connection', 'status' => 'success'];
            } else {
                $tests[] = ['name' => 'Database connection', 'status' => 'error', 'message' => 'Could not connect to database'];
                $all_passed = false;
            }
        } catch (Exception $e) {
            $tests[] = ['name' => 'Database connection', 'status' => 'error', 'message' => $e->getMessage()];
            $all_passed = false;
        }
    } else {
        $tests[] = ['name' => 'Database connection', 'status' => 'warning', 'message' => 'Cannot test - config file missing'];
    }
    
    // Test 4: Check license server accessibility
    $license_server_url = 'https://hylinktech.com/server';
    $license_api_url = 'https://hylinktech.com/api/verify.php';
    
    $ch = curl_init($license_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code > 0 && $http_code < 500) {
        $tests[] = ['name' => 'License server accessibility', 'status' => 'success', 'message' => "License server reachable (HTTP $http_code)"];
    } else {
        $tests[] = ['name' => 'License server accessibility', 'status' => 'warning', 'message' => "License server may not be accessible: $curl_error (HTTP $http_code). For local testing, update install.php to use local license server."];
    }
    
    // Test 5: Check installation files
    $install_file = __DIR__ . '/install.php';
    $install_db = __DIR__ . '/install/install-database.php';
    $schema_file = __DIR__ . '/database/schema.sql';
    
    if (file_exists($install_file)) {
        $tests[] = ['name' => 'Installation wizard exists', 'status' => 'success'];
    } else {
        $tests[] = ['name' => 'Installation wizard exists', 'status' => 'error'];
        $all_passed = false;
    }
    
    if (file_exists($install_db)) {
        $tests[] = ['name' => 'Installation database script exists', 'status' => 'success'];
    } else {
        $tests[] = ['name' => 'Installation database script exists', 'status' => 'error'];
        $all_passed = false;
    }
    
    if (file_exists($schema_file)) {
        $tests[] = ['name' => 'Database schema file exists', 'status' => 'success'];
    } else {
        $tests[] = ['name' => 'Database schema file exists', 'status' => 'error'];
        $all_passed = false;
    }
    
    // Test 6: Check settings manager
    $settings_file = __DIR__ . '/includes/settings.php';
    if (file_exists($settings_file)) {
        $tests[] = ['name' => 'Settings manager exists', 'status' => 'success'];
    } else {
        $tests[] = ['name' => 'Settings manager exists', 'status' => 'error'];
        $all_passed = false;
    }
    
    // Test 7: Check admin settings page
    $admin_settings = __DIR__ . '/admin/settings-general.php';
    if (file_exists($admin_settings)) {
        $tests[] = ['name' => 'Admin settings page exists', 'status' => 'success'];
    } else {
        $tests[] = ['name' => 'Admin settings page exists', 'status' => 'error'];
        $all_passed = false;
    }
    
    // Display results
    foreach ($tests as $test) {
        $status_class = $test['status'];
        $icon = $test['status'] === 'success' ? '✅' : ($test['status'] === 'error' ? '❌' : '⚠️');
        echo "<div class='test-box'>";
        echo "<h2 class='$status_class'>$icon {$test['name']}</h2>";
        if (isset($test['message'])) {
            echo "<p>{$test['message']}</p>";
        }
        echo "</div>";
    }
    
    // Summary
    echo "<div class='test-box' style='background: " . ($all_passed ? '#d4edda' : '#fff3cd') . "'>";
    echo "<h2>" . ($all_passed ? '✅ All Tests Passed' : '⚠️ Some Tests Need Attention') . "</h2>";
    
    if ($all_passed || !defined('SITE_INSTALLED') || SITE_INSTALLED === false) {
        echo "<p>You're ready to start the installation process!</p>";
        echo "<a href='install.php' class='btn'>Start Installation</a>";
    } else {
        echo "<p>Site is already installed. To reinstall, delete config/config.php or set SITE_INSTALLED = false</p>";
        echo "<a href='admin/login.php' class='btn'>Go to Admin Panel</a>";
    }
    echo "</div>";
    ?>
    
    <div class="test-box">
        <h2>📋 Quick Test Steps</h2>
        <ol>
            <li>Ensure XAMPP/WAMP is running (Apache + MySQL)</li>
            <li>Ensure license server is accessible (or update URL for local testing)</li>
            <li>Have a test license key ready</li>
            <li>Click "Start Installation" above</li>
            <li>Follow the installation wizard</li>
            <li>Verify all steps complete successfully</li>
        </ol>
    </div>
    
    <div class="test-box">
        <h2>🔧 For Local Testing</h2>
        <p>If testing with local license server, update these in <code>install.php</code>:</p>
        <pre style="background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;">
$license_server_url = 'http://localhost/license-server';
$license_api_url = 'http://localhost/license-server/api/verify.php';
        </pre>
    </div>
</body>
</html>


