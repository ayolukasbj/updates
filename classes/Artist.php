<?php
// classes/Artist.php
// Artist model for artist management

class Artist {
    private $conn;
    private $table = 'artists';

    public $id;
    public $name;
    public $bio;
    public $avatar;
    public $cover_image;
    public $verified;
    public $user_id;
    public $social_links;
    public $total_plays;
    public $total_downloads;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create new artist
    public function createArtist($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (name, bio, avatar, cover_image, user_id, social_links) 
                  VALUES (:name, :bio, :avatar, :cover_image, :user_id, :social_links)";

        $stmt = $this->conn->prepare($query);
        
        $social_links = json_encode($data['social_links'] ?? []);
        
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':bio', $data['bio']);
        $stmt->bindParam(':avatar', $data['avatar']);
        $stmt->bindParam(':cover_image', $data['cover_image']);
        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->bindParam(':social_links', $social_links);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return ['success' => true, 'artist_id' => $this->id];
        }

        return ['success' => false, 'error' => 'Failed to create artist'];
    }

    // Get artist by ID
    public function getArtistById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $artist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($artist && $artist['social_links']) {
            $artist['social_links'] = json_decode($artist['social_links'], true);
        }

        return $artist;
    }

    // Get all artists with pagination
    public function getArtists($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $where_conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where_conditions[] = "name LIKE :search";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['verified'])) {
            $where_conditions[] = "verified = :verified";
            $params[':verified'] = $filters['verified'];
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $query = "SELECT * FROM " . $this->table . " 
                  " . $where_clause . "
                  ORDER BY total_plays DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $artists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode social links for each artist
        foreach ($artists as &$artist) {
            if ($artist['social_links']) {
                $artist['social_links'] = json_decode($artist['social_links'], true);
            }
        }

        return $artists;
    }

    // Get featured artists
    public function getFeaturedArtists($limit = 10) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE verified = 1 
                  ORDER BY total_plays DESC 
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get artist's songs
    public function getArtistSongs($artist_id, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT s.*, al.title as album_title, g.name as genre_name
                  FROM songs s
                  LEFT JOIN albums al ON s.album_id = al.id
                  LEFT JOIN genres g ON s.genre_id = g.id
                  WHERE s.artist_id = :artist_id
                  ORDER BY s.upload_date DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':artist_id', $artist_id);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get artist's albums
    public function getArtistAlbums($artist_id) {
        $query = "SELECT * FROM albums WHERE artist_id = :artist_id ORDER BY release_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':artist_id', $artist_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Update artist
    public function updateArtist($id, $data) {
        $query = "UPDATE " . $this->table . " 
                  SET name = :name, bio = :bio, avatar = :avatar, 
                      cover_image = :cover_image, social_links = :social_links,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        
        $social_links = json_encode($data['social_links'] ?? []);
        
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':bio', $data['bio']);
        $stmt->bindParam(':avatar', $data['avatar']);
        $stmt->bindParam(':cover_image', $data['cover_image']);
        $stmt->bindParam(':social_links', $social_links);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }

    // Verify artist
    public function verifyArtist($id) {
        $query = "UPDATE " . $this->table . " SET verified = 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    // Get genres
    public function getGenres() {
        $query = "SELECT * FROM genres ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get artist statistics
    public function getArtistStats($id) {
        $stats = [];
        
        // Get total songs
        $query = "SELECT COUNT(*) as count FROM songs WHERE artist_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $stats['total_songs'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Get total albums
        $query = "SELECT COUNT(*) as count FROM albums WHERE artist_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $stats['total_albums'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Get total plays
        $query = "SELECT SUM(plays) as total FROM songs WHERE artist_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $stats['total_plays'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Get total downloads
        $query = "SELECT SUM(downloads) as total FROM songs WHERE artist_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $stats['total_downloads'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        return $stats;
    }

    // Search artists
    public function searchArtists($query, $limit = 20) {
        try {
            // Check if artists table exists, if not search users table instead
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'artists'");
            $tableExists = $tableCheck->rowCount() > 0;
            
            if ($tableExists) {
                // Search artists table
                $search_query = "SELECT * FROM " . $this->table . " 
                                 WHERE name LIKE :query 
                                 ORDER BY 
                                     CASE 
                                         WHEN name LIKE :exact THEN 1
                                         WHEN name LIKE :start THEN 2
                                         ELSE 3
                                     END,
                                     total_plays DESC
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
            } else {
                // Artists table doesn't exist, search users table for artists
                $search_query = "SELECT DISTINCT u.id, u.username as name, 
                                        COALESCE(SUM(s.plays), 0) as total_plays,
                                        COUNT(DISTINCT s.id) as total_songs
                                 FROM users u
                                 LEFT JOIN songs s ON s.uploaded_by = u.id
                                 WHERE u.username LIKE :query
                                 GROUP BY u.id, u.username
                                 ORDER BY 
                                     CASE 
                                         WHEN u.username LIKE :exact THEN 1
                                         WHEN u.username LIKE :start THEN 2
                                         ELSE 3
                                     END,
                                     total_plays DESC
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
        } catch (PDOException $e) {
            error_log("Artist search PDO error: " . $e->getMessage());
            return [];
        } catch (Exception $e) {
            error_log("Artist search error: " . $e->getMessage());
            return [];
        }
    }

    // Delete artist
    public function deleteArtist($id) {
        // Get artist info first
        $artist = $this->getArtistById($id);
        
        if (!$artist) {
            return ['success' => false, 'error' => 'Artist not found'];
        }

        // Delete avatar and cover images
        if ($artist['avatar'] && file_exists($artist['avatar'])) {
            unlink($artist['avatar']);
        }
        
        if ($artist['cover_image'] && file_exists($artist['cover_image'])) {
            unlink($artist['cover_image']);
        }

        // Delete from database
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }


    // Get artist by user ID
    public function getArtistByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get artist by name
    public function getArtistByName($name) {
        $query = "SELECT * FROM " . $this->table . " WHERE name = :name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
