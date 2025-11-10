<?php
// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Admin Debug</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5}";
echo ".success{background:#d4edda;color:#155724;padding:15px;margin:10px 0;border-radius:5px}";
echo ".error{background:#f8d7da;color:#721c24;padding:15px;margin:10px 0;border-radius:5px}";
echo "table{width:100%;background:white;border-collapse:collapse;margin:10px 0}";
echo "th,td{padding:10px;border:1px solid #ddd;text-align:left}";
echo "th{background:#667eea;color:white}</style></head><body>";

echo "<h1>🔍 Admin System Debug</h1>";

// Test 1: PHP Version
echo "<div class='success'>✅ PHP Version: " . PHP_VERSION . "</div>";

// Test 2: Config file
if (file_exists('../config/database.php')) {
    echo "<div class='success'>✅ Database config file exists</div>";
    require_once '../config/database.php';
} else {
    echo "<div class='error'>❌ Database config file NOT found</div>";
    exit;
}

// Test 3: Database connection
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn) {
        echo "<div class='success'>✅ Database connection successful</div>";
    } else {
        echo "<div class='error'>❌ Database connection failed</div>";
        exit;
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Database error: " . $e->getMessage() . "</div>";
    exit;
}

// Test 4: Check users table
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<div class='success'>✅ Users table exists</div>";
    } else {
        echo "<div class='error'>❌ Users table does NOT exist</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Table check error: " . $e->getMessage() . "</div>";
}

// Test 5: Check role column
try {
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($stmt->rowCount() > 0) {
        echo "<div class='success'>✅ Role column exists</div>";
    } else {
        echo "<div class='error'>⚠️ Role column does NOT exist - Run setup SQL first!</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Column check error: " . $e->getMessage() . "</div>";
}

// Test 6: List all users
try {
    $stmt = $conn->query("SELECT id, username, email, created_at FROM users ORDER BY id ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) > 0) {
        echo "<div class='success'>✅ Found " . count($users) . " user(s)</div>";
        echo "<table><tr><th>ID</th><th>Username</th><th>Email</th><th>Created</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . $user['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='error'>⚠️ No users found in database</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ User fetch error: " . $e->getMessage() . "</div>";
}

// Test 7: Check role values (if column exists)
try {
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->query("SELECT id, username, email, role FROM users");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>User Roles</h2>";
        echo "<table><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
        foreach ($users as $user) {
            $roleClass = in_array($user['role'] ?? '', ['admin', 'super_admin']) ? 'success' : 'error';
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td><span style='padding:5px 10px;border-radius:3px;background:" . ($roleClass == 'success' ? '#d4edda' : '#f8d7da') . "'>" . ($user['role'] ?? 'NULL') . "</span></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    // Column doesn't exist, skip
}

echo "<hr><h2>📋 Next Steps:</h2>";
echo "<ol>";
echo "<li><strong>If role column doesn't exist:</strong> Run the SQL from setup instructions</li>";
echo "<li><strong>If no admin role:</strong> Visit <a href='setup-admin.php'>setup-admin.php</a></li>";
echo "<li><strong>If everything is OK:</strong> Visit <a href='login.php'>login.php</a></li>";
echo "</ol>";

echo "<hr><p><a href='test.php'>← Back to Test Page</a> | <a href='login.php'>Go to Login →</a></p>";
echo "</body></html>";
?>

