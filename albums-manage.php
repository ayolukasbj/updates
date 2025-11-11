<?php
// albums-manage.php - Manage albums
session_start();
require_once 'config/config.php';
require_once 'config/database.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$user_id = get_user_id();
$db = new Database();
$conn = $db->getConnection();

$album_id = isset($_GET['album_id']) ? (int)$_GET['album_id'] : 0;
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_album'])) {
    $album_id_post = (int)($_POST['album_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $release_date = $_POST['release_date'] ?? null;
    
    // Handle cover art upload
    $cover_art = null;
    if (isset($_FILES['cover_art']) && $_FILES['cover_art']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/covers/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = pathinfo($_FILES['cover_art']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('album_' . $user_id . '_') . '.' . $file_ext;
        $cover_art = $upload_dir . $filename;
        
        if (!move_uploaded_file($_FILES['cover_art']['tmp_name'], $cover_art)) {
            $cover_art = null;
        }
    }
    
    // Check which columns exist in albums table (move before use)
    $checkColumnsStmt = $conn->query("SHOW COLUMNS FROM albums");
    $checkColumnsStmt->execute();
    $checkColumnNames = $checkColumnsStmt->fetchAll(PDO::FETCH_COLUMN);
    $has_user_id = in_array('user_id', $checkColumnNames);
    $has_artist_id = in_array('artist_id', $checkColumnNames);
    
    try {
        if ($album_id_post > 0) {
            // Update existing album - check based on available columns
            if ($has_user_id && $has_artist_id) {
                $verifyStmt = $conn->prepare("SELECT id FROM albums WHERE id = ? AND (artist_id = ? OR user_id = ?)");
                $verifyStmt->execute([$album_id_post, $user_id, $user_id]);
            } elseif ($has_user_id) {
                $verifyStmt = $conn->prepare("SELECT id FROM albums WHERE id = ? AND user_id = ?");
                $verifyStmt->execute([$album_id_post, $user_id]);
            } elseif ($has_artist_id) {
                $verifyStmt = $conn->prepare("
                    SELECT a.id 
                    FROM albums a
                    LEFT JOIN artists art ON a.artist_id = art.id
                    WHERE a.id = ? AND (art.user_id = ? OR a.artist_id = ?)
                ");
                $verifyStmt->execute([$album_id_post, $user_id, $user_id]);
            } else {
                $verifyStmt = $conn->prepare("SELECT id FROM albums WHERE id = ?");
                $verifyStmt->execute([$album_id_post]);
            }
            $album = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($album) {
                if ($cover_art) {
                    $updateStmt = $conn->prepare("UPDATE albums SET title = ?, description = ?, release_date = ?, cover_art = ? WHERE id = ?");
                    $updateStmt->execute([$title, $description, $release_date, $cover_art, $album_id_post]);
                } else {
                    $updateStmt = $conn->prepare("UPDATE albums SET title = ?, description = ?, release_date = ? WHERE id = ?");
                    $updateStmt->execute([$title, $description, $release_date, $album_id_post]);
                }
                
                // Update songs in album
                $selectedSongIds = [];
                if (!empty($_POST['album_songs']) && is_array($_POST['album_songs'])) {
                    $selectedSongIds = array_map('intval', $_POST['album_songs']);
                }
                
                // Get current songs in album
                $currentSongsStmt = $conn->prepare("SELECT id FROM songs WHERE album_id = ?");
                $currentSongsStmt->execute([$album_id_post]);
                $currentSongIds = $currentSongsStmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Remove songs that were unchecked
                foreach ($currentSongIds as $currentSongId) {
                    if (!in_array($currentSongId, $selectedSongIds)) {
                        // Verify user owns this song before removing
                        $verifySongStmt = $conn->prepare("
                            SELECT id FROM songs 
                            WHERE id = ? 
                            AND (uploaded_by = ? OR id IN (
                                SELECT song_id FROM song_collaborators WHERE user_id = ?
                            ))
                        ");
                        $verifySongStmt->execute([$currentSongId, $user_id, $user_id]);
                        $song = $verifySongStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($song) {
                            $removeStmt = $conn->prepare("UPDATE songs SET album_id = NULL WHERE id = ?");
                            $removeStmt->execute([$currentSongId]);
                        }
                    }
                }
                
                // Add new selected songs
                foreach ($selectedSongIds as $songId) {
                    if ($songId > 0 && !in_array($songId, $currentSongIds)) {
                        // Verify song belongs to user (uploader or collaborator)
                        $verifySongStmt = $conn->prepare("
                            SELECT id FROM songs 
                            WHERE id = ? 
                            AND (uploaded_by = ? OR id IN (
                                SELECT song_id FROM song_collaborators WHERE user_id = ?
                            ))
                        ");
                        $verifySongStmt->execute([$songId, $user_id, $user_id]);
                        $song = $verifySongStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($song) {
                            $updateSongStmt = $conn->prepare("UPDATE songs SET album_id = ? WHERE id = ?");
                            $updateSongStmt->execute([$album_id_post, $songId]);
                        }
                    }
                }
                
                $message = 'Album updated successfully!';
            } else {
                $message = 'Album not found or you do not have permission to edit it.';
                $message_type = 'error';
            }
        } else {
            // Create new album - build query based on available columns
            if ($has_user_id && $has_artist_id) {
                // Check if artist exists for this user_id, if not create one
                $artistCheckStmt = $conn->prepare("SELECT id FROM artists WHERE user_id = ? LIMIT 1");
                $artistCheckStmt->execute([$user_id]);
                $artistRecord = $artistCheckStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$artistRecord) {
                    // Create artist record first
                    $userStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                    $userStmt->execute([$user_id]);
                    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
                    $artistName = $userData['username'] ?? 'Artist ' . $user_id;
                    
                    $createArtistStmt = $conn->prepare("INSERT INTO artists (name, user_id, created_at) VALUES (?, ?, NOW())");
                    $createArtistStmt->execute([$artistName, $user_id]);
                    $artist_id = $conn->lastInsertId();
                } else {
                    $artist_id = $artistRecord['id'];
                }
                
                $insertStmt = $conn->prepare("INSERT INTO albums (title, description, release_date, cover_art, artist_id, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $insertStmt->execute([$title, $description, $release_date, $cover_art, $artist_id, $user_id]);
            } elseif ($has_user_id) {
                $insertStmt = $conn->prepare("INSERT INTO albums (title, description, release_date, cover_art, user_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $insertStmt->execute([$title, $description, $release_date, $cover_art, $user_id]);
            } elseif ($has_artist_id) {
                // Check if artist exists for this user_id, if not create one
                $artistCheckStmt = $conn->prepare("SELECT id FROM artists WHERE user_id = ? LIMIT 1");
                $artistCheckStmt->execute([$user_id]);
                $artistRecord = $artistCheckStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$artistRecord) {
                    // Create artist record first
                    $userStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                    $userStmt->execute([$user_id]);
                    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
                    $artistName = $userData['username'] ?? 'Artist ' . $user_id;
                    
                    $createArtistStmt = $conn->prepare("INSERT INTO artists (name, user_id, created_at) VALUES (?, ?, NOW())");
                    $createArtistStmt->execute([$artistName, $user_id]);
                    $artist_id = $conn->lastInsertId();
                } else {
                    $artist_id = $artistRecord['id'];
                }
                
                $insertStmt = $conn->prepare("INSERT INTO albums (title, description, release_date, cover_art, artist_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $insertStmt->execute([$title, $description, $release_date, $cover_art, $artist_id]);
            } else {
                // Fallback: just insert without user/artist reference
                $insertStmt = $conn->prepare("INSERT INTO albums (title, description, release_date, cover_art, created_at) VALUES (?, ?, ?, ?, NOW())");
                $insertStmt->execute([$title, $description, $release_date, $cover_art]);
            }
            $album_id_post = $conn->lastInsertId();
            
            // Add selected songs to album
            if (!empty($_POST['album_songs']) && is_array($_POST['album_songs'])) {
                $songIds = array_map('intval', $_POST['album_songs']);
                
                // Check if user is admin
                $is_admin = false;
                try {
                    $roleCheckStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
                    $roleCheckStmt->execute([$user_id]);
                    $userRole = $roleCheckStmt->fetchColumn();
                    $is_admin = in_array($userRole, ['admin', 'super_admin']);
                } catch (Exception $e) {
                    // Role column might not exist
                }
                
                foreach ($songIds as $songId) {
                    if ($songId > 0) {
                        if ($is_admin) {
                            // Admin can add any song
                            $updateSongStmt = $conn->prepare("UPDATE songs SET album_id = ? WHERE id = ?");
                            $updateSongStmt->execute([$album_id_post, $songId]);
                        } else {
                            // Regular user - verify song belongs to them
                            $verifySongStmt = $conn->prepare("
                                SELECT id FROM songs 
                                WHERE id = ? 
                                AND (uploaded_by = ? OR id IN (
                                    SELECT song_id FROM song_collaborators WHERE user_id = ?
                                ))
                            ");
                            $verifySongStmt->execute([$songId, $user_id, $user_id]);
                            $song = $verifySongStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($song) {
                                $updateSongStmt = $conn->prepare("UPDATE songs SET album_id = ? WHERE id = ?");
                                $updateSongStmt->execute([$album_id_post, $songId]);
                            }
                        }
                    }
                }
            }
            
            $message = 'Album created successfully!';
            $album_id = $album_id_post;
        }
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Error saving album: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Check which columns exist in albums table
$albumColumns = $conn->query("SHOW COLUMNS FROM albums");
$albumColumns->execute();
$albumColumnNames = $albumColumns->fetchAll(PDO::FETCH_COLUMN);
$has_user_id = in_array('user_id', $albumColumnNames);
$has_artist_id = in_array('artist_id', $albumColumnNames);

// Get current album if editing
$current_album = null;
$album_songs = [];
if ($album_id > 0) {
    // Build query based on available columns
    if ($has_user_id && $has_artist_id) {
        $albumStmt = $conn->prepare("SELECT * FROM albums WHERE id = ? AND (artist_id = ? OR user_id = ?)");
        $albumStmt->execute([$album_id, $user_id, $user_id]);
    } elseif ($has_user_id) {
        $albumStmt = $conn->prepare("SELECT * FROM albums WHERE id = ? AND user_id = ?");
        $albumStmt->execute([$album_id, $user_id]);
    } elseif ($has_artist_id) {
        // If only artist_id exists, try to find via artists table
        $albumStmt = $conn->prepare("
            SELECT a.* 
            FROM albums a
            LEFT JOIN artists art ON a.artist_id = art.id
            WHERE a.id = ? AND (art.user_id = ? OR a.artist_id = ?)
        ");
        $albumStmt->execute([$album_id, $user_id, $user_id]);
    } else {
        // Fallback: just check by id (less secure but prevents error)
        $albumStmt = $conn->prepare("SELECT * FROM albums WHERE id = ?");
        $albumStmt->execute([$album_id]);
    }
    $current_album = $albumStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get songs in this album
    if ($current_album) {
        $songsStmt = $conn->prepare("SELECT id, title FROM songs WHERE album_id = ? ORDER BY id DESC");
        $songsStmt->execute([$album_id]);
        $album_songs = $songsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Check if user is admin
$is_admin = false;
try {
    $roleCheckStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $roleCheckStmt->execute([$user_id]);
    $userRole = $roleCheckStmt->fetchColumn();
    $is_admin = in_array($userRole, ['admin', 'super_admin']);
} catch (Exception $e) {
    // Role column might not exist
}

// Get songs for selection - admin can see ALL songs, regular users only their own
$user_songs = [];
try {
    if ($is_admin) {
        // Admin can see all songs
        $songsStmt = $conn->prepare("
            SELECT DISTINCT s.id, s.title, s.album_id,
                   COALESCE(s.artist, u.username, 'Unknown Artist') as artist_name
            FROM songs s
            LEFT JOIN users u ON s.uploaded_by = u.id
            ORDER BY s.id DESC
        ");
        $songsStmt->execute();
    } else {
        // Regular user - only their songs
        $songsStmt = $conn->prepare("
            SELECT DISTINCT s.id, s.title, s.album_id,
                   COALESCE(s.artist, u.username, 'Unknown Artist') as artist_name
            FROM songs s
            LEFT JOIN users u ON s.uploaded_by = u.id
            LEFT JOIN song_collaborators sc ON sc.song_id = s.id
            WHERE s.uploaded_by = ? OR sc.user_id = ?
            ORDER BY s.id DESC
        ");
        $songsStmt->execute([$user_id, $user_id]);
    }
    $user_songs = $songsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching songs: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $album_id > 0 ? 'Edit' : 'Create'; ?> Album - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        .album-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
        }
        .album-form {
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
        input[type="text"], input[type="date"], textarea, input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
            font-family: inherit;
        }
        textarea {
            min-height: 150px;
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
    
    <div class="album-container">
        <h1 style="margin-bottom: 20px;"><i class="fas fa-compact-disc"></i> <?php echo $album_id > 0 ? 'Edit' : 'Create'; ?> Album</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="album-form">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="album_id" value="<?php echo $album_id; ?>">
                
                <div class="form-group">
                    <label>Album Title *</label>
                    <input type="text" name="title" required value="<?php echo htmlspecialchars($current_album['title'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description"><?php echo htmlspecialchars($current_album['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Release Date</label>
                    <input type="date" name="release_date" value="<?php echo $current_album['release_date'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Cover Art</label>
                    <?php if (!empty($current_album['cover_art'])): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="<?php echo htmlspecialchars($current_album['cover_art']); ?>" 
                                 alt="Current cover" style="max-width: 200px; border-radius: 5px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="cover_art" accept="image/*">
                    <small style="color: #666;">Leave empty to keep current cover art</small>
                </div>
                
                <div class="form-group">
                    <label>Select Songs for Album</label>
                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 10px; background: #f9f9f9;">
                        <?php if (!empty($user_songs)): ?>
                            <?php foreach ($user_songs as $song): 
                                $is_in_album = false;
                                $album_song_ids = array_column($album_songs, 'id');
                                if (in_array($song['id'], $album_song_ids)) {
                                    $is_in_album = true;
                                }
                            ?>
                                <label style="display: block; padding: 8px; margin-bottom: 5px; background: white; border-radius: 5px; cursor: pointer;">
                                    <input type="checkbox" name="album_songs[]" value="<?php echo $song['id']; ?>" 
                                           <?php echo ($is_in_album) ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($song['title']); ?>
                                    <?php if (!empty($song['artist_name'])): ?>
                                        <small style="color: #666;"> - <?php echo htmlspecialchars($song['artist_name']); ?></small>
                                    <?php endif; ?>
                                    <?php if ($song['album_id'] && $song['album_id'] != $album_id): ?>
                                        <small style="color: #999;">(Already in another album)</small>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #999; padding: 20px; text-align: center;">No songs available. Upload songs first.</p>
                        <?php endif; ?>
                    </div>
                    <small style="color: #666;">Select songs to add to this album. You can add songs you uploaded or collaborated on.</small>
                </div>
                
                <button type="submit" name="save_album" class="btn-save">
                    <i class="fas fa-save"></i> Save Album
                </button>
            </form>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="artist-profile-mobile.php?tab=albums" style="color: #666; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Artist Profile
            </a>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>

