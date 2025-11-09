<?php
// song-details.php - Song details page with full player
// Error reporting - only log errors, don't display in production
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
}
ini_set('log_errors', 1);

// Set error handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("FATAL ERROR in song-details.php: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        // Output basic error page instead of WSOD
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
        echo '<h1>Error Loading Page</h1>';
        echo '<p>Please check the error logs for details.</p>';
        echo '<p><a href="index.php">Go to Homepage</a></p>';
        echo '</body></html>';
    }
});

require_once 'config/config.php';
require_once 'config/database.php';

// Check if required files exist
if (!file_exists('classes/Song.php')) {
    error_log("FATAL: classes/Song.php not found");
    die("Required file missing. Please check file permissions.");
}
if (!file_exists('classes/Artist.php')) {
    error_log("FATAL: classes/Artist.php not found");
    die("Required file missing. Please check file permissions.");
}
if (!file_exists('includes/song-storage.php')) {
    error_log("FATAL: includes/song-storage.php not found");
    die("Required file missing. Please check file permissions.");
}

require_once 'classes/Song.php';
require_once 'classes/Artist.php';
require_once 'includes/song-storage.php';

// Load theme settings
if (file_exists(__DIR__ . '/includes/theme-loader.php')) {
    require_once __DIR__ . '/includes/theme-loader.php';
}

// Start session if not started (needed for is_logged_in)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$isLoggedIn = function_exists('is_logged_in') ? is_logged_in() : false;

// Helper function to create URL-friendly slug
function createSongSlug($title, $artist) {
    $slug = $title . ' by ' . $artist;
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9\s]+/', '', $slug);
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

// Get song ID or slug from URL
$songId = null;
$slug = $_GET['slug'] ?? null;

