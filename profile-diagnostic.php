<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Redirect if not logged in
if (!is_logged_in()) {
    die('Not logged in');
}

$user_id = get_user_id();

$db = new Database();
$conn = $db->getConnection();

echo "<h1>Profile Diagnostic Tool</h1>";
echo "<p>User ID: " . htmlspecialchars($user_id) . "</p>";
echo "<p>Session Username: " . htmlspecialchars($_SESSION['username'] ?? 'NOT SET') . "</p>";

// Fetch user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>User Data from Database:</h2>";
    echo "<pre>";
    print_r($user);
    echo "</pre>";
    
    echo "<h3>Specific Fields:</h3>";
    echo "<ul>";
    echo "<li>Username: " . htmlspecialchars($user['username'] ?? 'NULL') . "</li>";
    echo "<li>Email: " . htmlspecialchars($user['email'] ?? 'NULL') . "</li>";
    echo "<li>Bio: " . htmlspecialchars($user['bio'] ?? 'NULL') . "</li>";
    echo "<li>Facebook: " . htmlspecialchars($user['facebook'] ?? 'NULL') . "</li>";
    echo "<li>Twitter: " . htmlspecialchars($user['twitter'] ?? 'NULL') . "</li>";
    echo "<li>Instagram: " . htmlspecialchars($user['instagram'] ?? 'NULL') . "</li>";
    echo "<li>YouTube: " . htmlspecialchars($user['youtube'] ?? 'NULL') . "</li>";
    echo "<li>Avatar: " . htmlspecialchars($user['avatar'] ?? 'NULL') . "</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>Database Connection Info:</h3>";
echo "<ul>";
echo "<li>Database: " . DB_NAME . "</li>";
echo "<li>Host: " . DB_HOST . "</li>";
echo "</ul>";

// Check error log
echo "<h3>Recent Error Log (if accessible):</h3>";
$log_file = 'C:/xampp/htdocs/music/error.log'; // Adjust path as needed
if (file_exists($log_file)) {
    $log_lines = file($log_file);
    $recent_logs = array_slice($log_lines, -50); // Last 50 lines
    echo "<pre style='background: #f5f5f5; padding: 10px; overflow: auto; max-height: 400px;'>";
    echo htmlspecialchars(implode('', $recent_logs));
    echo "</pre>";
} else {
    echo "<p>Error log file not found at: " . htmlspecialchars($log_file) . "</p>";
    echo "<p>Check PHP error_log location in php.ini</p>";
}

echo "<hr>";
echo "<a href='artist-profile-mobile.php'>Back to Profile</a>";
?>

