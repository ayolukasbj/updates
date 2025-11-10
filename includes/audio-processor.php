<?php
/**
 * Audio Processor Helper
 * Handles audio file manipulation (concatenation, voice tags, etc.)
 */

class AudioProcessor {
    private $ffmpeg_path;
    private $temp_dir;
    
    public function __construct($ffmpeg_path = null) {
        // Try to find FFmpeg
        if ($ffmpeg_path && file_exists($ffmpeg_path)) {
            $this->ffmpeg_path = $ffmpeg_path;
        } else {
            // Try common locations
            $common_paths = [
                'ffmpeg', // In PATH
                '/usr/bin/ffmpeg',
                '/usr/local/bin/ffmpeg',
                'C:\\ffmpeg\\bin\\ffmpeg.exe',
                'C:\\xampp\\ffmpeg\\bin\\ffmpeg.exe',
            ];
            
            foreach ($common_paths as $path) {
                if ($this->checkFFmpeg($path)) {
                    $this->ffmpeg_path = $path;
                    break;
                }
            }
        }
        
        // Set temp directory
        $this->temp_dir = sys_get_temp_dir() . '/audio_processing_' . uniqid();
        if (!is_dir($this->temp_dir)) {
            mkdir($this->temp_dir, 0755, true);
        }
    }
    
    /**
     * Check if FFmpeg is available at given path
     */
    private function checkFFmpeg($path) {
        $command = escapeshellarg($path) . ' -version 2>&1';
        $output = @shell_exec($command);
        return strpos($output, 'ffmpeg version') !== false;
    }
    
    /**
     * Check if FFmpeg is available
     */
    public function isAvailable() {
        return !empty($this->ffmpeg_path);
    }
    
    /**
     * Get FFmpeg path
     */
    public function getFFmpegPath() {
        return $this->ffmpeg_path;
    }
    
    /**
     * Add voice tag to audio file (at start or end)
     * 
     * @param string $audio_file Path to main audio file
     * @param string $voice_tag_file Path to voice tag audio file
     * @param string $position 'start' or 'end'
     * @param string $output_file Path to save output file (optional)
     * @return string Path to processed file
     */
    public function addVoiceTag($audio_file, $voice_tag_file, $position = 'end', $output_file = null) {
        if (!$this->isAvailable()) {
            throw new Exception('FFmpeg is not available. Please install FFmpeg first.');
        }
        
        if (!file_exists($audio_file)) {
            throw new Exception('Audio file not found: ' . $audio_file);
        }
        
        if (!file_exists($voice_tag_file)) {
            throw new Exception('Voice tag file not found: ' . $voice_tag_file);
        }
        
        // Generate output file path if not provided
        if (!$output_file) {
            $path_info = pathinfo($audio_file);
            $output_file = $path_info['dirname'] . '/' . $path_info['filename'] . '_tagged.' . $path_info['extension'];
        }
        
        // Create file list for concatenation
        $file_list = $this->temp_dir . '/file_list.txt';
        
        if ($position === 'start') {
            // Voice tag first, then main audio
            $list_content = "file '" . str_replace("'", "'\\''", realpath($voice_tag_file)) . "'\n";
            $list_content .= "file '" . str_replace("'", "'\\''", realpath($audio_file)) . "'\n";
        } else {
            // Main audio first, then voice tag
            $list_content = "file '" . str_replace("'", "'\\''", realpath($audio_file)) . "'\n";
            $list_content .= "file '" . str_replace("'", "'\\''", realpath($voice_tag_file)) . "'\n";
        }
        
        file_put_contents($file_list, $list_content);
        
        // Build FFmpeg command
        $ffmpeg_cmd = escapeshellarg($this->ffmpeg_path);
        $file_list_escaped = escapeshellarg($file_list);
        $output_escaped = escapeshellarg($output_file);
        
        // Use concat demuxer for better compatibility
        $command = "$ffmpeg_cmd -f concat -safe 0 -i $file_list_escaped -c copy $output_escaped 2>&1";
        
        // Execute command
        $output = shell_exec($command);
        $return_code = 0;
        exec($command . '; echo $?', $output_lines, $return_code);
        
        // Clean up temp file list
        @unlink($file_list);
        
        if ($return_code !== 0 || !file_exists($output_file)) {
            $error_msg = !empty($output) ? $output : 'Unknown error';
            throw new Exception('Failed to add voice tag: ' . $error_msg);
        }
        
        return $output_file;
    }
    
