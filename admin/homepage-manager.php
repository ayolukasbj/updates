<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Homepage Management';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Create homepage_sections table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS homepage_sections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            section_type VARCHAR(50) NOT NULL,
            section_title VARCHAR(200),
            section_key VARCHAR(100) NOT NULL UNIQUE,
            content_type VARCHAR(50) NOT NULL,
            content_filter VARCHAR(200),
            limit_count INT DEFAULT 6,
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            display_position VARCHAR(50) DEFAULT 'main',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Insert default sections if table is empty
    $checkStmt = $conn->query("SELECT COUNT(*) as count FROM homepage_sections");
    $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count == 0) {
        $defaultSections = [
            ['featured_news', 'Featured News', 'news', '', 1, 1, 'featured'],
            ['hot_chart', 'Uganda Hot 100 Chart', 'songs', 'top_chart', 5, 2, 'main'],
            ['music_updates', 'Music Updates', 'songs', 'new', 8, 3, 'main'],
            ['entertainment', 'Entertainment', 'news', '', 6, 4, 'main'],
        ];
        
        $insertStmt = $conn->prepare("INSERT INTO homepage_sections (section_key, section_title, content_type, content_filter, limit_count, sort_order, display_position) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($defaultSections as $section) {
            $insertStmt->execute($section);
        }
    }
} catch (Exception $e) {
    // Table might already exist
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $section_key = trim($_POST['section_key'] ?? '');
                $section_title = trim($_POST['section_title'] ?? '');
                $content_type = $_POST['content_type'] ?? 'songs';
                $content_filter = trim($_POST['content_filter'] ?? '');
                $limit_count = (int)($_POST['limit_count'] ?? 6);
                $sort_order = (int)($_POST['sort_order'] ?? 0);
                $display_position = $_POST['display_position'] ?? 'main';
                
                if (empty($section_key) || empty($section_title)) {
                    $error = 'Section key and title are required';
                } else {
                    $stmt = $conn->prepare("INSERT INTO homepage_sections (section_key, section_title, content_type, content_filter, limit_count, sort_order, display_position) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$section_key, $section_title, $content_type, $content_filter, $limit_count, $sort_order, $display_position]);
                    $success = 'Section added successfully!';
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['id'] ?? 0);
                $section_key = trim($_POST['section_key'] ?? '');
                $section_title = trim($_POST['section_title'] ?? '');
                $content_type = $_POST['content_type'] ?? 'songs';
                $content_filter = trim($_POST['content_filter'] ?? '');
                $limit_count = (int)($_POST['limit_count'] ?? 6);
                $sort_order = (int)($_POST['sort_order'] ?? 0);
                $display_position = $_POST['display_position'] ?? 'main';
                
                if (empty($section_key) || empty($section_title)) {
                    $error = 'Section key and title are required';
                } else {
                    $stmt = $conn->prepare("UPDATE homepage_sections SET section_key = ?, section_title = ?, content_type = ?, content_filter = ?, limit_count = ?, sort_order = ?, display_position = ? WHERE id = ?");
                    $stmt->execute([$section_key, $section_title, $content_type, $content_filter, $limit_count, $sort_order, $display_position, $id]);
                    $success = 'Section updated successfully!';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $conn->prepare("DELETE FROM homepage_sections WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Section deleted successfully!';
                break;
                
            case 'toggle':
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $conn->prepare("UPDATE homepage_sections SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Section status updated!';
                break;
                
            case 'reorder':
                if (!empty($_POST['order'])) {
                    $order = json_decode($_POST['order'], true);
                    $stmt = $conn->prepare("UPDATE homepage_sections SET sort_order = ? WHERE id = ?");
                    foreach ($order as $index => $id) {
                        $stmt->execute([$index + 1, $id]);
                    }
                    $success = 'Section order updated!';
                }
                break;
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = 'Section key already exists';
        } else {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get all sections
$stmt = $conn->query("SELECT * FROM homepage_sections ORDER BY sort_order ASC, id ASC");
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available genres (check if genre column exists)
$genres = [];
try {
    $checkStmt = $conn->query("SHOW COLUMNS FROM songs LIKE 'genre'");
    if ($checkStmt->rowCount() > 0) {
        $genresStmt = $conn->query("SELECT DISTINCT genre FROM songs WHERE genre IS NOT NULL AND genre != '' ORDER BY genre ASC");
        $genres = $genresStmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {
    $genres = [];
    error_log("Genre column not found: " . $e->getMessage());
}

// Get available news categories
$newsCatStmt = $conn->query("SELECT name FROM news_categories WHERE is_active = 1 ORDER BY name ASC");
$newsCategories = $newsCatStmt->fetchAll(PDO::FETCH_COLUMN);

include 'includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-home"></i> Homepage Management</h1>
    <p>Configure which sections appear on the homepage and what content they display</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<!-- Add/Edit Form -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2 id="form-title">Add New Section</h2>
    </div>
    <div class="card-body">
        <form method="POST" id="section-form">
            <input type="hidden" name="action" id="form-action" value="add">
            <input type="hidden" name="id" id="form-id" value="">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>Section Key (unique identifier) *</label>
                    <input type="text" name="section_key" id="form-section-key" class="form-control" required placeholder="e.g., featured_news">
                    <small style="color: #666;">Used internally, must be unique</small>
                </div>
                
                <div class="form-group">
                    <label>Section Title *</label>
                    <input type="text" name="section_title" id="form-section-title" class="form-control" required placeholder="e.g., Featured News">
                    <small style="color: #666;">Displayed as section header</small>
                </div>
                
                <div class="form-group">
                    <label>Content Type *</label>
                    <select name="content_type" id="form-content-type" class="form-control" required onchange="updateFilterOptions()">
                        <option value="songs">Songs</option>
                        <option value="news">News</option>
                        <option value="artists">Artists</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Content Filter</label>
                    <select name="content_filter" id="form-content-filter" class="form-control">
                        <option value="">All / Default</option>
                        <option value="top_chart">Top Chart (by plays)</option>
                        <option value="new">Newest First</option>
                        <option value="trending">Trending (recent plays)</option>
                        <option value="featured">Featured Only</option>
                    </select>
                    <small style="color: #666;" id="filter-help">Filter options depend on content type</small>
                </div>
                
                <div class="form-group">
                    <label>Limit (Items to Show)</label>
                    <input type="number" name="limit_count" id="form-limit-count" class="form-control" value="6" min="1" max="50">
                    <small style="color: #666;">Number of items to display</small>
                </div>
                
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" id="form-sort-order" class="form-control" value="0" min="0">
                    <small style="color: #666;">Lower numbers appear first</small>
                </div>
                
                <div class="form-group">
                    <label>Display Position</label>
                    <select name="display_position" id="form-display-position" class="form-control">
                        <option value="featured">Featured (Top)</option>
                        <option value="main">Main Content</option>
                        <option value="sidebar">Sidebar</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group" id="genre-filter-group" style="display: none;">
                <label>Filter by Genre</label>
                <select name="genre_filter" id="form-genre-filter" class="form-control">
                    <option value="">All Genres</option>
                    <?php foreach ($genres as $genre): ?>
                    <option value="<?php echo htmlspecialchars($genre); ?>"><?php echo htmlspecialchars($genre); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" id="category-filter-group" style="display: none;">
                <label>Filter by News Category</label>
                <select name="category_filter" id="form-category-filter" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($newsCategories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Section
                </button>
                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Sections List -->
<div class="card">
    <div class="card-header">
        <h2>Homepage Sections (<?php echo count($sections); ?>)</h2>
        <p style="margin: 10px 0 0; font-size: 14px; color: #666;">
            Drag sections to reorder. Sections are displayed on the homepage in the order shown below.
        </p>
    </div>
    <div class="card-body">
        <div id="sections-list" style="min-height: 100px;">
            <?php if (empty($sections)): ?>
            <div style="text-align: center; color: #999; padding: 40px;">
                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; display: block; opacity: 0.5;"></i>
                <p>No sections configured yet. Add one above!</p>
            </div>
            <?php else: ?>
                <?php foreach ($sections as $section): ?>
                <div class="section-item" data-id="<?php echo $section['id']; ?>" style="background: white; border: 2px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 15px; cursor: move; transition: all 0.3s;" onmouseover="this.style.borderColor='#667eea'; this.style.boxShadow='0 2px 8px rgba(102,126,234,0.2)';" onmouseout="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                        <div style="display: flex; align-items: center; gap: 15px; flex: 1;">
                            <div style="cursor: move; color: #999; font-size: 20px;">
                                <i class="fas fa-grip-vertical"></i>
                            </div>
                            <div>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                    <h3 style="margin: 0; font-size: 18px; font-weight: 600;"><?php echo htmlspecialchars($section['section_title']); ?></h3>
                                    <?php if (!$section['is_active']): ?>
                                    <span class="badge badge-warning">Inactive</span>
                                    <?php endif; ?>
                                    <span class="badge badge-info"><?php echo ucfirst($section['display_position']); ?></span>
                                </div>
                                <div style="font-size: 14px; color: #666;">
                                    <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;"><?php echo htmlspecialchars($section['section_key']); ?></code>
                                    <span style="margin: 0 10px;">•</span>
                                    <strong>Type:</strong> <?php echo ucfirst($section['content_type']); ?>
                                    <span style="margin: 0 10px;">•</span>
                                    <strong>Filter:</strong> <?php echo $section['content_filter'] ? ucfirst(str_replace('_', ' ', $section['content_filter'])) : 'None'; ?>
                                    <span style="margin: 0 10px;">•</span>
                                    <strong>Limit:</strong> <?php echo $section['limit_count']; ?>
                                    <span style="margin: 0 10px;">•</span>
                                    <strong>Order:</strong> <?php echo $section['sort_order']; ?>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="btn btn-sm btn-primary" onclick="editSection(<?php echo htmlspecialchars(json_encode($section)); ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?php echo $section['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-<?php echo $section['is_active'] ? 'warning' : 'success'; ?>">
                                    <i class="fas fa-<?php echo $section['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                </button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this section?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $section['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let draggedElement = null;

// Make sections draggable
document.addEventListener('DOMContentLoaded', function() {
    const sections = document.querySelectorAll('.section-item');
    sections.forEach(section => {
        section.setAttribute('draggable', true);
        section.addEventListener('dragstart', handleDragStart);
        section.addEventListener('dragover', handleDragOver);
        section.addEventListener('drop', handleDrop);
        section.addEventListener('dragend', handleDragEnd);
    });
});

function handleDragStart(e) {
    draggedElement = this;
    this.style.opacity = '0.5';
    e.dataTransfer.effectAllowed = 'move';
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    e.dataTransfer.dropEffect = 'move';
    return false;
}

function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    
    if (draggedElement !== this) {
        const allSections = Array.from(document.querySelectorAll('.section-item'));
        const draggedIndex = allSections.indexOf(draggedElement);
        const targetIndex = allSections.indexOf(this);
        
        if (draggedIndex < targetIndex) {
            this.parentNode.insertBefore(draggedElement, this.nextSibling);
        } else {
            this.parentNode.insertBefore(draggedElement, this);
        }
        
        // Update order in database
        const newOrder = Array.from(document.querySelectorAll('.section-item')).map(el => el.dataset.id);
        saveOrder(newOrder);
    }
    
    return false;
}

function handleDragEnd(e) {
    this.style.opacity = '1';
    draggedElement = null;
}

function saveOrder(order) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="reorder">
        <input type="hidden" name="order" value='${JSON.stringify(order)}'>
    `;
    document.body.appendChild(form);
    form.submit();
}

function editSection(section) {
    document.getElementById('form-title').textContent = 'Edit Section';
    document.getElementById('form-action').value = 'edit';
    document.getElementById('form-id').value = section.id;
    document.getElementById('form-section-key').value = section.section_key || '';
    document.getElementById('form-section-title').value = section.section_title || '';
    document.getElementById('form-content-type').value = section.content_type || 'songs';
    document.getElementById('form-content-filter').value = section.content_filter || '';
    document.getElementById('form-limit-count').value = section.limit_count || 6;
    document.getElementById('form-sort-order').value = section.sort_order || 0;
    document.getElementById('form-display-position').value = section.display_position || 'main';
    
    updateFilterOptions();
    
    // Scroll to form
    document.getElementById('section-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function resetForm() {
    document.getElementById('form-title').textContent = 'Add New Section';
    document.getElementById('form-action').value = 'add';
    document.getElementById('form-id').value = '';
    document.getElementById('section-form').reset();
    document.getElementById('form-content-type').value = 'songs';
    document.getElementById('form-content-filter').value = '';
    document.getElementById('form-limit-count').value = 6;
    document.getElementById('form-sort-order').value = 0;
    document.getElementById('form-display-position').value = 'main';
    updateFilterOptions();
}

function updateFilterOptions() {
    const contentType = document.getElementById('form-content-type').value;
    const genreGroup = document.getElementById('genre-filter-group');
    const categoryGroup = document.getElementById('category-filter-group');
    const filterSelect = document.getElementById('form-content-filter');
    const filterHelp = document.getElementById('filter-help');
    
    // Show/hide filter groups
    genreGroup.style.display = contentType === 'songs' ? 'block' : 'none';
    categoryGroup.style.display = contentType === 'news' ? 'block' : 'none';
    
    // Update filter options
    filterSelect.innerHTML = '<option value="">All / Default</option>';
    
    if (contentType === 'songs') {
        filterSelect.innerHTML += `
            <option value="top_chart">Top Chart (by plays)</option>
            <option value="new">Newest First</option>
            <option value="trending">Trending (recent plays)</option>
            <option value="featured">Featured Only</option>
        `;
        filterHelp.textContent = 'Filter songs by popularity, date, or featured status';
    } else if (contentType === 'news') {
        filterSelect.innerHTML += `
            <option value="featured">Featured Only</option>
            <option value="new">Newest First</option>
        `;
        filterHelp.textContent = 'Filter news by featured status or date';
    } else if (contentType === 'artists') {
        filterSelect.innerHTML += `
            <option value="verified">Verified Artists</option>
            <option value="top">Top Artists (by songs)</option>
        `;
        filterHelp.textContent = 'Filter artists by verification or popularity';
    }
}
</script>

<style>
.section-item {
    user-select: none;
}
.section-item:hover {
    transform: translateY(-2px);
}
</style>

<?php include 'includes/footer.php'; ?>
