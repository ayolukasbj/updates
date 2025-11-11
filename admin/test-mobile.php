<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mobile Spacing Test</title>
    <style>
        /* Show exact spacing */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            background: #f0f0f0;
        }
        
        .test-box {
            background: #ff0000;
            color: white;
            padding: 20px;
            text-align: center;
            font-family: Arial, sans-serif;
        }
        
        .info {
            background: white;
            padding: 15px;
            margin: 10px;
            border-radius: 5px;
        }
        
        .green-bar {
            background: #00ff00;
            height: 5px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="green-bar"></div>
    <div class="test-box">
        <h1>📱 Mobile Spacing Test</h1>
        <p>If you see a GREEN LINE at the very top of this page (touching the edge), spacing is fixed!</p>
        <p>If there's WHITE SPACE above the green line, spacing still has issues.</p>
    </div>
    
    <div class="info">
        <h3>What to check:</h3>
        <ul style="text-align: left; margin-left: 20px;">
            <li>Is the green bar touching the TOP edge of your screen?</li>
            <li>Is there ANY white space above it?</li>
            <li>Take a screenshot if there's still spacing</li>
        </ul>
    </div>
    
    <div class="info">
        <p><strong>Device Info:</strong></p>
        <p>Screen Width: <span id="width"></span>px</p>
        <p>Screen Height: <span id="height"></span>px</p>
        <p>Scroll Position: <span id="scroll"></span>px</p>
    </div>
    
    <script>
        document.getElementById('width').textContent = window.innerWidth;
        document.getElementById('height').textContent = window.innerHeight;
        document.getElementById('scroll').textContent = window.scrollY;
        
        window.addEventListener('scroll', function() {
            document.getElementById('scroll').textContent = window.scrollY;
        });
    </script>
    
    <div class="info">
        <a href="index.php" style="display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Go to Admin Dashboard</a>
    </div>
</body>
</html>

