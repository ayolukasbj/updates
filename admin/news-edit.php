<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$edit_mode = isset($_GET['id']);
$news_id = $_GET['id'] ?? 0;
$news = null;
$success = '';
$error = '';

// Get categories from news_categories table
$categories = [];
try {
    $catStmt = $conn->query("SELECT name FROM news_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // If news_categories table doesn't exist, use fallback categories
    $categories = ['Entertainment', 'National News', 'Exclusive', 'Hot', 'Politics', 'Shocking', 'Celebrity Gossip', 'Just in', 'Lifestyle and Events'];
}

// If editing, fetch news
if ($edit_mode) {
    $stmt = $conn->prepare("SELECT * FROM news WHERE id = ?");
    $stmt->execute([$news_id]);
    $news = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$news) {
        header('Location: news.php');
        exit;
    }
    $page_title = 'Edit News';
} else {
    $page_title = 'Add News';
}

// Ensure co_author column exists in news table
try {
    $conn->exec("ALTER TABLE news ADD COLUMN co_author VARCHAR(255) DEFAULT NULL");
} catch (Exception $e) {
    // Column might already exist, ignore error
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $share_excerpt = trim($_POST['share_excerpt'] ?? '');
    $co_author = trim($_POST['co_author'] ?? '');
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    // Generate slug from title
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    
    // Handle image upload
    $image_path = $news['image'] ?? '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '_news.' . $file_ext;
        $upload_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            $image_path = 'uploads/images/' . $file_name;
        }
    }
    
    if (empty($title) || empty($content)) {
        $error = 'Title and content are required';
    } else {
        try {
            if ($edit_mode) {
                // Update - Core Author System: Admin is Priority 1 (always the author when publishing)
                // When publishing, always set author_id to admin's ID (admin is the core author)
                if ($is_published == 1) {
                    // Always set author_id to admin's ID when publishing (admin is priority 1)
                    // Check if share_excerpt column exists
                    try {
                        $checkCol = $conn->query("SHOW COLUMNS FROM news LIKE 'share_excerpt'");
                        if ($checkCol->rowCount() == 0) {
                            $conn->exec("ALTER TABLE news ADD COLUMN share_excerpt TEXT NULL AFTER excerpt");
                        }
                    } catch (Exception $e) {
                        error_log("Error checking/adding share_excerpt column: " . $e->getMessage());
                    }
                    
                    $stmt = $conn->prepare("
                        UPDATE news SET 
                            title = ?, slug = ?, category = ?, image = ?, 
                            content = ?, excerpt = ?, share_excerpt = ?, co_author = ?, is_published = ?, featured = ?, author_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $slug, $category, $image_path, $content, $excerpt, $share_excerpt, $co_author, $is_published, $featured, $_SESSION['user_id'], $news_id]);
                } else {
                    // Not publishing, just update without changing author_id
                    // Check if share_excerpt column exists
                    try {
                        $checkCol = $conn->query("SHOW COLUMNS FROM news LIKE 'share_excerpt'");
                        if ($checkCol->rowCount() == 0) {
                            $conn->exec("ALTER TABLE news ADD COLUMN share_excerpt TEXT NULL AFTER excerpt");
                        }
                    } catch (Exception $e) {
                        error_log("Error checking/adding share_excerpt column: " . $e->getMessage());
                    }
                    
                    $stmt = $conn->prepare("
                        UPDATE news SET 
                            title = ?, slug = ?, category = ?, image = ?, 
                            content = ?, excerpt = ?, share_excerpt = ?, co_author = ?, is_published = ?, featured = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $slug, $category, $image_path, $content, $excerpt, $share_excerpt, $co_author, $is_published, $featured, $news_id]);
                }
                
                $success = 'News article updated successfully';
                logAdminActivity($_SESSION['user_id'], 'update_news', 'news', $news_id, "Updated: $title");
                
                // Refresh news data
                $stmt = $conn->prepare("SELECT * FROM news WHERE id = ?");
                $stmt->execute([$news_id]);
                $news = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // Insert
                // Check if share_excerpt column exists
                try {
                    $checkCol = $conn->query("SHOW COLUMNS FROM news LIKE 'share_excerpt'");
                    if ($checkCol->rowCount() == 0) {
                        $conn->exec("ALTER TABLE news ADD COLUMN share_excerpt TEXT NULL AFTER excerpt");
                    }
                } catch (Exception $e) {
                    error_log("Error checking/adding share_excerpt column: " . $e->getMessage());
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO news (title, slug, category, image, content, excerpt, share_excerpt, co_author, is_published, featured, author_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $slug, $category, $image_path, $content, $excerpt, $share_excerpt, $co_author, $is_published, $featured, $_SESSION['user_id']]);
                
                $news_id = $conn->lastInsertId();
                $success = 'News article created successfully';
                logAdminActivity($_SESSION['user_id'], 'create_news', 'news', $news_id, "Created: $title");
                
                // Redirect to edit mode
                header("Location: news-edit.php?id=$news_id&success=1");
                exit;
            }
        } catch (Exception $e) {
            $error = 'Error saving news article: ' . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1><?php echo $edit_mode ? 'Edit News Article' : 'Add New Article'; ?></h1>
    <p><?php echo $edit_mode ? 'Update the news article' : 'Create a new news article'; ?></p>
</div>

<?php if ($success || isset($_GET['success'])): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo $success ?: 'News article saved successfully'; ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
        <!-- Main Content -->
        <div>
            <div class="card">
                <div class="card-header">
                    <h2>Article Content</h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" class="form-control" required 
                               value="<?php echo htmlspecialchars($news['title'] ?? ''); ?>" 
                               placeholder="Enter article title">
                    </div>
                    
                    <div class="form-group">
                        <label>Excerpt</label>
                        <textarea name="excerpt" class="form-control" rows="3" 
                                  placeholder="Brief summary of the article"><?php echo htmlspecialchars($news['excerpt'] ?? ''); ?></textarea>
                        <small style="color: #6b7280;">Short description shown in article listings</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Share Excerpt (for social media sharing)</label>
                        <textarea name="share_excerpt" class="form-control" rows="3" 
                                  placeholder="Custom description for when this article is shared on social media (Facebook, Twitter, etc.). If left empty, will use excerpt or auto-generated text."><?php echo htmlspecialchars($news['share_excerpt'] ?? ''); ?></textarea>
                        <small style="color: #6b7280;">This text will appear when the article is shared on social media. Keep it under 200 characters for best results.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Content *</label>
                        <!-- Custom Rich Text Editor -->
                        <div class="custom-editor-wrapper">
                            <div class="editor-toolbar">
                                <div class="toolbar-group">
                                    <button type="button" class="toolbar-btn" data-command="undo" title="Undo">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" data-command="redo" title="Redo">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                </div>
                                <div class="toolbar-separator"></div>
                                <div class="toolbar-group">
                                    <button type="button" class="toolbar-btn" data-command="bold" title="Bold">
                                        <i class="fas fa-bold"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" data-command="italic" title="Italic">
                                        <i class="fas fa-italic"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" data-command="underline" title="Underline">
                                        <i class="fas fa-underline"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" data-command="strikeThrough" title="Strikethrough">
                                        <i class="fas fa-strikethrough"></i>
                                    </button>
                                </div>
                                <div class="toolbar-separator"></div>
                                <div class="toolbar-group">
                                    <select class="toolbar-select" id="formatSelect" title="Format">
                                        <option value="">Format</option>
                                        <option value="h1">Heading 1</option>
                                        <option value="h2">Heading 2</option>
                                        <option value="h3">Heading 3</option>
                                        <option value="h4">Heading 4</option>
                                        <option value="p">Paragraph</option>
                                    </select>
                                </div>
                                <div class="toolbar-separator"></div>
                                <div class="toolbar-group">
                                    <button type="button" class="toolbar-btn" data-command="justifyLeft" title="Align Left">
                                        <i class="fas fa-align-left"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" data-command="justifyCenter" title="Align Center">
                                        <i class="fas fa-align-center"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" data-command="justifyRight" title="Align Right">
                                        <i class="fas fa-align-right"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" data-command="justifyFull" title="Justify">
                                        <i class="fas fa-align-justify"></i>
                                    </button>
                                </div>
                                <div class="toolbar-separator"></div>
                                <div class="toolbar-group">
                                    <button type="button" class="toolbar-btn" data-command="insertUnorderedList" title="Bullet List">
                                        <i class="fas fa-list-ul"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" data-command="insertOrderedList" title="Numbered List">
                                        <i class="fas fa-list-ol"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" data-command="outdent" title="Decrease Indent">
                                        <i class="fas fa-outdent"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" data-command="indent" title="Increase Indent">
                                        <i class="fas fa-indent"></i>
                                    </button>
                                </div>
                                <div class="toolbar-separator"></div>
                                <div class="toolbar-group">
                                    <button type="button" class="toolbar-btn" id="linkBtn" title="Insert Link">
                                        <i class="fas fa-link"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" id="imageBtn" title="Insert Image">
                                        <i class="fas fa-image"></i>
                                    </button>
                                    <input type="file" id="imageInput" accept="image/*" style="display: none;">
                                </div>
                                <div class="toolbar-separator"></div>
                                <div class="toolbar-group">
                                    <button type="button" class="toolbar-btn" data-command="removeFormat" title="Remove Formatting">
                                        <i class="fas fa-eraser"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" id="codeViewBtn" title="Code View">
                                        <i class="fas fa-code"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="editor-content-wrapper">
                                <div id="editor-content" class="editor-content" contenteditable="true" 
                                     placeholder="Write your article content here..."></div>
                                <textarea name="content" id="news-content" class="form-control" 
                                          style="display: none;"><?php echo isset($news['content']) ? str_replace('</textarea>', '&lt;/textarea&gt;', $news['content']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div>
            <!-- Publish Settings -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header">
                    <h2>Publish Settings</h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="is_published" value="1" 
                                   <?php echo ($news['is_published'] ?? 1) ? 'checked' : ''; ?>>
                            <span>Publish article</span>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="featured" value="1" 
                                   <?php echo ($news['featured'] ?? 0) ? 'checked' : ''; ?>>
                            <span>Featured article</span>
                        </label>
                    </div>
                    
                    <?php if ($edit_mode): ?>
                    <div style="padding-top: 15px; border-top: 1px solid #e5e7eb;">
                        <small style="color: #6b7280;">
                            <strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($news['created_at'])); ?><br>
                            <strong>Views:</strong> <?php echo number_format($news['views']); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Category -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header">
                    <h2>Category</h2>
                </div>
                <div class="card-body">
                    <div class="form-group" style="margin: 0;">
                        <select name="category" class="form-control">
                            <option value="">Select category</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($news['category'] ?? '') === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Co-Author -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header">
                    <h2>Co-Author</h2>
                </div>
                <div class="card-body">
                    <div class="form-group" style="margin: 0; position: relative;">
                        <input type="text" name="co_author" id="co-author-input" class="form-control" 
                               value="<?php echo htmlspecialchars($news['co_author'] ?? ''); ?>" 
                               placeholder="Start typing to search users/artists... (optional)"
                               autocomplete="off">
                        <div id="co-author-suggestions" class="autocomplete-suggestions" style="display: none;"></div>
                        <small style="color: #6b7280;">Optional: Add a co-author name to display alongside the main author</small>
                    </div>
                </div>
            </div>
            
            <!-- Featured Image -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header">
                    <h2>Featured Image</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($news['image'])): ?>
                    <div style="margin-bottom: 15px;">
                        <img src="../<?php echo htmlspecialchars($news['image']); ?>" 
                             alt="Current image" 
                             style="width: 100%; height: auto; border-radius: 6px;">
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group" style="margin: 0;">
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <small style="color: #6b7280;">Recommended: 800x450px (16:9 ratio)</small>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                        <i class="fas fa-save"></i> <?php echo $edit_mode ? 'Update Article' : 'Create Article'; ?>
                    </button>
                    <a href="news.php" class="btn btn-warning" style="width: 100%;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Custom Rich Text Editor Styles -->
