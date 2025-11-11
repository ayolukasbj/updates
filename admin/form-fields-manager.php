<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is admin
if (!is_logged_in() || !is_admin()) {
    redirect('../login.php');
}

$db = new Database();
$conn = $db->getConnection();

$message = '';
$message_type = '';

// Handle form field updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_upload_fields'])) {
        $fields_config = json_encode($_POST['upload_fields'] ?? []);
        
        try {
            $stmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value, setting_group) 
                VALUES ('upload_form_fields', ?, 'forms')
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$fields_config, $fields_config]);
            $message = 'Upload form fields updated successfully!';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    if (isset($_POST['save_profile_fields'])) {
        $fields_config = json_encode($_POST['profile_fields'] ?? []);
        
        try {
            $stmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value, setting_group) 
                VALUES ('profile_form_fields', ?, 'forms')
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$fields_config, $fields_config]);
            $message = 'Profile form fields updated successfully!';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get current settings
$upload_fields = [];
$profile_fields = [];

try {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'upload_form_fields'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $upload_fields = json_decode($result['setting_value'], true) ?: [];
    }
} catch (Exception $e) {
    // Use defaults
}

try {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'profile_form_fields'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $profile_fields = json_decode($result['setting_value'], true) ?: [];
    }
} catch (Exception $e) {
    // Use defaults
}

// Default upload fields
$default_upload_fields = [
    ['name' => 'title', 'label' => 'Song Title', 'type' => 'text', 'required' => true, 'enabled' => true],
    ['name' => 'artist', 'label' => 'Artist Name', 'type' => 'text', 'required' => true, 'enabled' => true],
    ['name' => 'album', 'label' => 'Album', 'type' => 'text', 'required' => false, 'enabled' => true],
    ['name' => 'genre', 'label' => 'Genre', 'type' => 'select', 'required' => true, 'enabled' => true],
    ['name' => 'year', 'label' => 'Release Year', 'type' => 'number', 'required' => false, 'enabled' => true],
    ['name' => 'lyrics', 'label' => 'Lyrics', 'type' => 'textarea', 'required' => false, 'enabled' => true],
    ['name' => 'explicit', 'label' => 'Explicit Content', 'type' => 'checkbox', 'required' => false, 'enabled' => true],
];

// Default profile fields
$default_profile_fields = [
    ['name' => 'username', 'label' => 'Artist Name', 'type' => 'text', 'required' => true, 'enabled' => true],
    ['name' => 'bio', 'label' => 'Biography', 'type' => 'textarea', 'required' => false, 'enabled' => true],
    ['name' => 'avatar', 'label' => 'Profile Picture', 'type' => 'file', 'required' => false, 'enabled' => true],
    ['name' => 'facebook', 'label' => 'Facebook URL', 'type' => 'url', 'required' => false, 'enabled' => true],
    ['name' => 'twitter', 'label' => 'Twitter URL', 'type' => 'url', 'required' => false, 'enabled' => true],
    ['name' => 'instagram', 'label' => 'Instagram URL', 'type' => 'url', 'required' => false, 'enabled' => true],
    ['name' => 'youtube', 'label' => 'YouTube URL', 'type' => 'url', 'required' => false, 'enabled' => true],
];

if (empty($upload_fields)) $upload_fields = $default_upload_fields;
if (empty($profile_fields)) $profile_fields = $default_profile_fields;

