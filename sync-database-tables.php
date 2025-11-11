<?php
/**
 * Sync Database Tables
 * This script checks for missing tables and creates them
 * Access: https://tesotalents.com/sync-database-tables.php
 * 
 * WARNING: Delete this file after use for security!
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Sync Database Tables</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .success { background: #d4edda; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
        h2 { margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; background: white; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #667eea; color: white; }
    </style>
</head>
<body>
    <h1>🔄 Sync Database Tables</h1>
    <p><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    
    <?php
    $errors = [];
    $warnings = [];
    $success = [];
    $created_tables = [];
    $existing_tables = [];
    $missing_tables = [];
    
    // Load config
    try {
        if (!file_exists('config/config.php')) {
            throw new Exception('Config file not found');
        }
        require_once 'config/config.php';
        
        if (!file_exists('config/database.php')) {
            throw new Exception('Database config file not found');
        }
        require_once 'config/database.php';
        
        // Connect to database
        $db = new Database();
        $conn = $db->getConnection();
        
        if (!$conn) {
            throw new Exception('Database connection failed');
        }
        
        echo "<div class='success'>✅ Database connection successful</div>";
        
        // Get existing tables
        $stmt = $conn->query("SHOW TABLES");
        $existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h2>Current Database Status</h2>";
        echo "<div class='info'>Found " . count($existing_tables) . " existing tables</div>";
        
        // List of all 35 tables that should exist
        $required_tables = [
            // Core tables (23 from install-database.php)
            'users',
            'artists',
            'genres',
            'albums',
            'songs',
            'playlists',
            'playlist_songs',
            'user_favorites',
            'downloads',
            'play_history',
            'subscriptions',
            'payments',
            'reviews',
            'follows',
            'notifications',
            'settings',
            'news',
            'news_comments',
            'news_views',
            'admin_logs',
            'song_comments',
            'song_ratings',
            
            // Additional tables (12 more)
            'email_settings',
            'email_templates',
            'email_queue',
            'news_categories',
            'song_collaborators',
            'favorites', // Alternative name for user_favorites
            'user_playlists', // Alternative name
            'artist_social_links',
            'song_lyrics',
            'biography',
            'albums_songs', // Junction table
            'license_activations', // License system
        ];
        
        // Check which tables are missing
        $missing_tables = array_diff($required_tables, $existing_tables);
        
        echo "<h2>Table Analysis</h2>";
        echo "<table>";
        echo "<tr><th>Table Name</th><th>Status</th></tr>";
        foreach ($required_tables as $table) {
            $status = in_array($table, $existing_tables) ? 
                "<span style='color: green;'>✅ Exists</span>" : 
                "<span style='color: red;'>❌ Missing</span>";
            echo "<tr><td>$table</td><td>$status</td></tr>";
        }
        echo "</table>";
        
        if (count($missing_tables) > 0) {
            echo "<h2>Creating Missing Tables</h2>";
            echo "<div class='warning'>Found " . count($missing_tables) . " missing tables. Creating them now...</div>";
            
            // Include the installation function
            require_once 'install/install-database.php';
            
            // Create missing tables one by one
            foreach ($missing_tables as $table) {
                try {
                    switch ($table) {
                        case 'email_settings':
                            $conn->exec("
                                CREATE TABLE IF NOT EXISTS email_settings (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    setting_key VARCHAR(255) UNIQUE NOT NULL,
                                    setting_value TEXT,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                            ");
                            $created_tables[] = $table;
                            echo "<div class='success'>✅ Created table: $table</div>";
                            break;
                            
                        case 'email_templates':
                            $conn->exec("
                                CREATE TABLE IF NOT EXISTS email_templates (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    slug VARCHAR(255) UNIQUE NOT NULL,
                                    subject VARCHAR(255) NOT NULL,
                                    body TEXT NOT NULL,
                                    is_active BOOLEAN DEFAULT TRUE,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                            ");
                            $created_tables[] = $table;
                            echo "<div class='success'>✅ Created table: $table</div>";
                            break;
                            
                        case 'email_queue':
                            $conn->exec("
                                CREATE TABLE IF NOT EXISTS email_queue (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    to_email VARCHAR(255) NOT NULL,
                                    subject VARCHAR(255) NOT NULL,
                                    body TEXT NOT NULL,
                                    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
                                    error_message TEXT,
                                    attempts INT DEFAULT 0,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    sent_at TIMESTAMP NULL,
                                    INDEX idx_status (status),
                                    INDEX idx_created (created_at)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                            ");
                            $created_tables[] = $table;
                            echo "<div class='success'>✅ Created table: $table</div>";
                            break;
                            
                        case 'news_categories':
                            $conn->exec("
                                CREATE TABLE IF NOT EXISTS news_categories (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    name VARCHAR(100) UNIQUE NOT NULL,
                                    slug VARCHAR(100) UNIQUE NOT NULL,
                                    description TEXT,
                                    is_active BOOLEAN DEFAULT TRUE,
                                    sort_order INT DEFAULT 0,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    INDEX idx_active (is_active),
                                    INDEX idx_sort (sort_order)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                            ");
                            // Insert default categories
                            $default_categories = [
                                'Entertainment', 'National News', 'Exclusive', 'Hot', 
                                'Politics', 'Shocking', 'Celebrity Gossip', 'Just in', 
                                'Lifestyle and Events'
                            ];
                            $catStmt = $conn->prepare("INSERT IGNORE INTO news_categories (name, slug, is_active) VALUES (?, ?, 1)");
                            foreach ($default_categories as $cat) {
                                $slug = strtolower(str_replace(' ', '-', $cat));
                                $catStmt->execute([$cat, $slug]);
                            }
                            $created_tables[] = $table;
                            echo "<div class='success'>✅ Created table: $table (with default categories)</div>";
                            break;
                            
                        case 'song_collaborators':
                            $conn->exec("
                                CREATE TABLE IF NOT EXISTS song_collaborators (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    song_id INT NOT NULL,
                                    artist_id INT,
                                    artist_name VARCHAR(255),
                                    role VARCHAR(50) DEFAULT 'featured',
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
                                    INDEX idx_song (song_id),
                                    INDEX idx_artist (artist_id)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                            ");
                            $created_tables[] = $table;
                            echo "<div class='success'>✅ Created table: $table</div>";
                            break;
                            
                        case 'favorites':
                            // Check if user_favorites exists, if so, create favorites as alias/view
                            if (in_array('user_favorites', $existing_tables)) {
                                echo "<div class='info'>ℹ️ Table 'favorites' not needed (user_favorites exists)</div>";
                            } else {
                                $conn->exec("
                                    CREATE TABLE IF NOT EXISTS favorites (
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
                                $created_tables[] = $table;
                                echo "<div class='success'>✅ Created table: $table</div>";
                            }
                            break;
                            
                        case 'user_playlists':
                            // Check if playlists exists
                            if (in_array('playlists', $existing_tables)) {
                                echo "<div class='info'>ℹ️ Table 'user_playlists' not needed (playlists exists)</div>";
                            } else {
                                $conn->exec("
                                    CREATE TABLE IF NOT EXISTS user_playlists (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        user_id INT NOT NULL,
                                        name VARCHAR(255) NOT NULL,
                                        description TEXT,
                                        is_public BOOLEAN DEFAULT FALSE,
                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                                        INDEX idx_user (user_id)
                                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                                ");
                                $created_tables[] = $table;
                                echo "<div class='success'>✅ Created table: $table</div>";
                            }
                            break;
                            
                        case 'artist_social_links':
                            // Check if artists table has social_links column
                            $checkStmt = $conn->query("SHOW COLUMNS FROM artists LIKE 'social_links'");
                            if ($checkStmt->rowCount() > 0) {
                                echo "<div class='info'>ℹ️ Table 'artist_social_links' not needed (artists.social_links exists)</div>";
                            } else {
                                $conn->exec("
                                    CREATE TABLE IF NOT EXISTS artist_social_links (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        artist_id INT NOT NULL,
                                        platform VARCHAR(50) NOT NULL,
                                        url VARCHAR(255) NOT NULL,
                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                        FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE,
                                        INDEX idx_artist (artist_id)
                                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                                ");
                                $created_tables[] = $table;
                                echo "<div class='success'>✅ Created table: $table</div>";
                            }
                            break;
                            
                        case 'song_lyrics':
                            // Check if songs table has lyrics column
                            $checkStmt = $conn->query("SHOW COLUMNS FROM songs LIKE 'lyrics'");
                            if ($checkStmt->rowCount() > 0) {
                                echo "<div class='info'>ℹ️ Table 'song_lyrics' not needed (songs.lyrics exists)</div>";
                            } else {
                                $conn->exec("
                                    CREATE TABLE IF NOT EXISTS song_lyrics (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        song_id INT NOT NULL UNIQUE,
                                        lyrics TEXT NOT NULL,
                                        language VARCHAR(10) DEFAULT 'en',
                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                        FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE
                                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                                ");
                                $created_tables[] = $table;
                                echo "<div class='success'>✅ Created table: $table</div>";
                            }
                            break;
                            
                        case 'biography':
                            $conn->exec("
                                CREATE TABLE IF NOT EXISTS biography (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    user_id INT NOT NULL UNIQUE,
                                    bio TEXT,
                                    early_life TEXT,
                                    career TEXT,
                                    achievements TEXT,
                                    personal_life TEXT,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                            ");
                            $created_tables[] = $table;
                            echo "<div class='success'>✅ Created table: $table</div>";
                            break;
                            
                        case 'albums_songs':
                            // Check if playlist_songs can be used or if we need this
                            if (in_array('playlist_songs', $existing_tables)) {
                                echo "<div class='info'>ℹ️ Table 'albums_songs' - albums table should have songs relationship</div>";
                            }
                            $conn->exec("
                                CREATE TABLE IF NOT EXISTS albums_songs (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    album_id INT NOT NULL,
                                    song_id INT NOT NULL,
                                    track_number INT,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
                                    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
                                    UNIQUE KEY unique_album_song (album_id, song_id),
                                    INDEX idx_album (album_id),
                                    INDEX idx_song (song_id)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                            ");
                            $created_tables[] = $table;
                            echo "<div class='success'>✅ Created table: $table</div>";
                            break;
                            
                        case 'license_activations':
                            $conn->exec("
                                CREATE TABLE IF NOT EXISTS license_activations (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    license_key VARCHAR(255) NOT NULL,
                                    domain VARCHAR(255) NOT NULL,
                                    ip_address VARCHAR(45),
                                    activated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    last_verified TIMESTAMP NULL,
                                    status ENUM('active', 'suspended', 'expired') DEFAULT 'active',
                                    INDEX idx_license (license_key),
                                    INDEX idx_domain (domain),
                                    INDEX idx_status (status)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                            ");
                            $created_tables[] = $table;
                            echo "<div class='success'>✅ Created table: $table</div>";
                            break;
                            
                        default:
                            echo "<div class='warning'>⚠️ Table '$table' creation not implemented yet</div>";
                            break;
                    }
                } catch (Exception $e) {
                    $errors[] = "Error creating table $table: " . $e->getMessage();
                    echo "<div class='error'>❌ Error creating table $table: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
            
            // Re-run createAllTables to ensure all core tables exist
            echo "<h2>Verifying Core Tables</h2>";
            try {
                $table_result = createAllTables($conn);
                if ($table_result['success']) {
                    echo "<div class='success'>✅ All core tables verified/created</div>";
                } else {
                    echo "<div class='warning'>⚠️ Some issues: " . implode(', ', $table_result['errors']) . "</div>";
                }
            } catch (Exception $e) {
                echo "<div class='error'>❌ Error verifying tables: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            echo "<div class='success'>✅ All required tables exist!</div>";
        }
        
        // Final count
        $stmt = $conn->query("SHOW TABLES");
        $final_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h2>Final Status</h2>";
        echo "<div class='success'>✅ Total tables in database: " . count($final_tables) . "</div>";
        echo "<div class='info'>Tables created in this run: " . count($created_tables) . "</div>";
        
        if (count($created_tables) > 0) {
            echo "<div class='info'><strong>Created tables:</strong> " . implode(', ', $created_tables) . "</div>";
        }
        
        // List all tables
        echo "<h2>All Tables in Database</h2>";
        echo "<table>";
        echo "<tr><th>#</th><th>Table Name</th></tr>";
        foreach ($final_tables as $index => $table) {
            echo "<tr><td>" . ($index + 1) . "</td><td>$table</td></tr>";
        }
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Fatal error: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<div class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</div>";
    }
    ?>
    
    <hr>
    <p><strong>⚠️ Security Warning:</strong> Delete this file after use!</p>
    <p><a href="index.php">← Back to Homepage</a></p>
</body>
</html>












