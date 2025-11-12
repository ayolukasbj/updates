<?php
// admin/api/install-update.php
// Update Installation API

// Prevent any output before JSON
ob_start();

require_once '../auth-check.php';
require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isSuperAdmin()) {
    ob_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Clear any output that might have been sent
ob_clean();
header('Content-Type: application/json');

// Increase execution time and memory limits for operations
// Set reasonable limits to prevent infinite hangs
set_time_limit(300); // 5 minutes max
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

// Restart output buffering to catch any errors
ob_start();

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

$backup_dir = __DIR__ . '/../../backups/';
$update_dir = __DIR__ . '/../../updates/';
$temp_dir = __DIR__ . '/../../temp/';

// Create directories if they don't exist
foreach ([$backup_dir, $update_dir, $temp_dir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function logMessage($message) {
    error_log('[UPDATE] ' . $message);
}

function createBackup($backup_dir) {
    global $backup_dir;
    
    // Set execution limits for backup
    set_time_limit(300); // 5 minutes max
    ini_set('max_execution_time', 300);
    ini_set('memory_limit', '512M');
    
    $timestamp = date('Y-m-d_H-i-s');
    $backup_path = $backup_dir . 'backup_' . $timestamp . '.zip';
    
    // Files and directories to backup (exclude backups, updates, temp, uploads)
    // More aggressive exclusions to speed up backup
    $exclude_dirs = [
        'backups',
        'updates',
        'temp',
        'uploads',
        'node_modules',
        '.git',
        'vendor',
        'cache',
        'logs',
        'database',
        'backup',
        'assets/vendor', // Exclude vendor assets
        'plugins', // Exclude plugins (can be reinstalled)
        '.vscode',
        '.idea'
    ];
    
    // File extensions to exclude
    $exclude_extensions = ['log', 'tmp', 'cache', 'swp', 'bak'];
    
    // Maximum file size to include (5MB) - skip very large files to speed up
    $max_file_size = 5 * 1024 * 1024; // 5MB
    
    $root_path = realpath(__DIR__ . '/../../');
    
    if (!$root_path) {
        throw new Exception('Cannot determine root path');
    }
    
    // Check if ZipArchive is available
    if (!class_exists('ZipArchive')) {
        throw new Exception('ZipArchive class not available. Please install php-zip extension.');
    }
    
    $zip = new ZipArchive();
    
    // Use a temporary file first, then move it
    $temp_backup_path = $backup_path . '.tmp';
    
    if ($zip->open($temp_backup_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception('Cannot create backup file: ' . $temp_backup_path);
    }
    
    // Use optimized iterator with flags to skip . and ..
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY // Only files, not directories
    );
    
    $added = 0;
    $skipped = 0;
    $start_time = time();
    $max_duration = 240; // 4 minutes max (leave 1 minute buffer)
    
    foreach ($iterator as $file) {
        // Check timeout
        if (time() - $start_time > $max_duration) {
            logMessage("Backup timeout reached, closing backup...");
            break;
        }
        
        $filePath = $file->getRealPath();
        
        // Skip if file doesn't exist or is not readable
        if (!file_exists($filePath) || !is_readable($filePath)) {
            $skipped++;
            continue;
        }
        
        // Skip if file is too large (check without reading full file)
        $fileSize = @filesize($filePath);
        if ($fileSize === false || $fileSize > $max_file_size) {
            $skipped++;
            continue;
        }
        
        $relativePath = str_replace('\\', '/', substr($filePath, strlen($root_path) + 1));
        
        // Fast exclusion check using string position
        $skip = false;
        
        // Check directory exclusions
        foreach ($exclude_dirs as $excluded) {
            if (strpos($relativePath, $excluded . '/') === 0 || strpos($relativePath, '/' . $excluded . '/') !== false) {
                $skip = true;
                break;
            }
        }
        
        // Check file extension exclusions
        if (!$skip) {
            $file_ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
            if (in_array($file_ext, $exclude_extensions)) {
                $skip = true;
            }
        }
        
        if ($skip) {
            continue;
        }
        
        // Add file to zip (use addFile for better performance than addFromString)
        try {
            if ($zip->addFile($filePath, $relativePath)) {
                $added++;
            } else {
                $skipped++;
            }
        } catch (Exception $e) {
            logMessage("Error adding file to backup: $filePath - " . $e->getMessage());
            $skipped++;
        }
        
        // Periodically check if we should continue
        if ($added % 500 == 0) {
            // Force garbage collection every 500 files
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }
    
    // Close zip file
    if (!$zip->close()) {
        @unlink($temp_backup_path);
        throw new Exception('Failed to close backup ZIP file');
    }
    
    // Move temp file to final location
    if (!rename($temp_backup_path, $backup_path)) {
        @unlink($temp_backup_path);
        throw new Exception('Failed to finalize backup file');
    }
    
    $duration = time() - $start_time;
    $backup_size_mb = file_exists($backup_path) ? round(filesize($backup_path) / 1024 / 1024, 2) : 0;
    
    logMessage("Backup created: $backup_path ($added files, $skipped skipped, {$backup_size_mb}MB, {$duration}s)");
    
    // Store backup path in session
    $_SESSION['last_backup'] = $backup_path;
    
    return [
        'success' => true,
        'backup_path' => $backup_path,
        'files_count' => $added,
        'skipped_count' => $skipped,
        'backup_size_mb' => $backup_size_mb,
        'duration_seconds' => $duration
    ];
}

function downloadUpdate($download_url, $update_dir, $version) {
    $zip_path = $update_dir . 'update_' . $version . '.zip';
    
    // Check if it's a GitHub repository URL (releases or main branch)
    // Remove trailing slash and clean URL
    $download_url = rtrim($download_url, '/');
    
    if (preg_match('/github\.com\/([^\/]+)\/([^\/\?]+)/i', $download_url, $matches)) {
        $owner = $matches[1];
        $repo = $matches[2];
        // Remove any query parameters or fragments from repo name
        $repo = preg_replace('/[?#].*$/', '', $repo);
        
        // Check if it's a releases URL
        if (preg_match('/\/releases/i', $download_url)) {
            // Try to get release assets first
            $tag = $version;
            if (preg_match('/tag\/([^\/\?]+)/i', $download_url, $tag_matches)) {
                $tag = $tag_matches[1];
            }
            
            // GitHub API: Get latest release asset
            $github_api_url = "https://api.github.com/repos/$owner/$repo/releases/latest";
            if ($tag && $tag !== 'latest' && $tag !== $version) {
                $github_api_url = "https://api.github.com/repos/$owner/$repo/releases/tags/$tag";
            }
            
            logMessage("Fetching GitHub release info from: $github_api_url");
            
            $ch = curl_init($github_api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'MusicPlatform-Update-System/1.0');
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $release_data = json_decode($response, true);
                if ($release_data && !empty($release_data['assets'])) {
                    // Find ZIP asset
                    $zip_asset = null;
                    foreach ($release_data['assets'] as $asset) {
                        if (preg_match('/\.zip$/i', $asset['browser_download_url'])) {
                            $zip_asset = $asset;
                            break;
                        }
                    }
                    
                    if ($zip_asset) {
                        $download_url = $zip_asset['browser_download_url'];
                        logMessage("Downloading from GitHub release: $download_url");
                    }
                }
            }
            
            // If no release assets found, fall through to download repository ZIP
            if (!isset($zip_asset)) {
                logMessage("No release assets found, downloading repository ZIP instead");
            }
        }
        
        // If no release assets or not a releases URL, download repository as ZIP
        if (!isset($zip_asset)) {
            // Try to determine branch (default to main)
            $branch = 'main';
            if (preg_match('/tree\/([^\/\?]+)/i', $download_url, $branch_matches)) {
                $branch = $branch_matches[1];
            }
            
            // Download repository as ZIP from branch
            // GitHub provides ZIP downloads at: https://github.com/owner/repo/archive/refs/heads/branch.zip
            $download_url = "https://github.com/$owner/$repo/archive/refs/heads/$branch.zip";
            logMessage("Downloading GitHub repository ZIP from: $download_url");
        }
    }
    
    // Check if it's a cPanel file manager URL - extract the directory path
    if (preg_match('/filemanager.*[?&]dir=([^&#]+)/i', $download_url, $cpanel_matches)) {
        $cpanel_dir = urldecode($cpanel_matches[1]);
        logMessage("Detected cPanel file manager URL. Directory: $cpanel_dir");
        throw new Exception('cPanel file manager URLs are not supported. Please use one of these options: 1) Direct file path: ' . $cpanel_dir . '/update-v1.1.0.zip, 2) Web-accessible URL: https://gospelkingz.com/updates/update-v1.1.0.zip, or 3) See UPDATE_SOURCES.md for setup instructions.');
    }
    
    // Check if it's a cPanel/local file path (absolute path starting with /)
    if (strpos($download_url, '/') === 0 && !preg_match('/^https?:\/\//i', $download_url)) {
        // It's a local file path (cPanel file manager path)
        $local_path = $download_url;
        
        // Try to resolve the path
        if (!file_exists($local_path)) {
            // Try with realpath
            $local_path = realpath($local_path) ?: $download_url;
        }
        
        // If still not found, try relative to common server paths
        if (!file_exists($local_path)) {
            $possible_paths = [
                $local_path,
                $_SERVER['DOCUMENT_ROOT'] . $download_url,
                dirname($_SERVER['DOCUMENT_ROOT']) . $download_url,
                '/home/' . get_current_user() . $download_url
            ];
            
            foreach ($possible_paths as $try_path) {
                if (file_exists($try_path)) {
                    $local_path = $try_path;
                    break;
                }
            }
        }
        
        if (!file_exists($local_path)) {
            throw new Exception('Local file not found: ' . $download_url . '. Please verify the file path or make the file web-accessible via HTTP.');
        }
        
        if (!is_readable($local_path)) {
            throw new Exception('Local file is not readable: ' . $local_path . '. Please check file permissions.');
        }
        
        // Copy local file to update directory
        if (!copy($local_path, $zip_path)) {
            throw new Exception('Failed to copy local file to update directory. Please check write permissions.');
        }
        
        logMessage("Copied local file: $local_path -> $zip_path");
        
        if (!file_exists($zip_path) || filesize($zip_path) < 1000) {
            throw new Exception('Copied file is invalid or too small');
        }
        
        return [
            'success' => true,
            'zip_path' => $zip_path,
            'size' => filesize($zip_path)
        ];
    }
    
    // Validate download URL before proceeding (for HTTP/HTTPS URLs)
    if (!empty($download_url) && !filter_var($download_url, FILTER_VALIDATE_URL) && !preg_match('/^\/|^\.\.\//', $download_url)) {
        throw new Exception('Invalid download URL: ' . $download_url);
    }
    
    // Regular HTTP/HTTPS download
    logMessage("Starting download from: $download_url");
    
    $ch = curl_init($download_url);
    $fp = fopen($zip_path, 'w');
    
    if (!$fp) {
        throw new Exception('Cannot create ZIP file: ' . $zip_path);
    }
    
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MusicPlatform-Update-System/1.0');
    curl_setopt($ch, CURLOPT_VERBOSE, false);
    
    $success = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $downloaded_size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
    $error = curl_error($ch);
    curl_close($ch);
    fclose($fp);
    
    logMessage("Download completed. HTTP: $http_code, Size: $downloaded_size bytes, Content-Type: $content_type");
    
    if (!$success || $http_code !== 200) {
        if (file_exists($zip_path)) {
            unlink($zip_path);
        }
        $error_msg = $error ?: "HTTP $http_code";
        if ($http_code === 404) {
            $error_msg .= " - File not found. Please check the URL.";
        } elseif ($http_code === 403) {
            $error_msg .= " - Access forbidden. Repository may be private or rate-limited.";
        }
        throw new Exception('Download failed: ' . $error_msg);
    }
    
    if (!file_exists($zip_path)) {
        throw new Exception('Downloaded file does not exist: ' . $zip_path);
    }
    
    $file_size = filesize($zip_path);
    if ($file_size < 1000) {
        // Read first few bytes to check if it's an error page
        $handle = fopen($zip_path, 'r');
        $first_bytes = fread($handle, 500);
        fclose($handle);
        
        if (strpos($first_bytes, '<html') !== false || strpos($first_bytes, '<!DOCTYPE') !== false) {
            throw new Exception('Downloaded file appears to be an HTML error page, not a ZIP file. Please check the URL.');
        }
        
        throw new Exception('Downloaded file is too small (' . $file_size . ' bytes). Expected ZIP file.');
    }
    
    // Verify it's actually a ZIP file
    $zip_test = new ZipArchive();
    $zip_test_result = $zip_test->open($zip_path, ZipArchive::CHECKCONS);
    if ($zip_test_result !== TRUE) {
        $zip_test->close();
        throw new Exception('Downloaded file is not a valid ZIP file. Error code: ' . $zip_test_result);
    }
    $zip_test->close();
    
    logMessage("ZIP file validated successfully. Size: " . number_format($file_size) . " bytes");
    
    logMessage("Update downloaded: $zip_path");
    
    return [
        'success' => true,
        'zip_path' => $zip_path,
        'size' => filesize($zip_path)
    ];
}

function rmdir_recursive($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? rmdir_recursive($path) : unlink($path);
    }
    rmdir($dir);
}

function extractUpdate($zip_path, $temp_dir, $version) {
    $extract_path = $temp_dir . 'update_' . $version . '/';
    
    // Clean extract directory
    if (is_dir($extract_path)) {
        rmdir_recursive($extract_path);
    }
    mkdir($extract_path, 0755, true);
    
    $zip = new ZipArchive();
    if ($zip->open($zip_path) !== TRUE) {
        throw new Exception('Cannot open update ZIP file');
    }
    
    $zip->extractTo($extract_path);
    $zip->close();
    
    logMessage("Update extracted to: $extract_path");
    
    return [
        'success' => true,
        'extract_path' => $extract_path
    ];
}

function deleteFiles($root_path, $extract_path = null) {
    /**
     * Delete files listed in deletions.txt from the live server
     * @param string $root_path Root path of the installation
     * @param string $extract_path Optional path to extracted update package (where deletions.txt should be)
     * @return array Result with deleted count and errors
     */
    // First, try to find deletions.txt in the extracted update package
    $deletions_file = null;
    if (!empty($extract_path)) {
        // Look for deletions.txt in the extract path (may be in root or updates subfolder)
        $possible_paths = [
            rtrim($extract_path, '/\\') . '/deletions.txt',
            rtrim($extract_path, '/\\') . '/updates/deletions.txt',
        ];
        
        // Also check if extract_path contains a subdirectory (GitHub ZIP structure)
        $items = @scandir($extract_path);
        if ($items) {
            foreach ($items as $item) {
                if ($item !== '.' && $item !== '..' && is_dir($extract_path . '/' . $item)) {
                    $possible_paths[] = rtrim($extract_path, '/\\') . '/' . $item . '/deletions.txt';
                    $possible_paths[] = rtrim($extract_path, '/\\') . '/' . $item . '/updates/deletions.txt';
                }
            }
        }
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $deletions_file = $path;
                logMessage("Found deletions.txt in update package: $path");
                break;
            }
        }
    }
    
    // Fallback: check live server's updates folder (for backward compatibility)
    if (empty($deletions_file)) {
        $deletions_file = $root_path . '/updates/deletions.txt';
    }
    
    if (!file_exists($deletions_file)) {
        logMessage("No deletions.txt file found, skipping file deletions");
        return [
            'success' => true,
            'files_deleted' => 0,
            'errors_count' => 0
        ];
    }
    
    logMessage("Using deletions.txt from: $deletions_file");
    
    $deleted = 0;
    $errors = [];
    $lines = file($deletions_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    logMessage("Processing " . count($lines) . " deletion entries");
    
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
            logMessage("ERROR: $error_msg");
            continue;
        }
        
        // Check if file exists
        if (!file_exists($target_path)) {
            logMessage("File not found (already deleted?): $file_path");
            continue;
        }
        
        // Delete the file
        if (@unlink($target_path)) {
            $deleted++;
            logMessage("Deleted: $file_path");
        } else {
            $error_msg = "Failed to delete: $file_path";
            $errors[] = $error_msg;
            logMessage("ERROR: $error_msg");
        }
    }
    
    logMessage("File deletions completed: $deleted deleted, " . count($errors) . " errors");
    
    return [
        'success' => true,
        'files_deleted' => $deleted,
        'errors_count' => count($errors),
        'errors' => $errors
    ];
}

