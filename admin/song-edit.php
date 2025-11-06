<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$song_id = $_GET['id'] ?? null;
$error = '';
$success = '';

if (!$song_id) {
    header('Location: songs.php');
    exit;
}

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

if (!$song) {
    header('Location: songs.php');
    exit;
}

// Get all artists for dropdown
$stmt = $conn->query("SELECT id, name FROM artists ORDER BY name");
$artists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all albums for dropdown
$stmt = $conn->query("SELECT id, title FROM albums ORDER BY title");
$albums = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $artist_id = $_POST['artist_id'] ?? null;
    $album_id = $_POST['album_id'] ?? null;
    $lyrics = trim($_POST['lyrics'] ?? '');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_explicit = isset($_POST['is_explicit']) ? 1 : 0;
    
    if (empty($title)) {
        $error = 'Song title is required';
    } else {
        // Update song
        $stmt = $conn->prepare("
            UPDATE songs 
            SET title = ?, artist_id = ?, album_id = ?, lyrics = ?, is_featured = ?, is_explicit = ?
            WHERE id = ?
        ");
        
        if ($stmt->execute([$title, $artist_id, $album_id, $lyrics, $is_featured, $is_explicit, $song_id])) {
            $success = 'Song updated successfully';
            
            // Refresh song data
            $stmt = $conn->prepare("
                SELECT s.*, a.name as artist_name, al.title as album_title
                FROM songs s
                LEFT JOIN artists a ON s.artist_id = a.id
                LEFT JOIN albums al ON s.album_id = al.id
                WHERE s.id = ?
            ");
            $stmt->execute([$song_id]);
            $song = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = 'Failed to update song';
        }
    }
}

$page_title = 'Edit Song';
include 'includes/header.php';
?>

<div class="page-header">
    <h1>Edit Song</h1>
    <a href="songs.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Songs
    </a>
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
        <h2>Song Information</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label>Song Title *</label>
                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($song['title']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Artist</label>
                <select name="artist_id" class="form-control">
                    <option value="">Select Artist</option>
                    <?php foreach ($artists as $artist): ?>
                    <option value="<?php echo $artist['id']; ?>" <?php echo $song['artist_id'] == $artist['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($artist['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Album</label>
                <select name="album_id" class="form-control">
                    <option value="">No Album</option>
                    <?php foreach ($albums as $album): ?>
                    <option value="<?php echo $album['id']; ?>" <?php echo $song['album_id'] == $album['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($album['title']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Lyrics</label>
                <textarea name="lyrics" class="form-control" rows="8"><?php echo htmlspecialchars($song['lyrics'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="is_featured" value="1" <?php echo $song['is_featured'] ? 'checked' : ''; ?>>
                    <span>Featured Song</span>
                </label>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="is_explicit" value="1" <?php echo $song['is_explicit'] ? 'checked' : ''; ?>>
                    <span>Explicit Content</span>
                </label>
            </div>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                <h3 style="margin-bottom: 15px;">Song Stats</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <strong>Song ID:</strong> <?php echo $song['id']; ?>
                    </div>
                    <div>
                        <strong>Plays:</strong> <?php echo number_format($song['plays']); ?>
                    </div>
                    <div>
                        <strong>Downloads:</strong> <?php echo number_format($song['downloads']); ?>
                    </div>
                    <div>
                        <strong>Duration:</strong> <?php echo gmdate('i:s', $song['duration']); ?>
                    </div>
                    <div>
                        <strong>File Size:</strong> <?php echo round($song['file_size'] / 1048576, 2); ?> MB
                    </div>
                    <div>
                        <strong>Uploaded:</strong> <?php echo date('M d, Y', strtotime($song['upload_date'])); ?>
                    </div>
                </div>
                
                <div style="margin-top: 15px;">
                    <strong>File Path:</strong><br>
                    <code style="background: #f3f4f6; padding: 8px; display: block; margin-top: 5px; border-radius: 4px; word-break: break-all;">
                        <?php echo htmlspecialchars($song['file_path']); ?>
                    </code>
                </div>
            </div>
            
            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="songs.php" class="btn btn-secondary">Cancel</a>
                <?php
                // Generate song slug for URL - get song data first
                $songSlug = 'song-' . $song_id; // Default fallback
                try {
                    $db = new Database();
                    $conn = $db->getConnection();
                    if ($conn) {
                        $slug_stmt = $conn->prepare("SELECT title, artist, uploaded_by FROM songs WHERE id = ?");
                        $slug_stmt->execute([$song_id]);
                        $slug_song = $slug_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($slug_song) {
                            $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $slug_song['title']));
                            $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
                            $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $slug_song['artist'] ?? 'unknown-artist'));
                            $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
                            $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
                        }
                    }
                } catch (Exception $e) {
                    // Use default
                }
                ?>
                <a href="../song/<?php echo urlencode($songSlug); ?>" class="btn btn-info" target="_blank">
                    <i class="fas fa-eye"></i> View Song
                </a>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

