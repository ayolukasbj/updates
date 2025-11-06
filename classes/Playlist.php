<?php
// classes/Playlist.php
// Playlist model for playlist management

class Playlist {
    private $conn;
    private $table = 'playlists';

    public $id;
    public $name;
    public $description;
    public $user_id;
    public $is_public;
    public $cover_image;
    public $total_tracks;
    public $total_duration;
    public $plays;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create new playlist
    public function createPlaylist($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (name, description, user_id, is_public, cover_image) 
                  VALUES (:name, :description, :user_id, :is_public, :cover_image)";

        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->bindParam(':is_public', $data['is_public']);
        $stmt->bindParam(':cover_image', $data['cover_image']);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return ['success' => true, 'playlist_id' => $this->id];
        }

        return ['success' => false, 'error' => 'Failed to create playlist'];
    }

    // Get playlist by ID
    public function getPlaylistById($id) {
        $query = "SELECT p.*, u.username as creator_name
                  FROM " . $this->table . " p
                  LEFT JOIN users u ON p.user_id = u.id
                  WHERE p.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get playlist songs
    public function getPlaylistSongs($playlist_id) {
        $query = "SELECT s.*, a.name as artist_name, al.title as album_title, g.name as genre_name
                  FROM playlist_songs ps
                  JOIN songs s ON ps.song_id = s.id
                  LEFT JOIN artists a ON s.artist_id = a.id
                  LEFT JOIN albums al ON s.album_id = al.id
                  LEFT JOIN genres g ON s.genre_id = g.id
                  WHERE ps.playlist_id = :playlist_id
                  ORDER BY ps.position ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':playlist_id', $playlist_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get user's playlists
    public function getUserPlaylists($user_id, $limit = 20) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC 
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get public playlists
    public function getPublicPlaylists($page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT p.*, u.username as creator_name
                  FROM " . $this->table . " p
                  LEFT JOIN users u ON p.user_id = u.id
                  WHERE p.is_public = 1
                  ORDER BY p.plays DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Add song to playlist
    public function addSongToPlaylist($playlist_id, $song_id) {
        // Check if song already exists in playlist
        $check_query = "SELECT id FROM playlist_songs WHERE playlist_id = :playlist_id AND song_id = :song_id";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':playlist_id', $playlist_id);
        $check_stmt->bindParam(':song_id', $song_id);
        $check_stmt->execute();

        if ($check_stmt->rowCount() > 0) {
            return ['success' => false, 'error' => 'Song already in playlist'];
        }

        // Get next position
        $position_query = "SELECT MAX(position) as max_pos FROM playlist_songs WHERE playlist_id = :playlist_id";
        $position_stmt = $this->conn->prepare($position_query);
        $position_stmt->bindParam(':playlist_id', $playlist_id);
        $position_stmt->execute();
        
        $position = $position_stmt->fetch(PDO::FETCH_ASSOC)['max_pos'] + 1;

        // Add song to playlist
        $query = "INSERT INTO playlist_songs (playlist_id, song_id, position) 
                  VALUES (:playlist_id, :song_id, :position)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':playlist_id', $playlist_id);
        $stmt->bindParam(':song_id', $song_id);
        $stmt->bindParam(':position', $position);

        if ($stmt->execute()) {
            // Update playlist track count
            $this->updateTrackCount($playlist_id);
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Failed to add song to playlist'];
    }

    // Remove song from playlist
    public function removeSongFromPlaylist($playlist_id, $song_id) {
        $query = "DELETE FROM playlist_songs WHERE playlist_id = :playlist_id AND song_id = :song_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':playlist_id', $playlist_id);
        $stmt->bindParam(':song_id', $song_id);

        if ($stmt->execute()) {
            // Update playlist track count
            $this->updateTrackCount($playlist_id);
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Failed to remove song from playlist'];
    }

    // Reorder playlist songs
    public function reorderPlaylistSongs($playlist_id, $song_orders) {
        $this->conn->beginTransaction();

        try {
            foreach ($song_orders as $song_id => $position) {
                $query = "UPDATE playlist_songs SET position = :position 
                          WHERE playlist_id = :playlist_id AND song_id = :song_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':position', $position);
                $stmt->bindParam(':playlist_id', $playlist_id);
                $stmt->bindParam(':song_id', $song_id);
                $stmt->execute();
            }

            $this->conn->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'error' => 'Failed to reorder playlist'];
        }
    }

    // Update playlist
    public function updatePlaylist($id, $data) {
        $query = "UPDATE " . $this->table . " 
                  SET name = :name, description = :description, 
                      is_public = :is_public, cover_image = :cover_image,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':is_public', $data['is_public']);
        $stmt->bindParam(':cover_image', $data['cover_image']);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }

    // Delete playlist
    public function deletePlaylist($id) {
        // Get playlist info first
        $playlist = $this->getPlaylistById($id);
        
        if (!$playlist) {
            return ['success' => false, 'error' => 'Playlist not found'];
        }

        // Delete cover image
        if ($playlist['cover_image'] && file_exists($playlist['cover_image'])) {
            unlink($playlist['cover_image']);
        }

        // Delete playlist songs first
        $query = "DELETE FROM playlist_songs WHERE playlist_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        // Delete playlist
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    // Duplicate playlist
    public function duplicatePlaylist($playlist_id, $new_name, $user_id) {
        $this->conn->beginTransaction();

        try {
            // Get original playlist
            $original = $this->getPlaylistById($playlist_id);
            if (!$original) {
                throw new Exception('Original playlist not found');
            }

            // Create new playlist
            $new_playlist_data = [
                'name' => $new_name,
                'description' => $original['description'],
                'user_id' => $user_id,
                'is_public' => 0, // Private by default
                'cover_image' => null
            ];

            $result = $this->createPlaylist($new_playlist_data);
            if (!$result['success']) {
                throw new Exception($result['error']);
            }

            $new_playlist_id = $result['playlist_id'];

            // Copy songs
            $songs = $this->getPlaylistSongs($playlist_id);
            foreach ($songs as $song) {
                $this->addSongToPlaylist($new_playlist_id, $song['id']);
            }

            $this->conn->commit();
            return ['success' => true, 'playlist_id' => $new_playlist_id];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Search playlists
    public function searchPlaylists($query, $limit = 20) {
        $search_query = "SELECT p.*, u.username as creator_name
                         FROM " . $this->table . " p
                         LEFT JOIN users u ON p.user_id = u.id
                         WHERE p.is_public = 1 AND (p.name LIKE :query OR p.description LIKE :query)
                         ORDER BY 
                             CASE 
                                 WHEN p.name LIKE :exact THEN 1
                                 WHEN p.name LIKE :start THEN 2
                                 ELSE 3
                             END,
                             p.plays DESC
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

    // Get featured playlists
    public function getFeaturedPlaylists($limit = 10) {
        $query = "SELECT p.*, u.username as creator_name
                  FROM " . $this->table . " p
                  LEFT JOIN users u ON p.user_id = u.id
                  WHERE p.is_public = 1 AND p.total_tracks > 0
                  ORDER BY p.plays DESC
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

    // Update track count
    private function updateTrackCount($playlist_id) {
        $query = "UPDATE " . $this->table . " SET total_tracks = (
                    SELECT COUNT(*) FROM playlist_songs WHERE playlist_id = :playlist_id
                  ) WHERE id = :playlist_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':playlist_id', $playlist_id);
        $stmt->execute();
    }

    // Get playlist statistics
    public function getPlaylistStats($id) {
        $stats = [];
        
        // Get total duration
        $query = "SELECT SUM(s.duration) as total_duration
                  FROM playlist_songs ps
                  JOIN songs s ON ps.song_id = s.id
                  WHERE ps.playlist_id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $stats['total_duration'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_duration'] ?? 0;
        
        // Get genre distribution
        $query = "SELECT g.name, COUNT(*) as count
                  FROM playlist_songs ps
                  JOIN songs s ON ps.song_id = s.id
                  LEFT JOIN genres g ON s.genre_id = g.id
                  WHERE ps.playlist_id = :id
                  GROUP BY g.id, g.name
                  ORDER BY count DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $stats['genres'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }
}
?>
