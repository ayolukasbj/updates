<?php
require_once 'auth-check.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');

if (empty($query) || strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $searchTerm = "%{$query}%";
    $users = [];
    $artists = [];
    $songsArtists = [];
    
    // Search in users table (username, email, full_name)
    try {
        $usersStmt = $conn->prepare("
            SELECT DISTINCT 
                username as name,
                'user' as type,
                CONCAT('User: ', username) as display
            FROM users 
            WHERE username LIKE ? 
               OR email LIKE ? 
               OR (full_name IS NOT NULL AND full_name LIKE ?)
            LIMIT 10
        ");
        $usersStmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Users table might not exist
    }
    
    // Search in artists table (name)
    try {
        $artistsStmt = $conn->prepare("
            SELECT DISTINCT 
                name,
                'artist' as type,
                CONCAT('Artist: ', name) as display
            FROM artists 
            WHERE name LIKE ?
            LIMIT 10
        ");
        $artistsStmt->execute([$searchTerm]);
        $artists = $artistsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Artists table might not exist
    }
    
    // Also search in songs table for artist names (in case artists table doesn't exist)
    try {
        $songsArtistsStmt = $conn->prepare("
            SELECT DISTINCT 
                artist as name,
                'artist' as type,
                CONCAT('Artist: ', artist) as display
            FROM songs 
            WHERE artist LIKE ?
            GROUP BY artist
            LIMIT 10
        ");
        $songsArtistsStmt->execute([$searchTerm]);
        $songsArtists = $songsArtistsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Songs table might not exist
    }
    
    // Combine and deduplicate results
    $results = [];
    $seen = [];
    
    foreach ($users as $user) {
        $key = strtolower($user['name']);
        if (!isset($seen[$key])) {
            $results[] = $user;
            $seen[$key] = true;
        }
    }
    
    foreach ($artists as $artist) {
        $key = strtolower($artist['name']);
        if (!isset($seen[$key])) {
            $results[] = $artist;
            $seen[$key] = true;
        }
    }
    
    foreach ($songsArtists as $artist) {
        $key = strtolower($artist['name']);
        if (!isset($seen[$key])) {
            $results[] = $artist;
            $seen[$key] = true;
        }
    }
    
    // Limit total results
    $results = array_slice($results, 0, 15);
    
    echo json_encode($results);
    
} catch (Exception $e) {
    echo json_encode([]);
}

