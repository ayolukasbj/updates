<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Dashboard Test</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5}";
echo ".success{background:#d4edda;color:#155724;padding:15px;margin:10px 0;border-radius:5px}";
echo ".error{background:#f8d7da;color:#721c24;padding:15px;margin:10px 0;border-radius:5px}";
echo "h1{color:#333}</style></head><body>";

echo "<h1>🎛️ Admin Dashboard Test</h1>";

// Test 1: Auth check file
if (file_exists('auth-check.php')) {
    echo "<div class='success'>✅ auth-check.php exists</div>";
} else {
    echo "<div class='error'>❌ auth-check.php missing</div>";
}

// Test 2: Header file
if (file_exists('includes/header.php')) {
    echo "<div class='success'>✅ includes/header.php exists</div>";
} else {
    echo "<div class='error'>❌ includes/header.php missing</div>";
}

// Test 3: CSS file
if (file_exists('assets/css/admin.css')) {
    echo "<div class='success'>✅ assets/css/admin.css exists</div>";
} else {
    echo "<div class='error'>❌ assets/css/admin.css missing</div>";
}

// Test 4: Dashboard file
if (file_exists('index.php')) {
    echo "<div class='success'>✅ index.php (dashboard) exists</div>";
} else {
    echo "<div class='error'>❌ index.php missing</div>";
}

// Test 5: All management pages
$pages = ['users.php', 'songs.php', 'artists.php', 'news.php', 'analytics.php', 'settings.php'];
echo "<h2>📄 Admin Pages Check:</h2>";
foreach ($pages as $page) {
    if (file_exists($page)) {
        echo "<div class='success'>✅ $page exists</div>";
    } else {
        echo "<div class='error'>❌ $page missing</div>";
    }
}

// Test 6: News edit page
if (file_exists('news-edit.php')) {
    echo "<div class='success'>✅ news-edit.php (content editor) exists</div>";
} else {
    echo "<div class='error'>❌ news-edit.php missing</div>";
}

echo "<hr><h2>🔍 What's Available:</h2>";
echo "<ol>";
echo "<li><strong>Dashboard</strong> - index.php (Statistics & Overview)</li>";
echo "<li><strong>Users Management</strong> - users.php (View, Edit, Ban, Delete)</li>";
echo "<li><strong>Songs Management</strong> - songs.php (Approve, Feature, Delete)</li>";
echo "<li><strong>Artists Management</strong> - artists.php (Verify, Manage)</li>";
echo "<li><strong>News/Content Management</strong> - news.php (Create, Edit, Delete Articles)</li>";
echo "<li><strong>Analytics</strong> - analytics.php (Reports & Statistics)</li>";
echo "<li><strong>Settings</strong> - settings.php (System Configuration)</li>";
echo "</ol>";

echo "<hr><h2>📋 Sidebar Navigation Includes:</h2>";
echo "<ul>";
echo "<li>🏠 Dashboard</li>";
echo "<li>👥 Users</li>";
echo "<li>🎵 Songs</li>";
echo "<li>🎤 Artists</li>";
echo "<li>📰 <strong>News (Content Management)</strong></li>";
echo "<li>📊 Analytics</li>";
echo "<li>⚙️ Settings</li>";
echo "<li>🌐 View Website</li>";
echo "<li>🚪 Logout</li>";
echo "</ul>";

echo "<hr><p style='font-size:18px;'><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Make sure you've run <a href='debug.php'>debug.php</a> and added the role column</li>";
echo "<li>Upgrade your account at <a href='setup-admin.php'>setup-admin.php</a></li>";
echo "<li>Login at <a href='login.php'>login.php</a></li>";
echo "<li>After login, you'll see the full dashboard with sidebar!</li>";
echo "</ol>";

echo "</body></html>";
?>

