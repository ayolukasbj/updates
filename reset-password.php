<?php
// reset-password.php
// Password reset page

require_once 'config/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect(SITE_URL . '/dashboard.php');
}

$auth = new AuthController();
$auth->resetPassword();
?>
