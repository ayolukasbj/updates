<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Theme Settings';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Available themes - only default theme
$available_themes = [
    'default' => 'Default Theme',
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'save_theme':
                $active_theme = trim($_POST['active_theme'] ?? 'default');
                saveSetting('active_theme', $active_theme);
                $_SESSION['success_message'] = 'Theme updated successfully!';
                header('Location: theme-settings.php');
                exit;
                
            case 'save_colors':
                $primary_color = trim($_POST['primary_color'] ?? '#1e4d72');
                $secondary_color = trim($_POST['secondary_color'] ?? '#667eea');
                $accent_color = trim($_POST['accent_color'] ?? '#764ba2');
                $background_color = trim($_POST['background_color'] ?? '#f5f5f5');
                
                saveSetting('primary_color', $primary_color);
                saveSetting('secondary_color', $secondary_color);
                saveSetting('accent_color', $accent_color);
                saveSetting('background_color', $background_color);
                
                $_SESSION['success_message'] = 'Color settings saved successfully!';
                header('Location: theme-settings.php');
                exit;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get success message from session
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get current settings
$active_theme = getSetting('active_theme', 'default');
$primary_color = getSetting('primary_color', '#1e4d72');
$secondary_color = getSetting('secondary_color', '#667eea');
$accent_color = getSetting('accent_color', '#764ba2');
$background_color = getSetting('background_color', '#f5f5f5');

// Helper functions
function getSetting($key, $default = '') {
    global $conn;
    try {
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
            // Column doesn't exist, that's fine
        }
        
        return $default;
    } catch (Exception $e) {
        error_log("Error getting setting $key: " . $e->getMessage());
        return $default;
    }
}

function saveSetting($key, $value) {
    global $conn;
    try {
        // Check if settings table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'settings'");
        if ($tableCheck->rowCount() == 0) {
            // Create settings table with setting_value column (as per schema)
            $conn->exec("
                CREATE TABLE IF NOT EXISTS settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) NOT NULL UNIQUE,
                    setting_value TEXT,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_key (setting_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        // Always use setting_value column (standard schema)
        $stmt = $conn->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
        ");
        $result = $stmt->execute([$key, $value, $value]);
        
        if (!$result) {
            error_log("Failed to save setting: $key = $value");
        }
    } catch (Exception $e) {
        error_log("Error saving setting: " . $e->getMessage());
        // Try creating table and column if missing
        try {
            $conn->exec("ALTER TABLE settings ADD COLUMN IF NOT EXISTS setting_value TEXT");
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        } catch (Exception $e2) {
            error_log("Error saving setting (fallback): " . $e2->getMessage());
        }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Theme Settings</h1>
    <p>Manage website themes and appearance</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Theme Selection -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Select Theme</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_theme">
            <div class="form-group">
                <label>Active Theme *</label>
                <select name="active_theme" class="form-control" required>
                    <?php foreach ($available_themes as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $active_theme === $key ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small>Select the theme layout for your website</small>
            </div>
            <button type="submit" class="btn btn-primary">Save Theme</button>
        </form>
    </div>
</div>

<!-- Color Customization -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Color Customization</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_colors">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Primary Color</label>
                    <input type="color" name="primary_color" class="form-control" value="<?php echo htmlspecialchars($primary_color); ?>" style="height: 50px;">
                </div>
                <div class="form-group">
                    <label>Secondary Color</label>
                    <input type="color" name="secondary_color" class="form-control" value="<?php echo htmlspecialchars($secondary_color); ?>" style="height: 50px;">
                </div>
                <div class="form-group">
                    <label>Accent Color</label>
                    <input type="color" name="accent_color" class="form-control" value="<?php echo htmlspecialchars($accent_color); ?>" style="height: 50px;">
                </div>
                <div class="form-group">
                    <label>Background Color</label>
                    <input type="color" name="background_color" class="form-control" value="<?php echo htmlspecialchars($background_color); ?>" style="height: 50px;">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save Colors</button>
        </form>
    </div>
</div>

<!-- Theme Info -->
<div class="card">
    <div class="card-header">
        <h2>Available Themes</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <?php foreach ($available_themes as $key => $label): ?>
            <div class="card" style="border: <?php echo $active_theme === $key ? '2px solid #007bff' : '1px solid #ddd'; ?>;">
                <div style="padding: 15px; text-align: center;">
                    <h3><?php echo htmlspecialchars($label); ?></h3>
                    <?php if ($active_theme === $key): ?>
                    <span class="badge badge-success">Active</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

