<?php
// Simple song storage system - Database-first with JSON fallback
function saveSong($songData) {
    // This function is deprecated - songs are saved directly to database in upload.php
    // Keeping for backward compatibility only
    return null;
}

function getSongs() {
    // Try database first
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        if (!$conn) {
            return [];
        }
        
        // Check if songs table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'songs'");
        if ($checkTable->rowCount() == 0) {
            return [];
        }
        
        $stmt = $conn->prepare("
            SELECT s.*, 
                   s.uploaded_by,
                   COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                   COALESCE(s.is_collaboration, 0) as is_collaboration,
                   COALESCE(s.plays, 0) as plays,
                   COALESCE(s.downloads, 0) as downloads,
                   COALESCE(s.upload_date, s.created_at) as uploaded_at
            FROM songs s
            LEFT JOIN users u ON s.uploaded_by = u.id
            WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
            ORDER BY s.id DESC
        ");
        $stmt->execute();
        $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format songs to match JSON structure
        foreach ($songs as &$song) {
            $song['id'] = $song['id'];
            $song['title'] = $song['title'];
            $song['artist'] = $song['artist'];
            $song['uploaded_at'] = $song['uploaded_at'];
            $song['plays'] = (int)($song['plays'] ?? 0);
            $song['downloads'] = (int)($song['downloads'] ?? 0);
        }
        
        return $songs;
    } catch (Exception $e) {
        // Database error - return empty array instead of JSON fallback
        error_log("Database error in getSongs(): " . $e->getMessage());
        return [];
    }
}

function getUserSongs($userId) {
    $songs = getSongs();
    return array_filter($songs, function($song) use ($userId) {
        return ($song['uploaded_by'] ?? $song['user_id'] ?? '') == $userId;
    });
}

// Get unique values for dropdowns from user's previous uploads
function getUserPreviousValues($userId, $field) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        // Map field names to database column names
        $fieldMap = [
            'album' => 'album_title',
            'album_title' => 'album_title',
            'producer' => 'producer',
            'composer' => 'composer',
            'lyricist' => 'lyricist',
            'record_label' => 'record_label',
            'instruments' => 'instruments',
            'tags' => 'tags',
            'genre' => 'genre'
        ];
        
        $dbField = $fieldMap[$field] ?? $field;
        
        // First check if the column exists in the songs table (case-insensitive check)
        $columns_check = $conn->query("SHOW COLUMNS FROM songs");
        $columns = $columns_check->fetchAll(PDO::FETCH_COLUMN);
        $columns_lower = array_map('strtolower', $columns);
        
        if (!in_array(strtolower($dbField), $columns_lower)) {
            // Column doesn't exist, try to create it
            error_log("Column $dbField does not exist in songs table, attempting to create it");
            try {
                $conn->exec("ALTER TABLE songs ADD COLUMN `$dbField` VARCHAR(255) NULL");
                error_log("Column $dbField created successfully");
                // Refresh columns list after creating
                $columns_check = $conn->query("SHOW COLUMNS FROM songs");
                $columns = $columns_check->fetchAll(PDO::FETCH_COLUMN);
                $columns_lower = array_map('strtolower', $columns);
            } catch (Exception $e) {
                error_log("Failed to create column $dbField: " . $e->getMessage());
                // Column doesn't exist and couldn't be created, use fallback method
                return getUserPreviousValuesFallback($userId, $field);
            }
        }
        
        // Get the actual column name (case-sensitive from database)
        $actualColumnName = $columns[array_search(strtolower($dbField), $columns_lower)];
        
        // Query database directly for better performance and accuracy
        // Get values from ALL songs uploaded by the user (for autocomplete)
        // This matches album field behavior - get all user songs, regardless of status or public flag
        
        // First, let's check if there are any songs for this user
        $count_stmt = $conn->prepare("SELECT COUNT(*) FROM songs WHERE uploaded_by = ?");
        $count_stmt->execute([$userId]);
        $total_songs = $count_stmt->fetch(PDO::FETCH_COLUMN);
        error_log("getUserPreviousValues DEBUG: User $userId has $total_songs total songs for field '$field' (dbField: '$dbField', actualColumn: '$actualColumnName')");
        
        // Check if any songs have this field populated
        $field_check_stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM songs 
            WHERE uploaded_by = ? 
            AND `$actualColumnName` IS NOT NULL 
            AND `$actualColumnName` != ''
        ");
        $field_check_stmt->execute([$userId]);
        $field_count = $field_check_stmt->fetch(PDO::FETCH_COLUMN);
        error_log("getUserPreviousValues DEBUG: User $userId has $field_count songs with non-empty $actualColumnName");
        
        // Get sample data to see what's in the database
        $sample_stmt = $conn->prepare("
            SELECT id, `$actualColumnName` 
            FROM songs 
            WHERE uploaded_by = ? 
            LIMIT 5
        ");
        $sample_stmt->execute([$userId]);
        $samples = $sample_stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("getUserPreviousValues DEBUG: Sample data for user $userId: " . json_encode($samples));
        
        $stmt = $conn->prepare("
            SELECT DISTINCT `$actualColumnName` 
            FROM songs 
            WHERE uploaded_by = ? 
            AND `$actualColumnName` IS NOT NULL 
            AND `$actualColumnName` != ''
            ORDER BY `$actualColumnName` ASC
        ");
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Debug: Log what was retrieved
        error_log("getUserPreviousValues for field '$field' (dbField: '$dbField') - userId: $userId - Found " . count($results) . " results: " . implode(', ', array_slice($results, 0, 10)));
        
        // Clean and return values
        $values = array_map('trim', $results);
        $values = array_filter($values, function($v) { return !empty($v); });
        $values = array_unique($values);
        sort($values);
        
        return array_values($values);
    } catch (Exception $e) {
        error_log("Error in getUserPreviousValues for field $field: " . $e->getMessage());
        // Fallback to old method
        return getUserPreviousValuesFallback($userId, $field);
    }
}

