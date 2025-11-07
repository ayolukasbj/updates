<?php
// Enable error reporting for debugging
$debug_mode = defined('DEBUG_MODE') && DEBUG_MODE === true;
if ($debug_mode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Start output buffering to catch errors
ob_start();

try {
    // Load config with error handling
    if (!file_exists('config/config.php')) {
        throw new Exception('Config file not found');
    }
    require_once 'config/config.php';
    
    // Verify config loaded
    if (!defined('SITE_NAME')) {
        throw new Exception('Config file loaded but constants not defined');
    }
    
    // Load database with error handling
    if (!file_exists('config/database.php')) {
        throw new Exception('Database config file not found');
    }
    require_once 'config/database.php';
    
    // Prevent browser caching
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    // Redirect if not logged in
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        if (function_exists('redirect')) {
            redirect('login.php');
        } else {
            header('Location: login.php');
            exit;
        }
    }
} catch (Exception $e) {
    ob_end_clean();
    error_log("Error in artist-profile-mobile.php: " . $e->getMessage());
    http_response_code(500);
    die('Error loading page: ' . htmlspecialchars($e->getMessage()) . '. Please check error logs.');
}

$user_id = get_user_id();

// Get user/artist data from database
$db = new Database();
$conn = $db->getConnection();

// Get categories from news_categories table
$news_categories = [];
try {
    $catStmt = $conn->query("SELECT name FROM news_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
    $news_categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // If news_categories table doesn't exist, use fallback categories
    $news_categories = ['Entertainment', 'National News', 'Exclusive', 'Hot', 'Politics', 'Shocking', 'Celebrity Gossip', 'Just in', 'Lifestyle and Events'];
}

$update_message = '';
$update_success = false;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $username = trim($_POST['username']);
        $bio = trim($_POST['bio'] ?? '');
        $facebook = trim($_POST['facebook'] ?? '');
        $twitter = trim($_POST['twitter'] ?? '');
        $instagram = trim($_POST['instagram'] ?? '');
        $youtube = trim($_POST['youtube'] ?? '');
        
        // Handle avatar upload
        $avatar_path = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/avatars/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('avatar_' . $user_id . '_') . '.' . $file_ext;
            $avatar_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $avatar_path)) {
                // Avatar uploaded successfully
            } else {
                $avatar_path = null;
            }
        }
        
        // Log the data we're trying to save
        error_log("Attempting to update profile for user_id: $user_id");
        error_log("Data: username=$username, bio=$bio, facebook=$facebook, twitter=$twitter, instagram=$instagram, youtube=$youtube");
        
        // Update user data with explicit error checking
        if ($avatar_path) {
            $stmt = $conn->prepare("
                UPDATE users 
                SET username = ?, bio = ?, facebook = ?, twitter = ?, instagram = ?, youtube = ?, avatar = ?
                WHERE id = ?
            ");
            $params = [$username, $bio, $facebook, $twitter, $instagram, $youtube, $avatar_path, $user_id];
            error_log("Executing UPDATE with avatar. Params: " . print_r($params, true));
            $result = $stmt->execute($params);
        } else {
            $stmt = $conn->prepare("
                UPDATE users 
                SET username = ?, bio = ?, facebook = ?, twitter = ?, instagram = ?, youtube = ?
                WHERE id = ?
            ");
            $params = [$username, $bio, $facebook, $twitter, $instagram, $youtube, $user_id];
            error_log("Executing UPDATE without avatar. Params: " . print_r($params, true));
            $result = $stmt->execute($params);
        }
        
        // Check if update was successful
        if ($result) {
            $rowCount = $stmt->rowCount();
            error_log("UPDATE successful. Rows affected: $rowCount");
            
            // Force commit to database (if not autocommit)
            if ($conn->inTransaction()) {
                $conn->commit();
                error_log("Transaction committed");
            }
            
            // Verify the data was actually saved
            $verify_stmt = $conn->prepare("SELECT username, bio, facebook, twitter, instagram, youtube FROM users WHERE id = ?");
            $verify_stmt->execute([$user_id]);
            $saved_data = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Verification query result: " . print_r($saved_data, true));
            
            // Update session username if changed
            $_SESSION['username'] = $username;
            
            // Small delay to ensure database write is complete
            usleep(100000); // 0.1 seconds
            
            // Clear opcode cache if available
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            // Redirect to edit tab with success message and strong cache buster
            $redirect_url = 'artist-profile-mobile.php?tab=edit&updated=1&_=' . uniqid() . '&t=' . microtime(true);
            error_log("Redirecting to: $redirect_url");
            header('Location: ' . $redirect_url);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            exit;
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("UPDATE failed. Error info: " . print_r($errorInfo, true));
            throw new Exception('Failed to update profile - database error: ' . implode(', ', $errorInfo));
        }
        
    } catch (Exception $e) {
        $update_message = 'Error updating profile: ' . $e->getMessage();
        $update_success = false;
        error_log('Profile Update Error: ' . $e->getMessage());
        error_log('Profile Update Data: ' . print_r($_POST, true));
    }
}

// Log the update attempt for debugging
if (isset($_POST['update_profile'])) {
    error_log('Profile update POST data: ' . print_r($_POST, true));
    error_log('Files uploaded: ' . print_r($_FILES, true));
}

// Check if just updated
if (isset($_GET['updated'])) {
    $update_success = true;
    $update_message = 'Profile updated successfully!';
}

// Check if just uploaded or updated a song
$uploaded_message = '';
if (isset($_GET['uploaded']) && isset($_GET['tab']) && $_GET['tab'] === 'music') {
    $uploaded_message = 'Song uploaded successfully!';
} elseif (isset($_GET['updated']) && isset($_GET['tab']) && $_GET['tab'] === 'music') {
    $uploaded_message = 'Song updated successfully!';
}

