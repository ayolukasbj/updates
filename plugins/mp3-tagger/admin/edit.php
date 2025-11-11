<?php
/**
 * MP3 Tagger Edit Page
 */

if (!defined('MP3_TAGGER_PLUGIN_DIR')) {
    define('MP3_TAGGER_PLUGIN_DIR', __DIR__ . '/../');
}

$tab = $GLOBALS['mp3_tagger_tab'] ?? $_GET['tab'] ?? 'edit';

// Load required files - use absolute paths from plugin admin directory
if (file_exists(__DIR__ . '/../../../includes/plugin-api.php')) {
    require_once __DIR__ . '/../../../includes/plugin-api.php';
}
if (file_exists(__DIR__ . '/../../../config/database.php')) {
    require_once __DIR__ . '/../../../config/database.php';
}
require_once MP3_TAGGER_PLUGIN_DIR . 'includes/class-mp3-tagger.php';

$success = '';
$error = '';
$song_id = $_GET['song_id'] ?? $_GET['id'] ?? '';
$tags = null;

if ($song_id) {
    try {
        $conn = get_db_connection();
        $stmt = $conn->prepare("SELECT * FROM songs WHERE id = ?");
        $stmt->execute([$song_id]);
        $song = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($song && !empty($song['file_path'])) {
            $file_path = $song['file_path'];
            $full_file_path = strpos($file_path, '/') === 0 || strpos($file_path, ':\\') !== false 
                ? $file_path 
                : __DIR__ . '/../../../' . ltrim($file_path, '/');
            
            $full_file_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full_file_path);
            
            if (file_exists($full_file_path)) {
                $tagger = new MP3Tagger($full_file_path);
                $tags = $tagger->readTags();
            } else {
                $error = 'File not found: ' . $file_path;
            }
        } else {
            $error = 'Song not found or no file path';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tags'])) {
    try {
        $conn = get_db_connection();
        $stmt = $conn->prepare("SELECT * FROM songs WHERE id = ?");
        $stmt->execute([$_POST['song_id']]);
        $song = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($song && !empty($song['file_path'])) {
            $file_path = $song['file_path'];
            $full_file_path = strpos($file_path, '/') === 0 || strpos($file_path, ':\\') !== false 
                ? $file_path 
                : __DIR__ . '/../../../' . ltrim($file_path, '/');
            
            $full_file_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full_file_path);
            
            $tagger = new MP3Tagger($full_file_path);
            
            $new_tags = [
                'title' => $_POST['title'] ?? '',
                'artist' => $_POST['artist'] ?? '',
                'album' => $_POST['album'] ?? '',
                'year' => $_POST['year'] ?? '',
                'genre' => $_POST['genre'] ?? '',
                'track_number' => $_POST['track_number'] ?? '',
                'comment' => $_POST['comment'] ?? '',
            ];
            
            $tagger->writeTags($new_tags);
            
            // Update database
            $update_stmt = $conn->prepare("
                UPDATE songs SET 
                    title = ?, genre = ?, release_year = ?
                WHERE id = ?
            ");
            $update_stmt->execute([
                $new_tags['title'],
                $new_tags['genre'],
                $new_tags['year'],
                $_POST['song_id']
            ]);
            
            $success = 'MP3 tags updated successfully!';
            $tags = $new_tags;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
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
    <h1><i class="fas fa-edit"></i> MP3 Tagger - Edit Tags</h1>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($tags): ?>
<div class="card">
    <div class="card-header">
        <h2>Edit MP3 Tags</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="song_id" value="<?php echo htmlspecialchars($song_id); ?>">
            
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($tags['title'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Artist</label>
                <input type="text" name="artist" class="form-control" value="<?php echo htmlspecialchars($tags['artist'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Album</label>
                <input type="text" name="album" class="form-control" value="<?php echo htmlspecialchars($tags['album'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Year</label>
                <input type="text" name="year" class="form-control" value="<?php echo htmlspecialchars($tags['year'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Genre</label>
                <input type="text" name="genre" class="form-control" value="<?php echo htmlspecialchars($tags['genre'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Track Number</label>
                <input type="text" name="track_number" class="form-control" value="<?php echo htmlspecialchars($tags['track_number'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Comment</label>
                <textarea name="comment" class="form-control"><?php echo htmlspecialchars($tags['comment'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit" name="update_tags" class="btn btn-primary">Update MP3 Tags</button>
        </form>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <p>Please select a song to edit its MP3 tags.</p>
        <a href="songs.php" class="btn btn-primary">Go to Songs</a>
    </div>
</div>
<?php endif; ?>

