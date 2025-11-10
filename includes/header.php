<?php
// Session should already be started by the including file
// Only start if not already started (fallback)
if (session_status() === PHP_SESSION_NONE) {
    @session_start(); // Suppress warning if headers already sent
}

// Check maintenance mode (only for non-admin pages, skip if already checked in index.php)
// Skip maintenance check if we're in news-details.php to avoid double-checking
if (!defined('MAINTENANCE_CHECKED') && !defined('SKIP_MAINTENANCE_CHECK')) {
    $is_maintenance = false;
    $is_admin = false;
    try {
        // Only check if database is available
        if (file_exists(__DIR__ . '/../config/database.php')) {
            require_once __DIR__ . '/../config/database.php';
            $db = new Database();
            $conn = $db->getConnection();
            if ($conn) {
                $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $maintenance_setting = $result['setting_value'] ?? 'false';
                $is_maintenance = ($maintenance_setting === 'true' || $maintenance_setting === '1');
                
                // Check if user is admin (bypass maintenance mode)
                if ($is_maintenance && isset($_SESSION['user_id'])) {
                    $user_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
                    $user_stmt->execute([$_SESSION['user_id']]);
                    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user && in_array($user['role'] ?? '', ['admin', 'super_admin'])) {
                        $is_admin = true;
                        $is_maintenance = false; // Allow admin to bypass
                    }
                }
                
                // Show maintenance page if enabled and user is not admin
                if ($is_maintenance && !$is_admin) {
                    // Skip if we're in admin area, API, login, or news pages
                    $script_path = $_SERVER['SCRIPT_NAME'] ?? '';
                    $is_admin_area = (strpos($script_path, '/admin/') !== false);
                    $is_api = (strpos($script_path, '/api/') !== false);
                    $is_login = (strpos($script_path, 'login.php') !== false);
                    $is_news = (strpos($script_path, 'news') !== false);
                    
                    if (!$is_admin_area && !$is_api && !$is_login && !$is_news) {
                        http_response_code(503);
                        ?>
                        <!DOCTYPE html>
                        <html lang="en">
                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <title>Maintenance Mode - <?php echo htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'Site'); ?></title>
                            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
                            <style>
                                * { margin: 0; padding: 0; box-sizing: border-box; }
                                body { 
                                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                    min-height: 100vh;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    padding: 20px;
                                }
                                .maintenance-container {
                                    background: white;
                                    border-radius: 20px;
                                    padding: 60px 40px;
                                    max-width: 600px;
                                    width: 100%;
                                    text-align: center;
                                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                                }
                                .maintenance-icon {
                                    font-size: 80px;
                                    color: #667eea;
                                    margin-bottom: 30px;
                                    animation: pulse 2s infinite;
                                }
                                @keyframes pulse {
                                    0%, 100% { transform: scale(1); }
                                    50% { transform: scale(1.1); }
                                }
                                h1 { 
                                    font-size: 36px;
                                    color: #1f2937;
                                    margin-bottom: 20px;
                                    font-weight: 800;
                                }
                                p { 
                                    font-size: 18px;
                                    color: #6b7280;
                                    line-height: 1.6;
                                    margin-bottom: 30px;
                                }
                                .info-box {
                                    background: #f3f4f6;
                                    border-radius: 10px;
                                    padding: 20px;
                                    margin-top: 30px;
                                    text-align: left;
                                }
                                .info-box h3 {
                                    color: #1f2937;
                                    margin-bottom: 10px;
                                    font-size: 16px;
                                }
                                .info-box ul {
                                    color: #6b7280;
                                    margin-left: 20px;
                                    line-height: 1.8;
                                }
                            </style>
                            
                        </head>
                        <body>
                            <div class="maintenance-container">
                                <div class="maintenance-icon">
                                    <i class="fas fa-tools"></i>
                                </div>
                                <h1>We'll Be Back Soon!</h1>
                                <p>We're currently performing scheduled maintenance to improve your experience. Please check back shortly.</p>
                                <div class="info-box">
                                    <h3><i class="fas fa-info-circle"></i> What's happening?</h3>
                                    <ul>
                                        <li>System updates and improvements</li>
                                        <li>Performance optimizations</li>
                                        <li>Security enhancements</li>
                                    </ul>
                                </div>
                            </div>
                        </body>
                        </html>
                        <?php
                        exit;
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Maintenance mode check error: " . $e->getMessage());
        // Don't block page if maintenance check fails
    }
    define('MAINTENANCE_CHECKED', true);
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? ($_SESSION['username'] ?? 'User') : '';

// Load theme settings
if (file_exists(__DIR__ . '/theme-loader.php')) {
    require_once __DIR__ . '/theme-loader.php';
    if (function_exists('getThemeColors')) {
        $themeColors = getThemeColors();
    } else {
        $themeColors = [];
    }
} else {
    $themeColors = [];
}

// Load brand colors
if (file_exists(__DIR__ . '/brand-colors.php')) {
    require_once __DIR__ . '/brand-colors.php';
    if (function_exists('renderBrandCSS')) {
        renderBrandCSS();
    }
}

// Load site settings
function getHeaderSetting($key, $default = '') {
    try {
        // Try to use existing connection if available
        global $conn;
        if (!$conn) {
            require_once __DIR__ . '/../config/database.php';
            $db = new Database();
            $conn = $db->getConnection();
        }
        if ($conn) {
            $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && !empty($result['setting_value'])) {
                return $result['setting_value'];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting header setting $key: " . $e->getMessage());
    }
    return $default;
}

$site_name = getHeaderSetting('site_name', defined('SITE_NAME') ? SITE_NAME : '');
$show_site_name = getHeaderSetting('show_site_name', '1');

// Get logo - use SettingsManager if available (like homepage), otherwise use direct query
$site_logo = '';
if (!empty($GLOBALS['site_logo_preloaded'])) {
    $site_logo = $GLOBALS['site_logo_preloaded'];
} else {
    // Try SettingsManager first (same as homepage)
    if (file_exists(__DIR__ . '/settings.php')) {
        require_once __DIR__ . '/settings.php';
        if (class_exists('SettingsManager')) {
            $site_logo = SettingsManager::getSiteLogo();
        }
    }
    
    // If SettingsManager didn't work or returned empty, try direct database query
    if (empty($site_logo)) {
        try {
            global $conn;
            if (!$conn) {
                require_once __DIR__ . '/../config/database.php';
                $db = new Database();
                $conn = $db->getConnection();
            }
            if ($conn) {
                $logoStmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_logo'");
                $logoStmt->execute();
                $logo_result = $logoStmt->fetch(PDO::FETCH_ASSOC);
                if ($logo_result && !empty($logo_result['setting_value'])) {
                    $site_logo = $logo_result['setting_value'];
                }
            }
        } catch (Exception $e) {
            error_log("Error getting logo in header.php: " . $e->getMessage());
        }
    }
    
    // Normalize and build full URL for logo (same as artist-profile-mobile.php)
    if (!empty($site_logo)) {
        // Normalize logo path
        $normalizedLogo = str_replace('\\', '/', $site_logo);
        $normalizedLogo = preg_replace('#^\.\./#', '', $normalizedLogo);
        $normalizedLogo = str_replace('../', '', $normalizedLogo);
        
        // Build full URL if needed (not already absolute)
        if (strpos($normalizedLogo, 'http://') !== 0 && strpos($normalizedLogo, 'https://') !== 0) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base_path = defined('BASE_PATH') ? BASE_PATH : '/';
            if ($base_path !== '/' && substr($base_path, -1) !== '/') {
                $base_path .= '/';
            }
            $baseUrl = $protocol . $host . $base_path;
            $site_logo = $baseUrl . ltrim($normalizedLogo, '/');
        } else {
            $site_logo = $normalizedLogo;
        }
    }
}

$site_favicon = getHeaderSetting('site_favicon', '');

// Debug: Log logo value for troubleshooting
if (!empty($site_logo)) {
    error_log("Site logo from DB: " . $site_logo);
}
$user_avatar = $isLoggedIn ? getHeaderSetting('user_avatar_' . ($_SESSION['user_id'] ?? ''), 'assets/images/default-avatar.svg') : 'assets/images/default-avatar.svg';
?>
<?php
// Get current request URL for base tag (works with IP and ngrok)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Fix: Always use root base path for header links
// When accessed via /news/{slug}, the base tag must point to root, not /news/
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';

// Always force base path to root for header
// This ensures relative links like href="songs.php" resolve to /songs.php, not /news/songs.php
$base_path = '/';

$currentBaseUrl = $protocol . $host . $base_path;

// Asset path helper - only define if not already defined
if (!function_exists('asset_path')) {
    function asset_path($path) {
        global $currentBaseUrl;
        if (empty($path)) {
            return '';
        }
        // If already absolute URL, return as is
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }
        
        // Convert Windows backslashes to forward slashes
        $path = str_replace('\\', '/', $path);
        
        // Remove '../' if present (from admin directory)
        $path = preg_replace('#^\.\./#', '', $path);
        $path = str_replace('../', '', $path);
        
        // Remove leading slash if present
        $path = ltrim($path, '/');
        
        // Check if path already contains base URL (avoid duplication)
        $baseWithoutSlash = rtrim($currentBaseUrl, '/');
        if (strpos($path, $baseWithoutSlash) === 0) {
            return $path;
        }
        
        // If path starts with uploads or assets, use as is
        if (strpos($path, 'uploads/') === 0 || strpos($path, 'assets/') === 0) {
            return $baseWithoutSlash . '/' . $path;
        }
        
        // For other paths, prepend base URL
        // Handle cases where path might be relative to root
        return $baseWithoutSlash . '/' . $path;
    }
}
?>
<base href="<?php echo htmlspecialchars($currentBaseUrl); ?>">
<?php
// Add favicon if set
if (!empty($site_favicon)) {
    $favicon_path = str_replace('../', '', $site_favicon);
    // If it's a relative path, prepend BASE_PATH
    if (strpos($favicon_path, 'http') !== 0 && strpos($favicon_path, '/') !== 0) {
        $favicon_path = $base_path . $favicon_path;
    }
    echo '<link rel="icon" type="image/x-icon" href="' . htmlspecialchars($favicon_path) . '">' . "\n";
    echo '<link rel="shortcut icon" type="image/x-icon" href="' . htmlspecialchars($favicon_path) . '">' . "\n";
}
?>
<!DOCTYPE html>
<?php 
if (function_exists('renderThemeStyles')) {
    renderThemeStyles();
}
?>
<style>
    /* Header Styles - Using Brand Colors */
    .main-header {
        background: var(--brand-primary-navy, #1e4d72) !important;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        position: sticky;
        top: 0;
        z-index: 999;
        width: 100%;
    }

    /* Add spacing below header for content */
    .main-header + * {
        margin-top: 0;
    }

    body {
        padding-top: 0;
    }

    .header-container {
        max-width: 1400px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 15px;
    }

    .logo {
        font-size: 20px;
        font-weight: bold;
        color: #fff;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .logo i {
        font-size: 24px;
    }

    /* Search Bar */
    .search-container {
        position: relative;
        flex: 1;
        max-width: 400px;
        margin: 0 20px;
    }

    .search-box {
        width: 100%;
        padding: 10px 40px 10px 15px;
        border: none;
        border-radius: 25px;
        background: rgba(255,255,255,0.9);
        font-size: 14px;
        outline: none;
        transition: all 0.3s;
    }

    .search-box:focus {
        background: #fff;
        box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        border: 2px solid var(--brand-primary-red, #e74c3c);
    }

    .search-icon {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #666;
        pointer-events: none;
    }

    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        max-height: 400px;
        overflow-y: auto;
        margin-top: 5px;
        z-index: 1000;
        display: none;
    }
    
    @media (max-width: 768px) {
        .search-results {
            position: fixed;
            top: 60px;
            left: 10px;
            right: 10px;
            max-height: calc(100vh - 70px);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
    }

    .search-results.active {
        display: block;
    }

    .search-result-item {
        padding: 12px 15px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: background 0.2s;
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        color: #333;
    }

    .search-result-item:hover {
        background: #f5f5f5;
    }

    .search-result-item:last-child {
        border-bottom: none;
    }

    .result-image {
        width: 50px;
        height: 50px;
        border-radius: 5px;
        object-fit: cover;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    @media (max-width: 768px) {
        .result-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
        }
    }

    .result-info {
        flex: 1;
    }

    .result-title {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 3px;
    }

    .result-meta {
        font-size: 12px;
        color: #666;
    }
    
    @media (max-width: 768px) {
        .search-result-item {
            padding: 16px;
        }
        
        .result-title {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .result-meta {
            font-size: 14px;
        }
        
        .result-type {
            font-size: 12px;
            padding: 3px 10px;
        }
    }

    .result-type {
        background: #64b5f6;
        color: #fff;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
    }

    .no-results {
        padding: 20px;
        text-align: center;
        color: #666;
    }

    .search-loader {
        padding: 15px;
        text-align: center;
        color: #666;
        display: none;
    }

    .search-loader.active {
        display: block;
    }

    .nav-menu {
        display: flex;
        align-items: center;
        gap: 20px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .nav-menu a {
        color: #fff;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: color 0.3s;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .nav-menu a i {
        font-size: 14px;
    }

    .nav-menu a:hover {
        color: #64b5f6;
    }

    .nav-menu a.active {
        color: #64b5f6;
        border-bottom: 2px solid #64b5f6;
    }

    /* Secondary Navigation Menu - Under Search Bar */
    .secondary-nav {
        background: #2c3e50;
        padding: 12px 15px;
        margin-top: 0;
    }

    .secondary-nav-container {
        max-width: 1400px;
        margin: 0 auto;
        position: relative;
    }

    .secondary-nav-menu {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 40px;
        list-style: none;
        margin: 0;
        padding: 0;
        position: relative;
    }

    .secondary-nav-menu li {
        position: relative;
    }

    .secondary-nav-menu a {
        color: #fff;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        letter-spacing: 0.5px;
        padding: 8px 16px;
        display: block;
        transition: color 0.3s;
        position: relative;
        z-index: 2;
    }

    .secondary-nav-menu a:hover,
    .secondary-nav-menu a.active {
        color: #4CAF50;
    }

    /* Curved line highlight */
    .nav-highlight {
        position: absolute;
        bottom: -8px;
        left: 0;
        height: 3px;
        background: #4CAF50;
        border-radius: 2px;
        transition: all 0.3s ease;
        z-index: 1;
    }

    @media (max-width: 768px) {
        .secondary-nav {
            padding: 10px 15px;
        }

        .secondary-nav-menu {
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .secondary-nav-menu a {
            font-size: 12px;
            padding: 6px 12px;
        }
    }

    .user-menu {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .btn-upload {
        background: #ff6600;
        color: #fff;
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        font-size: 20px;
        transition: all 0.3s;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }

    .btn-upload:hover {
        background: #ff8533;
        transform: scale(1.05);
        box-shadow: 0 3px 8px rgba(0,0,0,0.3);
    }

    .btn-login {
        background: #64b5f6;
        color: #fff;
        padding: 10px 20px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-login:hover {
        background: #42a5f5;
        transform: translateY(-2px);
        box-shadow: 0 3px 8px rgba(0,0,0,0.2);
    }

    /* Mobile Menu Toggle */
    .mobile-toggle {
        display: none;
        background: none;
        border: none;
        color: #fff;
        font-size: 24px;
        cursor: pointer;
    }

    /* Mobile user icon */
    .mobile-user-icon {
        display: none;
    }

    /* Mobile Responsive - Like Image Design */
    @media (max-width: 768px) {
        .header-container {
            display: grid;
            grid-template-columns: auto 1fr auto;
            grid-template-areas: 
                "hamburger logo user"
                "search search search";
            gap: 15px;
            padding: 12px 15px;
            align-items: center;
        }

        .mobile-toggle {
            grid-area: hamburger;
            display: block;
            background: none;
            border: none;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo {
            grid-area: logo;
            justify-self: center;
            font-size: 18px;
            gap: 8px;
        }

        .logo img {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }

        .logo .fa-music {
            display: none;
        }

        .logo span {
            display: <?php echo ($show_site_name == '1' && !empty($site_name)) ? 'inline' : 'none'; ?>;
        }

        .search-container {
            grid-area: search;
            order: 2;
            width: 100%;
            max-width: 100%;
            margin: 0;
        }

        .search-box {
            padding: 10px 40px 10px 15px;
            font-size: 14px;
        }

        .user-menu {
            grid-area: user;
            justify-self: end;
            gap: 8px;
        }

        .user-menu a {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            text-decoration: none;
            font-size: 18px;
        }

        .user-menu .btn-upload {
            width: 36px;
            height: 36px;
            font-size: 16px;
        }

        .user-menu .btn-login {
            width: 36px;
            height: 36px;
            padding: 0;
            border-radius: 50%;
            font-size: 16px;
        }

        .user-menu .btn-login i {
            margin: 0;
        }

        .user-menu .btn-login span {
            display: none;
        }

        .nav-menu {
            position: fixed;
            top: 60px;
            left: -100%;
            width: 80%;
            max-width: 300px;
            height: calc(100vh - 60px);
            background: #1e4d72;
            flex-direction: column;
            align-items: flex-start;
            padding: 20px;
            gap: 20px;
            transition: left 0.3s ease;
            box-shadow: 2px 0 5px rgba(0,0,0,0.2);
            z-index: 999;
        }

        .nav-menu.active {
            left: 0;
        }
    }
</style>

<header class="main-header">
    <div class="header-container">
        <button class="mobile-toggle" id="mobileToggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <a href="index.php" class="logo">
            <?php 
            // Always try to show logo if available
            $logoDisplayed = false;
            
            // Show logo if it exists and is not empty (don't require 'http' check - logo is already a full URL)
            if (!empty($site_logo) && trim($site_logo) !== ''):
                $logoDisplayed = true;
            ?>
                <img src="<?php echo htmlspecialchars($site_logo); ?>" 
                     alt="<?php echo htmlspecialchars($site_name ?: (defined('SITE_NAME') ? SITE_NAME : '')); ?>" 
                     style="width: 32px; height: 32px; object-fit: contain; display: block;"
                     onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='block'; this.nextElementSibling.style.display='flex';">
            <?php endif; ?>
            <?php if (!$logoDisplayed): ?>
                <i class="fas fa-music"></i>
            <?php else: ?>
                <i class="fas fa-music" style="display: none;"></i>
            <?php endif; ?>
            <?php if ($show_site_name == '1' && !empty($site_name)): ?>
                <span><?php echo htmlspecialchars($site_name); ?></span>
            <?php endif; ?>
        </a>

        <div class="search-container">
            <input type="text" class="search-box" id="searchBox" placeholder="Search songs, artists, albums...">
            <i class="fas fa-search search-icon"></i>
            <div class="search-results" id="searchResults">
                <div class="search-loader" id="searchLoader">
                    <i class="fas fa-spinner fa-spin"></i> Searching...
                </div>
            </div>
        </div>

        <nav>
            <ul class="nav-menu" id="navMenu">
                <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="songs.php"><i class="fas fa-music"></i> Latest Music</a></li>
                <li><a href="news.php"><i class="fas fa-newspaper"></i> News</a></li>
                <li><a href="artists.php"><i class="fas fa-users"></i> Artists</a></li>
                <li><a href="top-100.php"><i class="fas fa-trophy"></i> 100</a></li>
                <li><a href="polls.php"><i class="fas fa-poll"></i> Opinion poll</a></li>
                <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
            </ul>
        </nav>

        <div class="user-menu">
            <?php if ($isLoggedIn): ?>
                <a href="artist-profile-mobile.php" class="user-icon" title="Artist Profile" style="background: rgba(255, 255, 255, 0.1); width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-user"></i>
                </a>
            <?php else: ?>
                <a href="login.php" class="user-icon" title="Login" style="background: rgba(255, 255, 255, 0.1); width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-user"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Secondary Navigation Menu - Under Search Bar -->
<nav class="secondary-nav">
    <div class="secondary-nav-container">
        <ul class="secondary-nav-menu">
            <li>
                <a href="index.php" class="secondary-nav-link" data-item="home">HOME</a>
                <div class="nav-highlight" id="navHighlight"></div>
            </li>
            <li>
                <a href="songs.php" class="secondary-nav-link" data-item="music">MUSIC</a>
            </li>
            <li>
                <a href="artists.php" class="secondary-nav-link" data-item="artist">ARTIST</a>
            </li>
            <li>
                <a href="news.php" class="secondary-nav-link" data-item="news">NEWS</a>
            </li>
        </ul>
    </div>
</nav>

<script>
// Update active state for secondary nav
(function() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    const navLinks = document.querySelectorAll('.secondary-nav-link');
    const navHighlight = document.getElementById('navHighlight');
    
    let activeLink = null;
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage || 
            (currentPage === '' && href === 'index.php') ||
            (currentPage === 'index.php' && href === 'index.php')) {
            link.classList.add('active');
            activeLink = link;
        }
    });
    
    // Set highlight position for active link
    if (activeLink && navHighlight) {
        const activeItem = activeLink.parentElement;
        const offsetLeft = activeItem.offsetLeft;
        const width = activeItem.offsetWidth;
        
        navHighlight.style.left = offsetLeft + 'px';
        navHighlight.style.width = width + 'px';
    }
    
    // Update highlight on hover (optional)
    navLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            const item = this.parentElement;
            const offsetLeft = item.offsetLeft;
            const width = item.offsetWidth;
            
            navHighlight.style.left = offsetLeft + 'px';
            navHighlight.style.width = width + 'px';
        });
    });
    
    // Reset to active link on mouse leave
    const secondaryNav = document.querySelector('.secondary-nav-menu');
    if (secondaryNav && activeLink) {
        secondaryNav.addEventListener('mouseleave', function() {
            const activeItem = activeLink.parentElement;
            const offsetLeft = activeItem.offsetLeft;
            const width = activeItem.offsetWidth;
            
            navHighlight.style.left = offsetLeft + 'px';
            navHighlight.style.width = width + 'px';
        });
    }
})();
</script>

<script>
// Mobile menu toggle
document.getElementById('mobileToggle')?.addEventListener('click', function() {
    document.getElementById('navMenu').classList.toggle('active');
});

// Close menu when clicking outside
document.addEventListener('click', function(event) {
    const navMenu = document.getElementById('navMenu');
    const mobileToggle = document.getElementById('mobileToggle');
    
    if (navMenu && !navMenu.contains(event.target) && !mobileToggle.contains(event.target)) {
        navMenu.classList.remove('active');
    }
});

// Set active menu item based on current page
const currentPage = window.location.pathname.split('/').pop() || 'index.php';
document.querySelectorAll('.nav-menu a').forEach(link => {
    if (link.getAttribute('href') === currentPage) {
        link.classList.add('active');
    }
});

// Live Search Functionality
let searchTimeout;
const searchBox = document.getElementById('searchBox');
const searchResults = document.getElementById('searchResults');
const searchLoader = document.getElementById('searchLoader');

searchBox?.addEventListener('input', function() {
    const query = this.value.trim();
    
    // Clear previous timeout
    clearTimeout(searchTimeout);
    
    if (query.length < 2) {
        searchResults.classList.remove('active');
        return;
    }
    
    // Show loader
    searchLoader.classList.add('active');
    searchResults.classList.add('active');
    
    // Debounce search
    searchTimeout = setTimeout(() => {
        // Get base URL dynamically - works with ngrok, IP, and localhost
        // Try multiple methods to construct the API URL
        let basePath = window.location.pathname;
        
        // Remove trailing filename if present
        if (basePath.endsWith('.php') || basePath.split('/').pop().includes('.')) {
            basePath = basePath.substring(0, basePath.lastIndexOf('/') + 1);
        } else if (!basePath.endsWith('/')) {
            basePath += '/';
        }
        
        // Ensure basePath ends with /
        if (!basePath.endsWith('/')) {
            basePath += '/';
        }
        
        // Construct API URL using base path
        let apiUrl = window.location.origin + basePath + `api/search.php?q=${encodeURIComponent(query)}`;
        
        // Try the API call
        fetch(apiUrl, {
            method: 'GET',
            mode: 'cors',
            cache: 'no-cache',
            credentials: 'same-origin'
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                searchLoader.classList.remove('active');
                displaySearchResults(data);
            })
            .catch(error => {
                console.error('Search error:', error);
                
                // Try alternative paths (without hardcoded /music/)
                const alternativePaths = [
                    basePath + 'api/search.php',
                    '/api/search.php',
                    'api/search.php',
                    window.location.origin + '/api/search.php'
                ];
                
                let attempts = 0;
                const maxAttempts = alternativePaths.length;
                
                function tryAlternativePath(index) {
                    if (index >= maxAttempts) {
                        searchLoader.classList.remove('active');
                        searchResults.innerHTML = '<div class="no-results"><i class="fas fa-search"></i><br>Search unavailable. Please try again.</div>';
                        return;
                    }
                    
                    const altUrl = window.location.origin + alternativePaths[index] + `?q=${encodeURIComponent(query)}`;
                    
                    fetch(altUrl, {
                        method: 'GET',
                        mode: 'cors',
                        cache: 'no-cache'
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            searchLoader.classList.remove('active');
                            displaySearchResults(data);
                        })
                        .catch(err => {
                            console.error('Alternative path failed:', altUrl, err);
                            tryAlternativePath(index + 1);
                        });
                }
                
                tryAlternativePath(0);
            });
    }, 300);
});

function displaySearchResults(data) {
    if (!data) {
        searchLoader.classList.remove('active');
        searchResults.innerHTML = '<div class="no-results"><i class="fas fa-search"></i><br>No results found</div>';
        return;
    }
    
    // Check if there's an error message
    if (data.error && typeof data.error === 'string' && data.error.trim() !== '') {
        searchLoader.classList.remove('active');
        searchResults.innerHTML = '<div class="no-results">' + data.error + '</div>';
        return;
    }
    
    const hasResults = (data.songs && data.songs.length > 0) || 
                       (data.artists && data.artists.length > 0) || 
                       (data.news && data.news.length > 0);
    
    if (!hasResults) {
        searchResults.innerHTML = '<div class="no-results"><i class="fas fa-search"></i><br>No results found</div>';
        return;
    }
    
    let html = '';
    
    // Display songs
    if (data.songs && data.songs.length > 0) {
        data.songs.forEach(song => {
            const coverArt = song.cover_art || 'assets/images/default-cover.png';
            html += `
                <a href="/song/${song.slug || 'song-' + song.id}" class="search-result-item">
                    <img src="${coverArt}" alt="${song.title}" class="result-image" onerror="this.src='assets/images/default-cover.png'">
                    <div class="result-info">
                        <div class="result-title">${song.title}</div>
                        <div class="result-meta">${song.artist}</div>
                    </div>
                    <span class="result-type">Song</span>
                </a>
            `;
        });
    }
    
    // Display artists
    if (data.artists && data.artists.length > 0) {
        data.artists.forEach(artist => {
            const avatarUrl = artist.avatar || 'assets/images/default-avatar.svg';
            // Create slug format: convert spaces to hyphens (same format as used elsewhere)
            const artistSlug = artist.name.toLowerCase()
                .replace(/[^a-z0-9\s]+/gi, '')
                .replace(/\s+/g, '-')
                .replace(/^-+|-+$/g, '');
            // Use slug format without encodeURIComponent for cleaner URLs
            const profileUrl = `/artist/${artistSlug}`;
            html += `
                <a href="${profileUrl}" class="search-result-item">
                    <img src="${avatarUrl}" alt="${artist.name}" class="result-image" style="border-radius: 50%; width: 50px; height: 50px; object-fit: cover;" onerror="this.src='assets/images/default-avatar.svg'">
                    <div class="result-info">
                        <div class="result-title">${artist.name}</div>
                    </div>
                    <span class="result-type">Artist</span>
                </a>
            `;
        });
    }
    
    // Display news/posts
    if (data.news && data.news.length > 0) {
        data.news.forEach(news => {
            const newsImage = news.image || 'assets/images/default-cover.png';
            const basePath = '<?php echo defined("BASE_PATH") ? BASE_PATH : "/"; ?>';
            const newsUrl = basePath + `news/${encodeURIComponent(news.slug || '')}`;
            const newsDate = news.created_at ? new Date(news.created_at).toLocaleDateString() : '';
            html += `
                <a href="${newsUrl}" class="search-result-item">
                    <img src="${newsImage}" alt="${news.title}" class="result-image" onerror="this.src='assets/images/default-cover.png'">
                    <div class="result-info">
                        <div class="result-title">${news.title}</div>
                        <div class="result-meta">${news.category || 'News'}${newsDate ? ' • ' + newsDate : ''}</div>
                    </div>
                    <span class="result-type">News</span>
                </a>
            `;
        });
    }
    
    searchResults.innerHTML = html;
    searchLoader.classList.remove('active');
}

// Close search results when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.search-container')) {
        searchResults.classList.remove('active');
    }
});

