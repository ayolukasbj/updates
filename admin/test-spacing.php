<!DOCTYPE html>
<html style="margin:0;padding:0;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Spacing Diagnostic</title>
    <style>
        * { margin: 0 !important; padding: 0 !important; box-sizing: border-box; }
        html { height: 100%; background: red; }
        body { min-height: 100vh; background: green; }
        .test-bar { 
            background: yellow; 
            color: black; 
            padding: 20px !important; 
            text-align: center;
            font-family: Arial;
            font-size: 18px;
            font-weight: bold;
        }
        .info {
            background: white;
            padding: 20px !important;
            margin: 10px !important;
        }
        .green { color: green; }
        .red { color: red; }
    </style>
</head>
<body>
    <div class="test-bar">
        ⚠️ SPACING TEST
    </div>
    
    <div class="info">
        <h2>📱 What You Should See:</h2>
        <ul style="margin-left: 20px !important; padding-left: 20px !important; margin-top: 10px !important;">
            <li style="margin: 5px 0 !important;"><span class="green">✅ GOOD:</span> Yellow bar touching the TOP edge</li>
            <li style="margin: 5px 0 !important;"><span class="red">❌ BAD:</span> Red or white space above yellow bar</li>
        </ul>
        
        <h2 style="margin-top: 20px !important;">🔍 Diagnosis:</h2>
        <p><strong>Background Colors:</strong></p>
        <ul style="margin-left: 20px !important; padding-left: 20px !important; margin-top: 10px !important;">
            <li style="margin: 5px 0 !important;">🔴 Red = HTML element</li>
            <li style="margin: 5px 0 !important;">🟢 Green = BODY element</li>
            <li style="margin: 5px 0 !important;">🟡 Yellow = Content div</li>
        </ul>
        
        <p style="margin-top: 15px !important;">
            <strong>If you see:</strong><br>
            • Only Yellow = Perfect! No spacing<br>
            • Green above Yellow = Body has padding<br>
            • Red above Green = HTML has padding<br>
            • White above Red = Browser default spacing
        </p>
        
        <div style="margin-top: 20px !important; padding: 15px !important; background: #f0f0f0; border-radius: 5px;">
            <h3>📊 Technical Info:</h3>
            <p><strong>Window Height:</strong> <span id="height"></span>px</p>
            <p><strong>Window Width:</strong> <span id="width"></span>px</p>
            <p><strong>Scroll Y:</strong> <span id="scroll"></span>px</p>
            <p><strong>Body Offset Top:</strong> <span id="bodyTop"></span>px</p>
        </div>
        
        <div style="margin-top: 20px !important; text-align: center;">
            <a href="index.php" style="display: inline-block; background: #667eea; color: white; padding: 15px 30px !important; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Go to Dashboard
            </a>
        </div>
    </div>
    
    <script>
        function updateInfo() {
            document.getElementById('height').textContent = window.innerHeight;
            document.getElementById('width').textContent = window.innerWidth;
            document.getElementById('scroll').textContent = window.scrollY;
            document.getElementById('bodyTop').textContent = document.body.getBoundingClientRect().top;
        }
        updateInfo();
        window.addEventListener('scroll', updateInfo);
        window.addEventListener('resize', updateInfo);
    </script>
</body>
</html>

