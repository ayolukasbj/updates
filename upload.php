<?php
// upload.php
// Upload music page with MDUNDO-style design

// Prevent output buffering issues
if (ob_get_level() == 0) {
    ob_start();
}

require_once 'config/config.php';
require_once 'includes/song-storage.php';

// Redirect if not logged in and verified (skip if already checked in including file)
if (!isset($editing_song)) {
    require_login();
}

$error = '';
$success = '';

// Get logged-in user's stage name (artist name) for artist field
$logged_in_user_name = '';
$is_edit_mode = false;
try {
    require_once 'config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    // First check what columns exist in users table
    $columns_check = $conn->query("SHOW COLUMNS FROM users");
    $columns = $columns_check->fetchAll(PDO::FETCH_COLUMN);
    
    // Build query based on available columns
    $select_fields = ['username'];
    if (in_array('artist', $columns)) {
        $select_fields[] = 'artist';
    }
    if (in_array('stage_name', $columns)) {
        $select_fields[] = 'stage_name';
    }
    
    // Build COALESCE based on available columns
    $coalesce_parts = [];
    if (in_array('artist', $columns)) {
        $coalesce_parts[] = 'artist';
    }
    if (in_array('stage_name', $columns)) {
        $coalesce_parts[] = 'stage_name';
    }
    $coalesce_parts[] = 'username';
    
    $coalesce_sql = 'COALESCE(' . implode(', ', $coalesce_parts) . ') as artist_name';
    $select_sql = implode(', ', $select_fields) . ', ' . $coalesce_sql;
    
    $stmt = $conn->prepare("SELECT $select_sql FROM users WHERE id = ?");
    $stmt->execute([get_user_id()]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_data) {
        $logged_in_user_name = $user_data['artist_name'] ?? $user_data['username'] ?? 'Unknown Artist';
    } else {
        $logged_in_user_name = 'Unknown Artist';
    }
} catch (Exception $e) {
    // If query fails, try simple username query
    try {
        if (isset($conn)) {
            $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([get_user_id()]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user_data) {
                $logged_in_user_name = $user_data['username'];
            } else {
                $logged_in_user_name = 'Unknown Artist';
            }
        } else {
            $logged_in_user_name = 'Unknown Artist';
        }
    } catch (Exception $e2) {
        error_log("Error getting user name in upload.php: " . $e2->getMessage());
        $logged_in_user_name = 'Unknown Artist';
    }
}

// Detect edit mode (from wrapper edit-song.php or submitted form)
if (!empty($editing_song) || !empty($_POST['edit_mode']) || !empty($_GET['id'])) {
    $is_edit_mode = true;
}

