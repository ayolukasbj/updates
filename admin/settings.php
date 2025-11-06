<?php
require_once 'auth-check.php';
require_once '../config/database.php';

if (!isSuperAdmin()) {
    header('Location: index.php');
    exit;
}

$page_title = 'System Settings';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = $_POST['settings'] ?? [];
    
    try {
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
        }
        
        $success = 'Settings updated successfully';
        logAdminActivity($_SESSION['user_id'], 'update_settings', 'system', null, 'Updated system settings');
        
    } catch (Exception $e) {
        $error = 'Error updating settings: ' . $e->getMessage();
    }
}

// Get current settings (check if description column exists)
$settings = [];
try {
    $checkStmt = $conn->query("SHOW COLUMNS FROM settings LIKE 'description'");
    $has_description = $checkStmt->rowCount() > 0;
    
    if ($has_description) {
        $stmt = $conn->query("SELECT setting_key, setting_value, description FROM settings");
    } else {
        $stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
    }
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row;
    }
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>System Settings</h1>
    <p>Configure platform settings and preferences</p>
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

<form method="POST">
    <!-- General Settings -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">
            <h2>General Settings</h2>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label>Site Name</label>
                <input type="text" name="settings[site_name]" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['site_name']['setting_value'] ?? 'MusicStream'); ?>">
                <small style="color: #6b7280;">Display name of your website</small>
            </div>
            
            <div class="form-group">
                <label>Site Slogan / Tagline</label>
                <input type="text" name="settings[site_tagline]" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['site_tagline']['setting_value'] ?? 'Your Ultimate Music Streaming Platform'); ?>">
                <small style="color: #6b7280;">Tagline or slogan displayed on the homepage and About Us page</small>
            </div>
            
            <div class="form-group">
                <label>Enable Registration</label>
                <select name="settings[enable_registration]" class="form-control">
                    <option value="true" <?php echo ($settings['enable_registration']['setting_value'] ?? 'true') === 'true' ? 'selected' : ''; ?>>Enabled</option>
                    <option value="false" <?php echo ($settings['enable_registration']['setting_value'] ?? 'true') === 'false' ? 'selected' : ''; ?>>Disabled</option>
                </select>
                <small style="color: #6b7280;">Allow new users to register</small>
            </div>
            
            <div class="form-group">
                <label>Maintenance Mode</label>
                <select name="settings[maintenance_mode]" class="form-control">
                    <option value="false" <?php echo ($settings['maintenance_mode']['setting_value'] ?? 'false') === 'false' ? 'selected' : ''; ?>>Disabled</option>
                    <option value="true" <?php echo ($settings['maintenance_mode']['setting_value'] ?? 'false') === 'true' ? 'selected' : ''; ?>>Enabled</option>
                </select>
                <small style="color: #6b7280;">Put site in maintenance mode</small>
            </div>
            
            <div class="form-group">
                <label>Require Email Verification on Registration</label>
                <select name="settings[require_email_verification]" class="form-control">
                    <option value="true" <?php echo ($settings['require_email_verification']['setting_value'] ?? 'true') === 'true' ? 'selected' : ''; ?>>Required</option>
                    <option value="false" <?php echo ($settings['require_email_verification']['setting_value'] ?? 'true') === 'false' ? 'selected' : ''; ?>>Not Required</option>
                </select>
                <small style="color: #6b7280;">If enabled, users must verify their email before logging in. If disabled, users can login immediately after registration.</small>
            </div>
        </div>
    </div>
    
    <!-- Upload Settings -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">
            <h2>Upload Settings</h2>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label>Max Upload Size (bytes)</label>
                <input type="number" name="settings[max_upload_size]" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['max_upload_size']['setting_value'] ?? '50000000'); ?>">
                <small style="color: #6b7280;">Maximum file size for uploads (default: 50MB = 50000000 bytes)</small>
            </div>
            
            <div class="form-group">
                <label>Allowed Audio Formats</label>
                <input type="text" name="settings[allowed_formats]" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['allowed_formats']['setting_value'] ?? 'mp3,wav,flac,aac'); ?>">
                <small style="color: #6b7280;">Comma-separated list of allowed formats</small>
            </div>
        </div>
    </div>
    
    
    <!-- Streaming Settings -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">
            <h2>Streaming Settings</h2>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label>Default Streaming Quality</label>
                <select name="settings[streaming_quality]" class="form-control">
                    <option value="low" <?php echo ($settings['streaming_quality']['setting_value'] ?? 'high') === 'low' ? 'selected' : ''; ?>>Low (128kbps)</option>
                    <option value="medium" <?php echo ($settings['streaming_quality']['setting_value'] ?? 'high') === 'medium' ? 'selected' : ''; ?>>Medium (192kbps)</option>
                    <option value="high" <?php echo ($settings['streaming_quality']['setting_value'] ?? 'high') === 'high' ? 'selected' : ''; ?>>High (320kbps)</option>
                </select>
                <small style="color: #6b7280;">Default audio quality for streaming</small>
            </div>
        </div>
    </div>
    
    <!-- About Us Page Management -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">
            <h2>About Us Page</h2>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label>About Us Content</label>
                <textarea name="settings[about_us_content]" class="form-control" rows="10" 
                          style="min-height: 300px;"><?php echo htmlspecialchars($settings['about_us_content']['setting_value'] ?? ''); ?></textarea>
                <small style="color: #6b7280;">Custom content for the About Us page. If empty, default content will be displayed. You can use basic HTML tags.</small>
            </div>
        </div>
    </div>
    
    <!-- News & Ads Settings -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">
            <h2>News & Advertisement Settings</h2>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label>Paragraphs Between Ads</label>
                <input type="number" name="settings[ad_paragraph_spacing]" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['ad_paragraph_spacing']['setting_value'] ?? '5'); ?>" 
                       min="2" max="20">
                <small style="color: #6b7280;">Number of paragraphs between ad placements in news articles (default: 5). Minimum: 2, Maximum: 20.</small>
            </div>
        </div>
    </div>
    
    <!-- Footer Settings -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">
            <h2>Footer Settings</h2>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label>Contact Phone Number</label>
                <input type="text" name="settings[footer_phone]" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['footer_phone']['setting_value'] ?? ''); ?>" 
                       placeholder="e.g., +1234567890 or 0767088992">
                <small style="color: #6b7280;">Phone number displayed in the footer. Leave empty to hide.</small>
            </div>
            
            <div class="form-group">
                <label>Contact Email Address</label>
                <input type="email" name="settings[footer_email]" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['footer_email']['setting_value'] ?? 'admin@example.com'); ?>" 
                       placeholder="admin@example.com">
                <small style="color: #6b7280;">Email address displayed in the footer. Leave empty to hide.</small>
            </div>
            
            <div class="form-group">
                <label>Company/Property Text</label>
                <input type="text" name="settings[footer_company_text]" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['footer_company_text']['setting_value'] ?? 'This website is the property of Music Platform'); ?>" 
                       placeholder="e.g., This website is the property of Your Company Name">
                <small style="color: #6b7280;">Company or property ownership text displayed in the footer. Leave empty to hide.</small>
            </div>
            
            <div class="form-group">
                <label>Contact/Advertising Text</label>
                <input type="text" name="settings[footer_contact_text]" class="form-control" 
                       value="<?php echo htmlspecialchars($settings['footer_contact_text']['setting_value'] ?? 'Contact us for more information and advertising'); ?>" 
                       placeholder="e.g., Contact us for more information and advertising">
                <small style="color: #6b7280;">Additional contact or advertising text displayed in the footer. Leave empty to hide.</small>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Settings
            </button>
            <a href="index.php" class="btn btn-warning">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>

