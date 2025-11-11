<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Email Settings';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Create email_settings table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS email_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(255) NOT NULL UNIQUE,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Insert default settings if they don't exist
    $default_settings = [
        'email_method' => 'smtp',
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => '587',
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',
        'from_email' => 'noreply@example.com',
        'from_name' => 'Music Platform',
        'reply_to_email' => '',
        'test_email' => ''
    ];
    
    foreach ($default_settings as $key => $value) {
        $checkStmt = $conn->prepare("SELECT id FROM email_settings WHERE setting_key = ?");
        $checkStmt->execute([$key]);
        if ($checkStmt->rowCount() == 0) {
            $insertStmt = $conn->prepare("INSERT INTO email_settings (setting_key, setting_value) VALUES (?, ?)");
            $insertStmt->execute([$key, $value]);
        }
    }
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Get current settings
$settings = [];
try {
    $stmt = $conn->query("SELECT setting_key, setting_value FROM email_settings");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $error = 'Error fetching settings: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $updateStmt = $conn->prepare("INSERT INTO email_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        
        foreach ($_POST as $key => $value) {
            if ($key !== 'action' && strpos($key, 'smtp_') === 0 || in_array($key, ['email_method', 'from_email', 'from_name', 'reply_to_email', 'test_email'])) {
                $updateStmt->execute([$key, $value, $value]);
            }
        }
        
        $success = 'Email settings saved successfully!';
        logAdminActivity($_SESSION['user_id'], 'update_email_settings', 'settings', 0, "Updated email settings");
        
        // Refresh settings
        $stmt = $conn->query("SELECT setting_key, setting_value FROM email_settings");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        $error = 'Error saving settings: ' . $e->getMessage();
    }
}

// Handle test email
if (isset($_POST['send_test_email'])) {
    $test_email = trim($_POST['test_email'] ?? '');
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address for testing';
    } else {
        require_once '../helpers/EmailHelper.php';
        
        $subject = 'Test Email from ' . ($settings['from_name'] ?? 'Music Platform');
        $message = '
        <html>
        <body style="font-family: Arial, sans-serif; padding: 20px;">
            <h2>Test Email</h2>
            <p>This is a test email from your email configuration.</p>
            <p>If you receive this email, your email settings are working correctly!</p>
            <p><strong>Email Method:</strong> ' . htmlspecialchars($settings['email_method'] ?? 'smtp') . '</p>
            <p><strong>SMTP Host:</strong> ' . htmlspecialchars($settings['smtp_host'] ?? '') . '</p>
        </body>
        </html>
        ';
        
        $sent = EmailHelper::sendEmail($test_email, $subject, $message);
        if ($sent) {
            $success = 'Test email sent successfully to ' . htmlspecialchars($test_email) . '!';
        } else {
            $error = 'Failed to send test email. Please check your email settings and try again.';
        }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Email Settings</h1>
    <p>Configure SMTP and email sending options</p>
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

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Email Configuration</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label>Email Method <span style="color: red;">*</span></label>
                <select name="email_method" class="form-control" required>
                    <option value="smtp" <?php echo ($settings['email_method'] ?? 'smtp') === 'smtp' ? 'selected' : ''; ?>>SMTP</option>
                    <option value="mail" <?php echo ($settings['email_method'] ?? '') === 'mail' ? 'selected' : ''; ?>>PHP mail() function</option>
                </select>
                <small class="text-muted">SMTP is recommended for production use</small>
            </div>
            
            <h3 style="margin-top: 30px; margin-bottom: 20px;">SMTP Settings</h3>
            
            <div class="form-group">
                <label>SMTP Host</label>
                <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? 'smtp.gmail.com'); ?>" placeholder="smtp.gmail.com">
            </div>
            
            <div class="form-group">
                <label>SMTP Port</label>
                <input type="number" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>" placeholder="587">
                <small class="text-muted">Common ports: 587 (TLS), 465 (SSL), 25 (unencrypted)</small>
            </div>
            
            <div class="form-group">
                <label>SMTP Username</label>
                <input type="text" name="smtp_username" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" placeholder="your-email@gmail.com">
            </div>
            
            <div class="form-group">
                <label>SMTP Password</label>
                <input type="password" name="smtp_password" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>" placeholder="Your app password">
                <small class="text-muted">For Gmail, use an App Password (not your regular password)</small>
            </div>
            
            <div class="form-group">
                <label>SMTP Encryption</label>
                <select name="smtp_encryption" class="form-control">
                    <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                    <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="" <?php echo empty($settings['smtp_encryption'] ?? '') ? 'selected' : ''; ?>>None</option>
                </select>
            </div>
            
            <h3 style="margin-top: 30px; margin-bottom: 20px;">Sender Information</h3>
            
            <div class="form-group">
                <label>From Email <span style="color: red;">*</span></label>
                <input type="email" name="from_email" class="form-control" value="<?php echo htmlspecialchars($settings['from_email'] ?? 'noreply@example.com'); ?>" required>
            </div>
            
            <div class="form-group">
                <label>From Name</label>
                <input type="text" name="from_name" class="form-control" value="<?php echo htmlspecialchars($settings['from_name'] ?? 'Music Platform'); ?>">
            </div>
            
            <div class="form-group">
                <label>Reply-To Email</label>
                <input type="email" name="reply_to_email" class="form-control" value="<?php echo htmlspecialchars($settings['reply_to_email'] ?? ''); ?>" placeholder="support@example.com">
            </div>
            
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Test Email Configuration</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label>Test Email Address</label>
                <input type="email" name="test_email" class="form-control" value="<?php echo htmlspecialchars($settings['test_email'] ?? ''); ?>" placeholder="your-email@example.com" required>
                <small class="text-muted">Enter an email address to send a test email</small>
            </div>
            <button type="submit" name="send_test_email" class="btn btn-success">Send Test Email</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>


