<?php
/**
 * Sitemap Generator
 * Generates XML sitemap for all songs, news, artists, genres, etc.
 */

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Load config
require_once 'config/config.php';
require_once 'config/database.php';

// Set content type to XML
header('Content-Type: application/xml; charset=utf-8');

// Get base URL
$base_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

// Initialize database
$db = new Database();
$conn = $db->getConnection();

// Start XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

// Helper function to output URL
function outputUrl($loc, $lastmod = null, $changefreq = 'weekly', $priority = '0.8', $images = []) {
    $loc = htmlspecialchars($loc, ENT_XML1, 'UTF-8');
    $lastmod = $lastmod ? date('c', strtotime($lastmod)) : date('c');
    
    echo "  <url>\n";
    echo "    <loc>$loc</loc>\n";
    echo "    <lastmod>$lastmod</lastmod>\n";
    echo "    <changefreq>$changefreq</changefreq>\n";
    echo "    <priority>$priority</priority>\n";
    
    // Add images if provided
    foreach ($images as $image) {
        if (!empty($image['url'])) {
            echo "    <image:image>\n";
            echo "      <image:loc>" . htmlspecialchars($image['url'], ENT_XML1, 'UTF-8') . "</image:loc>\n";
            if (!empty($image['title'])) {
                echo "      <image:title>" . htmlspecialchars($image['title'], ENT_XML1, 'UTF-8') . "</image:title>\n";
            }
            if (!empty($image['caption'])) {
                echo "      <image:caption>" . htmlspecialchars($image['caption'], ENT_XML1, 'UTF-8') . "</image:caption>\n";
            }
            echo "    </image:image>\n";
        }
    }
    
    echo "  </url>\n";
}

// Homepage
outputUrl($base_url . '/', date('Y-m-d'), 'daily', '1.0');

