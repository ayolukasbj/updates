<?php
// songs.php - All Songs Page
require_once 'config/config.php';
require_once 'config/database.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 24; // Initial load: 24 songs
$offset = ($page - 1) * $per_page;

// Get all songs from database
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get total count
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM songs s
        LEFT JOIN users u ON s.uploaded_by = u.id
        WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
    ");
    $countStmt->execute();
    $total_songs = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_songs / $per_page);
    
    // Get paginated songs
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
        ORDER BY s.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $all_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique genres (from all songs, not just current page)
    $genresStmt = $conn->prepare("SELECT DISTINCT genre FROM songs WHERE genre IS NOT NULL AND genre != ''");
    $genresStmt->execute();
    $genres = $genresStmt->fetchAll(PDO::FETCH_COLUMN);
    sort($genres);
} catch (Exception $e) {
    error_log("Songs page error: " . $e->getMessage());
    $all_songs = [];
    $genres = [];
    $total_songs = 0;
    $total_pages = 0;
}
?>
<?php
// Load theme settings
require_once __DIR__ . '/includes/theme-loader.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Songs - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/luo-style.css" rel="stylesheet">
    <?php renderThemeStyles(); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding-bottom: 120px;
        }


        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 25px 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            text-align: center;
        }

        .songs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .song-card {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
        }

        .song-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-3px);
        }

        .song-cover {
            position: relative;
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            overflow: hidden;
        }

        .song-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .song-play-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        
        .song-play-overlay .play-button {
            pointer-events: all;
        }

        .song-card:hover .song-play-overlay {
            opacity: 1;
        }

        .play-button {
            width: 60px;
            height: 60px;
            background: #2196F3;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .play-button:hover {
            background: #1976D2;
            transform: scale(1.1);
        }

        .song-details {
            padding: 15px;
        }

        .song-title {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: color 0.3s;
        }

        .song-title:hover {
            color: #1e4d72;
        }

        .song-cover {
            cursor: pointer;
            transition: transform 0.3s;
        }

        .song-cover:hover {
            transform: scale(1.02);
        }

        .song-artist {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .song-stats {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #999;
        }

        .song-stats span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        @media (max-width: 768px) {
            .songs-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 15px !important;
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

    <div class="main-content">
        <div class="songs-grid">
            <?php foreach ($all_songs as $song): ?>
            <div class="song-card">
                <?php
                // Generate song slug for URL
                $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['title']));
                $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
                $songArtistForSlug = $song['artist'] ?? 'unknown-artist';
                $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $songArtistForSlug));
                $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
                $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
                ?>
                <a href="/song/<?php echo urlencode($songSlug); ?>" class="song-cover" style="text-decoration: none; display: block;">
                    <?php if (!empty($song['cover_art'])): ?>
                        <img src="<?php echo $song['cover_art']; ?>" alt="<?php echo htmlspecialchars($song['title']); ?>">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-size: 48px;">
                            <i class="fas fa-music"></i>
                        </div>
                    <?php endif; ?>
                    <div class="song-play-overlay">
                        <button class="play-button" onclick="event.preventDefault(); event.stopPropagation(); playSong('<?php echo $song['id']; ?>')">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                </a>
                <div class="song-details">
                    <a href="/song/<?php echo urlencode($songSlug); ?>" style="text-decoration: none; color: inherit;">
                        <h5 class="song-title"><?php echo htmlspecialchars($song['title']); ?></h5>
                    </a>
                    <p class="song-artist">
                        <?php 
                        // Display collaboration artists if applicable
                        if (!empty($song['is_collaboration'])) {
                            try {
                                $all_artist_names = [];
                                
                                // First, get uploader
                                if (!empty($song['uploaded_by'])) {
                                    $uploaderStmt = $conn->prepare("
                                        SELECT COALESCE(artist, stage_name, username) as artist_name, username 
                                        FROM users WHERE id = ?
                                    ");
                                    $uploaderStmt->execute([$song['uploaded_by']]);
                                    $uploader = $uploaderStmt->fetch(PDO::FETCH_ASSOC);
                                    if ($uploader && !empty($uploader['artist_name'])) {
                                        $all_artist_names[] = htmlspecialchars($uploader['artist_name']);
                                    }
                                }
                                
                                // Then get all collaborators
                                $collabStmt = $conn->prepare("
                                    SELECT DISTINCT sc.user_id, 
                                           COALESCE(u.artist, u.stage_name, u.username, sc.user_id) as artist_name
                                    FROM song_collaborators sc
                                    LEFT JOIN users u ON sc.user_id = u.id
                                    WHERE sc.song_id = ?
                                    ORDER BY sc.added_at ASC
                                ");
                                $collabStmt->execute([$song['id']]);
                                $collaborators = $collabStmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (!empty($collaborators)) {
                                    foreach ($collaborators as $c) {
                                        $collab_name = htmlspecialchars($c['artist_name'] ?? 'Unknown');
                                        // Avoid duplicating uploader
                                        if (!in_array($collab_name, $all_artist_names)) {
                                            $all_artist_names[] = $collab_name;
                                        }
                                    }
                                }
                                
                                if (count($all_artist_names) > 0) {
                                    echo implode(' x ', $all_artist_names);
                                } else {
                                    echo htmlspecialchars($song['artist']);
                                }
                            } catch (Exception $e) {
                                echo htmlspecialchars($song['artist']);
                            }
                        } else {
                            echo htmlspecialchars($song['artist']);
                        }
                        ?>
                    </p>
                    <div class="song-stats">
                        <span><i class="fas fa-play"></i> <?php echo $song['plays'] ?? 0; ?></span>
                        <span><i class="fas fa-download"></i> <?php echo $song['downloads'] ?? 0; ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Load More Button -->
        <?php if ($page < $total_pages): ?>
        <div style="text-align: center; margin-top: 40px;">
            <button id="loadMoreSongs" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 40px; border-radius: 25px; border: none; font-weight: 600; font-size: 16px; cursor: pointer; transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                <i class="fas fa-spinner fa-spin" id="loadMoreSongsSpinner" style="display: none; margin-right: 10px;"></i>
                <span id="loadMoreSongsText">Load More Songs</span>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let currentSongPage = <?php echo $page; ?>;
    const totalSongPages = <?php echo $total_pages; ?>;
    const loadMoreSongsBtn = document.getElementById('loadMoreSongs');
    const loadMoreSongsSpinner = document.getElementById('loadMoreSongsSpinner');
    const loadMoreSongsText = document.getElementById('loadMoreSongsText');
    const songsGrid = document.querySelector('.songs-grid');
    
    if (loadMoreSongsBtn) {
        loadMoreSongsBtn.addEventListener('click', function() {
            if (currentSongPage >= totalSongPages) return;
            
            currentSongPage++;
            loadMoreSongsSpinner.style.display = 'inline-block';
            loadMoreSongsText.textContent = 'Loading...';
            loadMoreSongsBtn.disabled = true;
            
            fetch(`songs.php?page=${currentSongPage}`)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newSongs = doc.querySelectorAll('.song-card');
                    
                    newSongs.forEach(song => {
                        songsGrid.appendChild(song);
                    });
                    
                    loadMoreSongsSpinner.style.display = 'none';
                    loadMoreSongsText.textContent = currentSongPage >= totalSongPages ? 'All Songs Loaded' : 'Load More Songs';
                    
                    if (currentSongPage >= totalSongPages) {
                        loadMoreSongsBtn.style.opacity = '0.5';
                        loadMoreSongsBtn.style.cursor = 'not-allowed';
                        loadMoreSongsBtn.style.pointerEvents = 'none';
                    } else {
                        loadMoreSongsBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error loading more songs:', error);
                    loadMoreSongsSpinner.style.display = 'none';
                    loadMoreSongsText.textContent = 'Load More Songs';
                    loadMoreSongsBtn.disabled = false;
                });
        });
    }
    </script>
    <script src="assets/js/luo-player.js"></script>
    <script>
        const player = new LuoPlayer();

        function playSong(songId) {
            fetch(`api/song-data.php?id=${songId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        player.loadSong(data.song);
                        player.play();
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    </script>
    <!-- Include Footer -->
    <?php include 'includes/footer.php'; ?>
</body>
</html>

