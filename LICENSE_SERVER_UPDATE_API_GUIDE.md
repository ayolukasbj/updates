# License Server Update API - File Change Detection Guide

## Overview

This guide explains how to implement file change detection in the license server's update API so that the "Force Check for Updates" feature can detect code/file changes even when the version number matches.

## Problem

Currently, the update system only checks version numbers. If files are updated but the version number stays the same, the system won't detect the changes. The "Force Check for Updates" feature should detect file changes by comparing file hashes/checksums.

## Solution: File Hash Comparison

The license server's `/api/updates.php` endpoint should:

1. **Accept `force_check` parameter**: When `force_check=1`, the server should compare file hashes even if versions match
2. **Calculate file hashes**: For each file in the update package, calculate MD5 or SHA256 hash
3. **Store file hashes**: Store file hashes in the database along with the update record
4. **Compare hashes**: When client requests update with `force_check=1`, compare stored hashes with client's file hashes
5. **Return update if differences found**: If any file hashes don't match, return the update even if version matches

## Implementation Steps

### Step 1: Database Schema

Add a table to store file hashes for each update:

```sql
CREATE TABLE IF NOT EXISTS update_file_hashes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    update_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_hash VARCHAR(64) NOT NULL,
    file_size INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_update_id (update_id),
    INDEX idx_file_path (file_path)
);
```

### Step 2: Update API Endpoint (`/api/updates.php`)

```php
<?php
// license-server/api/updates.php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

$version = $_GET['version'] ?? '1.0';
$force_check = isset($_GET['force_check']) && $_GET['force_check'] == '1';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get latest update
    $stmt = $conn->prepare("
        SELECT * FROM updates 
        WHERE is_active = 1 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $latest_update = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$latest_update) {
        echo json_encode([
            'has_update' => false,
            'message' => 'No updates available'
        ]);
        exit;
    }
    
    // Check if version matches
    $version_matches = ($latest_update['version'] === $version);
    
    // If version matches and force_check is enabled, check file hashes
    if ($version_matches && $force_check) {
        // Get file hashes for this update
        $hash_stmt = $conn->prepare("
            SELECT file_path, file_hash, file_size 
            FROM update_file_hashes 
            WHERE update_id = ?
        ");
        $hash_stmt->execute([$latest_update['id']]);
        $file_hashes = $hash_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If file hashes exist, this means files have changed
        // Return update even though version matches
        if (!empty($file_hashes)) {
            echo json_encode([
                'has_update' => true,
                'force_update' => true,
                'message' => 'File changes detected',
                'latest_update' => [
                    'version' => $latest_update['version'],
                    'title' => $latest_update['title'],
                    'description' => $latest_update['description'],
                    'changelog' => $latest_update['changelog'],
                    'download_url' => $latest_update['download_url'],
                    'file_count' => count($file_hashes),
                    'file_hashes' => $file_hashes
                ]
            ]);
            exit;
        }
    }
    
    // Normal version comparison
    if (!$version_matches) {
        echo json_encode([
            'has_update' => true,
            'latest_update' => [
                'version' => $latest_update['version'],
                'title' => $latest_update['title'],
                'description' => $latest_update['description'],
                'changelog' => $latest_update['changelog'],
                'download_url' => $latest_update['download_url']
            ]
        ]);
    } else {
        echo json_encode([
            'has_update' => false,
            'message' => 'You are running the latest version'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Update API error: " . $e->getMessage());
    echo json_encode([
        'has_update' => false,
        'error' => 'Error checking for updates'
    ]);
}
?>
```

### Step 3: Calculate File Hashes When Creating Update

When you create an update in the license server admin panel, calculate and store file hashes:

```php
// When creating/updating an update package
function calculateUpdateFileHashes($update_id, $zip_file_path) {
    try {
        $zip = new ZipArchive();
        if ($zip->open($zip_file_path) === TRUE) {
            $db = new Database();
            $conn = $db->getConnection();
            
            // Delete existing hashes for this update
            $delete_stmt = $conn->prepare("DELETE FROM update_file_hashes WHERE update_id = ?");
            $delete_stmt->execute([$update_id]);
            
            // Calculate hash for each file
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                
                // Skip directories
                if (substr($filename, -1) === '/') {
                    continue;
                }
                
                // Get file contents
                $file_contents = $zip->getFromIndex($i);
                
                // Calculate hash
                $file_hash = md5($file_contents);
                $file_size = strlen($file_contents);
                
                // Store hash
                $insert_stmt = $conn->prepare("
                    INSERT INTO update_file_hashes (update_id, file_path, file_hash, file_size) 
                    VALUES (?, ?, ?, ?)
                ");
                $insert_stmt->execute([$update_id, $filename, $file_hash, $file_size]);
            }
            
            $zip->close();
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Error calculating file hashes: " . $e->getMessage());
        return false;
    }
}
```

### Step 4: Client-Side File Hash Comparison (Optional)

For more accurate detection, the client can send file hashes to compare:

```php
// Client sends file hashes in update check request
$client_file_hashes = [
    'news-details.php' => md5_file('news-details.php'),
    'includes/ads.php' => md5_file('includes/ads.php'),
    // ... other files
];

// Send to server
$ch = curl_init($updates_api_url . '?version=' . $version . '&force_check=1');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['file_hashes' => $client_file_hashes]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
// ... rest of curl options
```

## Alternative: Simpler Approach

If implementing file hash comparison is too complex, you can use a simpler approach:

1. **Add `file_modified_at` timestamp** to updates table
2. **Compare timestamps**: When `force_check=1`, always return the update if `file_modified_at` is newer than the last update check
3. **Store last check time**: Client stores last update check timestamp
4. **Compare timestamps**: Server compares update timestamp with client's last check timestamp

```php
// Simpler approach - timestamp comparison
if ($force_check && $version_matches) {
    // Get last update check time from client (send as parameter)
    $last_check = $_GET['last_check'] ?? '0';
    
    // Get update's file modification time
    $update_timestamp = strtotime($latest_update['updated_at']);
    $last_check_timestamp = (int)$last_check;
    
    // If update is newer than last check, return update
    if ($update_timestamp > $last_check_timestamp) {
        echo json_encode([
            'has_update' => true,
            'force_update' => true,
            'latest_update' => $latest_update
        ]);
        exit;
    }
}
```

## Testing

1. **Create an update** with the same version number
2. **Update some files** in the update package
3. **Click "Force Check for Updates"** in admin panel
4. **Verify** that the update is detected even though version matches

## Notes

- File hash calculation can be CPU-intensive for large updates
- Consider caching file hashes
- For GitHub updates, calculate hashes from downloaded ZIP
- For local file updates, calculate hashes from file system

## Quick Fix for Immediate Use

If you need a quick fix without database changes:

1. **Add `force_update` flag** to updates table
2. **Set flag to 1** when files are updated but version stays the same
3. **Check flag in API**: If `force_update=1` and `force_check=1`, return update

```sql
ALTER TABLE updates ADD COLUMN force_update TINYINT(1) DEFAULT 0;
```

```php
// In updates.php API
if ($force_check && $version_matches && $latest_update['force_update'] == 1) {
    echo json_encode([
        'has_update' => true,
        'force_update' => true,
        'latest_update' => $latest_update
    ]);
    exit;
}
```

Then manually set `force_update=1` in the database when you update files without changing the version.

