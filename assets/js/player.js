// assets/js/player.js
// Audio player functionality for Music Streaming Platform

class AudioPlayer {
    constructor() {
        this.audio = document.getElementById('audio');
        this.player = document.getElementById('audio-player');
        this.playBtn = document.getElementById('play-btn');
        this.prevBtn = document.getElementById('prev-btn');
        this.nextBtn = document.getElementById('next-btn');
        this.volumeBtn = document.getElementById('volume-btn');
        
        this.progress = document.getElementById('progress');
        this.progressBar = document.querySelector('.progress-bar');
        this.currentTimeEl = document.getElementById('current-time');
        this.totalTimeEl = document.getElementById('total-time');
        
        this.volumeLevel = document.getElementById('volume-level');
        this.volumeBar = document.querySelector('.volume-bar');
        
        this.playerAlbumArt = document.getElementById('player-album-art');
        this.playerTitle = document.getElementById('player-title');
        this.playerArtist = document.getElementById('player-artist');
        
        this.currentSong = null;
        this.currentPlaylist = [];
        this.currentIndex = 0;
        this.isPlaying = false;
        this.volume = 0.7;
        
        this.init();
    }
    
    init() {
        // Set initial volume
        this.audio.volume = this.volume;
        this.updateVolumeDisplay();
        
        // Event listeners
        this.playBtn.addEventListener('click', () => this.togglePlay());
        this.prevBtn.addEventListener('click', () => this.previousSong());
        this.nextBtn.addEventListener('click', () => this.nextSong());
        this.volumeBtn.addEventListener('click', () => this.toggleMute());
        
        // Progress bar
        this.progressBar.addEventListener('click', (e) => this.setProgress(e));
        this.volumeBar.addEventListener('click', (e) => this.setVolume(e));
        
        // Audio events
        this.audio.addEventListener('timeupdate', () => this.updateProgress());
        this.audio.addEventListener('ended', () => this.nextSong());
        this.audio.addEventListener('loadedmetadata', () => this.updateDuration());
        this.audio.addEventListener('canplay', () => this.showPlayer());
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
        
        // Play buttons throughout the site
        document.addEventListener('click', (e) => {
            if (e.target.closest('.btn-play')) {
                const songId = e.target.closest('.btn-play').dataset.songId;
                this.playSong(songId);
            }
        });
    }
    
