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

$message = '';
$user = null;

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_username = trim($_POST['test_username'] ?? '');
    $test_bio = trim($_POST['test_bio'] ?? '');
    
    try {
        echo "<h3>Attempting UPDATE...</h3>";
        echo "<p>User ID: $user_id</p>";
        echo "<p>Username: " . htmlspecialchars($test_username) . "</p>";
        echo "<p>Bio: " . htmlspecialchars($test_bio) . "</p>";
        
        $stmt = $conn->prepare("UPDATE users SET username = ?, bio = ? WHERE id = ?");
        $result = $stmt->execute([$test_username, $test_bio, $user_id]);
        
        echo "<p>Execute result: " . ($result ? 'TRUE' : 'FALSE') . "</p>";
        echo "<p>Rows affected: " . $stmt->rowCount() . "</p>";
        
        // Verify immediately
        $verify_stmt = $conn->prepare("SELECT username, bio FROM users WHERE id = ?");
        $verify_stmt->execute([$user_id]);
        $verify_data = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h3>Verification (immediate):</h3>";
        echo "<pre>";
        print_r($verify_data);
        echo "</pre>";
        
        $message = "Update executed. Check results above.";
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        echo "<p style='color: red;'>" . htmlspecialchars($message) . "</p>";
    }
}

// Fetch current user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "<p style='color: red;'>Error fetching user: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Profile Update</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        input, textarea { width: 300px; padding: 8px; margin: 5px 0; }
        button { padding: 10px 20px; background: #4CAF50; color: white; border: none; cursor: pointer; }
        .info { background: #f0f0f0; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Test Profile Update</h1>
    
    <?php if ($message): ?>
        <div class="info"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <h2>Current Data in Database:</h2>
    <div class="info">
        <strong>Username:</strong> <?php echo htmlspecialchars($user['username'] ?? 'NULL'); ?><br>
        <strong>Bio:</strong> <?php echo htmlspecialchars($user['bio'] ?? 'NULL'); ?><br>
        <strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? 'NULL'); ?><br>
    </div>
    
    <h2>Update Test:</h2>
    <form method="POST">
        <label>Username:</label><br>
        <input type="text" name="test_username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required><br>
        
        <label>Bio:</label><br>
        <textarea name="test_bio" rows="4"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea><br>
        
        <button type="submit">Test Update</button>
    </form>
    
    <hr>
    <a href="profile-diagnostic.php">View Full Diagnostic</a> | 
    <a href="artist-profile-mobile.php">Back to Profile</a>
</body>
</html>

