// luo-player.js - Modern Music Player
class LuoPlayer {
    constructor() {
        this.audio = null;
        this.currentSong = null;
        this.queue = [];
        this.currentIndex = 0;
        this.isPlaying = false;
        this.volume = 1;
        this.isMuted = false;
        this.repeatMode = 'none'; // 'none', 'all', 'one'
        this.shuffleMode = false;
        this.init();
    }

    init() {
        this.createPlayerHTML();
        this.setupAudio();
        this.bindEvents();
        this.loadFromStorage();
    }

    createPlayerHTML() {
        const playerHTML = `
            <div id="luoPlayer" class="luo-player">
                <div class="luo-player-content">
                    <!-- Song Info -->
                    <div class="luo-song-info">
                        <div class="luo-song-cover">
                            <img src="assets/images/default-avatar.svg" alt="Cover" id="luo-cover-img">
                            <div class="luo-play-overlay">
                                <i class="fas fa-play"></i>
                            </div>
                        </div>
                        <div class="luo-song-details">
                            <div class="luo-song-title" id="luo-song-title">No song selected</div>
                            <div class="luo-song-artist" id="luo-song-artist">Select a song to play</div>
                            <div class="luo-song-stats">
                                <span class="luo-stat">
                                    <i class="fas fa-play"></i>
                                    <span id="luo-plays">0</span> plays
                                </span>
                                <span class="luo-stat">
                                    <i class="fas fa-download"></i>
                                    <span id="luo-downloads">0</span> downloads
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Player Controls -->
                    <div class="luo-player-controls">
                        <button class="luo-control-btn" id="luo-shuffle-btn" title="Shuffle">
                            <i class="fas fa-random"></i>
                        </button>
                        <button class="luo-control-btn" id="luo-prev-btn" title="Previous">
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button class="luo-control-btn luo-play-pause-btn" id="luo-play-pause-btn">
                            <i class="fas fa-play"></i>
                        </button>
                        <button class="luo-control-btn" id="luo-next-btn" title="Next">
                            <i class="fas fa-step-forward"></i>
                        </button>
                        <button class="luo-control-btn" id="luo-repeat-btn" title="Repeat">
                            <i class="fas fa-redo"></i>
                        </button>
                    </div>

                    <!-- Progress Bar -->
                    <div class="luo-player-progress">
                        <div class="luo-progress-bar" id="luo-progress-bar">
                            <div class="luo-progress-fill" id="luo-progress-fill"></div>
                        </div>
                        <div class="luo-time-info">
                            <span id="luo-current-time">0:00</span>
                            <span id="luo-total-time">0:00</span>
                        </div>
                    </div>

                    <!-- Additional Controls -->
                    <div class="luo-player-actions">
                        <button class="luo-action-btn" id="luo-volume-btn" title="Volume">
                            <i class="fas fa-volume-up"></i>
                        </button>
                        <div class="luo-volume-control" id="luo-volume-control">
                            <input type="range" id="luo-volume-slider" min="0" max="100" value="100">
                        </div>
                        <button class="luo-action-btn" id="luo-download-btn" title="Download">
                            <i class="fas fa-download"></i>
                        </button>
                        <button class="luo-action-btn" id="luo-favorite-btn" title="Add to Favorites">
                            <i class="far fa-heart"></i>
                        </button>
                        <button class="luo-action-btn" id="luo-queue-btn" title="Queue">
                            <i class="fas fa-list"></i>
                            <span class="luo-queue-count" id="luo-queue-count">0</span>
                        </button>
                    </div>
                </div>
            </div>
        `;

        const styleHTML = `
            <style>
                .luo-player {
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 0;
                    box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
                    z-index: 1000;
                    display: none;
                    transition: transform 0.3s ease;
                }

                .luo-player.active {
                    display: block;
                }

                .luo-player-content {
                    max-width: 1400px;
                    margin: 0 auto;
                    padding: 15px 20px;
                    display: grid;
                    grid-template-columns: 300px 1fr 250px;
                    gap: 20px;
                    align-items: center;
                }

                .luo-song-info {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    min-width: 0;
                }

                .luo-song-cover {
                    position: relative;
                    width: 56px;
                    height: 56px;
                    border-radius: 8px;
                    overflow: hidden;
                    background: rgba(255,255,255,0.2);
                    flex-shrink: 0;
                }

                .luo-song-cover img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }

                .luo-play-overlay {
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
                    transition: opacity 0.2s;
                }

                .luo-song-cover:hover .luo-play-overlay {
                    opacity: 1;
                }

                .luo-song-details {
                    min-width: 0;
                    flex: 1;
                }

                .luo-song-title {
                    font-weight: 600;
                    font-size: 14px;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    margin-bottom: 2px;
                }

                .luo-song-artist {
                    font-size: 12px;
                    opacity: 0.9;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    margin-bottom: 4px;
                }

                .luo-song-stats {
                    display: flex;
                    gap: 12px;
                    font-size: 11px;
                    opacity: 0.8;
                }

                .luo-stat {
                    display: flex;
                    align-items: center;
                    gap: 4px;
                }

                .luo-player-controls {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 12px;
                }

                .luo-control-btn {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 16px;
                    width: 36px;
                    height: 36px;
                    border-radius: 50%;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: all 0.2s;
                }

                .luo-control-btn:hover {
                    background: rgba(255,255,255,0.2);
                    transform: scale(1.1);
                }

                .luo-play-pause-btn {
                    background: rgba(255,255,255,0.3);
                    font-size: 18px;
                    width: 44px;
                    height: 44px;
                }

                .luo-play-pause-btn:hover {
                    background: rgba(255,255,255,0.4);
                }

                .luo-control-btn.active {
                    background: rgba(255,255,255,0.4);
                }

                .luo-player-progress {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }

                .luo-progress-bar {
                    width: 100%;
                    height: 4px;
                    background: rgba(255,255,255,0.3);
                    border-radius: 2px;
                    cursor: pointer;
                    position: relative;
                }

                .luo-progress-fill {
                    height: 100%;
                    background: white;
                    border-radius: 2px;
                    width: 0%;
                    transition: width 0.1s linear;
                }

                .luo-time-info {
                    display: flex;
                    justify-content: space-between;
                    font-size: 11px;
                    opacity: 0.9;
                }

                .luo-player-actions {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }

                .luo-action-btn {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 16px;
                    width: 36px;
                    height: 36px;
                    border-radius: 50%;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: all 0.2s;
                    position: relative;
                }

                .luo-action-btn:hover {
                    background: rgba(255,255,255,0.2);
                }

                .luo-action-btn.active {
                    color: #ffc107;
                }

                .luo-queue-count {
                    position: absolute;
                    top: -2px;
                    right: -2px;
                    background: #ffc107;
                    color: #333;
                    font-size: 10px;
                    font-weight: bold;
                    width: 18px;
                    height: 18px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .luo-volume-control {
                    display: flex;
                    align-items: center;
                }

                .luo-volume-slider {
                    width: 80px;
                    height: 4px;
                    cursor: pointer;
                }

                #luo-volume-slider {
                    width: 80px;
                }

                /* Responsive Design */
                @media (max-width: 968px) {
                    .luo-player-content {
                        grid-template-columns: 1fr;
                        gap: 8px;
                    }

                    .luo-player-progress {
                        order: -1;
                    }

                    .luo-song-info {
                        order: 1;
                    }

                    .luo-player-controls {
                        order: 2;
                    }

                    .luo-player-actions {
                        order: 3;
                        justify-content: center;
                    }
                }
            </style>
        `;

        document.head.insertAdjacentHTML('beforeend', styleHTML);
        document.body.insertAdjacentHTML('beforeend', playerHTML);
        this.player = document.getElementById('luoPlayer');
    }

