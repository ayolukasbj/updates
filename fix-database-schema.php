<?php
require_once 'config/config.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "<!DOCTYPE html><html><head><title>Fix Database Schema</title></head><body>";
echo "<h1>Database Schema Fixer</h1>";

$errors = [];
$success = [];

// Define required columns for users table
$required_columns = [
    'bio' => 'TEXT DEFAULT NULL',
    'facebook' => 'VARCHAR(255) DEFAULT NULL',
    'twitter' => 'VARCHAR(255) DEFAULT NULL',
    'instagram' => 'VARCHAR(255) DEFAULT NULL',
    'youtube' => 'VARCHAR(255) DEFAULT NULL',
    'avatar' => 'VARCHAR(255) DEFAULT NULL'
];

try {
    // Get existing columns
    $stmt = $conn->query("DESCRIBE users");
    $existing_columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $row['Field'];
    }
    
    echo "<h2>Checking Users Table...</h2>";
    echo "<ul>";
    
    // Add missing columns
    foreach ($required_columns as $column_name => $column_def) {
        if (!in_array($column_name, $existing_columns)) {
            try {
                $sql = "ALTER TABLE users ADD COLUMN `$column_name` $column_def";
                $conn->exec($sql);
                $success[] = "✓ Added column: $column_name";
                echo "<li style='color: green;'>✓ Added column: <strong>$column_name</strong></li>";
            } catch (PDOException $e) {
                $errors[] = "✗ Failed to add $column_name: " . $e->getMessage();
                echo "<li style='color: red;'>✗ Failed to add <strong>$column_name</strong>: " . htmlspecialchars($e->getMessage()) . "</li>";
            }
        } else {
            echo "<li style='color: blue;'>- Column already exists: <strong>$column_name</strong></li>";
        }
    }
    echo "</ul>";
    
    // Verify final structure
    echo "<h2>Final Users Table Structure:</h2>";
    $stmt = $conn->query("DESCRIBE users");
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $highlight = in_array($row['Field'], array_keys($required_columns)) ? "background: #d4edda;" : "";
        echo "<tr style='$highlight'>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if (count($success) > 0) {
        echo "<div style='background: #d4edda; padding: 15px; margin: 20px 0; border: 1px solid #c3e6cb; border-radius: 5px;'>";
        echo "<h3 style='color: #155724; margin-top: 0;'>Success!</h3>";
        foreach ($success as $msg) {
            echo "<p style='color: #155724; margin: 5px 0;'>$msg</p>";
        }
        echo "</div>";
    }
    
    if (count($errors) > 0) {
        echo "<div style='background: #f8d7da; padding: 15px; margin: 20px 0; border: 1px solid #f5c6cb; border-radius: 5px;'>";
        echo "<h3 style='color: #721c24; margin-top: 0;'>Errors:</h3>";
        foreach ($errors as $msg) {
            echo "<p style='color: #721c24; margin: 5px 0;'>$msg</p>";
        }
        echo "</div>";
    }
    
    if (count($success) > 0 || count($errors) == 0) {
        echo "<div style='background: #cce5ff; padding: 15px; margin: 20px 0; border: 1px solid #b8daff; border-radius: 5px;'>";
        echo "<p style='color: #004085;'><strong>Database schema is now ready!</strong></p>";
        echo "<p style='color: #004085;'>You can now use the profile update feature.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; margin: 20px 0; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<p style='color: #721c24;'><strong>Fatal Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p>";
echo "<a href='check-db-schema.php' style='padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Check Schema</a>";
echo "<a href='test-profile-update.php' style='padding: 10px 15px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Profile Update</a>";
echo "<a href='artist-profile-mobile.php' style='padding: 10px 15px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;'>Go to Profile</a>";
echo "</p>";
echo "</body></html>";
?>

