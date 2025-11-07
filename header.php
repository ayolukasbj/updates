<?php
// license-server/includes/header.php
// Shared header and navigation for all admin pages

if (!isset($_SESSION)) {
    session_start();
}

// Get current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);

// Navigation menu items (only show if user is admin, not client)
$nav_items = [];
if (!isset($_SESSION['client_license_key'])) {
    $nav_items = [
        'index.php' => ['icon' => 'fa-home', 'label' => 'Dashboard'],
        'licenses.php' => ['icon' => 'fa-key', 'label' => 'Licenses'],
        'create-license.php' => ['icon' => 'fa-plus', 'label' => 'Create License'],
        'customers.php' => ['icon' => 'fa-users', 'label' => 'Customers'],
        'logs.php' => ['icon' => 'fa-list', 'label' => 'Logs'],
        'settings.php' => ['icon' => 'fa-cog', 'label' => 'Settings'],
        'security.php' => ['icon' => 'fa-shield-alt', 'label' => 'Security'],
        'updates.php' => ['icon' => 'fa-sync', 'label' => 'Updates'],
        'version-control.php' => ['icon' => 'fa-code-branch', 'label' => 'Version Control']
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'License Management System'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .header { background: #1f2937; color: white; padding: 15px 20px; }
        .header-content { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .header h1 { font-size: 20px; margin: 0; }
        .header-actions { display: flex; gap: 15px; align-items: center; }
        .header-actions span { font-size: 14px; }
        .header-actions a { color: white; text-decoration: none; font-size: 14px; }
        .header-actions a:hover { text-decoration: underline; }
        .nav { background: white; padding: 12px 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow-x: auto; }
        .nav-content { max-width: 1200px; margin: 0 auto; display: flex; gap: 15px; flex-wrap: wrap; }
        .nav a { color: #333; text-decoration: none; padding: 8px 12px; border-radius: 4px; white-space: nowrap; font-size: 14px; transition: all 0.3s; }
        .nav a:hover { background: #f3f4f6; }
        .nav a.active { background: #3b82f6; color: white; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 15px; }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .header-content { flex-direction: column; align-items: flex-start; gap: 10px; }
            .header h1 { font-size: 18px; }
            .header-actions { width: 100%; justify-content: space-between; }
            .nav-content { gap: 10px; }
            .nav a { padding: 6px 10px; font-size: 13px; }
            .container { padding: 0 10px; }
        }
        
        @media (max-width: 480px) {
            .header h1 { font-size: 16px; }
        }
    </style>
    <?php if (isset($additional_css)): ?>
    <style><?php echo $additional_css; ?></style>
    <?php endif; ?>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-key"></i> License Management System</h1>
            <div class="header-actions">
                <?php if (isset($_SESSION['client_license_key'])): ?>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['client_email'] ?? 'Client'); ?></span>
                <a href="client-dashboard.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php else: ?>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (!empty($nav_items)): ?>
    <div class="nav">
        <div class="nav-content">
            <?php foreach ($nav_items as $page => $item): ?>
            <a href="<?php echo htmlspecialchars($page); ?>" class="<?php echo ($current_page === $page) ? 'active' : ''; ?>">
                <i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i> <?php echo htmlspecialchars($item['label']); ?>
            </a>
            <?php endforeach; ?>
            <a href="client-dashboard.php" class="<?php echo ($current_page === 'client-dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i> Client Dashboard
            </a>
        </div>
    </div>
    <?php endif; ?>

