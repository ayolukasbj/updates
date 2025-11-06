<?php
// admin/api/install-update.php
// Update Installation API

require_once '../auth-check.php';
require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

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
    
    $timestamp = date('Y-m-d_H-i-s');
    $backup_path = $backup_dir . 'backup_' . $timestamp . '.zip';
    
    // Files and directories to backup (exclude backups, updates, temp, uploads)
    $exclude = [
        'backups',
        'updates',
        'temp',
        'uploads',
        'node_modules',
        '.git'
    ];
    
    $root_path = realpath(__DIR__ . '/../../');
    $zip = new ZipArchive();
    
    if ($zip->open($backup_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception('Cannot create backup file');
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root_path),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $added = 0;
    foreach ($iterator as $file) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($root_path) + 1);
        
        // Skip excluded directories
        $skip = false;
        foreach ($exclude as $excluded) {
            if (strpos($relativePath, $excluded) === 0) {
                $skip = true;
                break;
            }
        }
        
        if ($skip || $file->isDir()) {
            continue;
        }
        
        $zip->addFile($filePath, $relativePath);
        $added++;
    }
    
    $zip->close();
    
    logMessage("Backup created: $backup_path ($added files)");
    
    // Store backup path in session
    $_SESSION['last_backup'] = $backup_path;
    
    return [
        'success' => true,
        'backup_path' => $backup_path,
        'files_count' => $added
    ];
}

function downloadUpdate($download_url, $update_dir, $version) {
    $zip_path = $update_dir . 'update_' . $version . '.zip';
    
    // Check if it's a GitHub release URL
    if (preg_match('/github\.com\/([^\/]+)\/([^\/]+)\/releases/i', $download_url, $matches)) {
        $owner = $matches[1];
        $repo = $matches[2];
        
        // Extract tag/version from URL or use provided version
        $tag = $version;
        if (preg_match('/tag\/([^\/\?]+)/i', $download_url, $tag_matches)) {
            $tag = $tag_matches[1];
        }
        
        // GitHub API: Get latest release asset
        $github_api_url = "https://api.github.com/repos/$owner/$repo/releases/latest";
        if ($tag && $tag !== 'latest') {
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
        
        if ($http_code !== 200) {
            throw new Exception('Failed to fetch GitHub release info (HTTP ' . $http_code . ')');
        }
        
        $release_data = json_decode($response, true);
        if (!$release_data || empty($release_data['assets'])) {
            throw new Exception('No release assets found on GitHub');
        }
        
        // Find ZIP asset
        $zip_asset = null;
        foreach ($release_data['assets'] as $asset) {
            if (preg_match('/\.zip$/i', $asset['browser_download_url'])) {
                $zip_asset = $asset;
                break;
            }
        }
        
        if (!$zip_asset) {
            throw new Exception('No ZIP file found in GitHub release assets');
        }
        
        $download_url = $zip_asset['browser_download_url'];
        logMessage("Downloading from GitHub: $download_url");
    }
    
    // Check if it's a cPanel/local file path
    if (strpos($download_url, '/') === 0 || strpos($download_url, '../') === 0 || strpos($download_url, './') === 0) {
        // It's a local file path (cPanel file manager path)
        $local_path = realpath($download_url);
        
        if (!$local_path || !file_exists($local_path)) {
            // Try relative to document root
            $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/../../';
            $local_path = realpath($doc_root . '/' . ltrim($download_url, '/'));
        }
        
        if (!$local_path || !file_exists($local_path)) {
            throw new Exception('Local file not found: ' . $download_url);
        }
        
        if (!is_readable($local_path)) {
            throw new Exception('Local file is not readable: ' . $local_path);
        }
        
        // Copy local file to update directory
        if (!copy($local_path, $zip_path)) {
            throw new Exception('Failed to copy local file to update directory');
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
    
    // Regular HTTP/HTTPS download
    $ch = curl_init($download_url);
    $fp = fopen($zip_path, 'w');
    
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MusicPlatform-Update-System/1.0');
    
    $success = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    fclose($fp);
    
    if (!$success || $http_code !== 200) {
        if (file_exists($zip_path)) {
            unlink($zip_path);
        }
        throw new Exception('Download failed: ' . ($error ?: "HTTP $http_code"));
    }
    
    if (!file_exists($zip_path) || filesize($zip_path) < 1000) {
        throw new Exception('Downloaded file is invalid or too small');
    }
    
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

function installFiles($extract_path, $root_path) {
    // Find the actual update files (may be in a subdirectory)
    $update_files = findUpdateFiles($extract_path);
    
    $copied = 0;
    $errors = [];
    
    foreach ($update_files as $file) {
        $relative_path = substr($file, strlen($extract_path));
        $target_path = $root_path . '/' . $relative_path;
        $target_dir = dirname($target_path);
        
        // Create directory if needed
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        // Copy file
        if (!copy($file, $target_path)) {
            $errors[] = "Failed to copy: $relative_path";
        } else {
            $copied++;
        }
    }
    
    logMessage("Files installed: $copied copied, " . count($errors) . " errors");
    
    if (!empty($errors)) {
        throw new Exception('Some files failed to install: ' . implode(', ', array_slice($errors, 0, 5)));
    }
    
    return [
        'success' => true,
        'files_copied' => $copied
    ];
}

function findUpdateFiles($extract_path) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($extract_path),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = $file->getRealPath();
        }
    }
    
    return $files;
}

function finalizeUpdate($version) {
    // Update version in config or database
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Update version in settings
        $stmt = $conn->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES ('script_version', ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$version, $version]);
        
        logMessage("Version updated to: $version");
        
        return ['success' => true];
    } catch (Exception $e) {
        logMessage("Warning: Could not update version in database: " . $e->getMessage());
        // Not critical, continue
        return ['success' => true];
    }
}

