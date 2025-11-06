-- Admin system enhancement for Music Platform
-- Add admin role to users table

-- Add role column to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('user', 'artist', 'admin', 'super_admin') DEFAULT 'user' AFTER subscription_type;

-- Create admin activity log table
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50), -- user, song, artist, news, etc.
    target_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
);

-- Create news table for admin content management
CREATE TABLE IF NOT EXISTS news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    category VARCHAR(50),
    image VARCHAR(255),
    content TEXT,
    excerpt TEXT,
    views INT DEFAULT 0,
    is_published BOOLEAN DEFAULT TRUE,
    featured BOOLEAN DEFAULT FALSE,
    author_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category),
    INDEX idx_published (is_published),
    INDEX idx_featured (featured),
    INDEX idx_created (created_at)
);

-- Add status field to songs for admin approval
ALTER TABLE songs ADD COLUMN IF NOT EXISTS status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved' AFTER is_explicit;

-- Add banned field to users
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_banned BOOLEAN DEFAULT FALSE AFTER is_active;
ALTER TABLE users ADD COLUMN IF NOT EXISTS banned_reason TEXT AFTER is_banned;

-- Create default super admin (password: admin123)
INSERT IGNORE INTO users (username, email, password, role, email_verified, is_active) VALUES
('superadmin', 'admin@musicplatform.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', TRUE, TRUE);

-- Insert sample news if news table is empty
INSERT IGNORE INTO news (title, slug, category, image, content, excerpt, author_id) VALUES
('Welcome to Our Music Platform', 'welcome-to-our-music-platform', 'Announcements', 'uploads/images/news1.jpg', 
'We are excited to announce the launch of our new music streaming platform. Discover, stream, and download your favorite songs from talented artists around the world.', 
'We are excited to announce the launch of our new music streaming platform.', 1),
('New Features Released', 'new-features-released', 'Updates', 'uploads/images/news2.jpg',
'Check out our latest features including improved search, personalized recommendations, and enhanced audio quality.',
'Check out our latest features including improved search and recommendations.', 1);

