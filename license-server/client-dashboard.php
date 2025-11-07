<?php
// license-server/client-dashboard.php
// Client Dashboard for License Viewing

session_start();
require_once 'config/config.php';
require_once 'config/database.php';

$error = '';
$license = null;
$domain_mismatches = [];
$verification_logs = [];

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $license_key = trim($_POST['license_key'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    
    if (empty($license_key) || empty($customer_email)) {
        $error = 'License key and customer email are required!';
    } else {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            // Verify license and customer email match
            $stmt = $conn->prepare("
                SELECT * FROM licenses 
                WHERE license_key = ? AND customer_email = ?
                LIMIT 1
            ");
            $stmt->execute([$license_key, $customer_email]);
            $license = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($license) {
                // Set session
                $_SESSION['client_license_key'] = $license_key;
                $_SESSION['client_email'] = $customer_email;
                $_SESSION['client_license_id'] = $license['id'];
            } else {
                $error = 'Invalid license key or email address!';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: client-dashboard.php');
    exit;
}

// Check if client is logged in
$is_logged_in = isset($_SESSION['client_license_key']) && isset($_SESSION['client_email']);

if ($is_logged_in) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Get license details
        $stmt = $conn->prepare("SELECT * FROM licenses WHERE license_key = ? AND customer_email = ?");
        $stmt->execute([$_SESSION['client_license_key'], $_SESSION['client_email']]);
        $license = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($license) {
            // Get domain mismatch logs
            try {
                $conn->exec("
                    CREATE TABLE IF NOT EXISTS domain_mismatch_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        license_id INT NOT NULL,
                        license_key VARCHAR(255) NOT NULL,
                        attempted_domain VARCHAR(255) NOT NULL,
                        bound_domain VARCHAR(255) NOT NULL,
                        ip_address VARCHAR(45),
                        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_license_id (license_id),
                        INDEX idx_license_key (license_key)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                
                $mismatchStmt = $conn->prepare("
                    SELECT * FROM domain_mismatch_logs 
                    WHERE license_key = ? 
                    ORDER BY attempted_at DESC 
                    LIMIT 20
                ");
                $mismatchStmt->execute([$license['license_key']]);
                $domain_mismatches = $mismatchStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Table might not exist yet
            }
            
            // Get recent verification logs
            $verifyStmt = $conn->prepare("
                SELECT * FROM license_logs 
                WHERE license_key = ? AND action = 'verify'
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $verifyStmt->execute([$license['license_key']]);
            $verification_logs = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $error = 'Error loading license data: ' . $e->getMessage();
    }
}

$page_title = $is_logged_in ? 'My License Dashboard' : 'Client License Login';
require_once 'includes/header.php';
?>

<style>
    .login-box { max-width: 500px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .license-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px; margin-bottom: 20px; }
    .license-key-display { background: rgba(255,255,255,0.2); padding: 15px; border-radius: 6px; font-size: 18px; font-family: monospace; word-break: break-all; margin: 15px 0; }
    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0; }
    .info-item { background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #3b82f6; }
    .info-item strong { display: block; color: #666; font-size: 12px; margin-bottom: 5px; }
    .info-item span { color: #1f2937; font-size: 16px; font-weight: 600; }
    .copy-btn { background: rgba(255,255,255,0.3); border: 1px solid rgba(255,255,255,0.5); color: white; padding: 8px 15px; border-radius: 4px; cursor: pointer; margin-left: 10px; }
    .copy-btn:hover { background: rgba(255,255,255,0.4); }
    .alert-warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 6px; margin: 20px 0; }
    .alert-warning h4 { margin: 0 0 10px 0; color: #92400e; }
    @media (max-width: 768px) {
        .info-grid { grid-template-columns: 1fr; }
        .license-key-display { font-size: 14px; }
    }
</style>

<div class="container">
    <?php if (!$is_logged_in): ?>
    <!-- Login Form -->
    <div class="login-box">
        <h2 style="margin-bottom: 20px; text-align: center;">Client License Login</h2>
        <p style="text-align: center; color: #666; margin-bottom: 30px;">Enter your license key and email to view your license details</p>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="action" value="login">
            
            <div class="form-group">
                <label>License Key</label>
                <input type="text" name="license_key" required 
                    placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" 
                    style="font-family: monospace;"
                    value="<?php echo htmlspecialchars($_POST['license_key'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Customer Email</label>
                <input type="email" name="customer_email" required 
                    placeholder="your@email.com"
                    value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>">
            </div>
            
            <button type="submit" class="btn" style="width: 100%;">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
    </div>
    
    <?php else: ?>
    <!-- License Dashboard -->
    <div class="page-header">
        <h1>My License Dashboard</h1>
        <p>View your license details and usage information</p>
    </div>
    
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($license): ?>
    <!-- License Key Card -->
    <div class="license-card">
        <h3 style="margin: 0 0 15px 0;">
            <i class="fas fa-key"></i> Your License Key
        </h3>
        <div class="license-key-display">
            <?php echo htmlspecialchars($license['license_key']); ?>
            <button type="button" class="copy-btn" onclick="copyLicenseKey()">
                <i class="fas fa-copy"></i> Copy
            </button>
        </div>
        <p style="margin: 0; opacity: 0.9; font-size: 14px;">
            <i class="fas fa-info-circle"></i> Your license is bound to: <strong><?php echo htmlspecialchars($license['bound_domain'] ?? 'Not bound yet'); ?></strong>
        </p>
    </div>
    
    <!-- License Information -->
    <div class="card">
        <div class="card-header">
            <h2>License Information</h2>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <strong>License Status</strong>
                    <span style="color: <?php echo $license['status'] === 'active' ? '#10b981' : '#ef4444'; ?>;">
                        <?php echo ucfirst($license['status']); ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <strong>License Type</strong>
                    <span><?php echo ucfirst($license['license_type'] ?? 'Lifetime'); ?></span>
                </div>
                
                <div class="info-item">
                    <strong>Customer Name</strong>
                    <span><?php echo htmlspecialchars($license['customer_name'] ?? 'N/A'); ?></span>
                </div>
                
                <div class="info-item">
                    <strong>Customer Email</strong>
                    <span><?php echo htmlspecialchars($license['customer_email'] ?? 'N/A'); ?></span>
                </div>
                
                <div class="info-item">
                    <strong>Bound Domain</strong>
                    <span><?php echo htmlspecialchars($license['bound_domain'] ?? 'Not bound'); ?></span>
                </div>
                
                <div class="info-item">
                    <strong>Purchase Date</strong>
                    <span><?php echo date('M d, Y', strtotime($license['purchase_date'] ?? $license['created_at'])); ?></span>
                </div>
                
                <div class="info-item">
                    <strong>Last Verified</strong>
                    <span><?php echo $license['last_verified'] ? date('M d, Y H:i', strtotime($license['last_verified'])) : 'Never'; ?></span>
                </div>
                
                <div class="info-item">
                    <strong>Verification Count</strong>
                    <span><?php echo number_format($license['verification_count'] ?? 0); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Domain Mismatch Alerts -->
    <?php if (!empty($domain_mismatches)): ?>
    <div class="alert-warning">
        <h4><i class="fas fa-exclamation-triangle"></i> Domain Mismatch Attempts Detected</h4>
        <p>Someone tried to use your license on a different domain. If this wasn't you, please contact support immediately.</p>
        <div style="margin-top: 15px;">
            <div style="overflow-x: auto;">
                <table style="width: 100%; font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Attempted Domain</th>
                            <th>Bound Domain</th>
                            <th>IP Address</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($domain_mismatches as $mismatch): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($mismatch['attempted_domain']); ?></code></td>
                            <td><code><?php echo htmlspecialchars($mismatch['bound_domain']); ?></code></td>
                            <td><?php echo htmlspecialchars($mismatch['ip_address'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($mismatch['attempted_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Recent Verification Logs -->
    <?php if (!empty($verification_logs)): ?>
    <div class="card">
        <div class="card-header">
            <h2>Recent Verification Logs</h2>
        </div>
        <div class="card-body">
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>IP Address</th>
                            <th>Status</th>
                            <th>Message</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($verification_logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['domain'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $log['status'] === 'success' ? 'success' : 'failed'; ?>">
                                    <?php echo ucfirst($log['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($log['message'] ?? ''); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="?logout=1" class="btn" style="background: #6b7280;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
    
    <script>
    function copyLicenseKey() {
        const licenseKey = '<?php echo htmlspecialchars($license['license_key']); ?>';
        navigator.clipboard.writeText(licenseKey).then(function() {
            alert('License key copied to clipboard!');
        }, function() {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = licenseKey;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            alert('License key copied to clipboard!');
        });
    }
    </script>
    
    <?php else: ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> License not found!
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>

</body>
</html>

