<?php
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Songs Diagnostic Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .success { background: #d4edda; color: #155724; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #17a2b8; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #ffc107; }
        h1 { color: #333; }
        h2 { color: #555; margin-top: 30px; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; background: white; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        tr:nth-child(even) { background: #f8f9fa; }
        pre { background: #fff; padding: 15px; border-radius: 4px; overflow-x: auto; border: 1px solid #ddd; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #333; }
    </style>
</head>
<body>
    <h1>🔍 Songs Database Diagnostic Test</h1>

<?php
// Test 1: Check database connection
echo '<h2>1️⃣ Database Connection</h2>';
try {
    $conn->query("SELECT 1");
    echo '<div class="success">✅ Database connection: OK</div>';
} catch (Exception $e) {
    echo '<div class="error">❌ Database connection failed: ' . $e->getMessage() . '</div>';
    exit;
}

// Test 2: Check if songs table exists
echo '<h2>2️⃣ Songs Table</h2>';
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'songs'");
    if ($stmt->rowCount() > 0) {
        echo '<div class="success">✅ Songs table exists</div>';
        
        // Show table structure
        echo '<h3>Table Structure:</h3>';
        $stmt = $conn->query("DESCRIBE songs");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo '<table>';
        echo '<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th></tr>';
        foreach ($columns as $col) {
            echo '<tr>';
            echo '<td><strong>' . $col['Field'] . '</strong></td>';
            echo '<td>' . $col['Type'] . '</td>';
            echo '<td>' . $col['Null'] . '</td>';
            echo '<td>' . ($col['Default'] ?? 'NULL') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="error">❌ Songs table does NOT exist!</div>';
        echo '<div class="warning">⚠️ You need to import database/schema.sql first!</div>';
        exit;
    }
} catch (Exception $e) {
    echo '<div class="error">❌ Error checking songs table: ' . $e->getMessage() . '</div>';
    exit;
}

// Test 3: Count songs in database
echo '<h2>3️⃣ Songs Count</h2>';
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM songs");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count > 0) {
        echo '<div class="success">✅ Found ' . $count . ' songs in database</div>';
    } else {
        echo '<div class="warning">⚠️ No songs in database (count: 0)</div>';
        echo '<div class="info">Songs are still in JSON file. You need to run the migration!</div>';
    }
} catch (Exception $e) {
    echo '<div class="error">❌ Error counting songs: ' . $e->getMessage() . '</div>';
}

// Test 4: Check artists table
echo '<h2>4️⃣ Artists Table</h2>';
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'artists'");
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM artists");
        $artist_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo '<div class="success">✅ Artists table exists (' . $artist_count . ' artists)</div>';
    } else {
        echo '<div class="error">❌ Artists table does NOT exist!</div>';
    }
} catch (Exception $e) {
    echo '<div class="error">❌ Error checking artists table: ' . $e->getMessage() . '</div>';
}

// Test 5: Check albums table
echo '<h2>5️⃣ Albums Table</h2>';
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'albums'");
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM albums");
        $album_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo '<div class="success">✅ Albums table exists (' . $album_count . ' albums)</div>';
    } else {
        echo '<div class="error">❌ Albums table does NOT exist!</div>';
    }
} catch (Exception $e) {
    echo '<div class="error">❌ Error checking albums table: ' . $e->getMessage() . '</div>';
}

