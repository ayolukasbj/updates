<?php
/**
 * MP3 Tagger Settings Page
 */

// Check if accessed directly
if (!defined('MP3_TAGGER_PLUGIN_DIR')) {
    // Define if not set
    define('MP3_TAGGER_PLUGIN_DIR', __DIR__ . '/../');
}

// Load required files
if (file_exists(__DIR__ . '/../../../includes/plugin-api.php')) {
    require_once __DIR__ . '/../../../includes/plugin-api.php';
}
require_once __DIR__ . '/../../../config/database.php';

// Include admin header if not already included
if (!function_exists('get_db_connection')) {
    function get_db_connection() {
        require_once __DIR__ . '/../../../config/database.php';
        $db = new Database();
        return $db->getConnection();
    }
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $conn = get_db_connection();
        
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
        
        foreach ($templates as $key => $value) {
            update_option($key, $value);
        }
        
        $enable_auto_tagging = isset($_POST['enable_auto_tagging']) ? '1' : '0';
        update_option('id3_auto_tagging_enabled', $enable_auto_tagging);
        
        $success = 'ID3 tag settings saved successfully!';
    } catch (Exception $e) {
        $error = 'Error saving settings: ' . $e->getMessage();
    }
}

// Get current settings
$current_templates = [
    'title' => get_option('id3_tag_title', '{TITLE} | {SITE_NAME}'),
    'artist' => get_option('id3_tag_artist', '{ARTIST} | {SITE_NAME}'),
    'album' => get_option('id3_tag_album', '{SITE_NAME}'),
    'comment' => get_option('id3_tag_comment', 'Downloaded from {SITE_NAME}'),
    'band' => get_option('id3_tag_band', '{SITE_NAME}'),
    'publisher' => get_option('id3_tag_publisher', '{SITE_NAME}'),
    'composer' => get_option('id3_tag_composer', '{SITE_NAME}'),
    'original_artist' => get_option('id3_tag_original_artist', '{UPLOADER}'),
    'copyright' => get_option('id3_tag_copyright', '{SITE_NAME}'),
    'encoded_by' => get_option('id3_tag_encoded_by', '{SITE_NAME}'),
    'filename' => get_option('id3_tag_filename', '{TITLE} by {ARTIST} [{SITE_NAME}]'),
];

$enable_auto_tagging = get_option('id3_auto_tagging_enabled', '1');
$tab = $GLOBALS['mp3_tagger_tab'] ?? $_GET['tab'] ?? 'settings';
?>

<!-- Tabs -->
<ul class="nav nav-tabs" style="margin-bottom: 20px;">
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'settings' ? 'active' : ''; ?>" href="?tab=settings">ID3 Tag Settings</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'sync' ? 'active' : ''; ?>" href="?tab=sync">Sync ID3 Tags</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'edit' ? 'active' : ''; ?>" href="?tab=edit">Edit MP3 Tags</a>
    </li>
</ul>

<div class="page-header">
    <h1><i class="fas fa-tag"></i> MP3 Tagger - Settings</h1>
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

<div class="card">
    <div class="card-header">
        <h2>ID3 Tag Settings</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label>
                    <input type="checkbox" name="enable_auto_tagging" value="1" <?php echo $enable_auto_tagging === '1' ? 'checked' : ''; ?>>
                    Enable automatic ID3 tagging for uploaded MP3 files
                </label>
            </div>
            
            <div class="alert alert-info">
                <strong>Available Placeholders:</strong>
                <code>{TITLE}</code>, <code>{ARTIST}</code>, <code>{UPLOADER}</code>, <code>{SITE_NAME}</code>
            </div>
            
            <div class="form-group">
                <label>Title Template</label>
                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($current_templates['title']); ?>">
            </div>
            
            <div class="form-group">
                <label>Artist Template</label>
                <input type="text" name="artist" class="form-control" value="<?php echo htmlspecialchars($current_templates['artist']); ?>">
            </div>
            
            <div class="form-group">
                <label>Album Template</label>
                <input type="text" name="album" class="form-control" value="<?php echo htmlspecialchars($current_templates['album']); ?>">
            </div>
            
            <div class="form-group">
                <label>Comment Template</label>
                <input type="text" name="comment" class="form-control" value="<?php echo htmlspecialchars($current_templates['comment']); ?>">
            </div>
            
            <div class="form-group">
                <label>Filename Template</label>
                <input type="text" name="filename" class="form-control" value="<?php echo htmlspecialchars($current_templates['filename']); ?>">
                <small>Leave empty to keep original filename</small>
            </div>
            
            <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</div>

