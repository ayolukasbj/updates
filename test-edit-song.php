<?php
// Simple test to isolate the error
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Test Edit Song</h1>";

// Step 1: Session
echo "<h2>Step 1: Session</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "<p>✅ Session OK</p>";

// Step 2: Check if logged in
echo "<h2>Step 2: Login Check</h2>";
if (!isset($_SESSION['user_id'])) {
    echo "<p>❌ Not logged in. Please <a href='login.php'>login</a> first.</p>";
    exit;
}
echo "<p>✅ Logged in as user ID: " . $_SESSION['user_id'] . "</p>";

// Step 3: Check ID
echo "<h2>Step 3: ID Check</h2>";
if (!isset($_GET['id'])) {
    echo "<p>❌ No ID parameter. Add ?id=11 to URL</p>";
    exit;
}
$song_id = $_GET['id'];
echo "<p>✅ Song ID: " . $song_id . "</p>";

// Step 4: Load configs
echo "<h2>Step 4: Load Configs</h2>";
try {
    require_once 'config/config.php';
    echo "<p>✅ Config loaded</p>";
} catch (Exception $e) {
    echo "<p>❌ Config error: " . $e->getMessage() . "</p>";
    exit;
}

try {
    require_once 'config/database.php';
    echo "<p>✅ Database config loaded</p>";
} catch (Exception $e) {
    echo "<p>❌ Database config error: " . $e->getMessage() . "</p>";
    exit;
}

try {
    require_once 'includes/song-storage.php';
    echo "<p>✅ Song storage loaded</p>";
} catch (Exception $e) {
    echo "<p>❌ Song storage error: " . $e->getMessage() . "</p>";
    exit;
}

// Step 5: Get song
echo "<h2>Step 5: Get Song</h2>";
try {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT * FROM songs WHERE id = ? AND uploaded_by = ?");
    $stmt->execute([$song_id, $_SESSION['user_id']]);
    $song = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$song) {
        echo "<p>❌ Song not found or not owned by user</p>";
        exit;
    }
    echo "<p>✅ Song found: " . htmlspecialchars($song['title']) . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

// Step 6: Set variables for upload.php
echo "<h2>Step 6: Set Variables</h2>";
$editing_song = true;
$edit_song_data = $song;
$_POST = [
    'title' => $song['title'] ?? '',
    'artist' => $song['artist'] ?? '',
    'edit_mode' => true,
    'song_id' => $song_id,
];
echo "<p>✅ Variables set</p>";

// Step 7: Try to include upload.php
echo "<h2>Step 7: Include Upload.php</h2>";
echo "<p>Attempting to include upload.php...</p>";
echo "<hr>";

// Suppress redirects temporarily
$original_redirect = null;
if (function_exists('redirect')) {
    // We can't easily override redirect, so let's just try
}

try {
    ob_start();
    include 'upload.php';
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "<p>✅ Upload.php included successfully</p>";
        echo "<p>Output length: " . strlen($output) . " bytes</p>";
        echo "<hr>";
        echo "<h3>Output:</h3>";
        echo $output;
    } else {
        echo "<p>⚠️ Upload.php included but produced no output</p>";
    }
} catch (Throwable $e) {
    ob_end_clean();
    echo "<p>❌ Fatal error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>


