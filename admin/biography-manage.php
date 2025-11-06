<?php
// admin/biography-manage.php - Admin page to manage artist biographies
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Biography Management';

$db = new Database();
$conn = $db->getConnection();

// Handle actions
$success = '';
$error = '';
$edit_user_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_user = null;

if ($edit_user_id > 0) {
    $editStmt = $conn->prepare("SELECT id, username, bio FROM users WHERE id = ?");
    $editStmt->execute([$edit_user_id]);
    $edit_user = $editStmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_biography'])) {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $biography = trim($_POST['biography'] ?? '');
        
        if ($user_id > 0) {
            try {
                $updateStmt = $conn->prepare("UPDATE users SET bio = ? WHERE id = ?");
                $updateStmt->execute([$biography, $user_id]);
                $success = 'Biography updated successfully!';
                logAdminActivity($_SESSION['user_id'], 'update_biography', 'user', $user_id, "Updated biography for user ID: $user_id");
                // Redirect to clear edit parameter
                header('Location: biography-manage.php?success=1');
                exit;
            } catch (Exception $e) {
                $error = 'Error updating biography: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add_biography'])) {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $biography = trim($_POST['biography'] ?? '');
        
        if ($user_id > 0 && !empty($biography)) {
            try {
                $updateStmt = $conn->prepare("UPDATE users SET bio = ? WHERE id = ?");
                $updateStmt->execute([$biography, $user_id]);
                $success = 'Biography added successfully!';
                logAdminActivity($_SESSION['user_id'], 'add_biography', 'user', $user_id, "Added biography for user ID: $user_id");
            } catch (Exception $e) {
                $error = 'Error adding biography: ' . $e->getMessage();
            }
        } else {
            $error = 'Please select a user and enter a biography.';
        }
    }
}

// Pagination
$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search filter
$search = $_GET['search'] ?? '';
$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM users $where_sql");
$countStmt->execute($params);
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total / $per_page);

// Get users
$params[] = $per_page;
$params[] = $offset;
// Check if bio column exists
$checkStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'bio'");
$has_bio = $checkStmt->rowCount() > 0;

if ($has_bio) {
    $stmt = $conn->prepare("
        SELECT id, username, email, bio 
        FROM users
        $where_sql
        ORDER BY id DESC
        LIMIT ? OFFSET ?
    ");
} else {
    $stmt = $conn->prepare("
        SELECT id, username, email, '' as bio 
        FROM users
        $where_sql
        ORDER BY id DESC
        LIMIT ? OFFSET ?
    ");
}
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="page-content">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1><i class="fas fa-user"></i> Biography Management</h1>
            <p>Manage artist biographies</p>
        </div>
        <button onclick="showAddBiographyModal()" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Biography
        </button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($edit_user): ?>
        <!-- Edit Form -->
        <div style="background: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h2 style="margin-bottom: 20px;">Edit Biography for: <?php echo htmlspecialchars($edit_user['username']); ?></h2>
            <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                <div class="form-group">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Biography:</label>
                    <textarea name="biography" rows="15" 
                              style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit; resize: vertical;"><?php echo htmlspecialchars($edit_user['bio'] ?? ''); ?></textarea>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" name="update_biography" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Biography
                    </button>
                    <a href="biography-manage.php" class="btn btn-secondary" style="margin-left: 10px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="search-bar">
        <form method="GET" style="display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
            <?php if ($search): ?>
                <a href="biography-manage.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Users List -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Has Biography</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php if (!empty($user['bio'])): ?>
                                    <span class="badge badge-success">Yes</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="biography-manage.php?edit=<?php echo $user['id']; ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 40px; color: #999;">
                            No users found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                   class="<?php echo $page == $i ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Biography Modal -->
<div id="biographyModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Biography</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" id="biographyForm">
            <input type="hidden" name="user_id" id="modal_user_id">
            <div class="form-group">
                <label>User: <strong id="modal_username"></strong></label>
                <textarea name="biography" id="modal_biography" rows="15" 
                          style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit; resize: vertical;"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" name="update_biography" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Biography
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddBiographyModal() {
    const modal = document.getElementById('addBiographyModal');
    if (modal) {
        modal.style.display = 'block';
        modal.style.zIndex = '10000';
        modal.style.position = 'fixed';
    }
}

function closeAddModal() {
    const modal = document.getElementById('addBiographyModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function editBiography(userId, username, biography) {
    console.log('editBiography called with:', {userId, username, biography: biography?.substring(0, 50)});
    const modal = document.getElementById('biographyModal');
    if (!modal) {
        console.error('Modal element not found in DOM');
        alert('Error: Modal not found. Please refresh the page.');
        return;
    }
    
    const userIdInput = document.getElementById('modal_user_id');
    const usernameEl = document.getElementById('modal_username');
    const biographyTextarea = document.getElementById('modal_biography');
    const titleEl = document.getElementById('biographyModalTitle');
    
    if (!userIdInput || !usernameEl || !biographyTextarea) {
        console.error('Modal form elements not found');
        alert('Error: Form elements not found. Please refresh the page.');
        return;
    }
    
    userIdInput.value = userId;
    usernameEl.textContent = username || 'User';
    biographyTextarea.value = biography || '';
    if (titleEl) titleEl.textContent = biography ? 'Edit Biography' : 'Add Biography';
    modal.style.display = 'block';
    modal.style.zIndex = '10000';
    modal.style.position = 'fixed';
    console.log('Modal displayed');
}

function closeModal() {
    const modal = document.getElementById('biographyModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('biographyModal');
    if (event.target == modal) {
        closeModal();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
        closeAddModal();
    }
});

// Close add modal when clicking outside
window.onclick = function(event) {
    const addModal = document.getElementById('addBiographyModal');
    if (event.target == addModal) {
        closeAddModal();
    }
};
</script>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 10000 !important;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    overflow: auto;
}

.modal-content {
    background: white;
    margin: 50px auto;
    padding: 0;
    border-radius: 10px;
    width: 90%;
    max-width: 800px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    color: #333;
}

.close {
    color: #999;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: #333;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
</style>

<?php include 'includes/footer.php'; ?>

