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
        $getid3_path = __DIR__ . '/../vendor/getid3/getid3/getid3.php';
        if (!file_exists($getid3_path)) {
            // Try alternative path
            $getid3_path = __DIR__ . '/../includes/getid3/getid3.php';
        }
        
        if (file_exists($getid3_path)) {
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
            }
        } else {
            throw new Exception('getID3 library not found. Please install it first.');
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
            $tag_data['comment'] = [$tags['comment']];
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

