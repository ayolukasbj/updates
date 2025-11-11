<?php
/**
 * File Sync Utility
 * Automatically copies updated files to the updates folder
 */

// Updates folder path - use relative path from root
// This will work on both Windows and Linux servers
$updates_folder_path = realpath(__DIR__ . '/../updates');
if (!$updates_folder_path) {
    // If updates folder doesn't exist, try to create it
    $updates_folder_path = __DIR__ . '/../updates';
    if (!is_dir($updates_folder_path)) {
        @mkdir($updates_folder_path, 0755, true);
        $updates_folder_path = realpath($updates_folder_path) ?: $updates_folder_path;
    }
}
define('UPDATES_FOLDER', $updates_folder_path);

// Directories to exclude from syncing
$exclude_dirs = [
    'config',
    'database',
    'uploads',
    'logs',
    'temp',
    'backups',
    '.git',
    'node_modules',
    'updates',
    'vendor',
    'cache'
];

// File patterns to exclude
$exclude_files = [
    '.sql',
    '.env',
    '.log',
    '.cache',
    'config.php',
    'database.php',
    '.htaccess',
    '.gitignore',
    '.gitattributes'
];

/**
 * Copy updated file to updates folder
 * @param string $file_path Relative or absolute path to the file
 * @return bool|string True on success, error message on failure
 */
function syncFileToUpdates($file_path) {
    global $exclude_dirs, $exclude_files;
    
    // Check if UPDATES_FOLDER is defined and accessible
    if (!defined('UPDATES_FOLDER') || empty(UPDATES_FOLDER)) {
        return 'Updates folder not configured';
    }
    
    // Get root path
    $root_path = realpath(__DIR__ . '/../');
    if (!$root_path) {
        return 'Cannot determine root path';
    }
    
    // Normalize file path
    if (!file_exists($file_path)) {
        // Try relative to root
        $file_path = $root_path . '/' . ltrim($file_path, '/\\');
    }
    
    $file_path = realpath($file_path);
    if (!$file_path || !file_exists($file_path)) {
        return 'File does not exist: ' . $file_path;
    }
    
    // Get relative path from root
    $relative_path = str_replace('\\', '/', substr($file_path, strlen($root_path) + 1));
    
    // Check if file should be excluded
    $path_parts = explode('/', $relative_path);
    foreach ($exclude_dirs as $excluded) {
        if (in_array($excluded, $path_parts)) {
            return 'File in excluded directory: ' . $excluded;
        }
    }
    
    // Check file extension/name exclusions
    $file_name = basename($relative_path);
    foreach ($exclude_files as $excluded) {
        if (strpos($file_name, $excluded) !== false || strpos($relative_path, $excluded) !== false) {
            return 'File matches exclusion pattern: ' . $excluded;
        }
    }
    
    // Create updates folder if it doesn't exist
    $updates_folder = UPDATES_FOLDER;
    if (!is_dir($updates_folder)) {
        if (!@mkdir($updates_folder, 0755, true)) {
            // If folder creation fails, return error but don't throw exception
            return 'Cannot create updates folder: ' . $updates_folder . ' (check permissions)';
        }
    }
    
    // Check if folder is writable
    if (!is_writable($updates_folder)) {
        return 'Updates folder is not writable: ' . $updates_folder;
    }
    
    // Create destination path maintaining directory structure
    $dest_path = $updates_folder . '/' . $relative_path;
    $dest_dir = dirname($dest_path);
    
    // Create destination directory if it doesn't exist
    if (!is_dir($dest_dir)) {
        if (!mkdir($dest_dir, 0755, true)) {
            return 'Cannot create destination directory: ' . $dest_dir;
        }
    }
    
    // Copy file
    if (!copy($file_path, $dest_path)) {
        return 'Failed to copy file to: ' . $dest_path;
    }
    
    // Log success
    error_log('[FILE_SYNC] Copied: ' . $relative_path . ' to updates folder');
    
    return true;
}

/**
 * Sync multiple files at once
 * @param array $file_paths Array of file paths
 * @return array Results array with success/failure for each file
 */
function syncFilesToUpdates($file_paths) {
    $results = [];
    foreach ($file_paths as $file_path) {
        $result = syncFileToUpdates($file_path);
        $results[$file_path] = $result === true ? 'success' : $result;
    }
    return $results;
}

/**
 * Sync entire directory to updates folder (for bulk operations)
 * @param string $dir_path Directory path relative to root (use '.' for root)
 * @return array Results with counts
 */
function syncDirectoryToUpdates($dir_path = '.') {
    global $exclude_dirs, $exclude_files;
    
    $root_path = realpath(__DIR__ . '/../');
    if (!$root_path) {
        return ['success' => false, 'error' => 'Cannot determine root path'];
    }
    
    // If dir_path is '.', sync from root
    if ($dir_path === '.') {
        $full_dir_path = $root_path;
    } else {
        $full_dir_path = $root_path . '/' . ltrim($dir_path, '/\\');
    }
    
    if (!is_dir($full_dir_path)) {
        return ['success' => false, 'error' => 'Directory does not exist: ' . $full_dir_path];
    }
    
    $copied = 0;
    $skipped = 0;
    $errors = [];
    $start_time = time();
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($full_dir_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($iterator as $file) {
        $file_path = $file->getRealPath();
        $result = syncFileToUpdates($file_path);
        
        if ($result === true) {
            $copied++;
        } else {
            $skipped++;
            // Only log non-exclusion errors
            if (strpos($result, 'excluded') === false && strpos($result, 'exclusion') === false) {
                $errors[] = $result;
            }
        }
        
        // Note: Output flushing removed to prevent breaking JSON API responses
        // If timeout is an issue, increase max_execution_time in PHP config
    }
    
    $duration = time() - $start_time;
    
    return [
        'success' => true,
        'copied' => $copied,
        'skipped' => $skipped,
        'errors' => array_slice($errors, 0, 10), // Limit errors to first 10
        'duration_seconds' => $duration
    ];
}

