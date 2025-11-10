<?php
/**
 * Ads Display Helper
 * Fetches and displays ads based on position
 */

require_once __DIR__ . '/../config/database.php';

function getAdsByPosition($position) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT * FROM ads WHERE position = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$position]);
        $ad = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $ad ?: null;
    } catch (Exception $e) {
        error_log("Error fetching ads: " . $e->getMessage());
        return null;
    }
}

function displayAd($position) {
    $ad = getAdsByPosition($position);
    
    if (!$ad || empty($ad['content'])) {
        return '';
    }
    
    switch ($ad['type']) {
        case 'code':
            // Output raw HTML/JS code - CRITICAL: Do not escape or modify
            // AdSense scripts MUST be output exactly as stored in database
            $ad_content = $ad['content'];
            
            // Remove any PHP tags that might have been accidentally saved
            $ad_content = str_replace(['<?php', '<?', '?>'], '', $ad_content);
            
            // For AdSense: Remove the head script tag if present (it should be in head, not body)
            // The head script will be extracted separately and added to <head>
            // We only want the body ad unit code here
            if (preg_match('/<script[^>]*src=["\'][^"\']*pagead2\.googlesyndication\.com[^"\']*["\'][^>]*><\/script>/i', $ad_content)) {
                // Remove the head script tag from body content
                $ad_content = preg_replace('/<script[^>]*src=["\'][^"\']*pagead2\.googlesyndication\.com[^"\']*["\'][^>]*><\/script>/i', '', $ad_content);
                $ad_content = trim($ad_content);
            }
            
            // Return raw HTML - scripts will execute when output to page
            // The ad content is stored in database and should contain valid HTML/JS
            return $ad_content;
            break;
            
        case 'image':
            // Fix image path - handle both absolute URLs and relative paths
            $imageContent = $ad['content'];
            if (strpos($imageContent, 'http://') === 0 || strpos($imageContent, 'https://') === 0) {
                // Already absolute URL
                $imageUrl = $imageContent;
            } else {
                // Remove '../' prefix if present (from upload path)
                $imageContent = ltrim($imageContent, '../');
                $imageContent = ltrim($imageContent, '/');
                // Use asset_path helper for proper absolute URL
                $imageUrl = asset_path($imageContent);
            }
            $output .= '<a href="' . htmlspecialchars($ad['link'] ?? '#') . '" target="_blank" rel="nofollow">';
            $output .= '<img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($ad['title'] ?? 'Advertisement') . '" style="max-width: 100%; height: auto; display: block; margin: 0 auto;" onerror="this.style.display=\'none\'; console.error(\'Ad image failed to load: ' . htmlspecialchars($imageUrl) . '\');">';
            $output .= '</a>';
            break;
            
        case 'video':
            $videoContent = $ad['content'];
            
            // Check if it's a local uploaded file
            if (strpos($videoContent, 'uploads/ads/videos/') === 0) {
                $videoUrl = asset_path($videoContent);
                // Remove controls attribute to hide progress bar and all controls
                $output .= '<video autoplay muted loop playsinline style="width: 100%; max-width: 100%; height: auto; display: block; pointer-events: none;"><source src="' . htmlspecialchars($videoUrl) . '"></video>';
            }
            // Check if it's YouTube
            else if (strpos($videoContent, 'youtube.com') !== false || strpos($videoContent, 'youtu.be') !== false) {
                // Convert YouTube URL to embed with hidden UI and autoplay
                if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $videoContent, $matches)) {
                    $videoId = $matches[1];
                    // YouTube embed with hidden UI elements and autoplay
                    // Parameters: autoplay=1, mute=1, controls=0 (hide controls), modestbranding=1 (hide YouTube logo),
                    // rel=0 (hide related videos), showinfo=0, iv_load_policy=3 (hide annotations),
                    // fs=0 (disable fullscreen), disablekb=1 (disable keyboard), playsinline=1 (mobile)
                    // For loop, need playlist=VIDEO_ID
                    // YouTube embed with completely hidden UI - no progress bar, no controls, nothing
                    // Additional parameters to hide progress: progressbar=0 (though this might not work in all browsers)
                    $embedUrl = 'https://www.youtube.com/embed/' . $videoId . '?autoplay=1&mute=1&loop=1&playlist=' . $videoId . '&controls=0&modestbranding=1&rel=0&showinfo=0&iv_load_policy=3&fs=0&disablekb=1&playsinline=1&enablejsapi=0&cc_load_policy=0';
                    $output .= '<iframe width="100%" height="315" src="' . htmlspecialchars($embedUrl) . '" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen style="display: block;"></iframe>';
                } else {
                    // Fallback to regular video tag without controls
                    $output .= '<video autoplay muted loop playsinline style="width: 100%; pointer-events: none;"><source src="' . htmlspecialchars($videoContent) . '"></video>';
                }
            } 
            // Check if it's Vimeo
            else if (strpos($videoContent, 'vimeo.com') !== false) {
                // Convert Vimeo URL to embed with autoplay
                if (preg_match('/vimeo\.com\/(\d+)/', $videoContent, $matches)) {
                    $videoId = $matches[1];
                    $embedUrl = 'https://player.vimeo.com/video/' . $videoId . '?autoplay=1&loop=1&muted=1&background=1';
                    $output .= '<iframe width="100%" height="315" src="' . htmlspecialchars($embedUrl) . '" frameborder="0" allow="autoplay; fullscreen" allowfullscreen style="display: block;"></iframe>';
                } else {
                    // Fallback to regular video tag without controls
                    $output .= '<video autoplay muted loop playsinline style="width: 100%; pointer-events: none;"><source src="' . htmlspecialchars($videoContent) . '"></video>';
                }
            } 
            // Direct video URL
            else {
                // Check if it's an absolute URL or relative
                if (strpos($videoContent, 'http://') === 0 || strpos($videoContent, 'https://') === 0) {
                    $videoUrl = $videoContent;
                } else {
                    $videoUrl = asset_path($videoContent);
                }
                // Remove controls attribute to hide progress bar and all controls
                $output .= '<video autoplay muted loop playsinline style="width: 100%; max-width: 100%; height: auto; display: block; pointer-events: none;"><source src="' . htmlspecialchars($videoUrl) . '"></video>';
            }
            break;
    }
    
    $output .= '</div>';
    
    return $output;
}

// Helper function for asset paths (same as in song-details.php)
if (!function_exists('asset_path')) {
    function asset_path($path) {
        if (empty($path)) return '';
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base_path = defined('BASE_PATH') ? BASE_PATH : '/';
        $baseUrl = $protocol . $host . $base_path;
        if (strpos($path, '/') === 0) {
            return $baseUrl . ltrim($path, '/');
        }
        return $baseUrl . ltrim($path, '/');
    }
}
?>

