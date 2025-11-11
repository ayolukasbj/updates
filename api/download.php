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
    
    // Generate filename using ID3 tag template if available
    $filename = null;
    
    // Try to get filename from ID3 tags or use template
    try {
        require_once __DIR__ . '/../includes/settings.php';
        
        // Try to use plugin's AutoTagger if available
        $auto_tagger_loaded = false;
        if (class_exists('PluginLoader')) {
            $active_plugins = PluginLoader::getActivePlugins();
            foreach ($active_plugins as $plugin_file) {
                if (strpos($plugin_file, 'mp3-tagger') !== false) {
                    $plugin_dir = dirname($plugin_file);
                    $auto_tagger_file = $plugin_dir . '/includes/class-auto-tagger.php';
                    if (file_exists($auto_tagger_file)) {
                        require_once $auto_tagger_file;
                        $auto_tagger_loaded = true;
                        break;
                    }
                }
            }
        }
        
        // Get tag templates if AutoTagger is available
        $tag_templates = [];
        $site_name = SettingsManager::getSiteName();
        
        if ($auto_tagger_loaded && class_exists('AutoTagger')) {
            $tag_templates = AutoTagger::getTagTemplates();
        }
        
        // Check if filename template is set and auto-tagging is enabled
        if (!empty($tag_templates) && !empty($tag_templates['filename'])) {
            // Use template to generate filename
            $filename_template = $tag_templates['filename'];
            
            // Get actual filename from the file if it was renamed
            $actual_filename = basename($song['file_path']);
            $file_ext = pathinfo($actual_filename, PATHINFO_EXTENSION);
            
            // Check if the actual filename matches the template pattern
            // If it does, use it; otherwise generate from template
            $title_clean = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $song['title']);
            $artist_clean = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $artist_string);
            $site_clean = preg_replace('/[^a-zA-Z0-9_\-\[\]() ]/', '', $site_name);
            
            // Generate filename from template
            // Default format: songtitle-by-artistes-sitename
            if (empty($filename_template) || $filename_template === '{TITLE} by {ARTIST} [{SITE_NAME}]') {
                // Use the new format: songtitle-by-artistes-sitename
                $generated_filename = strtolower($title_clean) . '-by-' . strtolower($artist_clean) . '-' . strtolower($site_clean);
                $generated_filename = preg_replace('/[^a-zA-Z0-9\-]/', '', $generated_filename);
                $generated_filename = preg_replace('/-+/', '-', $generated_filename);
                $generated_filename = trim($generated_filename, '-');
            } else {
                // Use custom template
            $generated_filename = str_replace(
                ['{TITLE}', '{ARTIST}', '{SITE_NAME}'],
                [$title_clean, $artist_clean, $site_clean],
                $filename_template
            );
            $generated_filename = preg_replace('/[^a-zA-Z0-9_\-\[\]() ]/', '', $generated_filename);
            $generated_filename = trim($generated_filename);
            }
            
            // Use generated filename or actual filename if it looks like it matches the pattern
            if (!empty($generated_filename)) {
                $filename = $generated_filename . '.' . $file_ext;
            } else {
                // Fallback: try to read from ID3 tags
                if (file_exists($audio_file) && strtolower($file_ext) === 'mp3') {
                    try {
                        $tagger = new MP3Tagger($audio_file);
                        $tags = $tagger->readTags();
                        
                        // Try to construct filename from ID3 tags
                        if (!empty($tags['title']) && !empty($tags['artist'])) {
                            $id3_title = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $tags['title']);
                            $id3_artist = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $tags['artist']);
                            if (!empty($id3_title) && !empty($id3_artist)) {
                                $filename = $id3_title . ' by ' . $id3_artist . '.' . $file_ext;
                            }
                        }
                    } catch (Exception $e) {
                        error_log('Error reading ID3 tags for download filename: ' . $e->getMessage());
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Error getting filename template: ' . $e->getMessage());
    }
    
    // Fallback to default filename if template didn't work
    if (empty($filename)) {
        // Use new format: songtitle-by-artistes-sitename
        $title_clean = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $song['title']);
        $artist_clean = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $artist_string);
        $site_clean = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $site_name);
        $filename = strtolower($title_clean) . '-by-' . strtolower($artist_clean) . '-' . strtolower($site_clean);
        $filename = preg_replace('/[^a-zA-Z0-9\-]/', '', $filename);
        $filename = preg_replace('/-+/', '-', $filename);
        $filename = trim($filename, '-') . '.mp3';
    } else {
        // Sanitize the generated filename
        $filename = sanitize_filename($filename);
    }
    
    // Apply site logo as cover art before download (if MP3 and plugin is active)
    $file_ext = strtolower(pathinfo($audio_file, PATHINFO_EXTENSION));
    if ($file_ext === 'mp3') {
        try {
            // Check if MP3 Tagger plugin is active
            $mp3_tagger_loaded = false;
            
            // Load plugin system if not already loaded
            if (!class_exists('PluginLoader')) {
                $plugin_loader_path = __DIR__ . '/../includes/plugin-loader.php';
                if (file_exists($plugin_loader_path)) {
                    require_once $plugin_loader_path;
                }
            }
            
            if (class_exists('PluginLoader')) {
                $active_plugins = PluginLoader::getActivePlugins();
                foreach ($active_plugins as $plugin_file) {
                    if (strpos($plugin_file, 'mp3-tagger') !== false) {
                        $plugin_dir = dirname($plugin_file);
                        $mp3_tagger_file = $plugin_dir . '/includes/class-mp3-tagger.php';
                        $auto_tagger_file = $plugin_dir . '/includes/class-auto-tagger.php';
                        
                        if (file_exists($mp3_tagger_file) && file_exists($auto_tagger_file)) {
                            require_once $mp3_tagger_file;
                            require_once $auto_tagger_file;
                            $mp3_tagger_loaded = true;
                            break;
                        }
                    }
                }
            }
            
            // If plugin is loaded and auto-tagging is enabled, apply site logo
            if ($mp3_tagger_loaded && class_exists('MP3Tagger') && class_exists('AutoTagger')) {
                $tag_templates = AutoTagger::getTagTemplates();
                
                // Only proceed if auto-tagging is enabled (getTagTemplates returns empty if disabled)
                if (!empty($tag_templates)) {
                    // Get site logo path
                    require_once __DIR__ . '/../includes/settings.php';
                    $site_name = SettingsManager::getSiteName();
                    $site_logo = SettingsManager::getSiteLogo();
                    $site_logo_path = null;
                    
                    if (!empty($site_logo)) {
                        $logo_paths = [
                            realpath(__DIR__ . '/../' . ltrim($site_logo, '/')),
                            realpath($site_logo),
                            $site_logo,
                        ];
                        
                        foreach ($logo_paths as $path) {
                            if ($path && file_exists($path)) {
                                $site_logo_path = $path;
                                break;
                            }
                        }
                    }
                    
                    // If site logo exists, apply it as cover art
                    if (!empty($site_logo_path) && file_exists($site_logo_path)) {
                        try {
                            $tagger = new MP3Tagger($audio_file);
                            
                            // Prepare tag data with site logo
                            $tag_data = [
                                'cover_art_path' => $site_logo_path,
                            ];
                            
                            // Optionally update other tags if templates are set
                            if (!empty($tag_templates['title'])) {
                                $tag_data['title'] = str_replace(
                                    ['{TITLE}', '{SITE_NAME}'],
                                    [$song['title'], $site_name],
                                    $tag_templates['title']
                                );
                            }
                            if (!empty($tag_templates['artist'])) {
                                $tag_data['artist'] = str_replace(
                                    ['{ARTIST}', '{SITE_NAME}'],
                                    [$artist_string, $site_name],
                                    $tag_templates['artist']
                                );
                            }
                            if (!empty($tag_templates['album'])) {
                                $tag_data['album'] = str_replace(
                                    ['{SITE_NAME}'],
                                    [$site_name],
                                    $tag_templates['album']
                                );
                            }
                            if (!empty($tag_templates['comment'])) {
                                $tag_data['comment'] = str_replace(
                                    ['{SITE_NAME}'],
                                    [$site_name],
                                    $tag_templates['comment']
                                );
                            }
                            
                            // Write tags (including cover art)
                            $tagger->writeTags($tag_data);
                            error_log("Applied site logo as cover art to song ID: $song_id");
                        } catch (Exception $e) {
                            error_log("Error applying site logo to MP3: " . $e->getMessage());
                            // Continue with download even if tagging fails
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error checking MP3 Tagger plugin: " . $e->getMessage());
            // Continue with download even if plugin check fails
        }
    }
    
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
    
    // Stream file immediately - start download right away
    $handle = fopen($audio_file, 'rb');
    
    // Use larger chunk size for faster streaming
    $chunk_size = 8192; // 8KB chunks for optimal performance
    $bytes_sent = 0;
    
    while (!feof($handle)) {
        $chunk = fread($handle, $chunk_size);
        if ($chunk === false || strlen($chunk) === 0) {
            break;
        }
        
        echo $chunk;
        $bytes_sent += strlen($chunk);
        
        // Flush immediately for faster download start
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