<style>
.custom-editor-wrapper {
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    background: white;
    overflow: hidden;
}

.editor-toolbar {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 10px 12px;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
    flex-wrap: wrap;
}

.toolbar-group {
    display: flex;
    align-items: center;
    gap: 3px;
}

.toolbar-separator {
    width: 1px;
    height: 24px;
    background: #e5e7eb;
    margin: 0 5px;
}

.toolbar-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    color: #6b7280;
    cursor: pointer;
    border-radius: 4px;
    transition: all 0.2s;
    font-size: 14px;
}

.toolbar-btn:hover {
    background: #e5e7eb;
    color: #1f2937;
}

.toolbar-btn.active {
    background: #667eea;
    color: white;
}

.toolbar-select {
    padding: 6px 10px;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    background: white;
    color: #1f2937;
    font-size: 13px;
    cursor: pointer;
    min-width: 120px;
}

.toolbar-select:focus {
    outline: none;
    border-color: #667eea;
}

.editor-content-wrapper {
    position: relative;
}

.editor-content {
    min-height: 500px;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-size: 14px;
    line-height: 1.6;
    color: #1f2937;
    outline: none;
    overflow-y: auto;
}

.editor-content:empty:before {
    content: attr(placeholder);
    color: #9ca3af;
    pointer-events: none;
}

