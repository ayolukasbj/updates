<?php
/**
 * Auto Tagger Class
 * Automatically tags uploaded MP3 files with site branding
 */

// Load MP3Tagger class if not already loaded
if (!class_exists('MP3Tagger')) {
    $mp3_tagger_paths = [
        __DIR__ . '/class-mp3-tagger.php',
        dirname(__DIR__) . '/includes/class-mp3-tagger.php',
    ];
    
    foreach ($mp3_tagger_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

if (!class_exists('AutoTagger')) {
    class AutoTagger {
        /**
         * Automatically tag an uploaded MP3 file
         */
        public static function tagUploadedSong($file_path, $song_data, $uploader_name = '') {
            try {
                if (!file_exists($file_path)) {
                    throw new Exception('File not found: ' . $file_path);
                }
                
                $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                if ($file_ext !== 'mp3') {
                    return ['success' => false, 'new_file_path' => null];
                }
                
                // Get site settings
                $site_name = 'Music Platform';
                $site_logo = '';
                $site_logo_path = null;
                
                if (class_exists('SettingsManager')) {
                    $site_name = SettingsManager::getSiteName();
                    $site_logo = SettingsManager::getSiteLogo();
                } elseif (function_exists('get_option')) {
                    $site_name = get_option('site_name', 'Music Platform');
                    $site_logo = get_option('site_logo', '');
                } elseif (defined('SITE_NAME')) {
                    $site_name = SITE_NAME;
                }
                
                // Resolve site logo path - try multiple locations
                if (!empty($site_logo)) {
                    $logo_paths = [
                        __DIR__ . '/../../../' . ltrim($site_logo, '/'),
                        __DIR__ . '/../../../../' . ltrim($site_logo, '/'),
                        $site_logo,
                        realpath(__DIR__ . '/../../../' . ltrim($site_logo, '/')),
                        realpath($site_logo),
                    ];
                    
                    foreach ($logo_paths as $path) {
                        if ($path && file_exists($path)) {
                            $site_logo_path = $path;
                            error_log("MP3 Auto-tagging: Found site logo at: $path");
                            break;
                        }
                    }
                    
                    if (!$site_logo_path) {
                        error_log("MP3 Auto-tagging: WARNING - Site logo not found. Tried paths: " . implode(', ', array_filter($logo_paths)));
                    }
                } else {
                    error_log("MP3 Auto-tagging: WARNING - Site logo setting is empty");
                }
                
                // Get tag templates
                $tag_templates = self::getTagTemplates();
                
                if (empty($tag_templates)) {
                    return ['success' => false, 'new_file_path' => null];
                }
                
                // Create MP3Tagger instance
                $tagger = new MP3Tagger($file_path);
                
                // Prepare options
                $options = [
                    'site_name' => $site_name,
                    'site_logo_path' => $site_logo_path,
                    'song_title' => $song_data['title'] ?? '',
                    'artist_name' => $song_data['artist'] ?? $uploader_name,
                    'uploader_name' => $uploader_name,
                    'year' => $song_data['year'] ?? '',
                    'genre' => $song_data['genre'] ?? '',
                    'tag_templates' => $tag_templates,
                ];
                
                // Auto-tag the file
                $result = $tagger->autoTag($options);
                
                // Rename file if template is set
                $new_file_path = null;
                $filename_template = $tag_templates['filename'] ?? '';
                if (!empty($filename_template) && $result) {
                    $replacements = [
                        '{TITLE}' => $song_data['title'] ?? '',
                        '{ARTIST}' => $song_data['artist'] ?? $uploader_name,
                        '{SITE_NAME}' => $site_name,
                    ];
                    
                    $new_filename = str_replace(array_keys($replacements), array_values($replacements), $filename_template);
                    $new_file_path = $tagger->renameFile($new_filename, $replacements);
                }
                
                return [
                    'success' => $result,
                    'new_file_path' => $new_file_path
                ];
            } catch (Exception $e) {
                error_log('Auto-tagging error: ' . $e->getMessage());
                return ['success' => false, 'new_file_path' => null];
            }
        }
        
        /**
         * Get tag templates from database
         */
        public static function getTagTemplates() {
            try {
                if (!function_exists('get_db_connection')) {
                    return [];
                }
                
                $conn = get_db_connection();
                if (!$conn) {
                    return [];
                }
                
                $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'id3_tag_%'");
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $templates = [];
                foreach ($results as $row) {
                    $key = str_replace('id3_tag_', '', $row['setting_key']);
                    $templates[$key] = $row['setting_value'];
                }
                
                // Check if auto-tagging is enabled
                $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'id3_auto_tagging_enabled'");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && $result['setting_value'] !== '1') {
                    return []; // Auto-tagging disabled
                }
                
                return $templates;
            } catch (Exception $e) {
                error_log('Error getting tag templates: ' . $e->getMessage());
                return [];
            }
        }
    }
}

