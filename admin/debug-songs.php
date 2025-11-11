<?php
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

header('Content-Type: text/plain; charset=utf-8');

echo "=== SONGS MANAGEMENT DEBUG ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Check if songs table exists
echo "1. CHECKING SONGS TABLE:\n";
echo "------------------------\n";
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'songs'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Songs table EXISTS\n";
        
        // Show structure
        echo "\nTable columns:\n";
        $stmt = $conn->query("DESCRIBE songs");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - {$row['Field']} ({$row['Type']})\n";
        }
    } else {
        echo "❌ Songs table DOES NOT EXIST!\n";
        echo "ACTION: Run http://localhost/music/admin/quick-fix.php\n";
        exit;
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit;
}

// 2. Check if artists table exists
echo "\n2. CHECKING ARTISTS TABLE:\n";
echo "---------------------------\n";
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'artists'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Artists table EXISTS\n";
    } else {
        echo "❌ Artists table DOES NOT EXIST!\n";
        echo "ACTION: Run http://localhost/music/admin/quick-fix.php\n";
        exit;
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit;
}

// 3. Count songs
echo "\n3. COUNTING SONGS:\n";
echo "-------------------\n";
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM songs");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Songs in database: $count\n";
    
    if ($count == 0) {
        echo "\n❌ NO SONGS IN DATABASE!\n";
        
        // Check JSON
        $jsonFile = '../data/songs.json';
        if (file_exists($jsonFile)) {
            $data = json_decode(file_get_contents($jsonFile), true);
            $json_count = count($data ?? []);
            echo "Songs in JSON file: $json_count\n";
            
            if ($json_count > 0) {
                echo "\nACTION: Run http://localhost/music/admin/migrate-songs.php\n";
            }
        }
        exit;
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit;
}

// 4. Run the EXACT query from admin/songs.php
echo "\n4. TESTING ADMIN SONGS QUERY:\n";
echo "------------------------------\n";
try {
    $sql = "
        SELECT s.*, a.name as artist_name
        FROM songs s
        LEFT JOIN artists a ON s.artist_id = a.id
        ORDER BY s.upload_date DESC
        LIMIT 20
    ";
    
    echo "Query:\n$sql\n\n";
    
    $stmt = $conn->query($sql);
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Results: " . count($songs) . " songs found\n\n";
    
    if (count($songs) > 0) {
        echo "Sample songs:\n";
        foreach ($songs as $idx => $song) {
            echo "\n[" . ($idx + 1) . "] ID: {$song['id']}\n";
            echo "    Title: {$song['title']}\n";
            echo "    Artist: " . ($song['artist_name'] ?? 'NULL') . "\n";
            echo "    Plays: {$song['plays']}\n";
            echo "    Downloads: {$song['downloads']}\n";
            echo "    File: {$song['file_path']}\n";
            if (isset($song['cover_art'])) {
                echo "    Cover: {$song['cover_art']}\n";
            }
        }
        
        echo "\n✅ SONGS QUERY WORKS!\n";
        echo "✅ Songs should appear at: http://localhost/music/admin/songs.php\n";
        
    } else {
        echo "❌ QUERY RETURNED 0 SONGS!\n";
        echo "This is strange - database has songs but query returns nothing.\n";
    }
    
} catch (Exception $e) {
    echo "❌ QUERY ERROR: " . $e->getMessage() . "\n";
}

// 5. Check total stats
echo "\n5. CHECKING STATISTICS:\n";
echo "------------------------\n";
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM songs");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $conn->query("SELECT SUM(plays) as plays, SUM(downloads) as downloads FROM songs");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total songs: $total\n";
    echo "Total plays: " . ($stats['plays'] ?? 0) . "\n";
    echo "Total downloads: " . ($stats['downloads'] ?? 0) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// 6. Direct link test
echo "\n6. NEXT STEPS:\n";
echo "---------------\n";
echo "Visit these URLs:\n";
echo "1. Admin Songs Page: http://localhost/music/admin/songs.php\n";
echo "2. Quick Fix Tool: http://localhost/music/admin/quick-fix.php\n";
echo "3. Migration Tool: http://localhost/music/admin/migrate-songs.php\n";

echo "\n=== END DEBUG ===\n";
?>