    setupAudio() {
        this.audio = new Audio();
        this.audio.addEventListener('timeupdate', () => this.updateProgress());
        this.audio.addEventListener('loadedmetadata', () => this.onMetadataLoaded());
        this.audio.addEventListener('ended', () => this.onSongEnded());
        this.audio.addEventListener('error', (e) => this.onAudioError(e));
        this.audio.volume = this.volume;
    }

    bindEvents() {
        // Play/Pause
        document.getElementById('luo-play-pause-btn').addEventListener('click', () => this.togglePlay());

        // Previous/Next
        document.getElementById('luo-prev-btn').addEventListener('click', () => this.previousSong());
        document.getElementById('luo-next-btn').addEventListener('click', () => this.nextSong());

        // Progress bar
        document.getElementById('luo-progress-bar').addEventListener('click', (e) => this.seekTo(e));

        // Volume
        document.getElementById('luo-volume-btn').addEventListener('click', () => this.toggleMute());
        document.getElementById('luo-volume-slider').addEventListener('input', (e) => this.setVolume(e.target.value));

        // Repeat
        document.getElementById('luo-repeat-btn').addEventListener('click', () => this.toggleRepeat());

        // Shuffle
        document.getElementById('luo-shuffle-btn').addEventListener('click', () => this.toggleShuffle());

        // Download
        document.getElementById('luo-download-btn').addEventListener('click', () => this.downloadSong());

        // Favorite
        document.getElementById('luo-favorite-btn').addEventListener('click', () => this.toggleFavorite());

        // Queue
        document.getElementById('luo-queue-btn').addEventListener('click', () => this.showQueue());

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
    }