.editor-content p {
    margin: 0 0 12px 0;
}

.editor-content h1, .editor-content h2, .editor-content h3, .editor-content h4 {
    margin: 20px 0 12px 0;
    font-weight: 600;
}

.editor-content h1 { font-size: 28px; }
.editor-content h2 { font-size: 24px; }
.editor-content h3 { font-size: 20px; }
.editor-content h4 { font-size: 18px; }

.editor-content ul, .editor-content ol {
    margin: 12px 0;
    padding-left: 30px;
}

.editor-content img {
    max-width: 100%;
    height: auto;
    border-radius: 6px;
    margin: 12px 0;
}

.editor-content a {
    color: #667eea;
    text-decoration: underline;
}

.editor-content code {
    background: #f3f4f6;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
}

.code-view {
    font-family: 'Courier New', monospace;
    font-size: 13px;
    background: #1f2937;
    color: #f9fafb;
    padding: 20px;
    white-space: pre-wrap;
    word-wrap: break-word;
}

/* Link and Image Modal Styles */
.editor-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.editor-modal.active {
    display: flex;
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 10px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.modal-header {
    margin-bottom: 20px;
}

.modal-header h3 {
    margin: 0;
    color: #1f2937;
    font-size: 20px;
}

.modal-body {
    margin-bottom: 20px;
}

.modal-body label {
    display: block;
    margin-bottom: 8px;
    color: #6b7280;
    font-size: 14px;
    font-weight: 500;
}

.modal-body input {
    width: 100%;
    padding: 10px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 14px;
}

.modal-body input:focus {
    outline: none;
    border-color: #667eea;
}

.modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.modal-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
}

