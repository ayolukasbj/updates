<?php
// install/install-database.php
// Database installation functions - Creates all tables programmatically

/**
 * Create all database tables programmatically
 */
function createAllTables($conn) {
    $errors = [];
    $success = [];
    
    try {
        // Enable foreign key checks
        $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        // 1. Users table (with all columns)
        $conn->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                first_name VARCHAR(50),
                last_name VARCHAR(50),
                full_name VARCHAR(255),
                avatar VARCHAR(255),
                subscription_type ENUM('free', 'premium', 'artist') DEFAULT 'free',
                subscription_expires TIMESTAMP NULL,
                role ENUM('user', 'artist', 'admin', 'super_admin') DEFAULT 'user',
                status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
                is_active BOOLEAN DEFAULT TRUE,
                is_banned BOOLEAN DEFAULT FALSE,
                banned_reason TEXT,
                email_verified BOOLEAN DEFAULT FALSE,
                verification_token VARCHAR(255),
                reset_token VARCHAR(255),
                reset_token_expires TIMESTAMP NULL,
                bio TEXT,
                facebook VARCHAR(255),
                twitter VARCHAR(255),
                instagram VARCHAR(255),
                youtube VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL,
                INDEX idx_email (email),
                INDEX idx_username (username),
                INDEX idx_subscription (subscription_type),
                INDEX idx_role (role),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Users table created";
        
        // 2. Artists table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS artists (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                bio TEXT,
                avatar VARCHAR(255),
                cover_image VARCHAR(255),
                verified BOOLEAN DEFAULT FALSE,
                user_id INT,
                social_links TEXT,
                total_plays BIGINT DEFAULT 0,
                total_downloads BIGINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_name (name),
                INDEX idx_verified (verified)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Artists table created";
        
        // 3. Genres table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS genres (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) UNIQUE NOT NULL,
                description TEXT,
                color VARCHAR(7) DEFAULT '#000000',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Genres table created";
        
        // 4. Albums table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS albums (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(100) NOT NULL,
                artist_id INT NOT NULL,
                release_date DATE,
                cover_art VARCHAR(255),
                description TEXT,
                genre_id INT,
                total_tracks INT DEFAULT 0,
                total_duration INT DEFAULT 0,
                total_plays BIGINT DEFAULT 0,
                total_downloads BIGINT DEFAULT 0,
                is_featured BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE,
                FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE SET NULL,
                INDEX idx_artist (artist_id),
                INDEX idx_genre (genre_id),
                INDEX idx_featured (is_featured)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Albums table created";
        
        // 5. Songs table (with all columns including status, uploaded_by, artist, etc.)
        $conn->exec("
            CREATE TABLE IF NOT EXISTS songs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(100) NOT NULL,
                artist VARCHAR(255),
                artist_id INT,
                album_id INT,
                album_title VARCHAR(255),
                file_path VARCHAR(500) NOT NULL,
                cover_art VARCHAR(500),
                file_size BIGINT,
                duration INT,
                bitrate INT DEFAULT 320,
                quality ENUM('low', 'medium', 'high', 'lossless') DEFAULT 'high',
                genre VARCHAR(100),
                genre_id INT,
                lyrics TEXT,
                track_number INT,
                plays INT DEFAULT 0,
                downloads INT DEFAULT 0,
                is_featured BOOLEAN DEFAULT FALSE,
                is_explicit BOOLEAN DEFAULT FALSE,
                status VARCHAR(50) DEFAULT 'active',
                upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                uploaded_by INT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE SET NULL,
                FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE SET NULL,
                FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE SET NULL,
                FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_artist (artist_id),
                INDEX idx_album (album_id),
                INDEX idx_genre (genre_id),
                INDEX idx_featured (is_featured),
                INDEX idx_plays (plays),
                INDEX idx_title (title),
                INDEX idx_status (status),
                INDEX idx_uploaded_by (uploaded_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Songs table created";
        
        // 6. Song collaborators table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS song_collaborators (
                id INT AUTO_INCREMENT PRIMARY KEY,
                song_id INT NOT NULL,
                user_id INT NOT NULL,
                role VARCHAR(50) DEFAULT 'collaborator',
                added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_song_user (song_id, user_id),
                INDEX idx_song (song_id),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Song collaborators table created";
        
        // 7. Playlists table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS playlists (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                user_id INT NOT NULL,
                is_public BOOLEAN DEFAULT TRUE,
                cover_image VARCHAR(255),
                total_tracks INT DEFAULT 0,
                total_duration INT DEFAULT 0,
                plays BIGINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user (user_id),
                INDEX idx_public (is_public)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Playlists table created";
        
        // 8. Playlist songs junction table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS playlist_songs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                playlist_id INT NOT NULL,
                song_id INT NOT NULL,
                position INT NOT NULL,
                added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
                FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
                UNIQUE KEY unique_playlist_song (playlist_id, song_id),
                INDEX idx_playlist (playlist_id),
                INDEX idx_song (song_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Playlist songs table created";
        
        // 9. User favorites table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS user_favorites (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                song_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_song (user_id, song_id),
                INDEX idx_user (user_id),
                INDEX idx_song (song_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "User favorites table created";
        
        // 10. Downloads tracking table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS downloads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                song_id INT NOT NULL,
                download_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                quality ENUM('low', 'medium', 'high', 'lossless'),
                ip_address VARCHAR(45),
                user_agent TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
                INDEX idx_user (user_id),
                INDEX idx_song (song_id),
                INDEX idx_date (download_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Downloads table created";
        
        // 11. Play history table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS play_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                song_id INT NOT NULL,
                played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                duration_played INT DEFAULT 0,
                completed BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
                INDEX idx_user (user_id),
                INDEX idx_song (song_id),
                INDEX idx_played_at (played_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Play history table created";
        
        // 12. Subscriptions table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                plan_type ENUM('free', 'premium', 'artist') NOT NULL,
                start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                end_date TIMESTAMP NULL,
                status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
                auto_renew BOOLEAN DEFAULT TRUE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user (user_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Subscriptions table created";
        
        // 13. Payments table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                subscription_id INT,
                amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'USD',
                payment_method ENUM('paypal', 'stripe', 'credit_card') NOT NULL,
                transaction_id VARCHAR(255),
                status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
                payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,
                INDEX idx_user (user_id),
                INDEX idx_status (status),
                INDEX idx_transaction (transaction_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Payments table created";
        
        // 14. Reviews and ratings table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS reviews (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                song_id INT NOT NULL,
                rating INT CHECK (rating >= 1 AND rating <= 5),
                review_text TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_song_review (user_id, song_id),
                INDEX idx_user (user_id),
                INDEX idx_song (song_id),
                INDEX idx_rating (rating)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Reviews table created";
        
        // 15. Follow system table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS follows (
                id INT AUTO_INCREMENT PRIMARY KEY,
                follower_id INT NOT NULL,
                following_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_follow (follower_id, following_id),
                INDEX idx_follower (follower_id),
                INDEX idx_following (following_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Follows table created";
        
        // 16. Notifications table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type ENUM('new_follower', 'new_song', 'playlist_update', 'payment', 'system') NOT NULL,
                title VARCHAR(100) NOT NULL,
                message TEXT NOT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                related_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user (user_id),
                INDEX idx_read (is_read),
                INDEX idx_type (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Notifications table created";
        
        // 17. Settings table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(255) UNIQUE NOT NULL,
                setting_value TEXT,
                description TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Settings table created";
        
        // 18. News table (with is_published field)
        $conn->exec("
            CREATE TABLE IF NOT EXISTS news (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                category VARCHAR(50),
                content TEXT NOT NULL,
                excerpt TEXT,
                image VARCHAR(255),
                author_id INT,
                submitted_by INT,
                views BIGINT DEFAULT 0,
                status ENUM('draft', 'published', 'archived') DEFAULT 'published',
                is_published BOOLEAN DEFAULT TRUE,
                featured BOOLEAN DEFAULT FALSE,
                rejection_reason TEXT,
                published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_category (category),
                INDEX idx_status (status),
                INDEX idx_is_published (is_published),
                INDEX idx_featured (featured),
                INDEX idx_published_at (published_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "News table created";
        
        // 19. News comments table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS news_comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                news_id INT NOT NULL,
                user_id INT NOT NULL,
                comment TEXT NOT NULL,
                is_approved BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_news (news_id),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "News comments table created";
        
        // 20. News views tracking table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS news_views (
                id INT AUTO_INCREMENT PRIMARY KEY,
                news_id INT NOT NULL,
                user_id INT,
                ip_address VARCHAR(45),
                viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_news (news_id),
                INDEX idx_viewed_at (viewed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "News views table created";
        
        // 21. Admin logs table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS admin_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                action VARCHAR(100) NOT NULL,
                target_type VARCHAR(50),
                target_id INT,
                description TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_admin (admin_id),
                INDEX idx_action (action),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Admin logs table created";
        
        // 22. Song comments table (if needed)
        $conn->exec("
            CREATE TABLE IF NOT EXISTS song_comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                song_id INT NOT NULL,
                user_id INT NOT NULL,
                comment TEXT NOT NULL,
                is_approved BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_song (song_id),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Song comments table created";
        
        // 23. Song ratings table (if needed)
        $conn->exec("
            CREATE TABLE IF NOT EXISTS song_ratings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                song_id INT NOT NULL,
                user_id INT NOT NULL,
                rating INT CHECK (rating >= 1 AND rating <= 5),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_song_rating (user_id, song_id),
                INDEX idx_song (song_id),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $success[] = "Song ratings table created";
        
        // Insert default genres
        $genres = [
            ['Pop', 'Popular music with catchy melodies', '#FF6B6B'],
            ['Rock', 'Rock and roll music', '#4ECDC4'],
            ['Hip Hop', 'Hip hop and rap music', '#45B7D1'],
            ['Electronic', 'Electronic and dance music', '#96CEB4'],
            ['Classical', 'Classical and orchestral music', '#FFEAA7'],
            ['Jazz', 'Jazz and blues music', '#DDA0DD'],
            ['Country', 'Country and folk music', '#98D8C8'],
            ['R&B', 'Rhythm and blues music', '#F7DC6F'],
            ['Reggae', 'Reggae and Caribbean music', '#BB8FCE'],
            ['Alternative', 'Alternative and indie music', '#85C1E9']
        ];
        
        $genreStmt = $conn->prepare("INSERT IGNORE INTO genres (name, description, color) VALUES (?, ?, ?)");
        foreach ($genres as $genre) {
            $genreStmt->execute($genre);
        }
        $success[] = "Default genres inserted";
        
        return ['success' => true, 'messages' => $success, 'errors' => []];
        
    } catch (Exception $e) {
        return ['success' => false, 'messages' => $success, 'errors' => ['Error creating tables: ' . $e->getMessage()]];
    }
}

function runInstallation($db_config, $site_config, $license_data) {
    global $install_license_key, $install_domain;
    $errors = [];
    $success = [];
    
    try {
        // Connect to database
        $conn = new PDO(
            "mysql:host={$db_config['db_host']};dbname={$db_config['db_name']};charset=utf8mb4",
            $db_config['db_user'],
            $db_config['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Create all tables programmatically (includes settings table)
        $table_result = createAllTables($conn);
        if ($table_result['success']) {
            $success = array_merge($success, $table_result['messages']);
        } else {
            $errors = array_merge($errors, $table_result['errors']);
            throw new Exception(implode(', ', $errors));
        }
        
        // Insert site settings
        $settings = [
            'site_name' => $site_config['site_name'],
            'site_slogan' => $site_config['site_slogan'] ?? '',
            'site_description' => $site_config['site_description'] ?? '',
            'license_key' => $install_license_key ?? '',
            'license_domain' => $install_domain ?? '',
            'license_type' => $license_data['license']['type'] ?? 'lifetime',
            'script_version' => '1.0.0', // Initial version
            'installation_date' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $conn->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }
        
        $success[] = "Site settings saved";
        
        // Create admin user (users table already created by createAllTables)
        $hashed_password = password_hash($site_config['admin_password'], PASSWORD_DEFAULT);
        
        // Check if admin user already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $checkStmt->execute([$site_config['admin_email'], $site_config['admin_username']]);
        
        if ($checkStmt->rowCount() == 0) {
            // Insert admin user with all available columns
            $adminStmt = $conn->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, full_name, role, status, is_active, email_verified)
                VALUES (?, ?, ?, ?, ?, ?, 'super_admin', 'active', TRUE, TRUE)
            ");
            $adminStmt->execute([
                $site_config['admin_username'],
                $site_config['admin_email'],
                $hashed_password,
                $site_config['admin_username'],
                '',
                $site_config['admin_username']
            ]);
            $success[] = "Admin user created";
        } else {
            // Update existing user to super_admin
            $updateStmt = $conn->prepare("
                UPDATE users 
                SET password = ?, role = 'super_admin', status = 'active', is_active = TRUE, email_verified = TRUE
                WHERE email = ? OR username = ?
            ");
            $updateStmt->execute([
                $hashed_password,
                $site_config['admin_email'],
                $site_config['admin_username']
            ]);
            $success[] = "Admin user updated";
        }
        
        return ['success' => true, 'errors' => [], 'success_messages' => $success];
        
    } catch (Exception $e) {
        $errors[] = 'Installation error: ' . $e->getMessage();
        return ['success' => false, 'errors' => $errors];
    }
}

function createConfigFile($db_config, $site_config, $license_data, $license_server_url, $license_key) {
    $config_content = "<?php
// config/config.php
// Auto-generated during installation - DO NOT EDIT MANUALLY

// Installation Status
define('SITE_INSTALLED', true);

// Site Configuration
define('SITE_NAME', " . var_export($site_config['site_name'], true) . ");
define('SITE_SLOGAN', " . var_export($site_config['site_slogan'] ?? '', true) . ");
define('SITE_DESCRIPTION', " . var_export($site_config['site_description'] ?? '', true) . ");

// Auto-detect SITE_URL and BASE_PATH
\$protocol = (!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
\$host = \$_SERVER['HTTP_HOST'] ?? 'localhost';

// Auto-detect base path from script location
\$script_path = dirname(\$_SERVER['SCRIPT_NAME'] ?? '');
\$base_path = \$script_path === '/' ? '/' : rtrim(\$script_path, '/') . '/';

// If installed in root, base_path should be '/'
if (strpos(\$script_path, '/admin') !== false) {
    // We're in admin folder, go up one level
    \$base_path = dirname(\$script_path) === '/' ? '/' : dirname(\$script_path) . '/';
} elseif (\$script_path === '/' || empty(\$script_path)) {
    \$base_path = '/';
}

define('SITE_URL', \$protocol . \$host . \$base_path);
define('BASE_PATH', \$base_path);

// Database Configuration
define('DB_HOST', " . var_export($db_config['db_host'], true) . ");
define('DB_NAME', " . var_export($db_config['db_name'], true) . ");
define('DB_USER', " . var_export($db_config['db_user'], true) . ");
define('DB_PASS', " . var_export($db_config['db_pass'], true) . ");

// File Upload Configuration
define('UPLOAD_PATH', 'uploads/');
define('MUSIC_PATH', 'uploads/music/');
define('IMAGES_PATH', 'uploads/images/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024);
define('ALLOWED_AUDIO_FORMATS', ['mp3', 'wav', 'flac', 'aac', 'm4a']);
define('ALLOWED_IMAGE_FORMATS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Environment
define('ENVIRONMENT', 'production');

// License Configuration
define('LICENSE_SERVER_URL', " . var_export($license_server_url, true) . ");
define('LICENSE_KEY', " . var_export($license_key, true) . ");

// Security
define('ENCRYPTION_KEY', bin2hex(random_bytes(32)));
define('SESSION_LIFETIME', 3600);
define('PASSWORD_MIN_LENGTH', 8);

// Subscription Configuration
define('FREE_DAILY_DOWNLOADS', 10);
define('PREMIUM_DAILY_DOWNLOADS', 100);
define('ARTIST_DAILY_DOWNLOADS', 500);

// Streaming Configuration
define('DEFAULT_STREAMING_QUALITY', 'high');
define('STREAMING_BUFFER_SIZE', 8192);

// Pagination
define('SONGS_PER_PAGE', 20);
define('PLAYLISTS_PER_PAGE', 12);
define('USERS_PER_PAGE', 15);

// Cache Configuration
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 3600);

// Email Configuration (will be set from admin settings)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('FROM_EMAIL', 'noreply@example.com');
define('FROM_NAME', " . var_export($site_config['site_name'], true) . ");

// Payment Configuration
define('PAYPAL_CLIENT_ID', '');
define('PAYPAL_CLIENT_SECRET', '');
define('STRIPE_PUBLISHABLE_KEY', '');
define('STRIPE_SECRET_KEY', '');

// Social Media Configuration
define('FACEBOOK_APP_ID', '');
define('GOOGLE_CLIENT_ID', '');

// Helper Functions
// Start session if not already started (only if not in CLI mode)
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    @session_start(); // Suppress warnings if headers already sent
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset(\$_SESSION['user_id']) && !empty(\$_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function get_user_id() {
    return \$_SESSION['user_id'] ?? null;
}

/**
 * Redirect to a URL
 */
function redirect(\$url) {
    if (!headers_sent()) {
        header('Location: ' . \$url);
        exit;
    } else {
        echo '<script>window.location.href = "' . htmlspecialchars(\$url) . '";</script>';
        exit;
    }
}

/**
 * Get base URL path (removes hardcoded /music/)
 */
function base_url(\$path = '') {
    \$base = defined('BASE_PATH') ? BASE_PATH : '/';
    // Remove leading slash from path if base already has trailing slash
    if (\$base !== '/' && \$path && \$path[0] === '/') {
        \$path = substr(\$path, 1);
    }
    return \$base . \$path;
}
";

    file_put_contents(__DIR__ . '/../config/config.php', $config_content);
}

