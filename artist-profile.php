<?php
// artist-profile.php - Artist profile page matching MDUNDO design
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Artist.php';
require_once 'includes/song-storage.php';

// Get and decode artist name from URL parameter
// Handle slug (from rewrite rules), name, artist, or id parameters
$artist_param = !empty($_GET['slug']) ? trim($_GET['slug']) : 
               (!empty($_GET['name']) ? trim($_GET['name']) : 
               (!empty($_GET['artist']) ? trim($_GET['artist']) : ''));
// Convert hyphens to spaces for database lookup (slug format: ayo-lukas-bj -> ayo lukas bj)
$artist_name = $artist_param ? str_replace('-', ' ', urldecode($artist_param)) : '';
$artist_id = $_GET['id'] ?? null;
$active_tab = $_GET['tab'] ?? 'songs'; // Get active tab from URL
$artist_data = null;
$artist_songs = [];
$artist_stats = [
    'total_songs' => 0,
    'total_plays' => 0,
    'total_downloads' => 0,
    'genres' => []
];
$use_database = false;

// Don't redirect immediately - try to find artist first
// Only redirect if we truly can't find anything after trying all methods

// Try to get artist from database first
$db = new Database();
$conn = $db->getConnection();
$artist = new Artist($conn);

try {
    // Prioritize name over ID - try by name first if available
    if ($artist_name && empty($artist_data)) {
        // First try users table by username
        // Check which verified column exists
        $colCheck = $conn->query("SHOW COLUMNS FROM users");
        $columns = $colCheck->fetchAll(PDO::FETCH_COLUMN);
        $verifiedCol = 'u.is_verified as verified';
        if (!in_array('is_verified', $columns) && in_array('email_verified', $columns)) {
            $verifiedCol = 'u.email_verified as verified';
        }
        
        $userNameStmt = $conn->prepare("
            SELECT u.id, 
                   u.username as name, 
                   u.username,
                   u.avatar, u.bio, $verifiedCol,
                   COALESCE((
                       SELECT COUNT(DISTINCT s.id)
                       FROM songs s
                       WHERE s.uploaded_by = u.id
                          OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                   ), 0) as total_songs,
                   COALESCE((
                       SELECT SUM(DISTINCT CASE WHEN s.id IS NOT NULL THEN s.plays ELSE 0 END)
                       FROM songs s
                       LEFT JOIN song_collaborators sc ON s.id = sc.song_id
                       WHERE (s.uploaded_by = u.id OR sc.user_id = u.id)
                   ), 0) as total_plays,
                   COALESCE((
                       SELECT SUM(DISTINCT CASE WHEN s.id IS NOT NULL THEN s.downloads ELSE 0 END)
                       FROM songs s
                       LEFT JOIN song_collaborators sc ON s.id = sc.song_id
                       WHERE (s.uploaded_by = u.id OR sc.user_id = u.id)
                   ), 0) as total_downloads
            FROM users u
            WHERE LOWER(u.username) = LOWER(?)
        ");
        $userNameStmt->execute([$artist_name]);
        $user_data = $userNameStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data) {
            // Get default artist cover if no custom cover
            $default_cover = '';
            try {
                $coverStmt = $conn->prepare("SELECT value FROM settings WHERE setting_key = 'default_artist_cover' LIMIT 1");
                $coverStmt->execute();
                $coverResult = $coverStmt->fetch(PDO::FETCH_ASSOC);
                if ($coverResult && !empty($coverResult['value'])) {
                    $default_cover = $coverResult['value'];
                }
            } catch (Exception $e) {
                // Ignore error
            }
            
            $artist_data = [
                'id' => $user_data['id'],
                'name' => $user_data['name'],
                'avatar' => $user_data['avatar'] ?? 'assets/images/default-avatar.svg',
                'bio' => $user_data['bio'] ?? '',
                'verified' => (int)($user_data['verified'] ?? ($user_data['email_verified'] ?? 0)),
                'total_songs' => (int)$user_data['total_songs'],
                'total_plays' => (int)$user_data['total_plays'],
                'total_downloads' => (int)$user_data['total_downloads'],
                'cover_image' => $default_cover
            ];
            $artist_name = $user_data['name']; // CRITICAL: Set artist_name when found by name
            $use_database = true;
            
            // Get artist songs - include both uploaded and collaborated songs
            // Also get collaboration info
            $stmt = $conn->prepare("
                SELECT DISTINCT s.*, 
                       COALESCE(s.artist, u.username, 'Unknown Artist') as artist_name,
                       CASE WHEN EXISTS (
                           SELECT 1 FROM song_collaborators sc2 WHERE sc2.song_id = s.id
                       ) THEN 1 ELSE 0 END as is_collaboration
                FROM songs s
                LEFT JOIN users u ON s.uploaded_by = u.id
                LEFT JOIN song_collaborators sc ON sc.song_id = s.id
                WHERE s.uploaded_by = ? OR sc.user_id = ?
                ORDER BY s.plays DESC, s.downloads DESC
            ");
            $stmt->execute([$user_data['id'], $user_data['id']]);
            $artist_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // For each song, get collaboration artist names
            foreach ($artist_songs as &$song) {
                $song['collaboration_artists'] = [];
                if (!empty($song['is_collaboration'])) {
                    $collabStmt = $conn->prepare("
                        SELECT DISTINCT u.id, 
                               u.username as username,
                               u.username as original_username
                        FROM song_collaborators sc
                        JOIN users u ON sc.user_id = u.id
                        WHERE sc.song_id = ?
                    ");
                    $collabStmt->execute([$song['id']]);
                    $collaborators = $collabStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Also add uploader if not already in collaborators
                    $uploaderStmt = $conn->prepare("
                        SELECT id, 
                               username
                        FROM users WHERE id = ?
                    ");
                    $uploaderStmt->execute([$song['uploaded_by']]);
                    $uploader = $uploaderStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $all_artists = [];
                    if ($uploader) {
                        $all_artists[] = $uploader;
                    }
                    foreach ($collaborators as $collab) {
                        if ($collab['id'] != $song['uploaded_by']) {
                            $all_artists[] = $collab;
                        }
                    }
                    $song['collaboration_artists'] = $all_artists;
                }
            }
            unset($song); // Break reference
            
            // Calculate stats (already done in query, but recalculate for consistency)
            $artist_stats['total_songs'] = count($artist_songs);
            $artist_stats['total_plays'] = array_sum(array_column($artist_songs, 'plays'));
            $artist_stats['total_downloads'] = array_sum(array_column($artist_songs, 'downloads'));
        } else {
            // Not found in users table, try artists table
            $artist_data = $artist->getArtistByName($artist_name);
            if ($artist_data) {
                $use_database = true;
                
                // Decode social links
                if (!empty($artist_data['social_links']) && is_string($artist_data['social_links'])) {
                    $artist_data['social_links'] = json_decode($artist_data['social_links'], true);
                }
                
                // Get artist songs from database
                $stmt = $conn->prepare("
                    SELECT s.*, a.name as artist_name
                    FROM songs s
                    LEFT JOIN artists a ON s.artist_id = a.id
                    WHERE s.artist_id = ?
                    ORDER BY s.plays DESC
                ");
                $stmt->execute([$artist_data['id']]);
                $artist_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Calculate stats
                $artist_stats['total_songs'] = count($artist_songs);
                $artist_stats['total_plays'] = array_sum(array_column($artist_songs, 'plays'));
                $artist_stats['total_downloads'] = array_sum(array_column($artist_songs, 'downloads'));
            }
        }
    }
    
    // Fallback: Try by ID if name didn't work - Check if it's a user ID (from users table) or artist ID (from artists table)
    if (!$artist_data && $artist_id) {
        // First try users table (since songs are linked via uploaded_by)
        // Check which verified column exists
        $colCheck = $conn->query("SHOW COLUMNS FROM users");
        $columns = $colCheck->fetchAll(PDO::FETCH_COLUMN);
        $verifiedCol = 'u.is_verified as verified';
        if (!in_array('is_verified', $columns) && in_array('email_verified', $columns)) {
            $verifiedCol = 'u.email_verified as verified';
        }
        
        $userStmt = $conn->prepare("
            SELECT u.id, 
                   u.username as name, 
                   u.username,
                   u.avatar, u.bio, $verifiedCol,
                   COALESCE((
                       SELECT COUNT(DISTINCT s.id)
                       FROM songs s
                       WHERE s.uploaded_by = u.id
                          OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                   ), 0) as total_songs,
                   COALESCE((
                       SELECT SUM(DISTINCT CASE WHEN s.id IS NOT NULL THEN s.plays ELSE 0 END)
                       FROM songs s
                       LEFT JOIN song_collaborators sc ON s.id = sc.song_id
                       WHERE (s.uploaded_by = u.id OR sc.user_id = u.id)
                   ), 0) as total_plays,
                   COALESCE((
                       SELECT SUM(DISTINCT CASE WHEN s.id IS NOT NULL THEN s.downloads ELSE 0 END)
                       FROM songs s
                       LEFT JOIN song_collaborators sc ON s.id = sc.song_id
                       WHERE (s.uploaded_by = u.id OR sc.user_id = u.id)
                   ), 0) as total_downloads
            FROM users u
            WHERE u.id = ?
        ");
        $userStmt->execute([$artist_id]);
        $user_data = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data) {
            // Found in users table - use this
            // Get default artist cover if no custom cover
            $default_cover = '';
            try {
                $coverStmt = $conn->prepare("SELECT value FROM settings WHERE setting_key = 'default_artist_cover' LIMIT 1");
                $coverStmt->execute();
                $coverResult = $coverStmt->fetch(PDO::FETCH_ASSOC);
                if ($coverResult && !empty($coverResult['value'])) {
                    $default_cover = $coverResult['value'];
                }
            } catch (Exception $e) {
                // Ignore error
            }
            
            $artist_data = [
                'id' => $user_data['id'],
                'name' => $user_data['name'],
                'avatar' => $user_data['avatar'] ?? 'assets/images/default-avatar.svg',
                'bio' => $user_data['bio'] ?? '',
                'verified' => (int)($user_data['verified'] ?? ($user_data['email_verified'] ?? 0)),
                'total_songs' => (int)$user_data['total_songs'],
                'total_plays' => (int)$user_data['total_plays'],
                'total_downloads' => (int)$user_data['total_downloads'],
                'cover_image' => $default_cover
            ];
            $artist_name = $user_data['name']; // CRITICAL: Set artist_name when found by ID
            $use_database = true;
            
            // Get artist songs from database - include both uploaded and collaborated songs
            // Limit initial load to 20 songs
            $songs_per_page = 20;
            $stmt = $conn->prepare("
                SELECT DISTINCT s.*, 
                       COALESCE(s.artist, u.username, 'Unknown Artist') as artist_name,
                       CASE WHEN EXISTS (
                           SELECT 1 FROM song_collaborators sc2 WHERE sc2.song_id = s.id
                       ) THEN 1 ELSE 0 END as is_collaboration
                FROM songs s
                LEFT JOIN users u ON s.uploaded_by = u.id
                LEFT JOIN song_collaborators sc ON sc.song_id = s.id
                WHERE s.uploaded_by = ? OR sc.user_id = ?
                ORDER BY s.plays DESC, s.downloads DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $artist_id, PDO::PARAM_INT);
            $stmt->bindValue(2, $artist_id, PDO::PARAM_INT);
            $stmt->bindValue(3, $songs_per_page, PDO::PARAM_INT);
            $stmt->execute();
            $artist_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count for pagination
            $countStmt = $conn->prepare("
                SELECT COUNT(DISTINCT s.id) as total
                FROM songs s
                LEFT JOIN song_collaborators sc ON sc.song_id = s.id
                WHERE s.uploaded_by = ? OR sc.user_id = ?
                AND (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
            ");
            $countStmt->execute([$artist_id, $artist_id]);
            $total_songs = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            $total_song_pages = ceil($total_songs / $songs_per_page);
            
            // For each song, get collaboration artist names
            foreach ($artist_songs as &$song) {
                $song['collaboration_artists'] = [];
                if (!empty($song['is_collaboration'])) {
                    $collabStmt = $conn->prepare("
                        SELECT DISTINCT u.id, 
                               u.username as username,
                               u.username as original_username
                        FROM song_collaborators sc
                        JOIN users u ON sc.user_id = u.id
                        WHERE sc.song_id = ?
                    ");
                    $collabStmt->execute([$song['id']]);
                    $collaborators = $collabStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Also add uploader if not already in collaborators
                    $uploaderStmt = $conn->prepare("
                        SELECT id, 
                               username
                        FROM users WHERE id = ?
                    ");
                    $uploaderStmt->execute([$song['uploaded_by']]);
                    $uploader = $uploaderStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $all_artists = [];
                    if ($uploader) {
                        $all_artists[] = $uploader;
                    }
                    foreach ($collaborators as $collab) {
                        if ($collab['id'] != $song['uploaded_by']) {
                            $all_artists[] = $collab;
                        }
                    }
                    $song['collaboration_artists'] = $all_artists;
                }
            }
            unset($song); // Break reference
            
            // Calculate stats (already done in query, but recalculate for consistency)
            $artist_stats['total_songs'] = count($artist_songs);
            $artist_stats['total_plays'] = array_sum(array_column($artist_songs, 'plays'));
            $artist_stats['total_downloads'] = array_sum(array_column($artist_songs, 'downloads'));
        } else {
            // Not found in users table, try artists table
            $artist_data = $artist->getArtistById($artist_id);
            if ($artist_data) {
                $artist_name = $artist_data['name'];
                $use_database = true;
                
                // Get artist songs from database
                $stmt = $conn->prepare("
                    SELECT s.*, a.name as artist_name
                    FROM songs s
                    LEFT JOIN artists a ON s.artist_id = a.id
                    WHERE s.artist_id = ?
                    ORDER BY s.plays DESC
                ");
                $stmt->execute([$artist_data['id']]);
                $artist_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Calculate stats
                $artist_stats['total_songs'] = count($artist_songs);
                $artist_stats['total_plays'] = array_sum(array_column($artist_songs, 'plays'));
                $artist_stats['total_downloads'] = array_sum(array_column($artist_songs, 'downloads'));
            }
        }
    }
} catch (Exception $e) {
    // Database error, fall back to JSON
    $use_database = false;
    error_log("Error in artist-profile.php: " . $e->getMessage());
}

// Fall back to JSON if database didn't work
if (!$use_database || empty($artist_songs)) {
    $artist_songs = getArtistSongs($artist_name);
    $artist_songs = array_values($artist_songs);
    
    // Calculate artist stats
    $artist_stats['total_songs'] = count($artist_songs);
    $artist_stats['total_plays'] = array_sum(array_column($artist_songs, 'plays'));
    $artist_stats['total_downloads'] = array_sum(array_column($artist_songs, 'downloads'));
    
    // Get unique genres
    $genres = array_unique(array_column($artist_songs, 'genre'));
    $artist_stats['genres'] = array_filter($genres);
    
    // Sort songs by plays (most popular first)
    usort($artist_songs, function($a, $b) {
        return ($b['plays'] ?? 0) - ($a['plays'] ?? 0);
    });
}

// Check if artist/user was found
// Only redirect if we have no artist data AND no name/id to work with
if (empty($artist_data) && empty($artist_name) && empty($artist_id)) {
    // No artist found and no parameters - redirect to artists list
    header('Location: artists.php');
    exit;
} elseif (empty($artist_data)) {
    // Artist not found but we had a parameter - show error message
    $error_message = "Artist \"" . htmlspecialchars($artist_name ?? '') . "\" was not found.";
} else if (empty($artist_songs)) {
    // Artist/user found but has no songs - this is OK, we'll still show the profile
    $artist_songs = [];
}

// Fetch additional data for tabs
$artist_lyrics = [];
$artist_news = [];
$artist_albums = [];

try {
    // Get artist ID (from users table or artists table)
    $current_artist_id = null;
    if (isset($artist_data['id'])) {
        $current_artist_id = $artist_data['id'];
    } elseif (isset($artist_id)) {
        $current_artist_id = $artist_id;
    }
    
    // Get lyrics from songs (if lyrics column exists)
    if (!empty($artist_songs)) {
        $lyrics_songs = [];
        foreach ($artist_songs as $song) {
            if (!empty($song['lyrics'])) {
                $lyrics_songs[] = $song;
            }
        }
        $artist_lyrics = $lyrics_songs;
    }
    
    // Get news related to artist (including where artist is co-author)
    $newsStmt = $conn->prepare("
        SELECT n.*, u.username as author_name
        FROM news n
        LEFT JOIN users u ON n.author_id = u.id
        WHERE n.is_published = 1
        AND (n.author_id = ? OR n.title LIKE ? OR n.content LIKE ? OR n.co_author LIKE ?)
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $search_term = '%' . $artist_name . '%';
    if (isset($artist_data['id'])) {
        $newsStmt->execute([$artist_data['id'], $search_term, $search_term, $search_term]);
    } else {
        $newsStmt->execute([0, $search_term, $search_term, $search_term]);
    }
    $artist_news = $newsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get albums for this artist
    if ($current_artist_id) {
        // Try to find artist in artists table by name or user_id
        $albumArtistStmt = $conn->prepare("
            SELECT id FROM artists 
            WHERE name = ? OR user_id = ?
            LIMIT 1
        ");
        $albumArtistStmt->execute([$artist_name, $current_artist_id]);
        $album_artist = $albumArtistStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($album_artist) {
            $albumsStmt = $conn->prepare("
                SELECT a.*, 
                       COUNT(s.id) as song_count,
                       SUM(s.plays) as total_plays,
                       SUM(s.downloads) as total_downloads
                FROM albums a
                LEFT JOIN songs s ON a.id = s.album_id
                WHERE a.artist_id = ?
                GROUP BY a.id
                ORDER BY a.release_date DESC, a.created_at DESC
            ");
            $albumsStmt->execute([$album_artist['id']]);
            $artist_albums = $albumsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Also try to get albums from songs where artist matches
    if (empty($artist_albums) && !empty($artist_songs)) {
        $albumIds = array_filter(array_column($artist_songs, 'album_id'));
        if (!empty($albumIds)) {
            $albumIds = array_unique($albumIds);
            $placeholders = implode(',', array_fill(0, count($albumIds), '?'));
            $albumsFromSongsStmt = $conn->prepare("
                SELECT a.*, 
                       COUNT(s.id) as song_count,
                       SUM(s.plays) as total_plays,
                       SUM(s.downloads) as total_downloads
                FROM albums a
                LEFT JOIN songs s ON a.id = s.album_id
                WHERE a.id IN ($placeholders)
                GROUP BY a.id
                ORDER BY a.release_date DESC, a.created_at DESC
            ");
            $albumsFromSongsStmt->execute($albumIds);
            $artist_albums = $albumsFromSongsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    error_log("Error fetching artist tab data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($artist_name); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #333;
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: #fff;
            border-bottom: 1px solid #e9ecef;
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #1db954;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .nav-link {
            color: #333;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-link:hover {
            color: #1db954;
        }

        .nav-link.active {
            color: #1db954;
            font-weight: 600;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .premium-banner {
            background: linear-gradient(135deg, #1db954 0%, #1ed760 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.2s;
        }

        .premium-banner:hover {
            transform: scale(1.05);
            color: white;
        }

        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* Artist Header */
        .artist-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 50px;
            color: white;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .artist-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.3) 0%, rgba(118, 75, 162, 0.3) 100%);
            z-index: 1;
        }

        .artist-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: pulse 20s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .artist-info {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .artist-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: rgba(255,255,255,0.9);
            backdrop-filter: blur(15px);
            border: 4px solid rgba(255,255,255,0.4);
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }

        .artist-avatar:hover {
            transform: scale(1.05);
        }

        .artist-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, rgba(29, 185, 84, 0.3) 0%, rgba(30, 215, 96, 0.3) 100%);
            padding: 6px 14px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid rgba(29, 185, 84, 0.4);
            margin-left: 12px;
            backdrop-filter: blur(10px);
        }

        .verified-badge i {
            color: #1db954;
            font-size: 14px;
        }

        .artist-bio {
            margin: 10px 0;
            font-size: 15px;
            line-height: 1.6;
            opacity: 0.95;
            max-width: 700px;
        }

        .social-links {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 16px;
        }

        .social-link:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            color: white;
        }

        .artist-details h1 {
            font-size: 42px;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            letter-spacing: -0.5px;
        }

        .artist-subtitle {
            font-size: 16px;
            opacity: 0.95;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .artist-stats {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }

        .stat-item {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 20px 25px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            min-width: 120px;
        }

        .stat-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 800;
            display: block;
            color: #fff;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 600;
        }

        /* Songs List */
        .songs-section {
            background: transparent;
            padding: 35px 0;
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 30px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .section-title::before {
            content: '';
            width: 4px;
            height: 28px;
            background: linear-gradient(135deg, #fff 0%, rgba(255,255,255,0.8) 100%);
            border-radius: 2px;
        }

        .songs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            padding: 0;
        }
        
        @media (max-width: 768px) {
            .songs-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
        }

        .song-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .song-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .song-card-image {
            width: 100%;
            aspect-ratio: 1;
            position: relative;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.3);
        }

        .song-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .song-card-play-btn {
            position: absolute;
            bottom: 12px;
            left: 12px;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 18px;
            z-index: 10;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
        }

        .song-card-play-btn:hover {
            background: rgba(0, 0, 0, 0.95);
            transform: scale(1.1);
        }

        .song-card-play-btn i {
            margin-left: 3px;
        }

        .song-card-info {
            padding: 15px;
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(5px);
        }

        .song-card-title {
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 6px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.3;
        }

        .song-card-artist {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
            text-align: center;
            line-height: 1.4;
        }

        .song-card-artist a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.2s;
        }

        .song-card-artist a:hover {
            color: rgba(255, 255, 255, 0.9);
        }

        .song-card-featured {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 4px;
        }

        /* Featured Playlists */
        .playlists-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 35px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .playlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .playlist-card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid rgba(102, 126, 234, 0.1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .playlist-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
            background: rgba(255, 255, 255, 0.95);
        }

        .playlist-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 28px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }

        .playlist-card:hover .playlist-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .playlist-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .playlist-description {
            font-size: 12px;
            color: #666;
        }

        /* Artist Tabs Menu */
        .artist-tabs-menu {
            display: flex;
            gap: 30px;
            align-items: center;
            overflow-x: auto;
            padding-bottom: 10px;
        }

        .artist-tab-link {
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
            font-size: 14px;
            text-decoration: none;
            padding: 10px 0;
            position: relative;
            white-space: nowrap;
            transition: color 0.3s;
            cursor: pointer;
        }

        .artist-tab-link:hover {
            color: rgba(255, 255, 255, 0.9);
        }

        .artist-tab-link.active {
            color: #ec4899;
            font-weight: 600;
        }

        .tab-content-section {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
            position: absolute !important;
            left: -9999px !important;
        }

        .tab-content-section.active {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            height: auto !important;
            overflow: visible !important;
            position: relative !important;
            left: auto !important;
        }
        
        /* Override for inline styles - ensure active tab shows */
        #tab-songs[style*="display: block"],
        #tab-albums[style*="display: block"],
        #tab-lyrics[style*="display: block"],
        #tab-news[style*="display: block"],
        #tab-biography[style*="display: block"] {
            display: block !important;
            visibility: visible !important;
        }

        /* Top Charts */
        .charts-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .charts-section {
                padding: 20px 15px;
            }
            
            .charts-section > div > div {
                padding: 12px 15px !important;
            }
            
            .charts-section .song-card-play-btn {
                width: 35px !important;
                height: 35px !important;
            }
        }

        .chart-tabs {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            border-bottom: 1px solid #e9ecef;
        }

        .chart-tab {
            padding: 10px 0;
            color: #666;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }

        .chart-tab:hover,
        .chart-tab.active {
            color: #1db954;
            border-bottom-color: #1db954;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .nav-links {
                gap: 20px;
            }

            .artist-info {
                flex-direction: column;
                text-align: center;
            }

            .artist-stats {
                justify-content: center;
            }

            .song-item {
                padding: 12px 0;
            }

            .song-play-btn {
                width: 35px;
                height: 35px;
                margin-right: 10px;
            }

            .playlist-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 20px 15px;
            }

            .artist-header {
                padding: 30px 20px;
            }

            .artist-details h1 {
                font-size: 28px;
            }

            .songs-section,
            .playlists-section,
            .charts-section {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <?php 
    // Display header ad if exists
    require_once 'includes/ads.php';
    $headerAd = displayAd('header');
    if ($headerAd) {
        echo '<div style="max-width: 1400px; margin: 10px auto; padding: 0 15px;">' . $headerAd . '</div>';
    }
    ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php if (!empty($artist_data)): ?>
            <!-- Artist Header -->
            <div class="artist-header" <?php if (!empty($artist_data['cover_image'])): ?>style="background-image: url('<?php echo htmlspecialchars($artist_data['cover_image']); ?>'); background-size: cover; background-position: center;"<?php endif; ?>>
                <div class="artist-info">
                    <div class="artist-avatar">
                        <?php if (!empty($artist_data['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($artist_data['avatar']); ?>" alt="<?php echo htmlspecialchars($artist_name); ?>">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="artist-details">
                        <h1>
                            <?php echo htmlspecialchars($artist_name); ?>
                            <?php 
                        // Check verified field ONLY from artists table (not email_verified from users table)
                        $is_verified = false;
                        if (isset($artist_data['verified'])) {
                            $is_verified = (int)$artist_data['verified'] == 1;
                        } else {
                            // Try to get verified status from artists table if available
                            try {
                                $verifyCheck = $conn->prepare("
                                    SELECT verified 
                                    FROM artists 
                                    WHERE user_id = ? OR name = ?
                                    LIMIT 1
                                ");
                                $verifyCheck->execute([$artist_data['id'] ?? 0, $artist_name]);
                                $artist_check = $verifyCheck->fetch(PDO::FETCH_ASSOC);
                                if ($artist_check && (int)($artist_check['verified'] ?? 0) == 1) {
                                    $is_verified = true;
                                }
                            } catch (Exception $e) {
                                // Ignore errors - no artist record found
                            }
                        }
                        
                        if ($is_verified): ?>
                                <span class="verified-badge" style="display: inline-flex !important; align-items: center; gap: 6px; background: linear-gradient(135deg, rgba(29, 185, 84, 0.3) 0%, rgba(30, 215, 96, 0.3) 100%); padding: 6px 14px; border-radius: 25px; font-size: 13px; font-weight: 600; border: 1px solid rgba(29, 185, 84, 0.4); margin-left: 12px; backdrop-filter: blur(10px);">
                                    <i class="fas fa-check-circle" style="color: #1db954; font-size: 14px;"></i> Verified
                                </span>
                            <?php endif; ?>
                        </h1>
                        
                        <?php if (!empty($artist_data['bio'])): ?>
                            <div class="artist-bio">
                                <?php echo nl2br(htmlspecialchars($artist_data['bio'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="artist-subtitle">
                            Music Artist
                        </div>
                        
                        <div class="artist-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo number_format($artist_stats['total_plays']); ?></span>
                                <span class="stat-label">Plays</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo number_format($artist_stats['total_downloads']); ?></span>
                                <span class="stat-label">Downloads</span>
                            </div>
                        </div>
                        
                        <?php if (!empty($artist_data['social_links']) && is_array($artist_data['social_links'])): ?>
                            <div class="social-links">
                                <?php if (!empty($artist_data['social_links']['facebook'])): ?>
                                    <a href="<?php echo htmlspecialchars($artist_data['social_links']['facebook']); ?>" target="_blank" class="social-link" title="Facebook">
                                        <i class="fab fa-facebook-f"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($artist_data['social_links']['twitter'])): ?>
                                    <a href="<?php echo htmlspecialchars($artist_data['social_links']['twitter']); ?>" target="_blank" class="social-link" title="Twitter">
                                        <i class="fab fa-twitter"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($artist_data['social_links']['instagram'])): ?>
                                    <a href="<?php echo htmlspecialchars($artist_data['social_links']['instagram']); ?>" target="_blank" class="social-link" title="Instagram">
                                        <i class="fab fa-instagram"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($artist_data['social_links']['youtube'])): ?>
                                    <a href="<?php echo htmlspecialchars($artist_data['social_links']['youtube']); ?>" target="_blank" class="social-link" title="YouTube">
                                        <i class="fab fa-youtube"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($artist_data['social_links']['spotify'])): ?>
                                    <a href="<?php echo htmlspecialchars($artist_data['social_links']['spotify']); ?>" target="_blank" class="social-link" title="Spotify">
                                        <i class="fab fa-spotify"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($artist_data['social_links']['website'])): ?>
                                    <a href="<?php echo htmlspecialchars($artist_data['social_links']['website']); ?>" target="_blank" class="social-link" title="Website">
                                        <i class="fas fa-globe"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tabbed Menu (like image) -->
            <div class="artist-tabs-section" style="background: rgba(45, 55, 72, 0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 20px 30px; margin-bottom: 30px; border: 1px solid rgba(255, 255, 255, 0.1);">
                <div class="artist-tabs-menu" style="display: flex; gap: 30px; align-items: center; overflow-x: auto; padding-bottom: 10px;">
                    <?php
                    // Build base URL for tabs
                    $tab_base_url = 'artist-profile.php?';
                    if ($artist_id) {
                        $tab_base_url .= 'id=' . urlencode($artist_id) . '&';
                    } else {
                        $tab_base_url .= 'name=' . urlencode($artist_name) . '&';
                    }
                    ?>
                    <a href="<?php echo $tab_base_url; ?>tab=songs" class="artist-tab-link <?php echo $active_tab === 'songs' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'songs' ? 'color: #ec4899; font-weight: 600;' : 'color: rgba(255, 255, 255, 0.7); font-weight: 500;'; ?> font-size: 14px; text-decoration: none; padding: 10px 0; position: relative; white-space: nowrap; transition: color 0.3s;">
                        Songs
                    </a>
                    <a href="<?php echo $tab_base_url; ?>tab=albums" class="artist-tab-link <?php echo $active_tab === 'albums' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'albums' ? 'color: #ec4899; font-weight: 600;' : 'color: rgba(255, 255, 255, 0.7); font-weight: 500;'; ?> font-size: 14px; text-decoration: none; padding: 10px 0; position: relative; white-space: nowrap; transition: color 0.3s;">
                        Albums
                    </a>
                    <a href="<?php echo $tab_base_url; ?>tab=lyrics" class="artist-tab-link <?php echo $active_tab === 'lyrics' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'lyrics' ? 'color: #ec4899; font-weight: 600;' : 'color: rgba(255, 255, 255, 0.7); font-weight: 500;'; ?> font-size: 14px; text-decoration: none; padding: 10px 0; position: relative; white-space: nowrap; transition: color 0.3s;">
                        Lyrics
                    </a>
                    <a href="<?php echo $tab_base_url; ?>tab=news" class="artist-tab-link <?php echo $active_tab === 'news' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'news' ? 'color: #ec4899; font-weight: 600;' : 'color: rgba(255, 255, 255, 0.7); font-weight: 500;'; ?> font-size: 14px; text-decoration: none; padding: 10px 0; position: relative; white-space: nowrap; transition: color 0.3s;">
                        News
                    </a>
                    <a href="<?php echo $tab_base_url; ?>tab=biography" class="artist-tab-link <?php echo $active_tab === 'biography' ? 'active' : ''; ?>" style="<?php echo $active_tab === 'biography' ? 'color: #ec4899; font-weight: 600;' : 'color: rgba(255, 255, 255, 0.7); font-weight: 500;'; ?> font-size: 14px; text-decoration: none; padding: 10px 0; position: relative; white-space: nowrap; transition: color 0.3s;">
                        Biography
                    </a>
                </div>
            </div>

            <!-- Tab Content Sections - Load directly (no AJAX) -->
            <?php
            // Set variables for included files - ensure they're set
            $tab_artist_name = !empty($artist_name) ? $artist_name : (!empty($artist_data['name']) ? $artist_data['name'] : '');
            $tab_artist_id = !empty($artist_id) ? $artist_id : (!empty($artist_data['id']) ? $artist_data['id'] : null);
            
            // Debug: Log variables
            error_log("artist-profile.php tabs - artist_name: " . ($artist_name ?? 'empty') . ", tab_artist_name: " . ($tab_artist_name ?? 'empty') . ", tab_artist_id: " . ($tab_artist_id ?? 'null'));
            ?>
            <?php if ($active_tab === 'songs'): ?>
            <div id="tab-songs" class="tab-content-section active" style="display: block !important; min-height: 200px;">
                <?php 
                // Ensure variables are set correctly (use previously set values or fallback)
                if (!isset($tab_artist_name) || empty($tab_artist_name)) {
                    $tab_artist_name = !empty($artist_name) ? $artist_name : (!empty($artist_data['name']) ? $artist_data['name'] : '');
                }
                if (!isset($tab_artist_id) || empty($tab_artist_id)) {
                    $tab_artist_id = !empty($artist_id) ? $artist_id : (!empty($artist_data['id']) ? $artist_data['id'] : null);
                }
                
                include 'ajax/artist-songs.php';
                ?>
            </div>
            <?php endif; ?>
            
            <?php if ($active_tab === 'albums'): ?>
            <div id="tab-albums" class="tab-content-section active" style="display: block !important; min-height: 200px;">
                <?php 
                $tab_artist_name = $artist_name;
                $tab_artist_id = $artist_id ?? $artist_data['id'] ?? null;
                include 'ajax/artist-albums.php'; 
                ?>
            </div>
            <?php endif; ?>
            
            <?php if ($active_tab === 'lyrics'): ?>
            <div id="tab-lyrics" class="tab-content-section active" style="display: block !important; min-height: 200px;">
                <?php 
                $tab_artist_name = $artist_name;
                $tab_artist_id = $artist_id ?? $artist_data['id'] ?? null;
                include 'ajax/artist-lyrics.php'; 
                ?>
            </div>
            <?php endif; ?>
            
            <?php if ($active_tab === 'news'): ?>
            <div id="tab-news" class="tab-content-section active" style="display: block !important; min-height: 200px;">
                <?php 
                $tab_artist_name = $artist_name;
                $tab_artist_id = $artist_id ?? $artist_data['id'] ?? null;
                include 'ajax/artist-news.php'; 
                ?>
            </div>
            <?php endif; ?>
            
            <?php if ($active_tab === 'biography'): ?>
            <div id="tab-biography" class="tab-content-section active" style="display: block !important; min-height: 200px;">
                <?php 
                $tab_artist_name = $artist_name;
                $tab_artist_id = $artist_id ?? $artist_data['id'] ?? null;
                include 'ajax/artist-biography.php'; 
                ?>
            </div>
            <?php endif; ?>

            <!-- Similar Artists Section -->
            <?php
            // Get similar artists (other artists on the site, excluding current artist)
            $similar_artists = [];
            try {
                $current_artist_id = $artist_data['id'] ?? $artist_id ?? null;
                if ($current_artist_id) {
                    $similarStmt = $conn->prepare("
                        SELECT 
                            u.id,
                            u.username as name,
                            u.avatar,
                            COALESCE((
                                SELECT COUNT(DISTINCT s.id)
                                FROM songs s
                                WHERE s.uploaded_by = u.id
                                   OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                            ), 0) as total_songs,
                            COALESCE((
                                SELECT SUM(DISTINCT s.plays)
                                FROM songs s
                                LEFT JOIN song_collaborators sc ON s.id = sc.song_id
                                WHERE (s.uploaded_by = u.id OR sc.user_id = u.id)
                            ), 0) as total_plays
                        FROM users u
                        WHERE u.id != ?
                        AND (
                            SELECT COUNT(DISTINCT s.id)
                            FROM songs s
                            WHERE s.uploaded_by = u.id
                               OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                        ) > 0
                        ORDER BY total_plays DESC, total_songs DESC
                        LIMIT 12
                    ");
                    $similarStmt->execute([(int)$current_artist_id]);
                    $similar_artists = $similarStmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (Exception $e) {
                error_log("Similar artists query error: " . $e->getMessage());
            }
            ?>
            <?php if (!empty($similar_artists)): ?>
            <div style="margin: 40px 0; padding: 0 20px;">
                <h2 style="font-size: 24px; font-weight: 700; color: #fff; margin-bottom: 25px;">Similar Artists</h2>
                <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 20px;" class="similar-artists-grid">
                    <?php foreach ($similar_artists as $similar): 
                        $similar_slug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $similar['name']));
                        $similar_slug = preg_replace('/\s+/', '-', trim($similar_slug));
                        $similar_slug = preg_replace('/-+/', '-', $similar_slug);
                        $similar_url = '/artist/' . $similar_slug;
                    ?>
                    <a href="<?php echo $similar_url; ?>" style="text-decoration: none; color: inherit; display: block;">
                        <div style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 15px; text-align: center; transition: all 0.3s; cursor: pointer;" onmouseover="this.style.background='rgba(255,255,255,0.1)'; this.style.transform='translateY(-5px)';" onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.transform='translateY(0)';">
                            <div style="width: 100px; height: 100px; margin: 0 auto 12px; border-radius: 50%; overflow: hidden; background: linear-gradient(135deg, #667eea, #764ba2);">
                                <?php if (!empty($similar['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($similar['avatar']); ?>" alt="<?php echo htmlspecialchars($similar['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-size: 36px;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <h3 style="font-size: 16px; font-weight: 600; color: #fff; margin: 0 0 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($similar['name']); ?>">
                                <?php echo htmlspecialchars($similar['name']); ?>
                            </h3>
                            <div style="font-size: 13px; color: rgba(255,255,255,0.7);">
                                <?php echo number_format((int)$similar['total_songs']); ?> Songs
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <style>
                @media (max-width: 768px) {
                    .similar-artists-grid {
                        grid-template-columns: repeat(2, 1fr) !important;
                        gap: 15px !important;
                    }
                }
            </style>
            <?php endif; ?>

        <?php else: ?>
            <!-- No Artist Found -->
            <div class="songs-section">
                <h2 class="section-title">User Not Found</h2>
                <p>The user "<?php echo htmlspecialchars($artist_name ?? ''); ?>" was not found.</p>
                <a href="index.php" class="btn btn-primary">Back to Home</a>
            </div>
        <?php endif; ?>
    </main>

    <script src="assets/js/mini-player.js"></script>
    <script>
        // Tabs now use page refresh - no AJAX needed
        // Tab links are already set up to refresh the page with ?tab= parameter
        
        // Playlist songs display functionality
        function showPlaylistSongs(type, songs) {
            const displayDiv = document.getElementById('playlist-songs-display');
            const titleDiv = document.getElementById('playlist-songs-title');
            const listDiv = document.getElementById('playlist-songs-list');
            
            if (!displayDiv || !titleDiv || !listDiv) return;
            
            // Set title based on type
            const titles = {
                'trending': 'Trending Now',
                'best': 'Best of <?php echo htmlspecialchars($artist_name); ?>',
                'new': 'New Releases'
            };
            
            titleDiv.textContent = titles[type] || 'Playlist';
            
            // Clear previous content
            listDiv.innerHTML = '';
            
            // Display songs
            if (songs && songs.length > 0) {
                songs.forEach(song => {
                    const coverArt = song.cover_art || 'assets/images/default-avatar.svg';
                    const mainArtist = song.artist || song.artist_name || 'Unknown Artist';
                    const songTitleSlug = song.title.toLowerCase().replace(/[^a-z0-9\s]+/gi, '').replace(/\s+/g, '-');
                    const songArtistSlug = mainArtist.toLowerCase().replace(/[^a-z0-9\s]+/gi, '').replace(/\s+/g, '-');
                    const songSlug = songTitleSlug + '-by-' + songArtistSlug;
                    
                    const songCard = document.createElement('div');
                    songCard.className = 'song-card';
                    songCard.setAttribute('data-song-id', song.id);
                    songCard.setAttribute('data-song-title', song.title);
                    songCard.setAttribute('data-song-artist', mainArtist);
                    songCard.setAttribute('data-song-cover', coverArt);
                    songCard.onclick = function() {
                        window.location.href = '/song/' + encodeURIComponent(songSlug);
                    };
                    
                    songCard.innerHTML = `
                        <div class="song-card-image">
                            <img src="${coverArt}" alt="${song.title}" onerror="this.src='assets/images/default-avatar.svg'">
                            <button class="song-card-play-btn" onclick="event.stopPropagation(); playSongCard(this)">
                                <i class="fas fa-play"></i>
                            </button>
                        </div>
                        <div class="song-card-info">
                            <div class="song-card-title">${song.title}</div>
                            <div class="song-card-artist">${mainArtist}</div>
                        </div>
                    `;
                    
                    listDiv.appendChild(songCard);
                });
            } else {
                listDiv.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">No songs in this playlist.</p>';
            }
            
            // Show display area
            displayDiv.style.display = 'block';
            displayDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        function closePlaylistSongs() {
            const displayDiv = document.getElementById('playlist-songs-display');
            if (displayDiv) {
                displayDiv.style.display = 'none';
            }
        }
        
        function playSong(button) {
            const songItem = button.closest('.song-item');
            const songData = {
                id: songItem.dataset.songId,
                title: songItem.dataset.songTitle,
                artist: songItem.dataset.songArtist,
                cover_art: songItem.dataset.songCover,
                duration: songItem.dataset.songDuration
            };
            
            if (window.miniPlayer) {
                window.miniPlayer.playSong(songData);
            }
        }
        
        function playSongCard(button) {
            const songCard = button.closest('.song-card');
            const songId = songCard.dataset.songId;
            const songTitle = songCard.dataset.songTitle;
            const songArtist = songCard.dataset.songArtist;
            const songCover = songCard.dataset.songCover;
            
            // Fetch song data and play
            // Use absolute URL for IP/ngrok compatibility
            let basePath = window.location.pathname;
            if (basePath.endsWith('.php') || basePath.split('/').pop().includes('.')) {
                basePath = basePath.substring(0, basePath.lastIndexOf('/') + 1);
            } else if (!basePath.endsWith('/')) {
                basePath += '/';
            }
            const apiBaseUrl = window.location.origin + basePath;
            fetch(`${apiBaseUrl}api/song-data.php?id=${songId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success || data.id) {
                        if (window.miniPlayer) {
                            window.miniPlayer.playSong(data);
                        } else if (window.player) {
                            window.player.loadSong(data);
                            window.player.play();
                        }
                    }
                })
                .catch(error => console.error('Error playing song:', error));
        }

        // Load More Songs functionality
        let currentArtistSongsPage = 1;
        const artistId = <?php echo json_encode($artist_data['id'] ?? $artist_id ?? null); ?>;
        const artistName = <?php echo json_encode($artist_name ?? ''); ?>;
        const loadMoreArtistSongsBtn = document.getElementById('loadMoreArtistSongs');
        const loadMoreArtistSongsSpinner = document.getElementById('loadMoreArtistSongsSpinner');
        const loadMoreArtistSongsText = document.getElementById('loadMoreArtistSongsText');
        const songsGrid = document.querySelector('.songs-grid');
        
        if (loadMoreArtistSongsBtn && songsGrid) {
            loadMoreArtistSongsBtn.addEventListener('click', function() {
                currentArtistSongsPage++;
                loadMoreArtistSongsSpinner.style.display = 'inline-block';
                loadMoreArtistSongsBtn.disabled = true;
                
                // Use absolute URL for IP/ngrok compatibility
                let basePath = window.location.pathname;
                if (basePath.endsWith('.php') || basePath.split('/').pop().includes('.')) {
                    basePath = basePath.substring(0, basePath.lastIndexOf('/') + 1);
                } else if (!basePath.endsWith('/')) {
                    basePath += '/';
                }
                const apiBaseUrlForSongs = window.location.origin + basePath;
                const apiUrl = `${apiBaseUrlForSongs}api/artist-songs.php?${artistId ? 'artist_id=' + artistId : 'artist_name=' + encodeURIComponent(artistName)}&page=${currentArtistSongsPage}`;
                
                fetch(apiUrl)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.songs.length > 0) {
                            data.songs.forEach(song => {
                                const songCard = createSongCard(song);
                                songsGrid.appendChild(songCard);
                            });
                            
                            if (!data.pagination.has_more) {
                                loadMoreArtistSongsBtn.style.display = 'none';
                            } else {
                                const remaining = data.pagination.total - (currentArtistSongsPage * data.pagination.per_page);
                                loadMoreArtistSongsText.textContent = `Load More Songs (${remaining > 0 ? remaining : 0} remaining)`;
                                loadMoreArtistSongsBtn.disabled = false;
                            }
                        } else {
                            loadMoreArtistSongsBtn.style.display = 'none';
                        }
                        loadMoreArtistSongsSpinner.style.display = 'none';
                    })
                    .catch(error => {
                        console.error('Error loading more songs:', error);
                        loadMoreArtistSongsSpinner.style.display = 'none';
                        loadMoreArtistSongsBtn.disabled = false;
                    });
            });
        }
        
        function createSongCard(song) {
            const songTitleSlug = song.title.toLowerCase().replace(/[^a-z0-9\s]+/gi, '').replace(/\s+/g, '-').trim();
            const songArtistSlug = (song.display_artist || song.artist || 'unknown-artist').toLowerCase().replace(/[^a-z0-9\s]+/gi, '').replace(/\s+/g, '-').trim();
            const songSlug = `${songTitleSlug}-by-${songArtistSlug}`;
            const coverArt = song.cover_art || 'assets/images/default-avatar.svg';
            
            const card = document.createElement('div');
            card.className = 'song-card';
            card.setAttribute('data-song-id', song.id);
            card.setAttribute('data-song-title', song.title);
            card.setAttribute('data-song-artist', song.display_artist || song.artist || '');
            card.setAttribute('data-song-cover', coverArt);
            card.onclick = () => window.location.href = `/song/${encodeURIComponent(songSlug)}`;
            
            card.innerHTML = `
                <div class="song-card-image">
                    <img src="${coverArt}" alt="${song.title}" onerror="this.src='assets/images/default-avatar.svg'">
                    <button class="song-card-play-btn" onclick="event.stopPropagation(); playSongCard(this)">
                        <i class="fas fa-play"></i>
                    </button>
                </div>
                <div class="song-card-info">
                    <div class="song-card-title">${song.title}</div>
                    <div class="song-card-artist">${song.display_artist || song.artist || 'Unknown Artist'}</div>
                </div>
            `;
            
            return card;
        }

        // Initialize mini player
        document.addEventListener('DOMContentLoaded', function() {
            if (window.MiniPlayer) {
                window.miniPlayer = new window.MiniPlayer();
            }
        });
    </script>
    <!-- Include Footer -->
    <?php include 'includes/footer.php'; ?>
</body>
</html>
