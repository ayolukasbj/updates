<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Migrate</title>
    <style>
        body { font-family: Arial; margin: 0; padding: 20px; background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; }
        .container { max-width: 600px; margin: 0 auto; }
        .card { background: white; border-radius: 10px; padding: 30px; margin: 20px 0; box-shadow: 0 10px 30px rgba(0,0,0,0.3); text-align: center; }
        h1 { color: #333; font-size: 28px; margin-bottom: 10px; }
        p { color: #666; line-height: 1.6; }
        .btn { display: inline-block; background: #28a745; color: white; padding: 20px 40px; border-radius: 5px; text-decoration: none; font-size: 18px; font-weight: bold; margin: 20px 10px; transition: all 0.3s; }
        .btn:hover { background: #218838; transform: scale(1.05); }
        .btn-alt { background: #667eea; }
        .btn-alt:hover { background: #5568d3; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107; }
        .issue { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>🎵 Song Migration</h1>
            <p>The old migration page is cached by your browser.</p>
            <div class="issue">
                <strong>❌ Current Issue:</strong><br>
                Getting "Column 'created_at' not found" error
            </div>
            <div class="warning">
                <strong>✅ Solution:</strong><br>
                Use this BRAND NEW migration script below
            </div>
            
            <a href="migrate.php?t=<?php echo time(); ?>" class="btn">
                🚀 Start Fresh Migration
            </a>
            
            <p style="margin-top: 30px; font-size: 14px; color: #999;">
                This will migrate all your songs without errors
            </p>
        </div>

        <div class="card">
            <h2 style="color: #667eea; font-size: 20px;">Alternative Options</h2>
            <a href="migrate-songs-new.php" class="btn btn-alt">Try Option 2</a>
            <a href="migrate-now.php" class="btn btn-alt">Try Option 3</a>
        </div>

        <div class="card" style="background: #f8f9fa;">
            <p style="font-size: 14px; color: #666;">
                After migration, go to: <strong>Songs Management</strong><br>
                All edit/delete buttons will be active!
            </p>
            <a href="index.php" style="color: #667eea; text-decoration: none;">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>

