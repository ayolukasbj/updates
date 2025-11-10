<?php
// classes/Song.php
// Song model for music management

class Song {
    private $conn;
    private $table = 'songs';

    public $id;
    public $title;
    public $artist_id;
    public $album_id;
    public $file_path;
    public $file_size;
    public $duration;
    public $bitrate;
    public $quality;
    public $genre_id;
    public $lyrics;
    public $track_number;
    public $plays;
    public $downloads;
    public $is_featured;
    public $is_explicit;
    public $upload_date;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Upload new song
    public function uploadSong($data, $file) {
        // Validate file
        $validation = $this->validateFile($file);
        if (!$validation['success']) {
            return $validation;
        }

        // Get audio metadata
        $metadata = $this->getAudioMetadata($file['tmp_name']);
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $file_extension;
        $file_path = MUSIC_PATH . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            return ['success' => false, 'error' => 'Failed to upload file'];
        }

        // Insert song record
        $query = "INSERT INTO " . $this->table . " 
                  (title, artist_id, album_id, file_path, file_size, duration, 
                   bitrate, quality, genre_id, lyrics, track_number) 
                  VALUES (:title, :artist_id, :album_id, :file_path, :file_size, :duration, 
                          :bitrate, :quality, :genre_id, :lyrics, :track_number)";

        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':title', $data['title']);
        $stmt->bindParam(':artist_id', $data['artist_id']);
        $stmt->bindParam(':album_id', $data['album_id']);
        $stmt->bindParam(':file_path', $file_path);
        $stmt->bindParam(':file_size', $file['size']);
        $stmt->bindParam(':duration', $metadata['duration']);
        $stmt->bindParam(':bitrate', $metadata['bitrate']);
        $stmt->bindParam(':quality', $data['quality']);
        $stmt->bindParam(':genre_id', $data['genre_id']);
        $stmt->bindParam(':lyrics', $data['lyrics']);
        $stmt->bindParam(':track_number', $data['track_number']);

        if ($stmt->execute()) {
            $song_id = $this->conn->lastInsertId();
            
            // Update album track count
            if ($data['album_id']) {
                $this->updateAlbumTrackCount($data['album_id']);
            }
            
            return ['success' => true, 'song_id' => $song_id];
        }

        return ['success' => false, 'error' => 'Failed to save song record'];
    }

    // Get song by ID
    public function getSongById($id) {
        $query = "SELECT s.*, 
                         s.artist as artist,
                         s.is_collaboration,
                         s.uploaded_by,
                         a.name as artist_name, 
                         al.title as album_title, 
                         g.name as genre_name
                  FROM " . $this->table . " s
                  LEFT JOIN artists a ON s.artist_id = a.id
                  LEFT JOIN albums al ON s.album_id = al.id
                  LEFT JOIN genres g ON s.genre_id = g.id
                  WHERE s.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get all songs with pagination
    public function getSongs($page = 1, $limit = SONGS_PER_PAGE, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $where_conditions = [];
        $params = [];

        if (!empty($filters['genre'])) {
            $where_conditions[] = "s.genre_id = :genre";
            $params[':genre'] = $filters['genre'];
        }

        if (!empty($filters['artist'])) {
            $where_conditions[] = "s.artist_id = :artist";
            $params[':artist'] = $filters['artist'];
        }

        if (!empty($filters['search'])) {
            $where_conditions[] = "(s.title LIKE :search OR a.name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $query = "SELECT s.*, 
                         s.artist as artist,
                         s.is_collaboration,
                         a.name as artist_name, 
                         al.title as album_title, 
                         g.name as genre_name
                  FROM " . $this->table . " s
                  LEFT JOIN artists a ON s.artist_id = a.id
                  LEFT JOIN albums al ON s.album_id = al.id
                  LEFT JOIN genres g ON s.genre_id = g.id
                  " . $where_clause . "
                  ORDER BY s.upload_date DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get featured songs
    public function getFeaturedSongs($limit = 10) {
        $query = "SELECT s.*, 
                         s.artist as artist,
                         s.is_collaboration,
                         a.name as artist_name, 
                         al.title as album_title, 
                         g.name as genre_name
                  FROM " . $this->table . " s
                  LEFT JOIN artists a ON s.artist_id = a.id
                  LEFT JOIN albums al ON s.album_id = al.id
                  LEFT JOIN genres g ON s.genre_id = g.id
                  WHERE s.is_featured = 1
                  ORDER BY s.plays DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get trending songs
    public function getTrendingSongs($limit = 10) {
        $query = "SELECT s.*, 
                         s.artist as artist,
                         s.is_collaboration,
                         a.name as artist_name, 
                         al.title as album_title, 
                         g.name as genre_name
                  FROM " . $this->table . " s
                  LEFT JOIN artists a ON s.artist_id = a.id
                  LEFT JOIN albums al ON s.album_id = al.id
                  LEFT JOIN genres g ON s.genre_id = g.id
                  WHERE s.upload_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  ORDER BY s.plays DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Increment play count
    public function incrementPlayCount($id) {
        $query = "UPDATE " . $this->table . " SET plays = plays + 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }

    // Increment download count
    public function incrementDownloadCount($id) {
        $query = "UPDATE " . $this->table . " SET downloads = downloads + 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }

    // Record play history
    public function recordPlayHistory($user_id, $song_id, $duration_played = 0, $completed = false) {
        $query = "INSERT INTO play_history (user_id, song_id, duration_played, completed) 
                  VALUES (:user_id, :song_id, :duration_played, :completed)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':song_id', $song_id);
        $stmt->bindParam(':duration_played', $duration_played);
        $stmt->bindParam(':completed', $completed);
        
        return $stmt->execute();
    }

    // Get recommended songs for user
    public function getRecommendedSongs($user_id, $limit = 8) {
        // Simple recommendation based on featured songs
        // In a real app, this would use user's listening history
        return $this->getFeaturedSongs($limit);
    }

    // Get user's download history
    public function getUserDownloads($user_id, $limit = 50) {
        $query = "SELECT d.*, s.title, s.duration, a.name as artist_name
                  FROM downloads d
                  JOIN " . $this->table . " s ON d.song_id = s.id
                  LEFT JOIN artists a ON s.artist_id = a.id
                  WHERE d.user_id = :user_id
                  ORDER BY d.downloaded_at DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get user's favorite songs
    public function getUserFavorites($user_id, $limit = 50) {
        $query = "SELECT f.*, s.title, s.duration, s.id as song_id, a.name as artist_name
                  FROM favorites f
                  JOIN " . $this->table . " s ON f.song_id = s.id
                  LEFT JOIN artists a ON s.artist_id = a.id
                  WHERE f.user_id = :user_id
                  ORDER BY f.created_at DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get songs by artist
    public function getSongsByArtist($artist_id, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $query = "SELECT s.*, 
                         s.artist as artist,
                         s.is_collaboration,
                         a.name as artist_name, 
                         al.title as album_title, 
                         g.name as genre_name
                  FROM " . $this->table . " s
                  LEFT JOIN artists a ON s.artist_id = a.id
                  LEFT JOIN albums al ON s.album_id = al.id
                  LEFT JOIN genres g ON s.genre_id = g.id
                  WHERE s.artist_id = :artist_id
                  ORDER BY s.created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':artist_id', $artist_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get user's recently played songs
    public function getRecentlyPlayed($user_id, $limit = 20) {
        $query = "SELECT DISTINCT s.*, 
                         s.artist as artist,
                         s.is_collaboration,
                         a.name as artist_name, 
                         al.title as album_title, 
                         g.name as genre_name
                  FROM play_history ph
                  JOIN " . $this->table . " s ON ph.song_id = s.id
                  LEFT JOIN artists a ON s.artist_id = a.id
                  LEFT JOIN albums al ON s.album_id = al.id
                  LEFT JOIN genres g ON s.genre_id = g.id
                  WHERE ph.user_id = :user_id
                  ORDER BY ph.played_at DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Validate uploaded file
    private function validateFile($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'File upload error'];
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'error' => 'File too large. Maximum size: ' . format_file_size(MAX_FILE_SIZE)];
        }

        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, ALLOWED_AUDIO_FORMATS)) {
            return ['success' => false, 'error' => 'Invalid file format. Allowed: ' . implode(', ', ALLOWED_AUDIO_FORMATS)];
        }

        return ['success' => true];
    }

    // Get audio metadata using getID3 or similar
    private function getAudioMetadata($file_path) {
        // This is a simplified version - you might want to use getID3 library
        $metadata = [
            'duration' => 0,
            'bitrate' => 128
        ];

        // For MP3 files, you can use basic file analysis
        if (function_exists('getid3_lib')) {
            // Use getID3 if available
            $getID3 = new getID3;
            $file_info = $getID3->analyze($file_path);
            
            if (isset($file_info['playtime_seconds'])) {
                $metadata['duration'] = (int)$file_info['playtime_seconds'];
            }
            
            if (isset($file_info['audio']['bitrate'])) {
                $metadata['bitrate'] = (int)$file_info['audio']['bitrate'];
            }
        }

        return $metadata;
    }

    // Update album track count
    private function updateAlbumTrackCount($album_id) {
        $query = "UPDATE albums SET total_tracks = (
                    SELECT COUNT(*) FROM songs WHERE album_id = :album_id
                  ) WHERE id = :album_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':album_id', $album_id);
        $stmt->execute();
    }

    // Delete song
    public function deleteSong($id) {
        // Get file path first
        $song = $this->getSongById($id);
        
        if (!$song) {
            return ['success' => false, 'error' => 'Song not found'];
        }

        // Delete file
        if (file_exists($song['file_path'])) {
            unlink($song['file_path']);
        }

        // Delete from database
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            // Update album track count
            if ($song['album_id']) {
                $this->updateAlbumTrackCount($song['album_id']);
            }
            
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Failed to delete song'];
    }

    // Search songs
    public function searchSongs($query, $limit = 20) {
        try {
            $search_term = '%' . $query . '%';
            $exact_match = $query;
            $start_match = $query . '%';
            
            $search_query = "SELECT s.*, 
                                    s.artist as artist,
                                    s.is_collaboration,
                                    COALESCE(s.artist, u.username, 'Unknown Artist') as artist_name,
                                    a.name as artist_table_name,
                                    al.title as album_title, 
                                    g.name as genre_name
                             FROM " . $this->table . " s
                             LEFT JOIN users u ON s.uploaded_by = u.id
                             LEFT JOIN artists a ON s.artist_id = a.id
                             LEFT JOIN albums al ON s.album_id = al.id
                             LEFT JOIN genres g ON s.genre_id = g.id
                             WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                             AND (
                                 s.title LIKE ? 
                                 OR s.artist LIKE ?
                                 OR u.username LIKE ?
                                 OR al.title LIKE ?
                             )
                             ORDER BY 
                                 CASE 
                                     WHEN s.title LIKE ? THEN 1
                                     WHEN s.title LIKE ? THEN 2
                                     ELSE 3
                                 END,
                                 s.plays DESC
                             LIMIT ?";

            $stmt = $this->conn->prepare($search_query);
            $stmt->bindValue(1, $search_term);
            $stmt->bindValue(2, $search_term);
            $stmt->bindValue(3, $search_term);
            $stmt->bindValue(4, $search_term);
            $stmt->bindValue(5, $exact_match);
            $stmt->bindValue(6, $start_match);
            $stmt->bindValue(7, $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Song search PDO error: " . $e->getMessage());
            return [];
        } catch (Exception $e) {
            error_log("Song search error: " . $e->getMessage());
            return [];
        }
    }
}
?>
