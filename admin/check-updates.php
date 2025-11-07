<?php
// admin/check-updates.php
// Check for script updates from license server

require_once 'auth-check.php';
require_once '../config/config.php';

$page_title = 'Check for Updates';

$update_available = false;
$update_info = null;
$error = '';

// Get current version (from config or database)
$current_version = '1.0';

try {
    // Try to get from database settings
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'script_version'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && !empty($result['setting_value'])) {
        $current_version = $result['setting_value'];
    }
} catch (Exception $e) {
    // Fallback to constant
    require_once '../config/version.php';
    $current_version = defined('SCRIPT_VERSION') ? SCRIPT_VERSION : '1.0';
}

// Check for updates
$license_server_url = defined('LICENSE_SERVER_URL') ? LICENSE_SERVER_URL : 'https://hylinktech.com/server';

// Auto-detect if localhost URL is set (for development) and switch to production URL
if (strpos($license_server_url, 'localhost') !== false || strpos($license_server_url, '127.0.0.1') !== false) {
    // This is a local development URL, use production URL instead
    $license_server_url = 'https://hylinktech.com/server';
    error_log("Update check: Detected localhost license server URL, using production URL instead");
}

$updates_api_url = rtrim($license_server_url, '/') . '/api/updates.php';

// Allow updates even with same version (force check for file changes)
// Add force_check parameter to allow server to return updates even if version matches
$force_check = isset($_GET['force']) ? '1' : '0';

