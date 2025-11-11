<?php
// Load site settings for footer
function getFooterSetting($key, $default = '') {
    try {
        require_once __DIR__ . '/../config/database.php';
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

$site_name = getFooterSetting('site_name', SITE_NAME ?? 'Music Platform');
$site_tagline = getFooterSetting('site_tagline', 'Your Ultimate Music Streaming Platform');
$footer_phone = getFooterSetting('footer_phone', '');
$footer_email = getFooterSetting('footer_email', 'admin@example.com');
$footer_company_text = getFooterSetting('footer_company_text', 'This website is the property of Music Platform');
$footer_contact_text = getFooterSetting('footer_contact_text', 'Contact us for more information and advertising');
$current_year = date('Y');
?>
<footer style="background: linear-gradient(135deg, #1e4d72 0%, #2c5f8d 100%); color: #fff; margin-top: 60px; padding: 40px 20px 20px;">
    <div style="max-width: 1400px; margin: 0 auto;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 40px; margin-bottom: 30px;">
            
            <!-- Quick Links -->
            <div>
                <h4 style="font-size: 18px; font-weight: 600; margin-bottom: 20px; color: #fff;">Quick Links</h4>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="margin-bottom: 12px;"><a href="index.php" style="color: rgba(255,255,255,0.8); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='#fff';" onmouseout="this.style.color='rgba(255,255,255,0.8)';"><i class="fas fa-home" style="margin-right: 8px;"></i> Home</a></li>
                    <li style="margin-bottom: 12px;"><a href="songs.php" style="color: rgba(255,255,255,0.8); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='#fff';" onmouseout="this.style.color='rgba(255,255,255,0.8)';"><i class="fas fa-music" style="margin-right: 8px;"></i> Latest Music</a></li>
                    <li style="margin-bottom: 12px;"><a href="artists.php" style="color: rgba(255,255,255,0.8); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='#fff';" onmouseout="this.style.color='rgba(255,255,255,0.8)';"><i class="fas fa-users" style="margin-right: 8px;"></i> Artists</a></li>
                    <li style="margin-bottom: 12px;"><a href="top-100.php" style="color: rgba(255,255,255,0.8); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='#fff';" onmouseout="this.style.color='rgba(255,255,255,0.8)';"><i class="fas fa-trophy" style="margin-right: 8px;"></i> Top 100</a></li>
                    <li style="margin-bottom: 12px;"><a href="news.php" style="color: rgba(255,255,255,0.8); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='#fff';" onmouseout="this.style.color='rgba(255,255,255,0.8)';"><i class="fas fa-newspaper" style="margin-right: 8px;"></i> News</a></li>
                </ul>
            </div>

            <!-- Contact Us Information -->
            <div>
                <h4 style="font-size: 18px; font-weight: 600; margin-bottom: 20px; color: #fff;">Contact Us</h4>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <?php if (!empty($footer_phone)): ?>
                    <li style="margin-bottom: 12px; color: rgba(255,255,255,0.8);">
                        <i class="fas fa-phone" style="margin-right: 8px;"></i>
                        <a href="tel:<?php echo htmlspecialchars($footer_phone); ?>" style="color: rgba(255,255,255,0.8); text-decoration: none;"><?php echo htmlspecialchars($footer_phone); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($footer_email)): ?>
                    <li style="margin-bottom: 12px; color: rgba(255,255,255,0.8);">
                        <i class="fas fa-envelope" style="margin-right: 8px;"></i>
                        <a href="mailto:<?php echo htmlspecialchars($footer_email); ?>" style="color: rgba(255,255,255,0.8); text-decoration: none;"><?php echo htmlspecialchars($footer_email); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($footer_company_text) || !empty($footer_contact_text)): ?>
                    <li style="margin-bottom: 12px; color: rgba(255,255,255,0.8); line-height: 1.6;">
                        <?php if (!empty($footer_company_text)): ?>
                        <p style="margin: 0 0 10px 0;">
                            <?php echo htmlspecialchars($footer_company_text); ?>
                        </p>
                        <?php endif; ?>
                        <?php if (!empty($footer_contact_text)): ?>
                        <p style="margin: 0;">
                            <?php echo htmlspecialchars($footer_contact_text); ?>
                        </p>
                        <?php endif; ?>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Copyright -->
        <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; text-align: center; color: rgba(255,255,255,0.6);">
            <p style="margin: 0;">&copy; <?php echo $current_year; ?> <?php echo htmlspecialchars($site_name); ?>. All rights reserved.</p>
        </div>
    </div>
</footer>

<style>
    /* Ensure footer stretches to full browser width - breaks out of any container */
    footer {
        width: 100vw !important;
        max-width: none !important;
        margin-left: calc(50% - 50vw) !important;
        margin-right: calc(50% - 50vw) !important;
        padding-left: calc(50vw - 50%) !important;
        padding-right: calc(50vw - 50%) !important;
        box-sizing: border-box;
    }
    
    @media (max-width: 768px) {
        footer > div > div {
            grid-template-columns: 1fr !important;
            gap: 30px !important;
        }
        
        footer {
            padding: 30px 15px 15px !important;
        }
    }
</style>

