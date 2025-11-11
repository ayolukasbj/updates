<?php
// Debug artist-dashboard.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Artist Dashboard Debug</h1>";

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
    require_once 'classes/User.php';
    require_once 'classes/Song.php';
    require_once 'classes/Artist.php';
    echo "<p>✅ Classes loaded</p>";
} catch (Exception $e) {
    echo "<p>❌ Classes error: " . $e->getMessage() . "</p>";
    exit;
}

// Check if user is logged in
if (!is_logged_in()) {
    echo "<p>❌ User not logged in</p>";
    echo "<p><a href='login.php'>Login</a></p>";
    exit;
}

echo "<p>✅ User logged in</p>";

// Test database connection
try {
    $database = new Database();
    $db = $database->getConnection();
    echo "<p>✅ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p>❌ Database connection error: " . $e->getMessage() . "</p>";
    exit;
}

// Test User class
try {
    $user = new User($db);
    $user_id = get_user_id();
    $user_data = $user->getUserById($user_id);
    echo "<p>✅ User data retrieved</p>";
    echo "<p>User subscription type: " . ($user_data['subscription_type'] ?? 'not set') . "</p>";
} catch (Exception $e) {
    echo "<p>❌ User class error: " . $e->getMessage() . "</p>";
}

// Test Artist class methods
try {
    $artist = new Artist($db);
    $artist_profile = $artist->getArtistByUserId($user_id);
    echo "<p>✅ getArtistByUserId() works</p>";
    if ($artist_profile) {
        echo "<p>Artist profile found: " . $artist_profile['name'] . "</p>";
    } else {
        echo "<p>No artist profile found</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Artist methods error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='artist-dashboard.php'>Try Artist Dashboard</a></p>";
?>
