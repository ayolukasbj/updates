<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title ?? 'Admin Dashboard'; ?> - Music Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/mobile-admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/mobile-fix.css?v=<?php echo time(); ?>">
    <style>
        /* MOBILE FIX - No top spacing + Working Hamburger Menu */
        @media (max-width: 768px) {
            /* Remove all top spacing */
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                margin-top: 0 !important;
                padding-top: 0 !important;
                height: 100% !important;
                overflow-x: hidden !important;
            }
            
            /* Admin wrapper fills screen */
            .admin-wrapper {
                margin: 0 !important;
                padding: 0 !important;
                margin-top: 0 !important;
                padding-top: 0 !important;
                display: flex !important;
                flex-direction: column !important;
                min-height: 100vh !important;
            }
            
            /* Sidebar - Hidden by default on mobile */
            .sidebar {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 280px !important;
                height: 100vh !important;
                background: linear-gradient(180deg, #1f2937 0%, #111827 100%) !important;
                transform: translateX(-100%) !important;
                transition: transform 0.3s ease !important;
                z-index: 1000 !important;
                overflow-y: auto !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            /* Sidebar shown when active */
            .sidebar.active {
                transform: translateX(0) !important;
                box-shadow: 2px 0 10px rgba(0,0,0,0.3) !important;
            }
            
            /* Sidebar header */
            .sidebar-header {
                margin: 0 !important;
                padding: 15px !important;
                text-align: center !important;
                border-bottom: 1px solid rgba(255,255,255,0.1) !important;
            }
            
            /* Main content takes full width */
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                flex: 1 !important;
            }
            
            /* Top bar visible */
            .topbar {
                display: flex !important;
                background: #fff !important;
                padding: 10px 15px !important;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1) !important;
                margin: 0 !important;
            }
            
            /* Hamburger button */
            .menu-toggle {
                display: block !important;
                background: none !important;
                border: none !important;
                font-size: 24px !important;
                color: #333 !important;
                cursor: pointer !important;
                padding: 8px !important;
            }
            
            /* Page content */
            .page-content {
                padding: 15px !important;
                margin: 0 !important;
            }
            
            /* Overlay when menu is open */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }
            
            .sidebar.active + .sidebar-overlay {
                display: block !important;
            }
            
            /* Ensure page content has proper z-index */
            .page-content {
                position: relative !important;
                z-index: 1 !important;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-music"></i>
                <h2>Music Admin</h2>
            </div>

            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="users.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                <a href="songs.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'songs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-music"></i>
                    <span>Songs</span>
                </a>
                <a href="artists.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'artists.php' ? 'active' : ''; ?>">
                    <i class="fas fa-microphone"></i>
                    <span>Artists</span>
                </a>
                <a href="news.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'news.php' ? 'active' : ''; ?>">
                    <i class="fas fa-newspaper"></i>
                    <span>News</span>
                </a>
                <a href="analytics.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Analytics</span>
                </a>
                <a href="settings-advanced.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings-advanced.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Advanced Settings</span>
                </a>
                <a href="song-edit-manager.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'song-edit-manager.php' ? 'active' : ''; ?>">
                    <i class="fas fa-edit"></i>
                    <span>Song Editor</span>
                </a>
                <a href="mp3-tagger.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'mp3-tagger.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tag"></i>
                    <span>MP3 Tagger</span>
                </a>
                <a href="genres-tags.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'genres-tags.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i>
                    <span>Genres & Tags</span>
                </a>
                <a href="ad-management.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'ad-management.php' ? 'active' : ''; ?>">
                    <i class="fas fa-ad"></i>
                    <span>Ad Management</span>
                </a>
                <a href="theme-settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'theme-settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-palette"></i>
                    <span>Theme Settings</span>
                </a>
                <a href="settings.php" class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['settings.php', 'settings-general.php', 'check-updates.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="settings-general.php" class="nav-item" style="padding-left: 40px; font-size: 0.9em;">
                    <i class="fas fa-sliders-h"></i>
                    <span>General</span>
                </a>
                <a href="check-updates.php" class="nav-item" style="padding-left: 40px; font-size: 0.9em;">
                    <i class="fas fa-sync"></i>
                    <span>Check Updates</span>
                </a>
                <a href="pages.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'pages.php' || basename($_SERVER['PHP_SELF']) == 'page-edit.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Pages</span>
                </a>
                <a href="comments.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'comments.php' ? 'active' : ''; ?>">
                    <i class="fas fa-comments"></i>
                    <span>Comments & Ratings</span>
                </a>
                <a href="news-categories.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'news-categories.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i>
                    <span>News Categories</span>
                </a>
                <a href="homepage-manager.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'homepage-manager.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Homepage Manager</span>
                </a>
                <a href="business-tabs.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'business-tabs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-columns"></i>
                    <span>Business Section Tabs</span>
                </a>
                <a href="polls.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'polls.php' ? 'active' : ''; ?>">
                    <i class="fas fa-poll"></i>
                    <span>Opinion Polls</span>
                </a>
                <div class="nav-divider"></div>
                <a href="email-templates.php" class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['email-templates.php', 'email-settings.php', 'email-queue.php', 'send-newsletter.php', 'newsletter-subscribers.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i>
                    <span>Email Management</span>
                </a>
                <a href="email-templates.php" class="nav-item" style="padding-left: 40px; font-size: 0.9em;">
                    <i class="fas fa-file-alt"></i>
                    <span>Templates</span>
                </a>
                <a href="email-settings.php" class="nav-item" style="padding-left: 40px; font-size: 0.9em;">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="send-newsletter.php" class="nav-item" style="padding-left: 40px; font-size: 0.9em;">
                    <i class="fas fa-paper-plane"></i>
                    <span>Send Newsletter</span>
                </a>
                <a href="newsletter-subscribers.php" class="nav-item" style="padding-left: 40px; font-size: 0.9em;">
                    <i class="fas fa-users"></i>
                    <span>Subscribers</span>
                </a>
                <a href="email-queue.php" class="nav-item" style="padding-left: 40px; font-size: 0.9em;">
                    <i class="fas fa-list"></i>
                    <span>Email Queue</span>
                </a>
                <div class="nav-divider"></div>
                <a href="license-management.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'license-management.php' ? 'active' : ''; ?>">
                    <i class="fas fa-key"></i>
                    <span>License Management</span>
                </a>
                <a href="lyrics-manage.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'lyrics-manage.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-text"></i>
                    <span>Lyrics Management</span>
                </a>
                <a href="albums-manage.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'albums-manage.php' ? 'active' : ''; ?>">
                    <i class="fas fa-compact-disc"></i>
                    <span>Albums Management</span>
                </a>
                <a href="biography-manage.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'biography-manage.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span>Biography Management</span>
                </a>
                
                <div class="nav-divider"></div>
                
                <a href="install-database.php" class="nav-item" style="color: #ffc107;">
                    <i class="fas fa-database"></i>
                    <span>Database Setup</span>
                </a>
                <a href="../index.php" class="nav-item">
                    <i class="fas fa-globe"></i>
                    <span>View Website</span>
                </a>
                <a href="logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
                
                <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
                <div class="nav-divider"></div>
                <a href="settings-advanced.php" class="nav-item" style="color: #dc3545; font-weight: bold;">
                    <i class="fas fa-user-shield"></i>
                    <span>Super Admin</span>
                </a>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <div class="admin-info">
                    <i class="fas fa-user-shield"></i>
                    <div>
                        <strong><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></strong>
                        <small><?php echo ucfirst($_SESSION['admin_role'] ?? 'admin'); ?></small>
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <header class="topbar">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="topbar-right">
                    <div class="admin-user">
                        <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></strong></span>
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="page-content">

