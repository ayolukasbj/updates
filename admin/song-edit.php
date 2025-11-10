<?php
// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal error in song-edit.php: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        if (!headers_sent()) {
            http_response_code(500);
            echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
            echo '<h1>An error occurred</h1>';
            echo '<p>Please check the error logs for details.</p>';
            echo '<p><a href="songs.php">Back to Songs</a></p>';
            echo '</body></html>';
        }
    }
});

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
    try {
        $title = trim($_POST['title'] ?? '');
        $artist_id = $_POST['artist_id'] ?? null;
        $album_id = $_POST['album_id'] ?? null;
        $lyrics = trim($_POST['lyrics'] ?? '');
        $share_excerpt = trim($_POST['share_excerpt'] ?? '');
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_explicit = isset($_POST['is_explicit']) ? 1 : 0;
        
        if (empty($title)) {
            $error = 'Song title is required';
        } else {
            // Check if share_excerpt column exists, if not add it
            try {
                $checkCol = $conn->query("SHOW COLUMNS FROM songs LIKE 'share_excerpt'");
                if ($checkCol->rowCount() == 0) {
                    $conn->exec("ALTER TABLE songs ADD COLUMN share_excerpt TEXT NULL");
                }
            } catch (Exception $e) {
                error_log("Error checking/adding share_excerpt column: " . $e->getMessage());
            }
            
            // Check if is_featured column exists, if not add it
            try {
                $checkFeatured = $conn->query("SHOW COLUMNS FROM songs LIKE 'is_featured'");
                if ($checkFeatured->rowCount() == 0) {
                    $conn->exec("ALTER TABLE songs ADD COLUMN is_featured TINYINT(1) DEFAULT 0");
                }
            } catch (Exception $e) {
                error_log("Error checking/adding is_featured column: " . $e->getMessage());
            }
            
            // Check if is_explicit column exists, if not add it
            try {
                $checkExplicit = $conn->query("SHOW COLUMNS FROM songs LIKE 'is_explicit'");
                if ($checkExplicit->rowCount() == 0) {
                    $conn->exec("ALTER TABLE songs ADD COLUMN is_explicit TINYINT(1) DEFAULT 0");
                }
            } catch (Exception $e) {
                error_log("Error checking/adding is_explicit column: " . $e->getMessage());
            }
            
            // Build dynamic UPDATE query based on available columns
            $update_fields = ['title = ?', 'artist_id = ?', 'album_id = ?', 'lyrics = ?'];
            $update_values = [$title, $artist_id, $album_id, $lyrics];
            
            // Add share_excerpt if column exists
            try {
                $checkCol = $conn->query("SHOW COLUMNS FROM songs LIKE 'share_excerpt'");
                if ($checkCol->rowCount() > 0) {
                    $update_fields[] = 'share_excerpt = ?';
                    $update_values[] = $share_excerpt;
                }
            } catch (Exception $e) {
                // Column doesn't exist, skip it
                error_log("Error checking share_excerpt for update: " . $e->getMessage());
            }
            
            // Add is_featured if column exists
            try {
                $checkFeatured = $conn->query("SHOW COLUMNS FROM songs LIKE 'is_featured'");
                if ($checkFeatured->rowCount() > 0) {
                    $update_fields[] = 'is_featured = ?';
                    $update_values[] = $is_featured;
                }
            } catch (Exception $e) {
                // Column doesn't exist, skip it
                error_log("Error checking is_featured for update: " . $e->getMessage());
            }
            
            // Add is_explicit if column exists
            try {
                $checkExplicit = $conn->query("SHOW COLUMNS FROM songs LIKE 'is_explicit'");
                if ($checkExplicit->rowCount() > 0) {
                    $update_fields[] = 'is_explicit = ?';
                    $update_values[] = $is_explicit;
                }
            } catch (Exception $e) {
                // Column doesn't exist, skip it
                error_log("Error checking is_explicit for update: " . $e->getMessage());
            }
            
            // Add WHERE clause
            $update_values[] = $song_id;
            
            // Build and execute UPDATE query
            $update_sql = "UPDATE songs SET " . implode(', ', $update_fields) . " WHERE id = ?";
            error_log("Song Update SQL: " . $update_sql);
            error_log("Song Update Values: " . print_r($update_values, true));
            
            $stmt = $conn->prepare($update_sql);
            
            if ($stmt->execute($update_values)) {
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
                $errorInfo = $stmt->errorInfo();
                $error = 'Failed to update song: ' . ($errorInfo[2] ?? 'Unknown error');
                error_log("Song update failed: " . print_r($errorInfo, true));
            }
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
        error_log("PDO Exception in song-edit.php: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
        error_log("Exception in song-edit.php: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
    } catch (Error $e) {
        $error = 'Fatal error: ' . $e->getMessage();
        error_log("Fatal Error in song-edit.php: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
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
                <label>Share Excerpt (for social media sharing)</label>
                <textarea name="share_excerpt" class="form-control" rows="3" placeholder="Custom description for when this song is shared on social media (Facebook, Twitter, etc.). If left empty, will use song description or auto-generated text."><?php echo htmlspecialchars($song['share_excerpt'] ?? ''); ?></textarea>
                <small class="text-muted">This text will appear when the song is shared on social media. Keep it under 200 characters for best results.</small>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="is_featured" value="1" <?php echo (!empty($song['is_featured']) && $song['is_featured']) ? 'checked' : ''; ?>>
                    <span>Featured Song</span>
                </label>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="is_explicit" value="1" <?php echo (!empty($song['is_explicit']) && $song['is_explicit']) ? 'checked' : ''; ?>>
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