// Test 6: Show sample songs (if any)
echo '<h2>6️⃣ Sample Songs from Database</h2>';
try {
    $stmt = $conn->query("
        SELECT s.id, s.title, s.plays, s.downloads, s.file_path, a.name as artist_name
        FROM songs s
        LEFT JOIN artists a ON s.artist_id = a.id
        LIMIT 5
    ");
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($songs) > 0) {
        echo '<table>';
        echo '<tr><th>ID</th><th>Title</th><th>Artist</th><th>Plays</th><th>Downloads</th><th>File Path</th></tr>';
        foreach ($songs as $song) {
            echo '<tr>';
            echo '<td>' . $song['id'] . '</td>';
            echo '<td>' . htmlspecialchars($song['title']) . '</td>';
            echo '<td>' . htmlspecialchars($song['artist_name'] ?? 'Unknown') . '</td>';
            echo '<td>' . $song['plays'] . '</td>';
            echo '<td>' . $song['downloads'] . '</td>';
            echo '<td><small>' . htmlspecialchars($song['file_path']) . '</small></td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="warning">⚠️ No songs found in database</div>';
    }
} catch (Exception $e) {
    echo '<div class="error">❌ Error fetching songs: ' . $e->getMessage() . '</div>';
}

// Test 7: Check JSON file
echo '<h2>7️⃣ JSON File Status</h2>';
$jsonFile = '../data/songs.json';
if (file_exists($jsonFile)) {
    $json_data = json_decode(file_get_contents($jsonFile), true);
    $json_count = count($json_data ?? []);
    echo '<div class="info">📋 JSON file exists with ' . $json_count . ' songs</div>';
    
    if ($json_count > 0) {
        echo '<h3>Sample from JSON:</h3>';
        echo '<table>';
        echo '<tr><th>Title</th><th>Artist</th><th>Plays</th><th>Downloads</th><th>Audio File</th></tr>';
        foreach (array_slice($json_data, 0, 3) as $song) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($song['title'] ?? 'Unknown') . '</td>';
            echo '<td>' . htmlspecialchars($song['artist'] ?? 'Unknown') . '</td>';
            echo '<td>' . ($song['plays'] ?? 0) . '</td>';
            echo '<td>' . ($song['downloads'] ?? 0) . '</td>';
            echo '<td><small>' . htmlspecialchars($song['audio_file'] ?? 'N/A') . '</small></td>';
            echo '</tr>';
        }
        echo '</table>';
    }
} else {
    echo '<div class="warning">⚠️ No JSON file found</div>';
}

// Test 8: Run the exact same query as admin/songs.php
echo '<h2>8️⃣ Testing Admin Songs Query</h2>';
try {
    $search = '';
    $status_filter = '';
    $featured_filter = '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(s.title LIKE ? OR a.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "s.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($featured_filter)) {
        $where_conditions[] = "s.is_featured = ?";
        $params[] = $featured_filter === 'yes' ? 1 : 0;
    }
    
    $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "
        SELECT s.*, a.name as artist_name
        FROM songs s
        LEFT JOIN artists a ON s.artist_id = a.id
        $where_sql
        ORDER BY s.upload_date DESC
        LIMIT 20
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<div class="info">Query returned: ' . count($songs) . ' songs</div>';
    echo '<pre>' . $sql . '</pre>';
    
    if (count($songs) > 0) {
        echo '<div class="success">✅ Query works! Songs should appear in admin panel.</div>';
    } else {
        echo '<div class="warning">⚠️ Query returned 0 songs - database is empty</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="error">❌ Query error: ' . $e->getMessage() . '</div>';
}

// Summary and Actions
echo '<h2>📊 Summary & Next Steps</h2>';

$db_songs = 0;
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM songs");
    $db_songs = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (Exception $e) {
    // ignore
}

if ($db_songs == 0 && file_exists($jsonFile) && $json_count > 0) {
    echo '<div class="warning">';
    echo '<h3>⚠️ ACTION REQUIRED: Run Migration</h3>';
    echo '<p>Your songs are in the JSON file but NOT in the database.</p>';
    echo '<p><strong>You have ' . $json_count . ' songs ready to migrate!</strong></p>';
    echo '<a href="setup-database.php" class="btn btn-warning">Step 1: Setup Database</a>';
    echo '<a href="migrate-songs.php" class="btn btn-success">Step 2: Run Migration</a>';
    echo '</div>';
} elseif ($db_songs > 0) {
    echo '<div class="success">';
    echo '<h3>✅ All Good!</h3>';
    echo '<p>You have ' . $db_songs . ' songs in the database.</p>';
    echo '<a href="songs.php" class="btn btn-success">View Songs in Admin Panel</a>';
    echo '</div>';
} else {
    echo '<div class="info">';
    echo '<h3>ℹ️ No Songs Found</h3>';
    echo '<p>Upload some songs to get started!</p>';
    echo '<a href="../upload.php" class="btn">Upload Songs</a>';
    echo '</div>';
}

echo '<div style="margin-top: 30px;">';
echo '<a href="index.php" class="btn">← Back to Dashboard</a>';
echo '</div>';
?>

</body>
</html>