    /**
     * Add voice tag with fade in/out for smoother transitions
     */
    public function addVoiceTagWithFade($audio_file, $voice_tag_file, $position = 'end', $fade_duration = 1.0, $output_file = null) {
        if (!$this->isAvailable()) {
            throw new Exception('FFmpeg is not available.');
        }
        
        if (!file_exists($audio_file) || !file_exists($voice_tag_file)) {
            throw new Exception('One or more files not found.');
        }
        
        if (!$output_file) {
            $path_info = pathinfo($audio_file);
            $output_file = $path_info['dirname'] . '/' . $path_info['filename'] . '_tagged.' . $path_info['extension'];
        }
        
        // Get duration of main audio
        $duration_cmd = escapeshellarg($this->ffmpeg_path) . ' -i ' . escapeshellarg($audio_file) . ' 2>&1 | grep "Duration"';
        $duration_output = shell_exec($duration_cmd);
        preg_match('/Duration: (\d{2}):(\d{2}):(\d{2})\.(\d{2})/', $duration_output, $matches);
        $main_duration = 0;
        if (!empty($matches)) {
            $main_duration = ($matches[1] * 3600) + ($matches[2] * 60) + $matches[3] + ($matches[4] / 100);
        }
        
        // Build filter complex for smooth transition
        $ffmpeg_cmd = escapeshellarg($this->ffmpeg_path);
        $audio_escaped = escapeshellarg($audio_file);
        $tag_escaped = escapeshellarg($voice_tag_file);
        $output_escaped = escapeshellarg($output_file);
        
        if ($position === 'start') {
            // Fade out main audio at end, fade in voice tag at start
            $fade_start = max(0, $main_duration - $fade_duration);
            $command = "$ffmpeg_cmd -i $audio_escaped -i $tag_escaped -filter_complex \"[0:a]afade=t=out:st=$fade_start:d=$fade_duration[a0];[1:a]afade=t=in:st=0:d=$fade_duration[a1];[a0][a1]concat=n=2:v=0:a=1[out]\" -map \"[out]\" -c:a libmp3lame -b:a 320k $output_escaped 2>&1";
        } else {
            // Fade out main audio at end, fade in voice tag at start
            $fade_start = max(0, $main_duration - $fade_duration);
            $command = "$ffmpeg_cmd -i $audio_escaped -i $tag_escaped -filter_complex \"[0:a]afade=t=out:st=$fade_start:d=$fade_duration[a0];[1:a]afade=t=in:st=0:d=$fade_duration[a1];[a0][a1]concat=n=2:v=0:a=1[out]\" -map \"[out]\" -c:a libmp3lame -b:a 320k $output_escaped 2>&1";
        }
        
        $output = shell_exec($command);
        $return_code = 0;
        exec($command . '; echo $?', $output_lines, $return_code);
        
        if ($return_code !== 0 || !file_exists($output_file)) {
            throw new Exception('Failed to add voice tag with fade: ' . (!empty($output) ? $output : 'Unknown error'));
        }
        
        return $output_file;
    }
    
    /**
     * Get audio file duration in seconds
     */
    public function getDuration($audio_file) {
        if (!$this->isAvailable()) {
            throw new Exception('FFmpeg is not available.');
        }
        
        $command = escapeshellarg($this->ffmpeg_path) . ' -i ' . escapeshellarg($audio_file) . ' 2>&1 | grep "Duration"';
        $output = shell_exec($command);
        
        if (preg_match('/Duration: (\d{2}):(\d{2}):(\d{2})\.(\d{2})/', $output, $matches)) {
            return ($matches[1] * 3600) + ($matches[2] * 60) + $matches[3] + ($matches[4] / 100);
        }
        
        return 0;
    }
    
    /**
     * Clean up temp files
     */
    public function cleanup() {
        if (is_dir($this->temp_dir)) {
            $files = glob($this->temp_dir . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->temp_dir);
        }
    }
    
    /**
     * Destructor - cleanup
     */
    public function __destruct() {
        $this->cleanup();
    }
}

