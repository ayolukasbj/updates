<?php
/**
 * Standalone File Deletion Script
 * 
 * This script can be run manually to delete files listed in deletions.txt
 * Usage: Access via browser or run via CLI: php admin/delete-files.php
 * 
 * SECURITY: Only run this if you're sure deletions.txt is correct!
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once 'auth-check.php';

if (!isSuperAdmin()) {
    die('Unauthorized access');
}

$root_path = realpath(__DIR__ . '/../');
$deletions_file = $root_path . '/updates/deletions.txt';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Delete Files from Live Server</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px; }
        .btn:hover { background: #c82333; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗑️ Delete Files from Live Server</h1>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
            echo '<div class="info">Starting file deletion process...</div>';
            
            if (!file_exists($deletions_file)) {
                echo '<div class="error">❌ deletions.txt not found at: ' . htmlspecialchars($deletions_file) . '</div>';
                echo '<p>Please ensure deletions.txt exists in the updates folder.</p>';
                exit;
            }
            
            echo '<div class="info">📄 Reading deletions.txt from: ' . htmlspecialchars($deletions_file) . '</div>';
            
            $deleted = 0;
            $not_found = 0;
            $errors = [];
            $deleted_files = [];
            
            $lines = file($deletions_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            echo '<div class="info">📋 Processing ' . count($lines) . ' entries from deletions.txt...</div>';
            echo '<pre>';
            
            foreach ($lines as $line_num => $line) {
                // Skip comments and empty lines
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }
                
                // Remove leading/trailing slashes and normalize path
                $file_path = trim($line, '/\\');
                $target_path = $root_path . '/' . $file_path;
                
                // Normalize path separators
                $target_path = str_replace('\\', '/', $target_path);
                
                // Security check: prevent deleting files outside root
                $real_target = realpath($target_path);
                $real_root = realpath($root_path);
                
                if (!$real_target || strpos($real_target, $real_root) !== 0) {
                    $error_msg = "Security: Cannot delete file outside root: $file_path";
                    $errors[] = $error_msg;
                    echo "❌ $error_msg\n";
                    continue;
                }
                
                // Check if file exists
                if (!file_exists($target_path)) {
                    $not_found++;
                    echo "⚠️  Not found (already deleted?): $file_path\n";
                    continue;
                }
                
                // Delete the file
                if (@unlink($target_path)) {
                    $deleted++;
                    $deleted_files[] = $file_path;
                    echo "✅ Deleted: $file_path\n";
                } else {
                    $error_msg = "Failed to delete: $file_path";
                    $errors[] = $error_msg;
                    echo "❌ $error_msg\n";
                }
            }
            
            echo '</pre>';
            
            echo '<div class="success">';
            echo '<h2>✅ Deletion Complete!</h2>';
            echo '<p><strong>Files deleted:</strong> ' . $deleted . '</p>';
            echo '<p><strong>Files not found:</strong> ' . $not_found . '</p>';
            echo '<p><strong>Errors:</strong> ' . count($errors) . '</p>';
            echo '</div>';
            
            if (!empty($errors)) {
                echo '<div class="error">';
                echo '<h3>Errors:</h3>';
                echo '<ul>';
                foreach ($errors as $error) {
                    echo '<li>' . htmlspecialchars($error) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
            
            if ($deleted > 0) {
                echo '<div class="info">';
                echo '<h3>Deleted Files:</h3>';
                echo '<ul>';
                foreach ($deleted_files as $file) {
                    echo '<li>' . htmlspecialchars($file) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
            
        } else {
            // Show confirmation form
            if (!file_exists($deletions_file)) {
                echo '<div class="error">❌ deletions.txt not found at: ' . htmlspecialchars($deletions_file) . '</div>';
                echo '<p>Please ensure deletions.txt exists in the updates folder with the list of files to delete.</p>';
            } else {
                $lines = file($deletions_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $file_count = 0;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line) && strpos($line, '#') !== 0) {
                        $file_count++;
                    }
                }
                
                echo '<div class="warning">';
                echo '<h2>⚠️ Warning</h2>';
                echo '<p>This will permanently delete <strong>' . $file_count . ' files</strong> listed in deletions.txt from the live server.</p>';
                echo '<p><strong>File location:</strong> ' . htmlspecialchars($deletions_file) . '</p>';
                echo '<p>This action cannot be undone. Make sure you have a backup!</p>';
                echo '</div>';
                
                echo '<form method="POST">';
                echo '<input type="hidden" name="confirm" value="yes">';
                echo '<button type="submit" class="btn">🗑️ Delete Files Now</button>';
                echo '<a href="dashboard.php" class="btn btn-success" style="margin-left: 10px;">Cancel</a>';
                echo '</form>';
                
                echo '<div class="info" style="margin-top: 20px;">';
                echo '<h3>Files to be deleted:</h3>';
                echo '<pre style="max-height: 300px; overflow-y: auto;">';
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line) && strpos($line, '#') !== 0) {
                        echo htmlspecialchars($line) . "\n";
                    }
                }
                echo '</pre>';
                echo '</div>';
            }
        }
        ?>
    </div>
</body>
</html>

