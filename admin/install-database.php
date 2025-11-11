<?php
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Get action
$action = $_GET['action'] ?? 'check';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Installation</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 30px auto; padding: 20px; background: #f5f5f5; }
        .success { background: #d4edda; color: #155724; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #17a2b8; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #ffc107; }
        h1 { color: #333; background: white; padding: 20px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #555; margin-top: 30px; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .btn { background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; font-size: 16px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-large { padding: 20px 40px; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; background: white; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        tr:nth-child(even) { background: #f8f9fa; }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-missing { color: #dc3545; font-weight: bold; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>

<h1>🛠️ Database Installation & Setup Tool</h1>

<?php
if ($action === 'install') {
    echo '<h2>📦 Installing Database Schema...</h2>';
    
    $schemaFile = '../database/schema.sql';
    
    if (!file_exists($schemaFile)) {
        echo '<div class="error">❌ Schema file not found: ' . $schemaFile . '</div>';
        echo '<a href="install-database.php" class="btn">← Back</a>';
        exit;
    }
    
    try {
        // Read SQL file
        $sql = file_get_contents($schemaFile);
        
        // Split into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) && 
                       strpos($stmt, '--') !== 0 && 
                       strpos($stmt, '/*') !== 0;
            }
        );
        
        $executed = 0;
        $errors = 0;
        
        foreach ($statements as $statement) {
            if (empty(trim($statement))) continue;
            
            try {
                $conn->exec($statement);
                $executed++;
            } catch (PDOException $e) {
                // Ignore "table already exists" errors
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo '<div class="warning">⚠️ ' . htmlspecialchars($e->getMessage()) . '</div>';
                    $errors++;
                }
            }
        }
        
        echo '<div class="success">✅ Executed ' . $executed . ' SQL statements</div>';
        
        if ($errors > 0) {
            echo '<div class="warning">⚠️ ' . $errors . ' non-critical errors (probably tables that already existed)</div>';
        }
        
        // Now check what we have
        echo '<h2>📊 Database Status After Installation</h2>';
        
        $tables = ['users', 'artists', 'albums', 'songs', 'genres', 'playlists', 'news'];
        echo '<table>';
        echo '<tr><th>Table</th><th>Status</th><th>Records</th></tr>';
        
        foreach ($tables as $table) {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                try {
                    $stmt = $conn->query("SELECT COUNT(*) as count FROM $table");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    echo '<tr>';
                    echo '<td><strong>' . $table . '</strong></td>';
                    echo '<td class="status-ok">✅ Exists</td>';
                    echo '<td>' . number_format($count) . '</td>';
                    echo '</tr>';
                } catch (Exception $e) {
                    echo '<tr>';
                    echo '<td><strong>' . $table . '</strong></td>';
                    echo '<td class="status-ok">✅ Exists</td>';
                    echo '<td>Error reading</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr>';
                echo '<td><strong>' . $table . '</strong></td>';
                echo '<td class="status-missing">❌ Missing</td>';
                echo '<td>-</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
        
        echo '<div class="success">';
        echo '<h3>✅ Installation Complete!</h3>';
        echo '<p>Your database has been set up successfully.</p>';
        echo '</div>';
        
        // Check if we need to migrate songs
        $jsonFile = '../data/songs.json';
        if (file_exists($jsonFile)) {
            $json_data = json_decode(file_get_contents($jsonFile), true);
            $json_count = count($json_data ?? []);
            
            if ($json_count > 0) {
                $stmt = $conn->query("SELECT COUNT(*) as count FROM songs");
                $db_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($db_count == 0) {
                    echo '<div class="warning">';
                    echo '<h3>📋 Songs Ready to Migrate</h3>';
                    echo '<p>Found <strong>' . $json_count . ' songs</strong> in JSON file.</p>';
                    echo '<a href="migrate-songs.php" class="btn btn-success btn-large">→ Migrate Songs Now</a>';
                    echo '</div>';
                }
            }
        }
        
        echo '<div style="margin-top: 30px;">';
        echo '<a href="index.php" class="btn btn-success">← Go to Dashboard</a>';
        echo '<a href="songs.php" class="btn">View Song Management</a>';
        echo '<a href="news.php" class="btn">View News Management</a>';
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<div class="error">❌ Installation failed: ' . $e->getMessage() . '</div>';
        echo '<a href="install-database.php" class="btn">← Back</a>';
    }
    
} else {
    // Check mode
    echo '<h2>🔍 Checking Database Status...</h2>';
    
    $tables = [
        'users' => 'User accounts',
        'artists' => 'Artist profiles',
        'albums' => 'Music albums',
        'songs' => 'Song library',
        'genres' => 'Music genres',
        'playlists' => 'User playlists',
        'news' => 'News articles'
    ];
    
    echo '<table>';
    echo '<tr><th>Table</th><th>Description</th><th>Status</th><th>Records</th></tr>';
    
    $missing = [];
    $existing = [];
    
    foreach ($tables as $table => $description) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $existing[] = $table;
            try {
                $stmt = $conn->query("SELECT COUNT(*) as count FROM $table");
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo '<tr>';
                echo '<td><strong>' . $table . '</strong></td>';
                echo '<td>' . $description . '</td>';
                echo '<td class="status-ok">✅ Exists</td>';
                echo '<td>' . number_format($count) . '</td>';
                echo '</tr>';
            } catch (Exception $e) {
                echo '<tr>';
                echo '<td><strong>' . $table . '</strong></td>';
                echo '<td>' . $description . '</td>';
                echo '<td class="status-ok">✅ Exists</td>';
                echo '<td>Error</td>';
                echo '</tr>';
            }
        } else {
            $missing[] = $table;
            echo '<tr>';
            echo '<td><strong>' . $table . '</strong></td>';
            echo '<td>' . $description . '</td>';
            echo '<td class="status-missing">❌ Missing</td>';
            echo '<td>-</td>';
            echo '</tr>';
        }
    }
    echo '</table>';
    
    if (count($missing) > 0) {
        echo '<div class="error">';
        echo '<h3>❌ Missing Tables</h3>';
        echo '<p>The following tables are missing:</p>';
        echo '<ul>';
        foreach ($missing as $table) {
            echo '<li><strong>' . $table . '</strong></li>';
        }
        echo '</ul>';
        echo '<p>Click below to automatically create all required tables:</p>';
        echo '<a href="install-database.php?action=install" class="btn btn-danger btn-large">🚀 Install Database Schema</a>';
        echo '</div>';
    } else {
        echo '<div class="success">';
        echo '<h3>✅ All Tables Exist</h3>';
        echo '<p>Your database is properly set up!</p>';
        echo '</div>';
        
        // Check for songs in JSON
        $jsonFile = '../data/songs.json';
        if (file_exists($jsonFile)) {
            $json_data = json_decode(file_get_contents($jsonFile), true);
            $json_count = count($json_data ?? []);
            
            if ($json_count > 0) {
                $stmt = $conn->query("SELECT COUNT(*) as count FROM songs");
                $db_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($db_count == 0) {
                    echo '<div class="warning">';
                    echo '<h3>📋 Songs Ready to Migrate</h3>';
                    echo '<p>Found <strong>' . $json_count . ' songs</strong> in JSON file, but <strong>0 songs</strong> in database.</p>';
                    echo '<a href="migrate-songs.php" class="btn btn-success btn-large">→ Migrate Songs to Database</a>';
                    echo '</div>';
                } elseif ($db_count < $json_count) {
                    echo '<div class="info">';
                    echo '<h3>ℹ️ Partial Migration</h3>';
                    echo '<p>Database has <strong>' . $db_count . ' songs</strong>, JSON has <strong>' . $json_count . ' songs</strong>.</p>';
                    echo '<a href="migrate-songs.php" class="btn btn-warning">→ Complete Migration</a>';
                    echo '</div>';
                }
            }
        }
    }
    
    echo '<div style="margin-top: 30px;">';
    echo '<a href="index.php" class="btn">← Back to Dashboard</a>';
    if (count($missing) == 0) {
        echo '<a href="songs.php" class="btn btn-success">View Song Management</a>';
    }
    echo '</div>';
}
?>

</body>
</html>

