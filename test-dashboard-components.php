<?php
// Test dashboard components
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Dashboard Component Test</h1>";

try {
    require_once 'config/config.php';
    require_once 'config/database.php';
    require_once 'classes/User.php';
    require_once 'classes/Song.php';
    require_once 'classes/Playlist.php';
    
    echo "<p>✅ All classes loaded</p>";
    
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);
    $song = new Song($db);
    $playlist = new Playlist($db);
    
    echo "<p>✅ All objects created</p>";
    
    // Test methods
    session_start();
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        
        echo "<h2>Testing Methods:</h2>";
        
        try {
            $user_data = $user->getUserById($user_id);
            echo "<p>✅ getUserById works</p>";
        } catch (Exception $e) {
            echo "<p>❌ getUserById error: " . $e->getMessage() . "</p>";
        }
        
        try {
            $user_stats = $user->getUserStats($user_id);
            echo "<p>✅ getUserStats works</p>";
        } catch (Exception $e) {
            echo "<p>❌ getUserStats error: " . $e->getMessage() . "</p>";
        }
        
        try {
            $recently_played = $song->getRecentlyPlayed($user_id, 10);
            echo "<p>✅ getRecentlyPlayed works</p>";
        } catch (Exception $e) {
            echo "<p>❌ getRecentlyPlayed error: " . $e->getMessage() . "</p>";
        }
        
        try {
            $user_playlists = $playlist->getUserPlaylists($user_id, 6);
            echo "<p>✅ getUserPlaylists works</p>";
        } catch (Exception $e) {
            echo "<p>❌ getUserPlaylists error: " . $e->getMessage() . "</p>";
        }
        
        try {
            $recommended_songs = $song->getRecommendedSongs($user_id, 8);
            echo "<p>✅ getRecommendedSongs works</p>";
        } catch (Exception $e) {
            echo "<p>❌ getRecommendedSongs error: " . $e->getMessage() . "</p>";
        }
        
    } else {
        echo "<p>❌ No user session - please login first</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ General error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='dashboard.php'>Try Dashboard Now</a></p>";
echo "<p><a href='dashboard-simple.php'>Try Simple Dashboard</a></p>";
?>