    async loadSong(songData, autoPlay = false) {
        try {
            this.currentSong = songData;
            this.updateSongInfo();

            // Load audio - always use stream API URL if song ID is available
            let audioSrc;
            if (songData.id) {
                audioSrc = 'api/stream.php?id=' + songData.id;
            } else if (songData.audio_file && songData.audio_file.includes('stream.php')) {
                audioSrc = songData.audio_file;
            } else {
                audioSrc = songData.audio_file || songData.file_path;
            }
            this.audio.src = audioSrc;

            // Show player
            this.show();

            // Auto-play if requested
            if (autoPlay) {
                await this.audio.play();
                this.isPlaying = true;
                this.updatePlayButton();
                this.recordPlay(songData.id);
            }

            // Save to storage
            this.saveToStorage();

        } catch (error) {
            console.error('Error loading song:', error);
        }
    }

    updateSongInfo() {
        if (!this.currentSong) return;

        document.getElementById('luo-song-title').textContent = this.currentSong.title || 'Unknown';
        document.getElementById('luo-song-artist').textContent = this.currentSong.artist || 'Unknown Artist';
        
        // Update cover art
        const coverImg = document.getElementById('luo-cover-img');
        if (this.currentSong.cover_art) {
            coverImg.src = this.currentSong.cover_art;
        } else {
            coverImg.src = 'assets/images/default-avatar.svg';
        }

        // Update stats
        document.getElementById('luo-plays').textContent = this.currentSong.plays || 0;
        document.getElementById('luo-downloads').textContent = this.currentSong.downloads || 0;
    }

    togglePlay() {
        if (this.isPlaying) {
            this.pause();
        } else {
            this.play();
        }
    }

    async play() {
        try {
            await this.audio.play();
            this.isPlaying = true;
            this.updatePlayButton();
            
            if (this.currentSong && this.currentSong.id) {
                this.recordPlay(this.currentSong.id);
            }
        } catch (error) {
            console.error('Error playing audio:', error);
        }
    }

    pause() {
        this.audio.pause();
        this.isPlaying = false;
        this.updatePlayButton();
    }

    updatePlayButton() {
        const btn = document.getElementById('luo-play-pause-btn');
        btn.innerHTML = this.isPlaying ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play"></i>';
    }

