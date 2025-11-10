-- News System Schema
-- Add this to your existing database

-- News table
CREATE TABLE IF NOT EXISTS news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    category ENUM('National News', 'Exclusive', 'Hot', 'Entertainment', 'Politics', 'Shocking', 'Celebrity Gossip', 'Just in', 'Lifestyle and Events') DEFAULT 'Entertainment',
    content TEXT NOT NULL,
    excerpt TEXT,
    image VARCHAR(255),
    author_id INT,
    views BIGINT DEFAULT 0,
    status ENUM('draft', 'published', 'archived') DEFAULT 'published',
    featured BOOLEAN DEFAULT FALSE,
    published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_featured (featured),
    INDEX idx_published_at (published_at),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
);

-- News comments table
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
);

-- News views tracking
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
);

-- Insert sample news data
INSERT INTO news (title, slug, category, content, excerpt, published_at) VALUES
('Pr Joseph Okidi\'s mother pleads with CJ Owiny Dollo to release her son\'s body for burial', 'pr-joseph-okidi-mother-pleads', 'National News', 'The mother of the late Pastor Joseph Okidi has made an emotional plea to the Chief Justice Alphonse Owiny-Dollo to release her son\'s body for burial...', 'Emotional plea from bereaved mother', '2025-10-28 10:00:00'),
('We don\'t want Bobi Wine in Kitgum - Jah Fire warns Bobi Wine', 'we-dont-want-bobi-wine-kitgum', 'Exclusive', 'Local artist Jah Fire has issued a strong warning to opposition leader Bobi Wine regarding his political ambitions in Kitgum...', 'Controversial statement from local artist', '2025-10-28 09:30:00'),
('Singer Profesa Maros clashes with fellow Lango artists over money', 'singer-profesa-maros-clashes', 'Hot', 'A heated dispute has erupted between Singer Profesa Maros and fellow Lango artists over financial disagreements...', 'Financial dispute among artists', '2025-10-27 14:20:00'),
('VIDEO: Odong Romeo they used you to fight me - Bosmic Otim in tears', 'odong-romeo-bosmic-otim-tears', 'Entertainment', 'In an emotional video, popular artist Bosmic Otim breaks down in tears while addressing allegations...', 'Emotional response from Bosmic Otim', '2025-10-27 11:15:00'),
('I am still alive not dead - Zing Zang debunks death rumours', 'zing-zang-alive-not-dead', 'Exclusive', 'Pioneer musician Zing Zang has come out to debunk rumours circulating on social media alleging that he had passed away...', 'Artist addresses false death reports', '2025-10-26 16:45:00'),
('NRM supporters involved in nasty road accident in Nwoya', 'nrm-supporters-road-accident-nwoya', 'Shocking', 'Several NRM supporters were involved in a serious road accident while returning from a political rally in Nwoya district...', 'Traffic incident involving political supporters', '2025-10-26 13:30:00');

