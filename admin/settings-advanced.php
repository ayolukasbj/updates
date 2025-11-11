<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'site_settings':
                $site_name = trim($_POST['site_name'] ?? '');
                $site_tagline = trim($_POST['site_tagline'] ?? '');
                $show_site_name = isset($_POST['show_site_name']) ? '1' : '0';
                $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
                $disable_copy = isset($_POST['disable_copy']) ? 1 : 0;
                
                // Save settings
                saveSetting('site_name', $site_name);
                saveSetting('site_tagline', $site_tagline);
                saveSetting('show_site_name', $show_site_name);
                saveSetting('maintenance_mode', $maintenance_mode);
                saveSetting('disable_copy', $disable_copy);
                
                // Update config.php
                updateConfigFile('SITE_NAME', $site_name);
                
                $success = 'Site settings saved successfully!';
                break;
                
            case 'upload_logo':
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/branding/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                    $filename = 'logo_' . time() . '.' . $file_ext;
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $filepath)) {
                        // Save relative path from root (not including ../)
                        // This path will be used with BASE_PATH in display
                        $relativePath = 'uploads/branding/' . $filename;
                        saveSetting('site_logo', $relativePath);
                        $success = 'Logo uploaded successfully!';
                    } else {
                        $error = 'Failed to upload logo. Please check file permissions.';
                    }
                }
                break;
                
            case 'upload_favicon':
                if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/branding/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_ext = pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION);
                    $filename = 'favicon_' . time() . '.' . $file_ext;
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['favicon']['tmp_name'], $filepath)) {
                        // Save relative path from root (same as logo)
                        $relativePath = 'uploads/branding/' . $filename;
                        saveSetting('site_favicon', $relativePath);
                        $success = 'Favicon uploaded successfully!';
                    } else {
                        $error = 'Failed to upload favicon. Please check file permissions.';
                    }
                }
                break;
                
            case 'default_cover':
                if (isset($_FILES['default_cover']) && $_FILES['default_cover']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/branding/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_ext = pathinfo($_FILES['default_cover']['name'], PATHINFO_EXTENSION);
                    $filename = 'default_artist_cover_' . time() . '.' . $file_ext;
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['default_cover']['tmp_name'], $filepath)) {
                        saveSetting('default_artist_cover', $filepath);
                        $success = 'Default artist cover uploaded successfully!';
                    } else {
                        $error = 'Failed to upload default cover.';
                    }
                }
                break;
                
            case 'email_settings':
                $smtp_host = trim($_POST['smtp_host'] ?? '');
                $smtp_port = trim($_POST['smtp_port'] ?? '');
                $smtp_username = trim($_POST['smtp_username'] ?? '');
                $smtp_password = trim($_POST['smtp_password'] ?? '');
                $from_email = trim($_POST['from_email'] ?? '');
                $from_name = trim($_POST['from_name'] ?? '');
                
                saveSetting('smtp_host', $smtp_host);
                saveSetting('smtp_port', $smtp_port);
                saveSetting('smtp_username', $smtp_username);
                if (!empty($smtp_password)) {
                    saveSetting('smtp_password', base64_encode($smtp_password));
                }
                saveSetting('from_email', $from_email);
                saveSetting('from_name', $from_name);
                
                $success = 'Email settings saved successfully!';
                break;
                
            case 'social_links':
                saveSetting('social_facebook', trim($_POST['facebook'] ?? ''));
                saveSetting('social_twitter', trim($_POST['twitter'] ?? ''));
                saveSetting('social_instagram', trim($_POST['instagram'] ?? ''));
                saveSetting('social_youtube', trim($_POST['youtube'] ?? ''));
                saveSetting('social_tiktok', trim($_POST['tiktok'] ?? ''));
                
                $success = 'Social media links saved successfully!';
                break;
                
            case 'upload_settings':
                saveSetting('max_upload_size', intval($_POST['max_upload_size'] ?? 50));
                saveSetting('allowed_audio_formats', trim($_POST['allowed_audio_formats'] ?? 'mp3,wav,ogg'));
                saveSetting('allowed_image_formats', trim($_POST['allowed_image_formats'] ?? 'jpg,jpeg,png,gif'));
                saveSetting('require_approval', isset($_POST['require_approval']) ? 1 : 0);
                
                $success = 'Upload settings saved successfully!';
                break;
                
            case 'seo_settings':
                saveSetting('meta_description', trim($_POST['meta_description'] ?? ''));
                saveSetting('meta_keywords', trim($_POST['meta_keywords'] ?? ''));
                saveSetting('google_analytics', trim($_POST['google_analytics'] ?? ''));
                saveSetting('facebook_pixel', trim($_POST['facebook_pixel'] ?? ''));
                
                $success = 'SEO settings saved successfully!';
                break;
                
            case 'legal_pages':
                saveSetting('terms_of_service', trim($_POST['terms_of_service'] ?? ''));
                saveSetting('privacy_policy', trim($_POST['privacy_policy'] ?? ''));
                
                $success = 'Legal pages saved successfully!';
                break;
                
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Helper functions
function saveSetting($key, $value) {
    global $conn;
    
    // Create settings table if it doesn't exist
    $conn->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(255) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    $stmt = $conn->prepare("
        INSERT INTO settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    $stmt->execute([$key, $value, $value]);
}

function getSetting($key, $default = '') {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function updateConfigFile($key, $value) {
    $config_file = '../config/config.php';
    if (file_exists($config_file)) {
        $content = file_get_contents($config_file);
        $pattern = "/define\('$key',\s*'[^']*'\);/";
        $replacement = "define('$key', '" . addslashes($value) . "');";
        $content = preg_replace($pattern, $replacement, $content);
        file_put_contents($config_file, $content);
        
    }
}

// Get current settings
$current_settings = [
    'site_name' => getSetting('site_name', 'Music Platform'),
    'site_tagline' => getSetting('site_tagline', 'Your Music Platform'),
    'show_site_name' => getSetting('show_site_name', 1),
    'maintenance_mode' => getSetting('maintenance_mode', 0),
    'disable_copy' => getSetting('disable_copy', 1), // Default to enabled
    'site_logo' => getSetting('site_logo', ''),
    'site_favicon' => getSetting('site_favicon', ''),
    'default_artist_cover' => getSetting('default_artist_cover', ''),
    'smtp_host' => getSetting('smtp_host', ''),
    'smtp_port' => getSetting('smtp_port', '587'),
    'smtp_username' => getSetting('smtp_username', ''),
    'from_email' => getSetting('from_email', ''),
    'from_name' => getSetting('from_name', ''),
    'social_facebook' => getSetting('social_facebook', ''),
    'social_twitter' => getSetting('social_twitter', ''),
    'social_instagram' => getSetting('social_instagram', ''),
    'social_youtube' => getSetting('social_youtube', ''),
    'social_tiktok' => getSetting('social_tiktok', ''),
    'max_upload_size' => getSetting('max_upload_size', 50),
    'allowed_audio_formats' => getSetting('allowed_audio_formats', 'mp3,wav,ogg'),
    'allowed_image_formats' => getSetting('allowed_image_formats', 'jpg,jpeg,png,gif'),
    'require_approval' => getSetting('require_approval', 0),
    'meta_description' => getSetting('meta_description', ''),
    'meta_keywords' => getSetting('meta_keywords', ''),
    'google_analytics' => getSetting('google_analytics', ''),
    'facebook_pixel' => getSetting('facebook_pixel', ''),
    'terms_of_service' => getSetting('terms_of_service', ''),
    'privacy_policy' => getSetting('privacy_policy', ''),
];

$page_title = 'Advanced Settings';
include 'includes/header.php';
?>

<style>
    .settings-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
        border-bottom: 2px solid #e5e7eb;
        overflow-x: auto;
    }
    
    .tab-btn {
        padding: 12px 24px;
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        font-weight: 600;
        color: #666;
        transition: all 0.3s;
        white-space: nowrap;
    }
    
    .tab-btn:hover {
        color: #1f2937;
    }
    
    .tab-btn.active {
        color: #3b82f6;
        border-bottom-color: #3b82f6;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .upload-preview {
        max-width: 200px;
        max-height: 200px;
        margin-top: 10px;
        border-radius: 8px;
        border: 2px solid #e5e7eb;
        padding: 5px;
    }
    
    .file-upload-box {
        border: 2px dashed #d1d5db;
        border-radius: 8px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        margin-bottom: 15px;
    }
    
    .file-upload-box:hover {
        border-color: #3b82f6;
        background: #f9fafb;
    }
    
    .file-upload-box i {
        font-size: 48px;
        color: #9ca3af;
        margin-bottom: 10px;
    }
    
    .setting-row {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 20px;
        margin-bottom: 25px;
        align-items: start;
    }
    
    .setting-label {
        font-weight: 600;
        color: #374151;
        padding-top: 8px;
    }
    
    .setting-description {
        font-size: 13px;
        color: #6b7280;
        margin-top: 5px;
    }
</style>

<div class="page-header">
    <h1><i class="fas fa-cog"></i> Advanced Settings</h1>
    <p>Configure your platform settings, branding, and features</p>
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

<!-- Settings Tabs -->
<div class="settings-tabs">
    <button class="tab-btn active" onclick="switchTab('general')">
        <i class="fas fa-globe"></i> General
    </button>
    <button class="tab-btn" onclick="switchTab('branding')">
        <i class="fas fa-palette"></i> Branding
    </button>
    <button class="tab-btn" onclick="switchTab('email')">
        <i class="fas fa-envelope"></i> Email
    </button>
    <button class="tab-btn" onclick="switchTab('social')">
        <i class="fas fa-share-alt"></i> Social Media
    </button>
    <button class="tab-btn" onclick="switchTab('uploads')">
        <i class="fas fa-upload"></i> Uploads
    </button>
    <button class="tab-btn" onclick="switchTab('seo')">
        <i class="fas fa-search"></i> SEO
    </button>
    <button class="tab-btn" onclick="switchTab('legal')">
        <i class="fas fa-gavel"></i> Legal Pages
    </button>
</div>

<!-- General Settings Tab -->
<div id="general" class="tab-content active">
    <div class="card">
        <div class="card-header">
            <h2>General Settings</h2>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="site_settings">
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">Site Name</div>
                        <div class="setting-description">The name of your music platform</div>
                    </div>
                    <input type="text" name="site_name" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['site_name']); ?>" required>
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">Site Tagline</div>
                        <div class="setting-description">A short description of your platform</div>
                    </div>
                    <input type="text" name="site_tagline" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['site_tagline']); ?>">
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">Show Site Name in Header</div>
                        <div class="setting-description">Display the site name next to the logo</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="show_site_name" 
                               <?php echo $current_settings['show_site_name'] ? 'checked' : ''; ?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">Maintenance Mode</div>
                        <div class="setting-description">Put the site in maintenance mode (only admins can access)</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="maintenance_mode" 
                               <?php echo $current_settings['maintenance_mode'] ? 'checked' : ''; ?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">Disable Copy/Right Click Protection</div>
                        <div class="setting-description">
                            When enabled, prevents users from right-clicking, copying text, selecting content, and accessing developer tools.
                            <br><small style="color: #ef4444;">Note: This provides basic protection but determined users can bypass it. It's not 100% secure.</small>
                        </div>
                    </div>
                    <div>
                        <label class="switch">
                            <input type="checkbox" name="disable_copy" 
                                   <?php echo ($current_settings['disable_copy'] == 1) ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                        <div style="margin-top: 10px; font-size: 13px; color: #6b7280;">
                            <i class="fas fa-info-circle"></i> 
                            Current status: <strong><?php echo ($current_settings['disable_copy'] == 1) ? 'Enabled' : 'Disabled'; ?></strong>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save General Settings
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Branding Tab -->
<div id="branding" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2>Branding & Visual Identity</h2>
        </div>
        <div class="card-body">
            <!-- Site Logo -->
            <h3 style="margin-bottom: 20px;"><i class="fas fa-image"></i> Site Logo</h3>
            <form method="POST" enctype="multipart/form-data" style="margin-bottom: 40px;">
                <input type="hidden" name="action" value="upload_logo">
                
                <div class="file-upload-box" onclick="document.getElementById('logo').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p><strong>Click to upload logo</strong></p>
                    <p style="font-size: 13px; color: #6b7280;">PNG, JPG, SVG (Recommended: 200x60px)</p>
                    <input type="file" id="logo" name="logo" accept="image/*" style="display: none;" 
                           onchange="previewImage(this, 'logo-preview')">
                </div>
                
                <?php if ($current_settings['site_logo']): ?>
                    <div>
                        <strong>Current Logo:</strong><br>
                        <?php 
                        // Use BASE_PATH for proper URL generation
                        $logo_path = $current_settings['site_logo'];
                        // Remove ../ if present
                        $logo_path = str_replace('../', '', $logo_path);
                        // If it's a relative path, prepend BASE_PATH
                        if (strpos($logo_path, 'http') !== 0 && strpos($logo_path, '/') !== 0) {
                            $logo_path = (defined('BASE_PATH') ? BASE_PATH : '/') . $logo_path;
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($logo_path); ?>" 
                             class="upload-preview" id="logo-preview" 
                             onerror="this.style.display='none'; console.error('Logo not found:', this.src);">
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload Logo
                </button>
            </form>
            
            <!-- Favicon -->
            <h3 style="margin-bottom: 20px;"><i class="fas fa-bookmark"></i> Favicon</h3>
            <form method="POST" enctype="multipart/form-data" style="margin-bottom: 40px;">
                <input type="hidden" name="action" value="upload_favicon">
                
                <div class="file-upload-box" onclick="document.getElementById('favicon').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p><strong>Click to upload favicon</strong></p>
                    <p style="font-size: 13px; color: #6b7280;">ICO, PNG (Recommended: 32x32px)</p>
                    <input type="file" id="favicon" name="favicon" accept="image/*" style="display: none;" 
                           onchange="previewImage(this, 'favicon-preview')">
                </div>
                
                <?php if ($current_settings['site_favicon']): ?>
                    <div>
                        <strong>Current Favicon:</strong><br>
                        <?php 
                        // Use BASE_PATH for proper URL generation
                        $favicon_path = $current_settings['site_favicon'];
                        // Remove ../ if present
                        $favicon_path = str_replace('../', '', $favicon_path);
                        // If it's a relative path, prepend BASE_PATH
                        if (strpos($favicon_path, 'http') !== 0 && strpos($favicon_path, '/') !== 0) {
                            $favicon_path = (defined('BASE_PATH') ? BASE_PATH : '/') . $favicon_path;
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($favicon_path); ?>" 
                             class="upload-preview" id="favicon-preview" style="max-width: 64px;"
                             onerror="this.style.display='none'; console.error('Favicon not found:', this.src);">
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload Favicon
                </button>
            </form>
            
            <!-- Default Artist Cover -->
            <h3 style="margin-bottom: 20px;"><i class="fas fa-user-circle"></i> Default Artist Cover Art</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="default_cover">
                
                <div class="file-upload-box" onclick="document.getElementById('default_cover').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p><strong>Click to upload default artist cover</strong></p>
                    <p style="font-size: 13px; color: #6b7280;">This will be used for all artist profiles without a custom cover (Recommended: 1200x400px)</p>
                    <input type="file" id="default_cover" name="default_cover" accept="image/*" style="display: none;" 
                           onchange="previewImage(this, 'cover-preview')">
                </div>
                
                <?php if ($current_settings['default_artist_cover']): ?>
                    <div>
                        <strong>Current Default Cover:</strong><br>
                        <img src="<?php echo htmlspecialchars($current_settings['default_artist_cover']); ?>" 
                             class="upload-preview" id="cover-preview" style="max-width: 400px;">
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload Default Cover
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Email Settings Tab -->
<div id="email" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2>Email Configuration</h2>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="email_settings">
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">SMTP Host</div>
                        <div class="setting-description">Your mail server address (e.g., smtp.gmail.com)</div>
                    </div>
                    <input type="text" name="smtp_host" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['smtp_host']); ?>" 
                           placeholder="smtp.gmail.com">
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">SMTP Port</div>
                        <div class="setting-description">Usually 587 for TLS or 465 for SSL</div>
                    </div>
                    <input type="number" name="smtp_port" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['smtp_port']); ?>" 
                           placeholder="587">
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">SMTP Username</div>
                        <div class="setting-description">Your email address</div>
                    </div>
                    <input type="email" name="smtp_username" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['smtp_username']); ?>" 
                           placeholder="noreply@yoursite.com">
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">SMTP Password</div>
                        <div class="setting-description">Your email password or app password</div>
                    </div>
                    <input type="password" name="smtp_password" class="form-control" 
                           placeholder="••••••••">
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">From Email</div>
                        <div class="setting-description">Email address that emails will come from</div>
                    </div>
                    <input type="email" name="from_email" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['from_email']); ?>" 
                           placeholder="noreply@yoursite.com">
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">From Name</div>
                        <div class="setting-description">Name that emails will come from</div>
                    </div>
                    <input type="text" name="from_name" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['from_name']); ?>" 
                           placeholder="<?php echo htmlspecialchars($current_settings['site_name']); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Email Settings
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Social Media Tab -->
<div id="social" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2>Social Media Links</h2>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="social_links">
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label"><i class="fab fa-facebook"></i> Facebook</div>
                        <div class="setting-description">Your Facebook page URL</div>
                    </div>
                    <input type="url" name="facebook" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['social_facebook']); ?>" 
                           placeholder="https://facebook.com/yourpage">
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label"><i class="fab fa-twitter"></i> Twitter</div>
                        <div class="setting-description">Your Twitter/X profile URL</div>
                    </div>
                    <input type="url" name="twitter" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['social_twitter']); ?>" 
                           placeholder="https://twitter.com/yourprofile">
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label"><i class="fab fa-instagram"></i> Instagram</div>
                        <div class="setting-description">Your Instagram profile URL</div>
                    </div>
                    <input type="url" name="instagram" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['social_instagram']); ?>" 
                           placeholder="https://instagram.com/yourprofile">
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label"><i class="fab fa-youtube"></i> YouTube</div>
                        <div class="setting-description">Your YouTube channel URL</div>
                    </div>
                    <input type="url" name="youtube" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['social_youtube']); ?>" 
                           placeholder="https://youtube.com/channel/yourchannel">
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label"><i class="fab fa-tiktok"></i> TikTok</div>
                        <div class="setting-description">Your TikTok profile URL</div>
                    </div>
                    <input type="url" name="tiktok" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['social_tiktok']); ?>" 
                           placeholder="https://tiktok.com/@yourprofile">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Social Links
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Upload Settings Tab -->
<div id="uploads" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2>Upload Settings</h2>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="upload_settings">
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">Max Upload Size (MB)</div>
                        <div class="setting-description">Maximum file size for uploads</div>
                    </div>
                    <input type="number" name="max_upload_size" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['max_upload_size']); ?>" 
                           min="1" max="500">
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">Allowed Audio Formats</div>
                        <div class="setting-description">Comma-separated list (e.g., mp3,wav,ogg)</div>
                    </div>
                    <input type="text" name="allowed_audio_formats" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['allowed_audio_formats']); ?>">
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">Allowed Image Formats</div>
                        <div class="setting-description">Comma-separated list (e.g., jpg,png,gif)</div>
                    </div>
                    <input type="text" name="allowed_image_formats" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['allowed_image_formats']); ?>">
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">Require Admin Approval</div>
                        <div class="setting-description">New uploads require admin approval before going live</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="require_approval" 
                               <?php echo $current_settings['require_approval'] ? 'checked' : ''; ?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Upload Settings
                </button>
            </form>
        </div>
    </div>
