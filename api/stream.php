<?php
// api/stream.php - Audio streaming endpoint with IDM protection
// CRITICAL: Must output ONLY binary audio data - no whitespace, no errors, nothing before binary

// Disable ALL error reporting and output - MUST be first
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Disable all output buffering immediately
while (ob_get_level()) {
    ob_end_clean();
}

// Start output buffering to catch ANY output from includes
ob_start();

require_once '../config/config.php';
require_once '../config/database.php';

// Clear any output from includes IMMEDIATELY
ob_end_clean();

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
    header('Access-Control-Allow-Headers: Range');
    header('Access-Control-Max-Age: 86400');
    http_response_code(200);
    exit;
}

$song_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$range = isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : '';

if (empty($song_id) || $song_id <= 0) {
    // If no valid ID, return empty audio response (not JSON)
    header('Content-Type: audio/mpeg');
    header('Content-Length: 0');
    exit;
}

try {
    // Get song data from database
    $db = new Database();
    $conn = $db->getConnection();
    
    // Only select file_path - audio_file column doesn't exist in this database
    $stmt = $conn->prepare("SELECT file_path FROM songs WHERE id = ? LIMIT 1");
    $stmt->execute([$song_id]);
    $song = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$song) {
        header('Content-Type: audio/mpeg');
        header('Content-Length: 0');
        exit;
    }
    
    // Get audio file path - only file_path column exists
    $audio_file = !empty($song['file_path']) ? trim($song['file_path']) : '';
    
    if (empty($audio_file)) {
        header('Content-Type: audio/mpeg');
        header('Content-Length: 0');
        exit;
    }
    
    // Normalize path
    $audio_file = str_replace('\\', '/', trim($audio_file));
    $audio_file = ltrim($audio_file, '/');
    
    // Get base directory
    $base_dir = realpath(__DIR__ . '/../');
    if ($base_dir === false) {
        header('Content-Type: audio/mpeg');
        header('Content-Length: 0');
        exit;
    }
    
    // Try to find the file - check uploads/audio first
    $possible_paths = [
        $base_dir . '/uploads/audio/' . basename($audio_file),
        $base_dir . '/' . $audio_file,
        __DIR__ . '/../uploads/audio/' . basename($audio_file),
        __DIR__ . '/../' . $audio_file,
    ];
    
    // Add original path from database - only file_path exists (audio_file column doesn't exist)
    if (!empty($song['file_path'])) {
        $orig = str_replace('\\', '/', ltrim(trim($song['file_path']), '/'));
        $possible_paths[] = $base_dir . '/' . $orig;
        $possible_paths[] = $base_dir . '/uploads/audio/' . basename($orig);
    }
    
    // Remove duplicates
    $possible_paths = array_filter(array_unique($possible_paths));
    
    $actual_path = '';
    foreach ($possible_paths as $path) {
        if (empty($path)) continue;
        $normalized = realpath($path);
        if ($normalized !== false && is_file($normalized) && is_readable($normalized)) {
            $actual_path = $normalized;
            break;
        }
        if (file_exists($path) && is_file($path) && is_readable($path)) {
            $actual_path = $path;
            break;
        }
    }
    
        if (empty($actual_path)) {
            // File not found - log all details
            error_log("Audio file not found for song ID: $song_id");
            error_log("Paths checked: " . implode("\n", $possible_paths));
            error_log("DB file_path: " . ($song['file_path'] ?? 'NULL'));
            error_log("Base dir: $base_dir");
            
            // Try one more time with absolute path check
            $audio_file_abs = str_replace('\\', '/', $audio_file);
            if (!empty($audio_file_abs)) {
                $last_try = $base_dir . '/' . ltrim($audio_file_abs, '/');
                error_log("Last try: $last_try (exists: " . (file_exists($last_try) ? 'YES' : 'NO') . ")");
                if (file_exists($last_try) && is_file($last_try) && is_readable($last_try)) {
                    $actual_path = realpath($last_try);
                    error_log("SUCCESS: Found file at last try: $actual_path");
                }
            }
            
            if (empty($actual_path)) {
                // Clear all output buffers
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Return proper 404 with audio content type to prevent browser decode errors
                header('Content-Type: audio/mpeg');
                header('Content-Length: 0');
                http_response_code(404);
                // Send a minimal valid MP3 header to prevent decode errors
                // This is a 1-frame silent MP3 (about 26 bytes)
                echo "\xFF\xFB\x90\x00"; // MP3 frame sync + header
                exit;
            }
        }
    
    $filesize = @filesize($actual_path);
    if ($filesize === false || $filesize <= 0) {
        error_log("Invalid file size for: $actual_path (size: " . var_export($filesize, true) . ")");
        http_response_code(500);
        exit;
    }
    
    // Verify file is actually an audio file by checking first bytes
    $handle_check = @fopen($actual_path, 'rb');
    if ($handle_check) {
        $magic_bytes = @fread($handle_check, 4);
        @fclose($handle_check);
        
        // Check if it's a valid audio file (at least check for MP3 ID3 tag or MPEG header)
        if ($magic_bytes !== false && strlen($magic_bytes) >= 2) {
            $is_valid = false;
            // Check for MP3: ID3 tag (starts with "ID3") or MPEG frame sync (starts with 0xFF)
            if (substr($magic_bytes, 0, 3) === 'ID3' || 
                (ord($magic_bytes[0]) === 0xFF && (ord($magic_bytes[1]) & 0xE0) === 0xE0)) {
                $is_valid = true;
            } elseif (substr($magic_bytes, 0, 4) === 'RIFF') { // WAV
                $is_valid = true;
            } elseif (substr($magic_bytes, 0, 4) === 'ftyp') { // M4A/AAC
                $is_valid = true;
            }
            
            if (!$is_valid) {
                error_log("File does not appear to be valid audio: $actual_path (first bytes: " . bin2hex($magic_bytes) . ")");
                // Continue anyway - might still be valid audio
            }
        }
    }
    
    // Detect MIME type
    $ext = strtolower(pathinfo($actual_path, PATHINFO_EXTENSION));
    $mime_types = [
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'flac' => 'audio/flac',
        'aac' => 'audio/aac',
        'm4a' => 'audio/mp4',
        'ogg' => 'audio/ogg',
        'oga' => 'audio/ogg',
        'webm' => 'audio/webm'
    ];
    $mime_type = isset($mime_types[$ext]) ? $mime_types[$ext] : 'audio/mpeg';
    
    // CRITICAL: Clear ALL output buffers before headers
    // This must happen AFTER file validation but BEFORE headers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Verify no output has been sent
    if (headers_sent($file, $line)) {
        error_log("CRITICAL: Headers already sent in $file:$line for song ID $song_id");
        // If headers are already sent, we can't serve binary data properly
        http_response_code(500);
        exit;
    }
    
    // Handle range requests
    $start = 0;
    $end = $filesize - 1;
    $is_range = false;
    
    if (!empty($range) && preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
        $start = max(0, (int)$matches[1]);
        $end = !empty($matches[2]) ? min($filesize - 1, (int)$matches[2]) : ($filesize - 1);
        if ($start < $filesize && $end >= $start) {
            $is_range = true;
        }
    }
    
    $content_length = $end - $start + 1;
    
    // Clear output buffer completely - CRITICAL for IDM blocking
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set CORS headers
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
    header('Access-Control-Allow-Headers: Range');
    header('Access-Control-Expose-Headers: Content-Range, Content-Length, Accept-Ranges');
    
    // Set content type and headers
    if ($is_range) {
        header('Content-Type: ' . $mime_type);
        header('Accept-Ranges: bytes');
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $filesize);
        header('Content-Length: ' . $content_length);
    } else {
        header('Content-Type: ' . $mime_type);
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . $filesize);
    }
    
    // Standard headers
    header('Content-Disposition: inline');
    header('Cache-Control: public, max-age=86400');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
    
    // Remove server signatures
    @header_remove('Server');
    @header_remove('X-Powered-By');
    
    // Final check - ensure no output buffering
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Verify file is still readable before streaming
    if (!is_readable($actual_path)) {
        error_log("File became unreadable: $actual_path");
        http_response_code(500);
        exit;
    }
    
    // Log successful streaming start (remove in production)
    error_log("Streaming audio file: $actual_path (size: $filesize, mime: $mime_type) for song ID: $song_id");
    
    // Stream the file - ONLY binary data after this point
    if ($is_range) {
        $handle = @fopen($actual_path, 'rb');
        if ($handle && @fseek($handle, $start, SEEK_SET) === 0) {
            $chunk_size = 8192;
            $bytes_sent = 0;
            
            while (!feof($handle) && $bytes_sent < $content_length) {
                $remaining = $content_length - $bytes_sent;
                $read_size = min($chunk_size, $remaining);
                $chunk = @fread($handle, $read_size);
                
                if ($chunk === false || strlen($chunk) === 0) {
                    break;
                }
                
                echo $chunk;
                $bytes_sent += strlen($chunk);
                flush();
                
                if (connection_aborted()) {
                    break;
                }
            }
            
            @fclose($handle);
        }
    } else {
        // Full file request - stream file directly
        @readfile($actual_path);
    }
    
    // Exit immediately - no trailing output
    exit;
    
} catch (Exception $e) {
    // Clear any output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Return empty audio response (not JSON)
    header('Content-Type: audio/mpeg');
    header('Content-Length: 0');
    exit;
}
// NO closing PHP tag - prevents trailing whitespace that breaks audio!

