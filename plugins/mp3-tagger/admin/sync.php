<?php
/**
 * MP3 Tagger Sync Page
 */

if (!defined('MP3_TAGGER_PLUGIN_DIR')) {
    define('MP3_TAGGER_PLUGIN_DIR', __DIR__ . '/../');
}

$tab = $GLOBALS['mp3_tagger_tab'] ?? $_GET['tab'] ?? 'sync';

// Load required files - use absolute paths from plugin admin directory
if (file_exists(__DIR__ . '/../../../includes/plugin-api.php')) {
    require_once __DIR__ . '/../../../includes/plugin-api.php';
}
if (file_exists(__DIR__ . '/../../../config/database.php')) {
    require_once __DIR__ . '/../../../config/database.php';
}
require_once MP3_TAGGER_PLUGIN_DIR . 'includes/class-auto-tagger.php';

$success = '';
$error = '';
$results = null;

// Handle sync
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_all'])) {
    try {
        $conn = get_db_connection();
        $offset = (int)($_POST['offset'] ?? 0);
        $batch_size = 50;
        
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
            $results = ['success' => [], 'failed' => [], 'skipped' => []];
            
            foreach ($songs as $song) {
                try {
                    $file_path = $song['file_path'];
                    $full_file_path = strpos($file_path, '/') === 0 || strpos($file_path, ':\\') !== false 
                        ? $file_path 
                        : __DIR__ . '/../../../' . ltrim($file_path, '/');
                    
                    $full_file_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full_file_path);
                    
                    if (!file_exists($full_file_path)) {
                        $results['skipped'][] = ['id' => $song['id'], 'title' => $song['title'], 'reason' => 'File not found'];
                        continue;
                    }
                    
                    $file_ext = strtolower(pathinfo($full_file_path, PATHINFO_EXTENSION));
                    if ($file_ext !== 'mp3') {
                        $results['skipped'][] = ['id' => $song['id'], 'title' => $song['title'], 'reason' => 'Not MP3'];
                        continue;
                    }
                    
                    $tag_song_data = [
                        'title' => $song['title'] ?? '',
                        'artist' => $song['artist_name'] ?? $song['artist'] ?? 'Unknown',
                        'year' => $song['year'] ?? '',
                        'genre' => $song['genre'] ?? '',
                    ];
                    
                    $tag_result = AutoTagger::tagUploadedSong($full_file_path, $tag_song_data, $song['uploader_name'] ?? '');
                    
                    if ($tag_result['success']) {
                        $results['success'][] = ['id' => $song['id'], 'title' => $song['title']];
                    } else {
                        $results['failed'][] = ['id' => $song['id'], 'title' => $song['title']];
                    }
                } catch (Exception $e) {
                    $results['failed'][] = ['id' => $song['id'], 'title' => $song['title'], 'error' => $e->getMessage()];
                }
            }
            
            $success = 'Processed ' . count($songs) . ' songs. ' . 
                      count($results['success']) . ' successful, ' . 
                      count($results['failed']) . ' failed, ' . 
                      count($results['skipped']) . ' skipped.';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

$auto_tagging_enabled = get_option('id3_auto_tagging_enabled', '1');
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
    <h1><i class="fas fa-sync"></i> MP3 Tagger - Sync ID3 Tags</h1>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (!$auto_tagging_enabled): ?>
<div class="alert alert-warning">
    Auto-tagging is disabled! Please enable it in Settings first.
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Sync ID3 Tags</h2>
    </div>
    <div class="card-body">
        <p>This will update ID3 tags for all MP3 files in your database.</p>
        
        <form method="POST" onsubmit="return confirm('This will update ID3 tags. Continue?');">
            <input type="hidden" name="offset" value="0">
            <button type="submit" name="sync_all" class="btn btn-primary" <?php echo !$auto_tagging_enabled ? 'disabled' : ''; ?>>
                <i class="fas fa-sync"></i> Start Sync
            </button>
        </form>
        
        <?php if ($results): ?>
        <div style="margin-top: 20px;">
            <h3>Results</h3>
            <p>Success: <?php echo count($results['success']); ?></p>
            <p>Failed: <?php echo count($results['failed']); ?></p>
            <p>Skipped: <?php echo count($results['skipped']); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