// Get logo from settings
$site_logo = '';
try {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_logo'");
    $stmt->execute();
    $logo_result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($logo_result && !empty($logo_result['setting_value'])) {
        $site_logo = $logo_result['setting_value'];
        // Normalize logo path (same as header.php)
        $normalizedLogo = str_replace('\\', '/', $site_logo);
        $normalizedLogo = preg_replace('#^\.\./#', '', $normalizedLogo);
        $normalizedLogo = str_replace('../', '', $normalizedLogo);
        
        // Build full URL if needed
        if (!empty($normalizedLogo) && strpos($normalizedLogo, 'http') !== 0) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base_path = defined('BASE_PATH') ? BASE_PATH : '/';
            $baseUrl = $protocol . $host . $base_path;
            $site_logo = $baseUrl . ltrim($normalizedLogo, '/');
        } else {
            $site_logo = $normalizedLogo;
        }
    }
} catch (Exception $e) {
    $site_logo = '';
}

try {
    // Updated to include collaboration stats - count songs where user uploaded OR collaborated
    // Using UNION approach to avoid double counting
    $stmt = $conn->prepare("
        SELECT u.*,
               COALESCE((
                   SELECT COUNT(DISTINCT s.id)
                   FROM songs s
                   WHERE s.uploaded_by = u.id
                      OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
               ), 0) as total_songs,
               COALESCE((
                   SELECT SUM(s.plays)
                   FROM songs s
                   WHERE s.uploaded_by = u.id
                      OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
               ), 0) as total_plays,
               COALESCE((
                   SELECT SUM(s.downloads)
                   FROM songs s
                   WHERE s.uploaded_by = u.id
                      OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
               ), 0) as total_downloads
        FROM users u
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log('User not found for ID: ' . $user_id);
        redirect('login.php');
    }
    
    // Log user data after fetch for debugging
    error_log('User data fetched: username=' . ($user['username'] ?? 'NULL') . ', bio=' . ($user['bio'] ?? 'NULL'));
} catch (Exception $e) {
    error_log('Error fetching user data: ' . $e->getMessage());
    // Fallback query without songs if join fails
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        error_log('User not found in fallback query for ID: ' . $user_id);
        redirect('login.php');
    }
    
    $user['total_songs'] = 0;
    $user['total_downloads'] = 0;
    $user['total_plays'] = 0;
}

// Calculate ranking based on total downloads compared to ALL users in database
// Rank 1 = highest downloads, Rank 2 = second highest, etc.
try {
    // Get total number of ALL users in database
    $stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users");
    $stmt->execute();
    $total_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_artists = $total_data['total_users'] ?? 0;
    
    // If user has no songs, rank them last
    if ($user['total_songs'] == 0 || $user['total_downloads'] == 0) {
        $ranking = $total_artists > 0 ? $total_artists : 100;
    } else {
        // Calculate ranking based on downloads (1 = highest)
        // Count how many users have MORE total downloads than this user
        $stmt = $conn->prepare("
            SELECT COUNT(*) as higher_ranked
            FROM (
                SELECT u.id, COALESCE(SUM(s.downloads), 0) as user_total_downloads
                FROM users u
                LEFT JOIN songs s ON s.uploaded_by = u.id
                WHERE u.id != ?
                GROUP BY u.id
                HAVING user_total_downloads > ?
            ) as ranked_users
        ");
        $stmt->execute([$user_id, $user['total_downloads']]);
        $ranking_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $ranking = ($ranking_data['higher_ranked'] ?? 0) + 1; // Add 1 to get rank position
    }
} catch (Exception $e) {
    $ranking = 100;
    $total_artists = 0;
}

// Check if user is active
$is_active = $user['is_active'] ?? 1;

// Get artist's songs
try {
    $stmt = $conn->prepare("
        SELECT DISTINCT s.*, 
               COALESCE(s.plays, 0) as plays,
               COALESCE(s.downloads, 0) as downloads
        FROM songs s
        LEFT JOIN song_collaborators sc ON sc.song_id = s.id
        WHERE s.uploaded_by = ? OR sc.user_id = ?
        ORDER BY s.id DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $user_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Fetched " . count($user_songs) . " songs for user_id: $user_id");
    if (count($user_songs) > 0) {
        error_log("First song: " . print_r($user_songs[0], true));
    } else {
        error_log("No songs found. Checking database...");
        // Debug query to see all songs
        $debug_stmt = $conn->query("SELECT id, title, uploaded_by FROM songs LIMIT 5");
        $debug_songs = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("All songs in DB: " . print_r($debug_songs, true));
    }
} catch (Exception $e) {
    error_log("Error fetching user songs: " . $e->getMessage());
    error_log("SQL Error Code: " . $e->getCode());
    $user_songs = [];
}

// Handle news submission
$news_submission_message = '';
$news_submission_success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_news'])) {
    try {
        $news_title = trim($_POST['news_title'] ?? '');
        $news_content = trim($_POST['news_content'] ?? '');
        $news_category = trim($_POST['news_category'] ?? 'Entertainment');
        
        if (empty($news_title) || empty($news_content)) {
            $news_submission_message = 'Title and content are required';
            $news_submission_success = false;
        } elseif (!isset($_FILES['news_image']) || $_FILES['news_image']['error'] !== UPLOAD_ERR_OK) {
            $news_submission_message = 'Image is required';
            $news_submission_success = false;
        } else {
            // Generate slug from title
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $news_title), '-'));
            
            // Handle image upload
            $image_path = null;
            if (isset($_FILES['news_image']) && $_FILES['news_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/news/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_ext = pathinfo($_FILES['news_image']['name'], PATHINFO_EXTENSION);
                $file_name = uniqid() . '_news.' . $file_ext;
                $image_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['news_image']['tmp_name'], $image_path)) {
                    // Image uploaded successfully
                } else {
                    $image_path = null;
                }
            }
            
            // Generate excerpt from content (first 150 chars)
            $excerpt = substr(strip_tags($news_content), 0, 150);
            
            // Check if news table exists first
            $news_table_exists = false;
            try {
                $table_check = $conn->query("SHOW TABLES LIKE 'news'");
                $news_table_exists = $table_check->rowCount() > 0;
            } catch (Exception $e) {
                $news_table_exists = false;
            }
            
            if (!$news_table_exists) {
                $news_submission_message = 'News table does not exist. Please contact administrator to set up the database.';
                $news_submission_success = false;
            } else {
                // Insert news with is_published = 0 (requires admin approval)
                // Store submitter in submitted_by, author_id will be set by admin when publishing
                // Check if submitted_by column exists
                $has_submitted_by = false;
                try {
                    $columns_check = $conn->query("SHOW COLUMNS FROM news LIKE 'submitted_by'");
                    $has_submitted_by = $columns_check->rowCount() > 0;
                } catch (Exception $e) {
                    $has_submitted_by = false;
                }
                
                if ($has_submitted_by) {
                $stmt = $conn->prepare("
                    INSERT INTO news (title, slug, category, content, excerpt, image, submitted_by, author_id, is_published, featured, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NULL, 0, 0, NOW())
                ");
                $result = $stmt->execute([
                    $news_title,
                    $slug,
                    $news_category,
                    $news_content,
                    $excerpt,
                    $image_path,
                    $user_id
                ]);
            } else {
                // If submitted_by doesn't exist, store in author_id temporarily (will be changed by admin)
                $stmt = $conn->prepare("
                    INSERT INTO news (title, slug, category, content, excerpt, image, author_id, is_published, featured, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NULL, 0, 0, NOW())
                ");
                $result = $stmt->execute([
                    $news_title,
                    $slug,
                    $news_category,
                    $news_content,
                    $excerpt,
                    $image_path
                ]);
                }
                
                if ($result) {
                    $news_submission_message = 'News submitted successfully! It will be reviewed by admin before being published.';
                    $news_submission_success = true;
                } else {
                    $news_submission_message = 'Failed to submit news. Please try again.';
                    $news_submission_success = false;
                }
            }
        }
    } catch (Exception $e) {
        error_log("News submission error: " . $e->getMessage());
        $news_submission_message = 'Error submitting news: ' . $e->getMessage();
        $news_submission_success = false;
    }
}

