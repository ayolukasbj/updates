<?php
// admin/settings-general.php
// General Site Settings

require_once 'auth-check.php';
require_once '../config/database.php';
require_once '../includes/settings.php';

$page_title = 'General Settings';

$db = new Database();
$conn = $db->getConnection();
$settings_manager = new SettingsManager();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $site_name = trim($_POST['site_name'] ?? '');
        $site_slogan = trim($_POST['site_slogan'] ?? '');
        $site_description = trim($_POST['site_description'] ?? '');
        $site_logo = trim($_POST['site_logo'] ?? '');
        $site_favicon = trim($_POST['site_favicon'] ?? '');
        
        if (empty($site_name)) {
            $error = 'Site name is required!';
        } else {
            // Save settings
            SettingsManager::set('site_name', $site_name);
            SettingsManager::set('site_slogan', $site_slogan);
            SettingsManager::set('site_description', $site_description);
            SettingsManager::set('site_logo', $site_logo);
            SettingsManager::set('site_favicon', $site_favicon);
            
            $success = 'Settings saved successfully!';
            logAdminActivity($_SESSION['user_id'], 'update_settings', 'settings', 0, "Updated general settings");
        }
    } catch (Exception $e) {
        $error = 'Error saving settings: ' . $e->getMessage();
    }
}

// Handle logo upload
if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/settings/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_ext = pathinfo($_FILES['logo_upload']['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    
    if (in_array(strtolower($file_ext), $allowed)) {
        $file_name = 'logo.' . $file_ext;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['logo_upload']['tmp_name'], $file_path)) {
            SettingsManager::set('site_logo', 'uploads/settings/' . $file_name);
            $success = 'Logo uploaded successfully!';
        } else {
            $error = 'Failed to upload logo';
        }
    } else {
        $error = 'Invalid file type. Allowed: ' . implode(', ', $allowed);
    }
}

// Get current settings
$current_settings = SettingsManager::getAll();

include 'includes/header.php';
?>

<div class="page-header">
    <h1>General Settings</h1>
    <p>Configure your site's basic information and branding</p>
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
        <h2>Site Information</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label>Site Name <span style="color: red;">*</span></label>
                <input type="text" name="site_name" class="form-control" required
                    value="<?php echo htmlspecialchars($current_settings['site_name']); ?>">
            </div>
            
            <div class="form-group">
                <label>Site Slogan</label>
                <input type="text" name="site_slogan" class="form-control"
                    value="<?php echo htmlspecialchars($current_settings['site_slogan']); ?>"
                    placeholder="Your catchy slogan">
            </div>
            
            <div class="form-group">
                <label>Site Description</label>
                <textarea name="site_description" class="form-control" rows="3"
                    placeholder="Brief description of your platform"><?php echo htmlspecialchars($current_settings['site_description']); ?></textarea>
                <small class="text-muted">Used for SEO meta description</small>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Branding</h2>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Site Logo</label>
                <?php if (!empty($current_settings['site_logo'])): ?>
                <div style="margin-bottom: 10px;">
                    <img src="../<?php echo htmlspecialchars($current_settings['site_logo']); ?>" 
                        alt="Current Logo" style="max-height: 100px; border: 1px solid #ddd; padding: 5px;">
                    <br><small>Current logo</small>
                </div>
                <?php endif; ?>
                <input type="file" name="logo_upload" class="form-control" accept="image/*">
                <small class="text-muted">Upload a new logo (JPG, PNG, GIF, WebP, SVG)</small>
            </div>
            
            <div class="form-group">
                <label>Logo URL (Alternative)</label>
                <input type="text" name="site_logo" class="form-control"
                    value="<?php echo htmlspecialchars($current_settings['site_logo']); ?>"
                    placeholder="uploads/settings/logo.png or full URL">
                <small class="text-muted">Or enter logo path/URL directly</small>
            </div>
            
            <div class="form-group">
                <label>Favicon URL</label>
                <input type="text" name="site_favicon" class="form-control"
                    value="<?php echo htmlspecialchars($current_settings['site_favicon']); ?>"
                    placeholder="assets/images/favicon.ico">
                <small class="text-muted">Path to favicon file</small>
            </div>
            
            <button type="submit" name="upload_logo" class="btn btn-primary">Save Branding</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>


