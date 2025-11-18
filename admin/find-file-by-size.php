<?php
// admin/find-file-by-size.php
// AJAX endpoint to find file by size for a specific song

require_once 'auth-check.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$song_id = $input['song_id'] ?? 0;
$target_file_size = $input['file_size'] ?? 0;

if (!$song_id || !$target_file_size) {
    echo json_encode(['success' => false, 'error' => 'Song ID and file size required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $base_dir = realpath(__DIR__ . '/../');
    if ($base_dir === false) {
        echo json_encode(['success' => false, 'error' => 'Could not determine base directory']);
        exit;
    }
    
    // Search directories
    $search_dirs = [
        $base_dir . '/uploads/audio/',
        $base_dir . '/uploads/music/',
        $base_dir . '/music/',
        $base_dir . '/uploads/',
    ];
    
    $found_file = false;
    $found_path = '';
    
    foreach ($search_dirs as $search_dir) {
        if (!is_dir($search_dir)) {
            continue;
        }
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($search_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $actual_size = $file->getSize();
                    $size_diff = abs($actual_size - $target_file_size);
                    
                    // Match if size is within 1KB tolerance
                    if ($size_diff <= 1024) {
                        $ext = strtolower($file->getExtension());
                        $audio_extensions = ['mp3', 'wav', 'flac', 'aac', 'm4a', 'ogg', 'oga', 'webm'];
                        
                        if (in_array($ext, $audio_extensions)) {
                            $full_path = $file->getRealPath();
                            $relative_path = str_replace('\\', '/', str_replace($base_dir . '/', '', $full_path));
                            $relative_path = ltrim($relative_path, '/');
                            
                            // Update database immediately
                            $update_stmt = $conn->prepare("UPDATE songs SET file_path = ? WHERE id = ?");
                            $update_stmt->execute([$relative_path, $song_id]);
                            
                            $found_file = true;
                            $found_path = $relative_path;
                            break 2;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error searching directory $search_dir: " . $e->getMessage());
            continue;
        }
    }
    
    if ($found_file) {
        echo json_encode([
            'success' => true,
            'file_path' => $found_path,
            'message' => 'File found and database updated'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'File not found in common directories'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Find file by size error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>