try {
    $ch = curl_init($updates_api_url . '?version=' . urlencode($current_version) . '&force_check=' . $force_check);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MusicPlatform-Update-Checker/1.0');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_info = curl_getinfo($ch);
    curl_close($ch);
    
    // Log connection details for debugging
    error_log("Update check: URL=" . $updates_api_url . ", HTTP_CODE=" . $http_code . ", ERROR=" . ($curl_error ?: 'none'));
    
    if ($curl_error) {
        $error = 'Failed to connect to update server: ' . $curl_error . 
                 '<br><small>URL: ' . htmlspecialchars($updates_api_url) . '</small>' .
                 '<br><small>Please check: <a href="test-update-connection.php">Test Connection</a> for diagnostics</small>';
    } elseif ($http_code === 200) {
        $result = json_decode($response, true);
        if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
            $error = 'Invalid JSON response from update server. Response: ' . htmlspecialchars(substr($response, 0, 200)) .
                     '<br><small>Please check: <a href="test-update-connection.php">Test Connection</a> for diagnostics</small>';
        } elseif ($result && isset($result['has_update']) && $result['has_update']) {
            $update_available = true;
            $update_info = $result['latest_update'];
        } elseif ($result && isset($result['error'])) {
            $error = 'Update server error: ' . htmlspecialchars($result['error']);
        } elseif ($result && isset($result['has_update']) && $result['has_update'] === false && isset($result['force_update']) && $result['force_update'] === true) {
            // Server indicates update available even with same version (file changes detected)
            $update_available = true;
            $update_info = $result['latest_update'];
        }
    } elseif ($http_code === 404) {
        $error = 'Update API endpoint not found (404). Please verify the API exists at: ' . htmlspecialchars($updates_api_url) .
                 '<br><small>Check: <a href="test-update-connection.php">Test Connection</a> for diagnostics</small>';
    } elseif ($http_code === 500) {
        $error = 'Update server returned error (500). Please check license server logs.' .
                 '<br><small>Check: <a href="test-update-connection.php">Test Connection</a> for diagnostics</small>';
    } else {
        $error = 'Update server returned error (HTTP ' . $http_code . ')' .
                 '<br><small>URL: ' . htmlspecialchars($updates_api_url) . '</small>' .
                 '<br><small>Response: ' . htmlspecialchars(substr($response, 0, 200)) . '</small>' .
                 '<br><small>Check: <a href="test-update-connection.php">Test Connection</a> for diagnostics</small>';
    }
} catch (Exception $e) {
    $error = 'Error checking for updates: ' . $e->getMessage() .
             '<br><small>Check: <a href="test-update-connection.php">Test Connection</a> for diagnostics</small>';
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Check for Updates</h1>
    <p>Check if new versions of the script are available</p>
</div>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Current Version</h2>
    </div>
    <div class="card-body">
        <p style="font-size: 18px; margin: 0; margin-bottom: 15px;">
            <strong>Installed Version:</strong> 
            <span style="color: #3b82f6; font-weight: 600;"><?php echo htmlspecialchars($current_version); ?></span>
        </p>
        <p style="margin: 0;">
            <a href="?force=1" class="btn btn-secondary" style="padding: 8px 16px; font-size: 14px;">
                <i class="fas fa-sync"></i> Force Check for Updates (Even if version matches)
            </a>
            <small style="display: block; margin-top: 8px; color: #6b7280;">
                Force check allows updates even if version is the same, useful when files have been modified.
            </small>
        </p>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<?php if ($update_available && $update_info): ?>
<div class="alert alert-success" style="background: #d1fae5; border-left: 4px solid #10b981;">
    <h3 style="margin-top: 0;">
        <i class="fas fa-download"></i> Update Available!
    </h3>
    <p style="margin-bottom: 10px;">
        <strong>Version <?php echo htmlspecialchars($update_info['version']); ?></strong> is now available.
    </p>
    <p style="margin-bottom: 15px;">
        <strong><?php echo htmlspecialchars($update_info['title']); ?></strong>
    </p>
    
    <?php if (!empty($update_info['description'])): ?>
    <p style="margin-bottom: 15px;">
        <?php echo nl2br(htmlspecialchars($update_info['description'])); ?>
    </p>
    <?php endif; ?>
    
    <?php if (!empty($update_info['changelog'])): ?>
    <div style="background: white; padding: 15px; border-radius: 6px; margin: 15px 0;">
        <strong>Changelog:</strong>
        <pre style="margin: 10px 0 0 0; white-space: pre-wrap; font-family: inherit;"><?php echo htmlspecialchars($update_info['changelog']); ?></pre>
    </div>
    <?php endif; ?>
    
    <?php if ($update_info['is_critical']): ?>
    <div style="background: #fee2e2; padding: 10px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #dc2626;">
        <strong style="color: #991b1b;">
            <i class="fas fa-exclamation-triangle"></i> Critical Update
        </strong>
        <p style="margin: 5px 0 0 0; color: #991b1b;">
            This is a critical security or patch update. Please update as soon as possible.
        </p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($update_info['download_url'])): ?>
    <div style="margin-top: 20px;">
        <form method="POST" action="install-update.php" style="display: inline;">
            <input type="hidden" name="version" value="<?php echo htmlspecialchars($update_info['version']); ?>">
            <input type="hidden" name="download_url" value="<?php echo htmlspecialchars($update_info['download_url']); ?>">
            <button type="submit" class="btn btn-primary" 
                style="display: inline-block; padding: 12px 24px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; border: none; cursor: pointer; font-size: 16px;">
                <i class="fas fa-magic"></i> Install Update Automatically
            </button>
        </form>
        <a href="<?php echo htmlspecialchars($update_info['download_url']); ?>" 
           target="_blank" 
           class="btn btn-secondary" 
           style="display: inline-block; padding: 12px 24px; background: #6b7280; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; margin-left: 10px;">
            <i class="fas fa-download"></i> Download Manually
        </a>
    </div>
    <?php else: ?>
    <p style="color: #666; margin-top: 15px;">
        <i class="fas fa-info-circle"></i> Download link will be provided by support.
    </p>
    <?php endif; ?>
</div>
<?php elseif (!$error): ?>
<div class="alert alert-info" style="background: #dbeafe; border-left: 4px solid #3b82f6;">
    <h3 style="margin-top: 0;">
        <i class="fas fa-check-circle"></i> You're Up to Date!
    </h3>
    <p style="margin: 0;">
        Your script is running the latest version (<?php echo htmlspecialchars($current_version); ?>).
    </p>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Check Again</h2>
    </div>
    <div class="card-body">
        <p>Click the button below to check for updates again.</p>
        <a href="check-updates.php" class="btn btn-primary">
            <i class="fas fa-sync"></i> Check for Updates
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