.modal-btn-primary {
    background: #667eea;
    color: white;
}

.modal-btn-primary:hover {
    background: #5568d3;
}

.modal-btn-secondary {
    background: #f3f4f6;
    color: #6b7280;
}

.modal-btn-secondary:hover {
    background: #e5e7eb;
}

@media (max-width: 768px) {
    .editor-toolbar {
        padding: 8px;
    }
    
    .toolbar-btn {
        width: 28px;
        height: 28px;
        font-size: 12px;
    }
    
    .toolbar-select {
        min-width: 100px;
        font-size: 12px;
        padding: 5px 8px;
    }
    
    .editor-content {
        min-height: 400px;
        padding: 15px;
        font-size: 16px; /* Prevents zoom on iOS */
    }
    
    .modal-content {
        padding: 20px;
        width: 95%;
    }
}
</style>

<!-- Custom Rich Text Editor Script -->
<script>
(function() {
    const editorContent = document.getElementById('editor-content');
    const hiddenTextarea = document.getElementById('news-content');
    const formatSelect = document.getElementById('formatSelect');
    const linkBtn = document.getElementById('linkBtn');
    const imageBtn = document.getElementById('imageBtn');
    const imageInput = document.getElementById('imageInput');
    const codeViewBtn = document.getElementById('codeViewBtn');
    
    // Initialize editor with existing content
    let existingContent = hiddenTextarea.value;
    // Decode the textarea tag if it was escaped
    if (existingContent) {
        existingContent = existingContent.replace(/&lt;\/textarea&gt;/gi, '</textarea>');
    }
    
    if (existingContent && existingContent.trim()) {
        editorContent.innerHTML = existingContent;
    }
    
    // History for undo/redo
    let history = [existingContent || ''];
    let historyIndex = 0;
    
    function saveHistory() {
        const current = editorContent.innerHTML;
        history = history.slice(0, historyIndex + 1);
        history.push(current);
        historyIndex = history.length - 1;
        if (history.length > 50) {
            history.shift();
            historyIndex--;
        }
        syncToTextarea();
    }
    
    function syncToTextarea() {
        // Get the actual HTML content from the editor
        let content = editorContent.innerHTML;
        
        // Clean up empty paragraphs and br tags only
        content = content.replace(/<p><br><\/p>/gi, '');
        content = content.replace(/<p>\s*<\/p>/gi, '');
        content = content.trim();
        
        // Set the textarea value
        hiddenTextarea.value = content || '';
    }
    
    // Execute command
    function execCommand(command, value = null) {
        document.execCommand(command, false, value);
        editorContent.focus();
        saveHistory();
        updateToolbarState();
    }
    
    // Update toolbar button states
    function updateToolbarState() {
        const commands = ['bold', 'italic', 'underline', 'strikeThrough', 'justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'];
        commands.forEach(cmd => {
            const btn = document.querySelector(`[data-command="${cmd}"]`);
            if (btn) {
                try {
                    if (document.queryCommandState(cmd)) {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                } catch(e) {}
            }
        });
    }
    
    // Toolbar button clicks
    document.querySelectorAll('.toolbar-btn[data-command]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const command = this.getAttribute('data-command');
            
            if (command === 'undo') {
                if (historyIndex > 0) {
                    historyIndex--;
                    editorContent.innerHTML = history[historyIndex];
                    syncToTextarea();
                }
            } else if (command === 'redo') {
                if (historyIndex < history.length - 1) {
                    historyIndex++;
                    editorContent.innerHTML = history[historyIndex];
                    syncToTextarea();
                }
            } else {
                execCommand(command);
            }
        });
    });
    
    // Format select change
    formatSelect.addEventListener('change', function() {
        const value = this.value;
        if (value) {
            execCommand('formatBlock', value);
        }
        this.value = '';
    });
    
    // Link button
    linkBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const selection = window.getSelection();
        const selectedText = selection.toString();
        
        const modal = document.createElement('div');
        modal.className = 'editor-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Insert Link</h3>
                </div>
                <div class="modal-body">
                    <label>URL:</label>
                    <input type="url" id="linkUrl" placeholder="https://example.com" value="">
                    <label style="margin-top: 15px;">Link Text:</label>
                    <input type="text" id="linkText" placeholder="Link text" value="${selectedText}">
                </div>
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-secondary" onclick="this.closest('.editor-modal').remove()">Cancel</button>
                    <button class="modal-btn modal-btn-primary" id="insertLinkBtn">Insert</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        modal.classList.add('active');
        
        document.getElementById('linkUrl').focus();
        
        document.getElementById('insertLinkBtn').addEventListener('click', function() {
            const url = document.getElementById('linkUrl').value.trim();
            const text = document.getElementById('linkText').value.trim();
            
            if (url) {
                const linkText = text || url;
                if (selection.rangeCount > 0 && selectedText) {
                    // Replace selected text
                    const range = selection.getRangeAt(0);
                    range.deleteContents();
                    const link = document.createElement('a');
                    link.href = url;
                    link.textContent = linkText;
                    range.insertNode(link);
                } else {
                    // Insert at cursor
                    execCommand('createLink', url);
                    if (linkText !== url) {
                        const link = editorContent.querySelector('a[href="' + url + '"]:not([href*="' + url + '"])');
                        if (!link) {
                            const links = editorContent.querySelectorAll('a');
                            const lastLink = links[links.length - 1];
                            if (lastLink && lastLink.href === url) {
                                lastLink.textContent = linkText;
                            }
                        }
                    }
                }
                editorContent.focus();
                saveHistory();
            }
            modal.remove();
        });
        
        // Close on Escape
        document.addEventListener('keydown', function escHandler(e) {
            if (e.key === 'Escape') {
                modal.remove();
                document.removeEventListener('keydown', escHandler);
            }
        });
    });
    
    // Image button
    imageBtn.addEventListener('click', function(e) {
        e.preventDefault();
        imageInput.click();
    });
    
    imageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const formData = new FormData();
            formData.append('image', file);
            formData.append('action', 'upload_editor_image');
            
            // Show loading
            const loading = document.createElement('div');
            loading.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.8); color: white; padding: 20px; border-radius: 8px; z-index: 10001;';
            loading.textContent = 'Uploading image...';
            document.body.appendChild(loading);
            
            fetch('upload-editor-image.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loading.remove();
                if (data.success && data.url) {
                    const img = document.createElement('img');
                    img.src = data.url;
                    img.style.maxWidth = '100%';
                    img.style.height = 'auto';
                    
                    const selection = window.getSelection();
                    if (selection.rangeCount > 0) {
                        const range = selection.getRangeAt(0);
                        range.insertNode(img);
                    } else {
                        editorContent.appendChild(img);
                    }
                    editorContent.focus();
                    saveHistory();
                } else {
                    alert('Image upload failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                loading.remove();
                alert('Error uploading image: ' + error.message);
            });
            
            // Reset input
            this.value = '';
        }
    });
    
    // Code view toggle
    let codeViewMode = false;
    codeViewBtn.addEventListener('click', function(e) {
        e.preventDefault();
        codeViewMode = !codeViewMode;
        
        if (codeViewMode) {
            editorContent.style.display = 'none';
            const codeTextarea = document.createElement('textarea');
            codeTextarea.id = 'code-view-textarea';
            codeTextarea.className = 'code-view';
            codeTextarea.style.cssText = 'width: 100%; min-height: 500px; padding: 20px; font-family: monospace; font-size: 13px; border: none; resize: vertical;';
            codeTextarea.value = editorContent.innerHTML;
            editorContent.parentNode.insertBefore(codeTextarea, editorContent);
            
            codeTextarea.addEventListener('blur', function() {
                editorContent.innerHTML = this.value;
                codeTextarea.remove();
                editorContent.style.display = 'block';
                codeViewMode = false;
                saveHistory();
            });
            
            codeTextarea.focus();
            this.classList.add('active');
        } else {
            const codeTextarea = document.getElementById('code-view-textarea');
            if (codeTextarea) {
                editorContent.innerHTML = codeTextarea.value;
                codeTextarea.remove();
                editorContent.style.display = 'block';
                saveHistory();
            }
            this.classList.remove('active');
        }
    });
    
    // Editor events
    editorContent.addEventListener('input', function() {
        saveHistory();
    });
    
    editorContent.addEventListener('keyup', function() {
        updateToolbarState();
    });
    
    editorContent.addEventListener('mouseup', function() {
        updateToolbarState();
    });
    
    // Sync before form submit - ensure it happens before validation
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Force sync immediately
            syncToTextarea();
            
            // Validate content - check if there's actual text content
            const content = editorContent.innerHTML;
            const textContent = editorContent.textContent || editorContent.innerText || '';
            const hasContent = textContent.trim().length > 0;
            
            if (!hasContent) {
                e.preventDefault();
                alert('Please enter article content.');
                editorContent.focus();
                return false;
            }
            
            // Final sync to ensure textarea has the latest content
            hiddenTextarea.value = editorContent.innerHTML;
            return true;
        });
    }
    
    // Sync on any change
    editorContent.addEventListener('blur', function() {
        syncToTextarea();
    });
    
    // Initial sync after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(syncToTextarea, 100);
        });
    } else {
        setTimeout(syncToTextarea, 100);
    }
    
    // Also sync on page unload
    window.addEventListener('beforeunload', function() {
        syncToTextarea();
    });
})();

