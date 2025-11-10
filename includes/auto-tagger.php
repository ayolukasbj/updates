<?php
/**
 * Auto Tagger Helper
 * Automatically tags uploaded MP3 files with site branding
 */

require_once __DIR__ . '/mp3-tagger.php';
require_once __DIR__ . '/settings.php';

class AutoTagger {
    /**
     * Automatically tag an uploaded MP3 file
     * @param string $file_path Path to the MP3 file
     * @param array $song_data Song data from upload form
     * @param string $uploader_name Name of the uploader
     * @return array ['success' => bool, 'new_file_path' => string|null] Returns new file path if renamed
     */
    public static function tagUploadedSong($file_path, $song_data, $uploader_name = '') {
        try {
            // Check if file exists
            if (!file_exists($file_path)) {
                throw new Exception('File not found: ' . $file_path);
            }
            
            // Check if file is MP3 (only MP3 files support ID3 tags)
            $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            if ($file_ext !== 'mp3') {
                // Skip tagging for non-MP3 files
                error_log('Auto-tagging skipped: File is not MP3 format (' . $file_ext . ')');
                return ['success' => false, 'new_file_path' => null];
            }
            
            // Get site settings
            $site_name = SettingsManager::getSiteName();
            $site_logo = SettingsManager::getSiteLogo();
            
            // Get tag templates from database
            $tag_templates = self::getTagTemplates();
            
            // If auto-tagging is disabled, return early
            if (empty($tag_templates)) {
                error_log('Auto-tagging is disabled or no templates found');
                return ['success' => false, 'new_file_path' => null];
            }
            
            // Resolve site logo path
            $site_logo_path = null;
            if (!empty($site_logo)) {
                // Try relative path first
                $logo_paths = [
                    __DIR__ . '/../' . ltrim($site_logo, '/'),
                    $site_logo,
                ];
                
                foreach ($logo_paths as $path) {
                    if (file_exists($path)) {
                        $site_logo_path = $path;
                        break;
                    }
                }
            }
            
            // Create MP3Tagger instance
            $tagger = new MP3Tagger($file_path);
            
            // Prepare options for auto-tagging
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
                    'TITLE' => self::sanitizeFilename($song_data['title'] ?? 'song'),
                    'ARTIST' => self::sanitizeFilename($song_data['artist'] ?? $uploader_name ?: 'artist'),
                    'SITE_NAME' => self::sanitizeFilename($site_name),
                ];
                $new_file_path = $tagger->renameFile($filename_template, $replacements);
                if ($new_file_path) {
                    // Return relative path from site root
                    $site_root = realpath(__DIR__ . '/..');
                    if ($site_root && strpos($new_file_path, $site_root) === 0) {
                        $new_file_path = str_replace($site_root . DIRECTORY_SEPARATOR, '', $new_file_path);
                        $new_file_path = str_replace('\\', '/', $new_file_path);
                    }
                }
            }
            
            return ['success' => $result, 'new_file_path' => $new_file_path];
        } catch (Exception $e) {
            error_log('Auto-tagging error: ' . $e->getMessage());
            // Don't throw - allow upload to continue even if tagging fails
            return ['success' => false, 'new_file_path' => null];
        }
    }
    
    /**
     * Get tag templates from database
     * @return array Tag templates
     */
    public static function getTagTemplates() {
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = new Database();
            $conn = $db->getConnection();
            
            // Check if auto-tagging is enabled
            $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'id3_auto_tagging_enabled'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $enabled = ($result && $result['setting_value'] === '1');
            
            if (!$enabled) {
                // Auto-tagging is disabled, return empty array
                return [];
            }
            
            $templates = [
                'title' => '{TITLE} | {SITE_NAME}',
                'artist' => '{ARTIST} | {SITE_NAME}',
                'album' => '{SITE_NAME}',
                'comment' => 'Downloaded from {SITE_NAME}',
                'band' => '{SITE_NAME}',
                'publisher' => '{SITE_NAME}',
                'composer' => '{SITE_NAME}',
                'original_artist' => '{UPLOADER}',
                'copyright' => '{SITE_NAME}',
                'encoded_by' => '{SITE_NAME}',
                'filename' => '{TITLE} by {ARTIST} [{SITE_NAME}]',
            ];
            
            // Load from database
            $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'id3_tag_%'");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                $key = str_replace('id3_tag_', '', $row['setting_key']);
                if (isset($templates[$key]) && !empty($row['setting_value'])) {
                    $templates[$key] = $row['setting_value'];
                }
            }
            
            return $templates;
        } catch (Exception $e) {
            error_log('Error loading tag templates: ' . $e->getMessage());
            // Return defaults (assuming enabled)
            return [
                'title' => '{TITLE} | {SITE_NAME}',
                'artist' => '{ARTIST} | {SITE_NAME}',
                'album' => '{SITE_NAME}',
                'comment' => 'Downloaded from {SITE_NAME}',
                'band' => '{SITE_NAME}',
                'publisher' => '{SITE_NAME}',
                'composer' => '{SITE_NAME}',
                'original_artist' => '{UPLOADER}',
                'copyright' => '{SITE_NAME}',
                'encoded_by' => '{SITE_NAME}',
                'filename' => '{TITLE} by {ARTIST} [{SITE_NAME}]',
            ];
        }
    }
    
    /**
     * Sanitize filename
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    private static function sanitizeFilename($filename) {
        // Remove special characters, keep only alphanumeric, spaces, hyphens, underscores
        $filename = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $filename);
        // Replace multiple spaces with single space
        $filename = preg_replace('/\s+/', ' ', $filename);
        // Trim
        $filename = trim($filename);
        // Limit length
        if (strlen($filename) > 50) {
            $filename = substr($filename, 0, 50);
        }
        return $filename;
    }
}

