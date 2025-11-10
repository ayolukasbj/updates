<?php
// admin/mp3-tagger.php
// MP3 Tagger - ID3 Tag Settings, Sync, and Edit

require_once 'auth-check.php';
require_once '../config/database.php';
require_once '../includes/auto-tagger.php';
require_once '../includes/settings.php';

$page_title = 'MP3 Tagger';

$db = new Database();
$conn = $db->getConnection();

// Get current tab
$tab = $_GET['tab'] ?? 'settings';

// Include the appropriate page based on tab
if ($tab === 'settings') {
    // ID3 Tag Settings
    $success = '';
    $error = '';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
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
        <h1>MP3 Tagger</h1>
    </div>
    
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
                    <small class="text-muted">Template for renaming uploaded files and download filenames (leave empty to keep original filename)</small>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Important:</strong>
                    <ul style="margin-top: 10px; margin-left: 20px;">
                        <li>Album art will be automatically set to your site logo</li>
                        <li>Year and Genre are taken from the upload form</li>
                        <li>File renaming only works for MP3 files</li>
                        <li>Download filenames will use this template format</li>
                        <li>These tags are embedded in the MP3 file but NOT displayed on the frontend</li>
                        <li>The frontend always displays values from the database, not from ID3 tags</li>
                    </ul>
                </div>
                
                <button type="submit" name="save_settings" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </form>
        </div>
    </div>
    
    <div class="card" style="margin-top: 20px;">
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
    
    <?php
} elseif ($tab === 'sync') {
    // Sync ID3 Tags - Include the sync functionality
    $success = '';
    $error = '';
    $results = null;
    $total_songs = 0;
    $processed = 0;
    $success_count = 0;
    $failed_count = 0;
    $skipped_count = 0;

    // Handle continue from previous batch (GET request)
    if (isset($_GET['continue']) && isset($_GET['offset'])) {
        $_POST['sync_all'] = true;
        $_POST['offset'] = (int)$_GET['offset'];
        $tab = 'sync';
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_all'])) {
        try {
            // Increase execution time for large batches
            set_time_limit(0);
            ini_set('max_execution_time', 0);
            
            // Get offset for batch processing (if provided)
            $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
            $batch_size = 50; // Process 50 songs at a time to avoid timeout
            
            // Get songs from database (with limit for batch processing)
            $stmt = $conn->prepare("
                SELECT s.id, s.title, s.file_path, s.genre, s.release_year as year, s.uploaded_by,
                       u.username as uploader_name, a.name as artist_name, s.artist
                FROM songs s
                LEFT JOIN users u ON s.uploaded_by = u.id
                LEFT JOIN artists a ON s.artist_id = a.id
                WHERE s.file_path IS NOT NULL AND s.file_path != ''
                ORDER BY s.id ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$batch_size, $offset]);
            $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($songs)) {
                $error = 'No more songs to process.';
            } else {
                $results = [
                    'success' => [],
                    'failed' => [],
                    'skipped' => []
                ];
                
                foreach ($songs as $song) {
                    $processed++;
                    
                    try {
                        // Get file path
                        $file_path = $song['file_path'];
                        
                        // Resolve full path
                        $full_file_path = strpos($file_path, '/') === 0 || strpos($file_path, ':\\') !== false 
                            ? $file_path 
                            : __DIR__ . '/../' . ltrim($file_path, '/');
                        
                        // Normalize path separators for Windows
                        $full_file_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full_file_path);
                        
                        // Check if file exists
                        if (!file_exists($full_file_path)) {
                            $results['skipped'][] = [
                                'id' => $song['id'],
                                'title' => $song['title'],
                                'reason' => 'File not found: ' . $file_path
                            ];
                            $skipped_count++;
                            continue;
                        }
                        
                        // Check if file is MP3
                        $file_ext = strtolower(pathinfo($full_file_path, PATHINFO_EXTENSION));
                        if ($file_ext !== 'mp3') {
                            $results['skipped'][] = [
                                'id' => $song['id'],
                                'title' => $song['title'],
                                'reason' => 'Not an MP3 file (' . $file_ext . ')'
                            ];
                            $skipped_count++;
                            continue;
                        }
                        
                        // Prepare song data for auto-tagging
                        $artist_name = $song['artist_name'] ?? $song['artist'] ?? $song['uploader_name'] ?? 'Unknown Artist';
                        $uploader_name = $song['uploader_name'] ?? 'Unknown Artist';
                        
                        $tag_song_data = [
                            'title' => $song['title'] ?? '',
                            'artist' => $artist_name,
                            'year' => $song['year'] ?? '',
                            'genre' => $song['genre'] ?? '',
                        ];
                        
                        // Auto-tag the file
                        $tag_result = AutoTagger::tagUploadedSong($full_file_path, $tag_song_data, $uploader_name);
                        
                        if ($tag_result['success']) {
                            $results['success'][] = [
                                'id' => $song['id'],
                                'title' => $song['title'],
                                'file' => $file_path
                            ];
                            $success_count++;
                            
                            // Update database if file was renamed
                            if (!empty($tag_result['new_file_path'])) {
                                try {
                                    $update_file_stmt = $conn->prepare("UPDATE songs SET file_path = ? WHERE id = ?");
                                    $update_file_stmt->execute([$tag_result['new_file_path'], $song['id']]);
                                } catch (Exception $e) {
                                    error_log("Failed to update file path for song ID {$song['id']}: " . $e->getMessage());
                                }
                            }
                        } else {
                            $results['failed'][] = [
                                'id' => $song['id'],
                                'title' => $song['title'],
                                'reason' => 'Tagging failed'
                            ];
                            $failed_count++;
                        }
                    } catch (Exception $e) {
                        $results['failed'][] = [
                            'id' => $song['id'],
                            'title' => $song['title'],
                            'reason' => $e->getMessage()
                        ];
                        $failed_count++;
                        error_log("Error syncing song ID {$song['id']}: " . $e->getMessage());
                    }
                }
                
                $next_offset = $offset + count($songs);
                $has_more = (count($songs) === $batch_size);
                
                if ($has_more) {
                    $success = "Processed batch: " . count($songs) . " songs (Offset: $offset). Success: $success_count, Failed: $failed_count, Skipped: $skipped_count. <a href='?tab=sync&continue=1&offset=$next_offset' class='btn btn-primary mt-2'>Continue processing next batch...</a>";
                } else {
                    $success = "Sync completed! Processed: " . ($offset + count($songs)) . " songs. Success: $success_count, Failed: $failed_count, Skipped: $skipped_count";
                    logAdminActivity($_SESSION['user_id'], 'sync_id3_tags', 'songs', 0, "Synced ID3 tags for " . ($offset + count($songs)) . " songs");
                }
            }
        } catch (Exception $e) {
            $error = 'Error syncing tags: ' . $e->getMessage();
            error_log('ID3 tag sync error: ' . $e->getMessage());
        }
    }

    // Get song count for display
    try {
        $count_stmt = $conn->query("
            SELECT COUNT(*) as total FROM songs 
            WHERE file_path IS NOT NULL AND file_path != ''
        ");
        $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
        $total_songs = $count_result['total'] ?? 0;
    } catch (Exception $e) {
        error_log('Error counting songs: ' . $e->getMessage());
    }

    // Get current tag templates for preview
    $tag_templates = AutoTagger::getTagTemplates();
    $auto_tagging_enabled = !empty($tag_templates);
    
    include 'includes/header.php';
    ?>
    
    <div class="page-header">
        <h1>MP3 Tagger</h1>
    </div>
    
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
    
    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    
    <?php if (!$auto_tagging_enabled): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> 
        <strong>Auto-tagging is disabled!</strong> Please enable it in <a href="?tab=settings">ID3 Tag Settings</a> before syncing.
    </div>
    <?php endif; ?>
    
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">
            <h2>Sync Configuration</h2>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                <strong>What this does:</strong>
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <li>Updates ID3 tags for all existing MP3 files in the database</li>
                    <li>Applies current tag templates from ID3 Tag Settings</li>
                    <li>Embeds site logo as album art</li>
                    <li>Updates filenames if filename template is configured</li>
                    <li>Only processes MP3 files (skips other formats)</li>
                    <li>Skips files that don't exist on the server</li>
                </ul>
            </div>
            
            <table class="table">
                <tr>
                    <th>Total Songs in Database</th>
                    <td><?php echo number_format($total_songs); ?></td>
                </tr>
                <tr>
                    <th>Auto-Tagging Status</th>
                    <td>
                        <?php if ($auto_tagging_enabled): ?>
                            <span class="badge badge-success">Enabled</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Disabled</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Current Tag Templates</th>
                    <td>
                        <small>
                            <strong>Title:</strong> <?php echo htmlspecialchars($tag_templates['title'] ?? 'N/A'); ?><br>
                            <strong>Artist:</strong> <?php echo htmlspecialchars($tag_templates['artist'] ?? 'N/A'); ?><br>
                            <strong>Album:</strong> <?php echo htmlspecialchars($tag_templates['album'] ?? 'N/A'); ?><br>
                            <strong>Filename:</strong> <?php echo htmlspecialchars($tag_templates['filename'] ?? 'N/A'); ?>
                        </small>
                    </td>
                </tr>
            </table>
            
            <form method="POST" onsubmit="return confirm('This will update ID3 tags for all MP3 files. This may take a while. Continue?');">
                <input type="hidden" name="offset" value="0">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Warning:</strong> This operation will modify the ID3 tags in your MP3 files. 
                    Make sure you have backups if needed. The process processes files in batches of 50 to avoid timeouts.
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Batch Processing:</strong> Files are processed in batches of 50 at a time. 
                    If you have many files, you may need to click "Continue" multiple times to process all files.
                </div>
                
                <button type="submit" name="sync_all" class="btn btn-primary" <?php echo !$auto_tagging_enabled ? 'disabled' : ''; ?>>
                    <i class="fas fa-sync"></i> Start Sync ID3 Tags
                </button>
                <a href="?tab=settings" class="btn btn-secondary">
                    <i class="fas fa-cog"></i> Configure Tag Templates
                </a>
            </form>
        </div>
    </div>
    
    <?php if ($results): ?>
    <div class="card">
        <div class="card-header">
            <h2>Sync Results</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="alert alert-success">
                        <h4><i class="fas fa-check"></i> Success: <?php echo $success_count; ?></h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-danger">
                        <h4><i class="fas fa-times"></i> Failed: <?php echo $failed_count; ?></h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-warning">
                        <h4><i class="fas fa-minus"></i> Skipped: <?php echo $skipped_count; ?></h4>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($results['failed'])): ?>
            <div class="mt-4">
                <h3>Failed Songs</h3>
                <div style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['failed'] as $item): ?>
                            <tr>
                                <td><?php echo $item['id']; ?></td>
                                <td><?php echo htmlspecialchars($item['title']); ?></td>
                                <td><small class="text-danger"><?php echo htmlspecialchars($item['reason']); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($results['skipped'])): ?>
            <div class="mt-4">
                <h3>Skipped Songs</h3>
                <div style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['skipped'] as $item): ?>
                            <tr>
                                <td><?php echo $item['id']; ?></td>
                                <td><?php echo htmlspecialchars($item['title']); ?></td>
                                <td><small class="text-warning"><?php echo htmlspecialchars($item['reason']); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($results['success']) && count($results['success']) <= 50): ?>
            <div class="mt-4">
                <h3>Successfully Synced Songs</h3>
                <div style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>File</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['success'] as $item): ?>
                            <tr>
                                <td><?php echo $item['id']; ?></td>
                                <td><?php echo htmlspecialchars($item['title']); ?></td>
                                <td><small><?php echo htmlspecialchars($item['file']); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php elseif (!empty($results['success'])): ?>
            <div class="mt-4">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    Successfully synced <?php echo count($results['success']); ?> songs. 
                    (List truncated - too many to display)
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php
} elseif ($tab === 'edit') {
    // Edit MP3 Tags for a specific song
    require_once '../includes/mp3-tagger.php';
    
    $song_id = $_GET['id'] ?? null;
    $error = '';
    $success = '';
    $tags = [];
    $song = null;
    $song_list = [];

    // Get list of songs for selection
    try {
        $stmt = $conn->query("
            SELECT s.id, s.title, s.file_path, a.name as artist_name, s.artist
            FROM songs s
            LEFT JOIN artists a ON s.artist_id = a.id
            WHERE s.file_path IS NOT NULL AND s.file_path != ''
            ORDER BY s.title ASC
            LIMIT 100
        ");
        $song_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error loading song list: ' . $e->getMessage());
    }

    if ($song_id) {
        // Get song data
        $stmt = $conn->prepare("
            SELECT s.*, a.name as artist_name, al.title as album_title
            FROM songs s
            LEFT JOIN artists a ON s.artist_id = a.id
            LEFT JOIN albums al ON s.album_id = al.id
            WHERE s.id = ?
        ");
        $stmt->execute([$song_id]);
        $song = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($song) {
            // Check if file exists
            $file_path = '../' . ltrim($song['file_path'], '/');
            if (!file_exists($file_path)) {
                $error = 'MP3 file not found: ' . $song['file_path'];
            } else {
                // Try to read existing tags
                try {
                    if (!class_exists('MP3Tagger')) {
                        throw new Exception('MP3Tagger class not found. Please check if includes/mp3-tagger.php is loaded correctly.');
                    }
                    $tagger = new MP3Tagger($file_path);
                    $tags = $tagger->readTags();
                    
                    // Merge with database values (database takes priority for display)
                    $tags['title'] = $song['title'] ?? $tags['title'];
                    $tags['artist'] = $song['artist'] ?? $song['artist_name'] ?? $tags['artist'];
                    $tags['album'] = $song['album_title'] ?? $tags['album'];
                    $tags['year'] = $tags['year'] ?? '';
                    $tags['genre'] = $song['genre'] ?? $tags['genre'];
                    $tags['track_number'] = $song['track_number'] ?? $tags['track_number'];
                    $tags['lyrics'] = $song['lyrics'] ?? $tags['lyrics'];
                } catch (Exception $e) {
                    $error = 'Error reading tags: ' . $e->getMessage();
                    error_log('MP3 Tagger Error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
                    // Initialize empty tags
                    $tags = [
                        'title' => $song['title'] ?? '',
                        'artist' => $song['artist'] ?? $song['artist_name'] ?? '',
                        'album' => $song['album_title'] ?? '',
                        'year' => '',
                        'genre' => $song['genre'] ?? '',
                        'track_number' => $song['track_number'] ?? '',
                        'lyrics' => $song['lyrics'] ?? '',
                        'album_art' => null
                    ];
                }
            }
        }
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tags'])) {
        try {
            $new_tags = [
                'title' => trim($_POST['title'] ?? ''),
                'artist' => trim($_POST['artist'] ?? ''),
                'album' => trim($_POST['album'] ?? ''),
                'year' => trim($_POST['year'] ?? ''),
                'genre' => trim($_POST['genre'] ?? ''),
                'track_number' => trim($_POST['track_number'] ?? ''),
                'lyrics' => trim($_POST['lyrics'] ?? ''),
            ];
            
            // Handle album art upload
            if (isset($_FILES['album_art']) && $_FILES['album_art']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/album-art/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_ext = strtolower(pathinfo($_FILES['album_art']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_ext, $allowed)) {
                    $art_filename = uniqid() . '_' . $song_id . '.' . $file_ext;
                    $art_path = $upload_dir . $art_filename;
                    
                    if (move_uploaded_file($_FILES['album_art']['tmp_name'], $art_path)) {
                        $new_tags['album_art_path'] = $art_path;
                    }
                }
            }
            
            // Write tags to MP3 file
            if ($song && file_exists($file_path)) {
                $tagger = new MP3Tagger($file_path);
                $tagger->writeTags($new_tags);
                $success = 'MP3 tags updated successfully!';
                
                // Update database
                $update_stmt = $conn->prepare("
                    UPDATE songs 
                    SET title = ?, genre = ?, track_number = ?, lyrics = ?
                    WHERE id = ?
                ");
                $update_stmt->execute([
                    $new_tags['title'],
                    $new_tags['genre'],
                    !empty($new_tags['track_number']) ? (int)$new_tags['track_number'] : null,
                    $new_tags['lyrics'],
                    $song_id
                ]);
                
                // Update cover art if uploaded
                if (!empty($new_tags['album_art_path'])) {
                    $cover_path = 'uploads/album-art/' . basename($new_tags['album_art_path']);
                    $cover_stmt = $conn->prepare("UPDATE songs SET cover_art = ? WHERE id = ?");
                    $cover_stmt->execute([$cover_path, $song_id]);
                }
                
                // Refresh song data
                $stmt->execute([$song_id]);
                $song = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Re-read tags to show updated values
                $tags = $tagger->readTags();
                $tags['title'] = $song['title'];
                $tags['artist'] = $song['artist'] ?? $song['artist_name'];
                $tags['album'] = $song['album_title'];
                $tags['lyrics'] = $song['lyrics'];
            }
        } catch (Exception $e) {
            $error = 'Error updating tags: ' . $e->getMessage();
            error_log('MP3 Tagger Error: ' . $e->getMessage());
        }
    }
    
    include 'includes/header.php';
    ?>
    
    <div class="page-header">
        <h1>MP3 Tagger</h1>
    </div>
    
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
    
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header">
            <h2>Select Song to Edit</h2>
        </div>
        <div class="card-body">
            <form method="GET">
                <input type="hidden" name="tab" value="edit">
                <div class="form-group">
                    <label>Select Song</label>
                    <select name="id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Select a song --</option>
                        <?php foreach ($song_list as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $song_id == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['title'] . ' - ' . ($s['artist_name'] ?? $s['artist'] ?? 'Unknown Artist')); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($song): ?>
    <div class="card">
        <div class="card-header">
            <h2>Edit MP3 Tags - <?php echo htmlspecialchars($song['title'] ?? 'Unknown'); ?></h2>
        </div>
        <div class="card-body">
            <div style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 5px;">
                <strong>File:</strong> <?php echo htmlspecialchars($song['file_path']); ?><br>
                <strong>File Size:</strong> <?php echo number_format($song['file_size'] / 1048576, 2); ?> MB<br>
                <strong>Duration:</strong> <?php echo gmdate('i:s', $song['duration'] ?? 0); ?>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_tags" value="1">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($tags['title'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Artist *</label>
                    <input type="text" name="artist" class="form-control" value="<?php echo htmlspecialchars($tags['artist'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Album</label>
                    <input type="text" name="album" class="form-control" value="<?php echo htmlspecialchars($tags['album'] ?? ''); ?>">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Year</label>
                        <input type="text" name="year" class="form-control" value="<?php echo htmlspecialchars($tags['year'] ?? ''); ?>" placeholder="2024" maxlength="4">
                    </div>
                    
                    <div class="form-group">
                        <label>Genre</label>
                        <input type="text" name="genre" class="form-control" value="<?php echo htmlspecialchars($tags['genre'] ?? ''); ?>" placeholder="Pop, Rock, etc.">
                    </div>
                    
                    <div class="form-group">
                        <label>Track Number</label>
                        <input type="number" name="track_number" class="form-control" value="<?php echo htmlspecialchars($tags['track_number'] ?? ''); ?>" min="1">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Album Art</label>
                    <input type="file" name="album_art" class="form-control" accept="image/jpeg,image/png,image/gif">
                    <small class="text-muted">Upload new album art (JPG, PNG, GIF - max 2MB)</small>
                    <?php if (!empty($tags['album_art'])): ?>
                    <div style="margin-top: 10px;">
                        <strong>Current Album Art:</strong><br>
                        <img src="data:<?php echo htmlspecialchars($tags['album_art_mime'] ?? 'image/jpeg'); ?>;base64,<?php echo htmlspecialchars($tags['album_art']); ?>" 
                             style="max-width: 200px; max-height: 200px; margin-top: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Lyrics</label>
                    <textarea name="lyrics" class="form-control" rows="10"><?php echo htmlspecialchars($tags['lyrics'] ?? ''); ?></textarea>
                </div>
                
                <div style="margin-top: 30px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update MP3 Tags
                    </button>
                    <a href="songs.php" class="btn btn-secondary">Back to Songs</a>
                    <a href="song-edit.php?id=<?php echo $song_id; ?>" class="btn btn-info">
                        <i class="fas fa-edit"></i> Edit Song Details
                    </a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <?php
} else {
    // Default to settings tab
    header('Location: ?tab=settings');
    exit;
}
?>

<?php include 'includes/footer.php'; ?>