function installFiles($extract_path, $root_path) {
    // Set execution limits for installation
    set_time_limit(300); // 5 minutes max
    ini_set('max_execution_time', 300);
    ini_set('memory_limit', '512M');
    
    // Find the actual update files (may be in a subdirectory)
    $update_files = findUpdateFiles($extract_path);
    
    if (empty($update_files)) {
        throw new Exception('No files found in update package');
    }
    
    $copied = 0;
    $errors = [];
    $start_time = time();
    $max_duration = 240; // 4 minutes max (leave 1 minute buffer)
    
    // Get base path from first file (all files should have the same base)
    $base_path = $update_files[0]['base_path'];
    $base_path = rtrim(realpath($base_path) ?: $base_path, '/\\') . DIRECTORY_SEPARATOR;
    $root_path = rtrim(realpath($root_path) ?: $root_path, '/\\') . DIRECTORY_SEPARATOR;
    
    logMessage("Installing files from base: $base_path to root: $root_path");
    logMessage("First file example: " . $update_files[0]['full_path']);
    logMessage("Total files to install: " . count($update_files));
    logMessage("Starting file installation at " . date('Y-m-d H:i:s'));
    
    // Exclude certain files/directories from installation
    // IMPORTANT: Never overwrite config.php, database.php, or user uploads
    $exclude_patterns = [
        'config/config.php',  // Protect live config from being overwritten
        'config/database.php', // Protect database config
        '/\.git/',
        '/\.gitignore/',
        '/README\.md$/',
        '/LICENSE$/',
        '/\.github/',
        '/node_modules/',
        '/vendor/',
        '/composer\.lock$/',
        '/package\.json$/',
        '/package-lock\.json$/',
        '/\.gitattributes$/',
        '/\.gitmodules$/'
    ];
    
    foreach ($update_files as $file_data) {
        // Check timeout
        if (time() - $start_time > $max_duration) {
            logMessage("Install timeout reached after " . (time() - $start_time) . " seconds. Copied $copied files so far.");
            break;
        }
        
        $file = $file_data['full_path'];
        $file_base = $file_data['base_path'];
        
        // Normalize paths for comparison (handle Windows/Unix differences)
        // Convert both to forward slashes for consistent comparison
        $file_normalized = str_replace('\\', '/', $file);
        $base_normalized = str_replace('\\', '/', $file_base);
        
        // Ensure base path ends with /
        if (substr($base_normalized, -1) !== '/') {
            $base_normalized .= '/';
        }
        
        // Calculate relative path from base
        if (strpos($file_normalized, $base_normalized) === 0) {
            $relative_path = substr($file_normalized, strlen($base_normalized));
        } else {
            // Fallback: try direct replacement
            $relative_path = str_replace($base_normalized, '', $file_normalized);
        }
        
        // Clean up relative path
        $relative_path = ltrim($relative_path, '/\\');
        
        // Skip if relative path is empty
        if (empty($relative_path)) {
            logMessage("Skipping file with empty relative path. File: $file, Base: $file_base");
            continue;
        }
        
        // Validate relative path doesn't contain dangerous patterns
        if (strpos($relative_path, '..') !== false) {
            logMessage("Skipping file with dangerous path: $relative_path");
            continue;
        }
        
        // Check file size before copying (skip very large files to prevent timeouts)
        $file_size = @filesize($file);
        $max_file_size = 10 * 1024 * 1024; // 10MB max
        if ($file_size !== false && $file_size > $max_file_size) {
            logMessage("Skipping large file: $relative_path (" . round($file_size / 1024 / 1024, 2) . "MB)");
            continue;
        }
        
        // Only log every 50th file to reduce log spam, but always log important files
        if ($copied % 50 === 0 || strpos($relative_path, '.php') !== false) {
            logMessage("Installing: $relative_path (file " . ($copied + 1) . " of " . count($update_files) . ")");
        }
        
        // Skip excluded files
        $skip = false;
        foreach ($exclude_patterns as $pattern) {
            // Check if pattern is a regex (starts with /) or exact match
            if (strpos($pattern, '/') === 0) {
                // Regex pattern
                if (preg_match($pattern, $relative_path)) {
                    $skip = true;
                    logMessage("Skipping excluded file (regex): $relative_path");
                    break;
                }
            } else {
                // Exact match or starts with
                if ($relative_path === $pattern || strpos($relative_path, $pattern) === 0) {
                    $skip = true;
                    logMessage("Skipping excluded file (exact): $relative_path");
                    break;
                }
            }
        }
        if ($skip) {
            continue;
        }
        
        // Use forward slashes for target path (works on both Windows and Unix)
        $target_path = $root_path . str_replace('\\', '/', $relative_path);
        $target_dir = dirname($target_path);
        
        // Normalize target directory path
        $target_dir = str_replace('\\', '/', $target_dir);
        
        // Create directory if needed
        if (!is_dir($target_dir)) {
            if (!@mkdir($target_dir, 0755, true)) {
                $error_msg = "Failed to create directory: $target_dir (check permissions)";
                $errors[] = $error_msg;
                logMessage("ERROR: $error_msg");
                continue;
            }
        }
        
        // Check if target directory is writable
        if (!is_writable($target_dir)) {
            $error_msg = "Directory not writable: $target_dir";
            $errors[] = $error_msg;
            logMessage("ERROR: $error_msg");
            continue;
        }
        
        // Copy file with timeout protection
        $copy_start = microtime(true);
        $copy_success = false;
        
        // Use a simple copy operation - don't sync during installation to avoid hangs
        if (@copy($file, $target_path)) {
            $copy_success = true;
            $copied++;
        }
        
        $copy_time = microtime(true) - $copy_start;
        if ($copy_time > 1) {
            logMessage("WARNING: Copy took {$copy_time}s for: $relative_path");
        }
        
        if (!$copy_success) {
            $last_error = error_get_last();
            $error_detail = $last_error ? $last_error['message'] : 'Unknown error';
            $error_msg = "Failed to copy: $relative_path - $error_detail";
            $errors[] = $error_msg;
            logMessage("ERROR: $error_msg");
        } else {
            // Log progress every 5 files to provide more frequent updates
            if ($copied % 5 === 0) {
                $elapsed = time() - $start_time;
                $remaining = count($update_files) - ($copied + 1);
                $percent = round(($copied / count($update_files)) * 100, 1);
                logMessage("Progress: Copied $copied files ({$percent}%) in {$elapsed}s, ~$remaining remaining");
                
                // Flush output to prevent timeout (but don't break JSON)
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                @flush();
            }
        }
    }
    
    $total_time = time() - $start_time;
    logMessage("Files installed: $copied copied, " . count($errors) . " errors in {$total_time} seconds");
    
    if (!empty($errors)) {
        $error_list = array_slice($errors, 0, 10);
        logMessage("Installation errors: " . implode('; ', $error_list));
        // Don't throw exception if we copied some files - just log errors
        if ($copied === 0) {
            throw new Exception('No files were installed. Errors: ' . implode(', ', $error_list) . (count($errors) > 10 ? ' ... and ' . (count($errors) - 10) . ' more' : ''));
        }
    }
    
    return [
        'success' => true,
        'files_copied' => $copied,
        'errors_count' => count($errors),
        'duration_seconds' => $total_time
    ];
}

