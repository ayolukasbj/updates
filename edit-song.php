<?php
// edit-song.php - Edit existing song using upload form
// Enable error reporting BEFORE any includes
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start session if not already started (BEFORE any output)
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Load configs (these may start session too)
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/song-storage.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id'])) {
    // Redirect back to referrer or default to artist profile
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($referrer) && (strpos($referrer, 'artist-profile') !== false || strpos($referrer, 'my-songs') !== false)) {
        header('Location: ' . $referrer);
    } else {
        // Default redirect - try to find user's profile
        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id) {
            header('Location: artist-profile.php?id=' . $user_id . '&tab=music');
        } else {
            header('Location: my-songs.php');
        }
    }
    exit;
}

$song_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Get song data
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verify user owns this song
    $stmt = $conn->prepare("SELECT * FROM songs WHERE id = ? AND uploaded_by = ?");
    $stmt->execute([$song_id, $user_id]);
    $song = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$song) {
        $_SESSION['error_message'] = "Song not found or you don't have permission to edit it.";
        $referrer = $_SERVER['HTTP_REFERER'] ?? 'my-songs.php';
        header('Location: ' . $referrer);
        exit;
    }
    
    // Get existing collaborators if any
    $existing_collaborators = [];
    try {
        $collab_stmt = $conn->prepare("SELECT user_id FROM song_collaborators WHERE song_id = ?");
        $collab_stmt->execute([$song_id]);
        $existing_collaborators = $collab_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // Table might not exist yet, ignore
    }
    
    // Check if redirected after update
    $updated = isset($_GET['updated']) && $_GET['updated'] == 1;
    if ($updated) {
        $_SESSION['song_updated_message'] = "Song updated successfully!";
    }
    
    // Re-fetch song data to ensure we have the latest version
    if ($updated) {
        $stmt = $conn->prepare("SELECT * FROM songs WHERE id = ? AND uploaded_by = ?");
        $stmt->execute([$song_id, $user_id]);
        $song = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$song) {
            $_SESSION['error_message'] = "Song not found after update.";
            $referrer = $_SERVER['HTTP_REFERER'] ?? 'my-songs.php';
            header('Location: ' . $referrer);
            exit;
        }
    }
    
    // Pre-fill $_POST with song data for the upload form
    $_POST = [
        'title' => $song['title'] ?? '',
        'artist' => $song['artist'] ?? '',
        'is_collaboration' => $song['is_collaboration'] ?? 0,
        'track_type' => $song['track_type'] ?? '',
        'album' => $song['album_title'] ?? $song['album'] ?? '',
        'genre' => $song['genre'] ?? '',
        'year' => $song['release_year'] ?? $song['year'] ?? '',
        'track_number' => $song['track_number'] ?? '',
        'description' => $song['description'] ?? '',
        'lyrics' => $song['lyrics'] ?? '',
        'producer' => $song['producer'] ?? '',
        'composer' => $song['composer'] ?? '',
        'lyricist' => $song['lyricist'] ?? '',
        'record_label' => $song['record_label'] ?? '',
        'language' => $song['language'] ?? 'English',
        'mood' => $song['mood'] ?? '',
        'tempo' => $song['tempo'] ?? '',
        'instruments' => $song['instruments'] ?? '',
        'tags' => $song['tags'] ?? '',
        'edit_mode' => true,
        'song_id' => $song_id,
        'selected_artist_ids' => implode(',', $existing_collaborators) // Pre-fill collaborators
    ];
    
    // Set variables for upload form
    $editing_song = true;
    $edit_song_data = $song;
    
} catch (Exception $e) {
    error_log("Error loading song in edit-song.php: " . $e->getMessage());
    $_SESSION['error_message'] = "Error loading song. Please try again.";
    $referrer = $_SERVER['HTTP_REFERER'] ?? 'my-songs.php';
    header('Location: ' . $referrer);
    exit;
}

// Include the upload form
// Capture any output/errors to prevent headers already sent issues
// Don't start output buffering if it's already active (upload.php might have started it)
if (ob_get_level() == 0) {
    ob_start();
}

try {
    include 'upload.php';
    
    // If upload.php redirected, it will have exited, so we won't reach here
    // Only output if upload.php didn't redirect
    if (ob_get_level() > 0) {
        $output = ob_get_clean();
        if (!headers_sent() && !empty($output)) {
            echo $output;
        }
    }
} catch (Throwable $e) {
    // Clean up any output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log("Fatal error in edit-song.php when including upload.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $_SESSION['error_message'] = "Error loading edit form: " . $e->getMessage();
    $referrer = $_SERVER['HTTP_REFERER'] ?? 'my-songs.php';
    if (!headers_sent()) {
        header('Location: ' . $referrer);
    }
    exit;
}
?>

