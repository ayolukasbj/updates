<?php
// admin/debug-dashboard.php
// Diagnostic tool for dashboard errors

require_once 'auth-check.php';
require_once '../config/config.php';

$page_title = 'Debug Dashboard';

$errors = [];
$info = [];

// Test 1: Check config loading
try {
    if (!defined('SITE_NAME')) {
        $errors[] = 'SITE_NAME not defined';
    } else {
        $info[] = 'Config loaded: SITE_NAME = ' . SITE_NAME;
    }
} catch (Exception $e) {
    $errors[] = 'Config error: ' . $e->getMessage();
}

// Test 2: Check database
try {
    require_once '../config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        $errors[] = 'Database connection failed';
    } else {
        $info[] = 'Database connection: OK';
    }
} catch (Exception $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}

// Test 3: Check functions
$functions_to_check = ['is_logged_in', 'get_user_id', 'redirect', 'getUser'];
foreach ($functions_to_check as $func) {
    if (function_exists($func)) {
        $info[] = "Function exists: $func()";
    } else {
        $errors[] = "Function missing: $func()";
    }
}

// Test 4: Check session
if (isset($_SESSION['user_id'])) {
    $info[] = 'Session user_id: ' . $_SESSION['user_id'];
} else {
    $errors[] = 'No user_id in session';
}

// Test 5: Check dashboard.php file
$dashboard_path = '../dashboard.php';
if (file_exists($dashboard_path)) {
    $info[] = 'dashboard.php exists';
    
    // Check file permissions
    if (is_readable($dashboard_path)) {
        $info[] = 'dashboard.php is readable';
    } else {
        $errors[] = 'dashboard.php is not readable';
    }
} else {
    $errors[] = 'dashboard.php not found at: ' . $dashboard_path;
}

// Test 6: Try to include dashboard.php (first 50 lines)
try {
    $dashboard_content = file_get_contents($dashboard_path);
    if ($dashboard_content) {
        $info[] = 'dashboard.php file size: ' . strlen($dashboard_content) . ' bytes';
        
        // Check for syntax errors
        $syntax_check = shell_exec('php -l ' . escapeshellarg($dashboard_path) . ' 2>&1');
        if (strpos($syntax_check, 'No syntax errors') !== false) {
            $info[] = 'dashboard.php syntax: OK';
        } else {
            $errors[] = 'dashboard.php syntax error: ' . $syntax_check;
        }
    }
} catch (Exception $e) {
    $errors[] = 'Error reading dashboard.php: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Debug Dashboard</h1>
    <p>Diagnostic tool for dashboard errors</p>
</div>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Diagnostic Results</h2>
    </div>
    <div class="card-body">
        <?php if (!empty($info)): ?>
        <div style="padding: 15px; background: #d1e7dd; border-radius: 6px; margin-bottom: 15px;">
            <h3 style="color: #0f5132; margin: 0 0 10px 0;">✓ Information:</h3>
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($info as $msg): ?>
                <li><?php echo htmlspecialchars($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
        <div style="padding: 15px; background: #f8d7da; border-radius: 6px; margin-bottom: 15px;">
            <h3 style="color: #842029; margin: 0 0 10px 0;">✗ Errors:</h3>
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $msg): ?>
                <li><?php echo htmlspecialchars($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Test Dashboard</h2>
    </div>
    <div class="card-body">
        <a href="../dashboard.php" target="_blank" class="btn btn-primary">
            <i class="fas fa-external-link-alt"></i> Test Dashboard
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

