<?php
require_once 'config/config.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "<!DOCTYPE html><html><head><title>Fix Songs Table</title></head><body>";
echo "<h1>Songs Table Schema Fixer</h1>";

$errors = [];
$success = [];

// Required columns for songs table
$required_columns = [
    'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
    'title' => 'VARCHAR(255) NOT NULL',
    'artist_id' => 'INT DEFAULT NULL',
    'album_title' => 'VARCHAR(255) DEFAULT NULL',
    'genre' => 'VARCHAR(100) DEFAULT NULL',
    'release_year' => 'INT DEFAULT NULL',
    'file_path' => 'VARCHAR(500) NOT NULL',
    'cover_art' => 'VARCHAR(500) DEFAULT NULL',
    'duration' => 'INT DEFAULT NULL',
    'file_size' => 'BIGINT DEFAULT NULL',
    'lyrics' => 'TEXT DEFAULT NULL',
    'is_explicit' => 'TINYINT(1) DEFAULT 0',
    'status' => 'VARCHAR(50) DEFAULT \'active\'',
    'is_featured' => 'TINYINT(1) DEFAULT 0',
    'upload_date' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'uploaded_by' => 'INT NOT NULL',
    'plays' => 'INT DEFAULT 0',
    'downloads' => 'INT DEFAULT 0'
];

try {
    // Check if table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'songs'");
    $table_exists = $stmt->rowCount() > 0;
    
    if (!$table_exists) {
        echo "<div style='background: #fff3cd; padding: 15px; margin: 10px 0; border: 1px solid #ffc107; border-radius: 5px;'>";
        echo "<h3>Creating Songs Table...</h3>";
        
        // Create table
        $create_sql = "CREATE TABLE IF NOT EXISTS songs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            artist_id INT DEFAULT NULL,
            album_title VARCHAR(255) DEFAULT NULL,
            genre VARCHAR(100) DEFAULT NULL,
            release_year INT DEFAULT NULL,
            file_path VARCHAR(500) NOT NULL,
            cover_art VARCHAR(500) DEFAULT NULL,
            duration INT DEFAULT NULL,
            file_size BIGINT DEFAULT NULL,
            lyrics TEXT DEFAULT NULL,
            is_explicit TINYINT(1) DEFAULT 0,
            status VARCHAR(50) DEFAULT 'active',
            is_featured TINYINT(1) DEFAULT 0,
            upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            uploaded_by INT NOT NULL,
            plays INT DEFAULT 0,
            downloads INT DEFAULT 0,
            INDEX idx_uploaded_by (uploaded_by),
            INDEX idx_status (status),
            INDEX idx_upload_date (upload_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $conn->exec($create_sql);
        $success[] = "✓ Created songs table successfully!";
        echo "<p style='color: #155724;'>✓ Songs table created successfully!</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #cce5ff; padding: 15px; margin: 10px 0; border: 1px solid #b8daff; border-radius: 5px;'>";
        echo "<p>Songs table exists. Checking columns...</p>";
        echo "</div>";
    }
    
    // Get existing columns
    $stmt = $conn->query("DESCRIBE songs");
    $existing_columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[$row['Field']] = $row;
    }
    
    echo "<h2>Checking Required Columns...</h2>";
    echo "<ul>";
    
    // Add missing columns
    foreach ($required_columns as $column_name => $column_def) {
        if (!isset($existing_columns[$column_name])) {
            try {
                // Skip id and upload_date as they're special
                if ($column_name === 'id' || $column_name === 'upload_date') {
                    echo "<li style='color: blue;'>- Skipping special column: <strong>$column_name</strong></li>";
                    continue;
                }
                
                $sql = "ALTER TABLE songs ADD COLUMN `$column_name` $column_def";
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
    echo "<h2>Final Songs Table Structure:</h2>";
    $stmt = $conn->query("DESCRIBE songs");
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $highlight = array_key_exists($row['Field'], $required_columns) ? "background: #d4edda;" : "";
        echo "<tr style='$highlight'>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
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
    
    if (count($errors) == 0) {
        echo "<div style='background: #cce5ff; padding: 15px; margin: 20px 0; border: 1px solid #b8daff; border-radius: 5px;'>";
        echo "<p style='color: #004085;'><strong>Songs table is now ready!</strong></p>";
        echo "<p style='color: #004085;'>You can now upload songs to the database.</p>";
        echo "</div>";
    }
    
    // Test database connection
    echo "<h2>Testing Database Connection:</h2>";
    try {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM songs");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p style='color: green;'>✓ Database connection working!</p>";
        echo "<p>Current songs in database: <strong>" . $count['count'] . "</strong></p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Database connection error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; margin: 20px 0; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<p style='color: #721c24;'><strong>Fatal Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p>";
echo "<a href='upload.php' style='padding: 10px 15px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Upload</a>";
echo "<a href='artist-profile-mobile.php?tab=music' style='padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;'>View My Songs</a>";
echo "<a href='check-db-schema.php' style='padding: 10px 15px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;'>Check All Tables</a>";
echo "</p>";
echo "</body></html>";
?>

