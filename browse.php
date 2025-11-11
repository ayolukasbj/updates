<?php
// browse.php - Browse music page
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Song.php';
require_once 'classes/Artist.php';

$database = new Database();
$db = $database->getConnection();
$song = new Song($db);
$artist = new Artist($db);

// Get filter parameters
$genre_id = isset($_GET['genre']) ? (int)$_GET['genre'] : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get songs and genres
if ($genre_id) {
    $songs = $song->getSongsByGenre($genre_id, 1, 20);
} elseif ($search_query) {
    $songs = $song->searchSongs($search_query, 1, 20);
} else {
    $songs = $song->getSongs(1, 20);
}
$genres = $artist->getGenres();

$page_title = 'Browse Music';
include 'includes/header.php';
?>

<div class="main-content container mt-4">
    <h1>Browse Music</h1>
    
    <div class="row">
        <div class="col-md-3">
            <h5>Genres</h5>
            <div class="list-group">
                <a href="browse.php" class="list-group-item list-group-item-action <?php echo !$genre_id ? 'active' : ''; ?>">
                    All Genres
                </a>
                <?php foreach ($genres as $genre): ?>
                    <a href="browse.php?genre=<?php echo $genre['id']; ?>" class="list-group-item list-group-item-action <?php echo $genre_id == $genre['id'] ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($genre['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="mb-3">
                <form method="GET" action="browse.php" class="d-flex">
                    <input type="text" name="search" class="form-control me-2" placeholder="Search songs..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if ($search_query || $genre_id): ?>
                        <a href="browse.php" class="btn btn-secondary ms-2">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <h5><?php echo $genre_id ? 'Genre: ' . htmlspecialchars($genres[array_search($genre_id, array_column($genres, 'id'))]['name'] ?? '') : ($search_query ? 'Search Results' : 'All Songs'); ?></h5>
            
            <?php if (empty($songs)): ?>
                <div class="alert alert-info">
                    <p>No songs found.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($songs as $song_item): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($song_item['title']); ?></h6>
                                    <p class="card-text"><?php echo htmlspecialchars($song_item['artist_name'] ?? 'Unknown Artist'); ?></p>
                                    <small class="text-muted"><?php echo function_exists('format_duration') ? format_duration($song_item['duration'] ?? 0) : ($song_item['duration'] ?? 0) . 's'; ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
