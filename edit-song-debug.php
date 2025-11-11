<?php
// Debug version of edit-song.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Debug Edit Song</h1>";

// Step 1: Check session
echo "<h2>Step 1: Session</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "<p>✅ Session started</p>";
} else {
    echo "<p>✅ Session already started</p>";
}

// Step 2: Load config
echo "<h2>Step 2: Config</h2>";
try {
    require_once 'config/config.php';
    echo "<p>✅ Config loaded</p>";
} catch (Exception $e) {
    echo "<p>❌ Config error: " . $e->getMessage() . "</p>";
    exit;
}

// Step 3: Load database
echo "<h2>Step 3: Database</h2>";
try {
    require_once 'config/database.php';
    echo "<p>✅ Database config loaded</p>";
} catch (Exception $e) {
    echo "<p>❌ Database config error: " . $e->getMessage() . "</p>";
    exit;
}

// Step 4: Load song-storage
echo "<h2>Step 4: Song Storage</h2>";
try {
    require_once 'includes/song-storage.php';
    echo "<p>✅ Song storage loaded</p>";
} catch (Exception $e) {
    echo "<p>❌ Song storage error: " . $e->getMessage() . "</p>";
    exit;
}

// Step 5: Check login
echo "<h2>Step 5: Login Check</h2>";
if (!isset($_SESSION['user_id'])) {
    echo "<p>❌ User not logged in</p>";
    exit;
}
echo "<p>✅ User logged in: " . $_SESSION['user_id'] . "</p>";

// Step 6: Check ID parameter
echo "<h2>Step 6: ID Parameter</h2>";
if (!isset($_GET['id'])) {
    echo "<p>❌ No ID parameter</p>";
    exit;
}
$song_id = $_GET['id'];
echo "<p>✅ Song ID: " . $song_id . "</p>";

// Step 7: Get song data
echo "<h2>Step 7: Get Song Data</h2>";
$user_id = $_SESSION['user_id'];
try {
    $db = new Database();
    $conn = $db->getConnection();
    echo "<p>✅ Database connection successful</p>";
    
    $stmt = $conn->prepare("SELECT * FROM songs WHERE id = ? AND uploaded_by = ?");
    $stmt->execute([$song_id, $user_id]);
    $song = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$song) {
        echo "<p>❌ Song not found or not owned by user</p>";
        exit;
    }
    echo "<p>✅ Song found: " . htmlspecialchars($song['title']) . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: <pre>" . $e->getTraceAsString() . "</pre></p>";
    exit;
}

// Step 8: Try to include upload.php
echo "<h2>Step 8: Include Upload.php</h2>";
try {
    // Set variables that upload.php expects
    $editing_song = true;
    $edit_song_data = $song;
    $_POST = [
        'title' => $song['title'] ?? '',
        'edit_mode' => true,
        'song_id' => $song_id,
    ];
    
    echo "<p>✅ Variables set, attempting to include upload.php...</p>";
    echo "<hr>";
    
    // Capture any output
    ob_start();
    include 'upload.php';
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "<p>✅ Upload.php included successfully</p>";
        echo $output;
    } else {
        echo "<p>⚠️ Upload.php included but produced no output</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error including upload.php: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: <pre>" . $e->getTraceAsString() . "</pre></p>";
} catch (Error $e) {
    echo "<p>❌ Fatal error including upload.php: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: <pre>" . $e->getTraceAsString() . "</pre></p>";
}
?>