// Fallback method to get values from getUserSongs
function getUserPreviousValuesFallback($userId, $field) {
    try {
        $userSongs = getUserSongs($userId);
        $values = [];
        
        // Map field names for fallback
        $fieldMap = [
            'album' => 'album_title',
            'album_title' => 'album_title',
            'producer' => 'producer',
            'composer' => 'composer',
            'lyricist' => 'lyricist',
            'record_label' => 'record_label',
            'instruments' => 'instruments',
            'tags' => 'tags',
            'genre' => 'genre'
        ];
        $checkField = $fieldMap[$field] ?? $field;
        
        foreach ($userSongs as $song) {
            // Get values from all user songs (for autocomplete)
            // This matches the album field behavior
            $value = $song[$checkField] ?? $song[$field] ?? null;
            if (!empty($value) && trim($value) !== '') {
                $values[] = trim($value);
            }
        }
        
        // Remove duplicates and sort
        $values = array_unique($values);
        sort($values);
        
        return array_values($values);
    } catch (Exception $e) {
        error_log("Error in getUserPreviousValuesFallback for field $field: " . $e->getMessage());
        return [];
    }
}

// Get all unique values for dropdowns from all songs (for suggestions)
function getAllPreviousValues($field) {
    $songs = getSongs();
    $values = [];
    
    foreach ($songs as $song) {
        if (!empty($song[$field])) {
            $values[] = trim($song[$field]);
        }
    }
    
    // Remove duplicates and sort
    $values = array_unique($values);
    sort($values);
    
    return $values;
}

function getFeaturedSongs($limit = 8) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT s.*, 
                   s.uploaded_by,
                   COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                   COALESCE(s.is_collaboration, 0) as is_collaboration,
                   COALESCE(s.plays, 0) as plays,
                   COALESCE(s.downloads, 0) as downloads
            FROM songs s
            LEFT JOIN users u ON s.uploaded_by = u.id
            WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
            AND (s.is_featured = 1 OR s.is_featured IS NULL)
            ORDER BY s.id DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Database error in getFeaturedSongs(): " . $e->getMessage());
    $songs = getSongs();
    return array_slice($songs, 0, $limit);
    }
}