if ($slug) {
    // Extract ID from slug format: "title-by-artist-name" or use slug to find song
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Try to match slug pattern - slug is in format "title-by-artist"
        // We'll search by title and artist (more precise matching)
        $slugParts = explode('-by-', $slug, 2);
        if (count($slugParts) == 2) {
            $titleSlug = str_replace('-', ' ', $slugParts[0]);
            $artistSlug = str_replace('-', ' ', $slugParts[1]);
            
            // First try exact match (more precise) - match by title and uploader username
            // This works for both single songs and collaboration songs (we use uploader for slug)
            $stmt = $conn->prepare("
                SELECT s.* FROM songs s
                LEFT JOIN users u ON s.uploaded_by = u.id
                WHERE LOWER(TRIM(s.title)) = LOWER(TRIM(?))
                AND (LOWER(TRIM(s.artist)) = LOWER(TRIM(?)) OR LOWER(TRIM(u.username)) = LOWER(TRIM(?)))
                AND (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                ORDER BY s.plays DESC
                LIMIT 1
            ");
            $stmt->execute([$titleSlug, $artistSlug, $artistSlug]);
            $songData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If exact match fails, try LIKE match (fallback) - still match by uploader
            if (!$songData) {
                $stmt = $conn->prepare("
                    SELECT s.* FROM songs s
                    LEFT JOIN users u ON s.uploaded_by = u.id
                    WHERE LOWER(s.title) LIKE ? 
                    AND (LOWER(s.artist) LIKE ? OR LOWER(u.username) LIKE ?)
                    AND (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                    ORDER BY s.plays DESC
                    LIMIT 1
                ");
                $titleSearch = '%' . $titleSlug . '%';
                $artistSearch = '%' . $artistSlug . '%';
                $stmt->execute([$titleSearch, $artistSearch, $artistSearch]);
                $songData = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // If still no match, try matching just by title (most permissive)
            // This is a last resort - find any song with matching title
            if (!$songData) {
                $stmt = $conn->prepare("
                    SELECT s.* FROM songs s
                    WHERE LOWER(TRIM(s.title)) = LOWER(TRIM(?))
                    AND (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                    ORDER BY s.plays DESC
                    LIMIT 1
                ");
                $stmt->execute([$titleSlug]);
                $songData = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            if ($songData) {
                $songId = $songData['id'];
            }
        }
    } catch (Exception $e) {
        error_log("Error parsing slug: " . $e->getMessage());
    }
} else {
    $songId = $_GET['id'] ?? null;
}

// Don't redirect immediately - try to find song first
// Only redirect if we truly can't find anything after trying all methods
if (empty($slug) && empty($songId)) {
    // No slug or ID provided - redirect to homepage
    header('Location: index.php');
    exit;
}

$song = null;
$artist_data = null;
$use_database = false;

// Try to get from database first
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if Song class exists and is callable
    if (!class_exists('Song')) {
        throw new Exception('Song class not found');
    }
    
    $song_model = new Song($conn);
    
    if (!method_exists($song_model, 'getSongById')) {
        throw new Exception('getSongById method not found in Song class');
    }
    
    $song = $song_model->getSongById($songId);
    if ($song) {
        $use_database = true;
        
        // Get artist details from users table using uploaded_by field
        $all_artists = []; // For collaboration support
        $is_collaboration = false;
        $artist_data = null; // Will only be used if NOT a collaboration
        $mapped_collaborators = []; // Initialize to avoid undefined variable errors
        
        // FIRST: Check if song is a collaboration - ONLY check database (song_collaborators table)
        // This determines whether we show uploader or collaborators
        try {
            if (!empty($song['id'])) {
                // Get collaborators from database only
                // Check which columns exist in users table
                $colCheck = $conn->query("SHOW COLUMNS FROM users");
                $columns = $colCheck->fetchAll(PDO::FETCH_COLUMN);
                
                // Build query based on available columns
                $selectCols = ["sc.user_id", "u.username", "u.avatar"];
                if (in_array('is_verified', $columns)) {
                    $selectCols[] = "u.is_verified";
                } else if (in_array('email_verified', $columns)) {
                    $selectCols[] = "u.email_verified as is_verified";
                }
                if (in_array('bio', $columns)) {
                    $selectCols[] = "u.bio";
                }
                
                $mapStmt = $conn->prepare("
                    SELECT " . implode(', ', $selectCols) . "
                    FROM song_collaborators sc
                    LEFT JOIN users u ON u.id = sc.user_id
                    WHERE sc.song_id = ?
                    ORDER BY sc.added_at ASC
                ");
                $mapStmt->execute([$song['id']]);
                $mapped_collaborators = $mapStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // If we have any collaborators, it's a collaboration
                if (!empty($mapped_collaborators) && count($mapped_collaborators) > 0) {
                    $is_collaboration = true;
                }
            }
        } catch (Exception $e) {
            error_log('Error checking collaboration: ' . $e->getMessage());
            $is_collaboration = false;
            $mapped_collaborators = [];
        }
        
        // LOGIC: If song has collaborators, show BOTH uploader AND collaborators.
        // If no collaborators, show ONLY uploader.
        
        // Always add uploader first if available
        if (!empty($song['uploaded_by'])) {
            
            // Get uploader stats
            $colCheck = $conn->query("SHOW COLUMNS FROM users");
            $columns = $colCheck->fetchAll(PDO::FETCH_COLUMN);
            
            $verifiedCol = '0 as is_verified'; // Default
            if (in_array('is_verified', $columns)) {
                $verifiedCol = 'u.is_verified';
            } else if (in_array('email_verified', $columns)) {
                $verifiedCol = 'u.email_verified as is_verified';
            }
            
            $stmt = $conn->prepare("
                SELECT u.*,
                       u.username as artist_name,
                       $verifiedCol,
                       COALESCE((
                           SELECT COUNT(DISTINCT s.id)
                           FROM songs s
                           WHERE s.uploaded_by = u.id
                              OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                       ), 0) as total_songs,
                       COALESCE((
                           SELECT SUM(s.plays)
                           FROM songs s
                           WHERE s.uploaded_by = u.id
                              OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                       ), 0) as total_plays,
                       COALESCE((
                           SELECT SUM(s.downloads)
                           FROM songs s
                           WHERE s.uploaded_by = u.id
                              OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                       ), 0) as total_downloads
                FROM users u
                WHERE u.id = ?
            ");
            $stmt->execute([$song['uploaded_by']]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data && !empty($user_data['username'])) {
                $uploader_avatar = !empty($user_data['avatar']) ? trim($user_data['avatar']) : null;
                
                $uploader_artist = [
                    'id' => (int)$user_data['id'],
                    'name' => ucwords(strtolower(trim($user_data['username']))),
                    'avatar' => $uploader_avatar,
                    'verified' => (int)($user_data['is_verified'] ?? ($user_data['email_verified'] ?? 0)),
                    'bio' => $user_data['bio'] ?? '',
                    'total_songs' => (int)($user_data['total_songs'] ?? 0),
                    'total_plays' => (int)($user_data['total_plays'] ?? 0),
                    'total_downloads' => (int)($user_data['total_downloads'] ?? 0)
                ];
                $all_artists[] = $uploader_artist;
            }
        }
        
        // Then add collaborators if they exist
        if ($is_collaboration && !empty($mapped_collaborators)) {
            // SONG HAS COLLABORATORS: Add collaborators to the list (uploader already added above)
            
            // Process each collaborator directly
            // Track IDs already in all_artists (to avoid duplicates with uploader)
            $seen_ids = array_map(function($a) { return $a['id']; }, $all_artists);
            
            foreach ($mapped_collaborators as $index => $mc) {
                $collab_id = !empty($mc['user_id']) ? (int)$mc['user_id'] : 0;
                
                // Skip if already added (e.g., uploader added themselves as collaborator)
                if ($collab_id > 0 && !in_array($collab_id, $seen_ids)) {
                    $seen_ids[] = $collab_id;
                    
                    // Get full user data with stats in ONE query
                    // Check if is_verified or email_verified column exists
                    $colCheck = $conn->query("SHOW COLUMNS FROM users");
                    $columns = $colCheck->fetchAll(PDO::FETCH_COLUMN);
                    
                    $verifiedCol = '0 as is_verified'; // Default
                    if (in_array('is_verified', $columns)) {
                        $verifiedCol = 'u.is_verified';
                    } else if (in_array('email_verified', $columns)) {
                        $verifiedCol = 'u.email_verified as is_verified';
                    }
                    
                    $collabStmt = $conn->prepare("
                        SELECT u.*,
                               u.username as artist_name,
                               $verifiedCol,
                               COALESCE((
                                   SELECT COUNT(DISTINCT s.id)
                                   FROM songs s
                                   WHERE s.uploaded_by = u.id
                                      OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                               ), 0) as total_songs,
                               COALESCE((
                                   SELECT SUM(s.plays)
                                   FROM songs s
                                   WHERE s.uploaded_by = u.id
                                      OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                               ), 0) as total_plays,
                               COALESCE((
                                   SELECT SUM(s.downloads)
                                   FROM songs s
                                   WHERE s.uploaded_by = u.id
                                      OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                               ), 0) as total_downloads
                        FROM users u
                        WHERE u.id = ?
                    ");
                    $collabStmt->execute([$collab_id]);
                    $collab_user = $collabStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($collab_user && !empty($collab_user['username'])) {
                        $collab_artist = [
                            'id' => $collab_id,
                            'name' => ucwords(strtolower(trim($collab_user['username']))),
                            'avatar' => !empty($collab_user['avatar']) ? trim($collab_user['avatar']) : null,
                            'verified' => (int)($collab_user['is_verified'] ?? ($collab_user['email_verified'] ?? 0)),
                            'bio' => $collab_user['bio'] ?? '',
                            'total_songs' => (int)($collab_user['total_songs'] ?? 0),
                            'total_plays' => (int)($collab_user['total_plays'] ?? 0),
                            'total_downloads' => (int)($collab_user['total_downloads'] ?? 0)
                        ];
                        $all_artists[] = $collab_artist;
                    }
                }
            }
        }
        
        // Remove duplicates by ID before final summary - more robust check
        if (!empty($all_artists)) {
            $original_count = count($all_artists);
            $unique_artists = [];
            $seen_ids = [];
            $seen_names = [];
            
            foreach ($all_artists as $artist) {
                $artist_id = !empty($artist['id']) ? (int)$artist['id'] : 0;
                $artist_name = !empty($artist['name']) ? strtolower(trim($artist['name'])) : '';
                
                // Skip if already seen by ID
                if ($artist_id > 0 && in_array($artist_id, $seen_ids)) {
                    continue;
                }
                
                // Skip if already seen by name (for artists without ID)
                if ($artist_id == 0 && !empty($artist_name) && in_array($artist_name, $seen_names)) {
                    continue;
                }
                
                // Add to seen lists
                if ($artist_id > 0) {
                    $seen_ids[] = $artist_id;
                }
                if (!empty($artist_name)) {
                    $seen_names[] = $artist_name;
                }
                
                $unique_artists[] = $artist;
            }
            $all_artists = $unique_artists;
        }
        
        // Make sure all_artists is available globally and accessible in display section
        // This ensures the variable persists outside the try block
        $GLOBALS['all_artists'] = $all_artists;
        $GLOBALS['is_collaboration'] = $is_collaboration;
        $GLOBALS['mapped_collaborators'] = $mapped_collaborators;

        // Create a display string for all artists
        $all_artist_names = array_map(function($a) { return $a['name']; }, $all_artists);
        
        // Priority: 1) Database artist names, 2) Song artist field, 3) Unknown Artist
        if (!empty($all_artist_names)) {
            $display_artist_name = implode(' x ', $all_artist_names);
        } else if (!empty($song['artist'])) {
            $display_artist_name = $song['artist'];
        } else {
            $display_artist_name = 'Unknown Artist';
        }
        
        // Remove duplicates by ID before final summary - more robust check
        if (!empty($all_artists)) {
            $original_count = count($all_artists);
            $unique_artists = [];
            $seen_ids = [];
            $seen_names = []; // Also check by name as fallback
            foreach ($all_artists as $artist) {
                $artist_id = !empty($artist['id']) ? (int)$artist['id'] : 0;
                $artist_name = !empty($artist['name']) ? strtolower(trim($artist['name'])) : '';
                
                // Skip if already seen by ID
                    if ($artist_id > 0 && in_array($artist_id, $seen_ids)) {
                        continue;
                    }
                    
                    // Skip if already seen by name (for artists without ID)
                    if ($artist_id == 0 && !empty($artist_name) && in_array($artist_name, $seen_names)) {
                        continue;
                    }
                
                // Add to seen lists
                if ($artist_id > 0) {
                    $seen_ids[] = $artist_id;
                }
                if (!empty($artist_name)) {
                    $seen_names[] = $artist_name;
                }
                
                $unique_artists[] = $artist;
            }
            $all_artists = $unique_artists;
        }
        
        // Make sure all_artists is available globally and accessible in display section
        // This ensures the variable persists outside the try block
        $GLOBALS['all_artists'] = $all_artists;
        $GLOBALS['is_collaboration'] = $is_collaboration;
        $GLOBALS['mapped_collaborators'] = $mapped_collaborators;

        // Create a display string for all artists
        $all_artist_names = array_map(function($a) { return $a['name']; }, $all_artists);
        
        // Priority: 1) Database artist names, 2) Song artist field, 3) Unknown Artist
        if (!empty($all_artist_names)) {
            $display_artist_name = implode(' x ', $all_artist_names);
        } else if (!empty($song['artist'])) {
            $display_artist_name = $song['artist'];
        } else {
            $display_artist_name = 'Unknown Artist';
        }
    }
} catch (Exception $e) {
    error_log("Song details error: " . $e->getMessage());
    error_log("Song details error trace: " . $e->getTraceAsString());
    $use_database = false;
    // Don't exit - allow fallback to file-based system if available
}

// CRITICAL: Check if song was found FIRST before accessing any song properties
if (empty($song) || !is_array($song) || !isset($song['id'])) {
    error_log("Song not found for ID: " . ($songId ?? 'null') . ", Slug: " . ($slug ?? 'null'));
    // Log all available data for debugging
    if (isset($song)) {
        error_log("Song variable exists but invalid: " . print_r($song, true));
    }
    // Show 404 page instead of redirecting to prevent loops
    http_response_code(404);
    include '404.php';
    exit;
}

// REDIRECT: If accessed by ID (not slug), redirect to slug URL for SEO
if (!empty($songId) && empty($slug)) {
    // Generate slug from song title and artist
    $song_title = $song['title'] ?? '';
    $song_artist = 'Unknown Artist';
    
    // Get artist name - try multiple sources
    if (!empty($song['artist'])) {
        $song_artist = $song['artist'];
    } elseif (!empty($all_artists) && is_array($all_artists) && count($all_artists) > 0) {
        // Use first artist from all_artists array
        $song_artist = $all_artists[0]['name'] ?? $all_artists[0]['username'] ?? 'Unknown Artist';
    } elseif (!empty($song['uploaded_by'])) {
        // Try to get uploader username
        try {
            $db = new Database();
            $conn = $db->getConnection();
            if ($conn) {
                $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                $user_stmt->execute([$song['uploaded_by']]);
                $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
                if ($user_data && !empty($user_data['username'])) {
                    $song_artist = $user_data['username'];
                }
            }
        } catch (Exception $e) {
            // Keep default
        }
    }
    
    // Generate slug
    $titleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song_title));
    $titleSlug = preg_replace('/\s+/', '-', trim($titleSlug));
    $artistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song_artist));
    $artistSlug = preg_replace('/\s+/', '-', trim($artistSlug));
    $generated_slug = $titleSlug . '-by-' . $artistSlug;
    
    // Redirect to slug URL (301 permanent redirect for SEO)
    $redirect_url = SITE_URL . '/song/' . rawurlencode($generated_slug);
    if (!headers_sent()) {
        header('Location: ' . $redirect_url, true, 301);
        exit;
    } else {
        echo '<script>window.location.replace("' . htmlspecialchars($redirect_url) . '");</script>';
        exit;
    }
}

// Initialize variables if not set - ONLY after song is confirmed to exist
// CRITICAL: Restore all_artists and collaboration data from GLOBALS if they were set there
if (isset($GLOBALS['all_artists'])) {
    $all_artists = $GLOBALS['all_artists'];
} else if (!isset($all_artists)) {
    $all_artists = [];
}

if (isset($GLOBALS['is_collaboration'])) {
    $is_collaboration = $GLOBALS['is_collaboration'];
} else if (!isset($is_collaboration)) {
    $is_collaboration = false;
}

if (isset($GLOBALS['mapped_collaborators'])) {
    $mapped_collaborators = $GLOBALS['mapped_collaborators'];
} else if (!isset($mapped_collaborators)) {
    $mapped_collaborators = [];
}

if (!isset($artist_data)) {
    $artist_data = null;
}

// Build display_artist_name - PRIORITY ORDER:
// 1. From all_artists (for collaborations)
// 2. Already resolved artist_data name
// 3. Song's artist field from database
// 4. Lookup uploader's username if uploaded_by exists
// 5. Unknown Artist

// Priority 1: From all_artists (for collaborations)
if (isset($all_artists) && !empty($all_artists) && count($all_artists) > 0) {
    $final_artist_names = array_map(function($a) { 
        return isset($a['name']) && !empty($a['name']) ? $a['name'] : ''; 
    }, $all_artists);
    $final_artist_names = array_filter($final_artist_names); // Remove empty values
    if (!empty($final_artist_names)) {
        $display_artist_name = implode(' x ', $final_artist_names);
    }
}

// Only do additional lookups if display_artist_name is still not set or Unknown Artist
if (!isset($display_artist_name) || empty($display_artist_name) || strcasecmp($display_artist_name, 'Unknown Artist') === 0) {
    // Try 1: Use already resolved artist_data
    if (isset($artist_data) && is_array($artist_data) && !empty($artist_data['name'])) {
        $display_artist_name = $artist_data['name'];
    }
    // Try 2: Use song's artist field (already checked above, but keep for consistency)
    else if (!empty($song['artist'])) {
        $display_artist_name = $song['artist'];
    }
    // Try 3: Lookup uploader's username
    else if (!empty($song['uploaded_by'])) {
        try {
            if (!isset($conn)) {
                $db = new Database();
                $conn = $db->getConnection();
            }
            $stmt = $conn->prepare("
                SELECT id, 
                       username, 
                       avatar, is_verified 
                FROM users WHERE id = ?
            ");
            $stmt->execute([$song['uploaded_by']]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u && !empty($u['username'])) {
                $display_artist_name = ucwords(strtolower(trim($u['username'])));
                // Ensure artist_data is set with uploader info
                if (empty($artist_data) || !is_array($artist_data) || empty($artist_data['name'])) {
                    if (!isset($artist_data)) {
                        $artist_data = [];
                    }
                    $artist_data = [
                        'id' => $u['id'],
                        'name' => $display_artist_name,
                        'avatar' => $u['avatar'] ?? 'assets/images/default-avatar.svg',
                        'verified' => $u['is_verified'] ?? 0,
                        'total_songs' => 0,
                        'total_plays' => 0,
                        'total_downloads' => 0
                    ];
                    // Add to all_artists if empty
                    if (empty($all_artists)) {
                        $all_artists[] = $artist_data;
                    }
                }
                
                // If we have all_artists, rebuild display_artist_name from it to include collaborators
                if (!empty($all_artists) && count($all_artists) > 1) {
                    $final_names = array_map(function($a) { 
                        return isset($a['name']) && !empty($a['name']) ? $a['name'] : ''; 
                    }, $all_artists);
                    $final_names = array_filter($final_names);
                    if (!empty($final_names)) {
                        $display_artist_name = implode(' x ', $final_names);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error getting uploader info: " . $e->getMessage());
        }
    }
}

// Final fallback - ensure display_artist_name is always set
if (!isset($display_artist_name) || empty($display_artist_name)) {
    if (!empty($song['artist'])) {
        $display_artist_name = $song['artist'];
    } else {
        $display_artist_name = 'Unknown Artist';
    }
}

// FINAL REBUILD: Always rebuild display_artist_name from all_artists RIGHT BEFORE OUTPUT
// This ensures collaboration info is included
if (isset($all_artists) && is_array($all_artists) && count($all_artists) > 0) {
    $final_rebuild_names = [];
    foreach ($all_artists as $artist) {
        if (isset($artist['name']) && !empty($artist['name'])) {
            $final_rebuild_names[] = trim($artist['name']);
        }
    }
    if (!empty($final_rebuild_names)) {
        $display_artist_name = implode(' x ', $final_rebuild_names);
    }
}

// Get related songs (same artist) - fetch from database with proper structure
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get all artist IDs (uploader + collaborators)
    $artistIds = [];
    if (!empty($song['uploaded_by'])) {
        $artistIds[] = (int)$song['uploaded_by'];
    }
    
    // Get collaborators
    if (!empty($song['id'])) {
        $collabStmt = $conn->prepare("SELECT DISTINCT user_id FROM song_collaborators WHERE song_id = ?");
        $collabStmt->execute([$song['id']]);
        $collaborators = $collabStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($collaborators as $collabId) {
            if (!in_array((int)$collabId, $artistIds)) {
                $artistIds[] = (int)$collabId;
            }
        }
    }
    
    if (!empty($artistIds)) {
        // Use the exact same pattern as artist-profile.php - exact duplicate prevention
        $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
        
        // First: Get unique song IDs using DISTINCT - this ensures no duplicates even for collaboration songs
        $uniqueIdsStmt = $conn->prepare("
            SELECT DISTINCT s.id
            FROM songs s
            LEFT JOIN song_collaborators sc ON sc.song_id = s.id
            WHERE (s.uploaded_by IN ($placeholders) OR sc.user_id IN ($placeholders))
            AND s.id != ?
            AND (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
            ORDER BY s.plays DESC, s.downloads DESC
            LIMIT 50
        ");
        $params = array_merge($artistIds, $artistIds, [$song['id']]);
        $uniqueIdsStmt->execute($params);
        $uniqueSongIds = $uniqueIdsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Remove any duplicate IDs from the array (extra safety)
        $uniqueSongIds = array_values(array_unique(array_map('intval', $uniqueSongIds)));
        
        // Remove current song ID if it somehow got through
        $uniqueSongIds = array_filter($uniqueSongIds, function($id) use ($song) {
            return (int)$id !== (int)$song['id'];
        });
        
        // Now fetch full song data for unique IDs only - one query per song ID ensures no duplicates
        if (!empty($uniqueSongIds)) {
            $songPlaceholders = implode(',', array_fill(0, count($uniqueSongIds), '?'));
            $relatedStmt = $conn->prepare("
                SELECT s.id, s.*, 
                       COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                       COALESCE(s.is_collaboration, 0) as is_collaboration,
                       u.username as artist_name,
                       s.uploaded_by,
                       s.cover_art
                FROM songs s
                LEFT JOIN users u ON s.uploaded_by = u.id
                WHERE s.id IN ($songPlaceholders)
                ORDER BY s.plays DESC, s.downloads DESC
            ");
            $relatedStmt->execute($uniqueSongIds);
            $relatedSongs = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $relatedSongs = [];
        }
        
        // Final duplicate removal by ID - use associative array for O(1) lookup
        $seenIds = [];
        $uniqueRelatedSongs = [];
        foreach ($relatedSongs as $relatedSong) {
            // Skip current song
            $songId = (int)$relatedSong['id'];
            if ($songId === (int)$song['id']) {
                continue;
            }
            // Use associative array key for fast duplicate check
            if (!isset($seenIds[$songId])) {
                $seenIds[$songId] = true;
                $uniqueRelatedSongs[] = $relatedSong;
            }
        }
        $relatedSongs = $uniqueRelatedSongs;
        
        // Get collaborators for each related song and prepare slug-friendly artist name
        foreach ($relatedSongs as &$relatedSong) {
            // Get collaborators for this song
            $collabStmt = $conn->prepare("
                SELECT u.username, 
                       sc.user_id
                FROM song_collaborators sc
                LEFT JOIN users u ON sc.user_id = u.id
                WHERE sc.song_id = ?
                ORDER BY sc.added_at ASC
            ");
            $collabStmt->execute([$relatedSong['id']]);
            $collaborators = $collabStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Build artist name with collaborators
            $artistNames = [];
            // Always use the uploader as the primary artist for slug generation
            $primaryArtist = $relatedSong['artist_name'] ?? $relatedSong['artist'] ?? 'Unknown Artist';
            if (!empty($primaryArtist)) {
                $artistNames[] = $primaryArtist;
            }
            foreach ($collaborators as $collab) {
                if (!empty($collab['username']) && !in_array($collab['username'], $artistNames)) {
                    $artistNames[] = $collab['username'];
                }
            }
            
            // Format artist display: "Main Artist ft Collaborator1, Collaborator2" or "Main Artist x Collaborator1"
            if (count($artistNames) > 1) {
                $relatedSong['display_artist'] = $artistNames[0] . ' ft ' . implode(', ', array_slice($artistNames, 1));
            } else {
                $relatedSong['display_artist'] = !empty($artistNames[0]) ? $artistNames[0] : ($relatedSong['artist'] ?? 'Unknown Artist');
            }
            
            // For slug generation, ALWAYS use the primary uploader username (artist_name)
            // This is critical for collaboration songs - we use the uploader, not collaborators
            // The slug parsing expects the primary uploader username
            $relatedSong['slug_artist'] = $primaryArtist;
            
            // Ensure slug_artist is not empty
            if (empty($relatedSong['slug_artist']) || $relatedSong['slug_artist'] === 'Unknown Artist') {
                // Fallback: try to get uploader username directly
                if (!empty($relatedSong['uploaded_by'])) {
                    try {
                        $uploaderStmt = $conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
                        $uploaderStmt->execute([$relatedSong['uploaded_by']]);
                        $uploader = $uploaderStmt->fetch(PDO::FETCH_ASSOC);
                        if ($uploader && !empty($uploader['username'])) {
                            $relatedSong['slug_artist'] = $uploader['username'];
                        }
                    } catch (Exception $e) {
                        // Ignore
                    }
                }
            }
        }
    } else {
        $relatedSongs = [];
    }
} catch (Exception $e) {
    error_log("Error fetching related songs: " . $e->getMessage());
    // Fallback to empty array
    $relatedSongs = [];
}

// Debug: Check audio file
$audioFile = !empty($song['audio_file']) ? $song['audio_file'] : (!empty($song['file_path']) ? $song['file_path'] : 'demo-audio.mp3');
if (!file_exists($audioFile) && strpos($audioFile, 'http') !== 0) {
    error_log("Audio file not found: " . $audioFile);
}

// Note: asset_path() function is defined in includes/header.php, which will be included later
// If we need it before header is included, we can use a wrapper
if (!function_exists('asset_path')) {
    function asset_path($path) {
        if (empty($path)) return '';
        // If already absolute URL, return as is (but upgrade HTTP to HTTPS if needed)
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            // If we're on HTTPS, upgrade HTTP URLs to HTTPS
            $isHttps = false;
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                $isHttps = true;
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                $isHttps = true;
            } elseif (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
                $isHttps = true;
            } elseif (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') {
                $isHttps = true;
            }
            
            if ($isHttps && strpos($path, 'http://') === 0) {
                return str_replace('http://', 'https://', $path);
            }
            return $path;
        }
        
        // Get base URL - properly detect HTTPS for ngrok/proxy
        $protocol = 'http://';
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $protocol = 'https://';
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $protocol = 'https://';
        } elseif (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            $protocol = 'https://';
        } elseif (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') {
            $protocol = 'https://';
        }
        
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . $host;
        
        // If starts with /, make it absolute URL
        if (strpos($path, '/') === 0) {
            return $baseUrl . $path;
        }
        // Otherwise, make it absolute using base path
        $base_path = defined('BASE_PATH') ? BASE_PATH : '/';
        return $baseUrl . $base_path . ltrim($path, '/');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    // Get current request URL for base tag (works with IP and ngrok)
    // Properly detect HTTPS - check multiple indicators for ngrok/proxy
    $protocol = 'http://';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $protocol = 'https://';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = 'https://';
    } elseif (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
        $protocol = 'https://';
    } elseif (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') {
        $protocol = 'https://';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_path = defined('BASE_PATH') ? BASE_PATH : '/';
    $currentBaseUrl = $protocol . $host . $base_path;
    ?>
    <base href="<?php echo htmlspecialchars($currentBaseUrl); ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($song['title']); ?> - <?php echo htmlspecialchars($display_artist_name ?? $song['artist']); ?> | <?php echo SITE_NAME; ?></title>
    
    <?php
    // Social sharing meta tags
    $shareSlug = createSongSlug($song['title'], $display_artist_name ?? $song['artist'] ?? 'unknown-artist');
    $shareUrl = SITE_URL . '/song/' . urlencode($shareSlug);
    $shareTitle = htmlspecialchars($song['title'] . ' - ' . ($display_artist_name ?? $song['artist'] ?? 'Unknown Artist'));
    $shareDescription = !empty($song['share_excerpt']) ? htmlspecialchars($song['share_excerpt']) : (!empty($song['description']) ? htmlspecialchars(strip_tags(substr($song['description'], 0, 200))) : htmlspecialchars('Listen to ' . $song['title'] . ' by ' . ($display_artist_name ?? $song['artist'] ?? 'Unknown Artist') . ' on ' . SITE_NAME));
    
    // Get share image - use cover art first, then artist profile image, then default
    $shareImage = '';
    if (!empty($song['cover_art'])) {
        $shareImage = asset_path($song['cover_art']);
    } else {
        // Try to get artist profile image from uploaded_by user
        $artist_avatar = '';
        if (!empty($song['uploaded_by']) && isset($conn)) {
            try {
                $avatarStmt = $conn->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
                $avatarStmt->execute([$song['uploaded_by']]);
                $avatarResult = $avatarStmt->fetch(PDO::FETCH_ASSOC);
                if ($avatarResult && !empty($avatarResult['avatar'])) {
                    $artist_avatar = $avatarResult['avatar'];
                }
            } catch (Exception $e) {
                error_log("Error fetching artist avatar for share image: " . $e->getMessage());
            }
        }
        
        if (!empty($artist_avatar)) {
            $shareImage = asset_path($artist_avatar);
        } else {
            // Fallback to default cover
            $shareImage = defined('SITE_URL') ? SITE_URL . '/assets/images/default-cover.jpg' : '';
        }
    }
    ?>
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="music.song">
    <meta property="og:url" content="<?php echo htmlspecialchars($shareUrl); ?>">
    <meta property="og:title" content="<?php echo $shareTitle; ?>">
    <meta property="og:description" content="<?php echo $shareDescription; ?>">
    <?php if (!empty($shareImage)): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($shareImage); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <?php endif; ?>
    <meta property="og:site_name" content="<?php echo htmlspecialchars(SITE_NAME); ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo htmlspecialchars($shareUrl); ?>">
    <meta name="twitter:title" content="<?php echo $shareTitle; ?>">
    <meta name="twitter:description" content="<?php echo $shareDescription; ?>">
    <?php if (!empty($shareImage)): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($shareImage); ?>">
    <?php endif; ?>
    
    <!-- Additional meta tags -->
    <meta name="description" content="<?php echo $shareDescription; ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Bar UI CSS removed - not needed for our custom player -->
    <?php 
    if (file_exists(__DIR__ . '/includes/theme-loader.php')) {
        require_once __DIR__ . '/includes/theme-loader.php';
        if (function_exists('renderThemeStyles')) {
            renderThemeStyles();
        }
    }
    ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f5f5;
            color: #333;
            min-height: 100vh;
            padding-bottom: 40px;
            font-family: Arial, sans-serif;
        }

        /* --- Global / Wrapper Styles --- */
        .custom-player-page-container {
            max-width: 100%;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            text-align: center;
        }

        .custom-player {
            border-radius: 0;
        }

        /* --- Main Player Box --- */
        .custom-player {
            position: relative;
            width: 100%;
            overflow: hidden;
            color: white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            border-radius: 8px 8px 0 0;
        }

        /* Cover art background image (blurred) - Bottom layer */
        .cover-bg-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            filter: blur(3px);
            opacity: 0.4;
            z-index: 1;
            transform: scale(1.02); /* Prevents blur edges */
        }

        /* Dark overlay on top of background */
        .dark-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2;
        }

        /* Checkered texture overlay - Grid pattern */
        .background-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(45deg, rgba(255, 255, 255, 0.05) 25%, transparent 25%),
                linear-gradient(-45deg, rgba(255, 255, 255, 0.05) 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, rgba(255, 255, 255, 0.05) 75%),
                linear-gradient(-45deg, transparent 75%, rgba(255, 255, 255, 0.05) 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
            background-color: transparent;
            z-index: 3;
        }

        .player-content-wrapper {
            display: flex;
            flex-direction: column;
            padding: 15px;
            min-height: 180px;
            position: relative;
            z-index: 10; /* Above all background layers */
        }

        /* --- Social Icons (Top Right) --- */
        .social-icons {
            display: flex;
            gap: 6px;
            justify-content: flex-end;
            margin-bottom: 15px;
        }

        /* --- Album Art and Title Row --- */
        .art-title-row {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-top: auto;
        }

        /* --- Album Art/Image (Square) --- */
        .album-art {
            width: 100px;
            height: 100px;
            border: 2px solid white;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.5);
            flex-shrink: 0;
            overflow: hidden;
        }

        .album-art img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* --- Song Title --- */
        .song-title {
            font-size: 1.3em;
            font-weight: 900;
            line-height: 1.1;
            text-transform: capitalize;
            margin: 0;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.8);
            color: white;
            text-align: left;
            flex: 1;
        }

        .social-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            line-height: 24px;
            text-decoration: none;
            color: white;
            transition: all 0.3s;
        }

        .social-icon:hover {
            transform: scale(1.1);
            color: white;
        }

        /* Specific Social Colors */
        .facebook { background-color: #3b5998; }
        .twitter { background-color: #55acee; }
        .whatsapp { background-color: #25D366; }

        /* --- Download Button & Stats --- */
        .download-button {
            background-color: #6cbf4d;
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 1.1em;
            font-weight: bold;
            margin-top: 20px;
            cursor: pointer;
            border-radius: 5px;
            box-shadow: 0 3px 0 #52943b;
            transition: background-color 0.15s ease;
            text-decoration: none;
            display: inline-block;
        }

        .download-button:hover {
            background-color: #5aa63c;
            color: white;
        }

        .download-button:active {
            transform: translateY(2px);
            box-shadow: 0 1px 0 #52943b;
        }

        .stats {
            margin-top: 15px;
            font-size: 0.9em;
            color: #ff3366;
            font-weight: bold;
        }

        /* Keep existing header section for compatibility */
        .header-section {
            display: none;
        }
        
        .song-artwork {
            width: 280px;
            height: 280px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            background: #667eea;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .song-artwork img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .song-info-header {
            flex: 1;
        }

        .song-title-large {
            font-size: 48px;
            font-weight: 700;
            color: white;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.5);
            margin-bottom: 10px;
        }
        
        /* Hide timestamp on mobile - show only on desktop */
        .player-time-display {
            display: none;
        }
        
        @media (min-width: 769px) {
            .player-time-display {
                display: flex !important;
                font-size: 13px !important;
                font-weight: 500 !important;
                color: #fff !important;
                opacity: 1 !important;
                text-shadow: 0 1px 2px rgba(0,0,0,0.3) !important;
            }
            
            .player-time-display #current-time {
                color: #fff !important;
                font-weight: 600 !important;
                opacity: 1 !important;
            }
            
            .player-time-display #total-time {
                color: #fff !important;
                opacity: 0.9 !important;
            }
        }
        
        @media (max-width: 768px) {
            /* On mobile, completely remove timestamp from layout - progress bar takes its exact position */
            .player-time-display {
                display: none !important;
                visibility: hidden !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                position: absolute !important;
                left: -9999px !important;
            }
            
            /* Progress bar takes the exact timestamp position on mobile */
            #bottom-progress-container {
                margin-top: 4px !important;
            }
        }
        
        @media (max-width: 768px) {
            .header-section {
                padding-bottom: 0;
            }
            
            .song-title-large {
                font-size: 32px;
            }
            
            .song-artwork {
                width: 140px;
                height: 140px;
        }

            /* Mobile: Full width scrolling title in player */
            .current-song-name {
                padding-left: 10px;
                font-size: 12px;
            }
            
            .song-info-header {
                position: absolute;
                top: 10px;
                right: 10px;
                max-width: 200px;
                z-index: 10;
            }
            
            .song-title-large {
                font-size: 24px;
                text-shadow: 1px 1px 4px rgba(0,0,0,0.7);
        }

            /* Hide top bar on mobile */
            .player-top-bar {
                display: none !important;
            }
        }
        
        /* Scrolling Title Animation */
        @keyframes scrollTitle {
            0% {
                transform: translateX(100%);
            }
            100% {
                transform: translateX(-100%);
            }
        }
        
        @keyframes scrollTitleMobile {
            0% {
                transform: translateX(100%);
            }
            100% {
                transform: translateX(-50%);
            }
        }
        
        /* Scrolling title like SoundManager2 */
        /* No scrolling on desktop */
        #scrolling-title { overflow: hidden; white-space: nowrap; display: inline-block; }
        /* Mobile-only infinite scroll */
        @media (max-width: 768px) {
            #scrolling-title { animation: scrollTitleMobile 20s linear infinite; padding-left: 100%; }
        }
        
        /* Scrolling animation - runs immediately on page load */
        @keyframes scrollText {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-50%);
            }
        }
        
        .scrolling-text {
            animation: scrollText 25s linear infinite;
            animation-delay: 0s;
        }

        /* Scrolling animation only on mobile */
        @media (max-width: 768px) {
            .mobile-scroll-container > div {
                animation: scrollTitleMobile 20s linear infinite;
            }
        }
        
        .social-icons {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 15px;
        }

        .social-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            text-decoration: none;
            transition: transform 0.2s;
        }
        
        .social-icon.facebook { background: #3b5998; }
        .social-icon.twitter { background: #1da1f2; }
        .social-icon.whatsapp { background: #25D366; }

        .social-icon:hover {
            transform: scale(1.1);
        }
        
        /* Inline Player Section */
        .inline-player {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .player-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .player-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .control-btn {
            width: 38px;
            height: 38px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            color: #333;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            border-radius: 4px;
            padding: 0;
        }
        
        .control-btn:hover {
            background: #e0e0e0;
            border-color: #bbb;
        }
        
        .control-btn.active {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }
        
        .control-btn.active:hover {
            background: #5568d3;
        }

        .control-btn-white {
            background: transparent;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            border-radius: 4px;
        }
        
        .control-btn-white:hover {
            background: rgba(255,255,255,0.2);
        }

        .progress-bar-container {
            flex: 1;
            max-width: 400px;
            position: relative;
        }
        
        .progress-bar {
            width: 100%;
            height: 4px;
            background: rgba(255,255,255,0.2);
            border-radius: 2px;
            cursor: pointer;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: rgba(59, 130, 246, 0.8);
            border-radius: 2px;
            width: 30%;
            position: relative;
        }

        .progress-handle {
            position: absolute;
            right: -6px;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            background: white;
            border-radius: 50%;
            cursor: grab;
            border: 2px solid #667eea;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        /* Content Below Player */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 30px;
            width: 100%;
            box-sizing: border-box;
        }
        
        @media (min-width: 768px) {
            .main-content {
                padding: 30px;
            }
        }
        
        @media (min-width: 1024px) {
            .main-content {
                padding: 40px;
            }
            
            .artist-song-info-grid {
                padding: 40px 50px;
                gap: 60px;
            }
            
            .download-section {
                padding: 25px 40px;
            }
            
            .section {
                min-height: 400px;
            }
        }
        
        .download-section {
            background: white;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .download-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 20px; /* reduced size */
            border-radius: 4px;
            cursor: pointer;
            font-size: 15px; /* slightly smaller */
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            white-space: nowrap; /* keep text on one line */
            transition: background 0.2s;
            margin: 0 auto;
        }

        .download-btn:hover {
            background: #218838;
            color: white;
            text-decoration: none;
        }

        .song-stats {
            font-size: 15px;
            color: #333;
            font-weight: 600;
            margin-top: 12px;
            display: flex;
            flex-direction: row; /* inline on one line */
            align-items: center;
            justify-content: center;
        }

        .song-stats div { display: inline; }
        .song-stats div + div::before { content: ' | '; color: #666; margin: 0 8px; }

        .song-stats span {
            color: #dc3545;
            font-weight: 700;
            margin-right: 3px;
        }

        /* Sections */
        .sections {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        @media (min-width: 1024px) {
            .sections {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
            }
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .section-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .song-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .song-list-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .song-list-item:hover {
            background: #f8f9fa;
        }

        .song-list-item:last-child {
            border-bottom: none;
        }

        .song-list-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .song-list-artist {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .song-list-stats {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        /* Other Songs from Artist - Play Overlay Styles */
        .artist-song-card:hover .artist-song-play-overlay {
            opacity: 1 !important;
        }

        .artist-song-card:hover .artist-song-play-overlay > div {
            transform: scale(1.1) !important;
        }

        /* Mobile: List view for artist songs */
        @media (max-width: 768px) {
            .artist-songs-grid {
                display: none !important; /* Hide grid on mobile */
            }
            .artist-songs-list {
                display: block !important;
            }
            .artist-song-list-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                background: #4a4a4a;
                border-radius: 8px;
                margin-bottom: 10px;
                cursor: pointer;
                transition: all 0.3s;
            }
            .artist-song-list-item:hover {
                background: #555;
                transform: translateX(5px);
            }
            .artist-song-list-item .thumb {
                width: 60px;
                height: 60px;
                border-radius: 6px;
                flex-shrink: 0;
                overflow: hidden;
                background: #2a2a2a;
            }
            .artist-song-list-item .info {
                flex: 1;
                min-width: 0;
            }
            .artist-song-list-item .title {
                font-size: 14px;
                font-weight: 600;
                color: #fff;
                margin-bottom: 4px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .artist-song-list-item .artist {
                font-size: 12px;
                color: #e91e63;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }
        @media (min-width: 769px) {
            .artist-songs-list {
                display: none !important; /* Hide list on desktop */
            }
            .more-artist-songs-list {
                display: none !important; /* Hide mobile list on desktop */
            }
        }
        @media (max-width: 768px) {
            .more-artist-songs-grid {
                display: none !important; /* Hide desktop grid on mobile */
            }
            
            /* You May Also Like - 2 columns on mobile */
            .you-may-also-like-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 15px !important;
            }
        }

        /* Artist and Song Info Grid */
        .artist-song-info-grid {
            display: grid;
            grid-template-columns: 1fr 1px 1fr;
            gap: 40px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        @media (max-width: 968px) {
            .artist-song-info-grid {
                grid-template-columns: 1fr;
                gap: 25px;
                padding: 20px;
            }
            
            .info-divider {
                height: 1px;
                width: 100%;
                background: linear-gradient(to right, transparent, #e0e0e0 20%, #e0e0e0 80%, transparent);
            }
            
            /* Mobile list view is handled above - grid is hidden on mobile */
        }
        
        /* Star Rating Styles */
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
        }
        .star-rating input[type="radio"] {
            display: none;
        }
        .star-rating .star-label {
            font-size: 28px;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
            -webkit-user-select: none;
            pointer-events: auto;
        }
        .star-rating .star-label:hover,
        .star-rating .star-label:hover ~ .star-label {
            color: #ffd700;
        }
        .star-rating input[type="radio"]:checked ~ .star-label,
        .star-rating input[type="radio"]:checked ~ .star-label ~ .star-label {
            color: #ffd700;
        }
        /* Highlight selected stars when clicked */
        .star-rating input[type="radio"]:checked + .star-label {
            color: #ffd700 !important;
            transform: scale(1.1);
        }
        /* Show all stars up to selected rating */
        .star-rating input[type="radio"]:checked ~ .star-label {
            color: #ffd700 !important;
        }
        
        /* Comments Section */
        .comments-section {
            margin-top: 30px;
        }
        
        .comment-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 15px;
            background: white;
            border-radius: 8px;
        }
        .comment-item:last-child {
            border-bottom: none;
        }
        .comment-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .comment-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        .comment-author {
            font-weight: 600;
            color: #333;
        }
        .comment-date {
            font-size: 12px;
            color: #999;
            margin-left: auto;
        }
        .comment-text {
            color: #555;
            line-height: 1.6;
        }
        
        .info-divider {
            background: linear-gradient(to bottom, transparent, #e0e0e0 20%, #e0e0e0 80%, transparent);
            width: 1px;
        }

        .artist-info-card {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .artist-avatar-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .artist-avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .artist-avatar-circle i {
            font-size: 35px;
            color: #ccc;
        }

        .artist-info-text {
            flex: 1;
        }

        .artist-name-large {
            font-size: 20px;
            font-weight: 700;
            color: #e91e63;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .artist-name-large:hover {
            text-decoration: underline;
        }

        .verified-icon {
            color: #4CAF50;
            font-size: 18px;
        }

        .artist-stats-text {
            font-size: 14px;
            color: #999;
            line-height: 1.6;
        }

        .info-column-title {
            color: #999;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }

        .song-info-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .song-info-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px dashed #f0f0f0;
        }

        .song-info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #999;
            font-size: 14px;
            font-weight: 500;
            min-width: 100px;
        }

        .info-value {
            color: #333;
            font-size: 14px;
            text-align: right;
            flex: 1;
        }

        .info-value a {
            color: #e91e63;
            text-decoration: none;
        }

        .info-value a:hover {
            text-decoration: underline;
        }

        /* Mobile styles applied to all screen sizes - no media queries needed */
            
            .artist-avatar-circle {
                width: 60px;
                height: 60px;
            }
            
            .artist-avatar-circle i {
                font-size: 28px;
            }
            
            .artist-name-large {
                font-size: 18px;
            }
            
            .song-info-item {
                flex-direction: column;
                gap: 5px;
            }
            
            .info-value {
                text-align: left;
            }
            
            .song-title-large {
                font-size: 36px;
            }
            
            .song-artwork {
                width: 200px;
                height: 200px;
            }
        }
        
        /* SM2 Bar UI option styles (inherit from demo) */
        .sm2-bar-ui { font-size: 23px; }
        .sm2-bar-ui .sm2-main-controls,
        .sm2-bar-ui .sm2-playlist-drawer { background-color: #2288cc; }
        .sm2-bar-ui .sm2-inline-texture { background: transparent; }
        /* Title sits a bit higher and close to left edge within stack */
        #scrolling-title { letter-spacing: .2px; }
        /* Hide timestamp on mobile */
        @media (max-width: 768px) {
            #scrolling-title { animation: scrollTitleMobile 20s linear infinite; padding-left: 100%; }
        }
        /* SM2 inline buttons: no borders/background; use native SM2 icon sprites */
        .sm2-bar-ui .sm2-inline-button {
            width: 35px;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: transparent !important;
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            padding: 0;
            transition: opacity 0.2s, transform 0.2s;
        }
        /* Show Font Awesome icons (hide SM2 sprite spans) */
        .sm2-bar-ui .sm2-inline-button .sm2-button-bd { display: none !important; }
        .sm2-bar-ui .sm2-inline-button i { color: #fff; font-size: 20px; line-height: 1; }
        .sm2-bar-ui .sm2-inline-button.play-pause { width: 40px; height: 40px; }
        .sm2-bar-ui .sm2-inline-button.play-pause i { font-size: 22px; }
        /* Active state for repeat button */
        .sm2-bar-ui .sm2-inline-button.active {
            background: rgba(255, 255, 255, 0.2) !important;
            border-radius: 4px !important;
        }
        .sm2-bar-ui .sm2-inline-button.active svg,
        .sm2-bar-ui .sm2-inline-button.active i {
            opacity: 1;
            color: #4CAF50 !important;
            fill: #4CAF50 !important;
        }
        .sm2-bar-ui .sm2-inline-controls { display: flex; gap: 8px; margin-left: auto; }
        .sm2-bar-ui .sm2-main-controls { display: flex; align-items: center; gap: 12px; padding: 8px; padding-left: 20px; }
        .sm2-bar-ui { background: rgba(30,77,114,0.95); padding: 3px 2px; }

        /* Custom volume bars icon */
        .vol-icon { display:inline-flex; align-items:flex-end; gap:2px; height:14px; }
        .vol-icon span { display:block; width:3px; background:#fff; border-radius:1px; }
        .vol-icon .b1 { height:6px; }
        .vol-icon .b2 { height:9px; }
        .vol-icon .b3 { height:12px; }
        .vol-icon .b4 { height:14px; }
        /* Muted state dims bars */
        .muted .vol-icon span { background: rgba(255,255,255,0.4); }

        /* Always-on marquee for the track title (start visible at x=0) */
        @keyframes marqueeTitle {
            0% { transform: translateX(0); }
            100% { transform: translateX(-100%); }
        }
        #scrolling-title {
            animation: marqueeTitle 28s linear infinite !important;
            will-change: transform;
            padding-left: 0;
        }
        /* Bottom bar: compact UI */
        .sm2-bar-ui { background: rgba(30,77,114,.95); padding: 3px 2px; }
        .sm2-bar-ui .sm2-main-controls { display:flex; align-items:center; gap:12px; padding: 6px 8px 6px 20px; background:transparent; }
        .sm2-inline-controls { display:flex; gap:8px; margin-left:auto; }
        .sm2-inline-button { width:35px; height:35px; display:inline-flex; align-items:center; justify-content:center; background:transparent!important; border:none!important; border-radius:0!important; box-shadow:none!important; padding:0; }
        .sm2-inline-button .sm2-button-bd { display:none; }
        .sm2-inline-button i { color:#fff; font-size:20px; line-height:1; }
        .sm2-inline-button.play-pause { width: 40px; height: 40px; }
        .sm2-inline-button.play-pause i { font-size: 22px; }
        /* Volume bars icon */
        .vol-icon { display:inline-flex; align-items:flex-end; gap:2px; height:12px; }
        .vol-icon span { display:block; width:3px; background:#fff; border-radius:1px; }
        .vol-icon .b1{height:5px}.vol-icon .b2{height:8px}.vol-icon .b3{height:10px}.vol-icon .b4{height:12px}
        .muted .vol-icon span{background:rgba(255,255,255,.4)}
        /* Scrolling title immediate start */
        @keyframes marqueeTitle { 0%{transform:translateX(100%)} 100%{transform:translateX(-100%)} }
        #scrolling-title{animation:marqueeTitle 5s linear infinite!important; will-change:transform; white-space:nowrap;}
        
        /* Push progress bar and title section up (both mobile and desktop) */
        .sm2-bar-ui .sm2-main-controls > div[style*="flex-direction:column"] {
            margin-top: -6px;
        }

        /* Desktop Responsive Styles */
        @media (min-width: 1024px) {
            .custom-player-page-container {
                max-width: 100%;
                margin: 0;
                padding-top: 70px;
                font-family: Arial, sans-serif;
                text-align: center;
            }

            .custom-player {
                position: relative;
            width: 100%;
            overflow: hidden;
                color: white;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
                border-radius: 8px 8px 0 0;
            }

            /* The main content area where the background is visible */
            .player-content-wrapper {
                display: flex;
                flex-direction: column;
                padding: 20px;
                min-height: 250px;
                position: relative;
                z-index: 10;
            }

            /* Blurred background image */
            .cover-bg-image {
                position: absolute;
                top: 0;
                left: 0;
            width: 100%;
                height: 100%;
            background-size: cover;
            background-position: center;
                filter: blur(4px);
                opacity: 0.3;
                z-index: 1;
                transform: scale(1.02);
            }

            /* Dark overlay for contrast */
            .dark-overlay {
                position: absolute;
                top: 0;
                left: 0;
            width: 100%;
            height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 2;
            }

            /* Checkered texture overlay - The prominent pixel grid */
            .background-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-image: 
                    linear-gradient(to right, rgba(0, 0, 0, 0.2) 1px, transparent 1px),
                    linear-gradient(to bottom, rgba(0, 0, 0, 0.2) 1px, transparent 1px);
                background-size: 8px 8px;
                background-color: rgba(51, 51, 51, 0.5);
                z-index: 3;
            }

            .social-icons {
                position: absolute;
                top: 20px;
                right: 20px;
                display: flex;
                gap: 8px;
                z-index: 11;
                margin-bottom: 0;
            }

            .social-icon {
                width: 30px;
                height: 30px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 14px;
                line-height: 30px;
                text-decoration: none;
                transition: transform 0.2s;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
            }

            .social-icon.facebook { background: #3b5998; }
            .social-icon.twitter { background: #55acee; }
            .social-icon.whatsapp { background: #25D366; }

            .art-title-row {
            display: flex;
                align-items: center;
                justify-content: flex-start;
                gap: 25px;
                margin-top: auto;
                padding-bottom: 20px;
            }

            .album-art {
                width: 160px;
                height: 160px;
                border: 3px solid white;
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.7);
                flex-shrink: 0;
            }

            .album-art img {
            width: 100%;
            height: 100%;
            object-fit: cover;
                display: block;
            }

            .song-title {
                font-size: 2.5em;
                font-weight: 900;
                line-height: 1.1;
                text-transform: capitalize;
                margin: 0;
                text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.9);
                color: white;
                text-align: left;
                flex: 1;
            }

            /* SM2 Player Bar Styles */
            /* The deep purple/blue background color from the image */
            .sm2-bar-ui {
                background: #393e62;
                padding: 6px 12px;
                border-radius: 0 0 8px 8px;
            }

            .sm2-bar-ui .sm2-main-controls {
                display: flex;
                align-items: center;
            gap: 15px;
            }

            /* Play/Pause Button (Large) */
            .sm2-bar-ui #main-play-btn i {
                font-size: 30px;
                color: white;
            }

            /* All Small Icons (Volume, Prev, Next, Repeat) */
            .sm2-inline-button i {
                font-size: 18px;
                color: white;
            }

            /* Volume Bars Icon (Custom CSS for the span elements) */
            .vol-icon {
                height: 18px;
                gap: 3px;
            }
            .vol-icon span {
                width: 3px;
                background: #fff;
            }
            .vol-icon .b1{height:6px}.vol-icon .b2{height:10px}.vol-icon .b3{height:14px}.vol-icon .b4{height:18px}

            /* Progress Bar (The visible white circle and the thin line) */
            #bottom-progress-container {
                height: 4px;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 2px;
            }
            #bottom-progress-fill {
                background: white;
            }

            /* Progress Handle (The white circle scrubber) */
            .sm2-bar-ui .sm2-progress-ball {
                background: white;
                border: none;
                width: 14px;
                height: 14px;
            }

            /* Song Title Text in Player Bar */
            #scrolling-title {
                color: white;
                font-weight: 700;
                font-size: 14px;
            }

            /* General Styles for the Player Bar Container */
            .media-player-bar {
                background-color: #2b7bbd;
                color: white;
                font-family: Arial, sans-serif;
                padding: 0;
                width: 100%;
                box-sizing: border-box;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            }

            /* Styles for the Title/Top Area */
            .player-top-controls {
                background-color: #216ba5;
                padding: 8px 15px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .player-title {
                font-size: 14px;
                font-weight: normal;
            }

            /* Styling the badge part */
            .player-title span {
                background-color: rgba(255, 255, 255, 0.2);
                padding: 2px 5px;
                border-radius: 3px;
            font-size: 12px;
                margin-left: 5px;
            }

            /* Styles for the Main Control Area (Bottom Strip) */
            .player-main-area {
                display: flex;
                align-items: center;
                padding: 10px 15px;
                gap: 10px;
            }

            /* Style for the Play Button */
            .play-button {
                background: none;
                border: none;
                color: white;
                font-size: 20px;
                cursor: pointer;
                padding: 0;
                outline: none;
            }

            /* Style for the Time Displays */
            .time-current,
            .time-duration {
                font-size: 14px;
            white-space: nowrap;
        }

            /* Progress Bar (Timeline) Styles */
            .progress-container {
                flex-grow: 1;
                height: 30px;
            display: flex;
                align-items: center;
            }

            .progress-bar {
                width: 100%;
                height: 8px;
                -webkit-appearance: none;
                appearance: none;
                cursor: pointer;
                background: transparent;
                margin: 0;
            }

            /* Track (The dark blue background of the timeline) */
            .progress-bar::-webkit-slider-runnable-track {
                background: #1e5c8e;
                height: 8px;
                border-radius: 4px;
            }

            .progress-bar::-moz-range-track {
                background: #1e5c8e;
                height: 8px;
                border-radius: 4px;
            }

            /* Thumb (The white circle and the lighter progress) */
            .progress-bar::-webkit-slider-thumb {
                -webkit-appearance: none;
                appearance: none;
                margin-top: -3px;
                height: 14px;
                width: 14px;
                background: white;
                border-radius: 50%;
                border: none;
                box-shadow: 0 0 5px rgba(0, 0, 0, 0.3);
            }

            .progress-bar::-moz-range-thumb {
                height: 14px;
                width: 14px;
                background: white;
                border-radius: 50%;
                border: none;
            }

            /* Icons and Right Controls */
            .player-icons {
                display: flex;
                align-items: center;
            gap: 15px;
                margin-left: 15px;
            }

            .player-icons span {
                font-size: 20px;
                cursor: pointer;
                line-height: 1;
            }

            /* Small visual effect for active/hover states on icons */
            .player-icons span:hover,
            .play-button:hover {
                opacity: 0.8;
            }

            /* Center all player controls */
            .sm2-bar-ui .sm2-main-controls {
                display: flex !important;
                justify-content: center !important;
                align-items: center !important;
                padding: 10px 20px !important;
            }

            /* Remove auto margin that pushes controls to right */
            .sm2-inline-controls {
                margin-left: 0 !important;
            }

            /* Stretch progress bar and song title section */
            .sm2-bar-ui .sm2-main-controls > div[style*="width:110px"] {
                flex: 1 1 auto !important;
                width: auto !important;
                max-width: 600px !important;
                min-width: 300px !important;
                margin-top: -8px !important;
            }

            /* Remove scrolling text animation on desktop */
            #scrolling-title {
                animation: none !important;
                transform: none !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }

            /* SM2 Bar UI Inline Elements */
            .sm2-bar-ui .sm2-inline-status {
                width: 100%;
                min-width: 100%;
                max-width: 100%;
            }
            
            .sm2-bar-ui .sm2-inline-element {
                width: 1%;
            }

            .sm2-bar-ui .sm2-inline-element {
                display: table-cell;
            }

            .sm2-bar-ui .sm2-inline-element {
                border-right: 0.075em dotted #666;
                border-right: 0.075em solid rgba(0, 0, 0, 0.1);
            }

            .sm2-bar-ui .sm2-inline-status {
                line-height: 100%;
                display: inline-block;
                min-width: 200px;
                max-width: 20em;
                padding-left: 0.75em;
                padding-right: 0.75em;
            }

            .sm2-bar-ui .sm2-inline-element, 
            .sm2-bar-ui .sm2-button-element .sm2-button-bd {
                min-width: 2.8em;
                min-height: 2.8em;
            }

            .sm2-bar-ui .sm2-inline-element, 
            .sm2-bar-ui .sm2-button-element .sm2-button-bd {
                position: relative;
            }

            .sm2-bar-ui .sm2-inline-element {
                position: relative;
                display: inline-block;
                vertical-align: middle;
                padding: 0px;
                overflow: hidden;
            }
        }
    </style>
</head>
<body>
    <?php 
    // Include header with error handling
    try {
        if (file_exists('includes/header.php')) {
            include 'includes/header.php';
        } else {
            error_log("FATAL: includes/header.php not found");
            echo '<header><h1>Error: Header file not found</h1></header>';
        }
    } catch (Exception $e) {
        error_log("FATAL: Error including header: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo '<header><h1>Error: Failed to load header</h1></header>';
    }
    ?>
    
    <?php
    // Include ads helper at the top level
    if (file_exists('includes/ads.php')) {
        require_once 'includes/ads.php';
    }
    
    // Display header ad if exists
    $headerAd = function_exists('displayAd') ? displayAd('header') : '';
    if ($headerAd) {
        echo '<div style="max-width: 1400px; margin: 10px auto; padding: 10px 15px;">' . $headerAd . '</div>';
    }
    ?>
    
    <!-- Custom Player Page Container -->
    <div class="custom-player-page-container">
        
        <div class="custom-player">
            
            <?php if (!empty($song['cover_art'])): ?>
            <!-- Blurred background image -->
            <div class="cover-bg-image" style="background-image: url('<?php echo htmlspecialchars(asset_path($song['cover_art'])); ?>');"></div>
            <?php endif; ?>
            
            <!-- Dark overlay -->
            <div class="dark-overlay"></div>
            
            <!-- Checkered texture overlay -->
            <div class="background-overlay"></div>
            
            <div class="player-content-wrapper">
                <!-- Social Icons at Top Right -->
                <div class="social-icons">
                    <?php
                    // Generate slug for sharing
                    $shareSlug = createSongSlug($song['title'], $display_artist_name ?? $song['artist'] ?? 'unknown-artist');
                    $shareUrl = SITE_URL . '/song/' . urlencode($shareSlug);
                    ?>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($shareUrl); ?>" target="_blank" class="social-icon facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($shareUrl); ?>&text=<?php echo urlencode($song['title'] . ' - ' . $display_artist_name); ?>" target="_blank" class="social-icon twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($song['title'] . ' - ' . $display_artist_name . ' ' . $shareUrl); ?>" target="_blank" class="social-icon whatsapp" style="background-color: #25D366;">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </div>

                <!-- Album Art and Title Row (Below Social Icons) -->
                <div class="art-title-row">
                    <!-- Album Art (Left) -->
                    <div class="album-art">
                        <?php if (!empty($song['cover_art'])): ?>
                            <img src="<?php echo htmlspecialchars(asset_path($song['cover_art'])); ?>" alt="<?php echo htmlspecialchars($display_artist_name); ?>">
                        <?php else: ?>
                            <div style="width: 100%; padding-bottom: 100%; position: relative; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 40px; color: white;">
                                    <i class="fas fa-music"></i>
                </div>
                            </div>
                        <?php endif; ?>
            </div>

                    <!-- Song Title (Right of Album Art) -->
                    <h1 class="song-title">
                        <?php 
                        // Use the same logic as songs.php - build collaboration display directly here
                        $final_title_artist = $display_artist_name ?? $song['artist'] ?? 'Unknown Artist';
                        if (!empty($song['is_collaboration'])) {
                            try {
                                $all_artist_names = [];
                                
                                // First, get uploader
                                if (!empty($song['uploaded_by'])) {
                                    $uploaderStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                                    $uploaderStmt->execute([$song['uploaded_by']]);
                                    $uploader = $uploaderStmt->fetch(PDO::FETCH_ASSOC);
                                    if ($uploader && !empty($uploader['username'])) {
                                        $all_artist_names[] = $uploader['username'];
                                    }
                                }
                                
                                // Then get all collaborators
                                $collabStmt = $conn->prepare("
                                    SELECT DISTINCT sc.user_id, COALESCE(u.username, sc.user_id) as artist_name
                                    FROM song_collaborators sc
                                    LEFT JOIN users u ON sc.user_id = u.id
                                    WHERE sc.song_id = ?
                                    ORDER BY sc.added_at ASC
                                ");
                                $collabStmt->execute([$song['id']]);
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
                                
                                if (count($all_artist_names) > 0) {
                                    $final_title_artist = implode(' x ', $all_artist_names);
                                }
                            } catch (Exception $e) {
                                // Keep default
                            }
                        }
                        echo htmlspecialchars($song['title']) . ' - ' . htmlspecialchars($final_title_artist);
                        ?>
                    </h1>
                                </div>
            </div>
        </div>
        
        
        <!-- Keep existing player controls below custom player box -->
        <div style="position: relative;">
            <!-- Bottom Bar - Compact with Title + Progress + Controls -->
            <div class="sm2-bar-ui">
                <div class="sm2-main-controls">
                    <!-- Play -->
                    <button id="main-play-btn" class="sm2-inline-button play-pause" title="Play/Pause"><i class="fas fa-play"></i></button>
                    
                    <!-- Middle stack: title + thin progress -->
                    <div style="display:flex; flex-direction:column; flex:0 0 auto; width:110px;">
                        <div style="overflow:hidden; margin-bottom:2px;">
                            <div id="scrolling-title" style="color:#fff; font-weight:700; font-size:13px; text-shadow:0 1px 2px rgba(0,0,0,.4); white-space: nowrap;">
                                <?php 
                                // Use the same logic as songs.php - build collaboration display directly here
                                $final_scroll_artist = $display_artist_name ?? $song['artist'] ?? 'Unknown Artist';
                                if (!empty($song['is_collaboration'])) {
                                    try {
                                        $all_artist_names = [];
                                        
                                        // First, get uploader
                                        if (!empty($song['uploaded_by'])) {
                                            $uploaderStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                                            $uploaderStmt->execute([$song['uploaded_by']]);
                                            $uploader = $uploaderStmt->fetch(PDO::FETCH_ASSOC);
                                            if ($uploader && !empty($uploader['username'])) {
                                                $all_artist_names[] = $uploader['username'];
                                            }
                                        }
                                        
                                        // Then get all collaborators
                                        $collabStmt = $conn->prepare("
                                            SELECT DISTINCT sc.user_id, COALESCE(u.artist, u.stage_name, u.username, sc.user_id) as artist_name
                                            FROM song_collaborators sc
                                            LEFT JOIN users u ON sc.user_id = u.id
                                            WHERE sc.song_id = ?
                                            ORDER BY sc.added_at ASC
                                        ");
                                        $collabStmt->execute([$song['id']]);
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
                                        
                                        if (count($all_artist_names) > 0) {
                                            $final_scroll_artist = implode(' x ', $all_artist_names);
                                        }
                                    } catch (Exception $e) {
                                        // Keep default
                                    }
                                }
                                
                                // Get file size and include it in the same line
                                $audioFileForSize = !empty($song['audio_file']) ? $song['audio_file'] : (!empty($song['file_path']) ? $song['file_path'] : '');
                                $fileSize = 0;
                                if (!empty($audioFileForSize) && file_exists($audioFileForSize)) {
                                    $fileSize = filesize($audioFileForSize);
                                }
                                $fileSizeFormatted = '';
                                if ($fileSize > 0) {
                                    if ($fileSize < 1024) {
                                        $fileSizeFormatted = ' • ' . $fileSize . ' B';
                                    } elseif ($fileSize < 1048576) {
                                        $fileSizeFormatted = ' • ' . round($fileSize / 1024, 2) . ' KB';
                                    } else {
                                        $fileSizeFormatted = ' • ' . round($fileSize / 1048576, 2) . ' MB';
                                    }
                                }
                                
                                echo htmlspecialchars($song['title']).' - '.htmlspecialchars($final_scroll_artist) . ($fileSizeFormatted ? '<span style="font-size: 11px; opacity: 0.8; font-weight: 500; margin-left: 8px; padding: 2px 6px; border: 1px solid rgba(255,255,255,0.3); border-radius: 4px; background: rgba(255,255,255,0.1);">' . htmlspecialchars($fileSizeFormatted) . '</span>' : ''); 
                                ?>
                            </div>
                            <!-- Timestamp - hidden on mobile, shown on desktop -->
                            <div style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:500; color:#fff; opacity:1; margin-top:4px; text-shadow:0 1px 2px rgba(0,0,0,0.3);" class="player-time-display">
                                <span id="current-time" style="color:#fff; font-weight:600;">0:00</span>
                                <span style="color:#fff; opacity:0.7;">/</span>
                                <span id="total-time" style="color:#fff; opacity:0.9;">0:00</span>
                            </div>
                        </div>
                        <!-- Progress bar - takes timestamp position on mobile -->
                        <div id="bottom-progress-container" style="position:relative; height:4px; background:rgba(255,255,255,.3); border-radius:2px; cursor:pointer; margin-top:4px;">
                            <div id="bottom-progress-fill" style="position:relative; height:100%; width:0%; background:#fff; border-radius:2px; transition:width 0.1s;">
                                <div id="progress-handle" style="position:absolute; right:-6px; top:50%; transform:translateY(-50%); width:12px; height:12px; background:#fff; border-radius:50%; box-shadow:0 1px 3px rgba(0,0,0,.4);"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Controls: volume bars, prev, next, repeat -->
                    <div class="sm2-inline-controls">
                        <button id="volume-btn" class="sm2-inline-button" title="Volume">
                            <span class="vol-icon"><span class="b1"></span><span class="b2"></span><span class="b3"></span><span class="b4"></span></span>
                        </button>
                        <button id="prev-btn" class="sm2-inline-button" title="Previous"><i class="fas fa-backward-step"></i></button>
                        <button id="next-btn" class="sm2-inline-button" title="Next"><i class="fas fa-forward-step"></i></button>
                        <button id="repeat-btn" class="sm2-inline-button" title="Repeat">
                            <svg viewBox="0 0 25 25" style="width:18px;height:18px;fill:#fff;">
                                <path d="M21.25 7.75h-4.75v3.5h3.75v5.25h-15.5v-5.25h5.25v2.75l5-4.5-5-4.5v2.75h-6.25c-1.38 0-2.5 1.119-2.5 2.5v7.25c0 1.38 1.12 2.5 2.5 2.5h17.5c1.381 0 2.5-1.12 2.5-2.5v-7.25c0-1.381-1.119-2.5-2.5-2.5z"/>
                            </svg>
                        </button>
                    </div>
                    <style>
                        /* Hide any extra play button in SM2 controls - comprehensively hide all play buttons except main */
                        .sm2-inline-controls button.sm2-play,
                        .sm2-inline-controls .sm2-play-button,
                        .sm2-inline-controls button[class*="play"]:not(#main-play-btn):not(#prev-btn):not(#next-btn):not(#repeat-btn),
                        .sm2-inline-controls button[title*="Play"]:not(#main-play-btn),
                        .sm2-inline-controls button[id*="play"]:not(#main-play-btn),
                        .sm2-bar-ui .sm2-inline-status .sm2-inline-element.sm2-play,
                        .sm2-bar-ui .sm2-inline-element[class*="play"]:not(#main-play-btn),
                        .sm2-bar-ui button.sm2-play:not(#main-play-btn),
                        .sm2-bar-ui .sm2-button-element.sm2-play:not(#main-play-btn),
                        .sm2-bar-ui button[class*="sm2-play"]:not(#main-play-btn),
                        .sm2-bar-ui a[class*="sm2-play"]:not(#main-play-btn),
                        .sm2-main-controls button[class*="play"]:not(#main-play-btn),
                        .sm2-main-controls button[id*="play"]:not(#main-play-btn),
                        button.sm2-play:not(#main-play-btn),
                        a.sm2-play:not(#main-play-btn),
                        /* Hide any button with play icon that's not our main button */
                        button:has(.fa-play):not(#main-play-btn):not(#prev-btn):not(#next-btn):not(#repeat-btn),
                        /* Hide SM2 generated play controls */
                        .sm2-inline-controls .sm2-button-element[data-action="play"]:not(#main-play-btn),
                        .sm2-bar-ui .sm2-button-element[data-action="play"]:not(#main-play-btn) {
                            display: none !important;
                            visibility: hidden !important;
                            opacity: 0 !important;
                            width: 0 !important;
                            height: 0 !important;
                            padding: 0 !important;
                            margin: 0 !important;
                            position: absolute !important;
                            left: -9999px !important;
                        }
                        /* Ensure only the main play button is visible */
                        #main-play-btn {
                            display: inline-flex !important;
                            visibility: visible !important;
                            opacity: 1 !important;
                            width: auto !important;
                            height: auto !important;
                            position: relative !important;
                            left: auto !important;
                        }
                    </style>
                </div>
            </div>
        </div>
        </div>

    <!-- Main Content Below Header -->
    <div class="main-content">
        <!-- Download Section -->
        <div class="download-section">
            <a href="#" class="download-btn" id="download-btn" onclick="return false;">
                Download Song
            </a>
            <div class="song-stats">
                <div><span><?php echo number_format($song['plays'] ?? 0); ?></span> plays</div>
                <div><span><?php echo number_format($song['downloads'] ?? 0); ?></span> downloads</div>
            </div>
        </div>

        <?php
        // Display content_top ad if exists (between download and info grid)
        $contentTopAd = function_exists('displayAd') ? displayAd('content_top') : '';
        if ($contentTopAd) {
            echo '<div style="margin: 20px 0; text-align: center;">' . $contentTopAd . '</div>';
        }
        ?>
        
        <!-- Artist and Song Info Grid -->
        <div class="artist-song-info-grid">
            <!-- Artist Column -->
            <div class="info-column artist-column">
                <h3 class="info-column-title">Artist<?php echo !empty($all_artists) && count($all_artists) > 1 ? 's' : ''; ?></h3>
                <?php 
                // Use $all_artists array that's already populated at the top of the file
                // This contains all artist information including avatars and stats
                
                // CRITICAL: Re-check GLOBALS if $all_artists is empty at display time
                if (empty($all_artists) && isset($GLOBALS['all_artists']) && !empty($GLOBALS['all_artists'])) {
                    $all_artists = $GLOBALS['all_artists'];
                }
                
                // Also do a direct database check as fallback if $all_artists is still empty
                if (!empty($all_artists) && count($all_artists) > 0):
                    foreach ($all_artists as $index => $artist):
                        if (!empty($artist['name'])):
                            ?>
                            <div class="artist-info-card" style="<?php echo $index > 0 ? 'margin-top: 20px;' : ''; ?>">
                                <div class="artist-avatar-circle">
                                    <?php if (!empty($artist['avatar'])): ?>
                                        <img src="<?php echo htmlspecialchars(asset_path($artist['avatar'])); ?>" alt="<?php echo htmlspecialchars($artist['name']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                        </div>
                                <div class="artist-info-text">
                                    <?php if (!empty($artist['id']) && $artist['id'] > 0): ?>
                                        <a href="/artist/<?php echo urlencode($artist['name']); ?>" class="artist-name-large">
                                            <?php echo htmlspecialchars($artist['name']); ?>
                                            <?php if (!empty($artist['verified']) && $artist['verified'] == 1): ?>
                                                <i class="fas fa-check-circle verified-icon"></i>
                                            <?php endif; ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="artist-name-large">
                                            <?php echo htmlspecialchars($artist['name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="artist-stats-text">
                                        <?php 
                                        $total_songs_display = (int)($artist['total_songs'] ?? 0);
                                        $total_plays_display = (int)($artist['total_plays'] ?? 0);
                                        ?>
                                        <?php echo number_format($total_songs_display); ?> Songs<br>
                                        <?php echo number_format($total_plays_display); ?> plays
                            </div>
                        </div>
                            </div>
                            <?php
                        endif;
                    endforeach;
                else:
                    // FALLBACK: Direct database query if $all_artists is empty
                    if (!empty($song['id'])) {
                        try {
                            // FALLBACK: Always show uploader first, then collaborators
                            
                            // First, show uploader
                            if (!empty($song['uploaded_by'])) {
                                $colCheck = $conn->query("SHOW COLUMNS FROM users");
                                $columns = $colCheck->fetchAll(PDO::FETCH_COLUMN);
                                
                                $verifiedCol = '0 as is_verified';
                                if (in_array('is_verified', $columns)) {
                                    $verifiedCol = 'u.is_verified';
                                } else if (in_array('email_verified', $columns)) {
                                    $verifiedCol = 'u.email_verified as is_verified';
                                }
                                
                                $uploaderStmt = $conn->prepare("
                                    SELECT u.id, u.username, u.avatar, $verifiedCol,
                                           COALESCE((
                                               SELECT COUNT(DISTINCT s.id)
                                               FROM songs s
                                               WHERE s.uploaded_by = u.id
                                                  OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                                           ), 0) as total_songs,
                                           COALESCE((
                                               SELECT SUM(s.plays)
                                               FROM songs s
                                               WHERE s.uploaded_by = u.id
                                                  OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                                           ), 0) as total_plays
                                    FROM users u
                                    WHERE u.id = ?
                                ");
                                $uploaderStmt->execute([$song['uploaded_by']]);
                                $uploader = $uploaderStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($uploader && !empty($uploader['username'])) {
                                    ?>
                                    <div class="artist-info-card">
                                        <div class="artist-avatar-circle">
                                            <?php if (!empty($uploader['avatar'])): ?>
                                                <img src="<?php echo htmlspecialchars(asset_path($uploader['avatar'])); ?>" alt="<?php echo htmlspecialchars($uploader['username']); ?>">
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="artist-info-text">
                                            <a href="/artist/<?php echo urlencode(ucwords(strtolower(trim($uploader['username'])))); ?>" class="artist-name-large">
                                                <?php echo htmlspecialchars(ucwords(strtolower(trim($uploader['username'])))); ?>
                                                <?php if (!empty($uploader['is_verified']) && $uploader['is_verified'] == 1): ?>
                                                    <i class="fas fa-check-circle verified-icon"></i>
                                                <?php endif; ?>
                                            </a>
                                            <div class="artist-stats-text">
                                                <?php echo number_format((int)$uploader['total_songs']); ?> Songs<br>
                                                <?php echo number_format((int)$uploader['total_plays']); ?> plays
            </div>
        </div>
                                    </div>
                                    <?php
                                }
                            }
                            
                            // Then show collaborators if they exist
                            $collabCheck = $conn->prepare("SELECT COUNT(*) as count FROM song_collaborators WHERE song_id = ?");
                            $collabCheck->execute([$song['id']]);
                            $collabCount = $collabCheck->fetch(PDO::FETCH_ASSOC)['count'];
                            
                            if ($collabCount > 0) {
                                // SONG HAS COLLABORATORS: Add collaborators (uploader already shown above)
                                $colCheck = $conn->query("SHOW COLUMNS FROM users");
                                $columns = $colCheck->fetchAll(PDO::FETCH_COLUMN);
                                
                                $verifiedCol = '0 as is_verified';
                                if (in_array('is_verified', $columns)) {
                                    $verifiedCol = 'u.is_verified';
                                } else if (in_array('email_verified', $columns)) {
                                    $verifiedCol = 'u.email_verified as is_verified';
                                }
                                
                                $fallbackStmt = $conn->prepare("
                                    SELECT u.id, u.username, u.avatar, $verifiedCol,
                                           COALESCE((
                                               SELECT COUNT(DISTINCT s.id)
                                               FROM songs s
                                               WHERE s.uploaded_by = u.id
                                                  OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                                           ), 0) as total_songs,
                                           COALESCE((
                                               SELECT SUM(s.plays)
                                               FROM songs s
                                               WHERE s.uploaded_by = u.id
                                                  OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                                           ), 0) as total_plays
                                    FROM song_collaborators sc
                                    JOIN users u ON u.id = sc.user_id
                                    WHERE sc.song_id = ? AND sc.user_id != ?
                                    ORDER BY sc.added_at ASC
                                ");
                                $fallbackStmt->execute([$song['id'], $song['uploaded_by'] ?? 0]);
                                $fallback_artists = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($fallback_artists as $idx => $fa) {
                                    if (!empty($fa['username'])) {
                                        ?>
                                        <div class="artist-info-card" style="margin-top: 20px;">
                                            <div class="artist-avatar-circle">
                                                <?php if (!empty($fa['avatar'])): ?>
                                                    <img src="<?php echo htmlspecialchars(asset_path($fa['avatar'])); ?>" alt="<?php echo htmlspecialchars($fa['username']); ?>">
                                                <?php else: ?>
                                                    <i class="fas fa-user"></i>
        <?php endif; ?>
                                            </div>
                                            <div class="artist-info-text">
                                                <a href="/artist/<?php echo urlencode(ucwords(strtolower(trim($fa['username'])))); ?>" class="artist-name-large">
                                                    <?php echo htmlspecialchars(ucwords(strtolower(trim($fa['username'])))); ?>
                                                    <?php if (!empty($fa['is_verified']) && $fa['is_verified'] == 1): ?>
                                                        <i class="fas fa-check-circle verified-icon"></i>
                                                    <?php endif; ?>
                                                </a>
                                                <div class="artist-stats-text">
                                                    <?php echo number_format((int)$fa['total_songs']); ?> Songs<br>
                                                    <?php echo number_format((int)$fa['total_plays']); ?> plays
                                                </div>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Error displaying artist info: " . $e->getMessage());
                            echo '<div class="artist-info-card"><p style="color: #999; font-style: italic;">Artist information not available</p></div>';
                        }
                    }
                ?>
                <?php endif; ?>
            </div>

            <!-- Vertical Divider -->
            <div class="info-divider"></div>

            <!-- Song Column -->
            <div class="info-column song-column">
                <h3 class="info-column-title">Song</h3>
                <div class="song-info-list">
                    <div class="song-info-item">
                        <span class="info-label">Title:</span>
                        <span class="info-value"><?php echo htmlspecialchars($song['title'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="song-info-item">
                        <span class="info-label">Artistes:</span>
                        <span class="info-value">
                            <?php 
                            // Use the same logic as songs.php - build collaboration display directly here
                            $detail_artist_display = $display_artist_name ?? $song['artist'] ?? 'Unknown Artist';
                            $detail_artist_names = [];
                            
                            if (!empty($song['is_collaboration'])) {
                                try {
                                    // First, get uploader
                                    if (!empty($song['uploaded_by'])) {
                                        $uploaderStmt = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
                                        $uploaderStmt->execute([$song['uploaded_by']]);
                                        $uploader = $uploaderStmt->fetch(PDO::FETCH_ASSOC);
                                        if ($uploader && !empty($uploader['username'])) {
                                            $detail_artist_names[] = [
                                                'id' => $uploader['id'],
                                                'name' => $uploader['username']
                                            ];
                                        }
                                    }
                                    
                                    // Then get all collaborators
                                    $collabStmt = $conn->prepare("
                                        SELECT DISTINCT sc.user_id, COALESCE(u.artist, u.stage_name, u.username, sc.user_id) as artist_name, u.id as user_id
                                        FROM song_collaborators sc
                                        LEFT JOIN users u ON sc.user_id = u.id
                                        WHERE sc.song_id = ?
                                        ORDER BY sc.added_at ASC
                                    ");
                                    $collabStmt->execute([$song['id']]);
                                    $collaborators = $collabStmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (!empty($collaborators)) {
                                        foreach ($collaborators as $c) {
                                            $collab_name = $c['artist_name'] ?? 'Unknown';
                                            $collab_id = $c['user_id'] ?? null;
                                            // Avoid duplicating uploader
                                            $already_added = false;
                                            foreach ($detail_artist_names as $existing) {
                                                if ($existing['name'] === $collab_name || ($collab_id && $existing['id'] == $collab_id)) {
                                                    $already_added = true;
                                                    break;
                                                }
                                            }
                                            if (!$already_added) {
                                                $detail_artist_names[] = [
                                                    'id' => $collab_id,
                                                    'name' => $collab_name
                                                ];
                                            }
                                        }
                                    }
                                    
                                    // Display with links
                                    if (count($detail_artist_names) > 0) {
                                        foreach ($detail_artist_names as $idx => $artist_info) {
                                            if (!empty($artist_info['id']) && !empty($artist_info['name'])) {
                                                echo '<a href="/artist/' . urlencode($artist_info['name']) . '">';
                                                echo htmlspecialchars($artist_info['name']);
                                                echo '</a>';
                                            } else {
                                                echo '<span>' . htmlspecialchars($artist_info['name']) . '</span>';
                                            }
                                            if ($idx < count($detail_artist_names) - 1) {
                                                echo ' x ';
                                            }
                                        }
                                    } else {
                                        echo htmlspecialchars($detail_artist_display);
                                    }
                                } catch (Exception $e) {
                                    echo htmlspecialchars($detail_artist_display);
                                }
                            } else {
                                // Single artist - use existing logic
                                if (!empty($display_artist_name)) {
                                    echo '<a href="/artist/' . urlencode($display_artist_name) . '">';
                                    echo htmlspecialchars($display_artist_name);
                                    echo '</a>';
                                } else {
                                    echo htmlspecialchars($display_artist_name ?? 'N/A');
                                }
                            }
                            ?>
                        </span>
                    </div>
                    <div class="song-info-item">
                        <span class="info-label">Added Date:</span>
                        <span class="info-value"><?php echo date('F d, Y', strtotime($song['upload_date'] ?? $song['created_at'] ?? 'now')); ?></span>
                    </div>
                    <div class="song-info-item">
                        <span class="info-label">Album:</span>
                        <span class="info-value"><?php echo !empty($song['album_title']) ? htmlspecialchars($song['album_title']) : (!empty($song['album']) ? htmlspecialchars($song['album']) : 'N/A'); ?></span>
                    </div>
                    <div class="song-info-item">
                        <span class="info-label">Producer:</span>
                        <span class="info-value"><?php echo !empty($song['producer']) ? htmlspecialchars($song['producer']) : 'N/A'; ?></span>
                    </div>
                    <div class="song-info-item">
                        <span class="info-label">Lyrics:</span>
                        <span class="info-value">
                            <?php if (!empty($song['lyrics']) && trim($song['lyrics']) !== ''): ?>
                                <a href="javascript:void(0);" onclick="event.preventDefault(); event.stopPropagation(); showLyricsModal('<?php echo addslashes($song['title']); ?>', <?php echo json_encode($song['lyrics']); ?>); return false;" style="cursor: pointer; color: #3498db; text-decoration: underline; position: relative; z-index: 10; pointer-events: auto;">View Lyrics</a>
                            <?php else: ?>
                                <span style="color: #999;">No lyrics</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Display sidebar ad if exists (after info grid, before sections)
        $sidebarAd = function_exists('displayAd') ? displayAd('sidebar') : '';
        if ($sidebarAd) {
            echo '<div style="margin: 30px 0; text-align: center;">' . $sidebarAd . '</div>';
        }
        ?>

        <!-- Sections: Artist Songs & You May Also Like -->
        <div class="sections">
            <!-- Other Songs from Artist -->
            <?php if (!empty($relatedSongs)): ?>
            <div class="section" style="background: #3a3a3a; padding: 25px; border-radius: 10px;">
                <h2 class="section-title" style="color: white; margin-bottom: 20px;">Other Songs from <?php echo htmlspecialchars($display_artist_name); ?></h2>
                
                <!-- Desktop: Grid View -->
                <div class="artist-songs-grid" id="artist-songs-grid" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 20px;">
                    <?php 
                    // Final deduplication pass - ensure no duplicates before display
                    $finalUniqueSongs = [];
                    $finalSeenIds = [];
                    foreach ($relatedSongs as $relatedSong) {
                        $songId = (int)$relatedSong['id'];
                        // Skip current song and duplicates
                        if ($songId === (int)$song['id'] || isset($finalSeenIds[$songId])) {
                            continue;
                        }
                        $finalSeenIds[$songId] = true;
                        $finalUniqueSongs[] = $relatedSong;
                    }
                    $relatedSongs = array_values($finalUniqueSongs);
                    
                    // Desktop: Display first 10 songs (5 columns x 2 rows)
                    $displayedSongs = array_slice($relatedSongs, 0, 10);
                    foreach ($displayedSongs as $relatedSong): 
                        // Get display artist name (with collaborators if applicable)
                        $relatedDisplayArtist = $relatedSong['display_artist'] ?? $relatedSong['artist_name'] ?? $relatedSong['artist'] ?? 'Unknown Artist';
                        
                        // Generate slug for related song - use slug_artist (primary artist) for matching
                        // This ensures the slug matches what song-details.php expects when parsing
                        $relatedTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $relatedSong['title']));
                        $relatedTitleSlug = preg_replace('/\s+/', '-', trim($relatedTitleSlug));
                        // Use slug_artist (primary artist username) not display_artist (which has "ft" format)
                        $relatedSlugArtist = $relatedSong['slug_artist'] ?? $relatedSong['artist_name'] ?? $relatedSong['artist'] ?? 'unknown-artist';
                        // Clean artist name for slug (remove special characters, spaces to hyphens)
                        $relatedArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $relatedSlugArtist));
                        $relatedArtistSlug = preg_replace('/\s+/', '-', trim($relatedArtistSlug));
                        // Remove multiple consecutive hyphens
                        $relatedArtistSlug = preg_replace('/-+/', '-', $relatedArtistSlug);
                        $relatedSongSlug = $relatedTitleSlug . '-by-' . $relatedArtistSlug;
                    ?>
                        <div class="artist-song-card" onclick="window.location.href='/song/<?php echo urlencode($relatedSongSlug); ?>'" style="cursor: pointer; background: #4a4a4a; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.3); transition: all 0.3s; position: relative;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.3)';">
                            <div style="position: relative; width: 100%; padding-top: 100%; overflow: hidden; background: #2a2a2a;">
                                <?php if (!empty($relatedSong['cover_art'])): ?>
                                <img src="<?php echo htmlspecialchars(asset_path($relatedSong['cover_art'])); ?>" alt="<?php echo htmlspecialchars($relatedSong['title']); ?>" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 32px;">
                                    <i class="fas fa-music"></i>
                                </div>
                                <?php endif; ?>
                                <!-- Play Button Overlay -->
                                <div class="artist-song-play-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s; cursor: pointer;" onclick="event.stopPropagation(); window.location.href='/song/<?php echo urlencode($relatedSongSlug); ?>'">
                                    <div style="width: 50px; height: 50px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; transform: scale(0.9); transition: transform 0.3s;">
                                        <i class="fas fa-play" style="color: #333; font-size: 18px; margin-left: 3px;"></i>
                                    </div>
                                </div>
                            </div>
                            <div style="padding: 12px;">
                                <div style="font-size: 14px; font-weight: 600; color: white; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($relatedSong['title']); ?>">
                                    <?php echo htmlspecialchars($relatedSong['title']); ?>
                                </div>
                                <div style="font-size: 12px; color: #e91e63; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($relatedDisplayArtist); ?>">
                                    <?php echo htmlspecialchars($relatedDisplayArtist); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Mobile: List View -->
                <div class="artist-songs-list" id="artist-songs-list" style="display: none;">
                    <?php 
                    // Mobile: Display first 8 songs in list view
                    $mobileDisplayedSongs = array_slice($relatedSongs, 0, 8);
                    foreach ($mobileDisplayedSongs as $relatedSong): 
                        // Get display artist name
                        $relatedDisplayArtist = $relatedSong['display_artist'] ?? $relatedSong['artist_name'] ?? $relatedSong['artist'] ?? 'Unknown Artist';
                        
                        // Generate slug
                        $relatedTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $relatedSong['title']));
                        $relatedTitleSlug = preg_replace('/\s+/', '-', trim($relatedTitleSlug));
                        $relatedSlugArtist = $relatedSong['slug_artist'] ?? $relatedSong['artist_name'] ?? $relatedSong['artist'] ?? 'unknown-artist';
                        $relatedArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $relatedSlugArtist));
                        $relatedArtistSlug = preg_replace('/\s+/', '-', trim($relatedArtistSlug));
                        $relatedArtistSlug = preg_replace('/-+/', '-', $relatedArtistSlug);
                        $relatedSongSlug = $relatedTitleSlug . '-by-' . $relatedArtistSlug;
                    ?>
                    <div class="artist-song-list-item" onclick="window.location.href='/song/<?php echo urlencode($relatedSongSlug); ?>'">
                        <div class="thumb">
                            <?php if (!empty($relatedSong['cover_art'])): ?>
                            <img src="<?php echo htmlspecialchars(asset_path($relatedSong['cover_art'])); ?>" alt="<?php echo htmlspecialchars($relatedSong['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                            <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                                <i class="fas fa-music"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="info">
                            <div class="title" title="<?php echo htmlspecialchars($relatedSong['title']); ?>">
                                <?php echo htmlspecialchars($relatedSong['title']); ?>
                            </div>
                            <div class="artist" title="<?php echo htmlspecialchars($relatedDisplayArtist); ?>">
                                <?php echo htmlspecialchars($relatedDisplayArtist); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($relatedSongs) > 8): ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <button id="load-more-artist-songs" style="padding: 12px 30px; background: var(--brand-primary-blue, #3498db); color: white; border: none; border-radius: 25px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.3s;" onmouseover="this.style.background='var(--brand-primary-blue-dark, #2980b9)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background='var(--brand-primary-blue, #3498db)'; this.style.transform='translateY(0)';">
                            Show More Songs
                        </button>
                        <!-- Desktop: More songs grid (beyond first 10) -->
                        <div id="more-artist-songs" class="more-artist-songs-grid" style="display: none; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-top: 15px;">
                            <?php 
                            // Desktop: Remaining songs (beyond first 10) - ensure no duplicates
                            $remainingSongs = array_slice($relatedSongs, 10);
                            $remainingUnique = [];
                            $remainingSeenIds = [];
                            foreach ($remainingSongs as $relatedSong) {
                                $songId = (int)$relatedSong['id'];
                                if ($songId === (int)$song['id'] || isset($remainingSeenIds[$songId])) {
                                    continue;
                                }
                                $remainingSeenIds[$songId] = true;
                                $remainingUnique[] = $relatedSong;
                            }
                            $remainingSongs = array_values($remainingUnique);
                            
                            foreach ($remainingSongs as $relatedSong): 
                                // Get display artist name (with collaborators if applicable)
                                $relatedDisplayArtist = $relatedSong['display_artist'] ?? $relatedSong['artist_name'] ?? $relatedSong['artist'] ?? 'Unknown Artist';
                                
                                // Generate slug for related song - use slug_artist (primary artist) for matching
                                // This ensures the slug matches what song-details.php expects when parsing
                                $relatedTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $relatedSong['title']));
                                $relatedTitleSlug = preg_replace('/\s+/', '-', trim($relatedTitleSlug));
                                // Use slug_artist (primary artist username) not display_artist (which has "ft" format)
                                $relatedSlugArtist = $relatedSong['slug_artist'] ?? $relatedSong['artist_name'] ?? $relatedSong['artist'] ?? 'unknown-artist';
                                // Clean artist name for slug (remove special characters, spaces to hyphens)
                                $relatedArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $relatedSlugArtist));
                                $relatedArtistSlug = preg_replace('/\s+/', '-', trim($relatedArtistSlug));
                                // Remove multiple consecutive hyphens
                                $relatedArtistSlug = preg_replace('/-+/', '-', $relatedArtistSlug);
                                $relatedSongSlug = $relatedTitleSlug . '-by-' . $relatedArtistSlug;
                            ?>
                                <div class="artist-song-card" onclick="window.location.href='/song/<?php echo urlencode($relatedSongSlug); ?>'" style="cursor: pointer; background: #4a4a4a; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.3); transition: all 0.3s; position: relative;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.3)';">
                                    <div style="position: relative; width: 100%; padding-top: 100%; overflow: hidden; background: #2a2a2a;">
                                        <?php if (!empty($relatedSong['cover_art'])): ?>
                                        <img src="<?php echo htmlspecialchars(asset_path($relatedSong['cover_art'])); ?>" alt="<?php echo htmlspecialchars($relatedSong['title']); ?>" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 32px;">
                                            <i class="fas fa-music"></i>
                                        </div>
                                        <?php endif; ?>
                                        <!-- Play Button Overlay -->
                                        <div class="artist-song-play-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s; cursor: pointer;" onclick="event.stopPropagation(); window.location.href='/song/<?php echo urlencode($relatedSongSlug); ?>'">
                                            <div style="width: 50px; height: 50px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; transform: scale(0.9); transition: transform 0.3s;">
                                                <i class="fas fa-play" style="color: #333; font-size: 18px; margin-left: 3px;"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="padding: 12px;">
                                        <div style="font-size: 14px; font-weight: 600; color: white; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($relatedSong['title']); ?>">
                                            <?php echo htmlspecialchars($relatedSong['title']); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #e91e63; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($relatedDisplayArtist); ?>">
                                            <?php echo htmlspecialchars($relatedDisplayArtist); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Mobile: More songs list (beyond first 8) -->
                        <div id="more-artist-songs-list" class="more-artist-songs-list" style="display: none;">
                            <?php 
                            // Mobile: Remaining songs (beyond first 8) - ensure no duplicates
                            $mobileRemainingSongs = array_slice($relatedSongs, 8);
                            $mobileRemainingUnique = [];
                            $mobileRemainingSeenIds = [];
                            foreach ($mobileRemainingSongs as $relatedSong) {
                                $songId = (int)$relatedSong['id'];
                                if ($songId === (int)$song['id'] || isset($mobileRemainingSeenIds[$songId])) {
                                    continue;
                                }
                                $mobileRemainingSeenIds[$songId] = true;
                                $mobileRemainingUnique[] = $relatedSong;
                            }
                            $mobileRemainingSongs = array_values($mobileRemainingUnique);
                            
                            foreach ($mobileRemainingSongs as $relatedSong): 
                                $relatedDisplayArtist = $relatedSong['display_artist'] ?? $relatedSong['artist_name'] ?? $relatedSong['artist'] ?? 'Unknown Artist';
                                $relatedTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $relatedSong['title']));
                                $relatedTitleSlug = preg_replace('/\s+/', '-', trim($relatedTitleSlug));
                                $relatedSlugArtist = $relatedSong['slug_artist'] ?? $relatedSong['artist_name'] ?? $relatedSong['artist'] ?? 'unknown-artist';
                                $relatedArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $relatedSlugArtist));
                                $relatedArtistSlug = preg_replace('/\s+/', '-', trim($relatedArtistSlug));
                                $relatedArtistSlug = preg_replace('/-+/', '-', $relatedArtistSlug);
                                $relatedSongSlug = $relatedTitleSlug . '-by-' . $relatedArtistSlug;
                            ?>
                            <div class="artist-song-list-item" onclick="window.location.href='/song/<?php echo urlencode($relatedSongSlug); ?>'">
                                <div class="thumb">
                                    <?php if (!empty($relatedSong['cover_art'])): ?>
                                    <img src="<?php echo htmlspecialchars(asset_path($relatedSong['cover_art'])); ?>" alt="<?php echo htmlspecialchars($relatedSong['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                    <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                                        <i class="fas fa-music"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="info">
                                    <div class="title" title="<?php echo htmlspecialchars($relatedSong['title']); ?>">
                                        <?php echo htmlspecialchars($relatedSong['title']); ?>
                                    </div>
                                    <div class="artist" title="<?php echo htmlspecialchars($relatedDisplayArtist); ?>">
                                        <?php echo htmlspecialchars($relatedDisplayArtist); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- You May Also Like -->
            <div class="section">
            <h2 class="section-title">You May Also Like</h2>
                <ul class="song-list">
                    <?php 
                    // Get songs of the same genre for "You May Also Like"
                    try {
                        $db = new Database();
                        $conn = $db->getConnection();
                        
                        // Get current song's genre_id
                        $currentGenreId = $song['genre_id'] ?? null;
                        
                        if (!empty($currentGenreId)) {
                            // Get songs with same genre, excluding current song
                            $mayAlsoLikeStmt = $conn->prepare("
                                SELECT s.*, 
                                       COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                                       COALESCE(s.is_collaboration, 0) as is_collaboration,
                                       u.username as artist_name,
                                       s.cover_art
                                FROM songs s
                                LEFT JOIN users u ON s.uploaded_by = u.id
                                WHERE s.genre_id = ? 
                                AND s.id != ?
                                AND (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                                ORDER BY s.plays DESC, s.downloads DESC
                                LIMIT 6
                            ");
                            $mayAlsoLikeStmt->execute([$currentGenreId, $songId]);
                            $mayAlsoLike = $mayAlsoLikeStmt->fetchAll(PDO::FETCH_ASSOC);
                        } else {
                            // Fallback: Get random songs if no genre
                            try {
                                $fallbackStmt = $conn->prepare("
                                    SELECT s.*, 
                                           COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                                           u.username as artist_name,
                                           s.cover_art
                                    FROM songs s
                                    LEFT JOIN users u ON s.uploaded_by = u.id
                                    WHERE s.id != ?
                                    AND (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                                    ORDER BY RAND()
                                    LIMIT 6
                                ");
                                $fallbackStmt->execute([$songId]);
                                $mayAlsoLike = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Exception $fallbackE) {
                                $mayAlsoLike = [];
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error fetching similar songs: " . $e->getMessage());
                        // Fallback to empty array
                        $mayAlsoLike = [];
                    }
                    ?>
                    <?php if (!empty($mayAlsoLike)): ?>
                        <div class="songs-grid you-may-also-like-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 20px; margin-top: 20px;">
                            <?php 
                            // Limit to 6 items for display
                            $mayAlsoLike = array_slice($mayAlsoLike, 0, 6);
                            foreach ($mayAlsoLike as $similarSong): 
                                // Get collaborators for this song if it's a collaboration
                                $displayArtistName = $similarSong['artist_name'] ?? $similarSong['artist'] ?? 'Unknown Artist';
                                if (!empty($similarSong['is_collaboration'])) {
                                    try {
                                        $collabStmt = $conn->prepare("
                                            SELECT u.username, 
                                                   sc.user_id
                                            FROM song_collaborators sc
                                            LEFT JOIN users u ON sc.user_id = u.id
                                            WHERE sc.song_id = ?
                                            ORDER BY sc.added_at ASC
                                        ");
                                        $collabStmt->execute([$similarSong['id']]);
                                        $collaborators = $collabStmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        // Build artist name with collaborators
                                        $artistNames = [];
                                        // Always use the uploader as the primary artist
                                        $primaryArtist = $similarSong['artist_name'] ?? $similarSong['artist'] ?? 'Unknown Artist';
                                        if (!empty($primaryArtist)) {
                                            $artistNames[] = $primaryArtist;
                                        }
                                        foreach ($collaborators as $collab) {
                                            if (!empty($collab['username']) && !in_array($collab['username'], $artistNames)) {
                                                $artistNames[] = $collab['username'];
                                            }
                                        }
                                        
                                        // Format artist display: "Main Artist ft Collaborator1, Collaborator2"
                                        if (count($artistNames) > 1) {
                                            $displayArtistName = $artistNames[0] . ' ft ' . implode(', ', array_slice($artistNames, 1));
                                        } else {
                                            $displayArtistName = !empty($artistNames[0]) ? $artistNames[0] : ($similarSong['artist'] ?? 'Unknown Artist');
                                        }
                                    } catch (Exception $e) {
                                        // Keep original display name if error
                                        $displayArtistName = $similarSong['artist_name'] ?? $similarSong['artist'] ?? 'Unknown Artist';
                                    }
                                }
                                
                                // Generate slug for similar song
                                $similarTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $similarSong['title']));
                                $similarTitleSlug = preg_replace('/\s+/', '-', trim($similarTitleSlug));
                                $similarArtistName = $similarSong['artist_name'] ?? $similarSong['artist'] ?? 'unknown-artist';
                                $similarArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $similarArtistName));
                                $similarArtistSlug = preg_replace('/\s+/', '-', trim($similarArtistSlug));
                                $similarSongSlug = $similarTitleSlug . '-by-' . $similarArtistSlug;
                                
                                // Get cover art
                                $similarCoverArt = !empty($similarSong['cover_art']) ? $similarSong['cover_art'] : 'assets/images/default-avatar.svg';
                                ?>
                                <div class="song-card" onclick="window.location.href='/song/<?php echo urlencode($similarSongSlug); ?>'" style="cursor: pointer;">
                                    <div class="song-card-image">
                                        <img src="<?php echo htmlspecialchars($similarCoverArt); ?>" 
                                             alt="<?php echo htmlspecialchars($similarSong['title']); ?>" 
                                             onerror="this.src='assets/images/default-avatar.svg'"
                                             style="width: 100%; height: 150px; object-fit: cover; border-radius: 10px;">
                                        <button class="song-card-play-btn" onclick="event.stopPropagation(); playSongCard(this)" style="position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    </div>
                                    <div class="song-card-info" style="padding: 10px 0;">
                                        <div class="song-card-title" style="font-weight: 600; font-size: 14px; color: #333; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($similarSong['title']); ?></div>
                                        <div class="song-card-artist" style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($displayArtistName); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #999; padding: 40px;">No similar songs found.</p>
                    <?php endif; ?>
            </div>
        </div>
        
        <!-- Lyrics Modal -->
        <div id="lyricsModal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow: auto;">
            <div style="background: white; margin: 50px auto; padding: 0; border-radius: 10px; width: 90%; max-width: 800px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                <div style="padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="margin: 0; color: #333;" id="lyricsModalTitle">Lyrics</h2>
                    <span onclick="closeLyricsModal()" style="color: #999; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                </div>
                <div style="padding: 30px; max-height: 70vh; overflow-y: auto;">
                    <div id="lyricsModalContent" style="color: #333; line-height: 1.8; white-space: pre-wrap; font-size: 15px; text-align: center;">
                    </div>
                </div>
            </div>
        </div>

        <!-- Lyrics Section -->
        <?php if (!empty($song['lyrics'])): ?>
        <div class="section" id="lyrics-section" style="background: white; padding: 30px; border-radius: 10px; margin-top: 30px;">
            <h2 class="section-title" style="margin-bottom: 20px;">Lyrics</h2>
            <div style="color: #333; line-height: 1.8; white-space: pre-wrap; font-size: 15px; max-height: 600px; overflow-y: auto; padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;">
                <?php echo nl2br(htmlspecialchars($song['lyrics'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Comments and Rating Section -->
        <div class="comments-section">
            <div class="section">
                <h2 class="section-title">Comments & Ratings</h2>
                
                <!-- Rating Section -->
                <div class="rating-section" style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 15px; flex-wrap: wrap;">
                        <div>
                            <div style="font-size: 32px; font-weight: 700; color: #667eea;">
                                <span id="average-rating">0.0</span>
                            </div>
                            <div style="font-size: 14px; color: #666;">
                                <span id="rating-count">0</span> ratings
                            </div>
                        </div>
                        <div style="flex: 1; min-width: 200px;">
                            <div style="margin-bottom: 10px; font-weight: 600;">Rate this song:</div>
                            <?php if ($isLoggedIn): ?>
                            <div class="star-rating" id="star-rating">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>">
                                <label for="star<?php echo $i; ?>" class="star-label" style="font-size: 28px; color: #ddd; cursor: pointer;">★</label>
                                <?php endfor; ?>
                            </div>
                            <?php else: ?>
                            <div style="padding: 10px; background: #fff3cd; border-radius: 4px; border: 1px solid #ffc107;">
                                <a href="login.php" style="color: #856404; text-decoration: underline; font-weight: 600;">Login to rate</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Add Comment Form -->
                <?php if ($isLoggedIn): ?>
                <div class="add-comment-form" style="margin-bottom: 30px; padding: 20px; background: white; border-radius: 8px; border: 1px solid #e0e0e0;">
                    <h3 style="margin-bottom: 15px; font-size: 18px;">Add a Comment</h3>
                    <div style="margin-bottom: 15px;">
                        <textarea id="comment-text" placeholder="Write your comment..." rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; resize: vertical; font-size: 14px; font-family: inherit;"></textarea>
                    </div>
                    <button id="submit-comment" style="padding: 10px 20px; background: var(--brand-primary-blue, #3498db); color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                        Post Comment
                    </button>
                </div>
                <?php else: ?>
                <div style="margin-bottom: 30px; padding: 20px; background: #fff3cd; border-radius: 8px; border: 1px solid #ffc107; text-align: center;">
                    <i class="fas fa-lock" style="font-size: 24px; color: #856404; margin-bottom: 10px;"></i>
                    <p style="color: #856404; margin: 0; font-size: 14px;">
                        <a href="login.php" style="color: var(--brand-primary-blue, #3498db); text-decoration: underline; font-weight: 600;">Login</a> to add comments and ratings
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- Comments List -->
                <div id="comments-list" style="margin-top: 20px;">
                    <!-- Comments will be loaded here via JavaScript -->
                    <div style="text-align: center; color: #999; padding: 20px;">Loading comments...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Use HTML5 Audio directly for now -->
            <audio id="song-player" preload="metadata"></audio>

    <script>
        console.log('Initializing simple audio player');
        
        let audio = null;
        let isPlaying = false;
        const playBtn = document.getElementById('main-play-btn');
        // Use bottom progress bar exclusively
        const progressBar = document.getElementById('bottom-progress-fill');
        let progressInterval = null;

        // Initialize audio player
        document.addEventListener('DOMContentLoaded', function() {
            // Debug song data
            <?php 
                $audioFile = !empty($song['audio_file']) ? $song['audio_file'] : (!empty($song['file_path']) ? $song['file_path'] : 'uploads/audio/demo.mp3');
                
                // Check if file exists
                $fileExists = file_exists($audioFile);
                
                echo "console.log('Song data:', " . json_encode($song) . ");";
                echo "console.log('Audio file path:', '" . htmlspecialchars($audioFile) . "');";
                echo "console.log('File exists:', " . ($fileExists ? 'true' : 'false') . ");";
            ?>
            
            // Initialize audio player
            const songId = <?php echo (int)$song['id']; ?>;
            const audioUrl = '<?php 
                echo htmlspecialchars($baseUrl . $audioFile);
            ?>';
            
            console.log('Loading audio:', audioUrl);
            
            // Initialize HTML5 audio
            audio = document.getElementById('song-player');
            audio.setAttribute('preload', 'metadata');
            audio.src = audioUrl;
            
            // Initialize MediaSession API for enhanced notifications
            if ('mediaSession' in navigator) {
                const songTitle = '<?php echo addslashes($song['title']); ?>';
                const songArtist = '<?php echo addslashes($display_artist_name ?? $song['artist'] ?? $song['artist_name'] ?? 'Unknown Artist'); ?>';
                const coverArt = '<?php echo htmlspecialchars($song['cover_art'] ?? 'assets/images/default-avatar.svg', ENT_QUOTES); ?>';
                
                // Convert relative path to absolute URL if needed
                let artworkUrl = coverArt;
                if (!coverArt.startsWith('http')) {
                    const baseUrl = '<?php 
                        $protocol = 'http://';
                        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                            $protocol = 'https://';
                        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                            $protocol = 'https://';
                        } elseif (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
                            $protocol = 'https://';
                        } elseif (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') {
                            $protocol = 'https://';
                        }
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $base_path = defined('BASE_PATH') ? BASE_PATH : '/';
                        echo htmlspecialchars($protocol . $host . $base_path);
                    ?>';
                    artworkUrl = baseUrl + coverArt;
                }
                
                // Set MediaSession metadata
                navigator.mediaSession.metadata = new MediaMetadata({
                    title: songTitle,
                    artist: songArtist,
                    artwork: [
                        { src: artworkUrl, sizes: '96x96', type: 'image/png' },
                        { src: artworkUrl, sizes: '128x128', type: 'image/png' },
                        { src: artworkUrl, sizes: '192x192', type: 'image/png' },
                        { src: artworkUrl, sizes: '256x256', type: 'image/png' },
                        { src: artworkUrl, sizes: '384x384', type: 'image/png' },
                        { src: artworkUrl, sizes: '512x512', type: 'image/png' }
                    ]
                });
                
                // Set action handlers
                navigator.mediaSession.setActionHandler('play', function() {
                    audio.play();
                    isPlaying = true;
                    updatePlayButton();
                });
                
                navigator.mediaSession.setActionHandler('pause', function() {
                    audio.pause();
                    isPlaying = false;
                    updatePlayButton();
                });
                
                navigator.mediaSession.setActionHandler('previoustrack', function() {
                    // Navigate to previous song if available
                    console.log('Previous track requested');
                    // Can implement previous song logic here
                });
                
                navigator.mediaSession.setActionHandler('nexttrack', function() {
                    // Navigate to next song if available
                    console.log('Next track requested');
                    // Can implement next song logic here
                });
                
                // Update playback state
                function updateMediaSessionState() {
                    navigator.mediaSession.playbackState = isPlaying ? 'playing' : 'paused';
                    
                    // Update position state
                    if (audio && audio.duration) {
                        navigator.mediaSession.setPositionState({
                            duration: audio.duration,
                            playbackRate: audio.playbackRate || 1.0,
                            position: audio.currentTime || 0
                        });
                    }
                }
                
                // Update state on play/pause
                audio.addEventListener('play', updateMediaSessionState);
                audio.addEventListener('pause', updateMediaSessionState);
                audio.addEventListener('timeupdate', function() {
                    if (navigator.mediaSession && navigator.mediaSession.setPositionState && audio.duration) {
                        navigator.mediaSession.setPositionState({
                            duration: audio.duration,
                            playbackRate: audio.playbackRate || 1.0,
                            position: audio.currentTime
                        });
                    }
                });
            }
            
            // Set up event listeners
            audio.addEventListener('loadedmetadata', function() {
                console.log('Audio metadata loaded');
                console.log('Duration:', audio.duration);
                updateTotalTime();
                
                // Update MediaSession position state when metadata loads
                if ('mediaSession' in navigator && navigator.mediaSession.setPositionState && audio.duration) {
                    navigator.mediaSession.setPositionState({
                        duration: audio.duration,
                        playbackRate: audio.playbackRate || 1.0,
                        position: audio.currentTime || 0
                    });
                }
            });
            
            // Track if play count has been incremented for this play session
            let playCountIncremented = false;
            let lastPlayPosition = -1; // Track position when play was last clicked
            
            audio.addEventListener('play', function() {
                console.log('Audio playing, currentTime:', audio.currentTime);
                isPlaying = true;
                updatePlayButton();
                startProgressUpdate();
                
                // Only increment play count when song starts from beginning
                // Check if current position is at or near the beginning
                const currentPos = audio.currentTime || 0;
                const isAtBeginning = currentPos === 0 || currentPos < 0.5;
                
                console.log('Play event - isAtBeginning:', isAtBeginning, 'playCountIncremented:', playCountIncremented, 'lastPlayPosition:', lastPlayPosition);
                
                // Count only if:
                // 1. Song is starting from beginning (currentTime < 0.5), AND
                // 2. We haven't already counted for this play session, OR
                // 3. The position has changed significantly (user restarted from later position to beginning)
                if (isAtBeginning && !playCountIncremented) {
                    console.log('Incrementing play count...');
                    incrementPlayCount();
                    playCountIncremented = true;
                    lastPlayPosition = currentPos;
                } else {
                    lastPlayPosition = currentPos;
                }
            });
            
            audio.addEventListener('pause', function() {
                console.log('Audio paused at:', audio.currentTime);
                isPlaying = false;
                updatePlayButton();
                stopProgressUpdate();
            });
            
            audio.addEventListener('seeked', function() {
                console.log('Audio seeked to:', audio.currentTime);
                // If user seeks back to beginning, allow counting again on next play
                if (audio.currentTime < 1) {
                    playCountIncremented = false;
                }
            });
            
            // Function to increment play count
            function incrementPlayCount() {
                const songId = <?php echo (int)$song['id']; ?>;
                
                // Use BASE_PATH from PHP config
                const basePath = '<?php echo BASE_PATH; ?>';
                const apiBaseUrlForPlay = window.location.origin + basePath;
                const playCountUrl = apiBaseUrlForPlay + 'api/update-play-count.php';
                
                console.log('Incrementing play count for song ID:', songId);
                console.log('Play count URL:', playCountUrl);
                console.log('Base path:', basePath);
                
                fetch(playCountUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ song_id: songId })
                })
                    .then(response => {
                        console.log('Play count response status:', response.status);
                        if (!response.ok) {
                            throw new Error('HTTP error! status: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Play count response:', data);
                        if (data.success) {
                            console.log('Play count updated:', data.plays);
                            // Don't update display instantly - will show on page reload
                            // Count is saved in database and will display correctly on next page load
                        } else {
                            console.error('Play count update failed:', data.error || data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error updating play count:', error);
                        console.error('Error details:', error.message, error.stack);
                    });
            }
            
            audio.addEventListener('ended', function() {
                console.log('Audio ended');
                isPlaying = false;
                updatePlayButton();
                stopProgressUpdate();
                if (progressBar) {
                    progressBar.style.width = '0%';
                }
                const currentTimeEl = document.getElementById('current-time');
                if (currentTimeEl) {
                    currentTimeEl.textContent = '0:00';
                }
                // Reset play count flag when song ends so next play from beginning will count
                playCountIncremented = false;
                lastPlayPosition = -1;
                audio.currentTime = 0;
            });
            
            // Reset flag when audio is ready to play from beginning
            audio.addEventListener('canplay', function() {
                console.log('Audio can play, currentTime:', audio.currentTime);
                if (audio.currentTime < 0.5) {
                    playCountIncremented = false;
                }
            });
            
            // Reset flag when audio starts loading
            audio.addEventListener('loadstart', function() {
                console.log('Audio load started');
                playCountIncremented = false;
                lastPlayPosition = -1;
            });
            
            audio.addEventListener('error', function(e) {
                console.error('Audio error:', e);
                console.error('Failed to load:', audio.src);
                // Provide more detailed error info
                var errorMsg = 'Failed to load audio file.\n\n';
                errorMsg += 'URL: ' + audio.src + '\n\n';
                if (audio.error) {
                    errorMsg += 'Error Code: ' + audio.error.code + '\n';
                    errorMsg += 'Error Message: ' + audio.error.message + '\n';
                }
                errorMsg += '\nPlease check:\n';
                errorMsg += '1. The file exists on the server\n';
                errorMsg += '2. The file path is correct\n';
                errorMsg += '3. The server allows access to the file';
                alert(errorMsg);
            });
            
            console.log('Audio player initialized');
        });
        
            function updatePlayButton() {
                playBtn.innerHTML = isPlaying ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play"></i>';
            }

        function startProgressUpdate() {
            if (progressInterval) clearInterval(progressInterval);
            
            progressInterval = setInterval(function() {
                if (audio && !isNaN(audio.duration) && audio.duration > 0) {
                    const position = audio.currentTime;
                    const duration = audio.duration;
                    const percent = (position / duration) * 100;
                    if (progressBar) {
                        progressBar.style.width = percent + '%';
                    }
                    const currentTimeEl = document.getElementById('current-time');
                    if (currentTimeEl) {
                        currentTimeEl.textContent = formatTime(position * 1000);
                    }
                }
            }, 100);
        }
        
        function stopProgressUpdate() {
            if (progressInterval) {
                clearInterval(progressInterval);
                progressInterval = null;
            }
        }
        
        function updateTotalTime() {
            if (audio && !isNaN(audio.duration) && audio.duration > 0) {
                const totalTimeEl = document.getElementById('total-time');
                if (totalTimeEl) {
                    totalTimeEl.textContent = formatTime(audio.duration * 1000);
                }
            }
        }
        
        function formatTime(milliseconds) {
            const totalSeconds = Math.floor(milliseconds / 1000);
            const mins = Math.floor(totalSeconds / 60);
            const secs = totalSeconds % 60;
            return `${mins}:${secs.toString().padStart(2, '0')}`;
                        }
        
        // Play/Pause button (both buttons)
        const handlePlayPause = function() {
            if (!audio) {
                console.error('No audio element available');
                return;
            }
            
            console.log('Play button clicked, current state:', isPlaying);
            
            if (isPlaying) {
                audio.pause();
            } else {
                audio.play().catch(err => {
                    console.error('Play failed:', err);
                    alert('Failed to play audio. Please try again.');
                });
            }
        };
        
        playBtn.addEventListener('click', handlePlayPause);
        
        // Progress bar clicking
        document.querySelector('#bottom-progress-container').addEventListener('click', function(e) {
            if (!audio) return;
            
            const rect = this.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            const position = percent * audio.duration;
            
            audio.currentTime = position;
        });

        // Repeat button
        const repeatBtn = document.getElementById('repeat-btn');
        if (repeatBtn) {
            repeatBtn.addEventListener('click', function() {
                if (!audio) return;
                
                this.classList.toggle('active');
                audio.loop = !audio.loop;
                
                // Visual feedback
                if (audio.loop) {
                    this.style.background = 'rgba(255,255,255,0.2)';
                    const icon = this.querySelector('i');
                    if (icon) { icon.style.color = '#4CAF50'; }
                } else {
                    this.style.background = 'transparent';
                    const icon = this.querySelector('i');
                    if (icon) { icon.style.color = '#fff'; }
            }
        });
        }

        // Prev/Next buttons
        document.getElementById('prev-btn').addEventListener('click', function() {
            if (audio) {
                audio.currentTime = Math.max(0, audio.currentTime - 10);
            }
        });
        
        document.getElementById('next-btn').addEventListener('click', function() {
            if (audio) {
                audio.currentTime = Math.min(audio.duration, audio.currentTime + 10);
            }
        });

        // Volume toggle
        const volumeBtn = document.getElementById('volume-btn');
        if (volumeBtn) {
            volumeBtn.addEventListener('click', function() {
                if (!audio) return;
                if (audio.muted || audio.volume === 0) {
                    audio.muted = false;
                    audio.volume = 1;
                    this.classList.remove('muted');
                } else {
                    audio.muted = true;
                    this.classList.add('muted');
                }
            });
        }
        
        // Download button - start download automatically and update count
        const downloadBtn = document.getElementById('download-btn');
        if (downloadBtn) {
            // Track if download is in progress to prevent double counting
            let downloadInProgress = false;
            
            // Disable button during download to prevent multiple clicks
            downloadBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Prevent multiple rapid clicks
                if (downloadInProgress) {
                    console.log('Download already in progress');
                    return false;
                }
                
                // Set flag and disable button IMMEDIATELY to prevent multiple clicks
                downloadInProgress = true;
                downloadBtn.disabled = true;
                downloadBtn.style.opacity = '0.6';
                downloadBtn.style.cursor = 'not-allowed';
                
                const songId = <?php echo (int)$song['id']; ?>;
                
                // Use BASE_PATH from PHP config
                const basePath = '<?php echo BASE_PATH; ?>';
                const apiBaseUrlDownload = window.location.origin + basePath;
                const downloadUrl = apiBaseUrlDownload + 'api/download.php?id=' + songId;
                
                console.log('Download URL:', downloadUrl);
                console.log('Base path:', basePath);
                
                console.log('Starting download from:', downloadUrl);
                
                // Use fetch to get the file as a blob, then download it with proper filename
                // Add headers to prevent IDM detection
                fetch(downloadUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/octet-stream, */*',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    },
                    cache: 'no-store'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Download failed: ' + response.status);
                    }
                    
                    // Extract filename from Content-Disposition header
                    const contentDisposition = response.headers.get('Content-Disposition');
                    let filename = '';
                    
                    if (contentDisposition) {
                        const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
                        if (filenameMatch && filenameMatch[1]) {
                            filename = filenameMatch[1].replace(/['"]/g, '');
                            // Decode URI component if needed
                            try {
                                filename = decodeURIComponent(filename);
                            } catch (e) {
                                // If decoding fails, use as is
                            }
                        }
                    }
                    
                    // If no filename found in header, construct it from song data
                    if (!filename) {
                        const songTitle = '<?php echo addslashes($song['title'] ?? 'song'); ?>';
                        const artistName = '<?php echo addslashes($display_artist_name ?? 'Unknown Artist'); ?>';
                        filename = songTitle.replace(/[^a-zA-Z0-9\s\-_]/g, '').trim().replace(/\s+/g, '-') + 
                                   '-by-' + 
                                   artistName.replace(/[^a-zA-Z0-9\s\-_]/g, '').trim().replace(/\s+/g, '-') + 
                                   '.mp3';
                    }
                    
                    console.log('Downloading as:', filename);
                    
                    return response.blob().then(blob => ({ blob, filename }));
                })
                .then(({ blob, filename }) => {
                    // Create object URL and trigger download with proper filename
                    // Use a random delay to prevent IDM detection
                    const randomDelay = Math.random() * 200 + 50; // 50-250ms delay
                    
                    setTimeout(function() {
                        const url = window.URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        
                        // Add attributes to prevent IDM detection
                        link.href = url;
                        link.download = filename; // Use extracted filename
                        link.style.display = 'none';
                        link.setAttribute('download', filename);
                        link.setAttribute('type', 'application/octet-stream');
                        
                        // Prevent IDM from detecting the link
                        Object.defineProperty(link, 'href', {
                            get: function() { return url; },
                            enumerable: true,
                            configurable: true
                        });
                        
                        document.body.appendChild(link);
                        
                        // Use a single click method to trigger download (prevent double download)
                        // Use direct click() method which is more reliable
                        link.click();
                        
                        // Cleanup after download starts
                        setTimeout(function() {
                            if (link.parentNode) {
                                document.body.removeChild(link);
                            }
                            window.URL.revokeObjectURL(url);
                        }, 500);
                        
                        console.log('Download started with filename:', filename);
                    }, randomDelay);
                })
                .catch(error => {
                    console.error('Download error:', error);
                    console.error('Download error details:', error.message, error.stack);
                    // Re-enable button on error
                    downloadInProgress = false;
                    downloadBtn.disabled = false;
                    downloadBtn.style.opacity = '1';
                    downloadBtn.style.cursor = 'pointer';
                    
                    // Show error message to user
                    alert('Download failed. Please try again or contact support.\n\nError: ' + error.message);
                });
                
                // Re-enable the button after download completes
                // Use a longer delay to ensure download has started
                setTimeout(function() {
                    downloadInProgress = false;
                    downloadBtn.disabled = false;
                    downloadBtn.style.opacity = '1';
                    downloadBtn.style.cursor = 'pointer';
                }, 3000);
            });
        }
        
        // Hide any extra play buttons that might be dynamically created by SM2
        function hideExtraPlayButtons() {
            // Select all buttons with play-related classes/attributes except main play button
            const allButtons = document.querySelectorAll('button, a');
            allButtons.forEach(function(btn) {
                // Skip the main play button
                if (btn.id === 'main-play-btn') return;
                
                // Check if button has play icon
                const hasPlayIcon = btn.querySelector('.fa-play, .fa-play-circle, [class*="play"]');
                
                // Check if button is in SM2 controls area and has play-related attributes
                const isInSM2Controls = btn.closest('.sm2-bar-ui, .sm2-inline-controls, .sm2-main-controls');
                const hasPlayClass = btn.className && (
                    btn.className.includes('sm2-play') ||
                    btn.className.includes('play-button') ||
                    (btn.className.includes('play') && !btn.className.includes('play-pause'))
                );
                const hasPlayTitle = btn.title && btn.title.toLowerCase().includes('play');
                
                // Hide if it matches our criteria and is not a control button (prev, next, repeat)
                if (isInSM2Controls && (hasPlayIcon || hasPlayClass || hasPlayTitle)) {
                    const isControlBtn = btn.id === 'prev-btn' || btn.id === 'next-btn' || btn.id === 'repeat-btn' || btn.id === 'volume-btn';
                    if (!isControlBtn) {
                        btn.style.display = 'none';
                        btn.style.visibility = 'hidden';
                        btn.style.opacity = '0';
                        btn.style.width = '0';
                        btn.style.height = '0';
                        btn.style.padding = '0';
                        btn.style.margin = '0';
                        btn.style.position = 'absolute';
                        btn.style.left = '-9999px';
                    }
                }
            });
        }
        
        // Run immediately and also watch for dynamically added elements
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                hideExtraPlayButtons();
                // Watch for dynamically added elements
                const observer = new MutationObserver(function(mutations) {
                    hideExtraPlayButtons();
                });
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            });
        } else {
            hideExtraPlayButtons();
            // Watch for dynamically added elements
            const observer = new MutationObserver(function(mutations) {
                hideExtraPlayButtons();
            });
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    </script>

    <!-- SoundManager2 core and Bar UI scripts (non-invasive include) -->
    <script>
        window.SM2_DEFER_INIT = true; // don't auto-init until after our page is ready
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/soundmanager2/2.97a.20170601/script/soundmanager2-jsmin.js"></script>
    <script>
        if (window.soundManager) {
            soundManager.setup({
                url: '.',
                preferFlash: false,
                useHTML5Audio: true,
                debugMode: false
            });
        }
    </script>
    <!-- Bar UI JS removed - not needed for our custom player -->
    
    <!-- Comments and Rating JavaScript -->
    <script>
        (function() {
            const songId = <?php echo (int)$song['id']; ?>;
            
            // Use absolute URL for all API calls (IP/ngrok compatible)
            // Better path calculation that works with all setups
            let basePath = window.location.pathname;
            // Remove filename if present
            if (basePath.endsWith('.php') || basePath.split('/').pop().includes('.')) {
                basePath = basePath.substring(0, basePath.lastIndexOf('/') + 1);
            } else if (!basePath.endsWith('/')) {
                basePath += '/';
            }
            const apiBaseUrl = window.location.origin + basePath;
            const baseUrl = apiBaseUrl; // For backward compatibility
            
            // Load comments and ratings - try multiple API paths
            function loadComments() {
                const alternativePaths = [
                    apiBaseUrl + 'api/comments.php',
                    window.location.origin + '/api/comments.php',
                    'api/comments.php'
                ];
                
                function tryLoadComments(urlIndex) {
                    if (urlIndex >= alternativePaths.length) {
                        const commentsList = document.getElementById('comments-list');
                        if (commentsList) {
                            commentsList.innerHTML = '<div style="text-align: center; color: #999; padding: 20px;">Failed to load comments.</div>';
                        }
                        return;
                    }
                    
                    const url = alternativePaths[urlIndex] + '?action=list&song_id=' + songId;
                    
                    fetch(url, {
                        method: 'GET',
                        mode: 'cors',
                        cache: 'no-cache'
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Update rating display
                            const avgRatingEl = document.getElementById('average-rating');
                            const ratingCountEl = document.getElementById('rating-count');
                            if (avgRatingEl) avgRatingEl.textContent = data.average_rating || '0.0';
                            if (ratingCountEl) ratingCountEl.textContent = data.rating_count || '0';
                            
                            // Display comments
                            const commentsList = document.getElementById('comments-list');
                            if (commentsList && data.comments && data.comments.length > 0) {
                                commentsList.innerHTML = data.comments.map(comment => {
                                    const date = new Date(comment.created_at);
                                    const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                                    const avatar = comment.avatar || '';
                                    const displayName = comment.display_name || 'Anonymous';
                                    const firstLetter = displayName.charAt(0).toUpperCase();
                                    
                                    return `
                                        <div class="comment-item">
                                            <div class="comment-header">
                                                <div class="comment-avatar" style="${avatar ? 'background-image: url(' + avatar + '); background-size: cover;' : ''}">${!avatar ? firstLetter : ''}</div>
                                                <div>
                                                    <div class="comment-author">${displayName}</div>
                                                </div>
                                                <div class="comment-date">${formattedDate}</div>
                                            </div>
                                            <div class="comment-text">${comment.comment.replace(/\n/g, '<br>')}</div>
                                        </div>
                                    `;
                                }).join('');
                            } else if (commentsList) {
                                commentsList.innerHTML = '<div style="text-align: center; color: #999; padding: 20px;">No comments yet. Be the first to comment!</div>';
                            }
                        } else {
                            // Try next alternative path
                            tryLoadComments(urlIndex + 1);
                        }
                    })
                    .catch(error => {
                        console.error('Error loading comments:', error, 'URL:', url);
                        // Try next alternative path
                        tryLoadComments(urlIndex + 1);
                    });
                }
                
                tryLoadComments(0);
            }
            
            // Check if user is logged in
            const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
            
            // Star rating handler - support both click and touch for mobile
            const starInputs = document.querySelectorAll('#star-rating input[type="radio"]');
            const starLabels = document.querySelectorAll('#star-rating .star-label');
            
            // Disable rating if not logged in - prevent all interactions
            if (!isLoggedIn) {
                starInputs.forEach(input => {
                    input.disabled = true;
                    input.style.pointerEvents = 'none';
                });
                starLabels.forEach(label => {
                    label.style.cursor = 'not-allowed';
                    label.style.opacity = '0.5';
                    label.style.pointerEvents = 'none';
                    // Remove click events
                    label.onclick = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        alert('Please login to rate songs');
                        window.location.href = baseUrl + 'login.php';
                        return false;
                    };
                });
            }
            
            // Handler function for rating
            function handleRating(rating) {
                // Double-check login status
                if (!isLoggedIn) {
                    alert('Please login to rate songs');
                    window.location.href = baseUrl + 'login.php';
                    return false;
                }
                
                // Use absolute URL for IP/ngrok compatibility
                // Try multiple API URL paths for compatibility
                const ratingUrl = apiBaseUrl + 'api/comments.php?action=rate&song_id=' + songId;
                
                // Also prepare alternative paths
                const alternativePaths = [
                    apiBaseUrl + 'api/comments.php',
                    window.location.origin + '/api/comments.php',
                    'api/comments.php'
                ];
                
                function tryRatingSubmission(urlIndex) {
                    if (urlIndex >= alternativePaths.length) {
                        alert('Error submitting rating. Please try again.');
                        return;
                    }
                    
                    const url = urlIndex === 0 ? ratingUrl : (alternativePaths[urlIndex] + '?action=rate&song_id=' + songId);
                    
                    fetch(url, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        mode: 'cors',
                        cache: 'no-cache',
                        body: JSON.stringify({song_id: songId, rating: rating})
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data && data.success) {
                            const avgRatingEl = document.getElementById('average-rating');
                            const ratingCountEl = document.getElementById('rating-count');
                            if (avgRatingEl) avgRatingEl.textContent = data.average_rating || '0.0';
                            if (ratingCountEl) ratingCountEl.textContent = data.rating_count || '0';
                            
                            // Update visual state of selected stars
                            const selectedStar = document.getElementById('star' + rating);
                            if (selectedStar) {
                                // Uncheck all stars first
                                document.querySelectorAll('#star-rating input[type="radio"]').forEach(star => {
                                    star.checked = false;
                                });
                                // Check the selected star
                                selectedStar.checked = true;
                                // Trigger visual update
                                const event = new Event('change', { bubbles: true });
                                selectedStar.dispatchEvent(event);
                            }
                            
                            // Reload comments/ratings after a short delay
                            setTimeout(function() {
                                loadComments();
                            }, 100);
                            
                            // Refresh page after a short delay to show updated rating
                            setTimeout(function() {
                                window.location.reload();
                            }, 500);
                        } else {
                            console.error('Rating error:', data ? (data.error || 'Unknown error') : 'No response');
                            if (data && data.error) {
                                alert('Error: ' + data.error);
                            } else if (urlIndex < alternativePaths.length - 1) {
                                // Try next alternative path
                                tryRatingSubmission(urlIndex + 1);
                            } else {
                                alert('Error submitting rating. Please try again.');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error rating:', error, 'URL:', url);
                        if (urlIndex < alternativePaths.length - 1) {
                            // Try next alternative path
                            tryRatingSubmission(urlIndex + 1);
                        } else {
                            alert('Error submitting rating. Please try again.');
                        }
                    });
                }
                
                tryRatingSubmission(0);
            }
            
            // Only add event listeners if user is logged in
            if (isLoggedIn) {
                // Add change event for radio inputs
                starInputs.forEach(star => {
                    star.addEventListener('change', function() {
                        // Update visual state immediately - stars are in reverse order (5 to 1)
                        const ratingValue = parseInt(this.value);
                        starInputs.forEach((s, idx) => {
                            const label = s.nextElementSibling;
                            if (label) {
                                const starValue = parseInt(s.value);
                                if (starValue <= ratingValue) {
                                    label.style.color = '#ffd700';
                                } else {
                                    label.style.color = '#ddd';
                                }
                            }
                        });
                        handleRating(this.value);
                    });
                });
                
                // Add click and touch events for labels (mobile support)
                starLabels.forEach((label, index) => {
                    const inputId = label.getAttribute('for');
                    const star = inputId ? document.getElementById(inputId) : label.previousElementSibling;
                    
                    if (star && star.type === 'radio') {
                        // Click event for desktop
                        label.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            if (!isLoggedIn) {
                                alert('Please login to rate songs');
                                window.location.href = baseUrl + 'login.php';
                                return false;
                            }
                            star.checked = true;
                            const changeEvent = new Event('change', { bubbles: true });
                            star.dispatchEvent(changeEvent);
                            handleRating(star.value);
                        });
                        
                        // Touch event for mobile (prevent double firing)
                        let touchHandled = false;
                        label.addEventListener('touchstart', function() {
                            touchHandled = false;
                        });
                        
                        label.addEventListener('touchend', function(e) {
                            if (!touchHandled) {
                                e.preventDefault();
                                e.stopPropagation();
                                if (!isLoggedIn) {
                                    alert('Please login to rate songs');
                                    window.location.href = baseUrl + 'login.php';
                                    return false;
                                }
                                touchHandled = true;
                                star.checked = true;
                                const changeEvent = new Event('change', { bubbles: true });
                                star.dispatchEvent(changeEvent);
                                handleRating(star.value);
                            }
                        });
                        
                        // Ensure label is clickable
                        label.style.cursor = 'pointer';
                        label.style.userSelect = 'none';
                        label.style.webkitUserSelect = 'none';
                    }
                });
            } else {
                // If not logged in, completely disable all interactions
                starInputs.forEach(star => {
                    star.disabled = true;
                    star.style.pointerEvents = 'none';
                });
                starLabels.forEach(label => {
                    label.style.pointerEvents = 'none';
                    label.style.cursor = 'not-allowed';
                    label.style.opacity = '0.5';
                    label.onclick = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        alert('Please login to rate songs');
                        window.location.href = baseUrl + 'login.php';
                        return false;
                    };
                });
            }
            
            // Submit comment handler
            const submitBtn = document.getElementById('submit-comment');
            if (submitBtn) {
                submitBtn.addEventListener('click', function() {
                    if (!isLoggedIn) {
                        alert('Please login to post comments');
                        window.location.href = baseUrl + 'login.php';
                        return;
                    }
                    
                    const commentEl = document.getElementById('comment-text');
                    const comment = commentEl ? commentEl.value.trim() : '';
                    
                    if (!comment) {
                        alert('Please enter a comment');
                        return;
                    }
                    
                    this.disabled = true;
                    this.textContent = 'Posting...';
                    
                    // Try multiple API URL paths for compatibility
                    const commentUrl = apiBaseUrl + 'api/comments.php?action=add';
                    const alternativePaths = [
                        apiBaseUrl + 'api/comments.php',
                        window.location.origin + '/api/comments.php',
                        'api/comments.php'
                    ];
                    
                    function tryCommentSubmission(urlIndex) {
                        if (urlIndex >= alternativePaths.length) {
                            alert('Failed to post comment. Please try again.');
                            this.disabled = false;
                            this.textContent = 'Post Comment';
                            return;
                        }
                        
                        const url = urlIndex === 0 ? commentUrl : (alternativePaths[urlIndex] + '?action=add');
                        
                        fetch(url, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            mode: 'cors',
                            cache: 'no-cache',
                            body: JSON.stringify({
                                song_id: songId,
                                comment: comment
                            })
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data && data.success) {
                                // Clear comment field
                                if (commentEl) commentEl.value = '';
                                
                                // Reload comments immediately
                                setTimeout(function() {
                                    loadComments();
                                }, 100);
                                
                                // Refresh page after a short delay to show new comment
                                setTimeout(function() {
                                    window.location.reload();
                                }, 500);
                            } else {
                                console.error('Comment error:', data ? (data.error || 'Unknown error') : 'No response');
                                const errorMsg = (data && data.error) ? data.error : 'Failed to post comment';
                                
                                if (urlIndex < alternativePaths.length - 1) {
                                    // Try next alternative path
                                    tryCommentSubmission.call(this, urlIndex + 1);
                                    return;
                                } else {
                                    alert('Error: ' + errorMsg);
                                }
                                this.disabled = false;
                                this.textContent = 'Post Comment';
                            }
                        })
                        .catch(error => {
                            console.error('Error posting comment:', error, 'URL:', url);
                            if (urlIndex < alternativePaths.length - 1) {
                                // Try next alternative path
                                tryCommentSubmission.call(this, urlIndex + 1);
                            } else {
                                alert('Failed to post comment. Please try again.');
                                this.disabled = false;
                                this.textContent = 'Post Comment';
                            }
                        });
                    }
                    
                    tryCommentSubmission.call(this, 0);
                });
            }
            
            // Load comments on page load
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', loadComments);
            } else {
                loadComments();
            }
        })();
        
        // Load more artist songs button - handles both mobile (list) and desktop (grid)
        const loadMoreBtn = document.getElementById('load-more-artist-songs');
        const moreSongsGrid = document.getElementById('more-artist-songs'); // Desktop grid
        const moreSongsList = document.getElementById('more-artist-songs-list'); // Mobile list
        const isMobile = window.innerWidth <= 768;
        
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function() {
                // Check if mobile or desktop
                const isMobileView = window.innerWidth <= 768;
                
                if (isMobileView && moreSongsList) {
                    // Mobile: Toggle list view
                    if (moreSongsList.style.display === 'none' || moreSongsList.style.display === '') {
                        moreSongsList.style.display = 'block';
                        this.textContent = 'Show Less';
                    } else {
                        moreSongsList.style.display = 'none';
                        this.textContent = 'Show More Songs';
                    }
                } else if (!isMobileView && moreSongsGrid) {
                    // Desktop: Toggle grid view
                    if (moreSongsGrid.style.display === 'none' || moreSongsGrid.style.display === '') {
                        moreSongsGrid.style.display = 'grid';
                        this.textContent = 'Show Less';
                    } else {
                        moreSongsGrid.style.display = 'none';
                        this.textContent = 'Show More Songs';
                    }
                }
            });
            
            // Update on window resize
            let resizeTimeout;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    const isMobileNow = window.innerWidth <= 768;
                    if (isMobileNow && moreSongsGrid) {
                        moreSongsGrid.style.display = 'none';
                    }
                    if (!isMobileNow && moreSongsList) {
                        moreSongsList.style.display = 'none';
                    }
                    if (loadMoreBtn) {
                        loadMoreBtn.textContent = 'Show More Songs';
                    }
                }, 250);
            });
        }
        
        // Lyrics Modal Functions
        function showLyricsModal(title, lyrics) {
            const modal = document.getElementById('lyricsModal');
            const titleEl = document.getElementById('lyricsModalTitle');
            const contentEl = document.getElementById('lyricsModalContent');
            
            if (modal && titleEl && contentEl) {
                titleEl.textContent = title + ' - Lyrics';
                // Escape HTML and convert newlines to <br>
                const escapedLyrics = lyrics.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                contentEl.innerHTML = escapedLyrics.replace(/\n/g, '<br>');
                // Ensure centered text
                contentEl.style.textAlign = 'center';
                modal.style.display = 'block';
                
                // Close on outside click
                const modalClickHandler = function(event) {
                    if (event.target === modal) {
                        closeLyricsModal();
                        modal.removeEventListener('click', modalClickHandler);
                    }
                };
                modal.addEventListener('click', modalClickHandler);
            }
        }
        
        function closeLyricsModal() {
            const modal = document.getElementById('lyricsModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeLyricsModal();
            }
        });
    </script>
    
    <!-- Include Footer -->
    <?php include 'includes/footer.php'; ?>
</body>
</html>

