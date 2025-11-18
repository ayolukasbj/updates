// mini-player.js - Dynamic mini player system
class MiniPlayer {
    constructor() {
        this.currentSong = null;
        this.isPlaying = false;
        this.isMuted = false;
        this.audio = null;
        this.audioDuration = 0;
        this.currentTime = 0;
        this.progressInterval = null;
        this.init();
    }
    
    init() {
        // Create mini player HTML
        this.createMiniPlayer();
        
        // Create audio element
        this.audio = new Audio();
        this.audio.addEventListener('ended', () => this.onSongEnded());
        this.audio.addEventListener('timeupdate', () => this.updateProgress());
        this.audio.addEventListener('loadedmetadata', () => {
            this.onMetadataLoaded();
            if ('mediaSession' in navigator) {
                this.updateMediaSessionState();
            }
        });
        this.audio.addEventListener('play', () => {
            if ('mediaSession' in navigator) {
                this.updateMediaSessionState();
            }
        });
        this.audio.addEventListener('pause', () => {
            if ('mediaSession' in navigator) {
                this.updateMediaSessionState();
            }
        });
        
        // Bind events
        this.bindEvents();
    }
    
    createMiniPlayer() {
        const miniPlayerHTML = `
            <div id="miniPlayer" class="mini-player" style="display: none;">
                <div class="mini-player-content">
                    <div class="mini-song-info">
                        <div class="mini-song-cover" title="Click to open full player">
                            <i class="fas fa-music"></i>
                        </div>
                        <div class="mini-song-details">
                            <div class="mini-song-title">No song selected</div>
                            <div class="mini-song-artist">Artist</div>
                        </div>
                    </div>
                    <div class="mini-player-controls">
                        <button id="miniPrevBtn" class="mini-btn">
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button id="miniPlayPauseBtn" class="mini-btn mini-play-btn">
                            <i class="fas fa-play"></i>
                        </button>
                        <button id="miniNextBtn" class="mini-btn">
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>
                    <div class="mini-player-progress">
                        <div class="mini-progress-bar">
                            <div class="mini-progress-fill"></div>
                        </div>
                        <div class="mini-time">
                            <span class="mini-current-time">0:00</span>
                            <span class="mini-total-time">0:00</span>
                        </div>
                    </div>
                    <div class="mini-player-actions">
                        <button id="miniVolumeBtn" class="mini-btn">
                            <i class="fas fa-volume-up"></i>
                        </button>
                        <button id="miniExpandBtn" class="mini-btn">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Add CSS
        const css = `
            <style>
                .mini-player {
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 10px 20px;
                    box-shadow: 0 -2px 10px rgba(0,0,0,0.3);
                    z-index: 1000;
                    backdrop-filter: blur(10px);
                }
                .mini-player-content {
                    display: flex;
                    align-items: center;
                    max-width: 1200px;
                    margin: 0 auto;
                }
                .mini-song-info {
                    display: flex;
                    align-items: center;
                    flex: 1;
                    min-width: 0;
                }
                .mini-song-cover {
                    width: 40px;
                    height: 40px;
                    background: rgba(255,255,255,0.2);
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-right: 12px;
                    flex-shrink: 0;
                    cursor: pointer;
                    transition: transform 0.2s, background 0.2s;
                }
                .mini-song-cover:hover {
                    transform: scale(1.05);
                    background: rgba(255,255,255,0.3);
                }
                .mini-song-details {
                    min-width: 0;
                }
                .mini-song-title {
                    font-weight: 600;
                    font-size: 14px;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                .mini-song-artist {
                    font-size: 12px;
                    opacity: 0.8;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                .mini-player-controls {
                    display: flex;
                    align-items: center;
                    margin: 0 20px;
                }
                .mini-btn {
                    background: none;
                    border: none;
                    color: white;
                    padding: 8px;
                    border-radius: 50%;
                    cursor: pointer;
                    transition: background 0.2s;
                }
                .mini-btn:hover {
                    background: rgba(255,255,255,0.2);
                }
                .mini-play-btn {
                    background: rgba(255,255,255,0.3);
                    margin: 0 8px;
                }
                .mini-player-progress {
                    flex: 1;
                    margin: 0 20px;
                }
                .mini-progress-bar {
                    width: 100%;
                    height: 4px;
                    background: rgba(255,255,255,0.3);
                    border-radius: 2px;
                    cursor: pointer;
                    margin-bottom: 4px;
                }
                .mini-progress-fill {
                    height: 100%;
                    background: white;
                    border-radius: 2px;
                    width: 0%;
                    transition: width 0.1s ease;
                }
                .mini-time {
                    display: flex;
                    justify-content: space-between;
                    font-size: 11px;
                    opacity: 0.8;
                }
                .mini-player-actions {
                    display: flex;
                    align-items: center;
                }
                @media (max-width: 768px) {
                    .mini-player-progress {
                        display: none;
                    }
                    .mini-player-controls {
                        margin: 0 10px;
                    }
                }
            </style>
        `;
        
        document.head.insertAdjacentHTML('beforeend', css);
        document.body.insertAdjacentHTML('beforeend', miniPlayerHTML);
    }
    
    bindEvents() {
        // Play/Pause button
        document.getElementById('miniPlayPauseBtn').addEventListener('click', () => {
            this.togglePlayPause();
        });
        
        // Progress bar click
        document.querySelector('.mini-progress-bar').addEventListener('click', (e) => {
            this.seekTo(e);
        });
        
        // Expand button (go to full player)
        document.getElementById('miniExpandBtn').addEventListener('click', () => {
            if (this.currentSong) {
                // Store current song state in sessionStorage for the full player
                sessionStorage.setItem('currentSong', JSON.stringify({
                    id: this.currentSong.id,
                    title: this.currentSong.title,
                    artist: this.currentSong.artist,
                    cover_art: this.currentSong.cover_art,
                    audio_file: this.currentSong.audio_file,
                    duration: this.currentSong.duration,
                    currentTime: this.audio.currentTime || 0,
                    isPlaying: this.isPlaying
                }));
                
                // Navigate to full player
                // Generate slug for song URL
                const titleSlug = this.currentSong.title.toLowerCase().replace(/[^a-z0-9\s]+/gi, '').replace(/\s+/g, '-').trim();
                const artistSlug = (this.currentSong.artist || 'unknown-artist').toLowerCase().replace(/[^a-z0-9\s]+/gi, '').replace(/\s+/g, '-').trim();
                const songSlug = `${titleSlug}-by-${artistSlug}`;
                window.location.href = `/song/${encodeURIComponent(songSlug)}`;
            }
        });
        
        // Volume button
        document.getElementById('miniVolumeBtn').addEventListener('click', () => {
            this.toggleMute();
        });
        
        // Make cover art clickable
        document.addEventListener('click', (e) => {
            if (e.target.closest('.mini-song-cover') && this.currentSong) {
                // Store current song state in sessionStorage for the full player
                sessionStorage.setItem('currentSong', JSON.stringify({
                    id: this.currentSong.id,
                    title: this.currentSong.title,
                    artist: this.currentSong.artist,
                    cover_art: this.currentSong.cover_art,
                    audio_file: this.currentSong.audio_file,
                    duration: this.currentSong.duration,
                    currentTime: this.audio.currentTime || 0,
                    isPlaying: this.isPlaying
                }));
                
                // Navigate to full player
                // Generate slug for song URL
                const titleSlug = this.currentSong.title.toLowerCase().replace(/[^a-z0-9\s]+/gi, '').replace(/\s+/g, '-').trim();
                const artistSlug = (this.currentSong.artist || 'unknown-artist').toLowerCase().replace(/[^a-z0-9\s]+/gi, '').replace(/\s+/g, '-').trim();
                const songSlug = `${titleSlug}-by-${artistSlug}`;
                window.location.href = `/song/${encodeURIComponent(songSlug)}`;
            }
        });
    }
    
    async playSong(songId) {
        try {
            // Try to fetch real song data first
            let songData;
            try {
                const response = await fetch(`api/song-data.php?id=${songId}`);
                const fetchedData = await response.json();
                
                if (fetchedData.error) {
                    songData = this.getFallbackSongData(songId);
                } else {
                    songData = fetchedData;
                }
            } catch (error) {
                songData = this.getFallbackSongData(songId);
            }
            
            this.currentSong = songData;
            
            // Update UI
            document.querySelector('.mini-song-title').textContent = songData.title;
            document.querySelector('.mini-song-artist').textContent = songData.artist;
            
            // Update cover art
            const coverElement = document.querySelector('.mini-song-cover');
            if (songData.cover_art && songData.cover_art !== '') {
                coverElement.innerHTML = `<img src="${songData.cover_art}" alt="Cover" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">`;
            } else {
                coverElement.innerHTML = '<i class="fas fa-music"></i>';
            }
            
            // Load and play real audio file
            await this.loadAudioFile(songData);
            
            // Initialize MediaSession API for enhanced notifications
            if ('mediaSession' in navigator) {
                // Convert relative path to absolute URL if needed
                let artworkUrl = songData.cover_art || 'assets/images/default-avatar.svg';
                if (artworkUrl && !artworkUrl.startsWith('http')) {
                    const baseUrl = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '');
                    artworkUrl = baseUrl + '/' + artworkUrl.replace(/^\//, '');
                }
                
                // Set MediaSession metadata
                navigator.mediaSession.metadata = new MediaMetadata({
                    title: songData.title || 'Unknown',
                    artist: songData.artist || 'Unknown Artist',
                    artwork: [
                        { src: artworkUrl, sizes: '96x96', type: 'image/png' },
                        { src: artworkUrl, sizes: '128x128', type: 'image/png' },
                        { src: artworkUrl, sizes: '192x192', type: 'image/png' },
                        { src: artworkUrl, sizes: '256x256', type: 'image/png' },
                        { src: artworkUrl, sizes: '384x384', type: 'image/png' },
                        { src: artworkUrl, sizes: '512x512', type: 'image/png' }
                    ]
                });
                
                // Set action handlers
                navigator.mediaSession.setActionHandler('play', () => {
                    this.audio.play();
                    this.isPlaying = true;
                    this.updatePlayButton();
                });
                
                navigator.mediaSession.setActionHandler('pause', () => {
                    this.audio.pause();
                    this.isPlaying = false;
                    this.updatePlayButton();
                });
                
                navigator.mediaSession.setActionHandler('previoustrack', () => {
                    console.log('Previous track requested');
                    // Can implement previous song logic here
                });
                
                navigator.mediaSession.setActionHandler('nexttrack', () => {
                    console.log('Next track requested');
                    // Can implement next song logic here
                });
                
                // Update playback state
                this.updateMediaSessionState();
            }
            
            // Show mini player
            document.getElementById('miniPlayer').style.display = 'block';
            
            // Start playing
            this.audio.play().then(() => {
                this.isPlaying = true;
                this.updatePlayButton();
                if ('mediaSession' in navigator) {
                    this.updateMediaSessionState();
                }
            }).catch(error => {
                console.log('Audio play failed:', error);
                // Fallback to simulated audio
                this.simulateAudio();
                this.isPlaying = true;
                this.updatePlayButton();
            });
            
            // Update play count
            this.updatePlayCount(songData.id);
            
        } catch (error) {
            console.error('Error playing song:', error);
        }
    }
    