function rollbackUpdate($backup_path) {
    if (!file_exists($backup_path)) {
        throw new Exception('Backup file not found');
    }
    
    $root_path = realpath(__DIR__ . '/../../');
    $zip = new ZipArchive();
    
    if ($zip->open($backup_path) !== TRUE) {
        throw new Exception('Cannot open backup file');
    }
    
    // Extract backup
    $zip->extractTo($root_path);
    $zip->close();
    
    logMessage("Backup restored from: $backup_path");
    
    return ['success' => true];
}

try {
    switch ($action) {
        case 'backup':
            $result = createBackup($backup_dir);
            echo json_encode($result);
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
            $version = $input['version'] ?? '';
            $zip_path = $_SESSION['update_zip_path'] ?? '';
            
            if (empty($zip_path) || !file_exists($zip_path)) {
                throw new Exception('Update ZIP file not found');
            }
            
            $result = extractUpdate($zip_path, $temp_dir, $version);
            $_SESSION['update_extract_path'] = $result['extract_path'];
            echo json_encode($result);
            break;
            
        case 'install':
            $version = $input['version'] ?? '';
            $extract_path = $_SESSION['update_extract_path'] ?? '';
            $root_path = realpath(__DIR__ . '/../../');
            
            if (empty($extract_path) || !is_dir($extract_path)) {
                throw new Exception('Extracted files not found');
            }
            
            $result = installFiles($extract_path, $root_path);
            echo json_encode($result);
            break;
            
        case 'finalize':
            $version = $input['version'] ?? '';
            $result = finalizeUpdate($version);
            
            // Cleanup
            if (isset($_SESSION['update_zip_path']) && file_exists($_SESSION['update_zip_path'])) {
                unlink($_SESSION['update_zip_path']);
            }
            if (isset($_SESSION['update_extract_path']) && is_dir($_SESSION['update_extract_path'])) {
                rmdir_recursive($_SESSION['update_extract_path']);
            }
            
            unset($_SESSION['update_zip_path'], $_SESSION['update_extract_path'], $_SESSION['update_version'], $_SESSION['update_url']);
            
            echo json_encode($result);
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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    logMessage("ERROR: " . $e->getMessage());
}

