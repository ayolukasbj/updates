<?php
require_once 'config/database.php';

$test_name = 'Ayolukasbj';
$db = new Database();
$conn = $db->getConnection();

// Simulate what artist-profile.php does
$artist_name = trim(urldecode($test_name));
echo "Input: '$artist_name'\n\n";

// Check which verified column exists
$colCheck = $conn->query("SHOW COLUMNS FROM users");
$columns = $colCheck->fetchAll(PDO::FETCH_COLUMN);
$verifiedCol = 'u.is_verified';
if (!in_array('is_verified', $columns) && in_array('email_verified', $columns)) {
    $verifiedCol = 'u.email_verified as is_verified';
}
echo "Using verified column: $verifiedCol\n\n";

// Test the exact query from artist-profile.php
$userNameStmt = $conn->prepare("
    SELECT u.id, u.username as name, u.avatar, u.bio, $verifiedCol as verified,
           COALESCE((
               SELECT COUNT(DISTINCT s.id)
               FROM songs s
               WHERE s.uploaded_by = u.id
                  OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
           ), 0) as total_songs,
           COALESCE((
               SELECT SUM(s.plays)
               FROM songs s
               WHERE s.uploaded_by = u.id
                  OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
           ), 0) as total_plays,
           COALESCE((
               SELECT SUM(s.downloads)
               FROM songs s
               WHERE s.uploaded_by = u.id
                  OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
           ), 0) as total_downloads
    FROM users u
    WHERE LOWER(TRIM(u.username)) = LOWER(TRIM(?))
");
$userNameStmt->execute([$artist_name]);
$user_data = $userNameStmt->fetch(PDO::FETCH_ASSOC);

if ($user_data) {
    echo "FOUND USER:\n";
    print_r($user_data);
} else {
    echo "USER NOT FOUND\n\n";
    
    // Try without TRIM
    echo "Trying without TRIM:\n";
    $stmt2 = $conn->prepare("SELECT u.id, u.username FROM users u WHERE LOWER(u.username) = LOWER(?)");
    $stmt2->execute([$artist_name]);
    $result2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($result2) {
        echo "Found without TRIM: ";
        print_r($result2);
    } else {
        echo "Still not found without TRIM\n";
    }
    
    // List all usernames
    echo "\nAll usernames in database:\n";
    $all = $conn->query("SELECT id, username FROM users LIMIT 20");
    while($row = $all->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, Username: '{$row['username']}'\n";
    }
}
?>

