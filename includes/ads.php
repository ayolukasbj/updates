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
    try {
        $ad = getAdsByPosition($position);
        
        if (!$ad || empty($ad['content'])) {
            return '';
        }
        
        // Validate ad type
        if (!isset($ad['type']) || !in_array($ad['type'], ['code', 'image', 'video'])) {
            error_log("Invalid ad type for position $position: " . ($ad['type'] ?? 'unknown'));
            return '';
        }
        
        // Initialize output variable
        $output = '';
        
        switch ($ad['type']) {
            case 'code':
                try {
                    // Output raw HTML/JS code - CRITICAL: Do not escape or modify
                    // All code/HTML ads (AdSense, custom HTML, etc.) MUST be output exactly as stored
                    $ad_content = $ad['content'];
                    
                    // Validate content is not empty after trimming
                    if (empty(trim($ad_content))) {
                        error_log("Empty ad content for position $position");
                        return '';
                    }
                    
                    // Remove any PHP tags that might have been accidentally saved (security)
                    $ad_content = str_replace(['<?php', '<?', '?>'], '', $ad_content);
                    
                    // Check if this is an AdSense ad
                    $is_adsense = preg_match('/pagead2\.googlesyndication\.com/i', $ad_content);
                    
                    if ($is_adsense) {
                        // For AdSense ONLY: Remove the head script tag if present
                        // The head script will be extracted separately and added to <head>
                        // We only want the body ad unit code here (<ins class="adsbygoogle">)
                        if (preg_match('/<script[^>]*src=["\'][^"\']*pagead2\.googlesyndication\.com[^"\']*["\'][^>]*><\/script>/i', $ad_content)) {
                            // Remove the head script tag from body content
                            $ad_content = preg_replace('/<script[^>]*src=["\'][^"\']*pagead2\.googlesyndication\.com[^"\']*["\'][^>]*><\/script>/i', '', $ad_content);
                            $ad_content = trim($ad_content);
                        }
                    }
                    // For non-AdSense code ads (custom HTML, other ad networks), output everything as-is
                    
                    // Return raw HTML - scripts will execute when output to page
                    // CRITICAL: This must be output with echo, NOT htmlspecialchars
                    return $ad_content;
                } catch (Exception $e) {
                    error_log("Error processing code ad for position $position: " . $e->getMessage());
                    return '';
                }
            
            case 'image':
                try {
                    // Fix image path - handle both absolute URLs and relative paths
                    $imageContent = $ad['content'];
                    if (empty($imageContent)) {
                        error_log("Empty image content for position $position");
                        return '';
                    }
                    
                    if (strpos($imageContent, 'http://') === 0 || strpos($imageContent, 'https://') === 0) {
                        // Already absolute URL
                        $imageUrl = $imageContent;
                    } else {
                        // Remove '../' prefix if present (from upload path)
                        $imageContent = ltrim($imageContent, '../');
                        $imageContent = ltrim($imageContent, '/');
                        // Use asset_path helper for proper absolute URL
                        if (function_exists('asset_path')) {
                            $imageUrl = asset_path($imageContent);
                        } else {
                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            $base_path = defined('BASE_PATH') ? BASE_PATH : '/';
                            $imageUrl = $protocol . $host . $base_path . ltrim($imageContent, '/');
                        }
                    }
                    $output = '<a href="' . htmlspecialchars($ad['link'] ?? '#') . '" target="_blank" rel="nofollow">';
                    $output .= '<img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($ad['title'] ?? 'Advertisement') . '" style="max-width: 100%; height: auto; display: block; margin: 0 auto;" onerror="this.style.display=\'none\'; console.error(\'Ad image failed to load: ' . htmlspecialchars($imageUrl) . '\');">';
                    $output .= '</a>';
                } catch (Exception $e) {
                    error_log("Error processing image ad for position $position: " . $e->getMessage());
                    return '';
                }
                break;
            
            case 'video':
                try {
                    $videoContent = $ad['content'];
                    
                    if (empty($videoContent)) {
                        error_log("Empty video content for position $position");
                        return '';
                    }
                    
                    // Check if it's a local uploaded file
                    if (strpos($videoContent, 'uploads/ads/videos/') === 0) {
                        if (function_exists('asset_path')) {
                            $videoUrl = asset_path($videoContent);
                        } else {
                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            $base_path = defined('BASE_PATH') ? BASE_PATH : '/';
                            $videoUrl = $protocol . $host . $base_path . ltrim($videoContent, '/');
                        }
                        // Remove controls attribute to hide progress bar and all controls
                        $output = '<video autoplay muted loop playsinline style="width: 100%; max-width: 100%; height: auto; display: block; pointer-events: none;"><source src="' . htmlspecialchars($videoUrl) . '"></video>';
                    }
                    // Check if it's YouTube
                    else if (strpos($videoContent, 'youtube.com') !== false || strpos($videoContent, 'youtu.be') !== false) {
                        // Convert YouTube URL to embed with hidden UI and autoplay
                        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $videoContent, $matches)) {
                            $videoId = $matches[1];
                            // YouTube embed with hidden UI elements and autoplay
                            $embedUrl = 'https://www.youtube.com/embed/' . $videoId . '?autoplay=1&mute=1&loop=1&playlist=' . $videoId . '&controls=0&modestbranding=1&rel=0&showinfo=0&iv_load_policy=3&fs=0&disablekb=1&playsinline=1&enablejsapi=0&cc_load_policy=0';
                            $output = '<iframe width="100%" height="315" src="' . htmlspecialchars($embedUrl) . '" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen style="display: block;"></iframe>';
                        } else {
                            // Fallback to regular video tag without controls
                            $output = '<video autoplay muted loop playsinline style="width: 100%; pointer-events: none;"><source src="' . htmlspecialchars($videoContent) . '"></video>';
                        }
                    } 
                    // Check if it's Vimeo
                    else if (strpos($videoContent, 'vimeo.com') !== false) {
                        // Convert Vimeo URL to embed with autoplay
                        if (preg_match('/vimeo\.com\/(\d+)/', $videoContent, $matches)) {
                            $videoId = $matches[1];
                            $embedUrl = 'https://player.vimeo.com/video/' . $videoId . '?autoplay=1&loop=1&muted=1&background=1';
                            $output = '<iframe width="100%" height="315" src="' . htmlspecialchars($embedUrl) . '" frameborder="0" allow="autoplay; fullscreen" allowfullscreen style="display: block;"></iframe>';
                        } else {
                            // Fallback to regular video tag without controls
                            $output = '<video autoplay muted loop playsinline style="width: 100%; pointer-events: none;"><source src="' . htmlspecialchars($videoContent) . '"></video>';
                        }
                    } 
                    // Direct video URL
                    else {
                        // Check if it's an absolute URL or relative
                        if (strpos($videoContent, 'http://') === 0 || strpos($videoContent, 'https://') === 0) {
                            $videoUrl = $videoContent;
                        } else {
                            if (function_exists('asset_path')) {
                                $videoUrl = asset_path($videoContent);
                            } else {
                                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                                $base_path = defined('BASE_PATH') ? BASE_PATH : '/';
                                $videoUrl = $protocol . $host . $base_path . ltrim($videoContent, '/');
                            }
                        }
                        // Remove controls attribute to hide progress bar and all controls
                        $output = '<video autoplay muted loop playsinline style="width: 100%; max-width: 100%; height: auto; display: block; pointer-events: none;"><source src="' . htmlspecialchars($videoUrl) . '"></video>';
                    }
                } catch (Exception $e) {
                    error_log("Error processing video ad for position $position: " . $e->getMessage());
                    return '';
                }
                break;
                
            default:
                // Unknown ad type - return empty
                error_log("Unknown ad type for position $position: " . ($ad['type'] ?? 'unknown'));
                return '';
        }
        
        // Return output (only for image and video types, code type returns early)
        return $output;
        
    } catch (Exception $e) {
        error_log("Error displaying ad for position $position: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return '';
    }
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

