<?php
// Debug installation step 3
session_start();

echo "<h2>Installation Debug - Step 3</h2>";
echo "<p>Current step: " . ($_GET['step'] ?? 'not set') . "</p>";
echo "<p>POST data: " . print_r($_POST, true) . "</p>";
echo "<p>Session data: " . print_r($_SESSION, true) . "</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>Form submitted!</h3>";
    
    $admin_username = $_POST['admin_username'] ?? '';
    $admin_email = $_POST['admin_email'] ?? '';
    $admin_password = $_POST['admin_password'] ?? '';
    
    echo "<p>Username: $admin_username</p>";
    echo "<p>Email: $admin_email</p>";
    echo "<p>Password: " . (empty($admin_password) ? 'EMPTY' : 'SET') . "</p>";
    
    if (empty($admin_username) || empty($admin_email) || empty($admin_password)) {
        echo "<p style='color: red;'>❌ All admin fields are required.</p>";
    } else {
        echo "<p style='color: green;'>✅ All fields filled!</p>";
        
        // Test database connection
        try {
            $db_host = $_SESSION['db_host'] ?? 'localhost';
            $db_name = $_SESSION['db_name'] ?? 'music_streaming';
            $db_user = $_SESSION['db_user'] ?? 'root';
            $db_pass = $_SESSION['db_pass'] ?? '';
            
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
            echo "<p style='color: green;'>✅ Database connection successful!</p>";
            
            // Test if users table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: green;'>✅ Users table exists!</p>";
            } else {
                echo "<p style='color: red;'>❌ Users table does not exist!</p>";
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
        }
    }
}
?>

<form method="POST">
    <h3>Test Admin Account Creation</h3>
    <p>
        <label>Username: <input type="text" name="admin_username" required></label>
    </p>
    <p>
        <label>Email: <input type="email" name="admin_email" required></label>
    </p>
    <p>
        <label>Password: <input type="password" name="admin_password" required></label>
    </p>
    <p>
        <button type="submit">Test Submit</button>
    </p>
</form>

<p><a href="install.php">Back to Installation</a></p>
