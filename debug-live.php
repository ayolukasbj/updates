<?php
/**
 * Live Server Debug Script
 * Use this to diagnose issues on live server
 * Access: https://tesotalents.com/debug-live.php
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start output buffering
ob_start();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Live Server Debug</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .success { background: #d4edda; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
        h2 { margin-top: 30px; }
    </style>
</head>
<body>
    <h1>🔍 Live Server Debug Report</h1>
    <p><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    
    <?php
    $errors = [];
    $warnings = [];
    $success = [];
    
    // Test 1: PHP Version
    echo "<h2>1. PHP Version</h2>";
    echo "<div class='info'>PHP Version: " . PHP_VERSION . "</div>";
    
    // Test 2: Session
    echo "<h2>2. Session</h2>";
    try {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        echo "<div class='success'>✅ Session started successfully</div>";
        $success[] = "Session";
    } catch (Exception $e) {
        echo "<div class='error'>❌ Session error: " . $e->getMessage() . "</div>";
        $errors[] = "Session: " . $e->getMessage();
    }
    
    // Test 3: Config File
    echo "<h2>3. Config File</h2>";
    try {
        if (!file_exists('config/config.php')) {
            throw new Exception('config/config.php not found');
        }
        require_once 'config/config.php';
        echo "<div class='success'>✅ Config file loaded</div>";
        echo "<div class='info'>SITE_NAME: " . (defined('SITE_NAME') ? SITE_NAME : 'NOT DEFINED') . "</div>";
        echo "<div class='info'>SITE_URL: " . (defined('SITE_URL') ? SITE_URL : 'NOT DEFINED') . "</div>";
        echo "<div class='info'>BASE_PATH: " . (defined('BASE_PATH') ? BASE_PATH : 'NOT DEFINED') . "</div>";
        $success[] = "Config";
    } catch (Throwable $e) {
        echo "<div class='error'>❌ Config error: " . $e->getMessage() . "</div>";
        echo "<div class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</div>";
        $errors[] = "Config: " . $e->getMessage();
    }
    
    // Test 4: Database Config
    echo "<h2>4. Database Config</h2>";
    try {
        if (!file_exists('config/database.php')) {
            throw new Exception('config/database.php not found');
        }
        require_once 'config/database.php';
        echo "<div class='success'>✅ Database config loaded</div>";
        $success[] = "Database Config";
    } catch (Throwable $e) {
        echo "<div class='error'>❌ Database config error: " . $e->getMessage() . "</div>";
        echo "<div class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</div>";
        $errors[] = "Database Config: " . $e->getMessage();
    }
    
    // Test 5: Database Connection
    echo "<h2>5. Database Connection</h2>";
    try {
        if (class_exists('Database')) {
            $db = new Database();
            $conn = $db->getConnection();
            if ($conn) {
                echo "<div class='success'>✅ Database connection successful</div>";
                $success[] = "Database Connection";
                
                // Test query
                $stmt = $conn->query("SELECT 1");
                if ($stmt) {
                    echo "<div class='success'>✅ Database query test successful</div>";
                }
            } else {
                throw new Exception('Connection returned null');
            }
        } else {
            throw new Exception('Database class not found');
        }
    } catch (Throwable $e) {
        echo "<div class='error'>❌ Database connection error: " . $e->getMessage() . "</div>";
        echo "<div class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</div>";
        $errors[] = "Database Connection: " . $e->getMessage();
    }
    
    // Test 6: Required Files
    echo "<h2>6. Required Files</h2>";
    $required_files = [
        'includes/header.php',
        'includes/footer.php',
        'includes/song-storage.php',
        'classes/User.php',
        'classes/Song.php',
        'classes/Artist.php',
        'controllers/AuthController.php',
    ];
    
    foreach ($required_files as $file) {
        if (file_exists($file)) {
            echo "<div class='success'>✅ $file exists</div>";
        } else {
            echo "<div class='error'>❌ $file NOT FOUND</div>";
            $errors[] = "Missing file: $file";
        }
    }
    
    // Test 7: File Permissions
    echo "<h2>7. File Permissions</h2>";
    $writable_dirs = [
        'uploads',
        'uploads/audio',
        'uploads/images',
        'uploads/avatars',
        'uploads/branding',
    ];
    
    foreach ($writable_dirs as $dir) {
        if (file_exists($dir)) {
            if (is_writable($dir)) {
                echo "<div class='success'>✅ $dir is writable</div>";
            } else {
                echo "<div class='warning'>⚠️ $dir is NOT writable</div>";
                $warnings[] = "$dir is not writable";
            }
        } else {
            echo "<div class='warning'>⚠️ $dir does not exist</div>";
            $warnings[] = "$dir does not exist";
        }
    }
    
    // Test 8: Try to load index.php functions
    echo "<h2>8. Test Index.php Functions</h2>";
    try {
        if (file_exists('includes/song-storage.php')) {
            require_once 'includes/song-storage.php';
            echo "<div class='success'>✅ song-storage.php loaded</div>";
            
            // Try to get songs
            if (function_exists('getAllSongs')) {
                try {
                    $songs = getAllSongs();
                    echo "<div class='success'>✅ getAllSongs() returned " . count($songs) . " songs</div>";
                } catch (Exception $e) {
                    echo "<div class='error'>❌ getAllSongs() error: " . $e->getMessage() . "</div>";
                    $errors[] = "getAllSongs(): " . $e->getMessage();
                }
            }
        }
    } catch (Throwable $e) {
        echo "<div class='error'>❌ Error loading functions: " . $e->getMessage() . "</div>";
        $errors[] = "Functions: " . $e->getMessage();
    }
    
    // Test 9: Check for PHP Errors
    echo "<h2>9. PHP Error Check</h2>";
    $error_log = ini_get('error_log');
    if ($error_log) {
        echo "<div class='info'>Error log location: $error_log</div>";
    } else {
        echo "<div class='warning'>⚠️ Error log not configured</div>";
    }
    
    // Test 10: Server Info
    echo "<h2>10. Server Information</h2>";
    echo "<div class='info'>Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</div>";
    echo "<div class='info'>Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</div>";
    echo "<div class='info'>Script Name: " . ($_SERVER['SCRIPT_NAME'] ?? 'Unknown') . "</div>";
    echo "<div class='info'>Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "</div>";
    echo "<div class='info'>HTTP Host: " . ($_SERVER['HTTP_HOST'] ?? 'Unknown') . "</div>";
    
    // Summary
    echo "<h2>📊 Summary</h2>";
    echo "<div class='success'>✅ Successful: " . count($success) . " tests</div>";
    if (count($warnings) > 0) {
        echo "<div class='warning'>⚠️ Warnings: " . count($warnings) . "</div>";
        echo "<pre>" . implode("\n", $warnings) . "</pre>";
    }
    if (count($errors) > 0) {
        echo "<div class='error'>❌ Errors: " . count($errors) . "</div>";
        echo "<pre>" . implode("\n", $errors) . "</pre>";
    } else {
        echo "<div class='success'>✅ No errors found!</div>";
    }
    
    // Show any output that was buffered
    $output = ob_get_clean();
    if (!empty($output)) {
        echo "<h2>11. Output Buffer</h2>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
    ?>
    
    <hr>
    <p><small>Delete this file after debugging for security.</small></p>
</body>
</html>












