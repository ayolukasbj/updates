<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Redirect if not logged in
if (!is_logged_in()) {
    redirect('login.php');
}

$user_id = get_user_id();

// Get user data
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boost Your Music - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            font-size: 36px;
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .header h1 i {
            color: #ff6600;
            font-size: 40px;
        }
        
        .header p {
            font-size: 18px;
            color: #666;
            margin-top: 10px;
        }
        
        .content {
            line-height: 1.8;
            font-size: 16px;
            color: #444;
        }
        
        .intro {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 5px solid #ff6600;
        }
        
        .intro p {
            font-size: 18px;
            font-weight: 500;
            color: #333;
            margin: 0;
        }
        
        .features {
            margin: 30px 0;
        }
        
        .features h2 {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .features h2 i {
            color: #ff6600;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
        }
        
        .feature-list li {
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .feature-list li:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .feature-list li i {
            font-size: 24px;
            color: #ff6600;
            min-width: 30px;
        }
        
        .cta {
            text-align: center;
            margin-top: 40px;
            padding: 30px;
            background: linear-gradient(135deg, #ff6600 0%, #ff8533 100%);
            border-radius: 15px;
            color: white;
        }
        
        .cta h3 {
            font-size: 26px;
            margin-bottom: 20px;
        }
        
        .contact-info {
            margin-top: 25px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        
        .contact-info p {
            margin: 10px 0;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .contact-info a {
            color: white;
            text-decoration: none;
            font-weight: 600;
        }
        
        .contact-info a:hover {
            text-decoration: underline;
        }
        
        .contact-info i {
            font-size: 20px;
        }
        
        .back-button {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.3s;
        }
        
        .back-button:hover {
            background: #5a6268;
        }
        
        .back-button i {
            margin-right: 8px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 25px;
            }
            
            .header h1 {
                font-size: 28px;
                flex-direction: column;
                gap: 10px;
            }
            
            .header h1 i {
                font-size: 32px;
            }
            
            .intro p {
                font-size: 16px;
            }
            
            .feature-list li {
                font-size: 16px;
                padding: 12px;
            }
            
            .cta h3 {
                font-size: 22px;
            }
            
            .contact-info p {
                font-size: 16px;
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="artist-profile-mobile.php?tab=profile" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Profile
        </a>
        
        <div class="header">
            <h1>
                <i class="fas fa-music"></i>
                Boost Your Music — Get Heard Worldwide!
            </h1>
            <p>Take your sound to the next level</p>
        </div>
        
        <div class="content">
            <div class="intro">
                <p>Are you an artist ready to take your sound to the next level?</p>
            </div>
            
            <p style="margin-bottom: 20px; font-size: 18px;">
                At Boost Your Music, we help musicians get the exposure, streams, and audience they deserve. Whether you're just starting out or already making waves, our platform connects your music with fans, DJs, influencers, and media outlets across the globe.
            </p>
            
            <div class="features">
                <h2>
                    <i class="fas fa-rocket"></i>
                    What We Offer
                </h2>
                <ul class="feature-list">
                    <li>
                        <i class="fas fa-fire"></i>
                        <span>Promote your latest release</span>
                    </li>
                    <li>
                        <i class="fas fa-headphones"></i>
                        <span>Reach a wider audience</span>
                    </li>
                    <li>
                        <i class="fas fa-rocket"></i>
                        <span>Grow your fan base and streams</span>
                    </li>
                    <li>
                        <i class="fas fa-microphone"></i>
                        <span>Get featured on playlists and radio stations</span>
                    </li>
                    <li>
                        <i class="fas fa-users"></i>
                        <span>Connect with DJs, influencers, and media outlets</span>
                    </li>
                    <li>
                        <i class="fas fa-chart-line"></i>
                        <span>Increase your streaming numbers and visibility</span>
                    </li>
                    <li>
                        <i class="fas fa-globe"></i>
                        <span>Expand your reach to international markets</span>
                    </li>
                    <li>
                        <i class="fas fa-bullhorn"></i>
                        <span>Social media promotion and marketing campaigns</span>
                    </li>
                    <li>
                        <i class="fas fa-star"></i>
                        <span>Get press coverage and reviews</span>
                    </li>
                    <li>
                        <i class="fas fa-handshake"></i>
                        <span>Network with industry professionals</span>
                    </li>
                    <li>
                        <i class="fas fa-trophy"></i>
                        <span>Enter music competitions and awards</span>
                    </li>
                    <li>
                        <i class="fas fa-video"></i>
                        <span>Music video promotion and distribution</span>
                    </li>
                </ul>
            </div>
            
            <div style="text-align: center; margin: 30px 0; font-size: 20px; font-weight: 600; color: #333;">
                Let's make your music heard!
            </div>
            
            <div class="cta">
                <h3>Get Started Today</h3>
                <div class="contact-info">
                    <p>
                        <i class="fas fa-phone"></i>
                        <a href="tel:+256767088992">+256 767 088 992</a>
                    </p>
                    <p>
                        <i class="fas fa-envelope"></i>
                        <a href="mailto:admin@example.com">admin@example.com</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

