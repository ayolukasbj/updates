<?php
// admin/id3-tag-settings.php
// ID3 Tag Settings - Configure automatic MP3 tagging templates

require_once 'auth-check.php';
require_once '../config/database.php';
require_once '../includes/settings.php';

$page_title = 'ID3 Tag Settings';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get tag templates from form
        $templates = [
            'id3_tag_title' => trim($_POST['title'] ?? ''),
            'id3_tag_artist' => trim($_POST['artist'] ?? ''),
            'id3_tag_album' => trim($_POST['album'] ?? ''),
            'id3_tag_comment' => trim($_POST['comment'] ?? ''),
            'id3_tag_band' => trim($_POST['band'] ?? ''),
            'id3_tag_publisher' => trim($_POST['publisher'] ?? ''),
            'id3_tag_composer' => trim($_POST['composer'] ?? ''),
            'id3_tag_original_artist' => trim($_POST['original_artist'] ?? ''),
            'id3_tag_copyright' => trim($_POST['copyright'] ?? ''),
            'id3_tag_encoded_by' => trim($_POST['encoded_by'] ?? ''),
            'id3_tag_filename' => trim($_POST['filename'] ?? ''),
        ];
        
        // Save each template to database
        foreach ($templates as $key => $value) {
            $stmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
        }
        
        // Save enable/disable setting
        $enable_auto_tagging = isset($_POST['enable_auto_tagging']) ? '1' : '0';
        $stmt = $conn->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES ('id3_auto_tagging_enabled', ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$enable_auto_tagging, $enable_auto_tagging]);
        
        $success = 'ID3 tag settings saved successfully!';
        logAdminActivity($_SESSION['user_id'], 'update_settings', 'settings', 0, "Updated ID3 tag settings");
    } catch (Exception $e) {
        $error = 'Error saving settings: ' . $e->getMessage();
        error_log('ID3 tag settings error: ' . $e->getMessage());
    }
}

// Get current settings
$current_templates = [
    'title' => '',
    'artist' => '',
    'album' => '',
    'comment' => '',
    'band' => '',
    'publisher' => '',
    'composer' => '',
    'original_artist' => '',
    'copyright' => '',
    'encoded_by' => '',
    'filename' => '',
];

$enable_auto_tagging = '1';

try {
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'id3_tag_%' OR setting_key = 'id3_auto_tagging_enabled'");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        if ($row['setting_key'] === 'id3_auto_tagging_enabled') {
            $enable_auto_tagging = $row['setting_value'];
        } else {
            $key = str_replace('id3_tag_', '', $row['setting_key']);
            if (isset($current_templates[$key])) {
                $current_templates[$key] = $row['setting_value'];
            }
        }
    }
} catch (Exception $e) {
    error_log('Error loading ID3 tag settings: ' . $e->getMessage());
}

