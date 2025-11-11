<?php
// Test if header.php works
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'auth-check.php';
ini_set('display_errors', 1);

$page_title = 'Test Header';

echo "Before header include<br>";
flush();

try {
    include 'includes/header.php';
    echo "After header include<br>";
    flush();
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "<br>";
} catch (Error $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

echo "End of test<br>";
flush();

if (ob_get_level()) {
    ob_end_flush();
}