function getRecentSongs($limit = 6) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        if (!$conn) {
            return [];
        }
        
        // Check if songs table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'songs'");
        if ($checkTable->rowCount() == 0) {
            return [];
        }
        
        // Get recent songs directly from database, ordered by upload date
        $stmt = $conn->prepare("
            SELECT s.*, 
                   s.uploaded_by,
                   COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                   COALESCE(s.is_collaboration, 0) as is_collaboration,
                   COALESCE(s.plays, 0) as plays,
                   COALESCE(s.downloads, 0) as downloads,
                   COALESCE(s.upload_date, s.created_at, s.uploaded_at) as uploaded_at
            FROM songs s
            LEFT JOIN users u ON s.uploaded_by = u.id
            WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
            ORDER BY COALESCE(s.upload_date, s.created_at, s.uploaded_at) DESC, s.id DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format songs
        foreach ($songs as &$song) {
            $song['id'] = (int)$song['id'];
            $song['title'] = $song['title'] ?? 'Unknown Title';
            $song['artist'] = $song['artist'] ?? 'Unknown Artist';
            $song['uploaded_at'] = $song['uploaded_at'] ?? date('Y-m-d H:i:s');
            $song['plays'] = (int)($song['plays'] ?? 0);
            $song['downloads'] = (int)($song['downloads'] ?? 0);
        }
        
        return $songs;
    } catch (Exception $e) {
        error_log("Database error in getRecentSongs(): " . $e->getMessage());
        // Fallback to getSongs method
        $songs = getSongs();
        usort($songs, function($a, $b) {
            $time_a = strtotime($b['uploaded_at'] ?? '1970-01-01');
            $time_b = strtotime($a['uploaded_at'] ?? '1970-01-01');
            return $time_a - $time_b;
        });
        return array_slice($songs, 0, $limit);
    }
}

function getSongById($song_id) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT s.*, 
                   COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                   COALESCE(s.is_collaboration, 0) as is_collaboration,
                   COALESCE(s.plays, 0) as plays,
                   COALESCE(s.downloads, 0) as downloads
            FROM songs s
            LEFT JOIN users u ON s.uploaded_by = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$song_id]);
        $song = $stmt->fetch(PDO::FETCH_ASSOC);
        return $song ?: null;
    } catch (Exception $e) {
        error_log("Database error in getSongById(): " . $e->getMessage());
        // Fallback to JSON
    $songs = getSongs();
    foreach ($songs as $song) {
        if ($song['id'] == $song_id) {
            return $song;
        }
    }
    return null;
    }
}

function incrementSongPlayCount($song_id) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("UPDATE songs SET plays = COALESCE(plays, 0) + 1 WHERE id = ?");
        $stmt->execute([$song_id]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error incrementing play count: " . $e->getMessage());
        return false;
    }
}

function getAllSongs() {
    return getSongs();
}

function getTrendingSongs($limit = 8) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        if (!$conn) {
            return [];
        }
        
        // Check if songs table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'songs'");
        if ($checkTable->rowCount() == 0) {
            return [];
        }
        
        $stmt = $conn->prepare("
            SELECT s.*, 
                   s.uploaded_by,
                   COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                   COALESCE(s.is_collaboration, 0) as is_collaboration,
                   COALESCE(s.plays, 0) as plays,
                   COALESCE(s.downloads, 0) as downloads
            FROM songs s
            LEFT JOIN users u ON s.uploaded_by = u.id
            WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
            ORDER BY s.plays DESC, s.downloads DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Database error in getTrendingSongs(): " . $e->getMessage());
        return [];
    }
}

function getNewSongs($limit = 6) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        if (!$conn) {
            return [];
        }
        
        // Check if songs table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'songs'");
        if ($checkTable->rowCount() == 0) {
            return [];
        }
        
        // Check if is_featured column exists
        $hasFeaturedColumn = false;
        try {
            $colCheck = $conn->query("SHOW COLUMNS FROM songs LIKE 'is_featured'");
            $hasFeaturedColumn = $colCheck->rowCount() > 0;
        } catch (Exception $e) {
            $hasFeaturedColumn = false;
        }
        
        // If is_featured column exists, get featured songs; otherwise get newest songs
        if ($hasFeaturedColumn) {
            $stmt = $conn->prepare("
                SELECT s.*, 
                       s.uploaded_by,
                       COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                       COALESCE(s.is_collaboration, 0) as is_collaboration,
                       COALESCE(s.plays, 0) as plays,
                       COALESCE(s.downloads, 0) as downloads
                FROM songs s
                LEFT JOIN users u ON s.uploaded_by = u.id
                WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                AND s.is_featured = 1
                ORDER BY s.id DESC, s.upload_date DESC
                LIMIT ?
            ");
        } else {
            // Fallback to newest songs if is_featured column doesn't exist
            $stmt = $conn->prepare("
                SELECT s.*, 
                       s.uploaded_by,
                       COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                       COALESCE(s.is_collaboration, 0) as is_collaboration,
                       COALESCE(s.plays, 0) as plays,
                       COALESCE(s.downloads, 0) as downloads
                FROM songs s
                LEFT JOIN users u ON s.uploaded_by = u.id
                WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                ORDER BY s.id DESC, s.upload_date DESC
                LIMIT ?
            ");
        }
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Database error in getNewSongs(): " . $e->getMessage());
        return [];
    }
}

