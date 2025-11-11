<?php
// lyrics-manage.php - Manage lyrics for songs
session_start();
require_once 'config/config.php';
require_once 'config/database.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$user_id = get_user_id();
$db = new Database();
$conn = $db->getConnection();

// Get song_id from GET or POST (form submission)
$song_id = isset($_GET['song_id']) ? (int)$_GET['song_id'] : (isset($_POST['song_id']) ? (int)$_POST['song_id'] : 0);
$message = '';
$message_type = '';

// Get user's songs
$user_songs = [];
try {
    $stmt = $conn->prepare("
        SELECT id, title, lyrics 
        FROM songs 
        WHERE uploaded_by = ? 
        ORDER BY id DESC
    ");
    $stmt->execute([$user_id]);
    $user_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching songs: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lyrics'])) {
    $song_id_post = (int)$_POST['song_id'];
    $lyrics = trim($_POST['lyrics'] ?? '');
    
    // Verify song belongs to user
    $verifyStmt = $conn->prepare("SELECT id FROM songs WHERE id = ? AND uploaded_by = ?");
    $verifyStmt->execute([$song_id_post, $user_id]);
    $song = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($song) {
        try {
            $updateStmt = $conn->prepare("UPDATE songs SET lyrics = ? WHERE id = ? AND uploaded_by = ?");
            $updateStmt->execute([$lyrics, $song_id_post, $user_id]);
            
            $message = 'Lyrics saved successfully!';
            $message_type = 'success';
            $song_id = $song_id_post; // Refresh current song
        } catch (Exception $e) {
            $message = 'Error saving lyrics: ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = 'Song not found or you do not have permission to edit it.';
        $message_type = 'error';
    }
}

// Get current song lyrics if editing
$current_lyrics = '';
$current_song_title = '';
if ($song_id > 0) {
    $songStmt = $conn->prepare("SELECT title, lyrics FROM songs WHERE id = ? AND uploaded_by = ?");
    $songStmt->execute([$song_id, $user_id]);
    $current_song = $songStmt->fetch(PDO::FETCH_ASSOC);
    if ($current_song) {
        $current_song_title = $current_song['title'];
        $current_lyrics = $current_song['lyrics'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Lyrics - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        .lyrics-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
        }
        .lyrics-form {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
            font-family: inherit;
        }
        textarea {
            min-height: 300px;
            resize: vertical;
        }
        .btn-save {
            background: #ff6600;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="lyrics-container">
        <h1 style="margin-bottom: 20px;"><i class="fas fa-file-text"></i> Manage Lyrics</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="lyrics-form">
            <form method="POST">
                <div class="form-group">
                    <label>Select Song</label>
                    <select name="song_id" id="song_select" required onchange="loadSongLyrics(this.value)">
                        <option value="">-- Select a song --</option>
                        <?php foreach ($user_songs as $song): ?>
                            <option value="<?php echo $song['id']; ?>" <?php echo ($song_id == $song['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($song['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="lyrics_form_container" style="<?php echo ($song_id > 0 && !empty($current_song_title)) ? '' : 'display: none;'; ?>">
                    <input type="hidden" name="selected_song_id" id="selected_song_id" value="<?php echo $song_id; ?>">
                    
                    <div class="form-group">
                        <label id="lyrics_label">Lyrics for: <strong id="selected_song_title"><?php echo htmlspecialchars($current_song_title ?? ''); ?></strong></label>
                        <textarea name="lyrics" id="lyrics_textarea" placeholder="Enter lyrics here..." rows="15"><?php echo htmlspecialchars($current_lyrics); ?></textarea>
                    </div>
                    
                    <button type="submit" name="save_lyrics" class="btn-save">
                        <i class="fas fa-save"></i> Save Lyrics
                    </button>
                </div>
            </form>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="artist-profile-mobile.php?tab=lyrics" style="color: #666; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Artist Profile
            </a>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
    function loadSongLyrics(songId) {
        if (!songId || songId === '') {
            document.getElementById('lyrics_form_container').style.display = 'none';
            return;
        }
        
        // Show loading
        document.getElementById('lyrics_form_container').style.display = 'block';
        document.getElementById('selected_song_id').value = songId;
        document.getElementById('lyrics_textarea').value = 'Loading...';
        
        // Fetch lyrics via AJAX
        fetch('api/get-song-lyrics.php?song_id=' + songId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('selected_song_title').textContent = data.title;
                    document.getElementById('lyrics_textarea').value = data.lyrics || '';
                } else {
                    document.getElementById('selected_song_title').textContent = 'Selected Song';
                    document.getElementById('lyrics_textarea').value = '';
                }
            })
            .catch(error => {
                console.error('Error loading lyrics:', error);
                document.getElementById('selected_song_title').textContent = 'Selected Song';
                document.getElementById('lyrics_textarea').value = '';
            });
    }
    
    // Load lyrics if song is already selected
    <?php if ($song_id > 0 && !empty($current_song_title)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        loadSongLyrics(<?php echo $song_id; ?>);
    });
    <?php endif; ?>
    </script>
</body>
</html>

