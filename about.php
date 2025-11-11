<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Load site settings for About Us page
function getAboutSetting($key, $default = '') {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

$site_name = getAboutSetting('site_name', SITE_NAME ?? 'Music Platform');
$site_tagline = getAboutSetting('site_tagline', 'Your Ultimate Music Streaming Platform');
$about_us_content = getAboutSetting('about_us_content', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - <?php echo htmlspecialchars($site_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .about-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #1e4d72 0%, #2d6a9f 100%);
            color: white;
            padding: 60px 40px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .hero-section h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .hero-section p {
            font-size: 20px;
            opacity: 0.9;
        }
        
        .content-section {
            background: white;
            border-radius: 12px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .content-section h2 {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .content-section h2 i {
            color: #1e4d72;
        }
        
        .content-section p {
            font-size: 16px;
            line-height: 1.8;
            color: #666;
            margin-bottom: 15px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .feature-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
        }
        
        .feature-card i {
            font-size: 40px;
            color: #1e4d72;
            margin-bottom: 15px;
        }
        
        .feature-card h3 {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .feature-card p {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #1e4d72 0%, #2d6a9f 100%);
            color: white;
            border-radius: 10px;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .contact-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
        }
        
        .contact-section h2 {
            font-size: 32px;
            margin-bottom: 15px;
        }
        
        .contact-section p {
            font-size: 18px;
            margin-bottom: 25px;
        }
        
        .contact-btn {
            background: white;
            color: #667eea;
            padding: 15px 40px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: transform 0.3s;
        }
        
        .contact-btn:hover {
            transform: scale(1.05);
            color: #667eea;
        }
        
        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 32px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .content-section {
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="about-container">
        <!-- Hero Section -->
        <div class="hero-section">
            <h1><i class="fas fa-music"></i> <?php echo htmlspecialchars($site_name); ?></h1>
            <p><?php echo htmlspecialchars($site_tagline); ?></p>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number">10K+</div>
                <div class="stat-label">Songs</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">5K+</div>
                <div class="stat-label">Artists</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">100K+</div>
                <div class="stat-label">Users</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">1M+</div>
                <div class="stat-label">Downloads</div>
            </div>
        </div>
        
        <!-- About Content -->
        <div class="content-section">
            <h2><i class="fas fa-info-circle"></i> About Us</h2>
            <?php if (!empty($about_us_content)): ?>
                <div style="font-size: 16px; line-height: 1.8; color: #666;">
                    <?php echo nl2br(htmlspecialchars($about_us_content)); ?>
                </div>
            <?php else: ?>
                <p>
                    <?php echo htmlspecialchars($site_name); ?> is your premier destination for discovering and enjoying the latest music from talented artists around the world. 
                    We are committed to providing a platform where artists can share their creativity and music lovers can explore new sounds.
                </p>
                <p>
                    Founded with a passion for music, our platform bridges the gap between artists and their audience, 
                    making it easier than ever to discover, stream, and download your favorite tracks.
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Features -->
        <div class="content-section">
            <h2><i class="fas fa-star"></i> Our Features</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h3>Easy Upload</h3>
                    <p>Artists can upload and share their music effortlessly with our user-friendly interface.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-headphones"></i>
                    <h3>High Quality</h3>
                    <p>Stream and download music in high quality for the best listening experience.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-users"></i>
                    <h3>Community</h3>
                    <p>Connect with artists and fellow music lovers in our vibrant community.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Analytics</h3>
                    <p>Artists can track their performance with detailed insights and statistics.</p>
                </div>
            </div>
        </div>
        
        <!-- Mission -->
        <div class="content-section">
            <h2><i class="fas fa-bullseye"></i> Our Mission</h2>
            <p>
                Our mission is to empower artists by providing them with the tools and platform they need to reach a global audience. 
                We believe in the power of music to connect people, inspire creativity, and bring joy to millions.
            </p>
            <p>
                We are dedicated to creating a fair and transparent ecosystem where artists are rewarded for their talent 
                and fans have unlimited access to diverse musical content.
            </p>
        </div>
        
        <!-- Contact Section -->
        <div class="contact-section">
            <h2>Get In Touch</h2>
            <p>Have questions or want to partner with us? We'd love to hear from you!</p>
            <a href="mailto:info@<?php echo strtolower(str_replace(' ', '', SITE_NAME)); ?>.com" class="contact-btn">
                <i class="fas fa-envelope"></i> Contact Us
            </a>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>

