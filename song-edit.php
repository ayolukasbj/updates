<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Redirect if not logged in
if (!is_logged_in()) {
    redirect('login.php');
}

$user_id = get_user_id();
$song_id = $_GET['id'] ?? 0;

if (!$song_id) {
    redirect('artist-profile-mobile.php?tab=music');
}

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Get song data
try {
    $stmt = $conn->prepare("SELECT * FROM songs WHERE id = ? AND uploaded_by = ?");
    $stmt->execute([$song_id, $user_id]);
    $song = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$song) {
        redirect('artist-profile-mobile.php?tab=music');
    }
} catch (Exception $e) {
    redirect('artist-profile-mobile.php?tab=music');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title']);
        $genre = trim($_POST['genre']);
        $lyrics = trim($_POST['lyrics'] ?? '');
        
        $stmt = $conn->prepare("
            UPDATE songs 
            SET title = ?, genre = ?, lyrics = ?
            WHERE id = ? AND uploaded_by = ?
        ");
        
        $result = $stmt->execute([$title, $genre, $lyrics, $song_id, $user_id]);
        
        if ($result) {
            header('Location: artist-profile-mobile.php?tab=music&edited=1');
            exit;
        } else {
            $error = 'Failed to update song';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Song - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
        }
        
        h1 {
            margin-bottom: 20px;
            font-size: 24px;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #ff6600;
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            margin-left: 10px;
        }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-edit"></i> Edit Song</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Song Title</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($song['title']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Genre</label>
                <input type="text" name="genre" value="<?php echo htmlspecialchars($song['genre'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Lyrics</label>
                <textarea name="lyrics"><?php echo htmlspecialchars($song['lyrics'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <a href="artist-profile-mobile.php?tab=music" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </form>
    </div>
</body>
</html>

