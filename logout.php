<?php
// logout.php
// Logout functionality

require_once 'config/config.php';

$auth = new AuthController();
$auth->logout();
?>