// Get edit song ID from various sources
$edit_song_id = null;
if (!empty($edit_song_data['id'])) {
    $edit_song_id = (int)$edit_song_data['id'];
} elseif (!empty($_GET['id'])) {
    $edit_song_id = (int)$_GET['id'];
} elseif (!empty($_POST['song_id'])) {
    $edit_song_id = (int)$_POST['song_id'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    // Check if track_type is "Collabo" to set is_collaboration
    $track_type = trim($_POST['track_type'] ?? '');
    $is_collaboration = ($track_type === 'Collabo') ? 1 : 0;
    $additional_artists = trim($_POST['additional_artists'] ?? '');

    // Build normalized artist list: uploader FIRST, then others (deduped)
    $primaryArtist = trim($logged_in_user_name);
    $otherArtists = [];
    if (!empty($additional_artists)) {
        // Split by common separators: comma, 'x', '&', 'feat', 'ft', 'featuring'
        $parts = preg_split('/\s*,\s*|\s+x\s+|\s*&\s*|\s*feat\.?\s*|\s*ft\.?\s*|\s*featuring\s*/i', $additional_artists);
        foreach ($parts as $p) {
            $name = trim($p);
            if ($name === '') { continue; }
            // Normalize casing
            $name = ucwords(strtolower($name));
            // Skip if same as uploader (case-insensitive)
            if (strcasecmp($name, $primaryArtist) === 0) { continue; }
            $otherArtists[] = $name;
        }
        // Deduplicate (case-insensitive)
        $seen = [];
        $deduped = [];
        foreach ($otherArtists as $n) {
            $key = strtolower($n);
            if (!isset($seen[$key])) { $seen[$key] = true; $deduped[] = $n; }
        }
        $otherArtists = $deduped;
    }

    // Final list with uploader first
    $finalArtists = array_merge([$primaryArtist], $otherArtists);
    $artist_name = implode(' x ', $finalArtists);
    // If more than one artist after normalization, mark as collaboration
    if (count($finalArtists) > 1) { $is_collaboration = 1; }
    
    $album = ''; // Album field removed - albums will be managed separately
    $genre = trim($_POST['genre'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $track_number = trim($_POST['track_number'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $lyrics = trim($_POST['lyrics'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $producer = trim($_POST['producer'] ?? '');
    $composer = trim($_POST['composer'] ?? '');
    // Author is automatically set to uploader's name
    $lyricist = trim($_POST['lyricist'] ?? '') ?: $logged_in_user_name;
    $record_label = trim($_POST['record_label'] ?? '');
    $language = trim($_POST['language'] ?? 'English');
    $mood = trim($_POST['mood'] ?? '');
    $tempo = trim($_POST['tempo'] ?? '');
    $instruments = trim($_POST['instruments'] ?? '');
    $release_date = trim($_POST['release_date'] ?? '');
    // Extract year from release_date if provided
    if (!empty($release_date) && empty($year)) {
        $year = date('Y', strtotime($release_date));
    }
    $explicit = isset($_POST['explicit']) ? 1 : 0;
    $public = isset($_POST['public']) ? 1 : 0;

    if (empty($title)) {
        $error = 'Song title is required.';
    } else {
        // Use user's profile picture as cover art
        $cover_art_path = 'assets/images/default-avatar.svg'; // Default fallback
        $user_id = get_user_id();
        
        // Get user's avatar from database
        try {
            require_once 'config/database.php';
            $db = new Database();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && !empty($user['avatar']) && file_exists($user['avatar'])) {
                $cover_art_path = $user['avatar'];
            }
        } catch (Exception $e) {
            // If database fails, check file system as fallback
        if (file_exists("uploads/profiles/user_{$user_id}.jpg")) {
            $cover_art_path = "uploads/profiles/user_{$user_id}.jpg";
        } elseif (file_exists("uploads/profiles/user_{$user_id}.png")) {
            $cover_art_path = "uploads/profiles/user_{$user_id}.png";
            }
        }

        // Handle audio file upload
        $audio_file_path = '';
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
            $audio_file = $_FILES['audio_file'];
            $allowed_audio_types = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg'];
            
            if (in_array($audio_file['type'], $allowed_audio_types)) {
                // Create uploads directory if it doesn't exist
                $upload_dir = 'uploads/audio/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generate unique filename
                $file_extension = pathinfo($audio_file['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '_audio.' . $file_extension;
                $audio_file_path = $upload_dir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($audio_file['tmp_name'], $audio_file_path)) {
                    $audio_file_path = $audio_file_path; // Success
                } else {
                    $audio_file_path = ''; // Failed
                }
            }
        }

        // Create song data with all user-entered information
        $songData = [
            'id' => uniqid(),
            'title' => $title,
            'artist' => $artist_name,
            'album' => $album,
            'genre' => $genre,
            'year' => $year,
            'track_number' => $track_number,
            'description' => $description,
            'lyrics' => $lyrics,
            'tags' => $tags,
            'producer' => $producer,
            'composer' => $composer,
            'lyricist' => $lyricist,
            'record_label' => $record_label,
            'language' => $language,
            'mood' => $mood,
            'tempo' => $tempo,
            'instruments' => $instruments,
            'explicit' => $explicit,
            'public' => $public,
            'duration' => '3:45', // Default duration - will be converted to seconds before saving
            'plays' => 0,
            'downloads' => 0,
            'favorites' => 0,
            'file_size' => isset($_FILES['audio_file']) ? $_FILES['audio_file']['size'] : 0,
            'audio_file' => $audio_file_path,
            'cover_art' => $cover_art_path,
            'uploaded_by' => get_user_id(),
            'uploaded_at' => date('Y-m-d H:i:s'),
            'status' => 'active',
            'featured' => 0,
            'key' => '', // Could be added as a form field
            'bpm' => '', // Could be added as a form field
            'copyright' => '', // Could be added as a form field
            'isrc' => '', // International Standard Recording Code
            'upc' => '', // Universal Product Code
            'release_date' => $year ? $year . '-01-01' : '', // Convert year to full date
            'original_filename' => isset($_FILES['audio_file']) ? $_FILES['audio_file']['name'] : '',
            'cover_art_original' => 'profile_picture',
            'metadata_extracted' => 0, // Flag to indicate if metadata was extracted from audio file
            'quality' => 'high', // Could be determined from file analysis
            'bitrate' => '', // Could be extracted from audio file
            'sample_rate' => '', // Could be extracted from audio file
            'channels' => '', // Could be extracted from audio file
            'format' => isset($_FILES['audio_file']) ? pathinfo($_FILES['audio_file']['name'], PATHINFO_EXTENSION) : '',
            'last_played' => null,
            'last_downloaded' => null,
            'rating' => 0,
            'reviews_count' => 0,
            'shares_count' => 0,
            'comments_count' => 0,
            'playlist_adds' => 0,
            'featured_in' => [], // Array of playlists/collections where song is featured
            'related_songs' => [], // Array of related song IDs
            'similar_artists' => [], // Array of similar artist names
            'geographic_data' => [
                'country' => '',
                'region' => '',
                'city' => ''
            ],
            'social_links' => [
                'spotify' => '',
                'apple_music' => '',
                'youtube' => '',
                'soundcloud' => '',
                'bandcamp' => ''
            ],
            'monetization' => [
                'price' => 0,
                'currency' => 'USD',
                'free_download' => 1,
                'premium_only' => 0
            ],
            'analytics' => [
                'daily_plays' => [],
                'monthly_plays' => [],
                'demographics' => [],
                'device_types' => [],
                'locations' => []
            ]
        ];

        // Detect edit mode
        $is_edit_mode = (!empty($_POST['edit_mode']) || !empty($_POST['song_id']) || !empty($editing_song));
        $edit_song_id = !empty($_POST['song_id']) ? (int)$_POST['song_id'] : (!empty($edit_song_data['id']) ? (int)$edit_song_data['id'] : null);
        
        if ($is_edit_mode && empty($edit_song_id)) {
            $error = 'Edit mode detected but song ID is missing.';
        } elseif ($is_edit_mode && $edit_song_id) {
            // Verify user owns this song
            $verifyStmt = $conn->prepare("SELECT id, file_path, uploaded_by FROM songs WHERE id = ? AND uploaded_by = ?");
            $verifyStmt->execute([$edit_song_id, $user_id]);
            $existing_song = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing_song) {
                $error = 'Song not found or you do not have permission to edit it.';
            }
        }
        
        // Save song to database
        if (empty($error)) {
        try {
            // Check if songs table exists first
            $songsTableExists = false;
            try {
                $checkSongs = $conn->query("SHOW TABLES LIKE 'songs'");
                $songsTableExists = $checkSongs->rowCount() > 0;
            } catch (Exception $e) {
                $songsTableExists = false;
            }
            
            if (!$songsTableExists) {
                throw new PDOException("Table 'songs' doesn't exist. Please run fix-songs-table.php to create it.");
            }
            
            // Check if artists table exists
            $artist_id = null;
            $artistsTableExists = false;
            try {
                $checkArtists = $conn->query("SHOW TABLES LIKE 'artists'");
                $artistsTableExists = $checkArtists->rowCount() > 0;
            } catch (Exception $e) {
                $artistsTableExists = false;
            }
            
            if ($artistsTableExists) {
                // Get or create artist record
                $stmt = $conn->prepare("SELECT id FROM artists WHERE name = ? LIMIT 1");
                $stmt->execute([$artist_name]);
                $artist_record = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($artist_record) {
                    $artist_id = $artist_record['id'];
                } else {
                    // Create new artist record
                    $stmt = $conn->prepare("
                        INSERT INTO artists (name, avatar, bio, created_at) 
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$artist_name, $cover_art_path, '']);
                    $artist_id = $conn->lastInsertId();
                }
            } else {
                // Artists table doesn't exist - skip artist_id (will be null)
                $artist_id = null;
            }
            
            // If editing, UPDATE instead of INSERT
            if ($is_edit_mode && !empty($edit_song_id) && !empty($existing_song)) {
                // Get old album_id BEFORE any updates (needed for album track count logic)
                $old_album_id = null;
                if (isset($existing_song['album_id'])) {
                    $old_album_id = $existing_song['album_id'];
                } else {
                    // Check if album_id column exists and get current value
                    $columns_check = $conn->query("SHOW COLUMNS FROM songs LIKE 'album_id'");
                    if ($columns_check->rowCount() > 0) {
                        $old_album_stmt = $conn->prepare("SELECT album_id FROM songs WHERE id = ?");
                        $old_album_stmt->execute([$edit_song_id]);
                        $old_album_result = $old_album_stmt->fetch(PDO::FETCH_ASSOC);
                        $old_album_id = $old_album_result['album_id'] ?? null;
                    }
                }
                error_log("Edit mode - old_album_id: " . ($old_album_id ?? 'NULL'));
                
                // Use existing file path if no new file uploaded
                $final_audio_path = !empty($audio_file_path) ? $audio_file_path : ($existing_song['file_path'] ?? '');
                $final_file_size = !empty($audio_file_path) ? $songData['file_size'] : ($existing_song['file_size'] ?? $songData['file_size']);
                // Use existing cover art if no new cover art uploaded
                $final_cover_art = !empty($cover_art_path) && $cover_art_path !== 'assets/images/default-avatar.svg' ? $cover_art_path : ($existing_song['cover_art'] ?? $cover_art_path);
                
                // Get list of existing columns in songs table
                $columns_check = $conn->query("SHOW COLUMNS FROM songs");
                $existing_columns = $columns_check->fetchAll(PDO::FETCH_COLUMN);
                $existing_columns_lower = array_map('strtolower', $existing_columns);
                
                // Ensure required columns exist (create them if they don't)
                $required_columns = [
                    'producer' => 'VARCHAR(255) NULL',
                    'composer' => 'VARCHAR(255) NULL',
                    'lyricist' => 'VARCHAR(255) NULL',
                    'record_label' => 'VARCHAR(255) NULL',
                    'release_date' => 'DATE NULL',
                    'track_type' => 'VARCHAR(50) NULL'
                ];
                foreach ($required_columns as $col => $colType) {
                    if (!in_array(strtolower($col), $existing_columns_lower)) {
                        try {
                            $conn->exec("ALTER TABLE songs ADD COLUMN `$col` $colType");
                            error_log("Created column $col in songs table (UPDATE)");
                            // Refresh columns list
                            $columns_check = $conn->query("SHOW COLUMNS FROM songs");
                            $existing_columns = $columns_check->fetchAll(PDO::FETCH_COLUMN);
                            $existing_columns_lower = array_map('strtolower', $existing_columns);
                        } catch (Exception $e) {
                            error_log("Failed to create column $col: " . $e->getMessage());
                        }
                    }
                }
                
                // Build UPDATE query dynamically based on existing columns
                $updateFields = [];
                $updateParams = [];
                
                // Always include these core fields
                // Convert duration from string format "MM:SS" to seconds (integer) for UPDATE
                $duration_seconds_update = 0;
                $duration_value_update = $songData['duration'] ?? ($existing_song['duration'] ?? '0:00');
                if (is_string($duration_value_update) && strpos($duration_value_update, ':') !== false) {
                    // Parse string format like "3:45" or "3:45:30"
                    $parts = explode(':', trim($duration_value_update));
                    if (count($parts) === 2) {
                        // Format: MM:SS - convert to seconds
                        $duration_seconds_update = (int)$parts[0] * 60 + (int)$parts[1];
                    } elseif (count($parts) === 3) {
                        // Format: HH:MM:SS - convert to seconds
                        $duration_seconds_update = (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (int)$parts[2];
                    } else {
                        $duration_seconds_update = (int)$duration_value_update;
                    }
                } elseif (is_numeric($duration_value_update)) {
                    // Already in seconds
                    $duration_seconds_update = (int)$duration_value_update;
                } else {
                    $duration_seconds_update = (int)$duration_value_update;
                }
                
                $coreFields = [
                    'title' => $title,
                    'album_title' => $album ?: null,
                    'genre' => $genre,
                    'release_year' => $year ?: null,
                    'file_path' => $final_audio_path,
                    'cover_art' => $final_cover_art,
                    'file_size' => $final_file_size,
                    'duration' => $duration_seconds_update // Store as integer seconds
                ];
                
                // Conditionally include fields based on column existence
                $optionalFields = [
                    'artist' => $artist_name,
                    'is_collaboration' => $is_collaboration,
                    'artist_id' => $artist_id,
                    'track_number' => $track_number ?: null,
                    'description' => $description ?: null,
                    'lyrics' => $lyrics ?: null,
                    'producer' => $producer ?: null,
                    'composer' => $composer ?: null,
                    'lyricist' => $lyricist ?: null,
                    'record_label' => $record_label ?: null,
                    'language' => $language ?: 'English',
                    'mood' => $mood ?: null,
                    'tempo' => $tempo ?: null,
                    'instruments' => $instruments ?: null,
                    'tags' => $tags ?: null,
                    'track_type' => $track_type ?: null,
                    'release_date' => !empty($release_date) ? $release_date : (!empty($year) ? $year . '-01-01' : null)
                ];
                
                // Add core fields
                foreach ($coreFields as $field => $value) {
                    if (in_array(strtolower($field), $existing_columns_lower)) {
                        $updateFields[] = "$field = ?";
                        $updateParams[] = $value;
                    }
                }
                
                // Add optional fields if columns exist
                foreach ($optionalFields as $field => $value) {
                    if (in_array(strtolower($field), $existing_columns_lower)) {
                        // Convert empty strings to null for database consistency
                        $dbValue = ($value === '' || $value === null) ? null : $value;
                        $updateFields[] = "$field = ?";
                        $updateParams[] = $dbValue;
                        // Log important fields
                        if (in_array($field, ['producer', 'composer', 'lyricist', 'record_label', 'release_date', 'track_type'])) {
                            error_log("UPDATE field '$field': value = " . ($dbValue ?? 'NULL'));
                        }
                    } else {
                        // Log if field is missing
                        if (in_array($field, ['producer', 'composer', 'lyricist', 'record_label', 'release_date', 'track_type'])) {
                            error_log("UPDATE field '$field' NOT included - column doesn't exist in database");
                        }
                    }
                }
                
                // Add WHERE clause parameters
                $updateParams[] = $edit_song_id;
                $updateParams[] = $user_id;
                
                // Build the UPDATE query
                $updateSql = "UPDATE songs SET " . implode(", ", $updateFields) . " WHERE id = ? AND uploaded_by = ?";
                
                try {
                    $stmt = $conn->prepare($updateSql);
                    $result = $stmt->execute($updateParams);
                    if ($result) {
                        $song_id = $edit_song_id; // Use existing ID
                        $success = "Song updated successfully!";
                        error_log("Song updated successfully! Song ID: $song_id, uploaded_by: $user_id");
                        
                        // Re-fetch updated song data for display
                        $stmt = $conn->prepare("SELECT * FROM songs WHERE id = ?");
                        $stmt->execute([$song_id]);
                        $updated_song = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($updated_song) {
                            $songData['title'] = $updated_song['title'];
                            $songData['artist'] = $updated_song['artist'] ?? '';
                            $songData['album'] = $updated_song['album_title'] ?? '';
                            $songData['genre'] = $updated_song['genre'] ?? '';
                            $songData['year'] = $updated_song['release_year'] ?? '';
                            $songData['track_number'] = $updated_song['track_number'] ?? '';
                            $songData['description'] = $updated_song['description'] ?? '';
                            $songData['lyrics'] = $updated_song['lyrics'] ?? '';
                            $songData['producer'] = $updated_song['producer'] ?? '';
                            $songData['composer'] = $updated_song['composer'] ?? '';
                            $songData['lyricist'] = $updated_song['lyricist'] ?? '';
                            $songData['record_label'] = $updated_song['record_label'] ?? '';
                            $songData['language'] = $updated_song['language'] ?? 'English';
                            $songData['mood'] = $updated_song['mood'] ?? '';
                            $songData['tempo'] = $updated_song['tempo'] ?? '';
                            $songData['instruments'] = $updated_song['instruments'] ?? '';
                            $songData['tags'] = $updated_song['tags'] ?? '';
                            $songData['track_type'] = $updated_song['track_type'] ?? '';
                            // Update other fields from $updated_song as needed
                        }
                        
                        // Save user preferences (album, producer, etc.) for autocomplete
                        try {
                            saveUserPreferences($user_id, [
                                'album' => $album,
                                'producer' => $producer,
                                'composer' => $composer,
                                'lyricist' => $lyricist,
                                'record_label' => $record_label,
                                'genre' => $genre,
                                'instruments' => $instruments,
                                'tags' => $tags
                            ]);
                            error_log("User preferences saved successfully for user_id: $user_id");
                        } catch (Exception $e) {
                            error_log("Error saving user preferences: " . $e->getMessage());
                        }
                    } else {
                        $error = "Failed to update song. Please try again.";
                        error_log("Song update failed! Song ID: $edit_song_id, uploaded_by: $user_id");
                    }
                } catch (PDOException $e) {
                    // Log the error but don't try fallback - we already checked columns
                    error_log("Song update error (after column check): " . $e->getMessage());
                    $error = 'Database error: ' . $e->getMessage();
                    error_log("Song update failed! Song ID: $edit_song_id, uploaded_by: $user_id, Error: " . $e->getMessage());
                }
            } else {
                // INSERT new song - Build INSERT query dynamically based on existing columns
                // Get list of existing columns in songs table
                $columns_check = $conn->query("SHOW COLUMNS FROM songs");
                $existing_columns = $columns_check->fetchAll(PDO::FETCH_COLUMN);
                $existing_columns_lower = array_map('strtolower', $existing_columns);
                
                // Ensure required columns exist (create them if they don't)
                $required_columns = [
                    'producer' => 'VARCHAR(255) NULL',
                    'composer' => 'VARCHAR(255) NULL',
                    'lyricist' => 'VARCHAR(255) NULL',
                    'record_label' => 'VARCHAR(255) NULL',
                    'release_date' => 'DATE NULL',
                    'track_type' => 'VARCHAR(50) NULL'
                ];
                foreach ($required_columns as $col => $colType) {
                    if (!in_array(strtolower($col), $existing_columns_lower)) {
                        try {
                            $conn->exec("ALTER TABLE songs ADD COLUMN `$col` $colType");
                            error_log("Created column $col in songs table");
                            // Refresh columns list
                            $columns_check = $conn->query("SHOW COLUMNS FROM songs");
                            $existing_columns = $columns_check->fetchAll(PDO::FETCH_COLUMN);
                            $existing_columns_lower = array_map('strtolower', $existing_columns);
                        } catch (Exception $e) {
                            error_log("Failed to create column $col: " . $e->getMessage());
                        }
                    }
                }
                
                // Build INSERT query dynamically based on existing columns
                $insertFields = [];
                $insertParams = [];
                $insertPlaceholders = [];
                
                // Always include these core fields (if columns exist)
                // Convert duration from string format "MM:SS" to seconds (integer)
                $duration_seconds = 0;
                $duration_value = $songData['duration'] ?? '0:00';
                if (is_string($duration_value) && strpos($duration_value, ':') !== false) {
                    // Parse string format like "3:45" or "3:45:30"
                    $parts = explode(':', trim($duration_value));
                    if (count($parts) === 2) {
                        // Format: MM:SS - convert to seconds
                        $duration_seconds = (int)$parts[0] * 60 + (int)$parts[1];
                    } elseif (count($parts) === 3) {
                        // Format: HH:MM:SS - convert to seconds
                        $duration_seconds = (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (int)$parts[2];
                    } else {
                        $duration_seconds = (int)$duration_value;
                    }
                } elseif (is_numeric($duration_value)) {
                    // Already in seconds
                    $duration_seconds = (int)$duration_value;
                } else {
                    $duration_seconds = (int)$duration_value;
                }
                
                $coreFields = [
                    'title' => $title,
                    'album_title' => $album ?: null,
                    'genre' => $genre,
                    'release_year' => $year ?: null,
                    'file_path' => $audio_file_path,
                    'cover_art' => $cover_art_path,
                    'file_size' => $songData['file_size'],
                    'duration' => $duration_seconds, // Store as integer seconds
                    'lyrics' => $lyrics ?: null,
                    'is_explicit' => $explicit,
                    'status' => 'active',
                    'is_featured' => 0,
                    'upload_date' => 'NOW()',
                    'uploaded_by' => $user_id,
                    'plays' => 0,
                    'downloads' => 0
                ];
                
                // Conditionally include fields based on column existence
                $optionalFields = [
                    'artist' => $artist_name,
                    'is_collaboration' => $is_collaboration,
                    'artist_id' => $artist_id,
                    'track_number' => $track_number ?: null,
                    'description' => $description ?: null,
                    'producer' => $producer ?: null,
                    'composer' => $composer ?: null,
                    'lyricist' => $lyricist ?: null,
                    'record_label' => $record_label ?: null,
                    'language' => $language ?: 'English',
                    'mood' => $mood ?: null,
                    'tempo' => $tempo ?: null,
                    'instruments' => $instruments ?: null,
                    'tags' => $tags ?: null,
                    'release_date' => !empty($release_date) ? $release_date : (!empty($year) ? $year . '-01-01' : null),
                    'track_type' => $track_type ?: null
                ];
                
                // Add core fields
                foreach ($coreFields as $field => $value) {
                    if (in_array(strtolower($field), $existing_columns_lower)) {
                        if ($field === 'upload_date' && $value === 'NOW()') {
                            $insertFields[] = $field;
                            $insertPlaceholders[] = 'NOW()';
                        } else {
                            $insertFields[] = $field;
                            $insertPlaceholders[] = '?';
                            $insertParams[] = $value;
                        }
                    }
                }
                
                // Add optional fields if columns exist
                foreach ($optionalFields as $field => $value) {
                    if (in_array(strtolower($field), $existing_columns_lower)) {
                        // Convert empty strings to null for database consistency
                        $dbValue = ($value === '' || $value === null) ? null : $value;
                        $insertFields[] = $field;
                        $insertPlaceholders[] = '?';
                        $insertParams[] = $dbValue;
                        // Log important fields
                        if (in_array($field, ['producer', 'composer', 'lyricist', 'record_label', 'release_date', 'track_type'])) {
                            error_log("INSERT field '$field': value = " . ($dbValue ?? 'NULL'));
                        }
                    } else {
                        // Log if field is missing
                        if (in_array($field, ['producer', 'composer', 'lyricist', 'record_label', 'release_date', 'track_type'])) {
                            error_log("INSERT field '$field' NOT included - column doesn't exist in database");
                        }
                    }
                }
                
                // Build the INSERT query
                $insertSql = "INSERT INTO songs (" . implode(", ", $insertFields) . ") VALUES (" . implode(", ", $insertPlaceholders) . ")";
                
                // Log upload attempt
                error_log("Uploading song: title=$title, user_id=$user_id");
                error_log("INSERT SQL: $insertSql");
                error_log("INSERT Fields: " . implode(', ', $insertFields));
                error_log("INSERT Params count: " . count($insertParams));
                // Log specific field values - find the param index by matching field names
                $paramIndex = 0;
                foreach ($insertFields as $fieldIdx => $fieldName) {
                    if ($insertPlaceholders[$fieldIdx] === '?') {
                        if ($fieldName === 'producer') {
                            error_log("Producer value in INSERT: " . ($insertParams[$paramIndex] ?? 'NULL'));
                        }
                        if ($fieldName === 'composer') {
                            error_log("Composer value in INSERT: " . ($insertParams[$paramIndex] ?? 'NULL'));
                        }
                        if ($fieldName === 'lyricist') {
                            error_log("Lyricist value in INSERT: " . ($insertParams[$paramIndex] ?? 'NULL'));
                        }
                        if ($fieldName === 'record_label') {
                            error_log("Record Label value in INSERT: " . ($insertParams[$paramIndex] ?? 'NULL'));
                        }
                        $paramIndex++;
                    }
                }
                error_log("Producer from POST: " . ($producer ?? 'EMPTY'));
                error_log("Composer from POST: " . ($composer ?? 'EMPTY'));
                error_log("Lyricist from POST: " . ($lyricist ?? 'EMPTY'));
                error_log("Record Label from POST: " . ($record_label ?? 'EMPTY'));
                
                // Execute INSERT
                $result = false;
                try {
                    $stmt = $conn->prepare($insertSql);
                    $result = $stmt->execute($insertParams);
                } catch (PDOException $e) {
                    error_log("INSERT error: " . $e->getMessage());
                    throw $e;
                }
            } // Close the else block (INSERT path)
            
            if ($result) {
                // Get song_id (either from INSERT or from UPDATE)
                if (empty($song_id)) {
                    $song_id = $is_edit_mode ? $edit_song_id : $conn->lastInsertId();
                }
                $action = $is_edit_mode ? 'updated' : 'uploaded';
                error_log("Song {$action} successfully! Song ID: $song_id, uploaded_by: $user_id");
                
                // Save collaborator mappings if provided
                try {
                    $selectedIdsCsv = trim($_POST['selected_artist_ids'] ?? '');
                    if (!empty($selectedIdsCsv)) {
                        $ids = array_filter(array_map('trim', explode(',', $selectedIdsCsv)), function($v){ return $v !== ''; });
                        // Deduplicate and exclude uploader
                        $ids = array_values(array_unique(array_map('intval', $ids)));
                        $ids = array_filter($ids, function($id) use ($user_id) { return $id > 0 && $id !== (int)$user_id; });
                        if (!empty($ids)) {
                            // Ensure mapping table exists
                            $conn->exec("CREATE TABLE IF NOT EXISTS song_collaborators (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                song_id INT NOT NULL,
                                user_id INT NOT NULL,
                                added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                                INDEX idx_song (song_id),
                                INDEX idx_user (user_id)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                            
                            // If editing, delete old mappings first
                            if ($is_edit_mode) {
                                $delStmt = $conn->prepare("DELETE FROM song_collaborators WHERE song_id = ?");
                                $delStmt->execute([$song_id]);
                            }
                            
                            // Insert new mappings
                            $insertMap = $conn->prepare("INSERT INTO song_collaborators (song_id, user_id) VALUES (?, ?)");
                            foreach ($ids as $cid) {
                                $insertMap->execute([$song_id, $cid]);
                            }
                            // If collaborators exist, force is_collaboration flag
                            try {
                                $conn->exec("ALTER TABLE songs ADD COLUMN IF NOT EXISTS is_collaboration TINYINT(1) DEFAULT 0");
                            } catch (Exception $ignored) {}
                            $upd = $conn->prepare("UPDATE songs SET is_collaboration = 1 WHERE id = ?");
                            $upd->execute([$song_id]);
                        } else if ($is_edit_mode) {
                            // If editing and no collaborators, remove mappings and unset flag
                            $delStmt = $conn->prepare("DELETE FROM song_collaborators WHERE song_id = ?");
                            $delStmt->execute([$song_id]);
                            try {
                                $conn->exec("ALTER TABLE songs ADD COLUMN IF NOT EXISTS is_collaboration TINYINT(1) DEFAULT 0");
                            } catch (Exception $ignored) {}
                            $upd = $conn->prepare("UPDATE songs SET is_collaboration = 0 WHERE id = ?");
                            $upd->execute([$song_id]);
                        }
                    } else if ($is_edit_mode) {
                        // If editing and no collaborator IDs provided, remove all mappings
                        $delStmt = $conn->prepare("DELETE FROM song_collaborators WHERE song_id = ?");
                        $delStmt->execute([$song_id]);
                        try {
                            $conn->exec("ALTER TABLE songs ADD COLUMN IF NOT EXISTS is_collaboration TINYINT(1) DEFAULT 0");
                        } catch (Exception $ignored) {}
                        $upd = $conn->prepare("UPDATE songs SET is_collaboration = 0 WHERE id = ?");
                        $upd->execute([$song_id]);
                    }
                } catch (Exception $e) {
                    error_log('Warning: failed to save collaborator mappings: ' . $e->getMessage());
                }
                
                // Save user preferences (album, producer, etc.) for autocomplete
                try {
                    if (function_exists('saveUserPreferences')) {
                        saveUserPreferences($user_id, [
                            'album' => $album,
                            'producer' => $producer,
                            'composer' => $composer,
                            'lyricist' => $lyricist,
                            'record_label' => $record_label,
                            'genre' => $genre,
                            'instruments' => $instruments,
                            'tags' => $tags
                        ]);
                        error_log("User preferences saved successfully for user_id: $user_id (new upload)");
                    }
                } catch (Exception $e) {
                    error_log("Error saving user preferences: " . $e->getMessage());
                }
                
                // Handle album creation/update
                if (!empty($album)) {
                    try {
                        // Check if albums table exists, if not create it
                        $conn->exec("CREATE TABLE IF NOT EXISTS albums (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            title VARCHAR(255) NOT NULL,
                            artist_id INT,
                            uploaded_by INT,
                            release_date DATE,
                            cover_art VARCHAR(255),
                            description TEXT,
                            genre VARCHAR(100),
                            total_tracks INT DEFAULT 0,
                            total_duration INT DEFAULT 0,
                            total_plays BIGINT DEFAULT 0,
                            total_downloads BIGINT DEFAULT 0,
                            is_featured BOOLEAN DEFAULT FALSE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_artist (artist_id),
                            INDEX idx_uploaded_by (uploaded_by),
                            INDEX idx_title (title)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                        
                        // Check if album_id column exists in songs table
                        $columns_check = $conn->query("SHOW COLUMNS FROM songs LIKE 'album_id'");
                        if ($columns_check->rowCount() == 0) {
                            $conn->exec("ALTER TABLE songs ADD COLUMN album_id INT NULL");
                        }
                        
                        // Get current album_id for the song (if editing) - use the one we got earlier
                        // If not set earlier (new song), get it now
                        if (!isset($old_album_id) && $is_edit_mode && $song_id) {
                            $old_album_stmt = $conn->prepare("SELECT album_id FROM songs WHERE id = ?");
                            $old_album_stmt->execute([$song_id]);
                            $old_album_result = $old_album_stmt->fetch(PDO::FETCH_ASSOC);
                            $old_album_id = $old_album_result['album_id'] ?? null;
                        }
                        
                        // Check if album exists for this user/artist
                        $album_stmt = $conn->prepare("
                            SELECT id FROM albums 
                            WHERE title = ? AND (uploaded_by = ? OR artist_id = ?)
                            LIMIT 1
                        ");
                        $album_stmt->execute([$album, $user_id, $artist_id ?? $user_id]);
                        $existing_album = $album_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $album_id = null;
                        
                        if ($existing_album) {
                            // Album exists
                            $album_id = $existing_album['id'];
                            
                            // Only update track count if:
                            // 1. New song (not editing) - always add to album
                            // 2. Editing but song was NOT in this album before (old_album_id != album_id or old_album_id is null)
                            if (!$is_edit_mode || $old_album_id != $album_id) {
                                error_log("Album '$album' exists (ID: $album_id), adding/updating song in album (is_edit_mode: $is_edit_mode, old_album_id: " . ($old_album_id ?? 'NULL') . ", new_album_id: $album_id)");
                                
                                // Update album track count
                                $update_album_stmt = $conn->prepare("
                                    UPDATE albums 
                                    SET total_tracks = total_tracks + 1,
                                        updated_at = NOW()
                                    WHERE id = ?
                                ");
                                $update_album_stmt->execute([$album_id]);
                                error_log("Incremented track count for album ID $album_id");
                            } else {
                                error_log("Album '$album' exists (ID: $album_id), song already in this album (old_album_id: $old_album_id) - no track count update needed");
                            }
                        } else {
                            // Album doesn't exist - create it
                            error_log("Album '$album' doesn't exist, creating new album");
                            
                            // Use song cover art if available, otherwise user avatar
                            $album_cover_art = $cover_art_path ?? 'assets/images/default-avatar.svg';
                            
                            // Create album
                            $create_album_stmt = $conn->prepare("
                                INSERT INTO albums (title, artist_id, uploaded_by, release_date, cover_art, genre, total_tracks, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
                            ");
                            $album_release_date = !empty($release_date) ? $release_date : (!empty($year) ? $year . '-01-01' : null);
                            $create_album_stmt->execute([
                                $album,
                                $artist_id ?? $user_id,
                                $user_id,
                                $album_release_date,
                                $album_cover_art,
                                $genre,
                            ]);
                            $album_id = $conn->lastInsertId();
                            error_log("Created new album '$album' with ID: $album_id");
                        }
                        
                        // If editing and song was in a different album, remove it from old album
                        if ($is_edit_mode && $old_album_id && $old_album_id != $album_id) {
                            error_log("Song was in album ID $old_album_id, removing from old album");
                            $decrement_old_album = $conn->prepare("
                                UPDATE albums 
                                SET total_tracks = GREATEST(0, total_tracks - 1),
                                    updated_at = NOW()
                                WHERE id = ?
                            ");
                            $decrement_old_album->execute([$old_album_id]);
                        }
                        
                        // Update song with album_id
                        if ($album_id) {
                            $update_song_album = $conn->prepare("UPDATE songs SET album_id = ? WHERE id = ?");
                            $update_song_album->execute([$album_id, $song_id]);
                            error_log("Linked song ID $song_id to album ID $album_id");
                        }
                    } catch (Exception $e) {
                        error_log("Error handling album creation/update: " . $e->getMessage());
                        // Don't fail the upload if album creation fails
                    }
                } elseif ($is_edit_mode && !empty($song_id)) {
                    // If editing and album is cleared (empty), remove song from album
                    try {
                        // Get current album_id
                        $old_album_stmt = $conn->prepare("SELECT album_id FROM songs WHERE id = ?");
                        $old_album_stmt->execute([$song_id]);
                        $old_album_result = $old_album_stmt->fetch(PDO::FETCH_ASSOC);
                        $old_album_id = $old_album_result['album_id'] ?? null;
                        
                        if ($old_album_id) {
                            error_log("Removing song ID $song_id from album ID $old_album_id");
                            
                            // Remove song from album
                            $update_song_album = $conn->prepare("UPDATE songs SET album_id = NULL WHERE id = ?");
                            $update_song_album->execute([$song_id]);
                            
                            // Decrement album track count
                            $decrement_album = $conn->prepare("
                                UPDATE albums 
                                SET total_tracks = GREATEST(0, total_tracks - 1),
                                    updated_at = NOW()
                                WHERE id = ?
                            ");
                            $decrement_album->execute([$old_album_id]);
                        }
                    } catch (Exception $e) {
                        error_log("Error removing song from album: " . $e->getMessage());
                    }
                }
                
                // Automatically upgrade user to artist role if they upload a song (only for new uploads)
                if (!$is_edit_mode) {
                    $stmt = $conn->prepare("UPDATE users SET role = 'artist' WHERE id = ? AND role = 'user'");
                    $stmt->execute([$user_id]);
                }
                
                // Redirect based on mode
                // If we're being included by edit-song.php, clean output buffer and redirect properly
                if (!empty($editing_song)) {
                    // We're included by edit-song.php - set session message and redirect
                    $_SESSION['song_updated_message'] = "Song updated successfully!";
                    // End all output buffering
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    // Redirect to edit page with updated flag
                    if (!headers_sent()) {
                        header("Location: edit-song.php?id=$song_id&updated=1");
                        exit;
                    } else {
                        // Headers already sent, use JavaScript redirect
                        echo '<script>window.location.href = "edit-song.php?id=' . $song_id . '&updated=1";</script>';
                        exit;
                    }
                } elseif ($is_edit_mode) {
                    // Direct access in edit mode - redirect normally
                    // End all output buffering
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    if (!headers_sent()) {
                        header("Location: edit-song.php?id=$song_id&updated=1");
                        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                        header('Pragma: no-cache');
                        exit;
                    } else {
                        echo '<script>window.location.href = "edit-song.php?id=' . $song_id . '&updated=1";</script>';
                        exit;
                    }
                } else {
                    // New upload - redirect to artist profile music tab with cache buster
                    $redirect_url = 'artist-profile-mobile.php?tab=music&uploaded=1&_=' . uniqid() . '&t=' . time();
                    // End all output buffering
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    if (!headers_sent()) {
                        header('Location: ' . $redirect_url);
                        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                        header('Pragma: no-cache');
                        exit;
                    } else {
                        echo '<script>window.location.href = "' . $redirect_url . '";</script>';
                        exit;
                    }
                }
            } else {
                $error = $is_edit_mode ? 'Failed to update song. Please try again.' : 'Failed to save song to database. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
            error_log("CRITICAL: Song upload failed - " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
            error_log("Song data: " . print_r($songData, true));
            $error .= '<br><br><strong>Please run <a href="fix-songs-table.php" style="color: #fff; text-decoration: underline;">fix-songs-table.php</a> to fix your database.</strong>';
        }
        } // Close if (empty($error)) block
    }
}

// Helper function to format file size
if (!function_exists('formatFileSize')) {
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
}

// Function to save user preferences
if (!function_exists('saveUserPreferences')) {
function saveUserPreferences($user_id, $preferences) {
    try {
        require_once 'config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        // Create user_preferences table if it doesn't exist
        $conn->exec("
            CREATE TABLE IF NOT EXISTS user_preferences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                preference_type VARCHAR(50) NOT NULL,
                preference_value VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_preference (user_id, preference_type, preference_value),
                INDEX idx_user_id (user_id),
                INDEX idx_preference_type (preference_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Save each preference
        foreach ($preferences as $type => $value) {
            if (!empty($value)) {
                $value = trim($value);
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO user_preferences (user_id, preference_type, preference_value)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
                    ");
                    $stmt->execute([$user_id, $type, $value]);
                } catch (PDOException $e) {
                    // Ignore duplicate key errors
                    if ($e->getCode() != 23000) {
                        error_log("Error saving preference $type: " . $e->getMessage());
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error in saveUserPreferences: " . $e->getMessage());
    }
}
}

// Function to get user preferences
if (!function_exists('getUserPreferences')) {
function getUserPreferences($user_id, $preference_type = null) {
    try {
        require_once 'config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        // Check if table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'user_preferences'");
        if ($tableCheck->rowCount() == 0) {
            return [];
        }
        
        if ($preference_type) {
            $stmt = $conn->prepare("
                SELECT DISTINCT preference_value
                FROM user_preferences
                WHERE user_id = ? AND preference_type = ?
                ORDER BY updated_at DESC
                LIMIT 20
            ");
            $stmt->execute([$user_id, $preference_type]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $stmt = $conn->prepare("
                SELECT preference_type, preference_value
                FROM user_preferences
                WHERE user_id = ?
                ORDER BY preference_type, updated_at DESC
            ");
            $stmt->execute([$user_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $preferences = [];
            foreach ($results as $row) {
                $preferences[$row['preference_type']][] = $row['preference_value'];
            }
            return $preferences;
        }
    } catch (Exception $e) {
        error_log("Error in getUserPreferences: " . $e->getMessage());
        return [];
    }
}
}

// Get user's previous values for smart dropdowns (only current user's data)
// Get values directly from songs table (like album field) - this is the primary source
$user_previous_values = [];
try {
    $user_id = get_user_id();
    // Get values directly from songs table first (primary source)
    if (function_exists('getUserPreviousValues')) {
        // Get album (works, so use it as reference)
        $user_previous_values['album'] = getUserPreviousValues($user_id, 'album_title') ?? [];
        
        // Get other fields - these should work the same way as album
        $user_previous_values['producer'] = getUserPreviousValues($user_id, 'producer') ?? [];
        $user_previous_values['composer'] = getUserPreviousValues($user_id, 'composer') ?? [];
        $user_previous_values['lyricist'] = getUserPreviousValues($user_id, 'lyricist') ?? [];
        $user_previous_values['record_label'] = getUserPreviousValues($user_id, 'record_label') ?? [];
        $user_previous_values['instruments'] = getUserPreviousValues($user_id, 'instruments') ?? [];
        $user_previous_values['tags'] = getUserPreviousValues($user_id, 'tags') ?? [];
        $user_previous_values['genre'] = getUserPreviousValues($user_id, 'genre') ?? [];
        
        // Debug: Log what we retrieved
        error_log("Retrieved previous values for user_id: $user_id - Album: " . count($user_previous_values['album']) . 
                  ", Producer: " . count($user_previous_values['producer']) . 
                  ", Composer: " . count($user_previous_values['composer']) . 
                  ", Lyricist: " . count($user_previous_values['lyricist']) . 
                  ", Record Label: " . count($user_previous_values['record_label']));
        error_log("Producer values: " . implode(', ', array_slice($user_previous_values['producer'], 0, 5)));
        error_log("Composer values: " . implode(', ', array_slice($user_previous_values['composer'], 0, 5)));
        error_log("Lyricist values: " . implode(', ', array_slice($user_previous_values['lyricist'], 0, 5)));
        error_log("Record Label values: " . implode(', ', array_slice($user_previous_values['record_label'], 0, 5)));
    }
    
    // Also get from user_preferences and merge (for additional values)
    try {
        $user_prefs = getUserPreferences($user_id);
        if (!empty($user_prefs)) {
            // Merge user_preferences with songs table values
            foreach (['album', 'producer', 'composer', 'lyricist', 'record_label', 'instruments', 'tags', 'genre'] as $field) {
                $pref_values = $user_prefs[$field] ?? [];
                if (!empty($pref_values) && is_array($pref_values)) {
                    $user_previous_values[$field] = array_unique(array_merge(
                        $user_previous_values[$field] ?? [],
                        $pref_values
                    ));
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error getting user preferences: " . $e->getMessage());
    }
    
    // Sort all arrays
    foreach ($user_previous_values as $key => $values) {
        if (is_array($values)) {
            sort($user_previous_values[$key]);
        }
    }
} catch (Exception $e) {
    error_log("Error getting user previous values: " . $e->getMessage());
    // Fallback - initialize empty arrays
    $user_previous_values = [
        'album' => [],
        'producer' => [],
        'composer' => [],
        'lyricist' => [],
        'record_label' => [],
        'instruments' => [],
        'tags' => [],
        'genre' => []
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($is_edit_mode ? 'Edit Song' : 'Upload Music'); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a1a1a;
            --secondary-color: #f8f9fa;
            --accent-color: #007bff;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --text-dark: #333;
            --text-light: #666;
            --border-color: #e9ecef;
        }

        body {
            background: #f5f5f5;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }

        .upload-container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .upload-header {
            text-align: left;
            margin-bottom: 30px;
        }

        .upload-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 0;
        }

        .upload-card {
            background: white;
        }

        .upload-progress {
            background: var(--secondary-color);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 20px;
            left: 60%;
            width: 80%;
            height: 2px;
            background: var(--border-color);
            z-index: 1;
        }

        .step.active:not(:last-child)::after {
            background: var(--accent-color);
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--border-color);
            color: var(--text-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .step.active .step-number {
            background: var(--accent-color);
            color: white;
        }

        .step.completed .step-number {
            background: var(--success-color);
            color: white;
        }

        .step-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-light);
            text-align: center;
        }

        .step.active .step-label {
            color: var(--accent-color);
        }

        .upload-form {
            padding: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--accent-color);
            display: inline-block;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }

        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            background: var(--secondary-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: var(--accent-color);
            background: rgba(0,123,255,0.05);
        }

        .file-upload-area.dragover {
            border-color: var(--accent-color);
            background: rgba(0,123,255,0.1);
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--accent-color);
            margin-bottom: 1rem;
        }

        .upload-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .upload-hint {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .btn-upload {
            background: var(--accent-color);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-upload:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }

        .form-check-input:checked {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.5rem;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }

        .stats-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stats-label {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .upload-container {
                padding: 1rem;
            }
            
            .upload-header h1 {
                font-size: 2rem;
            }
            
            .upload-form {
                padding: 1.5rem;
            }
            
            .progress-steps {
                flex-direction: column;
                gap: 1rem;
            }
            
            .step:not(:last-child)::after {
                display: none;
            }
        }
        
        /* Fix for fixed navbar overlapping content */
        .navbar.fixed-top {
            z-index: 1030 !important;
        }
        
        .main-content {
            position: relative;
            z-index: 1;
            padding-top: 80px !important;
        }
        
        /* Fix for any forms or edit screens */
        .form-container,
        .edit-screen,
        form,
        .upload-form {
            position: relative;
            z-index: 10;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }
        
        /* Ensure all content is properly positioned */
        .container-fluid,
        .container {
            position: relative;
            z-index: 1;
        }
    </style>
</head>
<body>
    <div class="upload-container">
        <!-- Header -->
        <div class="upload-header">
            <h1>UPLOAD NEW SONG</h1>
        </div>

        <!-- Upload Card -->
        <div class="upload-card">
            <!-- Upload Form -->
            <div class="upload-form">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($_SESSION['song_updated_message'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['song_updated_message']; ?>
                        <p style="margin-top: 10px;">The song has been updated with all your changes. You can now see the updated details below.</p>
                    </div>
                    <?php unset($_SESSION['song_updated_message']); ?>
                <?php elseif ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                    
                <?php if ($success || !empty($_SESSION['song_updated_message']) || !empty($songData)): ?>
                    <!-- Show detailed saved information -->
                    <div class="card mt-3">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-info-circle"></i> Song Information Saved</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary">Basic Information</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Song ID:</strong> <?php echo $songData['id'] ?? ''; ?></li>
                                        <li><strong>Title:</strong> <?php echo htmlspecialchars($songData['title'] ?? ''); ?></li>
                                        <li><strong>Artist:</strong> <?php echo htmlspecialchars($songData['artist'] ?? ''); ?></li>
                                        <li><strong>Album:</strong> <?php echo htmlspecialchars($songData['album'] ?: 'Single'); ?></li>
                                        <li><strong>Genre:</strong> <?php echo htmlspecialchars($songData['genre'] ?? ''); ?></li>
                                        <li><strong>Year:</strong> <?php echo htmlspecialchars($songData['year'] ?: 'Not specified'); ?></li>
                                        <li><strong>Track #:</strong> <?php echo htmlspecialchars($songData['track_number'] ?: 'Not specified'); ?></li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-primary">Additional Details</h6>
                                    <ul class="list-unstyled">
                                        <?php if ($songData['producer'] ?? ''): ?>
                                            <li><strong>Producer:</strong> <?php echo htmlspecialchars($songData['producer']); ?></li>
                                        <?php endif; ?>
                                        <?php if ($songData['composer'] ?? ''): ?>
                                            <li><strong>Composer:</strong> <?php echo htmlspecialchars($songData['composer']); ?></li>
                                        <?php endif; ?>
                                        <?php if ($songData['lyricist'] ?? ''): ?>
                                            <li><strong>Lyricist:</strong> <?php echo htmlspecialchars($songData['lyricist']); ?></li>
                                        <?php endif; ?>
                                        <?php if ($songData['record_label'] ?? ''): ?>
                                            <li><strong>Record Label:</strong> <?php echo htmlspecialchars($songData['record_label']); ?></li>
                                        <?php endif; ?>
                                        <li><strong>Language:</strong> <?php echo htmlspecialchars($songData['language'] ?? 'English'); ?></li>
                                        <?php if ($songData['mood'] ?? ''): ?>
                                            <li><strong>Mood:</strong> <?php echo htmlspecialchars($songData['mood']); ?></li>
                                        <?php endif; ?>
                                        <?php if ($songData['tempo'] ?? ''): ?>
                                            <li><strong>Tempo:</strong> <?php echo htmlspecialchars($songData['tempo']); ?></li>
                                        <?php endif; ?>
                                        <?php if ($songData['instruments'] ?? ''): ?>
                                            <li><strong>Instruments:</strong> <?php echo htmlspecialchars($songData['instruments']); ?></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                            <?php if ($songData['tags'] ?? ''): ?>
                                <div class="mt-3">
                                    <h6 class="text-primary">Tags</h6>
                                    <p><?php echo htmlspecialchars($songData['tags']); ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if ($songData['description'] ?? ''): ?>
                                <div class="mt-3">
                                    <h6 class="text-primary">Description</h6>
                                    <p><?php echo nl2br(htmlspecialchars($songData['description'])); ?></p>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3">
                                <h6 class="text-primary">File Information</h6>
                                <ul class="list-unstyled">
                                    <li><strong>File Size:</strong> <?php echo formatFileSize($songData['file_size'] ?? 0); ?></li>
                                    <li><strong>Format:</strong> <?php echo strtoupper($songData['format'] ?? ''); ?></li>
                                    <li><strong>Upload Time:</strong> <?php echo date('M j, Y g:i A', strtotime($songData['uploaded_at'] ?? 'now')); ?></li>
                                    <li><strong>Status:</strong> <span class="badge bg-success">Active</span></li>
                                    <li><strong>Public:</strong> <?php echo ($songData['public'] ?? 0) ? '<span class="badge bg-primary">Yes</span>' : '<span class="badge bg-secondary">No</span>'; ?></li>
                                    <li><strong>Explicit:</strong> <?php echo ($songData['explicit'] ?? 0) ? '<span class="badge bg-warning">Yes</span>' : '<span class="badge bg-success">No</span>'; ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <!-- Hidden fields for edit mode -->
                    <?php if ($is_edit_mode): ?>
                        <input type="hidden" name="edit_mode" value="1">
                        <?php if (!empty($edit_song_id)): ?>
                            <input type="hidden" name="song_id" value="<?php echo $edit_song_id; ?>">
                        <?php endif; ?>
                    <?php endif; ?>
                    <!-- File Upload Section -->
                    <div class="form-group" style="margin-bottom: 25px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Select music file:</label>
                        <button type="button" onclick="document.getElementById('audio_file').click()" style="background: #ff6b35; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">
                            Choose file
                        </button>
                        <input type="file" id="audio_file" name="audio_file" accept="audio/*" required style="display: none;" onchange="document.getElementById('file-name').textContent = this.files[0]?.name || 'No file chosen'">
                        <div id="file-name" style="margin-top: 5px; font-size: 12px; color: #666;">No file chosen</div>
                        <div style="margin-top: 5px; font-size: 12px; color: #999;">MP3 format, 128kbps min.</div>
                    </div>

                    <!-- Song Information Section -->
                    <div style="border-top: 1px solid #e0e0e0; padding-top: 20px; margin-top: 20px;">
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="title" style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Song title:</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo htmlspecialchars($_POST['title'] ?? $songData['title'] ?? ''); ?>" 
                                   placeholder="Enter song title" required
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Production year <span style="color: red;">*</span>:</label>
                            <select class="form-control" id="year" name="year" required
                                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"12\" height=\"12\" viewBox=\"0 0 12 12\"><path fill=\"%23333\" d=\"M6 9L1 4h10z\"/></svg>'); background-repeat: no-repeat; background-position: right 10px center; padding-right: 35px;">
                                <option value="">Select year</option>
                                <?php for ($y = date('Y'); $y >= 1900; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($_POST['year'] ?? $songData['year'] ?? '') == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                                    </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Producer <span style="color: red;">*</span>:</label>
                            <div style="position: relative;">
                                <select class="form-control" id="producer_select" onchange="handleDropdownChange('producer')" required
                                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"12\" height=\"12\" viewBox=\"0 0 12 12\"><path fill=\"%23333\" d=\"M6 9L1 4h10z\"/></svg>'); background-repeat: no-repeat; background-position: right 10px center; padding-right: 35px;">
                                    <option value="">Select producer</option>
                                    <?php foreach ($user_previous_values['producer'] ?? [] as $producer): ?>
                                        <option value="<?php echo htmlspecialchars($producer); ?>" <?php echo ($_POST['producer'] ?? $songData['producer'] ?? '') === $producer ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($producer); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="__other__">Other</option>
                                </select>
                                <input type="text" class="form-control" id="producer" name="producer" required
                                       value="<?php echo htmlspecialchars($_POST['producer'] ?? $songData['producer'] ?? ''); ?>" 
                                       placeholder="Enter producer name" 
                                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; margin-top: 10px; display: none;">
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Author (Owner of the lyrics):</label>
                            <div style="position: relative;">
                                <input type="text" class="form-control" id="lyricist" name="lyricist" 
                                       value="<?php echo htmlspecialchars($_POST['lyricist'] ?? $songData['lyricist'] ?? $logged_in_user_name); ?>" 
                                       placeholder="Author name" 
                                       readonly
                                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; background-color: #f5f5f5;">
                            </div>
                            <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">Automatically set to your name</small>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Composer (Owner of the composition) <span style="color: red;">*</span>:</label>
                            <div style="position: relative;">
                                <select class="form-control" id="composer_select" onchange="handleDropdownChange('composer')" required
                                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"12\" height=\"12\" viewBox=\"0 0 12 12\"><path fill=\"%23333\" d=\"M6 9L1 4h10z\"/></svg>'); background-repeat: no-repeat; background-position: right 10px center; padding-right: 35px;">
                                    <option value="">Select composer</option>
                                    <?php foreach ($user_previous_values['composer'] ?? [] as $composer): ?>
                                        <option value="<?php echo htmlspecialchars($composer); ?>" <?php echo ($_POST['composer'] ?? $songData['composer'] ?? '') === $composer ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($composer); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="__other__">Other</option>
                                </select>
                                <input type="text" class="form-control" id="composer" name="composer" required
                                       value="<?php echo htmlspecialchars($_POST['composer'] ?? $songData['composer'] ?? ''); ?>" 
                                       placeholder="Enter composer name" 
                                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; margin-top: 10px; display: none;">
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Copyright Owner (Owner of artwork):</label>
                            <div style="position: relative;">
                                <select class="form-control" id="record_label_select" onchange="handleDropdownChange('record_label')" 
                                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"12\" height=\"12\" viewBox=\"0 0 12 12\"><path fill=\"%23333\" d=\"M6 9L1 4h10z\"/></svg>'); background-repeat: no-repeat; background-position: right 10px center; padding-right: 35px;">
                                    <option value="">Select copyright owner</option>
                                    <?php foreach ($user_previous_values['record_label'] ?? [] as $label): ?>
                                        <option value="<?php echo htmlspecialchars($label); ?>" <?php echo ($_POST['record_label'] ?? $songData['record_label'] ?? '') === $label ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="__other__">Other</option>
                                </select>
                                <input type="text" class="form-control" id="record_label" name="record_label" 
                                       value="<?php echo htmlspecialchars($_POST['record_label'] ?? $songData['record_label'] ?? ''); ?>" 
                                       placeholder="Enter copyright owner name" 
                                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; margin-top: 10px; display: none;">
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Track type <span style="color: red;">*</span>:</label>
                            <select class="form-control" id="track_type" name="track_type" onchange="handleTrackTypeChange()" required
                                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"12\" height=\"12\" viewBox=\"0 0 12 12\"><path fill=\"%23333\" d=\"M6 9L1 4h10z\"/></svg>'); background-repeat: no-repeat; background-position: right 10px center; padding-right: 35px;">
                                <option value="">Select track type</option>
                                <option value="Single" <?php echo ($_POST['track_type'] ?? ($songData['track_type'] ?? '')) === 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Collabo" <?php echo ($_POST['track_type'] ?? ($songData['track_type'] ?? '')) === 'Collabo' ? 'selected' : ''; ?>>Collabo</option>
                                    </select>
                        </div>

                        <!-- Collaboration Field (shown when Collabo is selected) -->
                        <div id="collaboration_field" style="display: none; margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px; border: 1px solid #e0e0e0;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">
                                Additional Artists (will be added as: "<?php echo htmlspecialchars(ucwords($logged_in_user_name)); ?> x [Artist Names]")
                            </label>
                            <div style="position: relative;">
                                <input type="text" class="form-control" id="additional_artists" name="additional_artists" 
                                       value="<?php echo htmlspecialchars($_POST['additional_artists'] ?? ''); ?>" 
                                       placeholder="Start typing to search existing artists or enter names separated by commas..."
                                       autocomplete="off"
                                       oninput="searchArtists(this.value)"
                                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                                <div id="artist_suggestions" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-radius: 5px; max-height: 200px; overflow-y: auto; z-index: 1000; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-top: 5px;"></div>
                        </div>
                            <small style="color: #666; display: block; margin-top: 5px;">
                                Type to search existing artists or enter names separated by commas. Existing artists will receive collaboration invitations.
                            </small>
                            <div id="selected_artists" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;"></div>
                            <!-- Hidden input to carry selected collaborator IDs (comma-separated) -->
                            <input type="hidden" id="selected_artist_ids" name="selected_artist_ids" value="<?php echo htmlspecialchars($_POST['selected_artist_ids'] ?? ''); ?>">
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Track Genre <span style="color: red;">*</span>:</label>
                            <select class="form-control" id="genre" name="genre" required
                                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"12\" height=\"12\" viewBox=\"0 0 12 12\"><path fill=\"%23333\" d=\"M6 9L1 4h10z\"/></svg>'); background-repeat: no-repeat; background-position: right 10px center; padding-right: 35px;">
                                <option value="">Select Genre</option>
                                <?php
                                // Fetch genres from database
                                try {
                                    require_once 'config/database.php';
                                    $db = new Database();
                                    $conn = $db->getConnection();
                                    $genreStmt = $conn->prepare("SELECT id, name FROM genres ORDER BY name ASC");
                                    $genreStmt->execute();
                                    $genres = $genreStmt->fetchAll(PDO::FETCH_ASSOC);
                                    $selectedGenre = $_POST['genre'] ?? $songData['genre'] ?? '';
                                    
                                    foreach ($genres as $genre) {
                                        $isSelected = ($selectedGenre === $genre['name'] || $selectedGenre === $genre['id']) ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($genre['name']) . '" ' . $isSelected . '>' . htmlspecialchars($genre['name']) . '</option>';
                                    }
                                } catch (Exception $e) {
                                    error_log("Error fetching genres: " . $e->getMessage());
                                    // Fallback to hardcoded genres if database query fails
                                    $fallbackGenres = ['Pop', 'Hip-Hop', 'Rock', 'R&B', 'Jazz', 'Electronic', 'Country', 'Reggae', 'Blues'];
                                    foreach ($fallbackGenres as $genre) {
                                        $isSelected = ($selectedGenre === $genre) ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($genre) . '" ' . $isSelected . '>' . htmlspecialchars($genre) . '</option>';
                                    }
                                }
                                ?>
                                </select>
                    </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="display: flex; align-items: center; gap: 8px; font-weight: 500; color: #333; cursor: pointer;">
                                <input type="checkbox" id="terms" name="terms" required
                                       style="width: 18px; height: 18px; cursor: pointer;">
                                I agree to content provider <a href="#" style="color: #ff6b35;">Terms&Conditions</a>
                                    </label>
                    </div>

                        <!-- Submit Buttons -->
                        <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                            <button type="button" onclick="window.history.back()" 
                                    style="background: white; color: #ff6b35; border: 1px solid #ff6b35; padding: 12px 30px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">
                                Cancel
                            </button>
                            <button type="submit" 
                                    style="background: #ff6b35; color: white; border: none; padding: 12px 30px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">
                                Save
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle track type change - show collaboration field when "Collabo" is selected
        function handleTrackTypeChange() {
            const trackType = document.getElementById('track_type');
            const collaborationField = document.getElementById('collaboration_field');
            const additionalArtists = document.getElementById('additional_artists');
            
            if (trackType.value === 'Collabo') {
                collaborationField.style.display = 'block';
                // Focus on the input after a short delay to ensure it's visible
                setTimeout(() => {
                    if (additionalArtists) {
                        additionalArtists.focus();
                    }
                }, 100);
            } else {
                collaborationField.style.display = 'none';
                if (additionalArtists) {
                    additionalArtists.value = '';
                }
                // Clear selected artists
                if (typeof selectedArtists !== 'undefined') {
                    selectedArtists = [];
                    updateSelectedArtists();
                }
            }
        }
        
        let selectedArtists = [];
        let searchTimeout = null;
        
        // Search artists with autocomplete
        function searchArtists(query) {
            const suggestions = document.getElementById('artist_suggestions');
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                suggestions.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch('api/search-artists.php?q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        if (data.length === 0) {
                            suggestions.style.display = 'none';
                            return;
                        }
                        
                        let html = '';
                        data.forEach(artist => {
                            // Skip if already selected
                            if (selectedArtists.find(a => a.id === artist.id)) {
                                return;
                            }
                            
                            html += '<div style="padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px;" onclick="selectArtist(' + artist.id + ', \'' + escapeHtml(artist.username) + '\', \'' + escapeHtml(artist.email) + '\')" onmouseover="this.style.background=\'#f5f5f5\'" onmouseout="this.style.background=\'white\'">';
                            if (artist.avatar) {
                                html += '<img src="' + escapeHtml(artist.avatar) + '" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;">';
                            } else {
                                html += '<div style="width: 30px; height: 30px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center;"><i class="fas fa-user"></i></div>';
                            }
                            html += '<div><strong>' + escapeHtml(capitalizeFirstLetter(artist.username)) + '</strong><br><small style="color: #666;">' + escapeHtml(artist.email) + '</small></div>';
                            html += '</div>';
                        });
                        
                        suggestions.innerHTML = html;
                        suggestions.style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Error searching artists:', error);
                        suggestions.style.display = 'none';
                    });
            }, 300);
        }
        
        function selectArtist(id, username, email) {
            selectedArtists.push({ id: id, username: capitalizeFirstLetter(username), email: email });
            updateSelectedArtists();
            document.getElementById('additional_artists').value = '';
            document.getElementById('artist_suggestions').style.display = 'none';
        }
        
        function removeArtist(index) {
            selectedArtists.splice(index, 1);
            updateSelectedArtists();
        }
        
        function updateSelectedArtists() {
            const container = document.getElementById('selected_artists');
            const idsInput = document.getElementById('selected_artist_ids');
            const namesInput = document.getElementById('additional_artists');
            
            let html = '';
            selectedArtists.forEach((artist, index) => {
                html += '<div style="background: #e3f2fd; padding: 8px 12px; border-radius: 20px; display: flex; align-items: center; gap: 8px;">';
                html += '<i class="fas fa-user-check" style="color: #2196f3;"></i>';
                html += '<span>' + escapeHtml(artist.username) + '</span>';
                html += '<button type="button" onclick="removeArtist(' + index + ')" style="background: none; border: none; color: #f44336; cursor: pointer; padding: 0 5px;"><i class="fas fa-times"></i></button>';
                html += '</div>';
            });
            
            container.innerHTML = html;
            
            // Update hidden field with selected IDs
            idsInput.value = selectedArtists.map(a => a.id).join(',');
            
            // Update names field - only set if there are selected artists
            if (selectedArtists.length > 0) {
                namesInput.value = selectedArtists.map(a => a.username).join(', ');
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function capitalizeFirstLetter(string) {
            return string.charAt(0).toUpperCase() + string.slice(1).toLowerCase();
        }
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            const suggestions = document.getElementById('artist_suggestions');
            const input = document.getElementById('additional_artists');
            if (e.target !== input && e.target !== suggestions && !suggestions.contains(e.target)) {
                suggestions.style.display = 'none';
            }
        });
        
        // Check on page load if track_type is "Collabo" to show collaboration field
        document.addEventListener('DOMContentLoaded', function() {
            const trackType = document.getElementById('track_type');
            if (trackType && trackType.value === 'Collabo') {
                handleTrackTypeChange();
            }
            
            // Pre-load selected collaborators if editing
            const selectedIdsInput = document.getElementById('selected_artist_ids');
            if (selectedIdsInput && selectedIdsInput.value) {
                const ids = selectedIdsInput.value.split(',').filter(id => id.trim() !== '');
                if (ids.length > 0) {
                    // Fetch and display pre-selected collaborators
                    ids.forEach(userId => {
                        const userIdTrimmed = userId.trim();
                        if (userIdTrimmed && !isNaN(userIdTrimmed)) {
                            fetch('api/search-artists.php?id=' + userIdTrimmed)
                                .then(res => res.json())
                                .then(data => {
                                    if (data.length > 0) {
                                        const artist = data[0];
                                        selectArtist(artist.id, artist.username, artist.email || '');
                                    }
                                })
                                .catch(err => console.error('Error loading collaborator:', err));
                        }
                    });
                }
            }
        });
        
        // File upload area interactions
        const fileUploadAreas = document.querySelectorAll('.file-upload-area');
        
        fileUploadAreas.forEach(area => {
            area.addEventListener('dragover', (e) => {
                e.preventDefault();
                area.classList.add('dragover');
            });
            
            area.addEventListener('dragleave', () => {
                area.classList.remove('dragover');
            });
            
            area.addEventListener('drop', (e) => {
                e.preventDefault();
                area.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                const input = area.querySelector('input[type="file"]');
                if (files.length > 0) {
                    input.files = files;
                    updateFileDisplay(area, files[0]);
                }
            });
        });

        // Update file display when file is selected
        document.getElementById('audio_file').addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                updateFileDisplay(e.target.closest('.file-upload-area'), e.target.files[0]);
            }
        });

        function updateFileDisplay(area, file) {
            const text = area.querySelector('.upload-text');
            const hint = area.querySelector('.upload-hint');
            
            text.textContent = file.name;
            hint.textContent = formatFileSize(file.size);
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Form validation
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            // Make sure text inputs are visible if they have values before validation
            const fields = ['record_label', 'producer', 'composer', 'lyricist', 'instruments', 'tags'];
            fields.forEach(fieldName => {
                const input = document.getElementById(fieldName);
                const select = document.getElementById(fieldName + '_select');
                
                // If input has a value that's not in the dropdown, show it
                if (input && input.value && input.value.trim() !== '') {
                    // Check if value is in dropdown
                    const optionExists = select ? Array.from(select.options).some(opt => opt.value === input.value) : false;
                    if (!optionExists) {
                        // Value is not in dropdown, ensure input is visible
                        if (select) select.style.display = 'none';
                        input.style.display = 'block';
                    }
                }
            });
            
            // Validate all required fields
            <?php if (!$is_edit_mode): ?>
            // For new uploads: audio file, song title, production year, producer, composer, track type, track genre
            const requiredFields = [
                {id: 'audio_file', name: 'Audio file'},
                {id: 'title', name: 'Song title'},
                {id: 'year', name: 'Production year'},
                {id: 'producer', selectId: 'producer_select', name: 'Producer'},
                {id: 'composer', selectId: 'composer_select', name: 'Composer'},
                {id: 'track_type', name: 'Track type'},
                {id: 'genre', name: 'Track genre'}
            ];
            <?php else: ?>
            // For edit mode: song title, production year, producer, composer, track type, track genre (audio_file is optional)
            const requiredFields = [
                {id: 'title', name: 'Song title'},
                {id: 'year', name: 'Production year'},
                {id: 'producer', selectId: 'producer_select', name: 'Producer'},
                {id: 'composer', selectId: 'composer_select', name: 'Composer'},
                {id: 'track_type', name: 'Track type'},
                {id: 'genre', name: 'Track genre'}
            ];
            <?php endif; ?>
            
            let isValid = true;
            const missingFields = [];
            
            requiredFields.forEach(field => {
                let fieldElement = document.getElementById(field.id);
                let value = '';
                
                // Check if it's a dropdown field (has selectId)
                if (field.selectId) {
                    const selectElement = document.getElementById(field.selectId);
                    const inputElement = document.getElementById(field.id);
                    
                    if (selectElement && selectElement.style.display !== 'none') {
                        value = selectElement.value || '';
                    } else if (inputElement && inputElement.style.display !== 'none') {
                        value = inputElement.value ? inputElement.value.trim() : '';
                    }
                } else {
                    // Regular field
                    if (fieldElement) {
                        if (field.id === 'audio_file') {
                            value = fieldElement.files.length > 0 ? 'file_selected' : '';
                        } else {
                            value = fieldElement.value ? fieldElement.value.trim() : '';
                        }
                    }
                }
                
                if (!value || value === '') {
                    isValid = false;
                    missingFields.push(field.name);
                    if (fieldElement) {
                        fieldElement.classList.add('is-invalid');
                    }
                    if (field.selectId) {
                        const selectEl = document.getElementById(field.selectId);
                        if (selectEl) selectEl.classList.add('is-invalid');
                    }
                } else {
                    if (fieldElement) {
                        fieldElement.classList.remove('is-invalid');
                    }
                    if (field.selectId) {
                        const selectEl = document.getElementById(field.selectId);
                        if (selectEl) selectEl.classList.remove('is-invalid');
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields:\n\n' + missingFields.join('\n'));
                return false;
            }
        });

        // Unified dropdown handler for all fields with "Other" option
        function handleDropdownChange(fieldName) {
            const select = document.getElementById(fieldName + '_select');
            const input = document.getElementById(fieldName);
            
            if (!select || !input) return;
            
            if (select.value === '__other__') {
                // Show text input when "Other" is selected
                select.style.display = 'none';
                input.style.display = 'block';
                input.value = '';
                input.focus();
            } else if (select.value && select.value !== '') {
                // Set input value and hide it
                input.value = select.value;
                input.style.display = 'none';
                select.style.display = 'block';
            }
        }

        // Initialize form state on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check if any fields have values and show appropriate input
            const fields = ['record_label', 'producer', 'composer', 'lyricist', 'instruments', 'tags'];
            
            fields.forEach(fieldName => {
                const input = document.getElementById(fieldName);
                const select = document.getElementById(fieldName + '_select');
                
                if (input && select) {
                    // If input has a value
                    if (input.value && input.value.trim() !== '') {
                        // Check if value exists in dropdown (excluding empty, __other__, and __new__ options)
                        const optionExists = Array.from(select.options).some(opt => 
                            opt.value !== '' && 
                            opt.value !== '__other__' && 
                            opt.value !== '__new__' && 
                            opt.value === input.value
                        );
                        if (optionExists) {
                            // Value is in dropdown, show dropdown with value selected
                            select.value = input.value;
                            input.value = select.value; // Ensure input has the value for form submission
                            input.style.display = 'none';
                            select.style.display = 'block';
                        } else {
                            // Value is not in dropdown, show input field
                            select.style.display = 'none';
                            input.style.display = 'block';
                        }
                    } else {
                        // No value - show dropdown by default
                        input.style.display = 'none';
                        select.style.display = 'block';
                    }
                }
            });
            
            // Before form submission, ensure all input fields have values from their selects
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    fields.forEach(fieldName => {
                        const input = document.getElementById(fieldName);
                        const select = document.getElementById(fieldName + '_select');
                        
                        if (input && select && select.value && select.value !== '__other__' && select.value !== '') {
                            // If select has a value and it's not "Other", copy it to input
                            input.value = select.value;
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>