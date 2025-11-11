<?php
// song-details.php - Song details page with full player
require_once 'config/config.php';
require_once 'includes/song-storage.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$songId = $_GET['id'];
$song = getSongById($songId);

if (!$song) {
    header('Location: index.php');
    exit;
}

// Get related songs (same artist)
$allSongs = getSongs();
$relatedSongs = array_filter($allSongs, function($s) use ($song) {
    return $s['id'] != $song['id'] && isset($s['artist']) && $s['artist'] == $song['artist'];
});
$relatedSongs = array_slice($relatedSongs, 0, 6);

// Debug: Check audio file
$audioFile = !empty($song['audio_file']) ? $song['audio_file'] : (!empty($song['file_path']) ? $song['file_path'] : 'demo-audio.mp3');
if (!file_exists($audioFile) && strpos($audioFile, 'http') !== 0) {
    error_log("Audio file not found: " . $audioFile);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($song['title']); ?> - <?php echo htmlspecialchars($song['artist']); ?> | <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://www.schillmania.com/projects/soundmanager2/demo/bar-ui/css/bar-ui.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f5f5;
            color: #333;
            min-height: 100vh;
            padding-bottom: 40px;
            font-family: Arial, sans-serif;
        }

        /* --- Global / Wrapper Styles --- */
        .custom-player-page-container {
            max-width: 100%;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            text-align: center;
        }

        .custom-player {
            border-radius: 0;
        }

        /* --- Main Player Box --- */
        .custom-player {
            position: relative;
            width: 100%;
            overflow: hidden;
            color: white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            border-radius: 8px 8px 0 0;
        }

        /* Cover art background image (blurred) - Bottom layer */
        .cover-bg-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            filter: blur(3px);
            opacity: 0.4;
            z-index: 1;
            transform: scale(1.02); /* Prevents blur edges */
        }

        /* Dark overlay on top of background */
        .dark-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2;
        }

        /* Checkered texture overlay - Grid pattern */
        .background-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(45deg, rgba(255, 255, 255, 0.05) 25%, transparent 25%),
                linear-gradient(-45deg, rgba(255, 255, 255, 0.05) 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, rgba(255, 255, 255, 0.05) 75%),
                linear-gradient(-45deg, transparent 75%, rgba(255, 255, 255, 0.05) 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
            background-color: transparent;
            z-index: 3;
        }

        .player-content-wrapper {
            display: flex;
            flex-direction: column;
            padding: 15px;
            min-height: 180px;
            position: relative;
            z-index: 10; /* Above all background layers */
        }

        /* --- Social Icons (Top Right) --- */
        .social-icons {
            display: flex;
            gap: 6px;
            justify-content: flex-end;
            margin-bottom: 15px;
        }

        /* --- Album Art and Title Row --- */
        .art-title-row {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-top: auto;
        }

        /* --- Album Art/Image (Square) --- */
        .album-art {
            width: 100px;
            height: 100px;
            border: 2px solid white;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.5);
            flex-shrink: 0;
            overflow: hidden;
        }

        .album-art img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* --- Song Title --- */
        .song-title {
            font-size: 1.3em;
            font-weight: 900;
            line-height: 1.1;
            text-transform: capitalize;
            margin: 0;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.8);
            color: white;
            text-align: left;
            flex: 1;
        }

        .social-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            line-height: 24px;
            text-decoration: none;
            color: white;
            transition: all 0.3s;
        }

        .social-icon:hover {
            transform: scale(1.1);
            color: white;
        }

        /* Specific Social Colors */
        .facebook { background-color: #3b5998; }
        .twitter { background-color: #55acee; }
        .googleplus { background-color: #dd4b39; }

        /* --- Download Button & Stats --- */
        .download-button {
            background-color: #6cbf4d;
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 1.1em;
            font-weight: bold;
            margin-top: 20px;
            cursor: pointer;
            border-radius: 5px;
            box-shadow: 0 3px 0 #52943b;
            transition: background-color 0.15s ease;
            text-decoration: none;
            display: inline-block;
        }

        .download-button:hover {
            background-color: #5aa63c;
            color: white;
        }

        .download-button:active {
            transform: translateY(2px);
            box-shadow: 0 1px 0 #52943b;
        }

        .stats {
            margin-top: 15px;
            font-size: 0.9em;
            color: #ff3366;
            font-weight: bold;
        }

        /* Keep existing header section for compatibility */
        .header-section {
            display: none;
        }
        
        .song-artwork {
            width: 280px;
            height: 280px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            background: #667eea;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .song-artwork img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .song-info-header {
            flex: 1;
        }

        .song-title-large {
            font-size: 48px;
            font-weight: 700;
            color: white;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.5);
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .header-section {
                padding-bottom: 0;
            }
            
            .song-title-large {
                font-size: 32px;
            }
            
            .song-artwork {
                width: 140px;
                height: 140px;
        }

            /* Mobile: Full width scrolling title in player */
            .current-song-name {
                padding-left: 10px;
                font-size: 12px;
            }
            
            .song-info-header {
                position: absolute;
                top: 10px;
                right: 10px;
                max-width: 200px;
                z-index: 10;
            }
            
            .song-title-large {
                font-size: 24px;
                text-shadow: 1px 1px 4px rgba(0,0,0,0.7);
        }

            /* Hide top bar on mobile */
            .player-top-bar {
                display: none !important;
            }
        }
        
        /* Scrolling Title Animation */
        @keyframes scrollTitle {
            0% {
                transform: translateX(100%);
            }
            100% {
                transform: translateX(-100%);
            }
        }
        
        @keyframes scrollTitleMobile {
            0% {
                transform: translateX(100%);
            }
            100% {
                transform: translateX(-50%);
            }
        }
        
        /* Scrolling title like SoundManager2 */
        /* No scrolling on desktop */
        #scrolling-title { overflow: hidden; white-space: nowrap; display: inline-block; }
        /* Mobile-only infinite scroll */
        @media (max-width: 768px) {
            #scrolling-title { animation: scrollTitleMobile 20s linear infinite; padding-left: 100%; }
        }
        
        /* Scrolling animation - runs immediately on page load */
        @keyframes scrollText {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-50%);
            }
        }
        
        .scrolling-text {
            animation: scrollText 25s linear infinite;
            animation-delay: 0s;
        }

        /* Scrolling animation only on mobile */
        @media (max-width: 768px) {
            .mobile-scroll-container > div {
                animation: scrollTitleMobile 20s linear infinite;
            }
        }
        
        .social-icons {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 15px;
        }

        .social-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            text-decoration: none;
            transition: transform 0.2s;
        }
        
        .social-icon.facebook { background: #3b5998; }
        .social-icon.twitter { background: #1da1f2; }
        .social-icon.google { background: #dd4b39; }

        .social-icon:hover {
            transform: scale(1.1);
        }
        
        /* Inline Player Section */
        .inline-player {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .player-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .player-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .control-btn {
            width: 38px;
            height: 38px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            color: #333;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            border-radius: 4px;
            padding: 0;
        }
        
        .control-btn:hover {
            background: #e0e0e0;
            border-color: #bbb;
        }
        
        .control-btn.active {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }
        
        .control-btn.active:hover {
            background: #5568d3;
        }

        .control-btn-white {
            background: transparent;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            border-radius: 4px;
        }
        
        .control-btn-white:hover {
            background: rgba(255,255,255,0.2);
        }

        .progress-bar-container {
            flex: 1;
            max-width: 400px;
            position: relative;
        }
        
        .progress-bar {
            width: 100%;
            height: 4px;
            background: rgba(255,255,255,0.2);
            border-radius: 2px;
            cursor: pointer;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: rgba(59, 130, 246, 0.8);
            border-radius: 2px;
            width: 30%;
            position: relative;
        }

        .progress-handle {
            position: absolute;
            right: -6px;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            background: white;
            border-radius: 50%;
            cursor: grab;
            border: 2px solid #667eea;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        /* Content Below Player */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .download-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .download-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }

        .download-btn:hover {
            background: #218838;
            color: white;
            text-decoration: none;
        }
        
        .download-btn i {
            margin-right: 8px;
        }

        .song-stats {
            font-size: 15px;
            color: #333;
            font-weight: 600;
        }

        .song-stats span {
            color: #dc3545;
            font-weight: 700;
            margin-right: 3px;
        }

        /* Sections */
        .sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .section-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .song-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .song-list-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .song-list-item:hover {
            background: #f8f9fa;
        }

        .song-list-item:last-child {
            border-bottom: none;
        }

        .song-list-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .song-list-artist {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .song-list-stats {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        @media (max-width: 968px) {
            .sections {
                grid-template-columns: 1fr;
            }
            
            .song-title-large {
                font-size: 36px;
            }
            
            .song-artwork {
                width: 200px;
                height: 200px;
            }
        }
        
        /* SM2 Bar UI option styles (inherit from demo) */
        .sm2-bar-ui { font-size: 23px; }
        .sm2-bar-ui .sm2-main-controls,
        .sm2-bar-ui .sm2-playlist-drawer { background-color: #2288cc; }
        .sm2-bar-ui .sm2-inline-texture { background: transparent; }
        /* Title sits a bit higher and close to left edge within stack */
        #scrolling-title { letter-spacing: .2px; }
        @media (max-width: 768px) {
            #scrolling-title { animation: scrollTitleMobile 20s linear infinite; padding-left: 100%; }
        }
        /* SM2 inline buttons: no borders/background; use native SM2 icon sprites */
        .sm2-bar-ui .sm2-inline-button {
            width: 35px;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: transparent !important;
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            padding: 0;
        }
        /* Show Font Awesome icons (hide SM2 sprite spans) */
        .sm2-bar-ui .sm2-inline-button .sm2-button-bd { display: none !important; }
        .sm2-bar-ui .sm2-inline-button i { color: #fff; font-size: 20px; line-height: 1; }
        .sm2-bar-ui .sm2-inline-button.play-pause { width: 40px; height: 40px; }
        .sm2-bar-ui .sm2-inline-button.play-pause i { font-size: 22px; }
        .sm2-bar-ui .sm2-inline-controls { display: flex; gap: 8px; margin-left: auto; }
        .sm2-bar-ui .sm2-main-controls { display: flex; align-items: center; gap: 12px; padding: 8px; }
        .sm2-bar-ui { background: rgba(30,77,114,0.95); padding: 3px 2px; }

        /* Custom volume bars icon */
        .vol-icon { display:inline-flex; align-items:flex-end; gap:2px; height:14px; }
        .vol-icon span { display:block; width:3px; background:#fff; border-radius:1px; }
        .vol-icon .b1 { height:6px; }
        .vol-icon .b2 { height:9px; }
        .vol-icon .b3 { height:12px; }
        .vol-icon .b4 { height:14px; }
        /* Muted state dims bars */
        .muted .vol-icon span { background: rgba(255,255,255,0.4); }

        /* Always-on marquee for the track title (start visible at x=0) */
        @keyframes marqueeTitle {
            0% { transform: translateX(0); }
            100% { transform: translateX(-100%); }
        }
        #scrolling-title {
            animation: marqueeTitle 28s linear infinite !important;
            will-change: transform;
            padding-left: 0;
        }
        /* Bottom bar: compact UI */
        .sm2-bar-ui { background: rgba(30,77,114,.95); padding: 3px 2px; }
        .sm2-bar-ui .sm2-main-controls { display:flex; align-items:center; gap:12px; padding: 6px 8px; background:transparent; }
        .sm2-inline-controls { display:flex; gap:8px; margin-left:auto; }
        .sm2-inline-button { width:35px; height:35px; display:inline-flex; align-items:center; justify-content:center; background:transparent!important; border:none!important; border-radius:0!important; box-shadow:none!important; padding:0; }
        .sm2-inline-button .sm2-button-bd { display:none; }
        .sm2-inline-button i { color:#fff; font-size:20px; line-height:1; }
        .sm2-inline-button.play-pause { width: 40px; height: 40px; }
        .sm2-inline-button.play-pause i { font-size: 22px; }
        /* Volume bars icon */
        .vol-icon { display:inline-flex; align-items:flex-end; gap:2px; height:12px; }
        .vol-icon span { display:block; width:3px; background:#fff; border-radius:1px; }
        .vol-icon .b1{height:5px}.vol-icon .b2{height:8px}.vol-icon .b3{height:10px}.vol-icon .b4{height:12px}
        .muted .vol-icon span{background:rgba(255,255,255,.4)}
        /* Scrolling title immediate start */
        @keyframes marqueeTitle { 0%{transform:translateX(100%)} 100%{transform:translateX(-100%)} }
        #scrolling-title{animation:marqueeTitle 5s linear infinite!important; will-change:transform; white-space:nowrap;}
        
        /* Push progress bar and title section up (both mobile and desktop) */
        .sm2-bar-ui .sm2-main-controls > div[style*="flex-direction:column"] {
            margin-top: -6px;
        }

        /* Desktop Responsive Styles */
        @media (min-width: 1024px) {
            .custom-player-page-container {
                max-width: 100%;
                margin: 0;
                padding-top: 70px;
                font-family: Arial, sans-serif;
                text-align: center;
            }

            .custom-player {
                position: relative;
                width: 100%;
            overflow: hidden;
                color: white;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
                border-radius: 8px 8px 0 0;
            }

            /* The main content area where the background is visible */
            .player-content-wrapper {
                display: flex;
                flex-direction: column;
                padding: 20px;
                min-height: 250px;
                position: relative;
                z-index: 10;
            }

            /* Blurred background image */
            .cover-bg-image {
                position: absolute;
                top: 0;
                left: 0;
            width: 100%;
                height: 100%;
            background-size: cover;
            background-position: center;
                filter: blur(4px);
                opacity: 0.3;
                z-index: 1;
                transform: scale(1.02);
            }

            /* Dark overlay for contrast */
            .dark-overlay {
                position: absolute;
                top: 0;
                left: 0;
            width: 100%;
            height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 2;
            }

            /* Checkered texture overlay - The prominent pixel grid */
            .background-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-image: 
                    linear-gradient(to right, rgba(0, 0, 0, 0.2) 1px, transparent 1px),
                    linear-gradient(to bottom, rgba(0, 0, 0, 0.2) 1px, transparent 1px);
                background-size: 8px 8px;
                background-color: rgba(51, 51, 51, 0.5);
                z-index: 3;
            }

            .social-icons {
                position: absolute;
                top: 20px;
                right: 20px;
                display: flex;
                gap: 8px;
                z-index: 11;
                margin-bottom: 0;
            }

            .social-icon {
                width: 30px;
                height: 30px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 14px;
                line-height: 30px;
                text-decoration: none;
                transition: transform 0.2s;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
            }

            .social-icon.facebook { background: #3b5998; }
            .social-icon.twitter { background: #55acee; }
            .social-icon.googleplus { background: #dd4b39; }

            .art-title-row {
            display: flex;
                align-items: center;
                justify-content: flex-start;
                gap: 25px;
                margin-top: auto;
                padding-bottom: 20px;
            }

            .album-art {
                width: 160px;
                height: 160px;
                border: 3px solid white;
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.7);
                flex-shrink: 0;
            }

            .album-art img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }

            .song-title {
                font-size: 2.5em;
                font-weight: 900;
                line-height: 1.1;
                text-transform: capitalize;
                margin: 0;
                text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.9);
                color: white;
                text-align: left;
                flex: 1;
            }

            /* SM2 Player Bar Styles */
            /* The deep purple/blue background color from the image */
            .sm2-bar-ui {
                background: #393e62;
                padding: 6px 12px;
                border-radius: 0 0 8px 8px;
            }

            .sm2-bar-ui .sm2-main-controls {
                display: flex;
                align-items: center;
            gap: 15px;
            }

            /* Play/Pause Button (Large) */
            .sm2-bar-ui #main-play-btn i {
                font-size: 30px;
                color: white;
            }

            /* All Small Icons (Volume, Prev, Next, Repeat) */
            .sm2-inline-button i {
                font-size: 18px;
                color: white;
            }

            /* Volume Bars Icon (Custom CSS for the span elements) */
            .vol-icon {
                height: 18px;
                gap: 3px;
            }
            .vol-icon span {
                width: 3px;
                background: #fff;
            }
            .vol-icon .b1{height:6px}.vol-icon .b2{height:10px}.vol-icon .b3{height:14px}.vol-icon .b4{height:18px}

            /* Progress Bar (The visible white circle and the thin line) */
            #bottom-progress-container {
                height: 4px;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 2px;
            }
            #bottom-progress-fill {
                background: white;
            }

            /* Progress Handle (The white circle scrubber) */
            .sm2-bar-ui .sm2-progress-ball {
                background: white;
                border: none;
                width: 14px;
                height: 14px;
            }

            /* Song Title Text in Player Bar */
            #scrolling-title {
                color: white;
                font-weight: 700;
                font-size: 14px;
            }

            /* General Styles for the Player Bar Container */
            .media-player-bar {
                background-color: #2b7bbd;
                color: white;
                font-family: Arial, sans-serif;
                padding: 0;
                width: 100%;
                box-sizing: border-box;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            }

            /* Styles for the Title/Top Area */
            .player-top-controls {
                background-color: #216ba5;
                padding: 8px 15px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .player-title {
                font-size: 14px;
                font-weight: normal;
            }

            /* Styling the badge part */
            .player-title span {
                background-color: rgba(255, 255, 255, 0.2);
                padding: 2px 5px;
                border-radius: 3px;
            font-size: 12px;
                margin-left: 5px;
            }

            /* Styles for the Main Control Area (Bottom Strip) */
            .player-main-area {
                display: flex;
                align-items: center;
                padding: 10px 15px;
                gap: 10px;
            }

            /* Style for the Play Button */
            .play-button {
                background: none;
                border: none;
                color: white;
                font-size: 20px;
                cursor: pointer;
                padding: 0;
                outline: none;
            }

            /* Style for the Time Displays */
            .time-current,
            .time-duration {
                font-size: 14px;
                white-space: nowrap;
            }

            /* Progress Bar (Timeline) Styles */
            .progress-container {
                flex-grow: 1;
                height: 30px;
                display: flex;
                align-items: center;
            }

            .progress-bar {
                width: 100%;
                height: 8px;
                -webkit-appearance: none;
                appearance: none;
                cursor: pointer;
                background: transparent;
                margin: 0;
            }

            /* Track (The dark blue background of the timeline) */
            .progress-bar::-webkit-slider-runnable-track {
                background: #1e5c8e;
                height: 8px;
                border-radius: 4px;
            }

            .progress-bar::-moz-range-track {
                background: #1e5c8e;
                height: 8px;
                border-radius: 4px;
            }

            /* Thumb (The white circle and the lighter progress) */
            .progress-bar::-webkit-slider-thumb {
                -webkit-appearance: none;
                appearance: none;
                margin-top: -3px;
                height: 14px;
                width: 14px;
                background: white;
                border-radius: 50%;
                border: none;
                box-shadow: 0 0 5px rgba(0, 0, 0, 0.3);
            }

            .progress-bar::-moz-range-thumb {
                height: 14px;
                width: 14px;
                background: white;
                border-radius: 50%;
                border: none;
            }

            /* Icons and Right Controls */
            .player-icons {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-left: 15px;
            }

            .player-icons span {
                font-size: 20px;
                cursor: pointer;
                line-height: 1;
            }

            /* Small visual effect for active/hover states on icons */
            .player-icons span:hover,
            .play-button:hover {
                opacity: 0.8;
            }

            /* Center all player controls */
            .sm2-bar-ui .sm2-main-controls {
                display: flex !important;
                justify-content: center !important;
                align-items: center !important;
                padding: 10px 20px !important;
            }

            /* Remove auto margin that pushes controls to right */
            .sm2-inline-controls {
                margin-left: 0 !important;
            }

            /* Stretch progress bar and song title section */
            .sm2-bar-ui .sm2-main-controls > div[style*="width:110px"] {
                flex: 1 1 auto !important;
                width: auto !important;
                max-width: 600px !important;
                min-width: 300px !important;
                margin-top: -8px !important;
            }

            /* Remove scrolling text animation on desktop */
            #scrolling-title {
                animation: none !important;
                transform: none !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }

            /* SM2 Bar UI Inline Elements */
            .sm2-bar-ui .sm2-inline-status {
                width: 100%;
                min-width: 100%;
                max-width: 100%;
            }

            .sm2-bar-ui .sm2-inline-element {
                width: 1%;
            }

            .sm2-bar-ui .sm2-inline-element {
                display: table-cell;
            }

            .sm2-bar-ui .sm2-inline-element {
                border-right: 0.075em dotted #666;
                border-right: 0.075em solid rgba(0, 0, 0, 0.1);
            }

            .sm2-bar-ui .sm2-inline-status {
                line-height: 100%;
                display: inline-block;
                min-width: 200px;
                max-width: 20em;
                padding-left: 0.75em;
                padding-right: 0.75em;
            }

            .sm2-bar-ui .sm2-inline-element, 
            .sm2-bar-ui .sm2-button-element .sm2-button-bd {
                min-width: 2.8em;
                min-height: 2.8em;
            }

            .sm2-bar-ui .sm2-inline-element, 
            .sm2-bar-ui .sm2-button-element .sm2-button-bd {
                position: relative;
            }

            .sm2-bar-ui .sm2-inline-element {
                position: relative;
                display: inline-block;
                vertical-align: middle;
                padding: 0px;
                overflow: hidden;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <!-- Custom Player Page Container -->
    <div class="custom-player-page-container">
        
        <div class="custom-player">
            
            <?php if (!empty($song['cover_art'])): ?>
            <!-- Blurred background image -->
            <div class="cover-bg-image" style="background-image: url('<?php echo htmlspecialchars($song['cover_art']); ?>');"></div>
            <?php endif; ?>
            
            <!-- Dark overlay -->
            <div class="dark-overlay"></div>
            
            <!-- Checkered texture overlay -->
            <div class="background-overlay"></div>
            
            <div class="player-content-wrapper">
                <!-- Social Icons at Top Right -->
                <div class="social-icons">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('http://localhost/music/song-details.php?id=' . $songId); ?>" target="_blank" class="social-icon facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('http://localhost/music/song-details.php?id=' . $songId); ?>&text=<?php echo urlencode($song['title'] . ' - ' . $song['artist']); ?>" target="_blank" class="social-icon twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="https://plus.google.com/share?url=<?php echo urlencode('http://localhost/music/song-details.php?id=' . $songId); ?>" target="_blank" class="social-icon googleplus">
                        <i class="fab fa-google-plus-g"></i>
                    </a>
                </div>

                <!-- Album Art and Title Row (Below Social Icons) -->
                <div class="art-title-row">
                    <!-- Album Art (Left) -->
                    <div class="album-art">
                        <?php if (!empty($song['cover_art'])): ?>
                            <img src="<?php echo htmlspecialchars($song['cover_art']); ?>" alt="<?php echo htmlspecialchars($song['artist']); ?>">
                        <?php else: ?>
                            <div style="width: 100%; padding-bottom: 100%; position: relative; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 40px; color: white;">
                                    <i class="fas fa-music"></i>
                </div>
                            </div>
                        <?php endif; ?>
            </div>

                    <!-- Song Title (Right of Album Art) -->
                    <h1 class="song-title">
                        <?php 
                        $artist = htmlspecialchars($song['artist']);
                        // Check if there are multiple artists (indicated by 'x', '&', 'feat', 'ft.', etc.)
                        $hasMultipleArtists = preg_match('/(\\sx\\s|\\s&\\s|feat\\.?|ft\\.)/i', $artist);
                        
                        echo htmlspecialchars($song['title']) . ' - ' . $artist;
                        
                        // Only show album if there are multiple artists
                        if ($hasMultipleArtists && !empty($song['album'])) {
                            echo ' x ' . htmlspecialchars($song['album']);
                        }
                        ?>
                    </h1>
                                </div>
            </div>
        </div>
        
        
        <!-- Keep existing player controls below custom player box -->
        <div style="position: relative;">
            <!-- Bottom Bar - Compact with Title + Progress + Controls -->
            <div class="sm2-bar-ui">
                <div class="sm2-main-controls">
                    <!-- Play -->
                    <button id="main-play-btn" class="sm2-inline-button play-pause" title="Play/Pause"><i class="fas fa-play"></i></button>
                    
                    <!-- Middle stack: title + thin progress -->
                    <div style="display:flex; flex-direction:column; flex:0 0 auto; width:110px;">
                        <div style="overflow:hidden; margin-bottom:2px;">
                            <div id="scrolling-title" style="color:#fff; font-weight:700; font-size:13px; text-shadow:0 1px 2px rgba(0,0,0,.4);">
                                <?php echo htmlspecialchars($song['title']).' - '.htmlspecialchars($song['artist']); ?>
                            </div>
                        </div>
                        <div id="bottom-progress-container" style="position:relative; height:4px; background:rgba(255,255,255,.3); border-radius:2px; cursor:pointer;">
                            <div id="bottom-progress-fill" style="position:relative; height:100%; width:0%; background:#fff; border-radius:2px; transition:width 0.1s;">
                                <div id="progress-handle" style="position:absolute; right:-6px; top:50%; transform:translateY(-50%); width:12px; height:12px; background:#fff; border-radius:50%; box-shadow:0 1px 3px rgba(0,0,0,.4);"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Controls: volume bars, prev, next, repeat -->
                    <div class="sm2-inline-controls">
                        <button id="volume-btn" class="sm2-inline-button" title="Volume">
                            <span class="vol-icon"><span class="b1"></span><span class="b2"></span><span class="b3"></span><span class="b4"></span></span>
                        </button>
                        <button id="prev-btn" class="sm2-inline-button" title="Previous"><i class="fas fa-backward-step"></i></button>
                        <button id="next-btn" class="sm2-inline-button" title="Next"><i class="fas fa-forward-step"></i></button>
                        <button id="repeat-btn" class="sm2-inline-button" title="Repeat">
                            <svg viewBox="0 0 25 25" style="width:18px;height:18px;fill:#fff;">
                                <path d="M21.25 7.75h-4.75v3.5h3.75v5.25h-15.5v-5.25h5.25v2.75l5-4.5-5-4.5v2.75h-6.25c-1.38 0-2.5 1.119-2.5 2.5v7.25c0 1.38 1.12 2.5 2.5 2.5h17.5c1.381 0 2.5-1.12 2.5-2.5v-7.25c0-1.381-1.119-2.5-2.5-2.5z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </div>

    <!-- Main Content Below Header -->
    <div class="main-content">
        <!-- Download Section -->
        <div class="download-section">
            <a href="api/download.php?id=<?php echo $song['id']; ?>" class="download-btn" id="download-btn">
                <i class="fas fa-download"></i> Download Song
            </a>
            <div class="song-stats">
                <span><?php echo number_format($song['plays'] ?? 0); ?></span> plays | <?php echo number_format($song['downloads'] ?? 0); ?> Downloads
            </div>
        </div>

        <!-- Sections: Artist Songs & You May Also Like -->
        <div class="sections">
            <!-- Artist Songs -->
            <?php if (!empty($relatedSongs)): ?>
            <div class="section">
                <h2 class="section-title"><?php echo htmlspecialchars($song['artist']); ?></h2>
                <ul class="song-list">
                    <?php foreach (array_slice($relatedSongs, 0, 8) as $relatedSong): ?>
                        <li class="song-list-item" onclick="window.location.href='song-details.php?id=<?php echo $relatedSong['id']; ?>'">
                            <div class="song-list-title"><?php echo htmlspecialchars($relatedSong['title']); ?> - <?php echo htmlspecialchars($relatedSong['artist']); ?></div>
                            <div class="song-list-stats">
                                <span style="color: #dc3545;"><?php echo number_format($relatedSong['plays'] ?? 0); ?></span> plays | <?php echo number_format($relatedSong['downloads'] ?? 0); ?> downloads
                        </div>
                        </li>
                <?php endforeach; ?>
                </ul>
                <?php if (count($relatedSongs) > 8): ?>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="artists.php?artist=<?php echo urlencode($song['artist']); ?>" style="color: #667eea; text-decoration: none;">More Audios →</a>
            </div>
                <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- You May Also Like -->
            <div class="section">
            <h2 class="section-title">You May Also Like</h2>
                <ul class="song-list">
                    <?php 
                    // Get random songs for "You May Also Like"
                    $allSongs = getSongs();
                    $mayAlsoLike = array_filter($allSongs, function($s) use ($song) {
                        return $s['id'] != $song['id'] && isset($s['artist']) && $s['artist'] != $song['artist'];
                    });
                    $mayAlsoLike = array_slice($mayAlsoLike, 0, 8);
                    ?>
                    <?php foreach ($mayAlsoLike as $similarSong): ?>
                        <li class="song-list-item" onclick="window.location.href='song-details.php?id=<?php echo $similarSong['id']; ?>'">
                            <div class="song-list-title"><?php echo htmlspecialchars($similarSong['title']); ?> - <?php echo htmlspecialchars($similarSong['artist']); ?></div>
                            <div class="song-list-stats">
                                <span style="color: #dc3545;"><?php echo number_format($similarSong['plays'] ?? 0); ?></span> plays | <?php echo number_format($similarSong['downloads'] ?? 0); ?> downloads
                        </div>
                        </li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Use HTML5 Audio directly for now -->
    <audio id="song-player" preload="metadata"></audio>

    <script>
        console.log('Initializing simple audio player');
        
        let audio = null;
        let isPlaying = false;
        const playBtn = document.getElementById('main-play-btn');
        // Use bottom progress bar exclusively
        const progressBar = document.getElementById('bottom-progress-fill');
        let progressInterval = null;

        // Initialize audio player
        document.addEventListener('DOMContentLoaded', function() {
            // Debug song data
            <?php 
                $audioFile = !empty($song['audio_file']) ? $song['audio_file'] : (!empty($song['file_path']) ? $song['file_path'] : 'uploads/audio/demo.mp3');
                
                // Check if file exists
                $fileExists = file_exists($audioFile);
                
                echo "console.log('Song data:', " . json_encode($song) . ");";
                echo "console.log('Audio file path:', '" . htmlspecialchars($audioFile) . "');";
                echo "console.log('File exists:', " . ($fileExists ? 'true' : 'false') . ");";
            ?>
            
            const audioUrl = '<?php echo htmlspecialchars($audioFile); ?>';
            
            console.log('Loading audio from:', audioUrl);
            
            // Initialize HTML5 audio
            audio = document.getElementById('song-player');
            audio.src = audioUrl;
            
            // Set up event listeners
            audio.addEventListener('loadedmetadata', function() {
                console.log('Audio metadata loaded');
                console.log('Duration:', audio.duration);
                updateTotalTime();
            });
            
            audio.addEventListener('play', function() {
                console.log('Audio playing');
                isPlaying = true;
                updatePlayButton();
                startProgressUpdate();
            });
            
            audio.addEventListener('pause', function() {
                console.log('Audio paused');
                isPlaying = false;
                updatePlayButton();
                stopProgressUpdate();
            });
            
            audio.addEventListener('ended', function() {
                console.log('Audio ended');
                isPlaying = false;
                updatePlayButton();
                stopProgressUpdate();
                progressBar.style.width = '0%';
                document.getElementById('current-time').textContent = '0:00';
            });
            
            audio.addEventListener('error', function(e) {
                console.error('Audio error:', e);
                console.error('Failed to load:', audio.src);
                alert('Failed to load audio file. Please check the file path.');
            });
            
            console.log('Audio player initialized');
        });
        
            function updatePlayButton() {
                playBtn.innerHTML = isPlaying ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play"></i>';
            }

        function startProgressUpdate() {
            if (progressInterval) clearInterval(progressInterval);
            
            progressInterval = setInterval(function() {
                if (audio && !isNaN(audio.duration) && audio.duration > 0) {
                    const position = audio.currentTime;
                    const duration = audio.duration;
                    const percent = (position / duration) * 100;
                    if (progressBar) {
                        progressBar.style.width = percent + '%';
                    }
                    document.getElementById('current-time').textContent = formatTime(position * 1000);
                }
            }, 100);
        }
        
        function stopProgressUpdate() {
            if (progressInterval) {
                clearInterval(progressInterval);
                progressInterval = null;
            }
        }
        
        function updateTotalTime() {
            if (audio && !isNaN(audio.duration) && audio.duration > 0) {
                document.getElementById('total-time').textContent = formatTime(audio.duration * 1000);
            }
        }
        
        function formatTime(milliseconds) {
            const totalSeconds = Math.floor(milliseconds / 1000);
            const mins = Math.floor(totalSeconds / 60);
            const secs = totalSeconds % 60;
            return `${mins}:${secs.toString().padStart(2, '0')}`;
                        }
        
        // Play/Pause button (both buttons)
        const handlePlayPause = function() {
            if (!audio) {
                console.error('No audio element available');
                return;
            }
            
            console.log('Play button clicked, current state:', isPlaying);
            
            if (isPlaying) {
                audio.pause();
            } else {
                // Track play count on first play
                if (!audio.hasTrackedPlay) {
                    fetch('api/update-play-count.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ song_id: '<?php echo $song['id']; ?>' })
                    }).catch(err => console.log('Play count update failed:', err));
                    audio.hasTrackedPlay = true;
            }
                
                audio.play().catch(err => {
                    console.error('Play failed:', err);
                    alert('Failed to play audio. Please try again.');
                });
            }
        };
        
        playBtn.addEventListener('click', handlePlayPause);
        
        // Progress bar clicking
        document.querySelector('#bottom-progress-container').addEventListener('click', function(e) {
            if (!audio) return;
            
            const rect = this.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            const position = percent * audio.duration;
            
            audio.currentTime = position;
        });

        // Repeat button
        const repeatBtn = document.getElementById('repeat-btn');
        if (repeatBtn) {
            repeatBtn.addEventListener('click', function() {
                if (!audio) return;
                
                this.classList.toggle('active');
                audio.loop = !audio.loop;
                
                // Visual feedback
                if (audio.loop) {
                    this.style.background = 'rgba(255,255,255,0.2)';
                    const icon = this.querySelector('i');
                    if (icon) { icon.style.color = '#4CAF50'; }
                } else {
                    this.style.background = 'transparent';
                    const icon = this.querySelector('i');
                    if (icon) { icon.style.color = '#fff'; }
            }
        });
        }

        // Prev/Next buttons
        document.getElementById('prev-btn').addEventListener('click', function() {
            if (audio) {
                audio.currentTime = Math.max(0, audio.currentTime - 10);
            }
        });
        
        document.getElementById('next-btn').addEventListener('click', function() {
            if (audio) {
                audio.currentTime = Math.min(audio.duration, audio.currentTime + 10);
            }
        });

        // Volume toggle
        const volumeBtn = document.getElementById('volume-btn');
        if (volumeBtn) {
            volumeBtn.addEventListener('click', function() {
                if (!audio) return;
                if (audio.muted || audio.volume === 0) {
                    audio.muted = false;
                    audio.volume = 1;
                    this.classList.remove('muted');
                } else {
                    audio.muted = true;
                    this.classList.add('muted');
                }
            });
        }
        
        // Download button - update count after download
        document.getElementById('download-btn').addEventListener('click', function() {
            // Track download
            setTimeout(function() {
                // Reload page after download starts to update count
                location.reload();
            }, 1000);
        });
    </script>

    <!-- SoundManager2 core and Bar UI scripts (non-invasive include) -->
    <script>
        window.SM2_DEFER_INIT = true; // don't auto-init until after our page is ready
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/soundmanager2/2.97a.20170601/script/soundmanager2-jsmin.js"></script>
    <script>
        if (window.soundManager) {
            soundManager.setup({
                url: '.',
                preferFlash: false,
                useHTML5Audio: true,
                debugMode: false
            });
        }
    </script>
    <script src="https://www.schillmania.com/projects/soundmanager2/demo/bar-ui/script/bar-ui.js"></script>
    <script>
        // Optional: expose players if SM2 Bar UI finds any compatible markup
        if (window.sm2BarPlayers) {
            console.log('SM2 Bar UI players:', sm2BarPlayers.length);
        }
    </script>
</body>
</html>

