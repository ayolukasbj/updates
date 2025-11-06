<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Genres & Tags Management';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_genre':
                $genre_name = trim($_POST['genre_name'] ?? '');
                if (!empty($genre_name)) {
                    // Check if genre exists
                    $stmt = $conn->prepare("SELECT id FROM genres WHERE name = ?");
                    $stmt->execute([$genre_name]);
                    if ($stmt->rowCount() === 0) {
                        // Check if slug column exists, if not add it
                        try {
                            $checkCol = $conn->query("SHOW COLUMNS FROM genres LIKE 'slug'");
                            if ($checkCol->rowCount() === 0) {
                                $conn->exec("ALTER TABLE genres ADD COLUMN slug VARCHAR(100) AFTER name");
                            }
                        } catch (Exception $e) {
                            // Column might already exist or error, continue
                        }
                        
                        // Insert genre with or without slug
                        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $genre_name));
                        $description = trim($_POST['description'] ?? '');
                        
                        // Try with slug first, fallback to without slug
                        try {
                            $stmt = $conn->prepare("INSERT INTO genres (name, slug, description, created_at) VALUES (?, ?, ?, NOW())");
                            $stmt->execute([$genre_name, $slug, $description]);
                        } catch (Exception $e) {
                            // If slug column doesn't exist, insert without it
                            $stmt = $conn->prepare("INSERT INTO genres (name, description, created_at) VALUES (?, ?, NOW())");
                            $stmt->execute([$genre_name, $description]);
                        }
                        $success = 'Genre added successfully!';
                    } else {
                        $error = 'Genre already exists!';
                    }
                }
                break;
                
            case 'add_tag':
                $tag_name = trim($_POST['tag_name'] ?? '');
                if (!empty($tag_name)) {
                    // Check if tag exists
                    $stmt = $conn->prepare("SELECT id FROM tags WHERE name = ?");
                    $stmt->execute([$tag_name]);
                    if ($stmt->rowCount() === 0) {
                        $stmt = $conn->prepare("INSERT INTO tags (name, slug, description, created_at) VALUES (?, ?, ?, NOW())");
                        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $tag_name));
                        $description = trim($_POST['tag_description'] ?? '');
                        $stmt->execute([$tag_name, $slug, $description]);
                        $success = 'Tag added successfully!';
                    } else {
                        $error = 'Tag already exists!';
                    }
                }
                break;
                
            case 'delete_genre':
                $genre_id = (int)($_POST['genre_id'] ?? 0);
                if ($genre_id > 0) {
                    $stmt = $conn->prepare("DELETE FROM genres WHERE id = ?");
                    $stmt->execute([$genre_id]);
                    $success = 'Genre deleted successfully!';
                }
                break;
                
            case 'delete_tag':
                $tag_id = (int)($_POST['tag_id'] ?? 0);
                if ($tag_id > 0) {
                    $stmt = $conn->prepare("DELETE FROM tags WHERE id = ?");
                    $stmt->execute([$tag_id]);
                    $success = 'Tag deleted successfully!';
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get all genres
try {
    $stmt = $conn->query("SELECT * FROM genres ORDER BY name ASC");
    $genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Create genres table if doesn't exist
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS genres (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                slug VARCHAR(100) NOT NULL UNIQUE,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_slug (slug)
            )
        ");
        $genres = [];
    } catch (Exception $e2) {
        $genres = [];
        $error = 'Database error: ' . $e2->getMessage();
    }
}

// Get all tags
try {
    $stmt = $conn->query("SELECT * FROM tags ORDER BY name ASC");
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Create tags table if doesn't exist
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                slug VARCHAR(100) NOT NULL UNIQUE,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_slug (slug)
            )
        ");
        $tags = [];
    } catch (Exception $e2) {
        $tags = [];
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Genres & Tags Management</h1>
    <p>Manage music genres and tags for your platform</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
    <!-- Add Genre -->
    <div class="card">
        <div class="card-header">
            <h2>Add New Genre</h2>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_genre">
                <div class="form-group">
                    <label>Genre Name *</label>
                    <input type="text" name="genre_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Add Genre</button>
            </form>
        </div>
    </div>

    <!-- Add Tag -->
    <div class="card">
        <div class="card-header">
            <h2>Add New Tag</h2>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_tag">
                <div class="form-group">
                    <label>Tag Name *</label>
                    <input type="text" name="tag_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="tag_description" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Add Tag</button>
            </form>
        </div>
    </div>
</div>

<!-- Genres List -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>All Genres (<?php echo count($genres); ?>)</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Description</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($genres)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #999;">No genres yet</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($genres as $genre): ?>
                        <tr>
                            <td><?php echo $genre['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($genre['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($genre['slug'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($genre['description'] ?? '-'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($genre['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this genre?');">
                                    <input type="hidden" name="action" value="delete_genre">
                                    <input type="hidden" name="genre_id" value="<?php echo $genre['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Tags List -->
<div class="card">
    <div class="card-header">
        <h2>All Tags (<?php echo count($tags); ?>)</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Description</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tags)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #999;">No tags yet</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($tags as $tag): ?>
                        <tr>
                            <td><?php echo $tag['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($tag['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($tag['slug']); ?></td>
                            <td><?php echo htmlspecialchars($tag['description'] ?? '-'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($tag['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this tag?');">
                                    <input type="hidden" name="action" value="delete_tag">
                                    <input type="hidden" name="tag_id" value="<?php echo $tag['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

