<?php
require_once 'auth-check.php';
require_once '../config/database.php';
require_once '../includes/mp3-tagger.php';
require_once '../includes/audio-processor.php';

$db = new Database();
$conn = $db->getConnection();

$song_id = $_GET['id'] ?? null;
$error = '';
$success = '';
$tags = [];
$song = null;

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

// Check if file exists
$file_path = '../' . ltrim($song['file_path'], '/');
if (!file_exists($file_path)) {
    $error = 'MP3 file not found: ' . $song['file_path'];
} else {
    // Try to read existing tags
    try {
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

// Handle voice tag upload/processing
$voice_tag_processed = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_voice_tag'])) {
    try {
        $voice_tag_position = $_POST['voice_tag_position'] ?? 'end';
        $voice_tag_file = null;
        
        // Handle voice tag file upload
        if (isset($_FILES['voice_tag_file']) && $_FILES['voice_tag_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/voice-tags/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = strtolower(pathinfo($_FILES['voice_tag_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['mp3', 'wav', 'm4a', 'aac'];
            
            if (in_array($file_ext, $allowed)) {
                $tag_filename = uniqid() . '_voice_tag.' . $file_ext;
                $tag_path = $upload_dir . $tag_filename;
                
                if (move_uploaded_file($_FILES['voice_tag_file']['tmp_name'], $tag_path)) {
                    $voice_tag_file = $tag_path;
                }
            } else {
                throw new Exception('Invalid voice tag file format. Allowed: MP3, WAV, M4A, AAC');
            }
        } elseif (!empty($_POST['existing_voice_tag'])) {
            // Use existing voice tag
            $voice_tag_file = '../uploads/voice-tags/' . basename($_POST['existing_voice_tag']);
            if (!file_exists($voice_tag_file)) {
                throw new Exception('Selected voice tag file not found');
            }
        }
        
        if ($voice_tag_file && file_exists($file_path)) {
            // Process audio with voice tag
            $processor = new AudioProcessor();
            
            if (!$processor->isAvailable()) {
                throw new Exception('FFmpeg is not available. Please install FFmpeg to use voice tags.');
            }
            
            // Create backup
            $backup_path = $file_path . '.backup.' . time();
            copy($file_path, $backup_path);
            
            // Add voice tag
            $output_file = $processor->addVoiceTag($file_path, $voice_tag_file, $voice_tag_position);
            
            // Replace original with tagged version
            if (file_exists($output_file)) {
                // Update file size
                $new_size = filesize($output_file);
                
                // Replace original
                if (rename($output_file, $file_path)) {
                    // Update database
                    $update_stmt = $conn->prepare("UPDATE songs SET file_size = ? WHERE id = ?");
                    $update_stmt->execute([$new_size, $song_id]);
                    
                    $success = 'Voice tag added successfully! Original file backed up.';
                    $voice_tag_processed = true;
                } else {
                    throw new Exception('Failed to replace original file');
                }
            } else {
                throw new Exception('Failed to process voice tag');
            }
        } else {
            throw new Exception('Voice tag file is required');
        }
    } catch (Exception $e) {
        $error = 'Error adding voice tag: ' . $e->getMessage();
        error_log('Voice Tag Error: ' . $e->getMessage());
    }
}

// Get available voice tags
$voice_tags_dir = '../uploads/voice-tags/';
$available_voice_tags = [];
if (is_dir($voice_tags_dir)) {
    $files = glob($voice_tags_dir . '*.{mp3,wav,m4a,aac}', GLOB_BRACE);
    foreach ($files as $file) {
        $available_voice_tags[] = basename($file);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['add_voice_tag'])) {
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
        if (file_exists($file_path)) {
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

$page_title = 'MP3 Tagger';
include 'includes/header.php';
?>

<div class="page-header">
    <h1>MP3 Tagger</h1>
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
        <h2>Edit MP3 Tags - <?php echo htmlspecialchars($song['title'] ?? 'Unknown'); ?></h2>
    </div>
    <div class="card-body">
        <div style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 5px;">
            <strong>File:</strong> <?php echo htmlspecialchars($song['file_path']); ?><br>
            <strong>File Size:</strong> <?php echo number_format($song['file_size'] / 1048576, 2); ?> MB<br>
            <strong>Duration:</strong> <?php echo gmdate('i:s', $song['duration'] ?? 0); ?>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
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
                <a href="songs.php" class="btn btn-secondary">Cancel</a>
                <a href="song-edit.php?id=<?php echo $song_id; ?>" class="btn btn-info">
                    <i class="fas fa-edit"></i> Edit Song Details
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Voice Tag Section -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2>Add Voice Tag</h2>
    </div>
    <div class="card-body">
        <?php
        $processor = new AudioProcessor();
        $ffmpeg_available = $processor->isAvailable();
        ?>
        
        <?php if (!$ffmpeg_available): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>FFmpeg not found!</strong> Voice tag feature requires FFmpeg to be installed on your server.
            <br><br>
            <strong>Installation:</strong>
            <ul style="margin-top: 10px; margin-left: 20px;">
                <li><strong>Windows:</strong> Download from <a href="https://ffmpeg.org/download.html" target="_blank">ffmpeg.org</a> and add to PATH</li>
                <li><strong>Linux:</strong> <code>sudo apt-get install ffmpeg</code> or <code>sudo yum install ffmpeg</code></li>
                <li><strong>cPanel/Shared Hosting:</strong> Contact your hosting provider or use a VPS</li>
            </ul>
        </div>
        <?php else: ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> FFmpeg is available at: <code><?php echo htmlspecialchars($processor->getFFmpegPath()); ?></code>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="add_voice_tag" value="1">
            
            <div class="form-group">
                <label>Voice Tag Position *</label>
                <select name="voice_tag_position" class="form-control" required>
                    <option value="start">At the Start</option>
                    <option value="end" selected>At the End</option>
                </select>
                <small class="text-muted">Choose where to place the voice tag in the song</small>
            </div>
            
            <?php if (!empty($available_voice_tags)): ?>
            <div class="form-group">
                <label>Use Existing Voice Tag</label>
                <select name="existing_voice_tag" class="form-control">
                    <option value="">-- Select existing voice tag --</option>
                    <?php foreach ($available_voice_tags as $tag_file): ?>
                    <option value="<?php echo htmlspecialchars($tag_file); ?>"><?php echo htmlspecialchars($tag_file); ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">OR upload a new voice tag below</small>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label><?php echo !empty($available_voice_tags) ? 'Upload New Voice Tag' : 'Voice Tag File *'; ?></label>
                <input type="file" name="voice_tag_file" class="form-control" accept="audio/mpeg,audio/wav,audio/mp4,audio/aac" <?php echo empty($available_voice_tags) ? 'required' : ''; ?>>
                <small class="text-muted">Upload MP3, WAV, M4A, or AAC file (max 5MB recommended)</small>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                <strong>Important:</strong> 
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <li>A backup of the original file will be created automatically</li>
                    <li>The voice tag will be merged with the song audio</li>
                    <li>Processing may take a few moments depending on file size</li>
                    <li>The original file will be replaced with the tagged version</li>
                </ul>
            </div>
            
            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-microphone"></i> Add Voice Tag to Song
                </button>
                <a href="songs.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3>Instructions</h3>
    </div>
    <div class="card-body">
        <ul>
            <li><strong>Title & Artist:</strong> Required fields. These will be written to the MP3 file and updated in the database.</li>
            <li><strong>Album:</strong> Optional. Will be written to the MP3 file.</li>
            <li><strong>Year, Genre, Track Number:</strong> Optional metadata fields.</li>
            <li><strong>Album Art:</strong> Upload a new image to replace the existing album art in the MP3 file.</li>
            <li><strong>Lyrics:</strong> Will be embedded in the MP3 file and saved to the database.</li>
            <li><strong>Voice Tag:</strong> Add audio watermark/announcement at the start or end of the song (requires FFmpeg).</li>
            <li><strong>Note:</strong> Changes are written directly to the MP3 file. Make sure you have a backup if needed.</li>
        </ul>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

