<?php
// Minimal dashboard test - step by step
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Dashboard Debug - Step by Step</h1>";

echo "<h2>Step 1: Basic PHP</h2>";
echo "<p>✅ PHP is working</p>";

echo "<h2>Step 2: Session Check</h2>";
session_start();
if (isset($_SESSION['user_id'])) {
    echo "<p>✅ User session exists: " . $_SESSION['user_id'] . "</p>";
} else {
    echo "<p>❌ No user session - redirecting to login</p>";
    echo "<p><a href='login.php'>Go to Login</a></p>";
    exit;
}

echo "<h2>Step 3: Include Config</h2>";
try {
    require_once 'config/config.php';
    echo "<p>✅ Config loaded</p>";
} catch (Exception $e) {
    echo "<p>❌ Config error: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h2>Step 4: Include Database</h2>";
try {
    require_once 'config/database.php';
    echo "<p>✅ Database config loaded</p>";
} catch (Exception $e) {
    echo "<p>❌ Database config error: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h2>Step 5: Test Database Connection</h2>";
try {
    $database = new Database();
    $db = $database->getConnection();
    echo "<p>✅ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p>❌ Database connection error: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h2>Step 6: Load User Class</h2>";
try {
    require_once 'classes/User.php';
    echo "<p>✅ User class loaded</p>";
} catch (Exception $e) {
    echo "<p>❌ User class error: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h2>Step 7: Create User Object</h2>";
try {
    $user = new User($db);
    echo "<p>✅ User object created</p>";
} catch (Exception $e) {
    echo "<p>❌ User object error: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h2>Step 8: Get User Data</h2>";
try {
    $user_data = $user->getUserById(get_user_id());
    if ($user_data) {
        echo "<p>✅ User data retrieved</p>";
        echo "<p>Username: " . htmlspecialchars($user_data['username']) . "</p>";
    } else {
        echo "<p>❌ No user data found</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ User data error: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h2>✅ All Steps Completed Successfully!</h2>";
echo "<p>The dashboard should work now. <a href='dashboard.php'>Try Dashboard</a></p>";
?>
