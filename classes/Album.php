<?php
// classes/Album.php
// Album model for album management

class Album {
    private $conn;
    private $table = 'albums';

    public $id;
    public $title;
    public $artist_id;
    public $release_date;
    public $cover_art;
    public $description;
    public $genre_id;
    public $total_tracks;
    public $total_duration;
    public $total_plays;
    public $total_downloads;
    public $is_featured;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create new album
    public function createAlbum($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (title, artist_id, release_date, cover_art, description, genre_id) 
                  VALUES (:title, :artist_id, :release_date, :cover_art, :description, :genre_id)";

        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':title', $data['title']);
        $stmt->bindParam(':artist_id', $data['artist_id']);
        $stmt->bindParam(':release_date', $data['release_date']);
        $stmt->bindParam(':cover_art', $data['cover_art']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':genre_id', $data['genre_id']);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return ['success' => true, 'album_id' => $this->id];
        }

        return ['success' => false, 'error' => 'Failed to create album'];
    }

    // Get album by ID
    public function getAlbumById($id) {
        $query = "SELECT a.*, ar.name as artist_name, g.name as genre_name
                  FROM " . $this->table . " a
                  LEFT JOIN artists ar ON a.artist_id = ar.id
                  LEFT JOIN genres g ON a.genre_id = g.id
                  WHERE a.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get album songs
    public function getAlbumSongs($album_id) {
        $query = "SELECT s.*, 
                         g.name as genre_name,
                         u.username as uploader_username,
                         u.avatar as uploader_avatar,
                         u.id as uploaded_by,
                         COALESCE(s.artist, u.username, 'Unknown Artist') as artist
                  FROM songs s
                  LEFT JOIN genres g ON s.genre_id = g.id
                  LEFT JOIN users u ON s.uploaded_by = u.id
                  WHERE s.album_id = :album_id
                  ORDER BY COALESCE(s.track_number, 999999) ASC, s.title ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':album_id', $album_id);
        $stmt->execute();

        $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get collaborators for each song
        foreach ($songs as &$song) {
            try {
                $collabStmt = $this->conn->prepare("
                    SELECT u.id, u.username, u.avatar
                    FROM song_collaborators sc
                    JOIN users u ON sc.user_id = u.id
                    WHERE sc.song_id = ?
                ");
                $collabStmt->execute([$song['id']]);
                $collaborators = $collabStmt->fetchAll(PDO::FETCH_ASSOC);
                $song['collaborators'] = $collaborators;
            } catch (Exception $e) {
                $song['collaborators'] = [];
            }
        }
        
        return $songs;
    }

    // Get artist's albums
    public function getArtistAlbums($artist_id) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE artist_id = :artist_id 
                  ORDER BY release_date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':artist_id', $artist_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all albums with pagination
    public function getAlbums($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $where_conditions = [];
        $params = [];

        if (!empty($filters['genre'])) {
            $where_conditions[] = "a.genre_id = :genre";
            $params[':genre'] = $filters['genre'];
        }

        if (!empty($filters['artist'])) {
            $where_conditions[] = "a.artist_id = :artist";
            $params[':artist'] = $filters['artist'];
        }

        if (!empty($filters['search'])) {
            $where_conditions[] = "(a.title LIKE :search OR ar.name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $query = "SELECT a.*, ar.name as artist_name, g.name as genre_name
                  FROM " . $this->table . " a
                  LEFT JOIN artists ar ON a.artist_id = ar.id
                  LEFT JOIN genres g ON a.genre_id = g.id
                  " . $where_clause . "
                  ORDER BY a.release_date DESC
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

    // Get featured albums
    public function getFeaturedAlbums($limit = 10) {
        $query = "SELECT a.*, ar.name as artist_name, g.name as genre_name
                  FROM " . $this->table . " a
                  LEFT JOIN artists ar ON a.artist_id = ar.id
                  LEFT JOIN genres g ON a.genre_id = g.id
                  WHERE a.is_featured = 1 AND a.total_tracks > 0
                  ORDER BY a.total_plays DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Update album
    public function updateAlbum($id, $data) {
        $query = "UPDATE " . $this->table . " 
                  SET title = :title, release_date = :release_date, 
                      cover_art = :cover_art, description = :description,
                      genre_id = :genre_id, updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':title', $data['title']);
        $stmt->bindParam(':release_date', $data['release_date']);
        $stmt->bindParam(':cover_art', $data['cover_art']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':genre_id', $data['genre_id']);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }

    // Delete album
    public function deleteAlbum($id) {
        // Get album info first
        $album = $this->getAlbumById($id);
        
        if (!$album) {
            return ['success' => false, 'error' => 'Album not found'];
        }

        // Delete cover art
        if ($album['cover_art'] && file_exists($album['cover_art'])) {
            unlink($album['cover_art']);
        }

        // Update songs to remove album reference
        $query = "UPDATE songs SET album_id = NULL WHERE album_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        // Delete album
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    // Search albums
    public function searchAlbums($query, $limit = 20) {
        $search_query = "SELECT a.*, ar.name as artist_name, g.name as genre_name
                         FROM " . $this->table . " a
                         LEFT JOIN artists ar ON a.artist_id = ar.id
                         LEFT JOIN genres g ON a.genre_id = g.id
                         WHERE a.title LIKE :query OR ar.name LIKE :query
                         ORDER BY 
                             CASE 
                                 WHEN a.title LIKE :exact THEN 1
                                 WHEN a.title LIKE :start THEN 2
                                 ELSE 3
                             END,
                             a.total_plays DESC
                         LIMIT :limit";

        $stmt = $this->conn->prepare($search_query);
        $search_term = '%' . $query . '%';
        $exact_match = $query;
        $start_match = $query . '%';
        
        $stmt->bindParam(':query', $search_term);
        $stmt->bindParam(':exact', $exact_match);
        $stmt->bindParam(':start', $start_match);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Increment play count
    public function incrementPlayCount($id) {
        $query = "UPDATE " . $this->table . " SET total_plays = total_plays + 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }

    // Increment download count
    public function incrementDownloadCount($id) {
        $query = "UPDATE " . $this->table . " SET total_downloads = total_downloads + 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }

    // Update album statistics
    public function updateAlbumStats($id) {
        // Update track count
        $query = "UPDATE " . $this->table . " SET total_tracks = (
                    SELECT COUNT(*) FROM songs WHERE album_id = :id
                  ) WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        // Update total duration
        $query = "UPDATE " . $this->table . " SET total_duration = (
                    SELECT SUM(duration) FROM songs WHERE album_id = :id
                  ) WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        // Update total plays
        $query = "UPDATE " . $this->table . " SET total_plays = (
                    SELECT SUM(plays) FROM songs WHERE album_id = :id
                  ) WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        // Update total downloads
        $query = "UPDATE " . $this->table . " SET total_downloads = (
                    SELECT SUM(downloads) FROM songs WHERE album_id = :id
                  ) WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }

    // Get album statistics
    public function getAlbumStats($id) {
        $stats = [];
        
        // Get genre distribution
        $query = "SELECT g.name, COUNT(*) as count
                  FROM songs s
                  LEFT JOIN genres g ON s.genre_id = g.id
                  WHERE s.album_id = :id
                  GROUP BY g.id, g.name
                  ORDER BY count DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $stats['genres'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get play distribution over time
        $query = "SELECT DATE(ph.played_at) as date, COUNT(*) as plays
                  FROM play_history ph
                  JOIN songs s ON ph.song_id = s.id
                  WHERE s.album_id = :id AND ph.played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  GROUP BY DATE(ph.played_at)
                  ORDER BY date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $stats['play_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }

    // Set featured status
    public function setFeatured($id, $featured = true) {
        $query = "UPDATE " . $this->table . " SET is_featured = :featured WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':featured', $featured, PDO::PARAM_BOOL);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
}
?>