// Get news where this artist is tagged OR submitted by this artist
$latest_news = [];
$user_submitted_news = [];

try {
    // Check if submitted_by column exists
    $has_submitted_by = false;
    try {
        $columns_check = $conn->query("SHOW COLUMNS FROM news LIKE 'submitted_by'");
        $has_submitted_by = $columns_check->rowCount() > 0;
    } catch (Exception $e) {
        $has_submitted_by = false;
    }
    
    // Get news submitted by this user (both published and pending)
    if ($has_submitted_by) {
        // Use submitted_by column if it exists
        $stmt = $conn->prepare("
            SELECT *, 
                   CASE WHEN is_published = 1 THEN 'published' ELSE 'pending' END as status
            FROM news 
            WHERE submitted_by = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $user_submitted_news = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Fallback: check author_id if submitted_by doesn't exist
        $stmt = $conn->prepare("
            SELECT *, 
                   CASE WHEN is_published = 1 THEN 'published' ELSE 'pending' END as status
            FROM news 
            WHERE author_id = ? AND is_published = 0
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $user_submitted_news = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get news where this artist is co-author or mentioned in title/content
    $artist_name = $user['username'] ?? '';
    $search_term = '%' . $artist_name . '%';
    $newsStmt = $conn->prepare("
        SELECT n.*, u.username as author_name
        FROM news n
        LEFT JOIN users u ON n.author_id = u.id
        WHERE n.is_published = 1
        AND (n.author_id = ? OR n.title LIKE ? OR n.content LIKE ? OR (n.co_author IS NOT NULL AND n.co_author LIKE ?))
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $newsStmt->execute([$user_id, $search_term, $search_term, $search_term]);
    $latest_news = $newsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // News is now database-only - no JSON fallback
} catch (Exception $e) {
    error_log("Error fetching news: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?> - Artist Profile | <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        /* Header */
        .header {
            background: #4a4a4a;
            color: white;
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .site-logo {
            width: 35px;
            height: 35px;
            background: #ff6600;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: bold;
        }
        
        .site-name {
            font-size: 14px;
            font-weight: 600;
        }
        
        .header-right {
            display: flex;
            gap: 15px;
            font-size: 13px;
        }
        
        .header-right a {
            color: white;
            text-decoration: none;
        }
        
        /* Navigation */
        .nav-tabs {
            display: flex;
            background: #5a5a5a;
            overflow-x: auto;
        }
        
        .nav-tab {
            flex: 1;
            text-align: center;
            padding: 12px 10px;
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
        }
        
        .nav-tab.active {
            background: #4a4a4a;
            border-bottom-color: #ff6600;
        }
        
        /* Profile Container */
        .profile-container {
            padding: 20px 15px;
        }
        
        /* Edit Button */
        .edit-btn {
            position: absolute;
            top: 20px;
            right: 15px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: #333;
            text-decoration: none;
        }
        
        /* Avatar Section */
        .avatar-section {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
        }
        
        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #ddd;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: #999;
            overflow: hidden;
        }
        
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .artist-name {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: capitalize;
        }
        
        /* Active Toggle */
        .active-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .active-toggle span {
            font-size: 14px;
            font-weight: 500;
        }
        
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 26px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #4CAF50;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        
        .public-page-link {
            color: #ff6600;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 25px;
        }
        
        .btn {
            padding: 15px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            display: block;
            cursor: pointer;
        }
        
        .btn-upload {
            background: #ff6600;
            color: white;
            text-decoration: none;
        }
        
        .btn-boost {
            background: #2196F3;
            color: white;
            text-decoration: none;
        }
        
        /* Bio Section */
        .bio-section {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .bio-header {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        
        .bio-text {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }
        
        .bio-placeholder {
            color: #999;
            font-style: italic;
        }
        
        /* Owner Section */
        .owner-section {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .owner-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .owner-name {
            font-size: 16px;
            font-weight: 600;
            color: #2196F3;
        }
        
        /* News Section */
        .news-section {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        /* Stats Section */
        .stats-section {
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .news-header {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .news-item {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .news-item:last-child {
            border-bottom: none;
        }
        
        .news-title {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            text-decoration: none;
            display: block;
        }
        
        .news-title:hover {
            color: #ff6600;
        }
        
        .news-excerpt {
            font-size: 13px;
            color: #666;
            line-height: 1.4;
            margin-bottom: 5px;
        }
        
        .news-date {
            font-size: 12px;
            color: #999;
        }
        
        .no-news {
            text-align: center;
            padding: 20px;
            color: #999;
            font-style: italic;
        }
        
        /* Tab Content */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Music Section */
        .music-section {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .music-header {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .song-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .song-item:last-child {
            border-bottom: none;
        }
        
        .song-cover {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background: #ddd;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .song-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .song-cover i {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #999;
        }
        
        .song-info {
            flex: 1;
        }
        
        .song-info-title {
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 3px;
            color: #333;
        }
        
        .song-info-stats {
            font-size: 12px;
            color: #999;
            display: flex;
            gap: 15px;
        }
        
        .no-songs {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .no-songs i {
            font-size: 50px;
            margin-bottom: 15px;
            display: block;
            color: #ddd;
        }
        
        /* Edit Section */
        .edit-section {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .edit-header {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-left">
            <div class="site-logo" style="background: <?php echo !empty($site_logo) ? 'transparent' : '#ff6600'; ?>; border-radius: <?php echo !empty($site_logo) ? '0' : '50%'; ?>;">
                <?php if (!empty($site_logo)): ?>
                    <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 4px;" onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-music\'></i>'; this.parentElement.style.background='#ff6600'; this.parentElement.style.borderRadius='50%';">
                <?php else: ?>
                    <i class="fas fa-music"></i>
                <?php endif; ?>
            </div>
            <div class="site-name"><?php echo SITE_NAME; ?></div>
        </div>
        <div class="header-right">
            <a href="profile.php">Account</a>
            <span>|</span>
            <a href="logout.php">Log out</a>
        </div>
    </div>
    
    <!-- Navigation Tabs -->
    <div class="nav-tabs">
        <a href="javascript:void(0)" class="nav-tab active" onclick="switchTab('profile'); return false;">PROFILE</a>
        <a href="javascript:void(0)" class="nav-tab" onclick="switchTab('music'); return false;">MUSIC</a>
        <a href="javascript:void(0)" class="nav-tab" onclick="switchTab('lyrics'); return false;">LYRICS</a>
        <a href="javascript:void(0)" class="nav-tab" onclick="switchTab('albums'); return false;">ALBUMS</a>
        <a href="javascript:void(0)" class="nav-tab" onclick="switchTab('edit'); return false;">EDIT</a>
        <a href="javascript:void(0)" class="nav-tab" onclick="switchTab('news'); return false;">NEWS</a>
        <a href="javascript:void(0)" class="nav-tab" onclick="switchTab('stats'); return false;">STATS</a>
    </div>
    
    <!-- Profile Container -->
    <div class="profile-container">
    
    <!-- PROFILE TAB -->
    <div id="profile-tab" class="tab-content active">
        <!-- Avatar Section -->
        <div class="avatar-section">
            <div class="avatar">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>">
                <?php else: ?>
                    <i class="fas fa-headphones"></i>
                <?php endif; ?>
            </div>
            
            <h1 class="artist-name"><?php echo htmlspecialchars(ucwords(strtolower($user['username']))); ?></h1>
            
            <div class="active-toggle">
                <span>Active</span>
                <label class="toggle-switch">
                    <input type="checkbox" <?php echo $is_active ? 'checked' : ''; ?> onchange="toggleActiveStatus(this)">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            
            <a href="artist-profile.php?id=<?php echo $user_id; ?>" class="public-page-link" target="_blank">
                Your public page
            </a>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($user['total_downloads']); ?></div>
                <div class="stat-label">Total downloads</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($ranking); ?></div>
                <div class="stat-label">Ranking</div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="upload.php" class="btn btn-upload">Upload song</a>
            <a href="boost-your-music.php" class="btn btn-boost">Boost your music</a>
        </div>
        
        <!-- Bio Section -->
        <div class="bio-section">
            <div class="bio-header">Bio:</div>
            <div class="bio-text">
                <?php if (!empty($user['bio'])): ?>
                    <?php echo nl2br(htmlspecialchars($user['bio'])); ?>
                <?php else: ?>
                    <span class="bio-placeholder">No bio added yet</span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Owner Section -->
        <div class="owner-section">
            <div class="owner-label">Owner:</div>
            <div class="owner-name"><?php echo htmlspecialchars(ucwords(strtolower($user['username']))); ?></div>
        </div>
    </div>
    <!-- END PROFILE TAB -->
    
    <!-- MUSIC TAB -->
    <div id="music-tab" class="tab-content">
        <div class="music-section">
            <div class="music-header">
                <i class="fas fa-music"></i>
                My Songs
            </div>
            
            <?php if ($uploaded_message): ?>
            <div id="uploadSuccessMessage" style="background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-bottom: 15px; text-align: center; border: 1px solid #c3e6cb; position: relative; transition: opacity 0.5s;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($uploaded_message); ?>
                <button onclick="dismissMessage('uploadSuccessMessage')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #155724; font-size: 20px; cursor: pointer; padding: 0 5px;">&times;</button>
            </div>
            <?php endif; ?>
            
            <!-- Debug info (remove after testing) -->
            <?php if (isset($_GET['debug'])): ?>
            <div style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 12px; font-family: monospace;">
                <strong>DEBUG INFO:</strong><br>
                Total songs found: <?php echo count($user_songs); ?><br>
                User ID: <?php echo $user_id; ?><br>
                <?php if (!empty($user_songs)): ?>
                    First song: <?php echo htmlspecialchars($user_songs[0]['title'] ?? 'No title'); ?><br>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($user_songs)): ?>
                <?php foreach ($user_songs as $song): ?>
                    <div class="song-item">
                        <div class="song-cover">
                            <?php if (!empty($song['cover_art'])): ?>
                                <img src="<?php echo htmlspecialchars($song['cover_art']); ?>" 
                                     alt="<?php echo htmlspecialchars($song['title']); ?>">
                            <?php else: ?>
                                <i class="fas fa-music"></i>
                            <?php endif; ?>
                        </div>
                        <div class="song-info">
                            <div class="song-info-title"><?php echo htmlspecialchars($song['title']); ?></div>
                            <div class="song-info-stats">
                                <span><i class="fas fa-play"></i> <?php echo number_format($song['plays']); ?></span>
                                <span><i class="fas fa-download"></i> <?php echo number_format($song['downloads']); ?></span>
                            </div>
                        </div>
                        <div class="song-actions" style="display: flex; gap: 8px;">
                            <?php
                            // Generate song slug for URL
                            $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['title']));
                            $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
                            $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['artist'] ?? 'unknown-artist'));
                            $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
                            $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
                            ?>
                            <a href="/song/<?php echo urlencode($songSlug); ?>" 
                               style="padding: 8px 12px; background: #10b981; color: white; border-radius: 5px; text-decoration: none; font-size: 13px;"
                               title="View Song">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit-song.php?id=<?php echo $song['id']; ?>" 
                               style="padding: 8px 12px; background: #3b82f6; color: white; border-radius: 5px; text-decoration: none; font-size: 13px; display: inline-block; position: relative; z-index: 10;"
                               title="Edit Song"
                               onclick="if(event) { event.stopPropagation(); event.stopImmediatePropagation(); return true; }">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button onclick="deleteSong(<?php echo $song['id']; ?>)" 
                                    style="padding: 8px 12px; background: #ef4444; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px;"
                                    title="Delete Song">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-songs">
                    <i class="fas fa-music"></i>
                    No songs uploaded yet
                    <div style="margin-top: 15px;">
                        <a href="upload.php" class="btn btn-upload">Upload Your First Song</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- END MUSIC TAB -->
    
    <!-- EDIT TAB -->
    <div id="edit-tab" class="tab-content">
        <div class="edit-section">
            <div class="edit-header">
                <i class="fas fa-user-edit"></i>
                Edit Profile
            </div>
            
            <form id="profileEditForm" method="POST" enctype="multipart/form-data" style="padding: 20px 0;">
                <!-- Avatar Upload -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">
                        Profile Picture
                    </label>
                    <div style="text-align: center; margin-bottom: 10px;">
                        <div id="avatarPreview" style="width: 120px; height: 120px; border-radius: 50%; background: #ddd; margin: 0 auto; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($user['avatar']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-user" style="font-size: 50px; color: #999;"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <input type="file" name="avatar" accept="image/*" onchange="previewAvatar(this)" 
                           style="display: block; width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background: white;">
                    <small style="color: #666; display: block; margin-top: 5px;">Recommended: Square image, at least 200x200px</small>
                </div>
                
                <!-- Username -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">
                        Artist Name
                    </label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required
                           style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 15px;">
                </div>
                
                <!-- Bio -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">
                        Bio
                    </label>
                    <textarea name="bio" rows="4" placeholder="Tell us about yourself..."
                              style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 15px; resize: vertical;"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    <small style="color: #666; display: block; margin-top: 5px;">Describe your music style, achievements, etc.</small>
                </div>
                
                <!-- Social Links -->
                <div style="margin-bottom: 20px;">
                    <h4 style="margin-bottom: 15px; color: #333; font-size: 16px;">
                        <i class="fas fa-share-alt"></i> Social Media Links
                    </h4>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: 500; margin-bottom: 5px; color: #666;">
                            <i class="fab fa-facebook"></i> Facebook
                        </label>
                        <input type="url" name="facebook" value="<?php echo htmlspecialchars($user['facebook'] ?? ''); ?>" 
                               placeholder="https://facebook.com/yourprofile"
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: 500; margin-bottom: 5px; color: #666;">
                            <i class="fab fa-twitter"></i> Twitter
                        </label>
                        <input type="url" name="twitter" value="<?php echo htmlspecialchars($user['twitter'] ?? ''); ?>" 
                               placeholder="https://twitter.com/yourprofile"
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: 500; margin-bottom: 5px; color: #666;">
                            <i class="fab fa-instagram"></i> Instagram
                        </label>
                        <input type="url" name="instagram" value="<?php echo htmlspecialchars($user['instagram'] ?? ''); ?>" 
                               placeholder="https://instagram.com/yourprofile"
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: 500; margin-bottom: 5px; color: #666;">
                            <i class="fab fa-youtube"></i> YouTube
                        </label>
                        <input type="url" name="youtube" value="<?php echo htmlspecialchars($user['youtube'] ?? ''); ?>" 
                               placeholder="https://youtube.com/channel/yourchannel"
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" name="update_profile" 
                        style="width: 100%; padding: 15px; background: #ff6600; color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: 600; cursor: pointer;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
            
            <?php if ($update_message): ?>
            <div id="editMessage" style="display: block; margin-top: 15px; padding: 12px; border-radius: 5px; text-align: center; position: relative; transition: opacity 0.5s; <?php echo $update_success ? 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'; ?>">
                <i class="fas fa-<?php echo $update_success ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($update_message); ?>
                <button onclick="dismissMessage('editMessage')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: inherit; font-size: 20px; cursor: pointer; padding: 0 5px;">&times;</button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- END EDIT TAB -->
    
    <!-- LYRICS TAB -->
    <div id="lyrics-tab" class="tab-content">
        <div class="music-section">
            <div class="music-header">
                <i class="fas fa-file-text"></i>
                Manage Lyrics
            </div>
            
            <?php
            // Get songs with lyrics
            $songs_with_lyrics = [];
            try {
                $lyricsStmt = $conn->prepare("
                    SELECT id, title, lyrics 
                    FROM songs 
                    WHERE uploaded_by = ? 
                    AND lyrics IS NOT NULL 
                    AND lyrics != ''
                    ORDER BY id DESC
                ");
                $lyricsStmt->execute([$user_id]);
                $songs_with_lyrics = $lyricsStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Error fetching songs with lyrics: " . $e->getMessage());
            }
            ?>
            
            <div style="margin-bottom: 15px;">
                <a href="lyrics-manage.php" class="btn btn-upload" style="text-decoration: none; display: inline-block;">
                    <i class="fas fa-plus"></i> Add/Edit Lyrics
                </a>
            </div>
            
            <?php if (!empty($songs_with_lyrics)): ?>
                <?php foreach ($songs_with_lyrics as $song_lyric): ?>
                    <div class="song-item" style="background: white; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                        <div style="font-weight: 600; margin-bottom: 10px; color: #333; font-size: 16px;">
                            <?php echo htmlspecialchars($song_lyric['title']); ?>
                        </div>
                        <div style="font-size: 14px; color: #666; line-height: 1.6; max-height: 100px; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo nl2br(htmlspecialchars(substr($song_lyric['lyrics'], 0, 200))); ?>
                            <?php if (strlen($song_lyric['lyrics']) > 200): ?>...<?php endif; ?>
                        </div>
                        <div style="margin-top: 10px;">
                            <a href="lyrics-manage.php?song_id=<?php echo $song_lyric['id']; ?>" 
                               style="padding: 8px 15px; background: #3b82f6; color: white; border-radius: 5px; text-decoration: none; font-size: 13px; display: inline-block;">
                                <i class="fas fa-edit"></i> Edit Lyrics
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-songs">
                    <i class="fas fa-file-text"></i>
                    No lyrics added yet
                    <div style="margin-top: 15px;">
                        <a href="lyrics-manage.php" class="btn btn-upload">Add Your First Lyrics</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- END LYRICS TAB -->
    
    <!-- ALBUMS TAB -->
    <div id="albums-tab" class="tab-content">
        <div class="music-section">
            <div class="music-header">
                <i class="fas fa-compact-disc"></i>
                Manage Albums
            </div>
            
            <?php
            // Get artist's albums
            $artist_albums = [];
            try {
                // First try to find artist_id from artists table
                $artistCheckStmt = $conn->prepare("SELECT id FROM artists WHERE user_id = ? LIMIT 1");
                $artistCheckStmt->execute([$user_id]);
                $artistRecord = $artistCheckStmt->fetch(PDO::FETCH_ASSOC);
                
                // Check which columns exist in albums table
                $albumColumns = $conn->query("SHOW COLUMNS FROM albums");
                $albumColumns->execute();
                $albumColumnNames = $albumColumns->fetchAll(PDO::FETCH_COLUMN);
                $has_user_id = in_array('user_id', $albumColumnNames);
                $has_artist_id = in_array('artist_id', $albumColumnNames);
                
                if ($has_artist_id && $artistRecord) {
                    // Use artist_id if available
                    $albumsStmt = $conn->prepare("
                        SELECT a.*, 
                               COUNT(s.id) as song_count
                        FROM albums a
                        LEFT JOIN songs s ON s.album_id = a.id
                        WHERE a.artist_id = ?
                        GROUP BY a.id
                        ORDER BY a.release_date DESC, a.id DESC
                    ");
                    $albumsStmt->execute([$artistRecord['id']]);
                } elseif ($has_user_id) {
                    // Use user_id if available
                    $albumsStmt = $conn->prepare("
                        SELECT a.*, 
                               COUNT(s.id) as song_count
                        FROM albums a
                        LEFT JOIN songs s ON s.album_id = a.id
                        WHERE a.user_id = ?
                        GROUP BY a.id
                        ORDER BY a.release_date DESC, a.id DESC
                    ");
                    $albumsStmt->execute([$user_id]);
                } else {
                    // Fallback: get albums from songs where user is the creator (uploader), NOT just a collaborator
                    $albumsStmt = $conn->prepare("
                        SELECT DISTINCT a.*, 
                               COUNT(s.id) as song_count
                        FROM albums a
                        LEFT JOIN songs s ON s.album_id = a.id
                        WHERE s.album_id IS NOT NULL
                        AND s.uploaded_by = ?
                        GROUP BY a.id
                        ORDER BY a.release_date DESC, a.id DESC
                    ");
                    $albumsStmt->execute([$user_id]);
                }
                $artist_albums = $albumsStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Error fetching albums: " . $e->getMessage());
            }
            ?>
            
            <div style="margin-bottom: 15px;">
                <a href="albums-manage.php" class="btn btn-upload" style="text-decoration: none; display: inline-block;">
                    <i class="fas fa-plus"></i> Create New Album
                </a>
            </div>
            
            <?php if (!empty($artist_albums)): ?>
                <?php foreach ($artist_albums as $album): ?>
                    <div class="song-item" style="background: white; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <?php if (!empty($album['cover_art'])): ?>
                                <img src="<?php echo htmlspecialchars($album['cover_art']); ?>" 
                                     alt="<?php echo htmlspecialchars($album['title']); ?>"
                                     style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 80px; height: 80px; background: #e9ecef; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-compact-disc" style="font-size: 32px; color: #999;"></i>
                                </div>
                            <?php endif; ?>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; margin-bottom: 5px; color: #333; font-size: 16px;">
                                    <?php echo htmlspecialchars($album['title']); ?>
                                </div>
                                <div style="font-size: 13px; color: #666;">
                                    <?php echo (int)($album['song_count'] ?? 0); ?> songs
                                    <?php if (!empty($album['release_date'])): ?>
                                        • Released: <?php echo date('Y', strtotime($album['release_date'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <a href="albums-manage.php?album_id=<?php echo $album['id']; ?>" 
                                   style="padding: 8px 15px; background: #3b82f6; color: white; border-radius: 5px; text-decoration: none; font-size: 13px; display: inline-block;">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-songs">
                    <i class="fas fa-compact-disc"></i>
                    No albums created yet
                    <div style="margin-top: 15px;">
                        <a href="albums-manage.php" class="btn btn-upload">Create Your First Album</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- END ALBUMS TAB -->
    
    <!-- NEWS TAB -->
    <div id="news-tab" class="tab-content">
        <!-- News Submission Form -->
        <div class="news-section" style="margin-bottom: 20px;">
            <div class="news-header">
                <i class="fas fa-plus-circle"></i>
                Submit News
            </div>
            
            <?php if ($news_submission_message): ?>
                <div style="background: <?php echo $news_submission_success ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $news_submission_success ? '#155724' : '#721c24'; ?>; padding: 12px; border-radius: 5px; margin-bottom: 15px; border: 1px solid <?php echo $news_submission_success ? '#c3e6cb' : '#f5c6cb'; ?>;">
                    <i class="fas fa-<?php echo $news_submission_success ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($news_submission_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" style="padding: 15px 0;">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">Title *</label>
                    <input type="text" name="news_title" required
                           style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;"
                           placeholder="Enter news title">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">Category</label>
                    <select name="news_category" 
                            style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                        <?php foreach ($news_categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>">
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">Content *</label>
                    <textarea name="news_content" rows="6" required
                              style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; resize: vertical;"
                              placeholder="Write your news content here..."></textarea>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">Image <span style="color: red;">*</span></label>
                    <input type="file" name="news_image" accept="image/*" required
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background: white;">
                    <small style="color: #666; display: block; margin-top: 5px;">Recommended: 1200x630px</small>
                </div>
                
                <button type="submit" name="submit_news" 
                        style="width: 100%; padding: 12px; background: #ff6600; color: white; border: none; border-radius: 5px; font-size: 15px; font-weight: 600; cursor: pointer;">
                    <i class="fas fa-paper-plane"></i> Submit for Review
                </button>
                
                <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; font-size: 13px; color: #856404;">
                    <i class="fas fa-info-circle"></i> Your news will be reviewed by admin before being published.
                </div>
            </form>
        </div>
        
        <!-- My Submitted News -->
        <div class="news-section" style="margin-bottom: 20px;">
            <div class="news-header">
                <i class="fas fa-file-alt"></i>
                My Submitted News
            </div>
            
            <?php if (!empty($user_submitted_news)): ?>
                <?php foreach ($user_submitted_news as $news_item): ?>
                    <div class="news-item" style="position: relative;">
                        <?php if ($news_item['status'] === 'pending'): ?>
                            <span style="position: absolute; top: 10px; right: 10px; background: #ffc107; color: #856404; padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;">
                                Pending Approval
                            </span>
                        <?php else: ?>
                            <span style="position: absolute; top: 10px; right: 10px; background: #28a745; color: white; padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;">
                                Published
                            </span>
                        <?php endif; ?>
                        
                        <a href="<?php echo $news_item['status'] === 'published' ? 'news-details.php?id=' . $news_item['id'] : '#'; ?>" 
                           class="news-title" 
                           style="<?php echo $news_item['status'] === 'pending' ? 'opacity: 0.7;' : ''; ?>">
                            <?php echo htmlspecialchars($news_item['title']); ?>
                        </a>
                        <div class="news-excerpt">
                            <?php 
                            $excerpt = strip_tags($news_item['content'] ?? $news_item['excerpt'] ?? '');
                            echo htmlspecialchars(substr($excerpt, 0, 100)) . '...'; 
                            ?>
                        </div>
                        <div class="news-date">
                            <i class="far fa-clock"></i> 
                            <?php echo date('M d, Y', strtotime($news_item['created_at'])); ?>
                            <?php if ($news_item['status'] === 'pending'): ?>
                                <span style="color: #ffc107; margin-left: 10px;">
                                    <i class="fas fa-hourglass-half"></i> Awaiting admin approval
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-news">
                    <i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 10px; display: block;"></i>
                    No news submitted yet
                </div>
            <?php endif; ?>
        </div>
        
        <!-- News Where Artist is Tagged -->
        <?php if (!empty($latest_news)): ?>
        <div class="news-section">
            <div class="news-header">
                <i class="fas fa-newspaper"></i>
                News About You
            </div>
            
            <?php foreach ($latest_news as $news_item): ?>
                <div class="news-item">
                    <a href="news-details.php?id=<?php echo $news_item['id']; ?>" class="news-title">
                        <?php echo htmlspecialchars($news_item['title']); ?>
                    </a>
                    <div class="news-excerpt">
                        <?php 
                        $excerpt = strip_tags($news_item['content'] ?? '');
                        echo htmlspecialchars(substr($excerpt, 0, 100)) . '...'; 
                        ?>
                    </div>
                    <div class="news-date">
                        <i class="far fa-clock"></i> 
                        <?php echo date('M d, Y', strtotime($news_item['created_at'] ?? 'now')); ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div style="text-align: center; margin-top: 15px;">
                <em style="color: #999; font-size: 13px;">Showing news where you're tagged</em>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <!-- END NEWS TAB -->
    
    <!-- STATS TAB -->
    <div id="stats-tab" class="tab-content">
        <div class="stats-section">
            <div class="stats-summary" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px;">
                <div class="stat-card" style="background: white; border-radius: 8px; padding: 20px; text-align: center;">
                    <div class="stat-number" style="font-size: 28px; font-weight: 700; color: #333;"><?php echo number_format($user['total_songs'] ?? 0); ?></div>
                    <div class="stat-label" style="font-size: 13px; color: #666; margin-top: 5px;">Total Songs</div>
                </div>
                <div class="stat-card" style="background: white; border-radius: 8px; padding: 20px; text-align: center;">
                    <div class="stat-number" style="font-size: 28px; font-weight: 700; color: #333;"><?php echo number_format($user['total_plays'] ?? 0); ?></div>
                    <div class="stat-label" style="font-size: 13px; color: #666; margin-top: 5px;">Total Plays</div>
                </div>
                <div class="stat-card" style="background: white; border-radius: 8px; padding: 20px; text-align: center;">
                    <div class="stat-number" style="font-size: 28px; font-weight: 700; color: #333;"><?php echo number_format($user['total_downloads'] ?? 0); ?></div>
                    <div class="stat-label" style="font-size: 13px; color: #666; margin-top: 5px;">Total Downloads</div>
                </div>
            </div>
            
            <div class="songs-stats-container" style="background: white; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <h3 style="margin-bottom: 15px; font-size: 18px; color: #333;">
                    <i class="fas fa-chart-line"></i> Song Performance
                </h3>
                <?php if (empty($user_songs)): ?>
                    <p style="text-align: center; color: #999; padding: 40px 20px;">
                        <i class="fas fa-music" style="font-size: 40px; display: block; margin-bottom: 10px; color: #ddd;"></i>
                        No songs uploaded yet
                    </p>
                <?php else: ?>
                    <?php foreach ($user_songs as $song): ?>
                        <div class="song-stat-item" style="padding: 12px 0; border-bottom: 1px solid #eee;">
                            <div class="song-title" style="font-weight: 600; margin-bottom: 5px; color: #333;">
                                <?php echo htmlspecialchars($song['title']); ?>
                            </div>
                            <div class="song-metrics" style="display: flex; gap: 20px; font-size: 13px; color: #666;">
                                <span><i class="fas fa-play"></i> <?php echo number_format($song['plays']); ?> plays</span>
                                <span><i class="fas fa-download"></i> <?php echo number_format($song['downloads']); ?> downloads</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- END STATS TAB -->
    
    </div>
    <!-- END Profile Container -->
    
    <script>
        function toggleActiveStatus(checkbox) {
            const isActive = checkbox.checked ? 1 : 0;
            
            // Send AJAX request to update status
            fetch('api/update-artist-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ is_active: isActive })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(isActive ? 'Profile activated!' : 'Profile deactivated!');
                } else {
                    alert('Failed to update status');
                    checkbox.checked = !checkbox.checked; // Revert on error
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
                checkbox.checked = !checkbox.checked; // Revert on error
            });
        }
        
        function switchTab(tabName, skipStorage) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.nav-tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            const selectedTab = document.getElementById(tabName + '-tab');
            if (selectedTab) {
                selectedTab.classList.add('active');
            }
            
            // Add active class to clicked tab
            if (event && event.target) {
                event.target.classList.add('active');
            } else {
                // If called programmatically, find and activate the tab link
                tabs.forEach(tab => {
                    if (tab.getAttribute('onclick') && tab.getAttribute('onclick').includes("'" + tabName + "'")) {
                        tab.classList.add('active');
                    }
                });
            }
            
            // Update URL with tab parameter (unless told to skip)
            if (!skipStorage) {
                try {
                    // Update URL without reloading page
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tabName);
                    window.history.pushState({ tab: tabName }, '', url);
                    
                    // Save active tab to localStorage
                    localStorage.setItem('artistProfileActiveTab', tabName);
                } catch (e) {
                    console.error('Error saving tab to localStorage:', e);
                }
            }
        }
        
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('avatarPreview');
                    preview.innerHTML = '<img src="' + e.target.result + '" style="width: 100%; height: 100%; object-fit: cover;">';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function deleteSong(songId) {
            if (confirm('Are you sure you want to delete this song? This action cannot be undone.')) {
                fetch('api/delete-song.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ song_id: songId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Song deleted successfully!');
                        window.location.href = 'artist-profile-mobile.php?tab=music';
                    } else {
                        alert('Failed to delete song: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the song');
                });
            }
        }
        
        // Function to dismiss messages
        function dismissMessage(messageId) {
            const message = document.getElementById(messageId);
            if (message) {
                message.style.opacity = '0';
                setTimeout(function() {
                    message.style.display = 'none';
                    // Clean up URL parameters
                    const url = new URL(window.location);
                    url.searchParams.delete('uploaded');
                    url.searchParams.delete('updated');
                    window.history.replaceState({}, '', url);
                }, 500);
            }
        }
        
        // Auto-dismiss success messages after 5 seconds
        function autoHideMessages() {
            const uploadMessage = document.getElementById('uploadSuccessMessage');
            if (uploadMessage) {
                setTimeout(function() {
                    dismissMessage('uploadSuccessMessage');
                }, 5000); // Hide after 5 seconds
            }
            
            const editMessage = document.getElementById('editMessage');
            if (editMessage) {
                setTimeout(function() {
                    editMessage.style.opacity = '0';
                    setTimeout(function() {
                        editMessage.style.display = 'none';
                    }, 500);
                }, 5000);
            }
        }
        
        // Auto-switch to tab based on URL parameter or localStorage
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const urlTab = urlParams.get('tab');
            let activeTab = null;
            
            // Priority 1: URL parameter (from redirects like after upload)
            if (urlTab && ['profile', 'music', 'edit', 'news', 'stats'].includes(urlTab)) {
                activeTab = urlTab;
            } else {
                // Priority 2: localStorage (remembers last active tab)
                try {
                    const savedTab = localStorage.getItem('artistProfileActiveTab');
                    if (savedTab && ['profile', 'music', 'edit', 'news', 'stats'].includes(savedTab)) {
                        activeTab = savedTab;
                    }
                } catch (e) {
                    console.error('Error reading localStorage:', e);
                }
            }
            
            // If we have a tab to activate, switch to it
            if (activeTab) {
                setTimeout(function() {
                    switchTab(activeTab, false); // false = save to localStorage and update URL
                }, 100);
            } else {
                // Default to profile tab and save it
                try {
                    localStorage.setItem('artistProfileActiveTab', 'profile');
                    // Update URL if no tab parameter
                    const url = new URL(window.location);
                    if (!url.searchParams.has('tab')) {
                        url.searchParams.set('tab', 'profile');
                        window.history.replaceState({ tab: 'profile' }, '', url);
                    }
                } catch (e) {
                    console.error('Error saving to localStorage:', e);
                }
            }
            
            // Handle browser back/forward buttons
            window.addEventListener('popstate', function(event) {
                if (event.state && event.state.tab) {
                    switchTab(event.state.tab, true);
                } else {
                    const urlParams = new URLSearchParams(window.location.search);
                    const urlTab = urlParams.get('tab');
                    if (urlTab && ['profile', 'music', 'edit', 'news', 'stats'].includes(urlTab)) {
                        switchTab(urlTab, true);
                    }
                }
            });
            
            // Auto-hide success messages
            autoHideMessages();
        });
    </script>
</body>
</html>

