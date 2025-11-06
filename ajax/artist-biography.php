<?php
// ajax/artist-biography.php - Biography tab content for artist profile
// This file fetches biography from user edit screen (users.bio)
// Disable error display for AJAX calls
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set proper headers - CORS for IP/ngrok compatibility
header('Content-Type: text/html; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fix paths - use __DIR__ to get the actual file location
// When included from artist-profile.php, relative paths break
$ajax_dir = __DIR__; // Directory of this file (ajax/)
$project_root = dirname($ajax_dir); // Parent directory (project root)

require_once $project_root . '/config/config.php';
require_once $project_root . '/config/database.php';

// Support both GET parameter names and direct variable assignment from include
$artist_name = isset($_GET['artist_name']) ? trim($_GET['artist_name']) : (isset($tab_artist_name) ? $tab_artist_name : '');
$artist_id = isset($_GET['artist_id']) ? (int)$_GET['artist_id'] : (isset($tab_artist_id) ? (int)$tab_artist_id : 0);

if (empty($artist_name) && empty($artist_id)) {
    echo '<p style="text-align: center; color: #999; padding: 40px;">No artist specified.</p>';
    exit;
}

$bio_text = '';

try {
    if (!class_exists('Database')) {
        die('<p style="text-align: center; color: #999; padding: 40px;">Database class not found.</p>');
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    
    // Fetch biography from users table (user edit screen)
    if ($artist_id) {
        // Check if artist_id is from artists table or users table
        $checkArtistStmt = $conn->prepare("SELECT id, user_id FROM artists WHERE id = ? OR user_id = ? LIMIT 1");
        $checkArtistStmt->execute([$artist_id, $artist_id]);
        $artistRecord = $checkArtistStmt->fetch(PDO::FETCH_ASSOC);
        
        // Determine actual user_id to use
        $actual_user_id = $artist_id;
        if ($artistRecord && !empty($artistRecord['user_id'])) {
            $actual_user_id = $artistRecord['user_id'];
        }
        
        $bioStmt = $conn->prepare("SELECT bio FROM users WHERE id = ?");
        $bioStmt->execute([$actual_user_id]);
        $user_bio = $bioStmt->fetch(PDO::FETCH_ASSOC);
        if ($user_bio && !empty($user_bio['bio'])) {
            $bio_text = trim($user_bio['bio']);
        }
    } else {
        // Get bio by artist name
        $bioStmt = $conn->prepare("SELECT bio FROM users WHERE LOWER(username) = LOWER(?)");
        $bioStmt->execute([$artist_name]);
        $user_bio = $bioStmt->fetch(PDO::FETCH_ASSOC);
        if ($user_bio && !empty($user_bio['bio'])) {
            $bio_text = trim($user_bio['bio']);
        }
    }
} catch (Exception $e) {
    error_log("Error in artist-biography.php: " . $e->getMessage());
    echo '<p style="text-align: center; color: #999; padding: 40px;">Error loading biography. Please try again.</p>';
    exit;
} catch (Error $e) {
    error_log("Fatal error in artist-biography.php: " . $e->getMessage());
    echo '<p style="text-align: center; color: #999; padding: 40px;">Error loading biography. Please try again.</p>';
    exit;
}
?>

<div class="playlists-section">
    <h2 class="section-title">Biography</h2>
    <div style="color: #666; line-height: 1.8; padding: 20px 0;">
        <?php 
        if (!empty($bio_text)) {
            echo nl2br(htmlspecialchars($bio_text));
        } else {
            echo '<p style="text-align: center; color: #999; padding: 40px;">No biography available yet.</p>';
        }
        ?>
    </div>
</div>