    async loadAudioFile(songData) {
        // Try to load the actual audio file
        const audioSrc = this.getAudioSource(songData);
        this.audio.src = audioSrc;
        
        console.log('Loading audio file:', audioSrc);
        
        // Load the audio
        return new Promise((resolve, reject) => {
            this.audio.addEventListener('loadeddata', () => {
                console.log('Audio loaded successfully');
                resolve();
            });
            
            this.audio.addEventListener('error', (e) => {
                console.error('Audio load failed:', e);
                console.error('Audio error details:', {
                    error: this.audio.error,
                    code: this.audio.error?.code,
                    message: this.audio.error?.message,
                    src: this.audio.src
                });
                
                // Show user-friendly error message
                const errorMsg = this.getAudioErrorMessage(this.audio.error);
                this.showError(errorMsg);
                
                reject(e);
            });
            
            // Set a timeout to avoid hanging
            setTimeout(() => {
                console.log('Audio load timeout, falling back to simulated audio');
                reject(new Error('Audio load timeout'));
            }, 3000);
        });
    }
    
    getAudioSource(songData) {
        // Always use stream API URL if song ID is available
        if (songData.id) {
            return 'api/stream.php?id=' + songData.id;
        }
        
        // Use the audio_file if it's already a stream URL
        if (songData.audio_file && songData.audio_file !== '') {
            // If it's already a stream URL, use it
            if (songData.audio_file.includes('stream.php')) {
                return songData.audio_file;
            }
            // Otherwise, construct stream URL from ID if available
            if (songData.id) {
                return 'api/stream.php?id=' + songData.id;
            }
            return songData.audio_file;
        }
        
        // Fallback to demo audio file
        return 'demo-audio.mp3';
    }
    
