<?php
// admin/lyrics-manage.php - Admin page to manage lyrics
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Lyrics Management';

$db = new Database();
$conn = $db->getConnection();

// Handle actions
$success = '';
$error = '';
$edit_song_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_song = null;

if ($edit_song_id > 0) {
    $editStmt = $conn->prepare("SELECT id, title, lyrics FROM songs WHERE id = ?");
    $editStmt->execute([$edit_song_id]);
    $edit_song = $editStmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_lyrics'])) {
        $song_id = (int)($_POST['song_id'] ?? 0);
        $lyrics = trim($_POST['lyrics'] ?? '');
        
        if ($song_id > 0) {
            try {
                $updateStmt = $conn->prepare("UPDATE songs SET lyrics = ? WHERE id = ?");
                $updateStmt->execute([$lyrics, $song_id]);
                $success = 'Lyrics updated successfully!';
                logAdminActivity($_SESSION['user_id'], 'update_lyrics', 'song', $song_id, "Updated lyrics for song ID: $song_id");
                // Redirect to clear edit parameter
                header('Location: lyrics-manage.php?success=1');
                exit;
            } catch (Exception $e) {
                $error = 'Error updating lyrics: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add_lyrics'])) {
        $song_id = (int)($_POST['song_id'] ?? 0);
        $lyrics = trim($_POST['lyrics'] ?? '');
        
        if ($song_id > 0 && !empty($lyrics)) {
            try {
                $updateStmt = $conn->prepare("UPDATE songs SET lyrics = ? WHERE id = ?");
                $updateStmt->execute([$lyrics, $song_id]);
                $success = 'Lyrics added successfully!';
                logAdminActivity($_SESSION['user_id'], 'add_lyrics', 'song', $song_id, "Added lyrics for song ID: $song_id");
            } catch (Exception $e) {
                $error = 'Error adding lyrics: ' . $e->getMessage();
            }
        } else {
            $error = 'Please select a song and enter lyrics.';
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
    $where_clauses[] = "(s.title LIKE ? OR s.artist LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Check if uploaded_by column exists
$checkStmt = $conn->query("SHOW COLUMNS FROM songs LIKE 'uploaded_by'");
$has_uploaded_by = $checkStmt->rowCount() > 0;

// Get total count
if ($has_uploaded_by) {
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM songs s LEFT JOIN users u ON s.uploaded_by = u.id $where_sql");
} else {
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM songs s $where_sql");
}
$countStmt->execute($params);
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total / $per_page);

// Get songs with lyrics
$params[] = $per_page;
$params[] = $offset;
if ($has_uploaded_by) {
    $stmt = $conn->prepare("
        SELECT s.*, 
               COALESCE(s.artist, u.username, 'Unknown Artist') as artist_name
        FROM songs s
        LEFT JOIN users u ON s.uploaded_by = u.id
        $where_sql
        ORDER BY s.id DESC
        LIMIT ? OFFSET ?
    ");
} else {
    // Check if artist column exists
    $checkArtist = $conn->query("SHOW COLUMNS FROM songs LIKE 'artist'");
    $has_artist = $checkArtist->rowCount() > 0;
    
    if ($has_artist) {
        $stmt = $conn->prepare("
            SELECT s.*, 
                   COALESCE(s.artist, 'Unknown Artist') as artist_name
            FROM songs s
            $where_sql
            ORDER BY s.id DESC
            LIMIT ? OFFSET ?
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT s.*, 
                   'Unknown Artist' as artist_name
            FROM songs s
            $where_sql
            ORDER BY s.id DESC
            LIMIT ? OFFSET ?
        ");
    }
}
$stmt->execute($params);
$songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="page-content">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1><i class="fas fa-file-text"></i> Lyrics Management</h1>
            <p>Manage lyrics for all songs</p>
        </div>
        <button onclick="showAddLyricsModal()" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Lyrics
        </button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($edit_song): ?>
        <!-- Edit Form -->
        <div style="background: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h2 style="margin-bottom: 20px;">Edit Lyrics for: <?php echo htmlspecialchars($edit_song['title']); ?></h2>
            <form method="POST">
                <input type="hidden" name="song_id" value="<?php echo $edit_song['id']; ?>">
                <div class="form-group">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Lyrics:</label>
                    <textarea name="lyrics" rows="15" 
                              style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit; resize: vertical;"><?php echo htmlspecialchars($edit_song['lyrics'] ?? ''); ?></textarea>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" name="update_lyrics" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Lyrics
                    </button>
                    <a href="lyrics-manage.php" class="btn btn-secondary" style="margin-left: 10px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="search-bar">
        <form method="GET" style="display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Search songs..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
            <?php if ($search): ?>
                <a href="lyrics-manage.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Songs List -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Song Title</th>
                    <th>Artist</th>
                    <th>Has Lyrics</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($songs)): ?>
                    <?php foreach ($songs as $song): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($song['title']); ?></td>
                            <td><?php echo htmlspecialchars($song['artist_name']); ?></td>
                            <td>
                                <?php if (!empty($song['lyrics'])): ?>
                                    <span class="badge badge-success">Yes</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="lyrics-manage.php?edit=<?php echo $song['id']; ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 40px; color: #999;">
                            No songs found
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

<!-- Edit Lyrics Modal -->
<div id="lyricsModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="lyricsModalTitle">Edit Lyrics</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" id="lyricsForm">
            <input type="hidden" name="song_id" id="modal_song_id">
            <div class="form-group">
                <label>Song: <strong id="modal_song_title"></strong></label>
                <textarea name="lyrics" id="modal_lyrics" rows="15" 
                          style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit; resize: vertical;"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" name="update_lyrics" id="lyricsSubmitBtn" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Lyrics
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Lyrics Modal -->
<div id="addLyricsModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Lyrics</h2>
            <span class="close" onclick="closeAddModal()">&times;</span>
        </div>
        <form method="POST" id="addLyricsForm">
            <div class="form-group">
                <label>Select Song *</label>
                <select name="song_id" id="add_modal_song_id" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px;">
                    <option value="">-- Select Song --</option>
                    <?php foreach ($songs as $song): ?>
                        <option value="<?php echo $song['id']; ?>"><?php echo htmlspecialchars($song['title']); ?> - <?php echo htmlspecialchars($song['artist_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Lyrics *</label>
                <textarea name="lyrics" id="add_modal_lyrics" rows="15" required
                          style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit; resize: vertical;"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeAddModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" name="add_lyrics" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Lyrics
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddLyricsModal() {
    const modal = document.getElementById('addLyricsModal');
    if (modal) {
        modal.style.display = 'block';
        modal.style.zIndex = '10000';
        modal.style.position = 'fixed';
    }
}

function closeAddModal() {
    const modal = document.getElementById('addLyricsModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function editLyrics(songId, songTitle, lyrics) {
    console.log('editLyrics called with:', {songId, songTitle, lyrics: lyrics?.substring(0, 50)});
    const modal = document.getElementById('lyricsModal');
    if (!modal) {
        console.error('Modal element not found in DOM');
        alert('Error: Modal not found. Please refresh the page.');
        return;
    }
    
    const songIdInput = document.getElementById('modal_song_id');
    const songTitleEl = document.getElementById('modal_song_title');
    const lyricsTextarea = document.getElementById('modal_lyrics');
    const titleEl = document.getElementById('lyricsModalTitle');
    
    if (!songIdInput || !songTitleEl || !lyricsTextarea) {
        console.error('Modal form elements not found');
        alert('Error: Form elements not found. Please refresh the page.');
        return;
    }
    
    songIdInput.value = songId;
    songTitleEl.textContent = songTitle || 'Song';
    lyricsTextarea.value = lyrics || '';
    if (titleEl) titleEl.textContent = lyrics ? 'Edit Lyrics' : 'Add Lyrics';
    modal.style.display = 'block';
    modal.style.zIndex = '10000';
    modal.style.position = 'fixed';
    console.log('Modal displayed');
}

function closeModal() {
    const modal = document.getElementById('lyricsModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('lyricsModal');
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
    const addModal = document.getElementById('addLyricsModal');
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

