<?php
/**
 * Database Import Script
 * Automatically imports all SQL schema files in the correct order
 */

// Load database configuration
require_once 'config/config.php';
require_once 'config/database.php';

// Set execution time limit for large imports
set_time_limit(300);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Import Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #2196F3;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #bee5eb;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #ffeaa7;
        }
        .btn {
            background: #2196F3;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #1976D2;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Database Import Tool</h1>
        
        <?php
        $db = new Database();
        $conn = $db->getConnection();
        
        if (!$conn) {
            echo '<div class="error">❌ Database connection failed! Please check your database configuration in config/database.php</div>';
            exit;
        }
        
        echo '<div class="success">✅ Database connection successful!</div>';
        echo '<div class="info">📊 Database: ' . (defined('DB_NAME') ? DB_NAME : 'Unknown') . '</div>';
        
        // Schema files in import order
        $schema_files = [
            'database/schema.sql' => 'Main Schema (users, artists, songs, albums, genres, playlists)',
            'database/news-schema.sql' => 'News Schema (news, news_comments, news_views) + Sample Data',
            'database/admin-schema.sql' => 'Admin Schema (admin_logs, role column, enhancements)',
            'database/add-profile-columns.sql' => 'Profile Columns (bio, social media links)'
        ];
        
        $imported = [];
        $errors = [];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
            echo '<h2>Import Results</h2>';
            
            foreach ($schema_files as $file => $description) {
                $file_path = __DIR__ . '/' . $file;
                
                if (!file_exists($file_path)) {
                    $errors[] = "File not found: $file";
                    echo '<div class="error">❌ File not found: <strong>' . htmlspecialchars($file) . '</strong></div>';
                    continue;
                }
                
                echo '<div class="info">📄 Importing: <strong>' . htmlspecialchars($description) . '</strong></div>';
                
                try {
                    // Read SQL file
                    $sql = file_get_contents($file_path);
                    
                    if (empty($sql)) {
                        $errors[] = "Empty file: $file";
                        echo '<div class="warning">⚠️ File is empty: ' . htmlspecialchars($file) . '</div>';
                        continue;
                    }
                    
                    // Remove comments and split by semicolon
                    $sql = preg_replace('/--.*$/m', '', $sql);
                    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
                    
                    // Split into individual statements
                    $statements = array_filter(
                        array_map('trim', explode(';', $sql)),
                        function($stmt) {
                            return !empty($stmt) && strlen($stmt) > 5;
                        }
                    );
                    
                    $executed = 0;
                    $skipped = 0;
                    
                    foreach ($statements as $statement) {
                        if (empty(trim($statement))) {
                            continue;
                        }
                        
                        try {
                            $conn->exec($statement);
                            $executed++;
                        } catch (PDOException $e) {
                            // Skip if table/column already exists
                            if (strpos($e->getMessage(), 'already exists') !== false || 
                                strpos($e->getMessage(), 'Duplicate column') !== false) {
                                $skipped++;
                            } else {
                                // Log other errors but continue
                                error_log("SQL Error in $file: " . $e->getMessage());
                                $skipped++;
                            }
                        }
                    }
                    
                    $imported[] = [
                        'file' => $file,
                        'description' => $description,
                        'executed' => $executed,
                        'skipped' => $skipped
                    ];
                    
                    echo '<div class="success">✅ Imported: ' . htmlspecialchars($description) . ' (Executed: ' . $executed . ', Skipped: ' . $skipped . ')</div>';
                    
                } catch (Exception $e) {
                    $errors[] = "Error importing $file: " . $e->getMessage();
                    echo '<div class="error">❌ Error importing ' . htmlspecialchars($file) . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
            
            // Verify tables
            echo '<h2>📋 Table Verification</h2>';
            $required_tables = ['users', 'artists', 'songs', 'albums', 'genres', 'news', 'admin_logs'];
            $existing_tables = [];
            
            try {
                $stmt = $conn->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                echo '<table>';
                echo '<tr><th>Table Name</th><th>Status</th></tr>';
                
                foreach ($required_tables as $table) {
                    $exists = in_array($table, $tables);
                    $existing_tables[$table] = $exists;
                    
                    if ($exists) {
                        // Get row count
                        try {
                            $count_stmt = $conn->query("SELECT COUNT(*) as count FROM `$table`");
                            $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                            echo '<tr><td><strong>' . htmlspecialchars($table) . '</strong></td><td><span style="color: green;">✅ Exists (' . $count . ' rows)</span></td></tr>';
                        } catch (Exception $e) {
                            echo '<tr><td><strong>' . htmlspecialchars($table) . '</strong></td><td><span style="color: green;">✅ Exists</span></td></tr>';
                        }
                    } else {
                        echo '<tr><td><strong>' . htmlspecialchars($table) . '</strong></td><td><span style="color: red;">❌ Missing</span></td></tr>';
                    }
                }
                
                echo '</table>';
                
                $all_exist = count(array_filter($existing_tables)) === count($required_tables);
                
                if ($all_exist) {
                    echo '<div class="success">🎉 All required tables exist! Your database is ready.</div>';
                } else {
                    echo '<div class="warning">⚠️ Some tables are missing. Please check the errors above.</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="error">❌ Error checking tables: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            ?>
            <div class="info">
                <h3>📋 Import Order</h3>
                <p>This tool will import the following schema files in the correct order:</p>
                <ol>
                    <?php foreach ($schema_files as $file => $description): ?>
                    <li><strong><?php echo htmlspecialchars($description); ?></strong><br>
                        <small style="color: #666;"><?php echo htmlspecialchars($file); ?></small>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            
            <div class="warning">
                <strong>⚠️ Warning:</strong> This will create tables if they don't exist. 
                Existing data will NOT be deleted, but tables will be modified.
            </div>
            
            <form method="POST">
                <button type="submit" name="import" class="btn">🚀 Start Import</button>
                <a href="admin/index.php" class="btn btn-danger">Cancel</a>
            </form>
            <?php
        }
        ?>
        
        <hr style="margin: 30px 0;">
        <p style="color: #666; font-size: 14px;">
            <strong>Note:</strong> After importing, check your error logs if you encounter any issues.
            The import uses <code>CREATE TABLE IF NOT EXISTS</code> and <code>ALTER TABLE ... IF NOT EXISTS</code>
            to avoid conflicts with existing tables.
        </p>
    </div>
</body>
</html>