function getTopChart($limit = 5) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        if (!$conn) {
            return [];
        }
        
        // Check if songs table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'songs'");
        if ($checkTable->rowCount() == 0) {
            return [];
        }
        
        $stmt = $conn->prepare("
            SELECT s.*, 
                   s.uploaded_by,
                   COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                   COALESCE(s.is_collaboration, 0) as is_collaboration,
                   COALESCE(s.plays, 0) as plays,
                   COALESCE(s.downloads, 0) as downloads
            FROM songs s
            LEFT JOIN users u ON s.uploaded_by = u.id
            WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
            ORDER BY s.plays DESC, s.downloads DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Database error in getTopChart(): " . $e->getMessage());
        return [];
    }
}

// ========== NEWS FUNCTIONS ==========
// All news functions now use database only - JSON removed

// ========== ARTIST FUNCTIONS ==========
function getFeaturedArtists($limit = 6) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT COALESCE(s.artist, u.username, 'Unknown Artist') as name,
                   COALESCE(u.avatar, '') as avatar,
                   COUNT(s.id) as songs_count,
                   SUM(COALESCE(s.plays, 0)) as total_plays
            FROM songs s
            LEFT JOIN users u ON s.uploaded_by = u.id
            WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
            AND (COALESCE(s.artist, u.username) IS NOT NULL)
            GROUP BY COALESCE(s.artist, u.username), u.avatar
            ORDER BY total_plays DESC, songs_count DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Database error in getFeaturedArtists(): " . $e->getMessage());
        // Fallback to JSON
        $songs = getSongs();
        $artistsMap = [];
        foreach ($songs as $song) {
            $artistName = $song['artist'] ?? 'Unknown';
            if (!isset($artistsMap[$artistName])) {
                $artistsMap[$artistName] = [
                    'name' => $artistName,
                    'avatar' => '',
                    'songs_count' => 0,
                    'total_plays' => 0
                ];
            }
            $artistsMap[$artistName]['songs_count']++;
            $artistsMap[$artistName]['total_plays'] += ($song['plays'] ?? 0);
        }
        $artists = array_values($artistsMap);
        usort($artists, function($a, $b) {
            return $b['total_plays'] - $a['total_plays'];
        });
        return array_slice($artists, 0, $limit);
    }
}

function getAllArtists() {
    $songs = getSongs();
    $artistsMap = [];
    
    // Extract unique artists with their stats
    foreach ($songs as $song) {
        $artistName = $song['artist'] ?? 'Unknown';
        if (!isset($artistsMap[$artistName])) {
            $artistsMap[$artistName] = [
                'name' => $artistName,
                'avatar' => '',
                'songs_count' => 0,
                'total_plays' => 0,
                'total_downloads' => 0
            ];
        }
        $artistsMap[$artistName]['songs_count']++;
        $artistsMap[$artistName]['total_plays'] += ($song['plays'] ?? 0);
        $artistsMap[$artistName]['total_downloads'] += ($song['downloads'] ?? 0);
    }
    
    // Convert to array
    $artists = array_values($artistsMap);
    
    // Sort by name
    usort($artists, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    return $artists;
}

function getArtistSongs($artistName) {
    $songs = getSongs();
    return array_filter($songs, function($song) use ($artistName) {
        return ($song['artist'] ?? '') === $artistName;
    });
}

// News is now database-only - no JSON initialization needed
?>