function findUpdateFiles($extract_path) {
    $files = [];
    
    // Normalize path
    $extract_path = rtrim(realpath($extract_path) ?: $extract_path, '/\\') . DIRECTORY_SEPARATOR;
    
    logMessage("Searching for files in: $extract_path");
    
    // Check if extracted path contains a single subdirectory (common with GitHub ZIP downloads)
    $dirs = [];
    if (is_dir($extract_path)) {
        $items = scandir($extract_path);
        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($extract_path . $item)) {
                $dirs[] = $extract_path . $item;
            }
        }
    }
    
    // If there's exactly one subdirectory, use that as the base (GitHub repository ZIP structure)
    $base_path = $extract_path;
    if (count($dirs) === 1) {
        $base_path = rtrim(realpath($dirs[0]) ?: $dirs[0], '/\\') . DIRECTORY_SEPARATOR;
        logMessage("Detected GitHub repository structure, using subdirectory: " . basename($base_path));
    } else {
        logMessage("No single subdirectory found, using extract path directly. Found " . count($dirs) . " directories.");
    }
    
    if (!is_dir($base_path)) {
        throw new Exception("Base path does not exist: $base_path");
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $real_path = $file->getRealPath();
            if ($real_path) {
                $files[] = [
                    'full_path' => $real_path,
                    'base_path' => $base_path
                ];
            }
        }
    }
    
    logMessage("Found " . count($files) . " files to install from base: $base_path");
    
    if (count($files) === 0) {
        logMessage("WARNING: No files found! Extract path contents: " . implode(', ', array_slice(scandir($extract_path) ?: [], 0, 10)));
    }
    
    return $files;
}

