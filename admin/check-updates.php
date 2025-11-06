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
$current_version = '1.0.0';

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
    $current_version = defined('SCRIPT_VERSION') ? SCRIPT_VERSION : '1.0.0';
}

// Check for updates
$license_server_url = defined('LICENSE_SERVER_URL') ? LICENSE_SERVER_URL : 'https://hylinktech.com/server';
$updates_api_url = rtrim($license_server_url, '/') . '/api/updates.php';

try {
    $ch = curl_init($updates_api_url . '?version=' . urlencode($current_version));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        $error = 'Failed to connect to update server: ' . $curl_error;
    } elseif ($http_code === 200) {
        $result = json_decode($response, true);
        if ($result && isset($result['has_update']) && $result['has_update']) {
            $update_available = true;
            $update_info = $result['latest_update'];
        }
    } else {
        $error = 'Update server returned error (HTTP ' . $http_code . ')';
    }
} catch (Exception $e) {
    $error = 'Error checking for updates: ' . $e->getMessage();
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
        <p style="font-size: 18px; margin: 0;">
            <strong>Installed Version:</strong> 
            <span style="color: #3b82f6; font-weight: 600;"><?php echo htmlspecialchars($current_version); ?></span>
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

