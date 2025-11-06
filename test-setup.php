<?php
// test-setup.php - Test if everything is working
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Setup Test</h1>";

// Test 1: Config
echo "<h2>1. Config Test</h2>";
try {
    require_once 'config/config.php';
    echo "✅ Config loaded successfully<br>";
    echo "SITE_NAME: " . SITE_NAME . "<br>";
} catch (Exception $e) {
    echo "❌ Config error: " . $e->getMessage() . "<br>";
}

// Test 2: Song Storage
echo "<h2>2. Song Storage Test</h2>";
try {
    require_once 'includes/song-storage.php';
    echo "✅ Song storage loaded successfully<br>";
    
    $songs = getSongs();
    echo "Songs count: " . count($songs) . "<br>";
    
    $featured = getFeaturedSongs();
    echo "Featured songs: " . count($featured) . "<br>";
    
    $recent = getRecentSongs();
    echo "Recent songs: " . count($recent) . "<br>";
} catch (Exception $e) {
    echo "❌ Song storage error: " . $e->getMessage() . "<br>";
}

// Test 3: AJAX Endpoint
echo "<h2>3. AJAX Endpoint Test</h2>";
echo '<button onclick="testAjax()">Test AJAX</button>';
echo '<div id="ajax-result"></div>';

// Test 4: File Structure
echo "<h2>4. File Structure Test</h2>";
$files = [
    'config/config.php',
    'includes/song-storage.php',
    'ajax/index.php',
    'assets/js/ajax-navigation.js',
    'assets/js/mini-player.js',
    'data/songs.json'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file missing<br>";
    }
}
?>

<script>
function testAjax() {
    fetch('ajax/test.php')
        .then(response => response.text())
        .then(data => {
            document.getElementById('ajax-result').innerHTML = 'AJAX Response: ' + data;
        })
        .catch(error => {
            document.getElementById('ajax-result').innerHTML = 'AJAX Error: ' + error;
        });
}
</script>
