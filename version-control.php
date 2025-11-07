<?php
// license-server/version-control.php
// Version Control & Remote Management System

session_start();
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/auth.php';

requireLogin();

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Ensure version_control_settings table exists
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS version_control_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(255) UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create update_rollback table for tracking rollbacks
    $conn->exec("
        CREATE TABLE IF NOT EXISTS update_rollback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_version VARCHAR(50) NOT NULL,
            to_version VARCHAR(50) NOT NULL,
            rollback_reason TEXT,
            rollback_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            performed_by VARCHAR(255),
            INDEX idx_from_version (from_version),
            INDEX idx_to_version (to_version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create remote_management_logs table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS remote_management_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action_type VARCHAR(100) NOT NULL,
            target_license VARCHAR(255),
            target_domain VARCHAR(255),
            action_data TEXT,
            performed_by VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_action_type (action_type),
            INDEX idx_target_license (target_license)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    // Tables might already exist
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_settings') {
        $auto_update_enabled = isset($_POST['auto_update_enabled']) ? 1 : 0;
        $require_approval = isset($_POST['require_approval']) ? 1 : 0;
        $rollback_enabled = isset($_POST['rollback_enabled']) ? 1 : 0;
        $remote_management_enabled = isset($_POST['remote_management_enabled']) ? 1 : 0;
        
        try {
            $stmt = $conn->prepare("
                INSERT INTO version_control_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            
            $stmt->execute(['auto_update_enabled', $auto_update_enabled, $auto_update_enabled]);
            $stmt->execute(['require_approval', $require_approval, $require_approval]);
            $stmt->execute(['rollback_enabled', $rollback_enabled, $rollback_enabled]);
            $stmt->execute(['remote_management_enabled', $remote_management_enabled, $remote_management_enabled]);
            
            $success = 'Version control settings saved successfully!';
        } catch (Exception $e) {
            $error = 'Error saving settings: ' . $e->getMessage();
        }
    }
    
    elseif ($action === 'create_rollback') {
        $from_version = trim($_POST['from_version'] ?? '');
        $to_version = trim($_POST['to_version'] ?? '');
        $rollback_reason = trim($_POST['rollback_reason'] ?? '');
        
        if (empty($from_version) || empty($to_version)) {
            $error = 'From version and To version are required!';
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO update_rollback (from_version, to_version, rollback_reason, performed_by)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$from_version, $to_version, $rollback_reason, $_SESSION['user_name'] ?? 'Admin']);
                
                // Create a rollback update entry
                $stmt2 = $conn->prepare("
                    INSERT INTO updates (version, title, description, download_url, release_date, is_critical)
                    VALUES (?, ?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description)
                ");
                $stmt2->execute([
                    $to_version,
                    "Rollback to version $to_version",
                    "Rollback from version $from_version. Reason: $rollback_reason",
                    '', // Download URL for rollback version
                    date('Y-m-d')
                ]);
                
                $success = "Rollback created: Version $from_version → $to_version";
            } catch (Exception $e) {
                $error = 'Error creating rollback: ' . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'remote_action') {
        $action_type = $_POST['action_type'] ?? '';
        $target_license = trim($_POST['target_license'] ?? '');
        $target_domain = trim($_POST['target_domain'] ?? '');
        $action_data = json_encode($_POST['action_data'] ?? []);
        
        if (empty($action_type) || empty($target_license)) {
            $error = 'Action type and target license are required!';
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO remote_management_logs (action_type, target_license, target_domain, action_data, performed_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$action_type, $target_license, $target_domain, $action_data, $_SESSION['user_name'] ?? 'Admin']);
                
                $success = "Remote action '$action_type' logged for license: " . substr($target_license, 0, 10) . '...';
            } catch (Exception $e) {
                $error = 'Error logging remote action: ' . $e->getMessage();
            }
        }
    }
}

// Get current settings
$settings = [
    'auto_update_enabled' => 0,
    'require_approval' => 1,
    'rollback_enabled' => 1,
    'remote_management_enabled' => 1
];

try {
    $stmt = $conn->query("SELECT setting_key, setting_value FROM version_control_settings");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        $settings[$row['setting_key']] = (int)$row['setting_value'];
    }
} catch (Exception $e) {
    // Use defaults
}

