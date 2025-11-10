<?php
// api/test-stream.php - Test endpoint to debug streaming issues
header('Content-Type: text/plain');

$song_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

echo "Testing stream endpoint for song ID: $song_id\n\n";

try {
    require_once '../config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    // Only select file_path - audio_file column doesn't exist
    $stmt = $conn->prepare("SELECT id, file_path FROM songs WHERE id = ? LIMIT 1");
    $stmt->execute([$song_id]);
    $song = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$song) {
        echo "ERROR: Song not found in database\n";
        exit;
    }
    
    echo "Song found in database:\n";
    echo "  ID: " . $song['id'] . "\n";
    echo "  file_path: " . ($song['file_path'] ?? 'NULL') . "\n\n";
    
    $audio_file = !empty($song['file_path']) ? trim($song['file_path']) : '';
    
    if (empty($audio_file)) {
        echo "ERROR: No audio file path in database\n";
        exit;
    }
    
    $base_dir = realpath(__DIR__ . '/../');
    echo "Base directory: $base_dir\n\n";
    
    $possible_paths = [
        $base_dir . '/uploads/audio/' . basename($audio_file),
        $base_dir . '/' . $audio_file,
        __DIR__ . '/../uploads/audio/' . basename($audio_file),
        __DIR__ . '/../' . $audio_file,
    ];
    
    echo "Checking paths:\n";
    $found = false;
    foreach ($possible_paths as $path) {
        echo "  Checking: $path\n";
        $normalized = realpath($path);
        if ($normalized !== false && is_file($normalized) && is_readable($normalized)) {
            echo "    ✓ FOUND: $normalized\n";
            echo "    Size: " . filesize($normalized) . " bytes\n";
            $found = true;
            
            // Check first bytes
            $handle = fopen($normalized, 'rb');
            if ($handle) {
                $magic = fread($handle, 4);
                fclose($handle);
                echo "    First 4 bytes (hex): " . bin2hex($magic) . "\n";
                echo "    First 4 bytes (ascii): " . (ctype_print($magic) ? $magic : '[binary]') . "\n";
            }
            break;
        } else {
            echo "    ✗ Not found\n";
        }
    }
    
    if (!$found) {
        echo "\nERROR: File not found in any location\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>

