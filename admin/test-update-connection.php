<?php
// admin/test-update-connection.php
// Diagnostic tool to test license server connection

require_once 'auth-check.php';
require_once '../config/config.php';

$page_title = 'Test Update Connection';

// Get current version
$current_version = '1.0.0';
try {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'script_version'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && !empty($result['setting_value'])) {
        $current_version = $result['setting_value'];
    }
} catch (Exception $e) {
    $current_version = defined('SCRIPT_VERSION') ? SCRIPT_VERSION : '1.0.0';
}

// Get license server URL
$license_server_url = defined('LICENSE_SERVER_URL') ? LICENSE_SERVER_URL : 'https://hylinktech.com/server';
$updates_api_url = rtrim($license_server_url, '/') . '/api/updates.php';
$test_url = $updates_api_url . '?version=' . urlencode($current_version);

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Test Update Connection</h1>
    <p>Diagnostic tool to test connection to license server</p>
</div>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Configuration</h2>
    </div>
    <div class="card-body">
        <table class="table">
            <tr>
                <th>License Server URL:</th>
                <td><code><?php echo htmlspecialchars($license_server_url); ?></code></td>
            </tr>
            <tr>
                <th>Updates API URL:</th>
                <td><code><?php echo htmlspecialchars($updates_api_url); ?></code></td>
            </tr>
            <tr>
                <th>Test URL:</th>
                <td><code><?php echo htmlspecialchars($test_url); ?></code></td>
            </tr>
            <tr>
                <th>Current Version:</th>
                <td><code><?php echo htmlspecialchars($current_version); ?></code></td>
            </tr>
            <tr>
                <th>LICENSE_SERVER_URL Defined:</th>
                <td><?php echo defined('LICENSE_SERVER_URL') ? '<span style="color: green;">✓ Yes</span>' : '<span style="color: red;">✗ No (using default)</span>'; ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Connection Test</h2>
    </div>
    <div class="card-body">
        <?php
        // Test connection
        $test_results = [];
        
        // Test 1: Check if cURL is available
        $test_results['curl_available'] = function_exists('curl_init');
        
        // Test 2: Test connection
        if ($test_results['curl_available']) {
            $ch = curl_init($test_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'MusicPlatform-Update-Checker/1.0');
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            $curl_info = curl_getinfo($ch);
            curl_close($ch);
            
            $test_results['http_code'] = $http_code;
            $test_results['curl_error'] = $curl_error;
            $test_results['response'] = $response;
            $test_results['response_size'] = strlen($response);
            $test_results['total_time'] = $curl_info['total_time'] ?? 0;
        }
        
        // Display results
        ?>
        <table class="table">
            <tr>
                <th>cURL Available:</th>
                <td><?php echo $test_results['curl_available'] ? '<span style="color: green;">✓ Yes</span>' : '<span style="color: red;">✗ No</span>'; ?></td>
            </tr>
            <?php if ($test_results['curl_available']): ?>
            <tr>
                <th>HTTP Status Code:</th>
                <td>
                    <?php 
                    if ($test_results['http_code'] == 200) {
                        echo '<span style="color: green;">✓ 200 OK</span>';
                    } elseif ($test_results['http_code'] == 404) {
                        echo '<span style="color: red;">✗ 404 Not Found</span> - API endpoint may not exist';
                    } elseif ($test_results['http_code'] == 500) {
                        echo '<span style="color: red;">✗ 500 Server Error</span> - License server error';
                    } elseif ($test_results['http_code'] == 0) {
                        echo '<span style="color: red;">✗ 0 (Connection Failed)</span>';
                    } else {
                        echo '<span style="color: orange;">⚠ ' . $test_results['http_code'] . '</span>';
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th>Connection Time:</th>
                <td><?php echo number_format($test_results['total_time'], 2); ?> seconds</td>
            </tr>
            <tr>
                <th>Response Size:</th>
                <td><?php echo number_format($test_results['response_size']); ?> bytes</td>
            </tr>
            <?php if ($test_results['curl_error']): ?>
            <tr>
                <th>cURL Error:</th>
                <td><span style="color: red;"><?php echo htmlspecialchars($test_results['curl_error']); ?></span></td>
            </tr>
            <?php endif; ?>
            <?php endif; ?>
        </table>
        
        <?php if ($test_results['curl_available'] && !empty($test_results['response'])): ?>
        <div style="margin-top: 20px;">
            <h3>API Response:</h3>
            <pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; max-height: 400px;"><?php echo htmlspecialchars($test_results['response']); ?></pre>
            
            <?php
            // Try to decode JSON
            $json_response = json_decode($test_results['response'], true);
            if ($json_response): ?>
            <h3 style="margin-top: 20px;">Parsed JSON Response:</h3>
            <pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;"><?php print_r($json_response); ?></pre>
            
            <?php if (isset($json_response['has_update'])): ?>
            <div style="margin-top: 20px; padding: 15px; background: <?php echo $json_response['has_update'] ? '#d1fae5' : '#dbeafe'; ?>; border-radius: 5px;">
                <strong>Update Status:</strong> 
                <?php echo $json_response['has_update'] ? 'Update Available!' : 'No Updates Available'; ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div style="margin-top: 20px; padding: 15px; background: #fee2e2; border-radius: 5px; color: #991b1b;">
                <strong>⚠ Warning:</strong> Response is not valid JSON. The API may be returning an error page or HTML.
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Common Issues & Solutions</h2>
    </div>
    <div class="card-body">
        <h3>Issue 1: HTTP 404 - API Endpoint Not Found</h3>
        <p><strong>Solution:</strong> Verify the API endpoint exists at: <code><?php echo htmlspecialchars($updates_api_url); ?></code></p>
        <p>Check if the license server has the file: <code>/api/updates.php</code></p>
        
        <h3>Issue 2: HTTP 500 - Server Error</h3>
        <p><strong>Solution:</strong> Check license server error logs. The API may have a PHP error.</p>
        
        <h3>Issue 3: Connection Timeout</h3>
        <p><strong>Solution:</strong> Check if the license server URL is correct and accessible.</p>
        <p>Test manually: <a href="<?php echo htmlspecialchars($test_url); ?>" target="_blank">Open in browser</a></p>
        
        <h3>Issue 4: Invalid JSON Response</h3>
        <p><strong>Solution:</strong> The API should return JSON in this format:</p>
        <pre style="background: #f5f5f5; padding: 15px; border-radius: 5px;">{
    "has_update": true,
    "latest_update": {
        "version": "1.1.0",
        "title": "Update Title",
        "description": "Update description",
        "download_url": "https://github.com/ayolukasbj/updates",
        "is_critical": false
    }
}</pre>
        
        <h3>Issue 5: LICENSE_SERVER_URL Not Defined</h3>
        <p><strong>Solution:</strong> Add to <code>config/config.php</code>:</p>
        <pre style="background: #f5f5f5; padding: 15px; border-radius: 5px;">define('LICENSE_SERVER_URL', 'https://hylinktech.com/server');</pre>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Manual Test</h2>
    </div>
    <div class="card-body">
        <p>Test the API endpoint directly in your browser:</p>
        <a href="<?php echo htmlspecialchars($test_url); ?>" target="_blank" class="btn btn-primary">
            <i class="fas fa-external-link-alt"></i> Open API URL
        </a>
        <p style="margin-top: 15px; color: #666;">
            This should return JSON. If you see HTML or an error page, the API endpoint may not exist or has an error.
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