// Set defaults if empty
if (empty($current_templates['title'])) {
    $current_templates['title'] = '{TITLE} | {SITE_NAME}';
}
if (empty($current_templates['artist'])) {
    $current_templates['artist'] = '{ARTIST} | {SITE_NAME}';
}
if (empty($current_templates['album'])) {
    $current_templates['album'] = '{SITE_NAME}';
}
if (empty($current_templates['comment'])) {
    $current_templates['comment'] = 'Downloaded from {SITE_NAME}';
}
if (empty($current_templates['band'])) {
    $current_templates['band'] = '{SITE_NAME}';
}
if (empty($current_templates['publisher'])) {
    $current_templates['publisher'] = '{SITE_NAME}';
}
if (empty($current_templates['composer'])) {
    $current_templates['composer'] = '{SITE_NAME}';
}
if (empty($current_templates['original_artist'])) {
    $current_templates['original_artist'] = '{UPLOADER}';
}
if (empty($current_templates['copyright'])) {
    $current_templates['copyright'] = '{SITE_NAME}';
}
if (empty($current_templates['encoded_by'])) {
    $current_templates['encoded_by'] = '{SITE_NAME}';
}
if (empty($current_templates['filename'])) {
    $current_templates['filename'] = '{TITLE} by {ARTIST} [{SITE_NAME}]';
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>ID3 Tag Settings</h1>
    <p>Configure automatic MP3 tagging templates for uploaded songs</p>
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
        <h2>Auto-Tagging Configuration</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label>
                    <input type="checkbox" name="enable_auto_tagging" value="1" <?php echo $enable_auto_tagging === '1' ? 'checked' : ''; ?>>
                    Enable automatic ID3 tagging for uploaded MP3 files
                </label>
                <small class="text-muted">When enabled, all uploaded MP3 files will be automatically tagged with site branding</small>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                <strong>Available Placeholders:</strong>
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <li><code>{TITLE}</code> - Song title</li>
                    <li><code>{ARTIST}</code> - Artist name</li>
                    <li><code>{UPLOADER}</code> - Uploader name</li>
                    <li><code>{SITE_NAME}</code> - Site name</li>
                </ul>
                <p style="margin-top: 10px;"><strong>Note:</strong> These ID3 tags are embedded in the MP3 file but are NOT displayed on the frontend. The frontend always shows database values.</p>
            </div>
            
            <div class="form-group">
                <label>Title Template</label>
                <input type="text" name="title" class="form-control" 
                    value="<?php echo htmlspecialchars($current_templates['title']); ?>"
                    placeholder="{TITLE} | {SITE_NAME}">
                <small class="text-muted">Template for song title tag</small>
            </div>
            
            <div class="form-group">
                <label>Artist Template</label>
                <input type="text" name="artist" class="form-control" 
                    value="<?php echo htmlspecialchars($current_templates['artist']); ?>"
                    placeholder="{ARTIST} | {SITE_NAME}">
                <small class="text-muted">Template for artist tag</small>
            </div>
            
            <div class="form-group">
                <label>Album Template</label>
                <input type="text" name="album" class="form-control" 
                    value="<?php echo htmlspecialchars($current_templates['album']); ?>"
                    placeholder="{SITE_NAME}">
                <small class="text-muted">Template for album tag</small>
            </div>
            
            <div class="form-group">
                <label>Comment Template</label>
                <input type="text" name="comment" class="form-control" 
                    value="<?php echo htmlspecialchars($current_templates['comment']); ?>"
                    placeholder="Downloaded from {SITE_NAME}">
                <small class="text-muted">Template for comment tag</small>
            </div>
            
            <div class="form-group">
                <label>Band Template</label>
                <input type="text" name="band" class="form-control" 
                    value="<?php echo htmlspecialchars($current_templates['band']); ?>"
                    placeholder="{SITE_NAME}">
                <small class="text-muted">Template for band tag</small>
            </div>
            
            <div class="form-group">
                <label>Publisher Template</label>
                <input type="text" name="publisher" class="form-control" 
                    value="<?php echo htmlspecialchars($current_templates['publisher']); ?>"
                    placeholder="{SITE_NAME}">
                <small class="text-muted">Template for publisher tag</small>
            </div>
            
            <div class="form-group">
                <label>Composer Template</label>
                <input type="text" name="composer" class="form-control" 
                    value="<?php echo htmlspecialchars($current_templates['composer']); ?>"
                    placeholder="{SITE_NAME}">
                <small class="text-muted">Template for composer tag</small>
            </div>
            
            <div class="form-group">
                <label>Original Artist Template</label>
                <input type="text" name="original_artist" class="form-control" 
                    value="<?php echo htmlspecialchars($current_templates['original_artist']); ?>"
                    placeholder="{UPLOADER}">
                <small class="text-muted">Template for original artist tag (usually the uploader)</small>
            </div>
            
            <div class="form-group">
                <label>Copyright Template</label>
                <input type="text" name="copyright" class="form-control" 
                    value="<?php echo htmlspecialchars($current_templates['copyright']); ?>"
                    placeholder="{SITE_NAME}">
                <small class="text-muted">Template for copyright tag</small>
            </div>
            
            <div class="form-group">
                <label>Encoded By Template</label>
                <input type="text" name="encoded_by" class="form-control" 
                    value="<?php echo htmlspecialchars($current_templates['encoded_by']); ?>"
                    placeholder="{SITE_NAME}">
                <small class="text-muted">Template for encoded by tag</small>
            </div>
            
            <div class="form-group">
                <label>Filename Template</label>
                <input type="text" name="filename" class="form-control" 
                    value="<?php echo htmlspecialchars($current_templates['filename']); ?>"
                    placeholder="{TITLE} by {ARTIST} [{SITE_NAME}]">
                <small class="text-muted">Template for renaming uploaded files (leave empty to keep original filename)</small>
            </div>
            
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Important:</strong>
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <li>Album art will be automatically set to your site logo</li>
                    <li>Year and Genre are taken from the upload form</li>
                    <li>File renaming only works for MP3 files</li>
                    <li>These tags are embedded in the MP3 file but NOT displayed on the frontend</li>
                    <li>The frontend always displays values from the database, not from ID3 tags</li>
                </ul>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Settings
            </button>
            <a href="sync-id3-tags.php" class="btn btn-success">
                <i class="fas fa-sync-alt"></i> Sync Existing Files
            </a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Current Site Settings</h2>
    </div>
    <div class="card-body">
        <table class="table">
            <tr>
                <th>Site Name</th>
                <td><?php echo htmlspecialchars(SettingsManager::getSiteName()); ?></td>
            </tr>
            <tr>
                <th>Site Logo</th>
                <td><?php echo htmlspecialchars(SettingsManager::getSiteLogo()); ?></td>
            </tr>
        </table>
        <p class="text-muted">The site logo will be used as album art for all uploaded MP3 files. You can change these settings in <a href="settings-general.php">General Settings</a>.</p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