function finalizeUpdate($version) {
    // Update version in config or database
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        if (!$conn) {
            throw new Exception('Database connection failed');
        }
        
        // Get current version before updating (for rollback)
        $current_stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'script_version'");
        $current_stmt->execute();
        $current_result = $current_stmt->fetch(PDO::FETCH_ASSOC);
        $previous_version = $current_result['setting_value'] ?? '1.0';
        
        // Save previous version to session for rollback
        $_SESSION['previous_version'] = $previous_version;
        
        // Update version in settings
        $stmt = $conn->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES ('script_version', ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$version, $version]);
        
        logMessage("Version updated from $previous_version to: $version");
        
        return ['success' => true, 'message' => 'Update finalized successfully'];
    } catch (PDOException $e) {
        logMessage("Warning: Could not update version in database: " . $e->getMessage());
        // Not critical, continue - version update is optional
        return ['success' => true, 'message' => 'Update finalized (version not saved to database)', 'warning' => 'Database update skipped'];
    } catch (Exception $e) {
        logMessage("Warning: Could not update version in database: " . $e->getMessage());
        // Not critical, continue
        return ['success' => true, 'message' => 'Update finalized (version not saved to database)', 'warning' => 'Database update skipped'];
    }
}

function rollbackUpdate($backup_path) {
    // Rollback only changes the version in settings table, not files
    // Get the previous version from backup or session
    $previous_version = $_SESSION['previous_version'] ?? '1.0';
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Update version in settings table only
        $stmt = $conn->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES ('script_version', ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$previous_version, $previous_version]);
        
        logMessage("Version rolled back to: $previous_version (settings table only)");
        
        return [
            'success' => true,
            'message' => "Version rolled back to $previous_version",
            'version' => $previous_version
        ];
    } catch (Exception $e) {
        throw new Exception('Failed to rollback version: ' . $e->getMessage());
    }
}

