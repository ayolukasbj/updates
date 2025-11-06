<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/song-storage.php';

$db = new Database();
$conn = $db->getConnection();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 24; // Initial load: 24 artists (4 rows of 6 on desktop)
$offset = ($page - 1) * $per_page;

// Get total count for pagination (including collaborators)
try {
    // Check if song_collaborators table exists
    $collabTableExists = false;
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE 'song_collaborators'");
        $collabTableExists = $checkTable->rowCount() > 0;
    } catch (Exception $e) {
        $collabTableExists = false;
    }
    
    $countSql = "
        SELECT COUNT(DISTINCT artist_name) as total
        FROM (
            SELECT COALESCE(s.artist, u.username) as artist_name
            FROM songs s
            LEFT JOIN users u ON s.uploaded_by = u.id
            WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
            AND (COALESCE(s.artist, u.username) IS NOT NULL)
            
            " . ($collabTableExists ? "
            UNION
            SELECT u2.username as artist_name
            FROM song_collaborators sc
            INNER JOIN songs s2 ON sc.song_id = s2.id
            INNER JOIN users u2 ON sc.user_id = u2.id
            WHERE (s2.status = 'active' OR s2.status IS NULL OR s2.status = '' OR s2.status = 'approved')
            AND u2.username IS NOT NULL
            " : "") . "
        ) as combined_artists
    ";
    
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute();
    $total_artists = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_artists / $per_page);
} catch (Exception $e) {
    error_log("Error counting artists: " . $e->getMessage());
    $total_artists = 0;
    $total_pages = 0;
}

