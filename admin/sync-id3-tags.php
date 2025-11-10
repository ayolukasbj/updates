<?php
// admin/sync-id3-tags.php
// Sync ID3 tags for all existing uploaded MP3 files

require_once 'auth-check.php';
require_once '../config/database.php';
require_once '../includes/auto-tagger.php';
require_once '../includes/settings.php';

$page_title = 'Sync ID3 Tags';

$db = new Database();
$conn = $db->getConnection();

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
                $success = "Processed batch: " . count($songs) . " songs (Offset: $offset). Success: $success_count, Failed: $failed_count, Skipped: $skipped_count. <a href='?continue=1&offset=$next_offset' class='btn btn-primary mt-2'>Continue processing next batch...</a>";
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
    <h1>Sync ID3 Tags</h1>
    <p>Update ID3 tags for all existing uploaded MP3 files</p>
</div>

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
    <strong>Auto-tagging is disabled!</strong> Please enable it in <a href="id3-tag-settings.php">ID3 Tag Settings</a> before syncing.
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
            <a href="id3-tag-settings.php" class="btn btn-secondary">
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

<?php include 'includes/footer.php'; ?>
