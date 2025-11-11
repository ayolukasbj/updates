// assets/js/ajax-navigation.js - Complete AJAX navigation system
class AjaxNavigation {
    constructor() {
        this.currentPage = 'home';
        this.isLoading = false;
        this.cache = new Map();
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadInitialPage();
    }

    loadInitialPage() {
        // Load the initial page based on current URL
        const path = window.location.pathname.replace(/^\//, '');
        const page = path || 'index';
        console.log('Loading initial page:', page);
        this.loadPage(page, false);
    }

    bindEvents() {
        // Intercept all link clicks
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (link && link.href && !link.href.startsWith('javascript:')) {
                e.preventDefault();
                this.handleNavigation(link);
            }
        });

        // Handle browser back/forward buttons
        window.addEventListener('popstate', (e) => {
            if (e.state) {
                this.loadPage(e.state.page, false);
            }
        });

        // Prevent form submissions from causing page reloads
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('ajax-form')) {
                e.preventDefault();
                this.handleFormSubmission(e.target);
            }
        });
    }

    handleNavigation(link) {
        const url = new URL(link.href);
        const page = url.pathname.replace(/^\//, '') || 'index.php';
        
        // Don't navigate if it's the same page
        if (page === this.currentPage) return;
        
        this.loadPage(page, true);
    }

    async loadPage(page, addToHistory = true) {
        if (this.isLoading) {
            console.log('Already loading, skipping:', page);
            return;
        }
        
        console.log('Loading page:', page);
        this.isLoading = true;
        this.showLoading();

        // Set a timeout to prevent infinite loading
        const timeout = setTimeout(() => {
            if (this.isLoading) {
                console.error('Page load timeout for:', page);
                this.hideLoading();
                this.isLoading = false;
                this.showError('Page load timeout. Please try again.');
            }
        }, 10000); // 10 second timeout

        try {
            // Check cache first
            if (this.cache.has(page)) {
                console.log('Using cached content for:', page);
                clearTimeout(timeout);
                this.renderPage(this.cache.get(page));
                this.currentPage = page;
                if (addToHistory) {
                    history.pushState({ page }, '', `/${page}`);
                }
                this.hideLoading();
                this.isLoading = false;
                return;
            }

            // Load page content - handle different page formats
            let url = page;
            if (!page.includes('.php')) {
                url = `ajax/${page}.php`;
            } else if (!page.startsWith('ajax/')) {
                url = `ajax/${page}`;
            }
            
            // Special handling for index page - try minimal version if main fails
            if (page === 'index' && url === 'ajax/index.php') {
                console.log('Trying minimal index first...');
                try {
                    const minimalResponse = await fetch('ajax/index-minimal.php');
                    if (minimalResponse.ok) {
                        const minimalHtml = await minimalResponse.text();
                        console.log('Minimal index loaded successfully');
                        this.cache.set(page, minimalHtml);
                        this.renderPage(minimalHtml);
                        this.currentPage = page;
                        if (addToHistory) {
                            history.pushState({ page }, '', `/${page}`);
                        }
                        clearTimeout(timeout);
                        this.hideLoading();
                        this.isLoading = false;
                        return;
                    }
                } catch (e) {
                    console.log('Minimal index failed, trying main index...');
                }
            }
            
            console.log('Fetching URL:', url);
            
            // Add timeout to fetch request
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 8000); // 8 second timeout
            
            const response = await fetch(url, {
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const html = await response.text();
            console.log('Response length:', html.length);
            
            if (html.length < 100) {
                console.warn('Response seems too short:', html);
            }
            
            // Cache the content
            this.cache.set(page, html);
            
            // Render the page
            this.renderPage(html);
            this.currentPage = page;
            
            // Update browser history
            if (addToHistory) {
                history.pushState({ page }, '', `/${page}`);
            }
            
            clearTimeout(timeout);
            
        } catch (error) {
            console.error('Navigation error:', error);
            console.error('URL attempted:', url);
            clearTimeout(timeout);
            
            // Show specific error message
            let errorMessage = 'Failed to load page. Please try again.';
            if (error.name === 'AbortError') {
                errorMessage = 'Request timed out. Please try again.';
            } else if (error.message.includes('404')) {
                errorMessage = 'Page not found.';
            } else if (error.message.includes('500')) {
                errorMessage = 'Server error. Please try again later.';
            }
            
            this.showError(errorMessage);
            
            // Fallback: try to load a simple error page
            const mainContent = document.getElementById('main-content');
            if (mainContent) {
                mainContent.innerHTML = `
                    <div style="text-align: center; padding: 50px;">
                        <h2>Error Loading Page</h2>
                        <p>${errorMessage}</p>
                        <button onclick="location.reload()" class="btn btn-primary">Reload Page</button>
                        <button onclick="window.ajaxNav.loadPage('simple')" class="btn btn-secondary">Test Simple Page</button>
                    </div>
                `;
            }
        } finally {
            this.hideLoading();
            this.isLoading = false;
        }
    }

    renderPage(html) {
        console.log('Rendering page content...');
        
        // Parse the HTML and extract main content
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // Update main content area
        const mainContent = document.getElementById('main-content');
        if (mainContent) {
            console.log('Updating main content area');
            mainContent.innerHTML = doc.body.innerHTML;
        } else {
            console.log('No main-content div found, replacing body content');
            // If no main-content div, replace body content
            document.body.innerHTML = html;
        }
        
        // Re-initialize any page-specific functionality
        this.initializePageFeatures();
        
        // Update page title
        const title = doc.querySelector('title');
        if (title) {
            document.title = title.textContent;
        }
        
        console.log('Page rendered successfully');
    }

    initializePageFeatures() {
        // Re-bind events for dynamically loaded content
        this.bindEvents();
        
        // Initialize mini player if it exists
        if (window.MiniPlayer) {
            window.miniPlayer = new window.MiniPlayer();
        }
        
        // Initialize any other page-specific features
        this.initializeSongCards();
        this.initializeForms();
    }

    initializeSongCards() {
        // Add play functionality to song cards
        document.querySelectorAll('.song-card, .song-item').forEach(card => {
            const playBtn = card.querySelector('.play-btn, .song-play-btn');
            if (playBtn && !playBtn.hasAttribute('data-initialized')) {
                playBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.playSong(card);
                });
                playBtn.setAttribute('data-initialized', 'true');
            }
        });
    }

    initializeForms() {
        // Handle AJAX forms
        document.querySelectorAll('form').forEach(form => {
            if (!form.classList.contains('ajax-form')) {
                form.classList.add('ajax-form');
            }
        });
    }

    async playSong(card) {
        const songId = card.dataset.songId || card.querySelector('[data-song-id]')?.dataset.songId;
        if (!songId) return;

        try {
            const response = await fetch(`api/song-data.php?id=${songId}`);
            const songData = await response.json();
            
            if (window.miniPlayer) {
                window.miniPlayer.playSong(songData);
            }
        } catch (error) {
            console.error('Error playing song:', error);
        }
    }

    async handleFormSubmission(form) {
        const formData = new FormData(form);
        const action = form.action || form.dataset.action;
        
        try {
            const response = await fetch(action, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (result.redirect) {
                    this.loadPage(result.redirect);
                } else if (result.message) {
                    this.showMessage(result.message, 'success');
                }
            } else {
                this.showMessage(result.error || 'An error occurred', 'error');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            this.showMessage('Failed to submit form. Please try again.', 'error');
        }
    }

    showLoading() {
        let loader = document.getElementById('ajax-loader');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'ajax-loader';
            loader.innerHTML = `
                <div class="ajax-loader-overlay">
                    <div class="ajax-loader-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                </div>
            `;
            loader.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
            `;
            document.body.appendChild(loader);
        }
        loader.style.display = 'flex';
    }

    hideLoading() {
        const loader = document.getElementById('ajax-loader');
        if (loader) {
            loader.style.display = 'none';
        }
    }

    showMessage(message, type = 'info') {
        const messageEl = document.createElement('div');
        messageEl.className = `ajax-message ajax-message-${type}`;
        messageEl.textContent = message;
        messageEl.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            max-width: 300px;
        `;
        
        if (type === 'success') {
            messageEl.style.background = '#1db954';
        } else if (type === 'error') {
            messageEl.style.background = '#e22134';
        } else {
            messageEl.style.background = '#1a1a1a';
        }
        
        document.body.appendChild(messageEl);
        
        setTimeout(() => {
            messageEl.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                document.body.removeChild(messageEl);
            }, 300);
        }, 3000);
    }

    showError(message) {
        this.showMessage(message, 'error');
    }

    // Clear cache when needed
    clearCache() {
        this.cache.clear();
    }

    // Preload pages for better performance
    preloadPages(pages) {
        pages.forEach(page => {
            if (!this.cache.has(page)) {
                fetch(`ajax/${page}`)
                    .then(response => response.text())
                    .then(html => this.cache.set(page, html))
                    .catch(error => console.error(`Failed to preload ${page}:`, error));
            }
        });
    }
}

// Initialize AJAX navigation when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.ajaxNav = new AjaxNavigation();
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .ajax-loader-spinner {
        font-size: 2rem;
        color: white;
    }
    
    .ajax-loader-overlay {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }
`;
document.head.appendChild(style);
