<?php
// profile.php - User profile page
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/User.php';

if (!is_logged_in()) {
    redirect(SITE_URL . '/login.php');
}

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$user_id = get_user_id();
$user_data = $user->getUserById($user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $stage_name = $_POST['stage_name'] ?? '';
    
    // Get current artist name before update
    $current_artist_name = '';
    try {
        $stmt = $db->prepare("SELECT username as artist_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($current_user) {
            $current_artist_name = $current_user['artist_name'];
        }
    } catch (Exception $e) {
        error_log("Error getting current artist name: " . $e->getMessage());
    }
    
    if ($user->updateProfile($user_id, $username, $email, $bio, $stage_name)) {
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        
        // Update all existing songs if artist name changed
        if (!empty($stage_name) && $stage_name !== $current_artist_name) {
            $new_artist_name = $stage_name;
            try {
                // Update all songs uploaded by this user
                $updateStmt = $db->prepare("UPDATE songs SET artist = ? WHERE uploaded_by = ?");
                $updateStmt->execute([$new_artist_name, $user_id]);
                
                // Also update artist_id if needed - update or create artist record
                $artistStmt = $db->prepare("SELECT id FROM artists WHERE name = ? LIMIT 1");
                $artistStmt->execute([$new_artist_name]);
                $artist_record = $artistStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($artist_record) {
                    $artist_id = $artist_record['id'];
                } else {
                    // Create new artist record
                    $createStmt = $db->prepare("INSERT INTO artists (name, user_id, created_at) VALUES (?, ?, NOW())");
                    $createStmt->execute([$new_artist_name, $user_id]);
                    $artist_id = $db->lastInsertId();
                }
                
                // Update artist_id in songs
                $artistIdStmt = $db->prepare("UPDATE songs SET artist_id = ? WHERE uploaded_by = ?");
                $artistIdStmt->execute([$artist_id, $user_id]);
                
                $success = "Profile updated successfully! All your songs have been updated with your new artist name.";
            } catch (Exception $e) {
                error_log("Error updating songs with new artist name: " . $e->getMessage());
                $success = "Profile updated successfully! (Note: Some songs may not have been updated)";
            }
        } else {
            $success = "Profile updated successfully!";
        }
        
        $user_data = $user->getUserById($user_id); // Refresh data
    } else {
        $error = "Failed to update profile.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <?php include 'includes/brand-colors.php'; ?>
    <style>
        /* Fix for general header spacing */
        .main-content {
            padding-top: 20px;
        }
        
        .form-container {
            position: relative;
            z-index: 10;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        /* Enhanced Profile Styles - Modern Design */
        .profile-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #7e57c2 100%);
            border-radius: 20px;
            padding: 50px;
            margin-bottom: 30px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255,255,255,0.1) 0%, transparent 50%),
                url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.03)" points="0,1000 1000,0 1000,1000"/></svg>');
            background-size: cover;
            animation: gradientShift 15s ease infinite;
        }
        
        @keyframes gradientShift {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        .profile-cover {
            display: flex;
            align-items: center;
            gap: 30px;
            position: relative;
            z-index: 2;
        }

        .profile-avatar-section {
            position: relative;
        }

        .profile-avatar {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            overflow: hidden;
            border: 5px solid rgba(255, 255, 255, 0.4);
            position: relative;
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 12px 40px rgba(0,0,0,0.4);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
            border-radius: 50%;
        }

        .profile-avatar:hover .avatar-overlay {
            opacity: 1;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 12px;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.4);
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, rgba(255,255,255,1) 0%, rgba(255,255,255,0.9) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .profile-email {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .profile-stats {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .stat-item i {
            font-size: 1.1rem;
        }

        /* Profile Tabs */
        .profile-tabs {
            margin-bottom: 30px;
        }

        .profile-tabs .nav-pills .nav-link {
            background: transparent;
            color: #9ca3af;
            border: none;
            margin-right: 0;
            border-radius: 0;
            padding: 12px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 14px;
            position: relative;
        }

        .profile-tabs .nav-pills .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: #ec4899;
            transition: width 0.3s ease;
        }

        .profile-tabs .nav-pills .nav-link:hover {
            color: #ec4899;
        }
        
        .profile-tabs .nav-pills .nav-link:hover::after {
            width: 80%;
        }

        .profile-tabs .nav-pills .nav-link.active {
            background: transparent;
            color: #ec4899;
            border: none;
        }
        
        .profile-tabs .nav-pills .nav-link.active::after {
            width: 100%;
        }

        .profile-tabs .nav-pills .nav-link i {
            margin-right: 8px;
        }

        /* Profile Sections - Modern Design */
        .profile-section {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .profile-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .section-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f8f9fa;
        }

        .section-header h3 {
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .section-header p {
            color: #6c757d;
            margin: 0;
        }

        .section-header i {
            color: #667eea;
            margin-right: 10px;
        }

        /* Form Styles */
        .profile-form .form-group {
            margin-bottom: 25px;
        }

        .profile-form .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .profile-form .form-label i {
            color: #667eea;
            width: 16px;
        }

        .profile-form .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .profile-form .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .profile-form .form-text {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 5px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .form-actions .btn {
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .form-actions .btn i {
            margin-right: 8px;
        }

        /* Password Strength */
        .password-strength {
            margin-top: 10px;
        }

        .strength-bar {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 5px;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
            border-radius: 2px;
        }

        .strength-fill.weak { background: #dc3545; width: 25%; }
        .strength-fill.fair { background: #ffc107; width: 50%; }
        .strength-fill.good { background: #17a2b8; width: 75%; }
        .strength-fill.strong { background: #28a745; width: 100%; }

        .strength-text {
            font-size: 0.8rem;
            color: #6c757d;
        }

        /* Preferences Grid */
        .preferences-grid {
            display: grid;
            gap: 20px;
        }

        .preference-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e9ecef;
            transition: all 0.3s;
        }

        .preference-item:hover {
            background: #e9ecef;
            border-color: #dee2e6;
        }

        .preference-info h5 {
            color: #333;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .preference-info h5 i {
            color: #667eea;
            margin-right: 8px;
        }

        .preference-info p {
            color: #6c757d;
            margin: 0;
            font-size: 0.9rem;
        }

        .preference-control .form-check-input {
            width: 3rem;
            height: 1.5rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .profile-cover {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }

            .profile-name {
                font-size: 2rem;
            }

            .profile-stats {
                justify-content: center;
            }

            .form-actions {
                flex-direction: column;
            }

            .preference-item {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Include General Header -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-cover">
                    <div class="profile-avatar-section">
                        <div class="profile-avatar">
                            <img src="assets/images/default-avatar.svg" alt="Profile Picture" id="profileImage">
                            <div class="avatar-overlay">
                                <button type="button" class="btn btn-sm btn-light" onclick="document.getElementById('profileImageInput').click()">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                        </div>
                        <input type="file" id="profileImageInput" accept="image/*" style="display: none;">
                    </div>
                    <div class="profile-info">
                        <h1 class="profile-name"><?php echo htmlspecialchars($user_data['username']); ?></h1>
                        <p class="profile-email"><?php echo htmlspecialchars($user_data['email']); ?></p>
                        <div class="profile-stats">
                            <div class="stat-item">
                                <i class="fas fa-calendar"></i>
                                <span>Member since <?php echo date('M Y', strtotime($user_data['created_at'])); ?></span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-music"></i>
                                <span>Music Lover</span>
                            </div>
                        </div>
                        <div style="margin-top: 20px;">
                            <a href="logout.php" class="btn btn-outline-light" style="border: 2px solid rgba(255,255,255,0.5); color: white; padding: 10px 25px; border-radius: 25px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; background: rgba(255,255,255,0.1);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.8)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.5)';">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Profile Tabs -->
            <div class="profile-tabs" style="background: #2c3e50; padding: 0; border-radius: 0; margin: 0 -20px 30px -20px;">
                <nav class="nav nav-pills nav-fill" id="profileTabs" role="tablist" style="border-bottom: none; padding: 15px 20px 0; display: flex; gap: 0; flex-wrap: wrap;">
                    <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab" style="background: transparent; border: none; color: #ec4899; padding: 12px 20px; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; position: relative; border-bottom: 2px solid #ec4899;">
                        Overview
                    </button>
                    <button class="nav-link" id="biography-tab" data-bs-toggle="tab" data-bs-target="#biography" type="button" role="tab" style="background: transparent; border: none; color: #9ca3af; padding: 12px 20px; font-weight: 500; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; transition: color 0.3s;" onmouseover="this.style.color='#ec4899';" onmouseout="this.style.color='#9ca3af';">
                        Biography
                    </button>
                    <button class="nav-link" id="songs-tab" data-bs-toggle="tab" data-bs-target="#songs" type="button" role="tab" style="background: transparent; border: none; color: #9ca3af; padding: 12px 20px; font-weight: 500; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; transition: color 0.3s;" onmouseover="this.style.color='#ec4899';" onmouseout="this.style.color='#9ca3af';">
                        Songs
                    </button>
                    <button class="nav-link" id="videos-tab" data-bs-toggle="tab" data-bs-target="#videos" type="button" role="tab" style="background: transparent; border: none; color: #9ca3af; padding: 12px 20px; font-weight: 500; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; transition: color 0.3s;" onmouseover="this.style.color='#ec4899';" onmouseout="this.style.color='#9ca3af';">
                        Videos
                    </button>
                    <button class="nav-link" id="lyrics-tab" data-bs-toggle="tab" data-bs-target="#lyrics" type="button" role="tab" style="background: transparent; border: none; color: #9ca3af; padding: 12px 20px; font-weight: 500; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; transition: color 0.3s;" onmouseover="this.style.color='#ec4899';" onmouseout="this.style.color='#9ca3af';">
                        Lyrics
                    </button>
                    <button class="nav-link" id="news-tab" data-bs-toggle="tab" data-bs-target="#news" type="button" role="tab" style="background: transparent; border: none; color: #9ca3af; padding: 12px 20px; font-weight: 500; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; transition: color 0.3s;" onmouseover="this.style.color='#ec4899';" onmouseout="this.style.color='#9ca3af';">
                        News
                    </button>
                    <button class="nav-link" id="albums-tab" data-bs-toggle="tab" data-bs-target="#albums" type="button" role="tab" style="background: transparent; border: none; color: #9ca3af; padding: 12px 20px; font-weight: 500; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; transition: color 0.3s;" onmouseover="this.style.color='#ec4899';" onmouseout="this.style.color='#9ca3af';">
                        Albums
                    </button>
                    <button class="nav-link" id="stream-tab" data-bs-toggle="tab" data-bs-target="#stream" type="button" role="tab" style="background: transparent; border: none; color: #9ca3af; padding: 12px 20px; font-weight: 500; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; transition: color 0.3s;" onmouseover="this.style.color='#ec4899';" onmouseout="this.style.color='#9ca3af';">
                        Stream
                    </button>
                </nav>
            </div>
            
            <script>
            // Update active tab styling
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', function() {
                    // Remove active class from all tabs
                    document.querySelectorAll('.nav-link').forEach(l => {
                        l.style.color = '#9ca3af';
                        l.style.borderBottom = 'none';
                    });
                    // Add active styling to clicked tab
                    this.style.color = '#ec4899';
                    this.style.borderBottom = '2px solid #ec4899';
                });
            });
            </script>

            <!-- Tab Content -->
            <div class="tab-content" id="profileTabsContent">
                <!-- Overview Tab -->
                <div class="tab-pane fade show active" id="overview" role="tabpanel">
                    <div class="profile-section">
                        <div class="section-header">
                            <h3><i class="fas fa-edit"></i> Edit Profile Information</h3>
                            <p>Update your personal information and bio</p>
                        </div>
                        
                        <form method="POST" class="profile-form">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="username" class="form-label">
                                            <i class="fas fa-user"></i> Username
                                        </label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                                        <div class="form-text">This will be visible to other users</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email" class="form-label">
                                            <i class="fas fa-envelope"></i> Email Address
                                        </label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                                        <div class="form-text">We'll never share your email</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="stage_name" class="form-label">
                                    <i class="fas fa-microphone"></i> Stage Name / Artist Name
                                </label>
                                <?php 
                                // Get current stage name/artist name
                                $current_stage_name = '';
                                try {
                                    $stmt = $db->prepare("SELECT username as artist_name FROM users WHERE id = ?");
                                    $stmt->execute([$user_id]);
                                    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($current_user) {
                                        $current_stage_name = $current_user['artist_name'];
                                    }
                                } catch (Exception $e) {
                                    $current_stage_name = $user_data['username'];
                                }
                                ?>
                                <input type="text" class="form-control" id="stage_name" name="stage_name" 
                                       value="<?php echo htmlspecialchars($current_stage_name); ?>" required>
                                <div class="form-text">This is your artist name displayed on songs. Updating this will update all your existing songs.</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="bio" class="form-label">
                                    <i class="fas fa-quote-left"></i> Bio
                                </label>
                                <textarea class="form-control" id="bio" name="bio" rows="4" 
                                          placeholder="Tell us about yourself, your music taste, or anything you'd like to share..."><?php echo htmlspecialchars($user_data['bio'] ?? ''); ?></textarea>
                                <div class="form-text">Let others know more about you</div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Want to add profile picture, cover image, or social media links?</strong><br>
                                <a href="profile-edit.php" class="btn btn-primary btn-sm mt-2">
                                    <i class="fas fa-edit"></i> Go to Advanced Profile Editor
                                </a>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-lg" onclick="resetForm()">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Biography Tab -->
                <div class="tab-pane fade" id="biography" role="tabpanel">
                    <div class="profile-section">
                        <div class="section-header">
                            <h3><i class="fas fa-user"></i> Biography</h3>
                            <p>Your personal story and background</p>
                        </div>
                        <div style="padding: 20px; background: #f8f9fa; border-radius: 10px;">
                            <?php if (!empty($user_data['bio'])): ?>
                                <p style="font-size: 16px; line-height: 1.8; color: #333; white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($user_data['bio'])); ?></p>
                            <?php else: ?>
                                <p style="color: #999; font-style: italic;">No biography added yet. Update your bio in the Overview tab.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Songs Tab -->
                <div class="tab-pane fade" id="songs" role="tabpanel">
                    <div class="profile-section">
                        <div class="section-header">
                            <h3><i class="fas fa-music"></i> My Songs</h3>
                            <p>All songs you've uploaded</p>
                        </div>
                        <?php
                        // Get user's songs
                        try {
                            $songsStmt = $db->prepare("
                                SELECT s.*, 
                                       COALESCE(s.artist, u.username, 'Unknown Artist') as artist
                                FROM songs s
                                LEFT JOIN users u ON s.uploaded_by = u.id
                                WHERE s.uploaded_by = ?
                                AND (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                                ORDER BY s.id DESC
                            ");
                            $songsStmt->execute([$user_id]);
                            $user_songs = $songsStmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            $user_songs = [];
                            error_log("Error fetching user songs: " . $e->getMessage());
                        }
                        ?>
                        <?php if (!empty($user_songs)): ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                            <?php foreach ($user_songs as $song): 
                                $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['title']));
                                $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
                                $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['artist']));
                                $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
                                $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
                            ?>
                            <div style="background: #f8f9fa; border-radius: 10px; overflow: hidden; cursor: pointer; transition: transform 0.3s;" 
                                 onmouseover="this.style.transform='translateY(-5px)'" 
                                 onmouseout="this.style.transform='translateY(0)'"
                                 onclick="window.location.href='/song/<?php echo urlencode($songSlug); ?>'">
                                <div style="width: 100%; aspect-ratio: 1; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); position: relative; overflow: hidden;">
                                    <?php if (!empty($song['cover_art'])): ?>
                                    <img src="<?php echo htmlspecialchars($song['cover_art']); ?>" alt="<?php echo htmlspecialchars($song['title']); ?>" 
                                         style="width: 100%; height: 100%; object-fit: cover;" 
                                         onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\'width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:white;font-size:48px;\'><i class=\'fas fa-music\'></i></div>';">
                                    <?php else: ?>
                                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-size: 48px;">
                                        <i class="fas fa-music"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div style="padding: 15px;">
                                    <h6 style="font-weight: 600; margin-bottom: 5px; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($song['title']); ?></h6>
                                    <p style="font-size: 13px; color: #666; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($song['artist']); ?></p>
                                    <div style="display: flex; gap: 15px; margin-top: 10px; font-size: 12px; color: #999;">
                                        <span><i class="fas fa-play"></i> <?php echo number_format((int)($song['plays'] ?? 0)); ?></span>
                                        <span><i class="fas fa-download"></i> <?php echo number_format((int)($song['downloads'] ?? 0)); ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #999;">
                            <i class="fas fa-music" style="font-size: 48px; margin-bottom: 15px; display: block; opacity: 0.3;"></i>
                            <p>No songs uploaded yet.</p>
                            <a href="upload.php" class="btn btn-primary mt-3">
                                <i class="fas fa-upload"></i> Upload Your First Song
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Videos Tab -->
                <div class="tab-pane fade" id="videos" role="tabpanel">
                    <div class="profile-section">
                        <div class="section-header">
                            <h3><i class="fas fa-video"></i> Videos</h3>
                            <p>Your music videos and content</p>
                        </div>
                        <div style="text-align: center; padding: 40px; color: #999;">
                            <i class="fas fa-video" style="font-size: 48px; margin-bottom: 15px; display: block; opacity: 0.3;"></i>
                            <p>Video feature coming soon!</p>
                        </div>
                    </div>
                </div>

                <!-- Lyrics Tab -->
                <div class="tab-pane fade" id="lyrics" role="tabpanel">
                    <div class="profile-section">
                        <div class="section-header">
                            <h3><i class="fas fa-file-alt"></i> Lyrics</h3>
                            <p>Lyrics from your songs</p>
                        </div>
                        <?php
                        // Get songs with lyrics
                        try {
                            $lyricsStmt = $db->prepare("
                                SELECT s.id, s.title, s.artist, s.lyrics
                                FROM songs s
                                WHERE s.uploaded_by = ?
                                AND s.lyrics IS NOT NULL
                                AND s.lyrics != ''
                                AND (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                                ORDER BY s.id DESC
                            ");
                            $lyricsStmt->execute([$user_id]);
                            $songs_with_lyrics = $lyricsStmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            $songs_with_lyrics = [];
                        }
                        ?>
                        <?php if (!empty($songs_with_lyrics)): ?>
                        <div style="display: grid; gap: 20px; margin-top: 20px;">
                            <?php foreach ($songs_with_lyrics as $song): ?>
                            <div style="background: #f8f9fa; border-radius: 10px; padding: 20px;">
                                <h5 style="margin-bottom: 10px; color: #333;"><?php echo htmlspecialchars($song['title']); ?></h5>
                                <p style="color: #666; font-size: 14px; margin-bottom: 15px;">by <?php echo htmlspecialchars($song['artist']); ?></p>
                                <div style="background: white; padding: 20px; border-radius: 8px; white-space: pre-wrap; font-size: 15px; line-height: 1.8; color: #333;"><?php echo nl2br(htmlspecialchars($song['lyrics'])); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #999;">
                            <i class="fas fa-file-alt" style="font-size: 48px; margin-bottom: 15px; display: block; opacity: 0.3;"></i>
                            <p>No lyrics added to your songs yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- News Tab -->
                <div class="tab-pane fade" id="news" role="tabpanel">
                    <div class="profile-section">
                        <div class="section-header">
                            <h3><i class="fas fa-newspaper"></i> News</h3>
                            <p>News articles about you</p>
                        </div>
                        <?php
                        // Get news related to user
                        try {
                            $newsStmt = $db->prepare("
                                SELECT * FROM news 
                                WHERE (author_id = ? 
                                OR title LIKE ? 
                                OR content LIKE ?)
                                AND is_published = 1
                                ORDER BY created_at DESC
                                LIMIT 10
                            ");
                            $user_search = '%' . $user_data['username'] . '%';
                            $newsStmt->execute([$user_id, $user_search, $user_search]);
                            $user_news = $newsStmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            $user_news = [];
                        }
                        ?>
                        <?php if (!empty($user_news)): ?>
                        <div style="display: grid; gap: 20px; margin-top: 20px;">
                            <?php foreach ($user_news as $article): ?>
                            <div style="background: #f8f9fa; border-radius: 10px; padding: 20px; display: flex; gap: 20px; cursor: pointer; transition: background 0.3s;" 
                                 onmouseover="this.style.background='#e9ecef'" 
                                 onmouseout="this.style.background='#f8f9fa'"
                                 onclick="window.location.href='news-details.php?slug=<?php echo urlencode($article['slug']); ?>'">
                                <?php if (!empty($article['image'])): ?>
                                <img src="<?php echo htmlspecialchars($article['image']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>" 
                                     style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px; flex-shrink: 0;">
                                <?php endif; ?>
                                <div style="flex: 1;">
                                    <h5 style="margin-bottom: 8px; color: #333;"><?php echo htmlspecialchars($article['title']); ?></h5>
                                    <p style="color: #666; font-size: 14px; margin: 0;"><?php echo htmlspecialchars(substr($article['excerpt'] ?? $article['content'] ?? '', 0, 150)); ?>...</p>
                                    <p style="color: #999; font-size: 12px; margin-top: 10px; margin-bottom: 0;"><?php echo date('M d, Y', strtotime($article['created_at'])); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #999;">
                            <i class="fas fa-newspaper" style="font-size: 48px; margin-bottom: 15px; display: block; opacity: 0.3;"></i>
                            <p>No news articles found.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Albums Tab -->
                <div class="tab-pane fade" id="albums" role="tabpanel">
                    <div class="profile-section">
                        <div class="section-header">
                            <h3><i class="fas fa-compact-disc"></i> Albums</h3>
                            <p>Your music albums and collections</p>
                        </div>
                        <div style="text-align: center; padding: 40px; color: #999;">
                            <i class="fas fa-compact-disc" style="font-size: 48px; margin-bottom: 15px; display: block; opacity: 0.3;"></i>
                            <p>Album feature coming soon!</p>
                        </div>
                    </div>
                </div>

                <!-- Stream Tab -->
                <div class="tab-pane fade" id="stream" role="tabpanel">
                    <div class="profile-section">
                        <div class="section-header">
                            <h3><i class="fas fa-stream"></i> Stream</h3>
                            <p>Your activity stream and timeline</p>
                        </div>
                        <div style="text-align: center; padding: 40px; color: #999;">
                            <i class="fas fa-stream" style="font-size: 48px; margin-bottom: 15px; display: block; opacity: 0.3;"></i>
                            <p>Activity stream feature coming soon!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                button.classList.remove('fa-eye');
                button.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                button.classList.remove('fa-eye-slash');
                button.classList.add('fa-eye');
            }
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            let strengthText = '';
            let strengthClass = '';

            if (password.length >= 8) strength += 25;
            if (password.match(/[a-z]/)) strength += 25;
            if (password.match(/[A-Z]/)) strength += 25;
            if (password.match(/[0-9]/)) strength += 25;

            if (strength < 25) {
                strengthText = 'Very Weak';
                strengthClass = 'weak';
            } else if (strength < 50) {
                strengthText = 'Weak';
                strengthClass = 'weak';
            } else if (strength < 75) {
                strengthText = 'Fair';
                strengthClass = 'fair';
            } else if (strength < 100) {
                strengthText = 'Good';
                strengthClass = 'good';
            } else {
                strengthText = 'Strong';
                strengthClass = 'strong';
            }

            return { strength, strengthText, strengthClass };
        }

        // Update password strength indicator
        function updatePasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            if (password.length === 0) {
                strengthFill.style.width = '0%';
                strengthFill.className = 'strength-fill';
                strengthText.textContent = 'Password strength';
                return;
            }

            const result = checkPasswordStrength(password);
            strengthFill.style.width = result.strength + '%';
            strengthFill.className = 'strength-fill ' + result.strengthClass;
            strengthText.textContent = result.strengthText;
        }

        // Form reset function
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All changes will be lost.')) {
                document.querySelector('.profile-form').reset();
                updatePasswordStrength();
            }
        }

        // Save preferences function
        function savePreferences() {
            const preferences = {
                notifications: document.getElementById('notifications').checked,
                darkMode: document.getElementById('darkMode').checked,
                autoPlay: document.getElementById('autoPlay').checked,
                publicProfile: document.getElementById('publicProfile').checked
            };

            // Here you would typically send the preferences to the server
            console.log('Saving preferences:', preferences);
            
            // Show success message
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show';
            alert.innerHTML = `
                <i class="fas fa-check-circle"></i> Preferences saved successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.querySelector('.main-content .container').insertBefore(alert, document.querySelector('.profile-tabs'));
            
            // Auto-dismiss after 3 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 3000);
        }

        // Profile image upload
        document.getElementById('profileImageInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profileImage').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // Initialize password strength checker
        document.addEventListener('DOMContentLoaded', function() {
            const newPasswordField = document.getElementById('new_password');
            if (newPasswordField) {
                newPasswordField.addEventListener('input', updatePasswordStrength);
            }

            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Form validation
        function validateForm() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 8) {
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            return true;
        }

        // Add form validation to password change form
        document.querySelector('form[action="change-password.php"]').addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
            }
        });
    </script>
    
    <!-- Include Footer -->
    <?php include 'includes/footer.php'; ?>
</body>
</html>