try {
    switch ($action) {
        case 'backup':
            try {
                $result = createBackup($backup_dir);
                echo json_encode($result);
            } catch (Exception $e) {
                logMessage("Backup error: " . $e->getMessage());
                // Return error but don't fail completely - return 200 with success=false
                // so frontend can handle it gracefully
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'warning' => 'Backup failed but you can continue without backup (not recommended)'
                ]);
            } catch (Error $e) {
                // Catch fatal errors (PHP 7+)
                logMessage("Backup fatal error: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'warning' => 'Backup failed but you can continue without backup (not recommended)'
                ]);
            }
            break;
            
        case 'download':
            $download_url = $input['download_url'] ?? '';
            $version = $input['version'] ?? '';
            
            if (empty($download_url) || empty($version)) {
                throw new Exception('Download URL and version are required');
            }
            
            $result = downloadUpdate($download_url, $update_dir, $version);
            $_SESSION['update_zip_path'] = $result['zip_path'];
            echo json_encode($result);
            break;
            
        case 'extract':
            ob_clean();
            try {
                $version = $input['version'] ?? '';
                $zip_path = $_SESSION['update_zip_path'] ?? '';
                
                if (empty($zip_path) || !file_exists($zip_path)) {
                    throw new Exception('Update ZIP file not found');
                }
                
                $result = extractUpdate($zip_path, $temp_dir, $version);
                $_SESSION['update_extract_path'] = $result['extract_path'];
                
                ob_clean();
                echo json_encode($result);
            } catch (Exception $e) {
                ob_clean();
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
                logMessage("Extract error: " . $e->getMessage());
            }
            break;
            
        case 'install':
            // Ensure clean output buffer before starting
            ob_clean();
            
            // Set error handler to catch any PHP errors/warnings
            $error_handler = function($errno, $errstr, $errfile, $errline) {
                logMessage("PHP Error [$errno]: $errstr in $errfile:$errline");
                return true; // Don't execute PHP internal error handler
            };
            set_error_handler($error_handler);
            
            try {
                // Increase limits for install operation
                set_time_limit(300);
                ini_set('max_execution_time', 300);
                ini_set('memory_limit', '512M');
                
                $version = $input['version'] ?? '';
                $extract_path = $_SESSION['update_extract_path'] ?? '';
                $root_path = realpath(__DIR__ . '/../../');
                
                logMessage("Starting installation. Extract path: $extract_path, Root path: $root_path");
                
                if (empty($extract_path) || !is_dir($extract_path)) {
                    throw new Exception('Extracted files not found. Path: ' . $extract_path);
                }
                
                if (empty($root_path)) {
                    throw new Exception('Cannot determine root path');
                }
                
                // First, delete files listed in deletions.txt (from the extracted update package)
                logMessage("Checking for files to delete...");
                $delete_result = deleteFiles($root_path, $extract_path);
                if ($delete_result['files_deleted'] > 0) {
                    logMessage("Deleted " . $delete_result['files_deleted'] . " files from live server");
                } else {
                    logMessage("No files were deleted (deletions.txt may be empty or not found)");
                }
                
                // Then install new/updated files
                logMessage("Calling installFiles...");
                $install_start = time();
                $result = installFiles($extract_path, $root_path);
                $install_duration = time() - $install_start;
                logMessage("installFiles completed successfully in {$install_duration} seconds");
                
                // Add deletion info to result
                $result['files_deleted'] = $delete_result['files_deleted'] ?? 0;
                $result['deletion_errors'] = $delete_result['errors_count'] ?? 0;
                
                // Skip sync during installation - it can cause hangs and is not critical
                // Sync can be done manually later if needed
                logMessage("Skipping file sync (not critical for installation)");
                
                // Clean any output before sending JSON
                ob_clean();
                
                // Restore error handler
                restore_error_handler();
                
                logMessage("Installation successful. Sending JSON response.");
                echo json_encode($result);
                exit; // Ensure no other output
            } catch (Exception $e) {
                ob_clean();
                restore_error_handler();
                http_response_code(500);
                $error_response = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ];
                logMessage("Install error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                logMessage("Stack trace: " . $e->getTraceAsString());
                echo json_encode($error_response);
                exit;
            } catch (Error $e) {
                ob_clean();
                restore_error_handler();
                http_response_code(500);
                $error_response = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ];
                logMessage("Install fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                logMessage("Stack trace: " . $e->getTraceAsString());
                echo json_encode($error_response);
                exit;
            } catch (Throwable $e) {
                ob_clean();
                restore_error_handler();
                http_response_code(500);
                $error_response = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ];
                logMessage("Install throwable error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                logMessage("Stack trace: " . $e->getTraceAsString());
                echo json_encode($error_response);
                exit;
            }
            break;
            
        case 'finalize':
            // Clear any output before finalize
            ob_clean();
            
            $version = $input['version'] ?? '';
            $result = finalizeUpdate($version);
            
            // Cleanup
            if (isset($_SESSION['update_zip_path']) && file_exists($_SESSION['update_zip_path'])) {
                @unlink($_SESSION['update_zip_path']);
            }
            if (isset($_SESSION['update_extract_path']) && is_dir($_SESSION['update_extract_path'])) {
                rmdir_recursive($_SESSION['update_extract_path']);
            }
            
            unset($_SESSION['update_zip_path'], $_SESSION['update_extract_path'], $_SESSION['update_version'], $_SESSION['update_url']);
            
            // Ensure clean output for JSON
            ob_clean();
            echo json_encode($result, JSON_UNESCAPED_SLASHES);
            break;
            
        case 'rollback':
            $backup_path = $_SESSION['last_backup'] ?? '';
            if (empty($backup_path)) {
                throw new Exception('No backup found to restore');
            }
            $result = rollbackUpdate($backup_path);
            echo json_encode($result);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    // Clear any output before sending error
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    logMessage("ERROR: " . $e->getMessage());
    exit;
}

// End output buffering
ob_end_flush();