    getFallbackSongData(songId) {
        // Try to get song data from the page if available
        const songElements = document.querySelectorAll('[data-song-id="' + songId + '"]');
        
        if (songElements.length > 0) {
            const songElement = songElements[0];
            return {
                id: songId,
                title: songElement.dataset.songTitle || 'Unknown Song',
                artist: songElement.dataset.songArtist || 'Unknown Artist',
                cover_art: songElement.dataset.songCover || '',
                duration: songElement.dataset.songDuration || '3:45'
            };
        }
        
        // Final fallback
        return {
            id: songId,
            title: 'Song ' + songId.substring(0, 8),
            artist: 'Artist',
            cover_art: '',
            duration: '3:45'
        };
    }
    
    simulateAudio() {
        // Clear any existing interval
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
        }
        
        // Simulate audio duration
        this.audioDuration = 225; // 3:45 in seconds
        
        // Start progress simulation
        this.progressInterval = setInterval(() => {
            if (this.isPlaying) {
                this.currentTime += 1;
                const progress = (this.currentTime / this.audioDuration) * 100;
                document.querySelector('.mini-progress-fill').style.width = progress + '%';
                
                // Update time display
                const currentTime = this.formatTime(this.currentTime);
                const totalTime = this.formatTime(this.audioDuration);
                document.querySelector('.mini-current-time').textContent = currentTime;
                document.querySelector('.mini-total-time').textContent = totalTime;
                
                // End song when duration reached
                if (this.currentTime >= this.audioDuration) {
                    this.onSongEnded();
                }
            }
        }, 1000);
    }
    
    togglePlayPause() {
        if (this.isPlaying) {
            this.audio.pause();
            this.isPlaying = false;
        } else {
            this.audio.play().then(() => {
                this.isPlaying = true;
                if ('mediaSession' in navigator) {
                    this.updateMediaSessionState();
                }
            }).catch(error => {
                console.log('Audio play failed:', error);
                // Fallback to simulated audio
                this.simulateAudio();
                this.isPlaying = true;
            });
        }
        this.updatePlayButton();
    }
    
    updatePlayButton() {
        const btn = document.getElementById('miniPlayPauseBtn');
        if (this.isPlaying) {
            btn.innerHTML = '<i class="fas fa-pause"></i>';
        } else {
            btn.innerHTML = '<i class="fas fa-play"></i>';
        }
        
        // Update MediaSession state
        if ('mediaSession' in navigator) {
            this.updateMediaSessionState();
        }
    }
    
    updateMediaSessionState() {
        if ('mediaSession' in navigator && this.audio) {
            navigator.mediaSession.playbackState = this.isPlaying ? 'playing' : 'paused';
            
            if (this.audio.duration) {
                navigator.mediaSession.setPositionState({
                    duration: this.audio.duration,
                    playbackRate: this.audio.playbackRate || 1.0,
                    position: this.audio.currentTime || 0
                });
            }
        }
    }
    
    seekTo(e) {
        const progressBar = e.currentTarget;
        const rect = progressBar.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const width = rect.width;
        const percentage = clickX / width;
        
        if (this.audio.duration) {
            this.audio.currentTime = percentage * this.audio.duration;
        } else {
            // Fallback to simulated seeking
            this.currentTime = percentage * this.audioDuration;
            const progress = (this.currentTime / this.audioDuration) * 100;
            document.querySelector('.mini-progress-fill').style.width = progress + '%';
            
            const currentTime = this.formatTime(this.currentTime);
            document.querySelector('.mini-current-time').textContent = currentTime;
        }
    }
    
    updateProgress() {
        if (this.audio.duration) {
            const progress = (this.audio.currentTime / this.audio.duration) * 100;
            document.querySelector('.mini-progress-fill').style.width = progress + '%';
            
            // Update time display
            const currentTime = this.formatTime(this.audio.currentTime);
            const totalTime = this.formatTime(this.audio.duration);
            document.querySelector('.mini-current-time').textContent = currentTime;
            document.querySelector('.mini-total-time').textContent = totalTime;
            
            // Update MediaSession position state
            if ('mediaSession' in navigator && navigator.mediaSession.setPositionState) {
                navigator.mediaSession.setPositionState({
                    duration: this.audio.duration,
                    playbackRate: this.audio.playbackRate || 1.0,
                    position: this.audio.currentTime
                });
            }
        }
    }
    
    formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return minutes + ':' + (secs < 10 ? '0' : '') + secs;
    }
    
    onMetadataLoaded() {
        const totalTime = this.formatTime(this.audio.duration);
        document.querySelector('.mini-total-time').textContent = totalTime;
    }
    
    onSongEnded() {
        this.isPlaying = false;
        this.updatePlayButton();
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
        }
        // Auto-play next song or stop
        this.nextSong();
    }
    
    toggleMute() {
        this.audio.muted = !this.audio.muted;
        const btn = document.getElementById('miniVolumeBtn');
        if (this.audio.muted) {
            btn.innerHTML = '<i class="fas fa-volume-mute"></i>';
        } else {
            btn.innerHTML = '<i class="fas fa-volume-up"></i>';
        }
    }
    
    previousSong() {
        // For demo, just restart current song
        if (this.audio.duration) {
            this.audio.currentTime = Math.max(0, this.audio.currentTime - 10);
        } else {
            this.currentTime = Math.max(0, this.currentTime - 10);
            this.isPlaying = true;
            this.simulateAudio();
            this.updatePlayButton();
        }
    }
    
    nextSong() {
        // For demo, just restart current song
        if (this.audio.duration) {
            this.audio.currentTime = 0;
            this.audio.play();
            this.isPlaying = true;
            this.updatePlayButton();
        } else {
            this.currentTime = 0;
            this.isPlaying = true;
            this.simulateAudio();
            this.updatePlayButton();
        }
    }
    
    updatePlayCount(songId) {
        // Update play count in storage
        fetch('api/update-play-count.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                song_id: songId
            })
        }).catch(error => {
            console.log('Play count update failed:', error);
        });
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
        // Create error notification
        const errorDiv = document.createElement('div');
        errorDiv.className = 'audio-error-notification';
        errorDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #dc3545; color: white; padding: 15px 20px; border-radius: 8px; z-index: 10000; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
        errorDiv.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Audio Error</strong>
                    <div style="font-size: 14px; margin-top: 4px;">${message}</div>
                </div>
            </div>
        `;
        
        document.body.appendChild(errorDiv);
        
        // Remove after 8 seconds
        setTimeout(() => {
            errorDiv.style.transition = 'opacity 0.3s';
            errorDiv.style.opacity = '0';
            setTimeout(() => errorDiv.remove(), 300);
        }, 8000);
    }
}

// Initialize mini player
const miniPlayer = new MiniPlayer();

// Global function to play song
function playSong(songId) {
    // Get song data (in real app, this would be from API)
    const songData = {
        id: songId,
        title: 'Demo Song',
        artist: 'Demo Artist',
        cover_art: ''
    };
    
    miniPlayer.playSong(songData);
}

