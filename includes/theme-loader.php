<?php
// includes/theme-loader.php
// Load theme settings and apply them to frontend pages

require_once __DIR__ . '/../config/database.php';

function getThemeSetting($key, $default = '') {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Try setting_value first (standard schema column)
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && isset($result['setting_value']) && $result['setting_value'] !== null && $result['setting_value'] !== '') {
            return $result['setting_value'];
        }
        
        // Fallback to 'value' column if setting_value doesn't exist
        try {
            $stmt = $conn->prepare("SELECT value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result['value'])) {
                return $result['value'];
            }
        } catch (Exception $e2) {
            // Column doesn't exist, use default
        }
        
        return $default;
    } catch (Exception $e) {
        error_log("Error getting theme setting $key: " . $e->getMessage());
        return $default;
    }
}

function getActiveTheme() {
    return getThemeSetting('active_theme', 'default');
}

function getThemeColors() {
    return [
        'primary' => getThemeSetting('primary_color', '#1e4d72'),
        'secondary' => getThemeSetting('secondary_color', '#667eea'),
        'accent' => getThemeSetting('accent_color', '#764ba2'),
        'background' => getThemeSetting('background_color', '#f5f5f5')
    ];
}

function renderThemeStyles() {
    $colors = getThemeColors();
    $activeTheme = getActiveTheme();
    
    $css = "
    <style id='dynamic-theme-styles'>
        :root {
            --theme-primary: {$colors['primary']};
            --theme-secondary: {$colors['secondary']};
            --theme-accent: {$colors['accent']};
            --theme-background: {$colors['background']};
        }
        
        body {
            background-color: {$colors['background']};
        }
        
        .main-header {
            background: {$colors['primary']} !important;
        }
        
        .btn-primary, .primary-btn {
            background-color: {$colors['primary']};
            border-color: {$colors['primary']};
        }
        
        .btn-primary:hover, .primary-btn:hover {
            background-color: {$colors['secondary']};
            border-color: {$colors['secondary']};
        }
        
        a, .link-primary {
            color: {$colors['primary']};
        }
        
        a:hover, .link-primary:hover {
            color: {$colors['secondary']};
        }
    </style>
    ";
    
    echo $css;
}
?>