    async playSong(songId, playlist = null) {
        try {
            // Show loading state
            this.showPlayer();
            this.playerTitle.textContent = 'Loading...';
            this.playerArtist.textContent = 'Please wait';
            
            // Fetch song data
            const response = await fetch(`api/song.php?id=${songId}`);
            const songData = await response.json();
            
            if (!songData.success) {
                throw new Error(songData.error || 'Failed to load song');
            }
            
            this.currentSong = songData.song;
            this.currentPlaylist = playlist || [songData.song];
            this.currentIndex = this.currentPlaylist.findIndex(song => song.id == songId);
            
            // Update player UI
            this.updatePlayerInfo();
            
            // Set audio source
            this.audio.src = songData.song.file_path;
            this.audio.load();
            
            // Play the song
            await this.audio.play();
            this.isPlaying = true;
            this.updatePlayButton();
            
            // Record play history
            this.recordPlayHistory();
            
        } catch (error) {
            console.error('Error playing song:', error);
            this.showError('Failed to play song: ' + error.message);
        }
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
        } catch (error) {
            console.error('Error playing audio:', error);
        }
    }
    
    pause() {
        this.audio.pause();
        this.isPlaying = false;
        this.updatePlayButton();
    }
    
    previousSong() {
        if (this.currentIndex > 0) {
            this.currentIndex--;
            this.playSong(this.currentPlaylist[this.currentIndex].id, this.currentPlaylist);
        }
    }
    
    nextSong() {
        if (this.currentIndex < this.currentPlaylist.length - 1) {
            this.currentIndex++;
            this.playSong(this.currentPlaylist[this.currentIndex].id, this.currentPlaylist);
        }
    }
    
    setProgress(e) {
        const width = this.progressBar.clientWidth;
        const clickX = e.offsetX;
        const duration = this.audio.duration;
        
        this.audio.currentTime = (clickX / width) * duration;
    }
    
    setVolume(e) {
        const width = this.volumeBar.clientWidth;
        const clickX = e.offsetX;
        
        this.volume = clickX / width;
        this.audio.volume = this.volume;
        this.updateVolumeDisplay();
    }
    
    toggleMute() {
        if (this.audio.volume > 0) {
            this.audio.volume = 0;
            this.volumeBtn.innerHTML = '<i class="fas fa-volume-mute"></i>';
        } else {
            this.audio.volume = this.volume;
            this.volumeBtn.innerHTML = '<i class="fas fa-volume-up"></i>';
        }
        this.updateVolumeDisplay();
    }
    
    updateProgress() {
        const { duration, currentTime } = this.audio;
        const progressPercent = (currentTime / duration) * 100;
        
        this.progress.style.width = `${progressPercent}%`;
        this.currentTimeEl.textContent = this.formatTime(currentTime);
    }
    
    updateDuration() {
        this.totalTimeEl.textContent = this.formatTime(this.audio.duration);
    }
    
    updatePlayButton() {
        if (this.isPlaying) {
            this.playBtn.innerHTML = '<i class="fas fa-pause"></i>';
        } else {
            this.playBtn.innerHTML = '<i class="fas fa-play"></i>';
        }
    }
    
    updateVolumeDisplay() {
        const volumePercent = this.audio.volume * 100;
        this.volumeLevel.style.width = `${volumePercent}%`;
        
        if (this.audio.volume === 0) {
            this.volumeBtn.innerHTML = '<i class="fas fa-volume-mute"></i>';
        } else if (this.audio.volume < 0.5) {
            this.volumeBtn.innerHTML = '<i class="fas fa-volume-down"></i>';
        } else {
            this.volumeBtn.innerHTML = '<i class="fas fa-volume-up"></i>';
        }
    }
    
    updatePlayerInfo() {
        if (this.currentSong) {
            this.playerTitle.textContent = this.currentSong.title;
            this.playerArtist.textContent = this.currentSong.artist_name;
            this.playerAlbumArt.src = this.currentSong.cover_art || 'assets/images/default-album.jpg';
        }
    }
    
    showPlayer() {
        this.player.classList.add('active');
    }
    
    hidePlayer() {
        this.player.classList.remove('active');
    }
    
    formatTime(time) {
        if (isNaN(time)) return '0:00';
        
        const minutes = Math.floor(time / 60);
        const seconds = Math.floor(time % 60);
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }
    
    handleKeyboard(e) {
        // Only handle keyboard shortcuts when not typing in input fields
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }
        
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
            case 'ArrowUp':
                e.preventDefault();
                this.volume = Math.min(1, this.volume + 0.1);
                this.audio.volume = this.volume;
                this.updateVolumeDisplay();
                break;
            case 'ArrowDown':
                e.preventDefault();
                this.volume = Math.max(0, this.volume - 0.1);
                this.audio.volume = this.volume;
                this.updateVolumeDisplay();
                break;
        }
    }
    
    async recordPlayHistory() {
        if (!this.currentSong) return;
        
        try {
            const response = await fetch('api/play-history.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    song_id: this.currentSong.id,
                    duration_played: 0,
                    completed: false
                })
            });
            
            if (!response.ok) {
                console.error('Failed to record play history');
            }
        } catch (error) {
            console.error('Error recording play history:', error);
        }
    }
    
    showError(message) {
        // Create a temporary error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger position-fixed';
        errorDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        errorDiv.textContent = message;
        
        document.body.appendChild(errorDiv);
        
        // Remove after 5 seconds
        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
    }
}

// Initialize player when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.audioPlayer = new AudioPlayer();
});

// Export for use in other scripts
window.AudioPlayer = AudioPlayer;