</div>

<!-- SEO Settings Tab -->
<div id="seo" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2>SEO & Analytics</h2>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="seo_settings">
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">Meta Description</div>
                        <div class="setting-description">Description for search engines (160 characters max)</div>
                    </div>
                    <textarea name="meta_description" class="form-control" rows="3" 
                              maxlength="160"><?php echo htmlspecialchars($current_settings['meta_description']); ?></textarea>
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">Meta Keywords</div>
                        <div class="setting-description">Comma-separated keywords for SEO</div>
                    </div>
                    <input type="text" name="meta_keywords" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['meta_keywords']); ?>" 
                           placeholder="music, artists, songs, streaming">
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">Google Analytics ID</div>
                        <div class="setting-description">Your Google Analytics tracking ID (e.g., G-XXXXXXXXXX)</div>
                    </div>
                    <input type="text" name="google_analytics" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['google_analytics']); ?>" 
                           placeholder="G-XXXXXXXXXX">
                </div>
                
                <div class="setting-row">
                    <div>
                        <div class="setting-label">Facebook Pixel ID</div>
                        <div class="setting-description">Your Facebook Pixel ID for tracking</div>
                    </div>
                    <input type="text" name="facebook_pixel" class="form-control" 
                           value="<?php echo htmlspecialchars($current_settings['facebook_pixel']); ?>" 
                           placeholder="123456789012345">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save SEO Settings
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Legal Pages Tab -->
<div id="legal" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2>Legal Pages</h2>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="legal_pages">
                
                <div class="setting-row" style="grid-template-columns: 1fr; margin-bottom: 30px;">
                    <div>
                        <div class="setting-label">Terms of Service</div>
                        <div class="setting-description">Edit the Terms of Service page content. You can use HTML formatting.</div>
                    </div>
                    <textarea name="terms_of_service" class="form-control" rows="20" 
                              style="font-family: monospace; font-size: 13px;"><?php echo htmlspecialchars($current_settings['terms_of_service']); ?></textarea>
                    <div style="margin-top: 10px;">
                        <a href="../terms-of-service.php" target="_blank" class="btn btn-secondary">
                            <i class="fas fa-external-link-alt"></i> View Terms of Service Page
                        </a>
                    </div>
                </div>
                
                <div class="setting-row" style="grid-template-columns: 1fr; margin-bottom: 30px;">
                    <div>
                        <div class="setting-label">Privacy Policy</div>
                        <div class="setting-description">Edit the Privacy Policy page content. You can use HTML formatting.</div>
                    </div>
                    <textarea name="privacy_policy" class="form-control" rows="20" 
                              style="font-family: monospace; font-size: 13px;"><?php echo htmlspecialchars($current_settings['privacy_policy']); ?></textarea>
                    <div style="margin-top: 10px;">
                        <a href="../privacy-policy.php" target="_blank" class="btn btn-secondary">
                            <i class="fas fa-external-link-alt"></i> View Privacy Policy Page
                        </a>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Legal Pages
                </button>
            </form>
        </div>
    </div>
</div>


<script>
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName).classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
}

function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById(previewId);
            if (preview) {
                preview.src = e.target.result;
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include 'includes/footer.php'; ?>

