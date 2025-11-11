<?php
/**
 * Sync Modified Files to Updates Folder
 * Run this script to copy all platform files to the updates folder
 * Usage: php sync-to-updates.php
 */

require_once __DIR__ . '/includes/file-sync.php';

echo "Starting file sync to updates folder...\n";
echo "Target: " . UPDATES_FOLDER . "\n\n";

$start_time = time();

$result = syncDirectoryToUpdates('.');

$duration = time() - $start_time;

echo "\n";
echo "========================================\n";
echo "Sync Complete!\n";
echo "========================================\n";
echo "Files copied: " . $result['copied'] . "\n";
echo "Files skipped: " . $result['skipped'] . "\n";
echo "Duration: " . $duration . " seconds\n";

if (!empty($result['errors'])) {
    echo "\nErrors (" . count($result['errors']) . "):\n";
    foreach (array_slice($result['errors'], 0, 10) as $error) {
        echo "  - " . $error . "\n";
    }
}

echo "\nSync completed successfully!\n";

