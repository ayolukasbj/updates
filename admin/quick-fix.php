<?php
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Auto-run setup if requested
$auto_fix = $_GET['auto'] ?? '';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Quick Fix - Songs Not Showing</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .content { padding: 30px; }
        .step {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .step h2 { color: #667eea; margin-bottom: 10px; font-size: 24px; }
        .success { background: #d4edda; border-left-color: #28a745; }
        .success h2 { color: #28a745; }
        .error { background: #f8d7da; border-left-color: #dc3545; }
        .error h2 { color: #dc3545; }
        .warning { background: #fff3cd; border-left-color: #ffc107; }
        .warning h2 { color: #856404; }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 15px 30px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            margin: 10px 5px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover { background: #5568d3; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,0.3); }
        .btn-large { padding: 20px 40px; font-size: 20px; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .status-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            text-align: center;
        }
        .status-card.ok { border-color: #28a745; background: #f0f9f4; }
        .status-card.missing { border-color: #dc3545; background: #fef5f5; }
        .status-number { font-size: 36px; font-weight: bold; margin: 10px 0; }
        .status-label { color: #6c757d; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #f8f9fa; font-weight: 600; }
        .check { color: #28a745; font-weight: bold; }
        .cross { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🔧 Quick Fix Tool</h1>
        <p>Solving: Songs Not Showing in Admin Dashboard</p>
    </div>
    
    <div class="content">
        <?php
        // Check database status
        $tables_needed = ['songs', 'artists', 'albums', 'news', 'genres'];
        $missing_tables = [];
        $existing_tables = [];
        
        foreach ($tables_needed as $table) {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $existing_tables[] = $table;
            } else {
                $missing_tables[] = $table;
            }
        }
        
        // Count songs in DB
        $db_songs = 0;
        if (in_array('songs', $existing_tables)) {
            $stmt = $conn->query("SELECT COUNT(*) as count FROM songs");
            $db_songs = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        }
        
        // Count songs in JSON
        $json_songs = 0;
        $jsonFile = '../data/songs.json';
        if (file_exists($jsonFile)) {
            $data = json_decode(file_get_contents($jsonFile), true);
            $json_songs = count($data ?? []);
        }
        
        // Determine what needs to be done
        $needs_schema = count($missing_tables) > 0;
        $needs_migration = ($json_songs > 0 && $db_songs == 0);
        
        if ($auto_fix === 'schema' && $needs_schema) {
            // Auto-install schema
            echo '<div class="step success">';
            echo '<h2>⚡ Auto-Installing Database...</h2>';
            
            $schemaFile = '../database/schema.sql';
            if (file_exists($schemaFile)) {
                try {
                    $sql = file_get_contents($schemaFile);
                    $statements = array_filter(
                        array_map('trim', explode(';', $sql)),
                        function($stmt) {
                            return !empty($stmt) && strpos($stmt, '--') !== 0;
                        }
                    );
                    
                    foreach ($statements as $statement) {
                        if (empty(trim($statement))) continue;
                        try {
                            $conn->exec($statement);
                        } catch (PDOException $e) {
                            // Ignore table exists errors
                        }
                    }
                    
                    echo '<p>✅ Database schema installed successfully!</p>';
                    echo '<meta http-equiv="refresh" content="2;url=quick-fix.php">';
                    
                } catch (Exception $e) {
                    echo '<p>❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
            }
            echo '</div>';
            
        } elseif ($auto_fix === 'migrate' && $needs_migration) {
            // Auto-migrate songs
            echo '<div class="step success">';
            echo '<h2>⚡ Auto-Migrating Songs...</h2>';
            echo '<p>Please wait...</p>';
            echo '<meta http-equiv="refresh" content="1;url=migrate-songs.php">';
            echo '</div>';
            
        } else {
            // Show status
            echo '<div class="step">';
            echo '<h2>📊 Current Status</h2>';
            echo '<div class="status-grid">';
            
            // Tables status
            echo '<div class="status-card ' . (count($missing_tables) == 0 ? 'ok' : 'missing') . '">';
            echo '<div class="status-number">' . count($existing_tables) . '/' . count($tables_needed) . '</div>';
            echo '<div class="status-label">Database Tables</div>';
            echo '</div>';
            
            // Songs in DB
            echo '<div class="status-card ' . ($db_songs > 0 ? 'ok' : 'missing') . '">';
            echo '<div class="status-number">' . $db_songs . '</div>';
            echo '<div class="status-label">Songs in Database</div>';
            echo '</div>';
            
            // Songs in JSON
            echo '<div class="status-card ' . ($json_songs > 0 ? 'ok' : 'missing') . '">';
            echo '<div class="status-number">' . $json_songs . '</div>';
            echo '<div class="status-label">Songs in JSON File</div>';
            echo '</div>';
            
            echo '</div>';
            echo '</div>';
            
            // Show detailed table status
            echo '<div class="step">';
            echo '<h2>📋 Database Tables</h2>';
            echo '<table>';
            echo '<tr><th>Table</th><th>Status</th></tr>';
            foreach ($tables_needed as $table) {
                $exists = in_array($table, $existing_tables);
                echo '<tr>';
                echo '<td>' . $table . '</td>';
                echo '<td>' . ($exists ? '<span class="check">✅ Exists</span>' : '<span class="cross">❌ Missing</span>') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            echo '</div>';
            
            // Show what needs to be done
            if ($needs_schema) {
                echo '<div class="step error">';
                echo '<h2>❌ Problem Found: Missing Database Tables</h2>';
                echo '<p>Your database is missing required tables. Songs, news, and artists cannot be managed without them.</p>';
                echo '<p><strong>Missing tables:</strong> ' . implode(', ', $missing_tables) . '</p>';
                echo '<h3>Solution:</h3>';
                echo '<a href="quick-fix.php?auto=schema" class="btn btn-danger btn-large">🚀 Fix This Now (Auto-Install)</a>';
                echo '</div>';
                
            } elseif ($needs_migration) {
                echo '<div class="step warning">';
                echo '<h2>⚠️ Songs Need Migration</h2>';
                echo '<p>You have <strong>' . $json_songs . ' songs</strong> in your JSON file, but <strong>0 songs</strong> in the database.</p>';
                echo '<p>The admin panel shows database songs, so you need to migrate them.</p>';
                echo '<h3>Solution:</h3>';
                echo '<a href="quick-fix.php?auto=migrate" class="btn btn-success btn-large">📦 Migrate Songs Now</a>';
                echo '</div>';
                
            } else {
                echo '<div class="step success">';
                echo '<h2>✅ Everything Looks Good!</h2>';
                echo '<p>Database tables exist: <strong>Yes</strong></p>';
                echo '<p>Songs in database: <strong>' . $db_songs . '</strong></p>';
                echo '<p>If you still don\'t see songs, try these:</p>';
                echo '<ul style="margin-left: 30px; margin-top: 10px;">';
                echo '<li>Clear your browser cache (Ctrl+Shift+Delete)</li>';
                echo '<li>Try a different browser or incognito mode</li>';
                echo '<li>Check <a href="songs.php">Song Management</a> directly</li>';
                echo '</ul>';
                echo '</div>';
            }
            
            // Action buttons
            echo '<div style="text-align: center; margin-top: 30px; padding-top: 30px; border-top: 2px solid #e9ecef;">';
            echo '<a href="index.php" class="btn">← Back to Dashboard</a>';
            if (!$needs_schema) {
                echo '<a href="songs.php" class="btn btn-success">View Song Management</a>';
                echo '<a href="news.php" class="btn">View News Management</a>';
            }
            echo '</div>';
        }
        ?>
    </div>
</div>

</body>
</html>