// Songs
if ($conn) {
    try {
        // Check if songs table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'songs'");
        if ($checkTable->rowCount() > 0) {
            $songs = $conn->query("
                SELECT s.*, u.username as uploader_username
                FROM songs s
                LEFT JOIN users u ON s.uploaded_by = u.id
                WHERE s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved'
                ORDER BY s.created_at DESC
            ");
            
            while ($song = $songs->fetch(PDO::FETCH_ASSOC)) {
                // Generate slug
                $titleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['title'] ?? ''));
                $titleSlug = preg_replace('/\s+/', '-', trim($titleSlug));
                $artistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['artist'] ?? $song['uploader_username'] ?? 'unknown'));
                $artistSlug = preg_replace('/\s+/', '-', trim($artistSlug));
                $slug = $titleSlug . '-by-' . $artistSlug;
                
                $url = $base_url . '/song/' . urlencode($slug);
                
                // Get cover art for image
                $images = [];
                if (!empty($song['cover_art'])) {
                    $imageUrl = $base_url . '/' . ltrim($song['cover_art'], '/');
                    $images[] = [
                        'url' => $imageUrl,
                        'title' => htmlspecialchars($song['title'] ?? ''),
                        'caption' => htmlspecialchars(($song['artist'] ?? 'Unknown Artist') . ' - ' . ($song['title'] ?? ''))
                    ];
                }
                
                outputUrl($url, $song['updated_at'] ?? $song['created_at'] ?? null, 'weekly', '0.9', $images);
            }
        }
    } catch (Exception $e) {
        error_log("Sitemap error (songs): " . $e->getMessage());
    }
    
    // News
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE 'news'");
        if ($checkTable->rowCount() > 0) {
            $news = $conn->query("
                SELECT * FROM news 
                WHERE is_published = 1 
                ORDER BY created_at DESC
            ");
            
            while ($article = $news->fetch(PDO::FETCH_ASSOC)) {
                $url = $base_url . '/news-details.php?id=' . $article['id'];
                
                // Get featured image
                $images = [];
                if (!empty($article['featured_image'])) {
                    $imageUrl = $base_url . '/' . ltrim($article['featured_image'], '/');
                    $images[] = [
                        'url' => $imageUrl,
                        'title' => htmlspecialchars($article['title'] ?? ''),
                        'caption' => htmlspecialchars($article['title'] ?? '')
                    ];
                } elseif (!empty($article['image'])) {
                    $imageUrl = $base_url . '/' . ltrim($article['image'], '/');
                    $images[] = [
                        'url' => $imageUrl,
                        'title' => htmlspecialchars($article['title'] ?? ''),
                        'caption' => htmlspecialchars($article['title'] ?? '')
                    ];
                }
                
                outputUrl($url, $article['updated_at'] ?? $article['created_at'] ?? null, 'weekly', '0.8', $images);
            }
        }
    } catch (Exception $e) {
        error_log("Sitemap error (news): " . $e->getMessage());
    }
    
    // Artists
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE 'artists'");
        if ($checkTable->rowCount() > 0) {
            $artists = $conn->query("SELECT * FROM artists ORDER BY name");
            
            while ($artist = $artists->fetch(PDO::FETCH_ASSOC)) {
                $slug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $artist['name'] ?? ''));
                $slug = preg_replace('/\s+/', '-', trim($slug));
                $url = $base_url . '/artist/' . urlencode($slug);
                
                // Get avatar for image
                $images = [];
                if (!empty($artist['avatar'])) {
                    $imageUrl = $base_url . '/' . ltrim($artist['avatar'], '/');
                    $images[] = [
                        'url' => $imageUrl,
                        'title' => htmlspecialchars($artist['name'] ?? ''),
                        'caption' => htmlspecialchars($artist['name'] ?? '')
                    ];
                }
                
                outputUrl($url, $artist['updated_at'] ?? $artist['created_at'] ?? null, 'monthly', '0.7', $images);
            }
        }
    } catch (Exception $e) {
        error_log("Sitemap error (artists): " . $e->getMessage());
    }
    
    // Genres
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE 'genres'");
        if ($checkTable->rowCount() > 0) {
            $genres = $conn->query("SELECT * FROM genres ORDER BY name");
            
            while ($genre = $genres->fetch(PDO::FETCH_ASSOC)) {
                $slug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $genre['name'] ?? ''));
                $slug = preg_replace('/\s+/', '-', trim($slug));
                $url = $base_url . '/genre/' . urlencode($slug);
                
                outputUrl($url, $genre['updated_at'] ?? $genre['created_at'] ?? null, 'monthly', '0.6');
            }
        }
    } catch (Exception $e) {
        error_log("Sitemap error (genres): " . $e->getMessage());
    }
    
    // Albums
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE 'albums'");
        if ($checkTable->rowCount() > 0) {
            $albums = $conn->query("SELECT * FROM albums ORDER BY title");
            
            while ($album = $albums->fetch(PDO::FETCH_ASSOC)) {
                $slug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $album['title'] ?? ''));
                $slug = preg_replace('/\s+/', '-', trim($slug));
                $url = $base_url . '/album/' . urlencode($slug);
                
                // Get cover art
                $images = [];
                if (!empty($album['cover_art'])) {
                    $imageUrl = $base_url . '/' . ltrim($album['cover_art'], '/');
                    $images[] = [
                        'url' => $imageUrl,
                        'title' => htmlspecialchars($album['title'] ?? ''),
                        'caption' => htmlspecialchars($album['title'] ?? '')
                    ];
                }
                
                outputUrl($url, $album['updated_at'] ?? $album['created_at'] ?? null, 'monthly', '0.7', $images);
            }
        }
    } catch (Exception $e) {
        error_log("Sitemap error (albums): " . $e->getMessage());
    }
    
    // Playlists (if table exists)
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE 'playlists'");
        if ($checkTable->rowCount() > 0) {
            $playlists = $conn->query("SELECT * FROM playlists WHERE is_public = 1 ORDER BY created_at DESC");
            
            while ($playlist = $playlists->fetch(PDO::FETCH_ASSOC)) {
                $url = $base_url . '/playlist/' . $playlist['id'];
                outputUrl($url, $playlist['updated_at'] ?? $playlist['created_at'] ?? null, 'weekly', '0.6');
            }
        }
    } catch (Exception $e) {
        error_log("Sitemap error (playlists): " . $e->getMessage());
    }
}

// Static pages
$static_pages = [
    ['url' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
    ['url' => '/songs.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['url' => '/artists.php', 'priority' => '0.8', 'changefreq' => 'weekly'],
    ['url' => '/genres.php', 'priority' => '0.7', 'changefreq' => 'weekly'],
    ['url' => '/news.php', 'priority' => '0.8', 'changefreq' => 'daily'],
    ['url' => '/login.php', 'priority' => '0.5', 'changefreq' => 'monthly'],
    ['url' => '/register.php', 'priority' => '0.5', 'changefreq' => 'monthly'],
];

foreach ($static_pages as $page) {
    outputUrl($base_url . $page['url'], date('Y-m-d'), $page['changefreq'], $page['priority']);
}

echo '</urlset>';

