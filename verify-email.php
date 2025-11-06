<?php
// verify-email.php
// Email verification page

require_once 'config/config.php';

$auth = new AuthController();
$auth->verifyEmail();
?>