include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="page-header">
        <h1><i class="fas fa-sliders-h"></i> Form Fields Manager</h1>
        <p>Manage form fields for upload and profile pages</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <div class="tabs">
        <button class="tab-btn active" onclick="switchFormTab('upload')">Upload Form Fields</button>
        <button class="tab-btn" onclick="switchFormTab('profile')">Profile Form Fields</button>
    </div>

    <!-- Upload Form Fields -->
    <div id="upload-tab" class="tab-content active">
        <div class="card">
            <div class="card-header">
                <h2>Upload Form Fields</h2>
                <p>Configure fields that appear on the song upload form</p>
            </div>
            <div class="card-body">
                <form method="POST" id="uploadFieldsForm">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Enabled</th>
                                <th>Field Name</th>
                                <th>Label</th>
                                <th>Type</th>
                                <th>Required</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upload_fields as $index => $field): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="upload_fields[<?php echo $index; ?>][enabled]" value="1" 
                                           <?php echo ($field['enabled'] ?? true) ? 'checked' : ''; ?>>
                                </td>
                                <td>
                                    <input type="text" name="upload_fields[<?php echo $index; ?>][name]" 
                                           value="<?php echo htmlspecialchars($field['name']); ?>" readonly>
                                </td>
                                <td>
                                    <input type="text" name="upload_fields[<?php echo $index; ?>][label]" 
                                           value="<?php echo htmlspecialchars($field['label']); ?>">
                                </td>
                                <td>
                                    <select name="upload_fields[<?php echo $index; ?>][type]">
                                        <option value="text" <?php echo $field['type'] === 'text' ? 'selected' : ''; ?>>Text</option>
                                        <option value="textarea" <?php echo $field['type'] === 'textarea' ? 'selected' : ''; ?>>Textarea</option>
                                        <option value="select" <?php echo $field['type'] === 'select' ? 'selected' : ''; ?>>Select</option>
                                        <option value="number" <?php echo $field['type'] === 'number' ? 'selected' : ''; ?>>Number</option>
                                        <option value="checkbox" <?php echo $field['type'] === 'checkbox' ? 'selected' : ''; ?>>Checkbox</option>
                                        <option value="file" <?php echo $field['type'] === 'file' ? 'selected' : ''; ?>>File</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="checkbox" name="upload_fields[<?php echo $index; ?>][required]" value="1" 
                                           <?php echo ($field['required'] ?? false) ? 'checked' : ''; ?>>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 20px;">
                        <button type="submit" name="save_upload_fields" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Upload Fields
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Profile Form Fields -->
    <div id="profile-tab" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h2>Profile Form Fields</h2>
                <p>Configure fields that appear on the artist profile edit form</p>
            </div>
            <div class="card-body">
                <form method="POST" id="profileFieldsForm">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Enabled</th>
                                <th>Field Name</th>
                                <th>Label</th>
                                <th>Type</th>
                                <th>Required</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profile_fields as $index => $field): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="profile_fields[<?php echo $index; ?>][enabled]" value="1" 
                                           <?php echo ($field['enabled'] ?? true) ? 'checked' : ''; ?>>
                                </td>
                                <td>
                                    <input type="text" name="profile_fields[<?php echo $index; ?>][name]" 
                                           value="<?php echo htmlspecialchars($field['name']); ?>" readonly>
                                </td>
                                <td>
                                    <input type="text" name="profile_fields[<?php echo $index; ?>][label]" 
                                           value="<?php echo htmlspecialchars($field['label']); ?>">
                                </td>
                                <td>
                                    <select name="profile_fields[<?php echo $index; ?>][type]">
                                        <option value="text" <?php echo $field['type'] === 'text' ? 'selected' : ''; ?>>Text</option>
                                        <option value="textarea" <?php echo $field['type'] === 'textarea' ? 'selected' : ''; ?>>Textarea</option>
                                        <option value="url" <?php echo $field['type'] === 'url' ? 'selected' : ''; ?>>URL</option>
                                        <option value="file" <?php echo $field['type'] === 'file' ? 'selected' : ''; ?>>File</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="checkbox" name="profile_fields[<?php echo $index; ?>][required]" value="1" 
                                           <?php echo ($field['required'] ?? false) ? 'checked' : ''; ?>>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 20px;">
                        <button type="submit" name="save_profile_fields" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Profile Fields
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
    border-bottom: 2px solid #ddd;
}

.tab-btn {
    padding: 12px 24px;
    background: white;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 15px;
    font-weight: 500;
    color: #666;
    transition: all 0.3s;
}

.tab-btn:hover {
    color: #333;
    background: #f8f9fa;
}

.tab-btn.active {
    color: #007bff;
    border-bottom-color: #007bff;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.data-table th {
    background: #f8f9fa;
    font-weight: 600;
}

.data-table input[type="text"],
.data-table select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.data-table input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}
</style>

<script>
function switchFormTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Deactivate all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Activate button
    event.target.classList.add('active');
}
</script>

<?php include 'includes/footer.php'; ?>

