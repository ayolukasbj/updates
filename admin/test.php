<?php
// Simple test file to verify admin folder is accessible
echo "<!DOCTYPE html>";
echo "<html><head><title>Admin Folder Test</title></head><body>";
echo "<h1>✅ Admin folder is accessible!</h1>";
echo "<p>Current URL: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<hr>";
echo "<a href='login.php'>Go to Admin Login</a>";
echo "</body></html>";
?>