// Co-Author Autocomplete
(function() {
    const input = document.getElementById('co-author-input');
    const suggestions = document.getElementById('co-author-suggestions');
    let searchTimeout = null;
    let selectedIndex = -1;
    
    if (!input || !suggestions) return;
    
    // CSS Styles for autocomplete
    const style = document.createElement('style');
    style.textContent = `
        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            margin-top: 4px;
        }
        .autocomplete-suggestion {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .autocomplete-suggestion:last-child {
            border-bottom: none;
        }
        .autocomplete-suggestion:hover,
        .autocomplete-suggestion.highlighted {
            background: #f3f4f6;
        }
        .autocomplete-suggestion .type-badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .autocomplete-suggestion .type-badge.user {
            background: #dbeafe;
            color: #1e40af;
        }
        .autocomplete-suggestion .type-badge.artist {
            background: #fef3c7;
            color: #92400e;
        }
        .autocomplete-suggestion .name {
            flex: 1;
            font-weight: 500;
            color: #1f2937;
        }
    `;
    document.head.appendChild(style);
    
    function hideSuggestions() {
        suggestions.style.display = 'none';
        selectedIndex = -1;
    }
    
    function showSuggestions() {
        suggestions.style.display = 'block';
    }
    
    function renderSuggestions(data) {
        if (!data || data.length === 0) {
            suggestions.innerHTML = '<div class="autocomplete-suggestion" style="color: #6b7280; cursor: default;">No results found</div>';
            showSuggestions();
            return;
        }
        
        suggestions.innerHTML = data.map((item, index) => {
            const typeClass = item.type === 'user' ? 'user' : 'artist';
            const typeLabel = item.type === 'user' ? 'User' : 'Artist';
            return `
                <div class="autocomplete-suggestion" data-index="${index}" data-name="${item.name}">
                    <span class="type-badge ${typeClass}">${typeLabel}</span>
                    <span class="name">${escapeHtml(item.name)}</span>
                </div>
            `;
        }).join('');
        
        // Add click handlers
        suggestions.querySelectorAll('.autocomplete-suggestion').forEach(item => {
            item.addEventListener('click', function() {
                const name = this.getAttribute('data-name');
                input.value = name;
                hideSuggestions();
                input.focus();
            });
            
            item.addEventListener('mouseenter', function() {
                suggestions.querySelectorAll('.autocomplete-suggestion').forEach(s => s.classList.remove('highlighted'));
                this.classList.add('highlighted');
                selectedIndex = parseInt(this.getAttribute('data-index'));
            });
        });
        
        showSuggestions();
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function searchUsers(query) {
        if (query.length < 2) {
            hideSuggestions();
            return;
        }
        
        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Debounce search
        searchTimeout = setTimeout(() => {
            fetch(`search-users.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    renderSuggestions(data);
                })
                .catch(error => {
                    console.error('Search error:', error);
                    hideSuggestions();
                });
        }, 300);
    }
    
    // Input event
    input.addEventListener('input', function(e) {
        const query = this.value.trim();
        searchUsers(query);
    });
    
    // Keyboard navigation
    input.addEventListener('keydown', function(e) {
        const items = suggestions.querySelectorAll('.autocomplete-suggestion');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
            if (items[selectedIndex]) {
                items[selectedIndex].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                items.forEach((item, idx) => {
                    item.classList.toggle('highlighted', idx === selectedIndex);
                });
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, -1);
            items.forEach((item, idx) => {
                item.classList.toggle('highlighted', idx === selectedIndex);
            });
            if (selectedIndex === -1) {
                input.focus();
            }
        } else if (e.key === 'Enter' && selectedIndex >= 0 && items[selectedIndex]) {
            e.preventDefault();
            const name = items[selectedIndex].getAttribute('data-name');
            input.value = name;
            hideSuggestions();
        } else if (e.key === 'Escape') {
            hideSuggestions();
        }
    });
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !suggestions.contains(e.target)) {
            hideSuggestions();
        }
    });
    
    // Focus event
    input.addEventListener('focus', function() {
        const query = this.value.trim();
        if (query.length >= 2) {
            searchUsers(query);
        }
    });
})();
</script>

<?php include 'includes/footer.php'; ?>