// Get artists from database - include both uploaders and collaborators
try {
    // Check if song_collaborators table exists
    $collabTableExists = false;
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE 'song_collaborators'");
        $collabTableExists = $checkTable->rowCount() > 0;
    } catch (Exception $e) {
        $collabTableExists = false;
    }
    
    // Get unique artists from songs (uploaders) and collaborators
    // Calculate stats correctly - each song counted once per artist, plays/downloads summed correctly
    // Group by user_id first (if available) to prevent duplicates, then by normalized artist name
    $sql = "
        SELECT 
            COALESCE(MAX(primary_artist_name), MAX(artist_name)) as name,
            MAX(avatar) as avatar,
            MAX(user_id) as id,
            COUNT(DISTINCT song_id) as songs_count,
            SUM(song_plays) as total_plays,
            SUM(song_downloads) as total_downloads
        FROM (
            -- First, get unique artist-song combinations with MAX plays/downloads per song
            SELECT 
                artist_name,
                primary_artist_name,
                avatar,
                user_id,
                song_id,
                MAX(song_plays) as song_plays,
                MAX(song_downloads) as song_downloads
            FROM (
                -- Songs uploaded by user
                SELECT 
                    COALESCE(s.artist, u.username, 'Unknown Artist') as artist_name,
                    COALESCE(u.username, s.artist, 'Unknown Artist') as primary_artist_name,
                    COALESCE(u.avatar, '') as avatar,
                    COALESCE(u.id, 0) as user_id,
                    s.id as song_id,
                    COALESCE(s.plays, 0) as song_plays,
                    COALESCE(s.downloads, 0) as song_downloads
                FROM songs s
                LEFT JOIN users u ON s.uploaded_by = u.id
                WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                AND (COALESCE(s.artist, u.username) IS NOT NULL)
                
                " . ($collabTableExists ? "
                UNION ALL
                -- Songs where user is a collaborator (only if they're not already the uploader)
                SELECT 
                    COALESCE(u2.username, 'Unknown Artist') as artist_name,
                    COALESCE(u2.username, 'Unknown Artist') as primary_artist_name,
                    COALESCE(u2.avatar, '') as avatar,
                    COALESCE(u2.id, 0) as user_id,
                    s2.id as song_id,
                    COALESCE(s2.plays, 0) as song_plays,
                    COALESCE(s2.downloads, 0) as song_downloads
                FROM song_collaborators sc
                INNER JOIN songs s2 ON sc.song_id = s2.id
                INNER JOIN users u2 ON sc.user_id = u2.id
                LEFT JOIN users u3 ON s2.uploaded_by = u3.id
                WHERE (s2.status = 'active' OR s2.status IS NULL OR s2.status = '' OR s2.status = 'approved')
                AND u2.username IS NOT NULL
                -- Exclude if this artist is already the uploader for this song (by user_id to prevent duplicates)
                AND NOT (s2.uploaded_by = u2.id OR COALESCE(s2.artist, u3.username) = COALESCE(u2.username, 'Unknown Artist'))
                " : "") . "
            ) as all_artist_songs
            GROUP BY artist_name, primary_artist_name, song_id, avatar, user_id
        ) as unique_songs
        GROUP BY 
            CASE WHEN user_id > 0 THEN user_id ELSE NULL END,
            LOWER(TRIM(COALESCE(primary_artist_name, artist_name)))
        HAVING songs_count > 0
        ORDER BY total_plays DESC, songs_count DESC, name ASC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(1, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $artists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no artists found, try getFeaturedArtists as fallback
    if (empty($artists) && $page == 1) {
        $artists = array_slice(getFeaturedArtists(50), 0, $per_page);
    }
} catch (Exception $e) {
    error_log("Error fetching artists: " . $e->getMessage());
    // Fallback to getFeaturedArtists
    if ($page == 1) {
        $artists = array_slice(getFeaturedArtists(50), 0, $per_page);
    } else {
        $artists = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artists - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            padding-bottom: 0 !important;
        }
    </style>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
        }
        
        .artists-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 15px;
        }
        
        h1 {
            font-size: 28px;
            margin-bottom: 25px;
            color: #333;
        }
        
        .artists-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .artists-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 15px !important;
            }
        }
        
        .artist-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            position: relative;
        }
        
        .artist-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .artist-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            margin-bottom: 15px;
        }
        
        .artist-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .artist-avatar i {
            font-size: 60px;
            color: white;
        }
        
        .verified-badge {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: #4CAF50;
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            border: 2px solid white;
        }
        
        .artist-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #2c3e50;
            text-align: center;
        }
        
        .artist-stats {
            display: flex;
            justify-content: center;
            gap: 20px;
            font-size: 13px;
            color: #999;
            margin-bottom: 15px;
        }
        
        .artist-stats span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .view-profile-btn {
            background: #ff6600;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .view-profile-btn:hover {
            background: #ff8533;
        }
        
        .no-artists {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
        
        .no-artists i {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
            display: block;
        }
        
        .no-artists h3 {
            font-size: 22px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .no-artists p {
            color: #999;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="artists-container">
        <h1><i class="fas fa-users"></i> All Artists</h1>
        
        <?php if (empty($artists)): ?>
            <div class="no-artists">
                <i class="fas fa-microphone-alt"></i>
                <h3>No Artists Yet</h3>
                <p>Be the first to upload music and become an artist!</p>
                <?php if (is_logged_in()): ?>
                    <a href="upload.php" class="view-profile-btn">Upload Music</a>
                <?php else: ?>
                    <a href="register.php" class="view-profile-btn">Get Started</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="artists-grid">
                <?php 
                // Calculate rank based on offset
                $rank = $offset + 1;
                $processed_artists = []; // Track processed artists by user_id and normalized name to avoid duplicates
                
                foreach ($artists as $artist_item): 
                    // Create a unique key for this artist: prefer user_id if available, otherwise normalized name
                    $artist_id = !empty($artist_item['id']) && $artist_item['id'] > 0 ? $artist_item['id'] : null;
                    $normalized_name = strtolower(trim(preg_replace('/\s+/', ' ', $artist_item['name'] ?? '')));
                    $unique_key = $artist_id ? "user_{$artist_id}" : "name_{$normalized_name}";
                    
                    // Skip if we've already processed this artist
                    if (isset($processed_artists[$unique_key])) {
                        continue;
                    }
                    $processed_artists[$unique_key] = true;
                    
                    // Get artist slug for URL
                    $artistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $artist_item['name'] ?? ''));
                    $artistSlug = preg_replace('/\s+/', '-', trim($artistSlug));
                    $artistProfileUrl = !empty($artist_item['id']) && $artist_item['id'] > 0 ? 'artist-profile.php?id=' . $artist_item['id'] : '/artist/' . urlencode($artistSlug);
                    ?>
                    <div class="artist-card" onclick="window.location.href='<?php echo $artistProfileUrl; ?>'">
                        <div class="artist-avatar">
                            <?php if (!empty($artist_item['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($artist_item['avatar']); ?>" 
                                     alt="<?php echo htmlspecialchars($artist_item['name']); ?>">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                            <?php if ($rank <= 3): ?>
                                <div style="position: absolute; top: 5px; left: 5px; background: <?php echo $rank == 1 ? '#FFD700' : ($rank == 2 ? '#C0C0C0' : '#CD7F32'); ?>; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;">
                                    <?php echo $rank; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="artist-name">
                            <?php echo htmlspecialchars($artist_item['name']); ?>
                            <?php if ($rank > 3): ?>
                                <span style="font-size: 14px; color: #999; font-weight: normal;">#<?php echo $rank; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="artist-stats">
                            <span>
                                <i class="fas fa-music"></i>
                                <?php echo number_format($artist_item['songs_count'] ?? 0); ?> songs
                            </span>
                            <span>
                                <i class="fas fa-play"></i>
                                <?php echo number_format($artist_item['total_plays'] ?? 0); ?> plays
                            </span>
                        </div>
                    </div>
                    <?php $rank++; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Load More Button -->
            <?php if ($page < $total_pages): ?>
            <div style="text-align: center; margin-top: 40px;">
                <button id="loadMoreArtists" class="view-profile-btn" style="padding: 15px 40px; font-size: 16px; cursor: pointer; border: none;">
                    <i class="fas fa-spinner fa-spin" id="loadMoreSpinner" style="display: none; margin-right: 10px;"></i>
                    <span id="loadMoreText">Load More Artists</span>
                </button>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
    let currentPage = <?php echo $page; ?>;
    const totalPages = <?php echo $total_pages; ?>;
    const loadMoreBtn = document.getElementById('loadMoreArtists');
    const loadMoreSpinner = document.getElementById('loadMoreSpinner');
    const loadMoreText = document.getElementById('loadMoreText');
    const artistsGrid = document.querySelector('.artists-grid');
    
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            if (currentPage >= totalPages) return;
            
            currentPage++;
            loadMoreSpinner.style.display = 'inline-block';
            loadMoreText.textContent = 'Loading...';
            loadMoreBtn.disabled = true;
            
            fetch(`artists.php?page=${currentPage}`)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newArtists = doc.querySelectorAll('.artist-card');
                    
                    newArtists.forEach(artist => {
                        artistsGrid.appendChild(artist);
                    });
                    
                    loadMoreSpinner.style.display = 'none';
                    loadMoreText.textContent = currentPage >= totalPages ? 'All Artists Loaded' : 'Load More Artists';
                    
                    if (currentPage >= totalPages) {
                        loadMoreBtn.style.opacity = '0.5';
                        loadMoreBtn.style.cursor = 'not-allowed';
                    } else {
                        loadMoreBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error loading more artists:', error);
                    loadMoreSpinner.style.display = 'none';
                    loadMoreText.textContent = 'Load More Artists';
                    loadMoreBtn.disabled = false;
                });
        });
    }
    </script>
</body>
</html>
