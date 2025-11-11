<?php
// song-player.php - Modern music player interface
require_once 'config/config.php';
require_once 'includes/song-storage.php';

$song_id = $_GET['id'] ?? '';
$song = null;

if ($song_id) {
    $songs = getSongs();
    foreach ($songs as $s) {
        if ($s['id'] == $song_id) {
            $song = $s;
            break;
        }
    }
}

if (!$song) {
    header('Location: index.php');
    exit;
}

// Increment play count
$songs = getSongs();
foreach ($songs as &$s) {
    if ($s['id'] == $song_id) {
        $s['plays']++;
        break;
    }
}
file_put_contents('data/songs.json', json_encode($songs, JSON_PRETTY_PRINT));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($song['title']); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #000;
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            height: 100vh;
            overflow: hidden;
        }

        .player-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: linear-gradient(180deg, #1a1a1a 0%, #000 100%);
        }

        /* Status Bar */
        .status-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 20px;
            font-size: 14px;
            font-weight: 600;
            background: rgba(0,0,0,0.3);
        }

        .status-left {
            color: #fff;
        }

        .status-right {
            display: flex;
            gap: 4px;
            color: #fff;
        }

        /* Player Header */
        .player-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: rgba(0,0,0,0.5);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .minimize-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 18px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background 0.2s;
        }

        .minimize-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        .now-playing-text {
            font-size: 12px;
            color: #b3b3b3;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .song-title-header {
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            margin-top: 2px;
        }

        .menu-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 18px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background 0.2s;
        }

        .menu-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        /* Album Art */
        .album-art-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .album-art {
            width: 320px;
            height: 320px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }

        .album-art img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .album-art-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
            color: rgba(255,255,255,0.7);
        }

        /* Song Info */
        .song-info {
            padding: 0 20px 20px;
            text-align: center;
        }

        .song-title {
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
            line-height: 1.2;
        }

        .song-artist {
            font-size: 16px;
            color: #b3b3b3;
            margin-bottom: 20px;
        }

        .like-btn {
            background: none;
            border: none;
            color: #b3b3b3;
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.2s;
            margin-left: auto;
        }

        .like-btn:hover {
            color: #1db954;
            transform: scale(1.1);
        }

        .like-btn.liked {
            color: #1db954;
        }

        /* Song Stats */
        .song-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
            justify-content: center;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #b3b3b3;
            background: rgba(255,255,255,0.05);
            padding: 6px 12px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }

        .stat-item i {
            font-size: 10px;
            color: #1db954;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-left: 20px;
        }

        .download-btn {
            background: none;
            border: none;
            color: #b3b3b3;
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .download-btn:hover {
            color: #1db954;
            transform: scale(1.1);
            background: rgba(29,185,84,0.1);
        }

        /* Progress Bar */
        .progress-container {
            padding: 0 20px 20px;
        }

        .progress-bar {
            width: 100%;
            height: 4px;
            background: #404040;
            border-radius: 2px;
            cursor: pointer;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: #fff;
            border-radius: 2px;
            width: 0%;
            transition: width 0.1s ease;
        }

        .progress-times {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 12px;
            color: #b3b3b3;
        }

        /* Controls */
        .controls {
            padding: 0 20px 30px;
        }

        .control-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .control-btn {
            background: none;
            border: none;
            color: #b3b3b3;
            font-size: 18px;
            cursor: pointer;
            padding: 12px;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .control-btn:hover {
            color: #fff;
            background: rgba(255,255,255,0.1);
        }

        .play-pause-btn {
            background: #fff;
            color: #000;
            font-size: 24px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .play-pause-btn:hover {
            transform: scale(1.05);
            background: #f0f0f0;
        }

        /* Up Next */
        .up-next {
            padding: 0 20px 20px;
            border-top: 1px solid #404040;
            padding-top: 20px;
        }

        .up-next-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .up-next-title {
            font-size: 12px;
            color: #b3b3b3;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .queue-btn {
            background: none;
            border: none;
            color: #b3b3b3;
            font-size: 16px;
            cursor: pointer;
            padding: 4px;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .queue-btn:hover {
            color: #fff;
            background: rgba(255,255,255,0.1);
        }

        .next-song {
            font-size: 14px;
            color: #fff;
        }

        /* Hidden Audio Element */
        #audioPlayer {
            display: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .album-art {
                width: 280px;
                height: 280px;
            }
            
            .song-title {
                font-size: 24px;
            }
            
            .status-bar {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .album-art {
                width: 240px;
                height: 240px;
            }
            
            .song-title {
                font-size: 20px;
            }
            
            .player-header {
                padding: 15px;
            }
            
            .controls {
                padding: 0 15px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="player-container">
        <!-- Status Bar -->
        <div class="status-bar">
            <div class="status-left">9:41</div>
            <div class="status-right">
                <i class="fas fa-wifi"></i>
                <i class="fas fa-signal"></i>
                <i class="fas fa-battery-three-quarters"></i>
            </div>
        </div>

        <!-- Player Header -->
        <div class="player-header">
            <div class="header-left">
                <button class="minimize-btn" onclick="window.history.back()">
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div>
                    <div class="now-playing-text">Now Playing</div>
                    <div class="song-title-header"><?php echo htmlspecialchars($song['title']); ?></div>
                </div>
            </div>
            <button class="menu-btn">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>

        <!-- Album Art -->
        <div class="album-art-container">
            <div class="album-art">
                <?php if (!empty($song['cover_art'])): ?>
                    <img src="<?php echo htmlspecialchars($song['cover_art']); ?>" alt="Cover Art">
                <?php else: ?>
                    <div class="album-art-placeholder">
                        <i class="fas fa-music"></i>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Song Info -->
        <div class="song-info">
            <div style="display: flex; align-items: center; justify-content: center;">
                <div style="flex: 1;">
                    <h1 class="song-title"><?php echo htmlspecialchars($song['title']); ?></h1>
                    <p class="song-artist"><?php echo htmlspecialchars($song['artist']); ?></p>
                    
                    <!-- Song Stats -->
                    <div class="song-stats">
                        <div class="stat-item">
                            <i class="fas fa-play"></i>
                            <span><?php echo number_format($song['plays'] ?? 0); ?> plays</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-download"></i>
                            <span><?php echo number_format($song['downloads'] ?? 0); ?> downloads</span>
                        </div>
                        <?php if (!empty($song['album'])): ?>
                        <div class="stat-item">
                            <i class="fas fa-compact-disc"></i>
                            <span><?php echo htmlspecialchars($song['album']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($song['year'])): ?>
                        <div class="stat-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo $song['year']; ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($song['genre'])): ?>
                        <div class="stat-item">
                            <i class="fas fa-music"></i>
                            <span><?php echo htmlspecialchars($song['genre']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="action-buttons">
                    <button class="like-btn" id="likeBtn">
                        <i class="far fa-heart"></i>
                    </button>
                    <button class="download-btn" id="downloadBtn" onclick="downloadSong()">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress-container">
            <div class="progress-bar" id="progressBar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <div class="progress-times">
                <span id="currentTime">1:09</span>
                <span id="totalTime">4:04</span>
            </div>
        </div>

        <!-- Controls -->
        <div class="controls">
            <div class="control-buttons">
                <button class="control-btn" id="shuffleBtn">
                    <i class="fas fa-random"></i>
                </button>
                <button class="control-btn" id="prevBtn">
                    <i class="fas fa-step-backward"></i>
                </button>
                <button class="control-btn play-pause-btn" id="playPauseBtn">
                    <i class="fas fa-pause"></i>
                </button>
                <button class="control-btn" id="nextBtn">
                    <i class="fas fa-step-forward"></i>
                </button>
                <button class="control-btn" id="castBtn">
                    <i class="fas fa-tv"></i>
                </button>
            </div>
        </div>

        <!-- Up Next -->
        <div class="up-next">
            <div class="up-next-header">
                <span class="up-next-title">Up next</span>
                <button class="queue-btn">
                    <i class="fas fa-chevron-up"></i>
                </button>
            </div>
            <div class="next-song">Vandells - Prelude</div>
        </div>
    </div>

    <!-- Hidden Audio Element -->
    <audio id="audioPlayer" preload="metadata">
        <?php if (!empty($song['audio_file'])): ?>
            <source src="<?php echo htmlspecialchars($song['audio_file']); ?>" type="audio/mpeg">
        <?php else: ?>
            <source src="demo-audio.mp3" type="audio/mpeg">
        <?php endif; ?>
        Your browser does not support the audio element.
    </audio>

    <script>
        const audio = document.getElementById('audioPlayer');
        const playPauseBtn = document.getElementById('playPauseBtn');
        const progressBar = document.getElementById('progressBar');
        const progressFill = document.getElementById('progressFill');
        const currentTimeSpan = document.getElementById('currentTime');
        const totalTimeSpan = document.getElementById('totalTime');
        const likeBtn = document.getElementById('likeBtn');
        const shuffleBtn = document.getElementById('shuffleBtn');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const castBtn = document.getElementById('castBtn');

        let isPlaying = false;
        let isShuffled = false;
        let isLiked = false;

        // Check if coming from mini player
        const storedSongState = sessionStorage.getItem('currentSong');
        if (storedSongState) {
            const songState = JSON.parse(storedSongState);
            console.log('Restoring song state from mini player:', songState);
            
            // Restore playback position
            audio.addEventListener('loadedmetadata', function() {
                if (songState.currentTime > 0) {
                    audio.currentTime = songState.currentTime;
                }
                
                // Auto-play if it was playing in mini player
                if (songState.isPlaying) {
                    audio.play().then(() => {
                        isPlaying = true;
                        updatePlayButton();
                    }).catch(error => {
                        console.log('Auto-play failed:', error);
                    });
                }
            });
            
            // Clear stored state
            sessionStorage.removeItem('currentSong');
        }

        // Play/Pause functionality
        playPauseBtn.addEventListener('click', () => {
            if (isPlaying) {
                audio.pause();
            } else {
                audio.play();
            }
        });

        audio.addEventListener('play', () => {
            isPlaying = true;
            updatePlayButton();
        });

        audio.addEventListener('pause', () => {
            isPlaying = false;
            updatePlayButton();
        });

        function updatePlayButton() {
            const icon = playPauseBtn.querySelector('i');
            if (isPlaying) {
                icon.className = 'fas fa-pause';
            } else {
                icon.className = 'fas fa-play';
            }
        }

        // Progress bar functionality
        audio.addEventListener('timeupdate', () => {
            const progress = (audio.currentTime / audio.duration) * 100;
            progressFill.style.width = progress + '%';
            currentTimeSpan.textContent = formatTime(audio.currentTime);
        });

        audio.addEventListener('loadedmetadata', () => {
            totalTimeSpan.textContent = formatTime(audio.duration);
        });

        progressBar.addEventListener('click', (e) => {
            const rect = progressBar.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const percentage = clickX / rect.width;
            audio.currentTime = percentage * audio.duration;
        });

        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }

        // Like functionality
        likeBtn.addEventListener('click', () => {
            isLiked = !isLiked;
            const icon = likeBtn.querySelector('i');
            if (isLiked) {
                icon.className = 'fas fa-heart';
                likeBtn.classList.add('liked');
            } else {
                icon.className = 'far fa-heart';
                likeBtn.classList.remove('liked');
            }
        });

        // Shuffle functionality
        shuffleBtn.addEventListener('click', () => {
            isShuffled = !isShuffled;
            shuffleBtn.style.color = isShuffled ? '#1db954' : '#b3b3b3';
        });

        // Control buttons
        prevBtn.addEventListener('click', () => {
            audio.currentTime = Math.max(0, audio.currentTime - 10);
        });

        nextBtn.addEventListener('click', () => {
            audio.currentTime = Math.min(audio.duration, audio.currentTime + 10);
        });

        castBtn.addEventListener('click', () => {
            // Cast functionality would go here
            console.log('Cast button clicked');
        });

        // Auto-play when page loads (if coming from mini player)
        if (storedSongState) {
            audio.addEventListener('canplay', () => {
                if (JSON.parse(storedSongState).isPlaying) {
                    audio.play();
                }
            });
        }

        // Download functionality
        function downloadSong() {
            const songId = '<?php echo $song_id; ?>';
            const songTitle = '<?php echo addslashes($song['title']); ?>';
            const songArtist = '<?php echo addslashes($song['artist']); ?>';
            
            // Create download link
            const audioFile = '<?php echo !empty($song['audio_file']) ? addslashes($song['audio_file']) : 'demo-audio.mp3'; ?>';
            const fileName = `${songArtist} - ${songTitle}.mp3`;
            
            // Create temporary download link
            const link = document.createElement('a');
            link.href = audioFile;
            link.download = fileName;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Update download count
            fetch('api/update-download-count.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    song_id: songId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the download count display
                    const downloadStat = document.querySelector('.stat-item:nth-child(2) span');
                    if (downloadStat) {
                        const currentCount = parseInt(downloadStat.textContent.replace(/[^\d]/g, ''));
                        downloadStat.textContent = `${(currentCount + 1).toLocaleString()} downloads`;
                    }
                    
                    // Show download success feedback
                    const downloadBtn = document.getElementById('downloadBtn');
                    const originalColor = downloadBtn.style.color;
                    downloadBtn.style.color = '#1db954';
                    setTimeout(() => {
                        downloadBtn.style.color = originalColor;
                    }, 1000);
                }
            })
            .catch(error => {
                console.error('Error updating download count:', error);
            });
        }
    </script>
</body>
</html>