    updateProgress() {
        if (!this.audio.duration) return;
        
        const progress = (this.audio.currentTime / this.audio.duration) * 100;
        document.getElementById('luo-progress-fill').style.width = `${progress}%`;
        
        document.getElementById('luo-current-time').textContent = this.formatTime(this.audio.currentTime);
    }

    onMetadataLoaded() {
        document.getElementById('luo-total-time').textContent = this.formatTime(this.audio.duration);
    }

    seekTo(e) {
        const progressBar = e.currentTarget;
        const rect = progressBar.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const width = rect.width;
        const percentage = clickX / width;
        
        if (this.audio.duration) {
            this.audio.currentTime = percentage * this.audio.duration;
        }
    }

    previousSong() {
        if (this.currentIndex > 0) {
            this.currentIndex--;
            this.loadSong(this.queue[this.currentIndex], true);
        } else if (this.repeatMode === 'all') {
            this.currentIndex = this.queue.length - 1;
            this.loadSong(this.queue[this.currentIndex], true);
        }
    }

    nextSong() {
        if (this.shuffleMode && this.queue.length > 1) {
            const nextIndex = Math.floor(Math.random() * this.queue.length);
            this.currentIndex = nextIndex;
            this.loadSong(this.queue[this.currentIndex], true);
        } else if (this.currentIndex < this.queue.length - 1) {
            this.currentIndex++;
            this.loadSong(this.queue[this.currentIndex], true);
        } else if (this.repeatMode === 'all') {
            this.currentIndex = 0;
            this.loadSong(this.queue[this.currentIndex], true);
        }
    }

    toggleRepeat() {
        const modes = ['none', 'all', 'one'];
        const currentIndex = modes.indexOf(this.repeatMode);
        this.repeatMode = modes[(currentIndex + 1) % modes.length];
        
        const btn = document.getElementById('luo-repeat-btn');
        btn.classList.toggle('active', this.repeatMode !== 'none');
    }

    toggleShuffle() {
        this.shuffleMode = !this.shuffleMode;
        const btn = document.getElementById('luo-shuffle-btn');
        btn.classList.toggle('active', this.shuffleMode);
    }

    setVolume(value) {
        this.volume = value / 100;
        this.audio.volume = this.volume;
        this.updateVolumeButton();
    }

    toggleMute() {
        this.isMuted = !this.isMuted;
        this.audio.muted = this.isMuted;
        this.updateVolumeButton();
    }

    updateVolumeButton() {
        const btn = document.getElementById('luo-volume-btn');
        if (this.isMuted || this.audio.volume === 0) {
            btn.innerHTML = '<i class="fas fa-volume-mute"></i>';
        } else if (this.audio.volume < 0.5) {
            btn.innerHTML = '<i class="fas fa-volume-down"></i>';
        } else {
            btn.innerHTML = '<i class="fas fa-volume-up"></i>';
        }
    }

    downloadSong() {
        if (!this.currentSong) return;
        
        const downloadUrl = `api/download.php?id=${this.currentSong.id}`;
        window.open(downloadUrl, '_blank');
    }

    toggleFavorite() {
        if (!this.currentSong) return;
        
        const btn = document.getElementById('luo-favorite-btn');
        btn.classList.toggle('active');
        // Add to favorites logic here
    }

    showQueue() {
        console.log('Queue:', this.queue);
        // Show queue modal
        alert(`Queue: ${this.queue.length} songs`);
    }

    onSongEnded() {
        this.isPlaying = false;
        this.updatePlayButton();
        
        if (this.repeatMode === 'one') {
            this.audio.currentTime = 0;
            this.play();
        } else {
            this.nextSong();
        }
    }

    onAudioError(e) {
        console.error('Audio error:', e);
        console.error('Audio error details:', {
            error: this.audio.error,
            code: this.audio.error?.code,
            message: this.audio.error?.message,
            src: this.audio.src
        });
        
        const errorMsg = this.getAudioErrorMessage(this.audio.error);
        this.showError(errorMsg);
    }
    
