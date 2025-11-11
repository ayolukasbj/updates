<?php
/**
 * Helper script to create a ZIP file of the MP3 Tagger plugin for upload to license server
 * 
 * Usage: php create-plugin-zip.php
 * Output: mp3-tagger.zip in the current directory
 */

// Check if ZipArchive is available
if (!class_exists('ZipArchive')) {
    die("Error: ZipArchive class not found. Please enable the 'zip' extension in php.ini\n");
}

// Plugin source directory
$plugin_source = __DIR__ . '/../C:/Users/HYLINK/Desktop/music - Copy/plugins/mp3-tagger';
$plugin_source_alt = 'C:/Users/HYLINK/Desktop/music - Copy/plugins/mp3-tagger';

// Try both paths
if (!is_dir($plugin_source)) {
    $plugin_source = $plugin_source_alt;
}

if (!is_dir($plugin_source)) {
    die("Error: Plugin directory not found at: $plugin_source\nPlease check the path.\n");
}

// Output ZIP file
$zip_file = __DIR__ . '/mp3-tagger.zip';

// Remove existing ZIP if it exists
if (file_exists($zip_file)) {
    unlink($zip_file);
}

// Create ZIP archive
$zip = new ZipArchive();
if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Error: Cannot create ZIP file: $zip_file\n");
}

// Get all files in plugin directory
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_source, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($plugin_source) + 1);
        
        // Add file to ZIP (preserving folder structure)
        $zip->addFile($filePath, 'mp3-tagger/' . $relativePath);
    }
}

$zip->close();

echo "✓ Plugin ZIP created successfully!\n";
echo "  File: $zip_file\n";
echo "  Size: " . number_format(filesize($zip_file) / 1024, 2) . " KB\n\n";
echo "Next steps:\n";
echo "1. Go to your License Server Admin: https://hylinktech.com/server\n";
echo "2. Navigate to: Plugin Store\n";
echo "3. Upload the file: $zip_file\n";
echo "4. Verify the plugin appears in the list with slug: mp3-tagger\n";
echo "5. Then try installing from your platform's Plugin Store\n";

