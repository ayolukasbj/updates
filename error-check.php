<?php
// Simple error checker
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Error Check</h1>";

// Test 1: Session
echo "<h2>Test 1: Session</h2>";
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
echo "<p>✅ Session OK</p>";

// Test 2: Config
echo "<h2>Test 2: Config</h2>";
try {
    require_once 'config/config.php';
    echo "<p>✅ Config loaded</p>";
} catch (Throwable $e) {
    echo "<p>❌ Config error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
    exit;
}

// Test 3: Database
echo "<h2>Test 3: Database</h2>";
try {
    require_once 'config/database.php';
    echo "<p>✅ Database config loaded</p>";
} catch (Throwable $e) {
    echo "<p>❌ Database config error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
    exit;
}

// Test 4: Song Storage
echo "<h2>Test 4: Song Storage</h2>";
try {
    require_once 'includes/song-storage.php';
    echo "<p>✅ Song storage loaded</p>";
} catch (Throwable $e) {
    echo "<p>❌ Song storage error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
    exit;
}

// Test 5: Upload.php syntax check
echo "<h2>Test 5: Upload.php Syntax Check</h2>";
$syntax_check = shell_exec('php -l upload.php 2>&1');
if (strpos($syntax_check, 'No syntax errors') !== false) {
    echo "<p>✅ Upload.php syntax OK</p>";
} else {
    echo "<p>❌ Upload.php syntax error:</p>";
    echo "<pre>" . htmlspecialchars($syntax_check) . "</pre>";
}

// Test 6: Try to include upload.php (first few lines only)
echo "<h2>Test 6: Include Upload.php (First 100 lines)</h2>";
try {
    // Read first 100 lines
    $lines = file('upload.php');
    $first_100 = implode('', array_slice($lines, 0, 100));
    
    // Create temp file
    $temp_file = tempnam(sys_get_temp_dir(), 'upload_test_');
    file_put_contents($temp_file, $first_100 . "\n?>");
    
    ob_start();
    include $temp_file;
    $output = ob_get_clean();
    unlink($temp_file);
    
    echo "<p>✅ First 100 lines executed OK</p>";
    if (!empty($output)) {
        echo "<p>Output length: " . strlen($output) . " bytes</p>";
    }
} catch (Throwable $e) {
    echo "<p>❌ Error in first 100 lines: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Test 7: Check if upload.php has any parse errors
echo "<h2>Test 7: Parse Upload.php</h2>";
try {
    $tokens = token_get_all(file_get_contents('upload.php'));
    $errors = [];
    $brace_count = 0;
    $paren_count = 0;
    
    foreach ($tokens as $token) {
        if (is_array($token)) {
            if ($token[0] == T_OPEN_CURLY) $brace_count++;
            if ($token[0] == T_CLOSE_CURLY) $brace_count--;
            if ($token[0] == T_OPEN_TAG && isset($token[1]) && strpos($token[1], '<?php') === false) {
                $errors[] = "Unexpected tag at line " . $token[2];
            }
        }
    }
    
    if ($brace_count != 0) {
        echo "<p>⚠️ Brace count mismatch: " . $brace_count . "</p>";
    } else {
        echo "<p>✅ Brace count OK</p>";
    }
    
    if (!empty($errors)) {
        echo "<p>⚠️ Warnings:</p><ul>";
        foreach ($errors as $error) {
            echo "<li>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p>❌ Parse check error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='edit-song.php?id=11'>Try Edit Song</a></p>";
?>