    getAudioErrorMessage(error) {
        if (!error) {
            return 'Failed to load audio file. Please check if the file exists.';
        }
        
        switch (error.code) {
            case MediaError.MEDIA_ERR_ABORTED:
                return 'Audio loading was aborted.';
            case MediaError.MEDIA_ERR_NETWORK:
                return 'Network error while loading audio. Please check your connection.';
            case MediaError.MEDIA_ERR_DECODE:
                return 'Audio file could not be decoded. The file may be corrupted or in an unsupported format.';
            case MediaError.MEDIA_ERR_SRC_NOT_SUPPORTED:
                return 'Audio format not supported. Please try a different file.';
            default:
                return 'Failed to load audio file. Error code: ' + error.code;
        }
    }

    showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'luo-error';
        errorDiv.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Audio Error</strong>
                    <div style="font-size: 14px; margin-top: 4px;">${message}</div>
                </div>
            </div>
        `;
        errorDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #dc3545; color: white; padding: 15px 20px; border-radius: 8px; z-index: 10000; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
        document.body.appendChild(errorDiv);
        setTimeout(() => {
            errorDiv.style.transition = 'opacity 0.3s';
            errorDiv.style.opacity = '0';
            setTimeout(() => errorDiv.remove(), 300);
        }, 8000);
    }

    show() {
        this.player.classList.add('active');
    }

    hide() {
        this.player.classList.remove('active');
    }

    formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    handleKeyboard(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        switch (e.code) {
            case 'Space':
                e.preventDefault();
                this.togglePlay();
                break;
            case 'ArrowLeft':
                e.preventDefault();
                this.audio.currentTime = Math.max(0, this.audio.currentTime - 10);
                break;
            case 'ArrowRight':
                e.preventDefault();
                this.audio.currentTime = Math.min(this.audio.duration, this.audio.currentTime + 10);
                break;
        }
    }

    recordPlay(songId) {
        fetch('api/update-play-count.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ song_id: songId })
        }).catch(err => console.log('Play count update failed:', err));
    }

    saveToStorage() {
        if (this.currentSong) {
            sessionStorage.setItem('luoPlayer_currentSong', JSON.stringify(this.currentSong));
            sessionStorage.setItem('luoPlayer_queue', JSON.stringify(this.queue));
            sessionStorage.setItem('luoPlayer_currentIndex', this.currentIndex);
        }
    }

    loadFromStorage() {
        const currentSong = sessionStorage.getItem('luoPlayer_currentSong');
        if (currentSong) {
            this.currentSong = JSON.parse(currentSong);
            this.updateSongInfo();
            this.show();
        }

        const queue = sessionStorage.getItem('luoPlayer_queue');
        if (queue) {
            this.queue = JSON.parse(queue);
        }

        const index = sessionStorage.getItem('luoPlayer_currentIndex');
        if (index !== null) {
            this.currentIndex = parseInt(index);
        }
    }

    // Global function to play song from anywhere
    playSong(songId, addToQueue = true) {
        // Fetch song data
        fetch(`api/song-data.php?id=${songId}`)
            .then(res => res.json())
            .then(data => {
                if (addToQueue) {
                    this.queue.push(data);
                    this.currentIndex = this.queue.length - 1;
                    document.getElementById('luo-queue-count').textContent = this.queue.length;
                }
                this.loadSong(data, true);
            })
            .catch(err => console.error('Error fetching song:', err));
    }
}

// Initialize player
let luoPlayer;

document.addEventListener('DOMContentLoaded', () => {
    luoPlayer = new LuoPlayer();
    window.luoPlayer = luoPlayer;
});

// Global function for playing songs
function playSong(songId) {
    if (window.luoPlayer) {
        window.luoPlayer.playSong(songId);
    }
}