// Get all updates
$updates = [];
try {
    $stmt = $conn->query("SELECT * FROM updates ORDER BY release_date DESC, version DESC");
    $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error
}

// Get rollback history
$rollbacks = [];
try {
    $stmt = $conn->query("SELECT * FROM update_rollback ORDER BY rollback_date DESC LIMIT 10");
    $rollbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error
}

// Get remote management logs
$remote_logs = [];
try {
    $stmt = $conn->query("SELECT * FROM remote_management_logs ORDER BY created_at DESC LIMIT 20");
    $remote_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error
}

// Get all active licenses for remote management
$active_licenses = [];
try {
    $stmt = $conn->query("SELECT license_key, bound_domain, customer_name, customer_email FROM licenses WHERE status = 'active' ORDER BY created_at DESC");
    $active_licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error
}

?>
<?php
$page_title = 'Version Control & Remote Management';
$additional_css = '
        .info-box-api { background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .info-box-api h4 { margin: 0 0 10px 0; color: #1e40af; font-size: 16px; }
        .info-box-api code { background: #e0f2fe; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
        .api-endpoint { background: #f9fafb; padding: 15px; border-radius: 6px; margin: 10px 0; font-family: monospace; font-size: 13px; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { color: #1f2937; margin-bottom: 5px; font-size: 24px; }
        .page-header p { color: #6b7280; font-size: 14px; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { color: #1f2937; margin-bottom: 5px; font-size: 24px; }
        .page-header p { color: #6b7280; font-size: 14px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { padding: 18px 20px; border-bottom: 1px solid #e5e7eb; }
        .card-header h2 { margin: 0; color: #1f2937; font-size: 18px; }
        .card-body { padding: 20px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 14px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #3b82f6; }
        .form-group small { color: #666; display: block; margin-top: 5px; font-size: 12px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .switch { position: relative; display: inline-block; width: 50px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #3b82f6; }
        input:checked + .slider:before { transform: translateX(26px); }
        .btn { padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-block; text-decoration: none; }
        .btn:hover { background: #2563eb; }
        .btn-sm { padding: 6px 12px; font-size: 13px; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        .btn-warning { background: #f59e0b; }
        .btn-warning:hover { background: #d97706; }
        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-primary { background: #dbeafe; color: #1e40af; }
        .badge-success { background: #d1fae5; color: #065f46; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        table th { background: #f9fafb; font-weight: 600; color: #374151; }
        table tr:last-child td { border-bottom: none; }
        .tabs { display: flex; gap: 10px; border-bottom: 2px solid #e5e7eb; margin-bottom: 20px; overflow-x: auto; }
        .tab-btn { padding: 12px 20px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 600; color: #666; font-size: 14px; white-space: nowrap; }
        .tab-btn:hover { color: #1f2937; }
        .tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .info-box { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .info-box h3 { margin: 0 0 10px 0; color: #1e40af; font-size: 16px; }
        .info-box ul { margin: 10px 0 0 20px; padding: 0; }
        .info-box li { margin-bottom: 5px; color: #1e3a8a; }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .header-content { flex-direction: column; align-items: flex-start; gap: 10px; }
            .header h1 { font-size: 18px; }
            .header-actions { width: 100%; justify-content: space-between; }
            .nav-content { gap: 10px; }
            .nav a { padding: 6px 10px; font-size: 13px; }
            .container { padding: 0 10px; }
            .card-body { padding: 15px; }
            .form-row { grid-template-columns: 1fr; }
            table { font-size: 12px; }
            table th, table td { padding: 8px; }
            .tabs { gap: 5px; }
            .tab-btn { padding: 10px 15px; font-size: 13px; }
        }
        
        @media (max-width: 480px) {
            .page-header h1 { font-size: 20px; }
            table { display: block; overflow-x: auto; white-space: nowrap; }
            .btn { padding: 8px 16px; font-size: 13px; }
        }
        .info-box-api { background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .info-box-api h4 { margin: 0 0 10px 0; color: #1e40af; font-size: 16px; }
        .info-box-api code { background: #e0f2fe; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
        .api-endpoint { background: #f9fafb; padding: 15px; border-radius: 6px; margin: 10px 0; font-family: monospace; font-size: 13px; }
    </style>
<?php
$page_title = 'Version Control & Remote Management';
require_once 'includes/header.php';
?>
    
    <div class="container">
        <div class="page-header">
            <h1>Version Control & Remote Management</h1>
            <p>Manage automatic updates, rollbacks, and remote platform control</p>
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

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('settings')">Settings</button>
            <button class="tab-btn" onclick="switchTab('updates')">Updates Management</button>
            <button class="tab-btn" onclick="switchTab('rollback')">Rollback</button>
            <button class="tab-btn" onclick="switchTab('remote')">Remote Management</button>
        </div>

        <!-- Settings Tab -->
        <div id="settings" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <h2>Version Control Settings</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_settings">
                        
                        <div class="info-box">
                            <h3><i class="fas fa-info-circle"></i> How Version Control Works</h3>
                            <ul>
                                <li><strong>Automatic Updates:</strong> Allows platforms to automatically download and install updates when available</li>
                                <li><strong>Require Approval:</strong> When enabled, platforms must request approval before installing updates</li>
                                <li><strong>Rollback:</strong> Enables rollback functionality to revert to previous versions</li>
                                <li><strong>Remote Management:</strong> Allows server to remotely control platform features and settings</li>
                            </ul>
                        </div>
                        
                        <div class="form-group">
                            <label style="display: flex; align-items: center; justify-content: space-between;">
                                <span>Enable Automatic Updates</span>
                                <label class="switch">
                                    <input type="checkbox" name="auto_update_enabled" value="1" <?php echo $settings['auto_update_enabled'] ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </label>
                            <small>Allow platforms to automatically install updates without manual intervention</small>
                        </div>
                        
                        <div class="form-group">
                            <label style="display: flex; align-items: center; justify-content: space-between;">
                                <span>Require Approval for Updates</span>
                                <label class="switch">
                                    <input type="checkbox" name="require_approval" value="1" <?php echo $settings['require_approval'] ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </label>
                            <small>Platforms must request approval before installing updates (if automatic updates are enabled)</small>
                        </div>
                        
                        <div class="form-group">
                            <label style="display: flex; align-items: center; justify-content: space-between;">
                                <span>Enable Rollback Functionality</span>
                                <label class="switch">
                                    <input type="checkbox" name="rollback_enabled" value="1" <?php echo $settings['rollback_enabled'] ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </label>
                            <small>Allow rolling back to previous versions if issues occur</small>
                        </div>
                        
                        <div class="form-group">
                            <label style="display: flex; align-items: center; justify-content: space-between;">
                                <span>Enable Remote Management</span>
                                <label class="switch">
                                    <input type="checkbox" name="remote_management_enabled" value="1" <?php echo $settings['remote_management_enabled'] ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </label>
                            <small>Allow server to remotely manage platform settings and features</small>
                        </div>
                        
                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Updates Management Tab -->
        <div id="updates" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2>Updates Management</h2>
                </div>
                <div class="card-body">
                    <p style="margin-bottom: 20px;">
                        <a href="updates.php" class="btn">
                            <i class="fas fa-plus"></i> Create New Update
                        </a>
                    </p>
                    
                    <?php if (empty($updates)): ?>
                    <p style="text-align: center; padding: 40px; color: #999;">No updates created yet.</p>
                    <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Version</th>
                                    <th>Title</th>
                                    <th>Release Date</th>
                                    <th>Status</th>
                                    <th>Download URL</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($updates as $update): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($update['version']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($update['title']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($update['release_date'])); ?></td>
                                    <td>
                                        <?php if ($update['is_critical']): ?>
                                        <span class="badge badge-danger">Critical</span>
                                        <?php endif; ?>
                                        <?php if ($update['is_featured']): ?>
                                        <span class="badge badge-primary">Featured</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo !empty($update['download_url']) ? htmlspecialchars($update['download_url']) : '<span style="color: #999;">Not set</span>'; ?>
                                    </td>
                                    <td>
                                        <a href="updates.php" class="btn btn-sm">Edit</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Rollback Tab -->
        <div id="rollback" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2>Create Rollback</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_rollback">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>From Version (Current)</label>
                                <select name="from_version" required>
                                    <option value="">Select version...</option>
                                    <?php foreach ($updates as $update): ?>
                                    <option value="<?php echo htmlspecialchars($update['version']); ?>">
                                        <?php echo htmlspecialchars($update['version']); ?> - <?php echo htmlspecialchars($update['title']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>To Version (Rollback to)</label>
                                <select name="to_version" required>
                                    <option value="">Select version...</option>
                                    <?php foreach ($updates as $update): ?>
                                    <option value="<?php echo htmlspecialchars($update['version']); ?>">
                                        <?php echo htmlspecialchars($update['version']); ?> - <?php echo htmlspecialchars($update['title']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Rollback Reason</label>
                            <textarea name="rollback_reason" rows="3" 
                                placeholder="Reason for rollback (e.g., Critical bug found, Customer request, etc.)"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-undo"></i> Create Rollback
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>Rollback History</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($rollbacks)): ?>
                    <p style="text-align: center; padding: 40px; color: #999;">No rollbacks performed yet.</p>
                    <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>From Version</th>
                                    <th>To Version</th>
                                    <th>Reason</th>
                                    <th>Performed By</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rollbacks as $rollback): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($rollback['from_version']); ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($rollback['to_version']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($rollback['rollback_reason']); ?></td>
                                    <td><?php echo htmlspecialchars($rollback['performed_by']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($rollback['rollback_date'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Remote Management Tab -->
        <div id="remote" class="tab-content">
            <div class="info-box-api">
                <h4><i class="fas fa-info-circle"></i> API Management</h4>
                <p>Manage platforms remotely via API. All actions are logged and can be executed programmatically.</p>
                <p><strong>API Endpoint:</strong> <code><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>/api/remote-management.php</code></p>
                <p><strong>Method:</strong> POST | <strong>Content-Type:</strong> application/json</p>
                <div class="api-endpoint">
                    <strong>Example Request:</strong><br>
                    POST /api/remote-management.php<br>
                    {<br>
                    &nbsp;&nbsp;"license_key": "XXXX-XXXX-XXXX-XXXX",<br>
                    &nbsp;&nbsp;"action": "enable_feature",<br>
                    &nbsp;&nbsp;"feature": "maintenance_mode"<br>
                    }
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>Remote Platform Management</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="remote_action">
                        
                        <div class="form-group">
                            <label>Select License</label>
                            <select name="target_license" required onchange="updateDomain(this.value)">
                                <option value="">Select license...</option>
                                <?php foreach ($active_licenses as $license): ?>
                                <option value="<?php echo htmlspecialchars($license['license_key']); ?>" 
                                        data-domain="<?php echo htmlspecialchars($license['bound_domain'] ?? ''); ?>">
                                    <?php echo htmlspecialchars(substr($license['license_key'], 0, 20)); ?>... 
                                    (<?php echo htmlspecialchars($license['customer_name'] ?? $license['customer_email'] ?? 'N/A'); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Target Domain</label>
                            <input type="text" name="target_domain" id="target_domain" readonly 
                                placeholder="Will be auto-filled from selected license">
                        </div>
                        
                        <div class="form-group">
                            <label>Action Type</label>
                            <select name="action_type" id="action_type" required onchange="updateActionDataFields()">
                                <option value="">Select action...</option>
                                <option value="get_status">Get Platform Status</option>
                                <option value="enable_feature">Enable Feature</option>
                                <option value="disable_feature">Disable Feature</option>
                                <option value="update_setting">Update Setting</option>
                                <option value="clear_cache">Clear Cache</option>
                                <option value="enable_maintenance">Enable Maintenance Mode</option>
                                <option value="disable_maintenance">Disable Maintenance Mode</option>
                                <option value="force_update">Force Update</option>
                                <option value="disable_platform">Disable Platform</option>
                                <option value="enable_platform">Enable Platform</option>
                                <option value="reset_password">Reset Admin Password</option>
                                <option value="backup_database">Backup Database</option>
                                <option value="run_maintenance">Run Maintenance Tasks</option>
                                <option value="get_logs">Get Management Logs</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="feature_field" style="display: none;">
                            <label>Feature Name</label>
                            <input type="text" name="action_data[feature]" placeholder="e.g., maintenance_mode, user_registration, api_access" 
                                value="<?php echo htmlspecialchars($_POST['action_data']['feature'] ?? ''); ?>">
                            <small>Enter the feature name to enable/disable</small>
                        </div>
                        
                        <div class="form-group" id="setting_key_field" style="display: none;">
                            <label>Setting Key</label>
                            <input type="text" name="action_data[setting_key]" placeholder="e.g., site_name, max_upload_size" 
                                value="<?php echo htmlspecialchars($_POST['action_data']['setting_key'] ?? ''); ?>">
                            <small>Enter the setting key to update</small>
                        </div>
                        
                        <div class="form-group" id="setting_value_field" style="display: none;">
                            <label>Setting Value</label>
                            <input type="text" name="action_data[setting_value]" placeholder="Enter the new value" 
                                value="<?php echo htmlspecialchars($_POST['action_data']['setting_value'] ?? ''); ?>">
                            <small>Enter the new value for the setting</small>
                        </div>
                        
                        <div class="form-group" id="version_field" style="display: none;">
                            <label>Target Version (Optional)</label>
                            <input type="text" name="action_data[version]" placeholder="e.g., 1.0.1" 
                                value="<?php echo htmlspecialchars($_POST['action_data']['version'] ?? ''); ?>">
                            <small>Optional: Specify version to force update to</small>
                        </div>
                        
                        <div class="form-group" id="limit_field" style="display: none;">
                            <label>Log Limit</label>
                            <input type="number" name="action_data[limit]" placeholder="50" value="50" min="1" max="1000">
                            <small>Number of logs to retrieve (1-1000)</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Action Data (JSON)</label>
                            <textarea name="action_data[data]" rows="4" 
                                placeholder='{"feature": "maintenance_mode", "value": "1"}' 
                                style="font-family: monospace; font-size: 12px;"></textarea>
                            <small>JSON data for the action (e.g., {"setting_key": "maintenance_mode", "setting_value": "1"})</small>
                        </div>
                        
                        <button type="submit" class="btn">
                            <i class="fas fa-paper-plane"></i> Execute Remote Action
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>Remote Management Logs</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($remote_logs)): ?>
                    <p style="text-align: center; padding: 40px; color: #999;">No remote actions performed yet.</p>
                    <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Action Type</th>
                                    <th>Target License</th>
                                    <th>Target Domain</th>
                                    <th>Action Data</th>
                                    <th>Performed By</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($remote_logs as $log): ?>
                                <tr>
                                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($log['action_type']); ?></span></td>
                                    <td><code><?php echo htmlspecialchars(substr($log['target_license'], 0, 15)); ?>...</code></td>
                                    <td><?php echo htmlspecialchars($log['target_domain']); ?></td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars(substr($log['action_data'], 0, 50)); ?>...
                                    </td>
                                    <td><?php echo htmlspecialchars($log['performed_by']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    function switchTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Show selected tab
        document.getElementById(tabName).classList.add('active');
        event.target.classList.add('active');
    }
    
    function updateDomain(licenseKey) {
        const select = document.querySelector('select[name="target_license"]');
        const selectedOption = select.options[select.selectedIndex];
        const domain = selectedOption.getAttribute('data-domain') || '';
        document.getElementById('target_domain').value = domain;
    }
    
    function updateActionDataFields() {
        const actionType = document.getElementById('action_type').value;
        
        // Hide all fields
        document.getElementById('feature_field').style.display = 'none';
        document.getElementById('setting_key_field').style.display = 'none';
        document.getElementById('setting_value_field').style.display = 'none';
        document.getElementById('version_field').style.display = 'none';
        document.getElementById('limit_field').style.display = 'none';
        
        // Show relevant fields based on action type
        if (actionType === 'enable_feature' || actionType === 'disable_feature') {
            document.getElementById('feature_field').style.display = 'block';
        } else if (actionType === 'update_setting') {
            document.getElementById('setting_key_field').style.display = 'block';
            document.getElementById('setting_value_field').style.display = 'block';
        } else if (actionType === 'force_update') {
            document.getElementById('version_field').style.display = 'block';
        } else if (actionType === 'get_logs') {
            document.getElementById('limit_field').style.display = 'block';
        }
    }
    </script>
</body>
</html>
