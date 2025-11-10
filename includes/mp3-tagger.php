<?php
/**
 * MP3 Tagger Helper
 * Handles reading and writing ID3 tags to MP3 files
 */

class MP3Tagger {
    private $file_path;
    private $getID3;
    private $getID3_writer;
    
    public function __construct($file_path) {
        $this->file_path = $file_path;
        
        // Check if getID3 library exists, if not, provide instructions
        // Try multiple possible paths
        $possible_paths = [
            __DIR__ . '/getid3/getid3.php',  // includes/getid3/getid3.php
            __DIR__ . '/../vendor/james-heinrich/getid3/getid3/getid3.php',  // Composer path
            __DIR__ . '/../vendor/getid3/getid3/getid3.php',  // Alternative vendor path
            __DIR__ . '/../includes/getid3/getid3.php',  // Alternative includes path
        ];
        
        $getid3_path = null;
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $getid3_path = $path;
                break;
            }
        }
        
        if ($getid3_path && file_exists($getid3_path)) {
            require_once $getid3_path;
            $this->getID3 = new getID3;
            $this->getID3->encoding = 'UTF-8';
            
            // Load getID3 writer
            $writer_path = str_replace('getid3.php', 'write.php', $getid3_path);
            if (file_exists($writer_path)) {
                require_once $writer_path;
                $this->getID3_writer = new getid3_writetags;
                $this->getID3_writer->filename = $this->file_path;
                // Use only one ID3v2 version (id3v2.3 is most compatible)
                $this->getID3_writer->tagformats = ['id3v2.3'];
                $this->getID3_writer->overwrite_tags = true;
                $this->getID3_writer->tag_encoding = 'UTF-8';
            } else {
                throw new Exception('getID3 writer (write.php) not found at: ' . $writer_path);
            }
        } else {
            $searched_paths = implode(', ', $possible_paths);
            throw new Exception('getID3 library not found. Searched paths: ' . $searched_paths . '. Please install getID3 library. See MP3_TAGGER_SETUP.md for instructions.');
        }
    }
    
    /**
     * Read ID3 tags from MP3 file
     */
    public function readTags() {
        if (!file_exists($this->file_path)) {
            throw new Exception('File not found: ' . $this->file_path);
        }
        
        $info = $this->getID3->analyze($this->file_path);
        
        // Extract ID3 tags
        $tags = [
            'title' => $info['tags']['id3v2']['title'][0] ?? $info['tags']['id3v1']['title'][0] ?? '',
            'artist' => $info['tags']['id3v2']['artist'][0] ?? $info['tags']['id3v1']['artist'][0] ?? '',
            'album' => $info['tags']['id3v2']['album'][0] ?? $info['tags']['id3v1']['album'][0] ?? '',
            'year' => $info['tags']['id3v2']['year'][0] ?? $info['tags']['id3v1']['year'][0] ?? '',
            'genre' => $info['tags']['id3v2']['genre'][0] ?? $info['tags']['id3v1']['genre'][0] ?? '',
            'track_number' => $info['tags']['id3v2']['track_number'][0] ?? $info['tags']['id3v1']['track_number'][0] ?? '',
            'comment' => $info['tags']['id3v2']['comment'][0]['data'] ?? $info['tags']['id3v1']['comment'][0] ?? '',
            'band' => $info['tags']['id3v2']['band'][0] ?? $info['tags']['id3v2']['ensemble'][0] ?? '',
            'publisher' => $info['tags']['id3v2']['publisher'][0] ?? $info['tags']['id3v2']['publisher'][0] ?? '',
            'composer' => $info['tags']['id3v2']['composer'][0] ?? '',
            'original_artist' => $info['tags']['id3v2']['original_artist'][0] ?? $info['tags']['id3v2']['original_artist'][0] ?? '',
            'copyright' => $info['tags']['id3v2']['copyright'][0] ?? '',
            'encoded_by' => $info['tags']['id3v2']['encoded_by'][0] ?? '',
            'album_art' => null,
            'lyrics' => $info['tags']['id3v2']['unsynchronised_lyric'][0]['data'] ?? $info['tags']['id3v2']['synchronised_lyric'][0]['data'] ?? '',
        ];
        
        // Extract album art if available
        if (isset($info['id3v2']['APIC'][0]['data'])) {
            $tags['album_art'] = base64_encode($info['id3v2']['APIC'][0]['data']);
            $tags['album_art_mime'] = $info['id3v2']['APIC'][0]['mime'] ?? 'image/jpeg';
        } elseif (isset($info['id3v2']['PIC'][0]['data'])) {
            $tags['album_art'] = base64_encode($info['id3v2']['PIC'][0]['data']);
            $tags['album_art_mime'] = 'image/jpeg';
        }
        
        return $tags;
    }
    
    /**
     * Write ID3 tags to MP3 file
     */
    public function writeTags($tags) {
        if (!file_exists($this->file_path)) {
            throw new Exception('File not found: ' . $this->file_path);
        }
        
        if (!$this->getID3_writer) {
            throw new Exception('getID3 writer not initialized');
        }
        
        // Prepare tag data
        $tag_data = [];
        
        if (!empty($tags['title'])) {
            $tag_data['title'] = [$tags['title']];
        }
        if (!empty($tags['artist'])) {
            $tag_data['artist'] = [$tags['artist']];
        }
        if (!empty($tags['album'])) {
            $tag_data['album'] = [$tags['album']];
        }
        if (!empty($tags['year'])) {
            $tag_data['year'] = [$tags['year']];
        }
        if (!empty($tags['genre'])) {
            $tag_data['genre'] = [$tags['genre']];
        }
        if (!empty($tags['track_number'])) {
            $tag_data['track_number'] = [$tags['track_number']];
        }
        if (!empty($tags['comment'])) {
            $tag_data['comment'] = [['data' => $tags['comment']]];
        }
        if (!empty($tags['band'])) {
            $tag_data['band'] = [$tags['band']];
        }
        if (!empty($tags['publisher'])) {
            $tag_data['publisher'] = [$tags['publisher']];
        }
        if (!empty($tags['composer'])) {
            $tag_data['composer'] = [$tags['composer']];
        }
        if (!empty($tags['original_artist'])) {
            $tag_data['original_artist'] = [$tags['original_artist']];
        }
        if (!empty($tags['copyright'])) {
            $tag_data['copyright'] = [$tags['copyright']];
        }
        if (!empty($tags['encoded_by'])) {
            $tag_data['encoded_by'] = [$tags['encoded_by']];
        }
        if (!empty($tags['lyrics'])) {
            $tag_data['unsynchronised_lyric'] = [['language' => 'eng', 'description' => '', 'data' => $tags['lyrics']]];
        }
        
        $this->getID3_writer->tag_data = $tag_data;
        
        // Handle album art
        if (!empty($tags['album_art_path']) && file_exists($tags['album_art_path'])) {
            $art_data = file_get_contents($tags['album_art_path']);
            $mime_type = mime_content_type($tags['album_art_path']);
            
            $this->getID3_writer->tag_data['attached_picture'][0] = [
                'data' => $art_data,
                'picturetypeid' => 3, // Cover (front)
                'description' => 'Cover',
                'mime' => $mime_type
            ];
        } elseif (!empty($tags['album_art_data']) && !empty($tags['album_art_mime'])) {
            // Album art provided as base64 data
            $art_data = base64_decode($tags['album_art_data']);
            $this->getID3_writer->tag_data['attached_picture'][0] = [
                'data' => $art_data,
                'picturetypeid' => 3,
                'description' => 'Cover',
                'mime' => $tags['album_art_mime']
            ];
        }
        
        // Write tags
        if ($this->getID3_writer->WriteTags()) {
            if (!empty($this->getID3_writer->warnings)) {
                error_log('MP3 Tagger warnings: ' . implode(', ', $this->getID3_writer->warnings));
            }
            return true;
        } else {
            $errors = !empty($this->getID3_writer->errors) ? implode(', ', $this->getID3_writer->errors) : 'Unknown error';
            throw new Exception('Failed to write tags: ' . $errors);
        }
    }
    
    /**
     * Extract album art from MP3 file
     */
    public function extractAlbumArt($output_path = null) {
        $tags = $this->readTags();
        
        if (empty($tags['album_art'])) {
            return false;
        }
        
        $art_data = base64_decode($tags['album_art']);
        $mime = $tags['album_art_mime'] ?? 'image/jpeg';
        
        // Determine file extension
        $ext = 'jpg';
        if (strpos($mime, 'png') !== false) {
            $ext = 'png';
        } elseif (strpos($mime, 'gif') !== false) {
            $ext = 'gif';
        }
        
        // If output path not provided, create one
        if (!$output_path) {
            $output_path = dirname($this->file_path) . '/cover_' . basename($this->file_path, '.mp3') . '.' . $ext;
        }
        
        if (file_put_contents($output_path, $art_data)) {
            return $output_path;
        }
        
        return false;
    }
    
    /**
     * Automatically tag MP3 file with site branding
     * @param array $options Options array with:
     *   - site_name: Site name
     *   - site_logo_path: Path to site logo image file
     *   - song_title: Original song title
     *   - artist_name: Original artist name
     *   - uploader_name: Name of the uploader
     *   - year: Release year
     *   - genre: Genre
     *   - tag_templates: Array of tag templates (optional, will use defaults if not provided)
     * @return bool Success status
     */
    public function autoTag($options) {
        try {
            // Get tag templates from options or use defaults
            $templates = $options['tag_templates'] ?? [];
            $site_name = $options['site_name'] ?? 'Music Platform';
            $song_title = $options['song_title'] ?? '';
            $artist_name = $options['artist_name'] ?? '';
            $uploader_name = $options['uploader_name'] ?? '';
            $year = $options['year'] ?? '';
            $genre = $options['genre'] ?? '';
            
            // Prepare tags with templates
            $tags = [
                'title' => !empty($templates['title']) ? str_replace(['{TITLE}', '{SITE_NAME}'], [$song_title, $site_name], $templates['title']) : ($song_title . ' | ' . $site_name),
                'artist' => !empty($templates['artist']) ? str_replace(['{ARTIST}', '{SITE_NAME}'], [$artist_name, $site_name], $templates['artist']) : ($artist_name . ' | ' . $site_name),
                'album' => !empty($templates['album']) ? str_replace(['{SITE_NAME}'], [$site_name], $templates['album']) : $site_name,
                'year' => $year,
                'genre' => $genre,
                'comment' => !empty($templates['comment']) ? str_replace(['{SITE_NAME}'], [$site_name], $templates['comment']) : ('Downloaded from ' . $site_name),
                'band' => !empty($templates['band']) ? str_replace(['{SITE_NAME}'], [$site_name], $templates['band']) : $site_name,
                'publisher' => !empty($templates['publisher']) ? str_replace(['{SITE_NAME}'], [$site_name], $templates['publisher']) : $site_name,
                'composer' => !empty($templates['composer']) ? str_replace(['{SITE_NAME}'], [$site_name], $templates['composer']) : $site_name,
                'original_artist' => !empty($templates['original_artist']) ? str_replace(['{UPLOADER}', '{ARTIST}'], [$uploader_name, $artist_name], $templates['original_artist']) : ($uploader_name ?: $artist_name),
                'copyright' => !empty($templates['copyright']) ? str_replace(['{SITE_NAME}'], [$site_name], $templates['copyright']) : $site_name,
                'encoded_by' => !empty($templates['encoded_by']) ? str_replace(['{SITE_NAME}'], [$site_name], $templates['encoded_by']) : $site_name,
            ];
            
            // Add album art (site logo)
            if (!empty($options['site_logo_path']) && file_exists($options['site_logo_path'])) {
                $tags['album_art_path'] = $options['site_logo_path'];
            }
            
            // Write tags
            return $this->writeTags($tags);
        } catch (Exception $e) {
            error_log('Auto-tagging error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Rename file with site branding
     * @param string $new_filename New filename pattern (e.g., "{TITLE} by {ARTIST} [{SITE_NAME}]")
     * @param array $replacements Array of replacements
     * @return string New file path
     */
    public function renameFile($new_filename, $replacements) {
        $dir = dirname($this->file_path);
        $ext = pathinfo($this->file_path, PATHINFO_EXTENSION);
        
        // Replace placeholders
        foreach ($replacements as $key => $value) {
            $new_filename = str_replace('{' . $key . '}', $value, $new_filename);
        }
        
        // Sanitize filename
        $new_filename = preg_replace('/[^a-zA-Z0-9_\-\[\]() ]/', '', $new_filename);
        $new_filename = trim($new_filename);
        
        $new_path = $dir . '/' . $new_filename . '.' . $ext;
        
        if (rename($this->file_path, $new_path)) {
            $this->file_path = $new_path;
            return $new_path;
        }
        
        return false;
    }
}

/**
 * Simple MP3 tagger using native PHP (fallback if getID3 not available)
 * Note: This is a basic implementation and may not support all ID3 features
 */
class SimpleMP3Tagger {
    private $file_path;
    
    public function __construct($file_path) {
        $this->file_path = $file_path;
    }
    
    /**
     * Read basic ID3v1 tags (last 128 bytes of MP3 file)
     */
    public function readTags() {
        if (!file_exists($this->file_path)) {
            throw new Exception('File not found');
        }
        
        $handle = fopen($this->file_path, 'rb');
        fseek($handle, -128, SEEK_END);
        $tag = fread($handle, 128);
        fclose($handle);
        
        if (substr($tag, 0, 3) !== 'TAG') {
            return ['title' => '', 'artist' => '', 'album' => '', 'year' => '', 'genre' => '', 'comment' => ''];
        }
        
        return [
            'title' => trim(substr($tag, 3, 30)),
            'artist' => trim(substr($tag, 33, 30)),
            'album' => trim(substr($tag, 63, 30)),
            'year' => trim(substr($tag, 93, 4)),
            'comment' => trim(substr($tag, 97, 28)),
            'genre' => ord(substr($tag, 127, 1))
        ];
    }
    
    /**
     * Write basic ID3v1 tags
     */
    public function writeTags($tags) {
        // This is a simplified version - full implementation would require binary manipulation
        // For production, use getID3 library
        throw new Exception('Simple tagger does not support writing. Please install getID3 library.');
    }
}

