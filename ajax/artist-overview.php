<?php
// ajax/artist-overview.php - Overview tab content for artist profile
require_once '../config/config.php';
require_once '../config/database.php';

$artist_name = isset($_GET['artist_name']) ? trim($_GET['artist_name']) : '';
$artist_id = isset($_GET['artist_id']) ? (int)$_GET['artist_id'] : 0;

if (empty($artist_name) && empty($artist_id)) {
    echo '<p style="text-align: center; color: #999; padding: 40px;">No artist specified.</p>';
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get artist songs
    $artist_songs = [];
    if ($artist_id) {
        $stmt = $conn->prepare("
            SELECT DISTINCT s.*, 
                   s.title,
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
        $stmt->execute([$artist_id, $artist_id]);
        $artist_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $conn->prepare("
            SELECT DISTINCT s.*, 
                   s.title,
                   COALESCE(s.artist, u.username, 'Unknown Artist') as artist_name,
                   CASE WHEN EXISTS (
                       SELECT 1 FROM song_collaborators sc2 WHERE sc2.song_id = s.id
                   ) THEN 1 ELSE 0 END as is_collaboration
            FROM songs s
            LEFT JOIN users u ON s.uploaded_by = u.id
            LEFT JOIN song_collaborators sc ON sc.song_id = s.id
            WHERE LOWER(u.username) = LOWER(?)
            ORDER BY s.plays DESC, s.downloads DESC
        ");
        $stmt->execute([$artist_name]);
        $artist_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get trending songs (by plays + downloads)
    $trending_songs = [];
    if (!empty($artist_songs)) {
        $trending_songs = $artist_songs;
        usort($trending_songs, function($a, $b) {
            $score_a = ((int)($a['plays'] ?? 0) * 1) + ((int)($a['downloads'] ?? 0) * 2);
            $score_b = ((int)($b['plays'] ?? 0) * 1) + ((int)($b['downloads'] ?? 0) * 2);
            return $score_b - $score_a;
        });
        $trending_songs = array_slice($trending_songs, 0, 6);
    }
    
    // Get best songs (by downloads)
    $best_songs = [];
    if (!empty($artist_songs)) {
        $best_songs = $artist_songs;
        usort($best_songs, function($a, $b) {
            $downloads_a = (int)($a['downloads'] ?? 0);
            $downloads_b = (int)($b['downloads'] ?? 0);
            if ($downloads_b !== $downloads_a) {
                return $downloads_b - $downloads_a;
            }
            return ((int)($b['plays'] ?? 0)) - ((int)($a['plays'] ?? 0));
        });
        $best_songs = array_slice($best_songs, 0, 6);
    }
    
    // Get new releases (by date)
    $new_songs = [];
    if (!empty($artist_songs)) {
        $new_songs = $artist_songs;
        usort($new_songs, function($a, $b) {
            $date_a = strtotime($a['created_at'] ?? $a['uploaded_at'] ?? '1970-01-01');
            $date_b = strtotime($b['created_at'] ?? $b['uploaded_at'] ?? '1970-01-01');
            return $date_b - $date_a;
        });
        $new_songs = array_slice($new_songs, 0, 6);
    }
    
    // Get top songs sorted by downloads
    $top_songs = [];
    if (!empty($artist_songs)) {
        $top_songs = $artist_songs;
        usort($top_songs, function($a, $b) {
            $downloads_a = (int)($a['downloads'] ?? 0);
            $downloads_b = (int)($b['downloads'] ?? 0);
            if ($downloads_b !== $downloads_a) {
                return $downloads_b - $downloads_a;
            }
            $plays_a = (int)($a['plays'] ?? 0);
            $plays_b = (int)($b['plays'] ?? 0);
            return $plays_b - $plays_a;
        });
        $top_songs = array_slice($top_songs, 0, 10);
    }
    
} catch (Exception $e) {
    error_log("Error in artist-overview.php: " . $e->getMessage());
    $artist_songs = [];
    $trending_songs = [];
    $best_songs = [];
    $new_songs = [];
    $top_songs = [];
}
?>

<!-- Featured Playlists -->
<div class="playlists-section">
    <h2 class="section-title">Featured in Playlist</h2>
    <div class="playlist-grid">
        <div class="playlist-card" onclick="showPlaylistSongs('trending', <?php echo htmlspecialchars(json_encode($trending_songs)); ?>)">
            <div class="playlist-icon">
                <i class="fas fa-fire"></i>
            </div>
            <div class="playlist-title">Trending Now</div>
            <div class="playlist-description"><?php echo count($trending_songs); ?> songs</div>
        </div>
        <div class="playlist-card" onclick="showPlaylistSongs('best', <?php echo htmlspecialchars(json_encode($best_songs)); ?>)">
            <div class="playlist-icon">
                <i class="fas fa-star"></i>
            </div>
            <div class="playlist-title">Best of <?php echo htmlspecialchars($artist_name); ?></div>
            <div class="playlist-description"><?php echo count($best_songs); ?> songs</div>
        </div>
        <div class="playlist-card" onclick="showPlaylistSongs('new', <?php echo htmlspecialchars(json_encode($new_songs)); ?>)">
            <div class="playlist-icon">
                <i class="fas fa-music"></i>
            </div>
            <div class="playlist-title">New Releases</div>
            <div class="playlist-description"><?php echo count($new_songs); ?> songs</div>
        </div>
    </div>
</div>

<!-- Playlist Songs Display Area -->
<div id="playlist-songs-display" style="display: none; margin-top: 30px; background: rgba(255, 255, 255, 0.95); border-radius: 20px; padding: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 id="playlist-songs-title" style="color: #333; font-weight: 600; margin: 0;"></h3>
        <button onclick="closePlaylistSongs()" style="background: none; border: none; color: #666; font-size: 24px; cursor: pointer; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div id="playlist-songs-list" class="songs-grid"></div>
</div>

<!-- Top Songs -->
<div class="charts-section" style="margin-top: 30px;">
    <h2 class="section-title" style="color: #333; margin-bottom: 20px;">Top Songs</h2>
    <p style="color: #666; margin-bottom: 25px;">Most downloaded songs by <?php echo htmlspecialchars($artist_name); ?></p>
    <?php if (!empty($top_songs)): ?>
    <div style="background: #f8f9fa; border-radius: 10px; overflow: hidden;">
        <?php foreach ($top_songs as $index => $song): 
            $cover_art = !empty($song['cover_art']) ? $song['cover_art'] : 'assets/images/default-avatar.svg';
            $main_artist = $song['artist'] ?? $song['artist_name'] ?? 'Unknown Artist';
            
            $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['title']));
            $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
            $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $main_artist));
            $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
            $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
        ?>
        <div style="display: flex; align-items: center; padding: 15px 20px; border-bottom: 1px solid #e9ecef; transition: background 0.2s; cursor: pointer;" 
             onmouseover="this.style.background='#fff';" 
             onmouseout="this.style.background='transparent';"
             onclick="window.location.href='/song/<?php echo urlencode($songSlug); ?>'">
            <div style="width: 50px; text-align: center; font-size: 24px; font-weight: 700; color: #667eea; margin-right: 20px; flex-shrink: 0;">
                <?php echo $index + 1; ?>
            </div>
            <div style="width: 60px; height: 60px; border-radius: 8px; overflow: hidden; margin-right: 15px; flex-shrink: 0; background: #e9ecef;">
                <img src="<?php echo htmlspecialchars($cover_art); ?>" alt="<?php echo htmlspecialchars($song['title']); ?>" 
                     style="width: 100%; height: 100%; object-fit: cover;" 
                     onerror="this.src='assets/images/default-avatar.svg'">
            </div>
            <div style="flex: 1; min-width: 0;">
                <div style="font-size: 16px; font-weight: 600; color: #333; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <?php echo htmlspecialchars($song['title']); ?>
                </div>
                <div style="font-size: 14px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <?php echo htmlspecialchars($main_artist); ?>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 20px; margin-left: 20px; flex-shrink: 0;">
                <div style="text-align: right;">
                    <div style="font-size: 14px; font-weight: 600; color: #333;">
                        <i class="fas fa-download" style="color: #667eea; margin-right: 5px;"></i>
                        <?php echo number_format((int)($song['downloads'] ?? 0)); ?>
                    </div>
                    <div style="font-size: 12px; color: #999; margin-top: 2px;">
                        <i class="fas fa-play" style="margin-right: 3px;"></i>
                        <?php echo number_format((int)($song['plays'] ?? 0)); ?> plays
                    </div>
                </div>
                <button class="song-card-play-btn" 
                        style="position: static; width: 40px; height: 40px; flex-shrink: 0;"
                        onclick="event.stopPropagation(); playSongCard(this)"
                        data-song-id="<?php echo $song['id']; ?>"
                        data-song-title="<?php echo htmlspecialchars($song['title']); ?>"
                        data-song-artist="<?php echo htmlspecialchars($song['artist'] ?? $song['artist_name'] ?? ''); ?>"
                        data-song-cover="<?php echo htmlspecialchars($song['cover_art'] ?? ''); ?>">
                    <i class="fas fa-play"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align: center; padding: 40px; color: #999;">
        <i class="fas fa-music" style="font-size: 48px; margin-bottom: 15px; display: block; opacity: 0.3;"></i>
        <p>No songs available yet.</p>
    </div>
    <?php endif; ?>
</div>

<script>
function showPlaylistSongs(type, songs) {
    const displayDiv = document.getElementById('playlist-songs-display');
    const titleDiv = document.getElementById('playlist-songs-title');
    const listDiv = document.getElementById('playlist-songs-list');
    
    if (!displayDiv || !titleDiv || !listDiv) return;
    
    const titles = {
        'trending': 'Trending Now',
        'best': 'Best of <?php echo htmlspecialchars($artist_name); ?>',
        'new': 'New Releases'
    };
    
    titleDiv.textContent = titles[type] || 'Playlist';
    listDiv.innerHTML = '';
    
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
    
    displayDiv.style.display = 'block';
    displayDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function closePlaylistSongs() {
    const displayDiv = document.getElementById('playlist-songs-display');
    if (displayDiv) {
        displayDiv.style.display = 'none';
    }
}
</script>

