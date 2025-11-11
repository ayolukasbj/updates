<?php
// Test rewrite rules
echo "Testing URL Rewrite...<br>";
echo "Current URL: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Base Path: " . dirname($_SERVER['SCRIPT_NAME']) . "<br>";
echo "GET params: " . print_r($_GET, true) . "<br>";

if (isset($_GET['slug'])) {
    echo "<h2>SUCCESS! Slug parameter received: " . htmlspecialchars($_GET['slug']) . "</h2>";
} else {
    echo "<h2>No slug parameter found. Rewrite might not be working.</h2>";
}
?>




