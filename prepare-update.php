<?php
/**
 * Prepare Update Package
 * 
 * This script copies changed files to the updates folder for packaging
 * Usage: php prepare-update.php
 */

$source_dir = __DIR__;
$updates_dir = 'C:\Users\HYLINK\Desktop\music - Copy\updates';

// Files that were changed for base URL fix
$changed_files = [
    'config/config.php',
    'config/license.php',
    'index.php',
    'news.php',
    'news-details.php',
    'includes/header.php',
    'includes/ads.php',
    'song-details.php',
    'artist-profile-mobile.php',
    'album-details.php',
    'install/install-database.php',
    'admin/includes/footer.php',
    'admin/api/install-update.php',
    '.htaccess'
];

echo "Preparing update package...\n\n";

// Create updates directory structure
foreach ($changed_files as $file) {
    $source_path = $source_dir . DIRECTORY_SEPARATOR . $file;
    $dest_path = $updates_dir . DIRECTORY_SEPARATOR . $file;
    $dest_dir = dirname($dest_path);
    
    if (!file_exists($source_path)) {
        echo "⚠️  File not found: $file\n";
        continue;
    }
    
    // Create destination directory
    if (!is_dir($dest_dir)) {
        mkdir($dest_dir, 0755, true);
        echo "📁 Created directory: $dest_dir\n";
    }
    
    // Copy file
    if (copy($source_path, $dest_path)) {
        echo "✅ Copied: $file\n";
    } else {
        echo "❌ Failed to copy: $file\n";
    }
}

echo "\n✅ Update package prepared in: $updates_dir\n";
echo "📦 Ready to create ZIP file for distribution\n";












