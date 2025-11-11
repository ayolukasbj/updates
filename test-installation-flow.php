<?php
// test-installation-flow.php
// Interactive test script for installation flow

session_start();

$step = (int)($_GET['step'] ?? 1);
$license_server_url = 'http://localhost/license-server';
$license_api_url = 'http://localhost/license-server/api/verify.php';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Installation Flow Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .test-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #17a2b8; }
        .btn { padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px; }
        .btn:hover { background: #0056b3; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        input, select { padding: 8px; margin: 5px 0; width: 300px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="test-box">
        <h1>🧪 Installation Flow Test</h1>
        <p>This script tests the installation flow step by step.</p>
    </div>

    <?php if ($step === 1): ?>
    <!-- Step 1: License Verification Test -->
    <div class="test-box">
        <h2>Step 1: License Verification Test</h2>
        <form method="GET">
            <input type="hidden" name="step" value="2">
            <label>License Key:</label><br>
            <input type="text" name="license_key" placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" required><br>
            <label>Domain:</label><br>
            <input type="text" name="domain" value="localhost" required><br><br>
            <button type="submit">Test License Verification</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($step === 2): 
        $license_key = $_GET['license_key'] ?? '';
        $domain = $_GET['domain'] ?? 'localhost';
    ?>
    <div class="test-box">
        <h2>Step 2: License Verification Result</h2>
        
        <?php
        $data = [
            'license_key' => $license_key,
            'domain' => $domain,
            'ip' => '127.0.0.1'
        ];
        
        $ch = curl_init($license_api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            echo "<p class='error'>❌ Connection Error: $curl_error</p>";
        } elseif ($http_code !== 200) {
            echo "<p class='error'>❌ HTTP Error: $http_code</p>";
        } else {
            $result = json_decode($response, true);
            if ($result && isset($result['valid']) && $result['valid']) {
                echo "<p class='success'>✅ License Verification: SUCCESS</p>";
                echo "<pre>";
                print_r($result);
                echo "</pre>";
                echo "<p class='info'>✅ License is valid! You can proceed with installation.</p>";
                echo "<a href='install.php?license_key=$license_key&domain=$domain' class='btn'>Proceed to Installation</a>";
            } else {
                echo "<p class='error'>❌ License Verification: FAILED</p>";
                echo "<pre>";
                print_r($result);
                echo "</pre>";
                echo "<p class='error'>License is invalid. Please check your license key and domain.</p>";
            }
        }
        ?>
        
        <a href="test-installation-flow.php" class="btn">Test Again</a>
    </div>
    <?php endif; ?>

    <div class="test-box">
        <h2>Quick Links</h2>
        <a href="test-installation.php" class="btn">System Check</a>
        <a href="install.php" class="btn">Full Installation</a>
        <a href="admin/login.php" class="btn">Admin Login</a>
        <a href="admin/check-updates.php" class="btn">Check Updates</a>
    </div>

    <div class="test-box">
        <h2>License Server Links</h2>
        <a href="../license-server/index.php" class="btn" target="_blank">License Server Dashboard</a>
        <a href="../license-server/create-license.php" class="btn" target="_blank">Create License</a>
        <a href="../license-server/updates.php" class="btn" target="_blank">Manage Updates</a>
        <a href="../license-server/test-license-api.php" class="btn" target="_blank">Test License API</a>
        <a href="../license-server/test-updates-api.php" class="btn" target="_blank">Test Updates API</a>
    </div>
</body>
</html>


