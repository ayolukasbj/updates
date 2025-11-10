<?php
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Database Schema</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 30px auto; padding: 20px; background: #f5f5f5; }
        .success { background: #d4edda; color: #155724; padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 4px solid #17a2b8; }
        .warning { background: #fff3cd; color: #856404; padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 4px solid #ffc107; }
        h1 { color: #333; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 25px; font-size: 20px; }
        .btn { background: #667eea; color: white; padding: 12px 24px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #5568d3; }
    </style>
</head>
<body>

<h1>🔧 Database Schema Update</h1>

<?php
$updates = [];
$errors = [];

// Update Users Table
echo '<h2>👤 Updating Users Table</h2>';

$user_columns = [
    'avatar' => "VARCHAR(255) DEFAULT NULL",
    'cover_image' => "VARCHAR(255) DEFAULT NULL",
    'social_links' => "TEXT DEFAULT NULL",
    'bio' => "TEXT DEFAULT NULL"
];

foreach ($user_columns as $col_name => $col_def) {
    try {
        $check = $conn->query("SHOW COLUMNS FROM users LIKE '$col_name'");
        if ($check->rowCount() == 0) {
            $conn->exec("ALTER TABLE users ADD COLUMN $col_name $col_def");
            $updates[] = "Added '$col_name' to users table";
            echo "<div class='success'>✅ Added column: $col_name</div>";
        } else {
            echo "<div class='info'>✓ Column already exists: $col_name</div>";
        }
    } catch (Exception $e) {
        $errors[] = "Users table - $col_name: " . $e->getMessage();
        echo "<div class='error'>❌ Error with $col_name: " . $e->getMessage() . "</div>";
    }
}

// Update Artists Table
echo '<h2>🎤 Updating Artists Table</h2>';

// First, check if artists table exists
try {
    $check = $conn->query("SHOW TABLES LIKE 'artists'");
    if ($check->rowCount() == 0) {
        // Create artists table
        $conn->exec("CREATE TABLE artists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            bio TEXT,
            avatar VARCHAR(255),
            cover_image VARCHAR(255),
            verified BOOLEAN DEFAULT FALSE,
            user_id INT,
            social_links TEXT,
            total_plays BIGINT DEFAULT 0,
            total_downloads BIGINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $updates[] = "Created artists table";
        echo "<div class='success'>✅ Created artists table</div>";
    } else {
        echo "<div class='info'>✓ Artists table already exists</div>";
        
        // Add missing columns
        $artist_columns = [
            'avatar' => "VARCHAR(255) DEFAULT NULL",
            'cover_image' => "VARCHAR(255) DEFAULT NULL",
            'social_links' => "TEXT DEFAULT NULL",
            'bio' => "TEXT DEFAULT NULL",
            'verified' => "BOOLEAN DEFAULT FALSE",
            'total_plays' => "BIGINT DEFAULT 0",
            'total_downloads' => "BIGINT DEFAULT 0",
            'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];

        foreach ($artist_columns as $col_name => $col_def) {
            try {
                $check = $conn->query("SHOW COLUMNS FROM artists LIKE '$col_name'");
                if ($check->rowCount() == 0) {
                    $conn->exec("ALTER TABLE artists ADD COLUMN $col_name $col_def");
                    $updates[] = "Added '$col_name' to artists table";
                    echo "<div class='success'>✅ Added column: $col_name</div>";
                } else {
                    echo "<div class='info'>✓ Column already exists: $col_name</div>";
                }
            } catch (Exception $e) {
                $errors[] = "Artists table - $col_name: " . $e->getMessage();
                echo "<div class='error'>❌ Error with $col_name: " . $e->getMessage() . "</div>";
            }
        }
    }
} catch (Exception $e) {
    $errors[] = "Artists table: " . $e->getMessage();
    echo "<div class='error'>❌ Error creating artists table: " . $e->getMessage() . "</div>";
}

// Update Songs Table
echo '<h2>🎵 Updating Songs Table</h2>';

$song_columns = [
    'cover_art' => "VARCHAR(255) DEFAULT NULL",
    'status' => "VARCHAR(20) DEFAULT 'approved'",
    'is_featured' => "BOOLEAN DEFAULT FALSE",
    'is_explicit' => "BOOLEAN DEFAULT FALSE"
];

foreach ($song_columns as $col_name => $col_def) {
    try {
        $check = $conn->query("SHOW COLUMNS FROM songs LIKE '$col_name'");
        if ($check->rowCount() == 0) {
            $conn->exec("ALTER TABLE songs ADD COLUMN $col_name $col_def");
            $updates[] = "Added '$col_name' to songs table";
            echo "<div class='success'>✅ Added column: $col_name</div>";
        } else {
            echo "<div class='info'>✓ Column already exists: $col_name</div>";
        }
    } catch (Exception $e) {
        $errors[] = "Songs table - $col_name: " . $e->getMessage();
        echo "<div class='error'>❌ Error with $col_name: " . $e->getMessage() . "</div>";
    }
}

// Create upload directories
echo '<h2>📁 Creating Upload Directories</h2>';

$directories = [
    '../uploads/avatars/',
    '../uploads/covers/',
    '../uploads/audio/',
    '../uploads/images/'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0777, true)) {
            $updates[] = "Created directory: $dir";
            echo "<div class='success'>✅ Created directory: $dir</div>";
        } else {
            $errors[] = "Failed to create directory: $dir";
            echo "<div class='error'>❌ Failed to create: $dir</div>";
        }
    } else {
        echo "<div class='info'>✓ Directory already exists: $dir</div>";
    }
}

// Summary
echo '<h2>📊 Summary</h2>';
echo '<div class="info"><strong>Total Updates: ' . count($updates) . '</strong></div>';
if (count($errors) > 0) {
    echo '<div class="error"><strong>Total Errors: ' . count($errors) . '</strong></div>';
}

if (count($updates) > 0 && count($errors) == 0) {
    echo '<div class="success"><h3>🎉 Schema Updated Successfully!</h3></div>';
} elseif (count($errors) > 0) {
    echo '<div class="warning"><h3>⚠️ Schema updated with some errors. Please review.</h3></div>';
} else {
    echo '<div class="info"><h3>✓ Schema is already up to date!</h3></div>';
}

echo '<p style="text-align: center; margin-top: 30px;">';
echo '<a href="index.php" class="btn">🏠 Back to Dashboard</a>';
echo '<a href="artists.php" class="btn">🎤 View Artists</a>';
echo '</p>';
?>

</body>
</html>

