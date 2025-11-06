<?php
// search.php - Search page
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Song.php';
require_once 'classes/Artist.php';

$database = new Database();
$db = $database->getConnection();
$song = new Song($db);
$artist = new Artist($db);

$query = $_GET['q'] ?? '';
$results = [];
$artists = [];
$songs = [];

$search_error = '';
if (!empty($query)) {
    try {
        if (method_exists($song, 'searchSongs') && is_callable([$song, 'searchSongs'])) {
            $songs_result = $song->searchSongs($query, 20);
            if ($songs_result === false || $songs_result === null) {
                $songs = [];
            } elseif (is_array($songs_result)) {
                $songs = $songs_result;
            } else {
                $songs = [];
            }
        } else {
            $songs = [];
            error_log("Search method searchSongs not available or not callable");
        }
    } catch (PDOException $e) {
        $search_error = "Database error. Please try again.";
        $songs = [];
        error_log("Search PDO error: " . $e->getMessage());
    } catch (Exception $e) {
        $search_error = "Search error. Please try again.";
        $songs = [];
        error_log("Search error: " . $e->getMessage());
    } catch (Error $e) {
        $search_error = "Search error. Please try again.";
        $songs = [];
        error_log("Search fatal error: " . $e->getMessage());
    } catch (Throwable $e) {
        $search_error = "Search error. Please try again.";
        $songs = [];
        error_log("Search error: " . $e->getMessage());
    }
    
    try {
        if (method_exists($artist, 'searchArtists') && is_callable([$artist, 'searchArtists'])) {
            $artists_result = $artist->searchArtists($query, 10);
            if ($artists_result === false || $artists_result === null) {
                $artists = [];
            } elseif (is_array($artists_result)) {
                $artists = $artists_result;
            } else {
                $artists = [];
            }
        } else {
            $artists = [];
            if (empty($search_error)) {
                error_log("Search method searchArtists not available or not callable");
            }
        }
    } catch (PDOException $e) {
        if (empty($search_error)) {
            $search_error = "Database error. Please try again.";
        }
        $artists = [];
        error_log("Search PDO error: " . $e->getMessage());
    } catch (Exception $e) {
        if (empty($search_error)) {
            $search_error = "Search error. Please try again.";
        }
        $artists = [];
        error_log("Search error: " . $e->getMessage());
    } catch (Error $e) {
        if (empty($search_error)) {
            $search_error = "Search error. Please try again.";
        }
        $artists = [];
        error_log("Search fatal error: " . $e->getMessage());
    } catch (Throwable $e) {
        if (empty($search_error)) {
            $search_error = "Search error. Please try again.";
        }
        $artists = [];
        error_log("Search error: " . $e->getMessage());
    }
    
    if (empty($songs) && empty($artists) && empty($search_error)) {
        $search_error = "No results found. Please try a different search term.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
</head>
<body>
    <!-- Include General Header -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="container" style="max-width: 1400px; margin: 30px auto; padding: 0 20px;">
        <h1>Search</h1>
        
        <!-- Search Form -->
        <div class="row justify-content-center mb-5">
            <div class="col-md-8">
                <form method="GET" action="search.php">
                    <div class="input-group input-group-lg">
                        <input class="form-control" type="search" name="q" 
                               value="<?php echo htmlspecialchars($query); ?>" 
                               placeholder="Search for songs, artists..." aria-label="Search">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if (!empty($query)): ?>
            <h2>Search Results for "<?php echo htmlspecialchars($query); ?>"</h2>
            
            <?php if (!empty($search_error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($search_error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Artists Results -->
            <?php if (!empty($artists)): ?>
                <h3>Artists</h3>
                <div class="row mb-4">
                    <?php foreach ($artists as $artist_item): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-circle fa-3x text-primary mb-2"></i>
                                    <h6><?php echo htmlspecialchars($artist_item['name']); ?></h6>
                                    <?php
                                    // Generate artist slug for friendly URL
                                    $artistSlug = strtolower($artist_item['name']);
                                    $artistSlug = preg_replace('/[^a-z0-9\s]+/', '', $artistSlug);
                                    $artistSlug = preg_replace('/\s+/', '-', trim($artistSlug));
                                    ?>
                                    <a href="/artist/<?php echo urlencode($artistSlug); ?>" class="btn btn-sm btn-primary">
                                        View Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Songs Results -->
            <?php if (!empty($songs)): ?>
                <h3>Songs</h3>
                <div class="row">
                    <?php foreach ($songs as $song_item): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($song_item['title']); ?></h6>
                                    <p class="card-text text-muted">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($song_item['artist_name']); ?>
                                        <br>
                                        <i class="fas fa-clock"></i> <?php echo format_duration($song_item['duration']); ?>
                                    </p>
                                    <button class="btn btn-primary btn-sm" onclick="playSong(<?php echo $song_item['id']; ?>)">
                                        <i class="fas fa-play"></i> Play
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($artists) && empty($songs)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h3>No Results Found</h3>
                    <p class="text-muted">Try searching for something else.</p>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h3>Search Music</h3>
                <p class="text-muted">Enter a search term above to find songs and artists.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Include Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
</body>
</html>

