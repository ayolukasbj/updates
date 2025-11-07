<?php
// test-news.php - Simple test to see exact error
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Testing News Page</h1>";

$slug = 'one-pupil-misses-ple-at-swaria-centre-in-soroti';

echo "<p>Testing slug: <strong>$slug</strong></p>";

// Test 1: Config
echo "<h2>Test 1: Config</h2>";
try {
    require_once 'config/config.php';
    echo "✓ Config loaded<br>";
    echo "SITE_NAME: " . (defined('SITE_NAME') ? SITE_NAME : 'NOT DEFINED') . "<br>";
} catch (Exception $e) {
    echo "✗ Config error: " . $e->getMessage() . "<br>";
    die();
}

// Test 2: Database
echo "<h2>Test 2: Database</h2>";
try {
    require_once 'config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    if ($conn) {
        echo "✓ Database connected<br>";
    } else {
        echo "✗ Database connection failed<br>";
        die();
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
    die();
}

// Test 3: Find News Article
echo "<h2>Test 3: Find News Article</h2>";
try {
    $slug_variations = [
        $slug,
        urldecode($slug),
        str_replace('-', ' ', $slug),
        str_replace('-', '_', $slug)
    ];
    
    $found = false;
    foreach ($slug_variations as $slug_to_try) {
        $stmt = $conn->prepare("
            SELECT id, title, slug, is_published 
            FROM news 
            WHERE slug = ? OR slug LIKE ?
            LIMIT 1
        ");
        $stmt->execute([$slug_to_try, '%' . $slug_to_try . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo "✓ Found article!<br>";
            echo "ID: " . $result['id'] . "<br>";
            echo "Title: " . htmlspecialchars($result['title']) . "<br>";
            echo "Slug in DB: " . htmlspecialchars($result['slug']) . "<br>";
            echo "Is Published: " . ($result['is_published'] ? 'Yes' : 'No') . "<br>";
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "✗ Article not found with any slug variation<br>";
        echo "<p>Trying title search...</p>";
        $title_search = str_replace('-', ' ', $slug);
        $stmt = $conn->prepare("
            SELECT id, title, slug, is_published 
            FROM news 
            WHERE title LIKE ?
            LIMIT 5
        ");
        $stmt->execute(['%' . $title_search . '%']);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($results) {
            echo "Found " . count($results) . " articles with similar title:<br>";
            foreach ($results as $r) {
                echo "- ID: {$r['id']}, Title: " . htmlspecialchars($r['title']) . ", Slug: " . htmlspecialchars($r['slug']) . "<br>";
            }
        } else {
            echo "✗ No articles found with similar title<br>";
        }
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}

// Test 4: Include news-details.php
echo "<h2>Test 4: Include news-details.php</h2>";
echo "<p>Attempting to include news-details.php...</p>";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";

$_GET['slug'] = $slug;
ob_start();
try {
    include 'news-details.php';
    $output = ob_get_clean();
    echo "✓ news-details.php included successfully";
    echo "</pre>";
    echo "<p>Output length: " . strlen($output) . " bytes</p>";
} catch (Exception $e) {
    $output = ob_get_clean();
    echo "✗ Error including news-details.php: " . $e->getMessage();
    echo "</pre>";
    echo "<p>Output before error: " . htmlspecialchars(substr($output, 0, 500)) . "</p>";
} catch (Error $e) {
    $output = ob_get_clean();
    echo "✗ Fatal error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    echo "</pre>";
}

?>

