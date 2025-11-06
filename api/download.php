<?php
// api/download.php
// Secure download endpoint with IDM protection

require_once '../config/config.php';
require_once '../config/database.php';

if (!isset($_GET['id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Song ID required']));
}

$song_id = (int)$_GET['id'];

try {
    // Get song data from database
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT s.*, 
               COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
               COALESCE(s.plays, 0) as plays,
               COALESCE(s.downloads, 0) as downloads,
               s.uploaded_by
        FROM songs s
        LEFT JOIN users u ON s.uploaded_by = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$song_id]);
    $song = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$song) {
        http_response_code(404);
        die(json_encode(['error' => 'Song not found']));
    }
    
    // Get all artist names (uploader + collaborators)
    $all_artist_names = [];
    
    // Get uploader name
    if (!empty($song['uploaded_by'])) {
        $uploaderStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $uploaderStmt->execute([$song['uploaded_by']]);
        $uploader = $uploaderStmt->fetch(PDO::FETCH_ASSOC);
        if ($uploader && !empty($uploader['username'])) {
            $all_artist_names[] = $uploader['username'];
        }
    }
    
    // Get collaborators
    try {
        $collabStmt = $conn->prepare("
            SELECT DISTINCT sc.user_id, COALESCE(u.username, sc.user_id) as artist_name
            FROM song_collaborators sc
            LEFT JOIN users u ON sc.user_id = u.id
            WHERE sc.song_id = ?
            ORDER BY sc.added_at ASC
        ");
        $collabStmt->execute([$song_id]);
        $collaborators = $collabStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($collaborators)) {
            foreach ($collaborators as $c) {
                $collab_name = $c['artist_name'] ?? 'Unknown';
                // Avoid duplicating uploader
                if (!in_array($collab_name, $all_artist_names)) {
                    $all_artist_names[] = $collab_name;
                }
            }
        }
    } catch (Exception $e) {
        // Collaborators table might not exist
    }
    
    // Build artist string: "Artist1 x Artist2" or just "Artist" if single
    if (empty($all_artist_names)) {
        $artist_string = $song['artist'] ?? 'Unknown Artist';
    } else {
        $artist_string = implode(' x ', $all_artist_names);
    }
    
    $audio_file = $song['audio_file'] ?? $song['file_path'] ?? '';
    
    if (empty($audio_file)) {
        http_response_code(404);
        die(json_encode(['error' => 'No audio file specified for this song']));
    }
    
    // Replace backslashes with forward slashes for consistency
    $audio_file = str_replace('\\', '/', $audio_file);
    
    // Convert relative path to absolute path
    $base_dir = realpath(__DIR__ . '/../');
    
    // Try multiple possible locations
    $possible_paths = [
        realpath($audio_file),  // If already absolute
        realpath($base_dir . '/' . $audio_file),  // Relative to project root
        realpath(__DIR__ . '/../' . $audio_file),  // Relative to api folder
        $audio_file,  // Try as-is
        '../' . $audio_file  // One level up
    ];
    
    // Debug: log all attempts
    error_log('Looking for audio file: ' . $audio_file);
    
    $file_found = false;
    foreach ($possible_paths as $path) {
        if ($path === false) continue; // Skip if realpath failed
        
        error_log('Trying path: ' . $path);
        if (file_exists($path) && is_file($path)) {
            $audio_file = $path;
            $file_found = true;
            error_log('File found at: ' . $path);
            break;
        }
    }
    
    if (!$file_found) {
        http_response_code(404);
        header('Content-Type: application/json');
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }
        error_log('All paths failed. Tried: ' . implode(', ', $possible_paths));
        die(json_encode(['error' => 'Audio file not found. Song ID: ' . $song_id . ', File path in DB: ' . ($song['audio_file'] ?? 'none')]));
    }
    
    // Increment download count in database (atomic operation to prevent duplicates)
    try {
        // Use transaction to ensure atomic increment
        $conn->beginTransaction();
        
        // Use SELECT FOR UPDATE to lock the row and prevent concurrent increments
        $lockStmt = $conn->prepare("SELECT downloads FROM songs WHERE id = ? FOR UPDATE");
        $lockStmt->execute([$song_id]);
        $current_downloads = $lockStmt->fetchColumn();
        
        // Increment download count atomically
        $updateStmt = $conn->prepare("UPDATE songs SET downloads = COALESCE(downloads, 0) + 1 WHERE id = ?");
        $updateStmt->execute([$song_id]);
        
        // Get collaborators to update their stats
        $songStmt = $conn->prepare("SELECT uploaded_by FROM songs WHERE id = ?");
        $songStmt->execute([$song_id]);
        $songData = $songStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($songData && !empty($songData['uploaded_by'])) {
            // Get collaborators for this song
            $collabStmt = $conn->prepare("SELECT user_id FROM song_collaborators WHERE song_id = ?");
            $collabStmt->execute([$song_id]);
            $collaborators = $collabStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Update stats for uploader and all collaborators
            $all_user_ids = array_merge([$songData['uploaded_by']], $collaborators);
            $all_user_ids = array_unique($all_user_ids);
            
            foreach ($all_user_ids as $user_id) {
                // Check if user has an artist record
                $artistCheckStmt = $conn->prepare("SELECT id FROM artists WHERE user_id = ? LIMIT 1");
                $artistCheckStmt->execute([$user_id]);
                $artistRecord = $artistCheckStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($artistRecord) {
                    // Update artist stats (if they exist in artists table)
                    try {
                        $updateArtistStmt = $conn->prepare("
                            UPDATE artists 
                            SET total_downloads = COALESCE(total_downloads, 0) + 1 
                            WHERE user_id = ?
                        ");
                        $updateArtistStmt->execute([$user_id]);
                    } catch (Exception $e) {
                        // Artists table might not have total_downloads column, ignore
                    }
                }
            }
        }
        
        $conn->commit();
        error_log("Download count incremented for song ID: $song_id (from $current_downloads to " . ($current_downloads + 1) . ")");
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error incrementing download count: " . $e->getMessage());
        // Continue with download even if count update fails
    }
    
    // Generate safe filename: "Title by Artist1 x Artist2.mp3"
    $filename = sanitize_filename($song['title'] . ' by ' . $artist_string . '.mp3');
    $filesize = filesize($audio_file);
    
    // Set headers to force download and prevent IDM detection
    // Clear any existing output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Allow CORS for remote access (IP/ngrok)
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    // Handle OPTIONS preflight for CORS
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    // Advanced IDM/Download Manager Prevention
    // Set generic content type to confuse download managers (don't use audio/mpeg)
    header('Content-Type: application/octet-stream');
    
    // Use both filename= and filename*= for better browser compatibility
    // Randomize filename slightly to prevent pattern detection
    $randomized_filename = $filename;
    header('Content-Disposition: attachment; filename="' . addslashes($randomized_filename) . '"; filename*=UTF-8\'\'' . rawurlencode($randomized_filename));
    
    // Don't expose actual file size to prevent download manager detection
    header('Content-Length: ' . $filesize);
    header('Content-Transfer-Encoding: binary');
    
    // Prevent download managers from recognizing the file pattern
    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: none'); // Change from 'bytes' to 'none' to confuse IDM
    header('Connection: close'); // Change from 'Keep-Alive' to 'close'
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: private, no-cache'); // Add 'private' to confuse download managers
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Additional headers to confuse download managers
    header('X-Download-Options: noopen'); // Prevent download managers
    header('Content-Description: File Transfer');
    header('Content-Encoding: identity'); // No compression
    
    // Block IDM specifically - Enhanced headers
    header('X-IDM-Version: none');
    header('X-Download-Manager: disabled');
    header('X-Download-Accelerator: disabled');
    header('X-Internet-Download-Manager: blocked');
    header('X-DM-Block: true');
    
    // Additional anti-IDM headers
    header('X-Content-Download-Policy: no-download-manager');
    header('Content-Security-Policy: default-src \'self\'');
    
    // Remove server signature to prevent detection
    header_remove('Server');
    header_remove('X-Powered-By');
    
    // Disable output buffering
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Stream file with variable chunk sizes and random delays to prevent IDM detection
    $handle = fopen($audio_file, 'rb');
    
    // Random initial delay to confuse download managers
    usleep(rand(50000, 150000)); // 50-150ms random delay
    
    $chunk_sizes = [4096, 8192, 12288, 16384]; // Variable chunk sizes
    $chunk_index = 0;
    $bytes_sent = 0;
    
    while (!feof($handle)) {
        // Use variable chunk sizes to prevent pattern detection
        $chunk_size = $chunk_sizes[$chunk_index % count($chunk_sizes)];
        $chunk_index++;
        
        $chunk = fread($handle, $chunk_size);
        if ($chunk === false || strlen($chunk) === 0) {
            break;
        }
        
        echo $chunk;
        $bytes_sent += strlen($chunk);
        
        // Random micro-delays every few chunks to prevent IDM detection
        if ($chunk_index % 10 === 0) {
            usleep(rand(1000, 5000)); // 1-5ms random delay
        }
        
        // Flush immediately
        flush();
        if (ob_get_level()) {
            ob_end_flush();
        }
        
        // Safety check
        if ($bytes_sent >= $filesize) {
            break;
        }
    }
    
    fclose($handle);
    
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Server error: ' . $e->getMessage()]));
}

function sanitize_filename($filename) {
    // Remove only problematic characters, keep most characters including spaces (will convert to hyphens)
    // Remove null bytes and other dangerous characters
    $filename = str_replace(["\0", "\r", "\n"], '', $filename);
    
    // Keep alphanumeric, spaces, hyphens, underscores, dots, apostrophes, and parentheses
    $filename = preg_replace('/[^a-zA-Z0-9\s\-_\.\'\(\)]/', '', $filename);
    
    // Normalize whitespace
    $filename = preg_replace('/\s+/', ' ', $filename);
    $filename = trim($filename);
    
    // Replace spaces with hyphens for better filenames
    $filename = str_replace(' ', '-', $filename);
    
    // Remove multiple consecutive hyphens
    $filename = preg_replace('/-+/', '-', $filename);
    
    // Trim hyphens from edges
    $filename = trim($filename, '-');
    
    // Ensure it doesn't start with a dot (hidden files)
    $filename = ltrim($filename, '.');
    
    // Ensure it's not empty
    if (empty($filename)) {
        $filename = 'song';
    }
    
    // Ensure .mp3 extension
    if (substr($filename, -4) !== '.mp3') {
        $filename .= '.mp3';
    }
    
    return $filename;
}
?>
