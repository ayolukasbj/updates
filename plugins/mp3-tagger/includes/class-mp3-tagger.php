<?php
/**
 * MP3 Tagger Class
 * Handles reading and writing ID3 tags to MP3 files
 */

if (!class_exists('MP3Tagger')) {
    class MP3Tagger {
        private $file_path;
        private $getID3;
        private $getID3_writer;
        
        public function __construct($file_path) {
            $this->file_path = $file_path;
            
            // Check if getID3 library exists
            $possible_paths = [
                __DIR__ . '/../../../includes/getid3/getid3.php',
                __DIR__ . '/../../../../includes/getid3/getid3.php',
                __DIR__ . '/../../../../vendor/james-heinrich/getid3/getid3/getid3.php',
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
                    $this->getID3_writer->tagformats = ['id3v2.3'];
                    $this->getID3_writer->overwrite_tags = true;
                    $this->getID3_writer->tag_encoding = 'UTF-8';
                } else {
                    throw new Exception('getID3 writer (write.php) not found');
                }
            } else {
                throw new Exception('getID3 library not found. Please install getID3 library.');
            }
        }
        
        /**
         * Read tags from MP3 file
         */
        public function readTags() {
            try {
                $tags = $this->getID3->analyze($this->file_path);
                
                // Extract ID3v2 tags (preferred)
                $id3v2 = $tags['tags']['id3v2'] ?? [];
                
                // Fallback to ID3v1 if ID3v2 not available
                if (empty($id3v2)) {
                    $id3v1 = $tags['tags']['id3v1'] ?? [];
                } else {
                    $id3v1 = [];
                }
                
                return [
                    'title' => $this->getTagValue($id3v2, $id3v1, 'title'),
                    'artist' => $this->getTagValue($id3v2, $id3v1, 'artist'),
                    'album' => $this->getTagValue($id3v2, $id3v1, 'album'),
                    'year' => $this->getTagValue($id3v2, $id3v1, 'year'),
                    'genre' => $this->getTagValue($id3v2, $id3v1, 'genre'),
                    'track_number' => $this->getTagValue($id3v2, $id3v1, 'track_number'),
                    'comment' => $this->getTagValue($id3v2, $id3v1, 'comment'),
                    'cover_art' => $this->extractCoverArt($tags),
                ];
            } catch (Exception $e) {
                throw new Exception('Error reading tags: ' . $e->getMessage());
            }
        }
        
        /**
         * Write tags to MP3 file
         */
        public function writeTags($tags) {
            try {
                $tag_data = [];
                
                // Map tags to getID3 format
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
                
                // Additional ID3 tags
                if (!empty($tags['band'])) {
                    $tag_data['band'] = [$tags['band']]; // TPE2 - Album Artist/Band
                }
                if (!empty($tags['publisher'])) {
                    $tag_data['publisher'] = [$tags['publisher']]; // TPUB - Publisher
                }
                if (!empty($tags['composer'])) {
                    $tag_data['composer'] = [$tags['composer']]; // TCOM - Composer
                }
                if (!empty($tags['original_artist'])) {
                    $tag_data['original_artist'] = [$tags['original_artist']]; // TOPE - Original Artist
                }
                if (!empty($tags['copyright'])) {
                    $tag_data['copyright'] = [$tags['copyright']]; // TCOP - Copyright
                }
                if (!empty($tags['encoded_by'])) {
                    $tag_data['encoded_by'] = [$tags['encoded_by']]; // TENC - Encoded By
                }
                
                // Handle cover art - add to tag_data BEFORE assigning to writer
                if (!empty($tags['cover_art_path']) && file_exists($tags['cover_art_path'])) {
                    error_log("MP3 Tagger: Adding cover art from: " . $tags['cover_art_path']);
                    $art_data = file_get_contents($tags['cover_art_path']);
                    
                    if ($art_data === false || empty($art_data)) {
                        error_log("MP3 Tagger: ERROR - Could not read cover art file: " . $tags['cover_art_path']);
                    } else {
                        $mime_type = function_exists('mime_content_type') ? mime_content_type($tags['cover_art_path']) : '';
                        
                        if (empty($mime_type)) {
                            // Fallback MIME type detection
                            $ext = strtolower(pathinfo($tags['cover_art_path'], PATHINFO_EXTENSION));
                            switch ($ext) {
                                case 'jpg':
                                case 'jpeg':
                                    $mime_type = 'image/jpeg';
                                    break;
                                case 'png':
                                    $mime_type = 'image/png';
                                    break;
                                case 'gif':
                                    $mime_type = 'image/gif';
                                    break;
                                default:
                                    $mime_type = 'image/jpeg';
                            }
                        }
                        
                        error_log("MP3 Tagger: Cover art MIME type: $mime_type, Size: " . strlen($art_data) . " bytes");
                        
                        // Use 'ATTACHED_PICTURE' for getID3 (it converts to APIC for ID3v2)
                        // Format: array of arrays with required fields
                        $tag_data['ATTACHED_PICTURE'] = [[
                            'data' => $art_data,
                            'picturetypeid' => 3, // Cover (front)
                            'description' => 'Cover',
                            'mime' => $mime_type
                        ]];
                        
                        error_log("MP3 Tagger: Cover art added to tag_data['ATTACHED_PICTURE'], Size: " . strlen($art_data) . " bytes");
                    }
                } else {
                    if (empty($tags['cover_art_path'])) {
                        error_log("MP3 Tagger: WARNING - cover_art_path is empty");
                    } else {
                        error_log("MP3 Tagger: WARNING - Cover art file not found: " . $tags['cover_art_path']);
                    }
                }
                
                // Assign tag_data to writer AFTER adding cover art
                $this->getID3_writer->tag_data = $tag_data;
                
                // Write tags
                if ($this->getID3_writer->WriteTags()) {
                    if (!empty($this->getID3_writer->warnings)) {
                        error_log('MP3 Tagger warnings: ' . implode(', ', $this->getID3_writer->warnings));
                    }
                    return true;
                } else {
                    throw new Exception('Failed to write tags: ' . implode(', ', $this->getID3_writer->errors));
                }
            } catch (Exception $e) {
                throw new Exception('Error writing tags: ' . $e->getMessage());
            }
        }
        
        /**
         * Get tag value from ID3v2 or ID3v1
         */
        private function getTagValue($id3v2, $id3v1, $key) {
            if (!empty($id3v2[$key][0])) {
                return $id3v2[$key][0];
            }
            if (!empty($id3v1[$key][0])) {
                return $id3v1[$key][0];
            }
            return '';
        }
        
        /**
         * Extract cover art from tags
         */
        private function extractCoverArt($tags) {
            if (isset($tags['id3v2']['APIC'][0]['data'])) {
                return $tags['id3v2']['APIC'][0]['data'];
            }
            return null;
        }
        
        /**
         * Automatically tag MP3 file with site branding
         */
        public function autoTag($options) {
            try {
                $templates = $options['tag_templates'] ?? [];
                $site_name = $options['site_name'] ?? 'Music Platform';
                $song_title = $options['song_title'] ?? '';
                $artist_name = $options['artist_name'] ?? '';
                $uploader_name = $options['uploader_name'] ?? '';
                $year = $options['year'] ?? '';
                $genre = $options['genre'] ?? '';
                
                // Build replacements array
                $replacements = [
                    '{TITLE}' => $song_title,
                    '{ARTIST}' => $artist_name,
                    '{UPLOADER}' => $uploader_name,
                    '{SITE_NAME}' => $site_name,
                ];
                
                // Function to replace placeholders in template
                $replacePlaceholders = function($template) use ($replacements) {
                    if (empty($template)) {
                        return '';
                    }
                    return str_replace(array_keys($replacements), array_values($replacements), $template);
                };
                
                $tags = [
                    'title' => !empty($templates['title']) ? $replacePlaceholders($templates['title']) : ($song_title . ' | ' . $site_name),
                    'artist' => !empty($templates['artist']) ? $replacePlaceholders($templates['artist']) : ($artist_name . ' | ' . $site_name),
                    'album' => !empty($templates['album']) ? $replacePlaceholders($templates['album']) : $site_name,
                    'year' => $year,
                    'genre' => $genre,
                    'comment' => !empty($templates['comment']) ? $replacePlaceholders($templates['comment']) : ('Downloaded from ' . $site_name),
                    'band' => !empty($templates['band']) ? $replacePlaceholders($templates['band']) : '',
                    'publisher' => !empty($templates['publisher']) ? $replacePlaceholders($templates['publisher']) : '',
                    'composer' => !empty($templates['composer']) ? $replacePlaceholders($templates['composer']) : '',
                    'original_artist' => !empty($templates['original_artist']) ? $replacePlaceholders($templates['original_artist']) : '',
                    'copyright' => !empty($templates['copyright']) ? $replacePlaceholders($templates['copyright']) : '',
                    'encoded_by' => !empty($templates['encoded_by']) ? $replacePlaceholders($templates['encoded_by']) : '',
                ];
                
                // Always set cover art if site logo is available
                if (!empty($options['site_logo_path'])) {
                    $site_logo_path = $options['site_logo_path'];
                    
                    // Try multiple path variations
                    $logo_paths = [
                        $site_logo_path,
                        realpath($site_logo_path),
                        __DIR__ . '/../../../' . ltrim($site_logo_path, '/'),
                        __DIR__ . '/../../../../' . ltrim($site_logo_path, '/'),
                    ];
                    
                    $logo_found = false;
                    foreach ($logo_paths as $path) {
                        if ($path && file_exists($path)) {
                            $tags['cover_art_path'] = $path;
                            $logo_found = true;
                            error_log("MP3 Tagger autoTag: Using site logo at: $path");
                            break;
                        }
                    }
                    
                    if (!$logo_found) {
                        error_log("MP3 Tagger autoTag: WARNING - Site logo not found. Tried: " . implode(', ', array_filter($logo_paths)));
                    }
                } else {
                    error_log("MP3 Tagger autoTag: WARNING - site_logo_path is empty in options");
                }
                
                return $this->writeTags($tags);
            } catch (Exception $e) {
                error_log('Auto-tagging error: ' . $e->getMessage());
                throw $e;
            }
        }
        
        /**
         * Rename file with site branding
         */
        public function renameFile($new_filename, $replacements) {
            $dir = dirname($this->file_path);
            $ext = pathinfo($this->file_path, PATHINFO_EXTENSION);
            
            foreach ($replacements as $key => $value) {
                $new_filename = str_replace('{' . $key . '}', $value, $new_filename);
            }
            
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
}

