<?php
// admin/fix-empty-file-paths.php
// Script to fix songs with empty file_path by finding files based on file_size

require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Fix Empty File Paths';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';
$results = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_paths'])) {
    try {
        // Get all songs with empty file_path but have file_size
        $stmt = $conn->prepare("
            SELECT id, title, file_size, uploaded_date, upload_date, created_at 
            FROM songs 
            WHERE (file_path IS NULL OR file_path = '' OR TRIM(file_path) = '')
            AND file_size > 0
            ORDER BY id
        ");
        $stmt->execute();
        $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $base_dir = realpath(__DIR__ . '/../');
        $fixed_count = 0;
        $not_found_count = 0;
        
        // Common directories to search - expanded list
        $search_dirs = [
            $base_dir . '/uploads/audio/',
            $base_dir . '/uploads/music/',
            $base_dir . '/music/',
            $base_dir . '/uploads/',
            $base_dir . '/audio/',
            $base_dir . '/songs/',
            $base_dir . '/files/',
            $base_dir . '/'
        ];
        
        // Also check if there's a specific upload directory based on song ID or date
        foreach ($songs as $song) {
            $upload_date = $song['upload_date'] ?? $song['created_at'] ?? null;
            if ($upload_date) {
                $year = date('Y', strtotime($upload_date));
                $month = date('m', strtotime($upload_date));
                $search_dirs[] = $base_dir . '/uploads/' . $year . '/';
                $search_dirs[] = $base_dir . '/uploads/' . $year . '/' . $month . '/';
            }
        }
        $search_dirs = array_unique($search_dirs);
        
        foreach ($songs as $song) {
            $song_id = $song['id'];
            $file_size = $song['file_size'];
            $found_file = false;
            $found_path = '';
            
            // Search in all directories
            foreach ($search_dirs as $search_dir) {
                if (!is_dir($search_dir)) {
                    continue;
                }
                
                // Use RecursiveDirectoryIterator to search for files matching the size
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($search_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        // Check if file size matches (allow small tolerance for rounding)
                        $actual_size = $file->getSize();
                        $size_diff = abs($actual_size - $file_size);
                        
                        // Match if size is within 5KB tolerance (more lenient)
                        if ($size_diff <= 5120) {
                            // Check if it's an audio file
                            $ext = strtolower($file->getExtension());
                            $audio_extensions = ['mp3', 'wav', 'flac', 'aac', 'm4a', 'ogg', 'oga', 'webm'];
                            
                            if (in_array($ext, $audio_extensions)) {
                                // Get relative path from base directory
                                $full_path = $file->getRealPath();
                                $relative_path = str_replace('\\', '/', str_replace($base_dir . '/', '', $full_path));
                                $relative_path = ltrim($relative_path, '/');
                                
                                $found_file = true;
                                $found_path = $relative_path;
                                break 2; // Break out of both loops
                            }
                        }
                    }
                }
            }
            
            if ($found_file) {
                // Update the file_path in database
                $update_stmt = $conn->prepare("UPDATE songs SET file_path = ? WHERE id = ?");
                if ($update_stmt->execute([$found_path, $song_id])) {
                    $fixed_count++;
                    $results[] = [
                        'id' => $song_id,
                        'title' => $song['title'],
                        'status' => 'fixed',
                        'path' => $found_path
                    ];
                }
            } else {
                $not_found_count++;
                $results[] = [
                    'id' => $song_id,
                    'title' => $song['title'],
                    'status' => 'not_found',
                    'path' => ''
                ];
            }
        }
        
        if ($fixed_count > 0) {
            $success = "Successfully fixed $fixed_count file path(s).";
            if ($not_found_count > 0) {
                $success .= " $not_found_count file(s) could not be located.";
            }
        } else {
            $error = "No files were found to fix. $not_found_count song(s) still have empty file paths.";
        }
        
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
        error_log("Fix empty file paths error: " . $e->getMessage());
    }
}

// Get songs with empty file_path
$stmt = $conn->prepare("
    SELECT id, title, file_size, file_path, upload_date, created_at 
    FROM songs 
    WHERE (file_path IS NULL OR file_path = '' OR TRIM(file_path) = '')
    AND file_size > 0
    ORDER BY id
");
$stmt->execute();
$songs_with_empty_paths = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Fix Empty File Paths</h1>
    <a href="songs.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Songs
    </a>
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

<div class="card">
    <div class="card-header">
        <h2>Songs with Empty File Paths</h2>
    </div>
    <div class="card-body">
        <?php if (count($songs_with_empty_paths) > 0): ?>
            <p class="text-muted">
                Found <strong><?php echo count($songs_with_empty_paths); ?></strong> song(s) with empty file paths but have file sizes.
                Click the button below to automatically find and fix them.
            </p>
            
            <form method="POST" onsubmit="return confirm('This will search for files and update the database. Continue?');">
                <button type="submit" name="fix_paths" class="btn btn-primary">
                    <i class="fas fa-search"></i> Find and Fix File Paths
                </button>
            </form>
            
            <?php if (!empty($results)): ?>
                <div style="margin-top: 30px;">
                    <h3>Results:</h3>
                    <table class="table" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>File Path</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                            <tr>
                                <td><?php echo $result['id']; ?></td>
                                <td><?php echo htmlspecialchars($result['title']); ?></td>
                                <td>
                                    <?php if ($result['status'] === 'fixed'): ?>
                                        <span class="badge badge-success">Fixed</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Not Found</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code style="font-size: 12px;"><?php echo htmlspecialchars($result['path']); ?></code>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px;">
                <h3>Affected Songs:</h3>
                <table class="table" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>File Size</th>
                            <th>Upload Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($songs_with_empty_paths as $song): ?>
                        <tr>
                            <td><?php echo $song['id']; ?></td>
                            <td><?php echo htmlspecialchars($song['title']); ?></td>
                            <td><?php echo round($song['file_size'] / 1048576, 2); ?> MB</td>
                            <td><?php echo date('M d, Y', strtotime($song['upload_date'] ?? $song['created_at'] ?? 'now')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> All songs have file paths set. No action needed.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>