// Prevent closing when clicking inside search results
searchResults?.addEventListener('click', function(event) {
    event.stopPropagation();
});
</script>

<?php
// Check if disable copy protection is enabled
$disable_copy_enabled = true; // Default to enabled
try {
    if (file_exists(__DIR__ . '/../config/database.php')) {
        require_once __DIR__ . '/../config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        if ($conn) {
            $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'disable_copy'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $disable_copy_setting = $result['setting_value'] ?? '1';
            $disable_copy_enabled = ($disable_copy_setting === '1' || $disable_copy_setting === 'true');
        }
    }
} catch (Exception $e) {
    // Default to enabled if check fails
    $disable_copy_enabled = true;
}

// Only apply protection if enabled
if ($disable_copy_enabled):
?>
<!-- Disable Copy/Right Click Protection -->
<script>
// Disable right-click context menu
document.addEventListener('contextmenu', function(e) {
    e.preventDefault();
    return false;
});

// Disable text selection
document.addEventListener('selectstart', function(e) {
    e.preventDefault();
    return false;
});

// Disable copy (Ctrl+C, Ctrl+A, Ctrl+X)
document.addEventListener('keydown', function(e) {
    // Disable Ctrl+C (Copy)
    if (e.ctrlKey && e.keyCode === 67) {
        e.preventDefault();
        return false;
    }
    // Disable Ctrl+A (Select All)
    if (e.ctrlKey && e.keyCode === 65) {
        e.preventDefault();
        return false;
    }
    // Disable Ctrl+X (Cut)
    if (e.ctrlKey && e.keyCode === 88) {
        e.preventDefault();
        return false;
    }
    // Disable Ctrl+S (Save)
    if (e.ctrlKey && e.keyCode === 83) {
        e.preventDefault();
        return false;
    }
    // Disable Ctrl+P (Print)
    if (e.ctrlKey && e.keyCode === 80) {
        e.preventDefault();
        return false;
    }
    // Disable F12 (Developer Tools)
    if (e.keyCode === 123) {
        e.preventDefault();
        return false;
    }
    // Disable Ctrl+Shift+I (Developer Tools)
    if (e.ctrlKey && e.shiftKey && e.keyCode === 73) {
        e.preventDefault();
        return false;
    }
    // Disable Ctrl+Shift+C (Inspect Element)
    if (e.ctrlKey && e.shiftKey && e.keyCode === 67) {
        e.preventDefault();
        return false;
    }
    // Disable Ctrl+Shift+J (Console)
    if (e.ctrlKey && e.shiftKey && e.keyCode === 74) {
        e.preventDefault();
        return false;
    }
});

// Disable drag and drop
document.addEventListener('dragstart', function(e) {
    e.preventDefault();
    return false;
});

// Clear clipboard on copy attempt
document.addEventListener('copy', function(e) {
    e.clipboardData.setData('text/plain', '');
    e.preventDefault();
    return false;
});

// Disable image dragging
document.addEventListener('DOMContentLoaded', function() {
    var images = document.querySelectorAll('img');
    images.forEach(function(img) {
        img.addEventListener('dragstart', function(e) {
            e.preventDefault();
            return false;
        });
    });
});
</script>

<style>
/* Disable text selection */
body {
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    -webkit-touch-callout: none;
}

/* Allow selection in input fields and textareas */
input, textarea, [contenteditable="true"] {
    -webkit-user-select: text;
    -moz-user-select: text;
    -ms-user-select: text;
    user-select: text;
}
</style>
<?php endif; ?>

