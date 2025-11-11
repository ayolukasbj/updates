<?php
/**
 * Add is_verified column to users table
 * Run this once to enable email verification feature
 */

require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Add Verification Column</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>Add Email Verification Column</h1>";

try {
    // Check if is_verified column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'is_verified'");
    
    if ($result->rowCount() > 0) {
        echo '<div class="info">✓ The <code>is_verified</code> column already exists in the users table.</div>';
    } else {
        // Add is_verified column
        $conn->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0 AFTER email");
        echo '<div class="success">✓ Successfully added <code>is_verified</code> column to users table!</div>';
    }
    
    // Check if verification_token column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'verification_token'");
    
    if ($result->rowCount() > 0) {
        echo '<div class="info">✓ The <code>verification_token</code> column already exists in the users table.</div>';
    } else {
        // Add verification_token column
        $conn->exec("ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) NULL AFTER is_verified");
        echo '<div class="success">✓ Successfully added <code>verification_token</code> column to users table!</div>';
    }
    
    // Check current table structure
    $columns = $conn->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<div class="info">';
    echo '<h3>Current Users Table Structure:</h3>';
    echo '<table border="1" cellpadding="10" style="border-collapse: collapse; width: 100%;">';
    echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>';
    foreach ($columns as $column) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($column['Field']) . '</td>';
        echo '<td>' . htmlspecialchars($column['Type']) . '</td>';
        echo '<td>' . htmlspecialchars($column['Null']) . '</td>';
        echo '<td>' . htmlspecialchars($column['Default'] ?? 'NULL') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';
    
    echo '<div class="success">';
    echo '<h3>✓ Email Verification Setup Complete!</h3>';
    echo '<p>You can now:</p>';
    echo '<ul>';
    echo '<li>Manually verify users from the admin panel (User Edit page)</li>';
    echo '<li>Users will receive verification emails upon registration</li>';
    echo '<li>Unverified users will be prompted to verify their email</li>';
    echo '</ul>';
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<div class="error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

echo '<a href="users.php" class="btn">← Back to Users</a>';
echo '<a href="index.php" class="btn" style="background: #28a745; margin-left: 10px;">Go to Dashboard</a>';
echo '</body></html>';
?>

