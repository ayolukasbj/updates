<?php
// index-debug.php - Debug version with error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Music Streaming Platform</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .debug { background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Debug Homepage</h1>
    
    <div class="debug">
        <strong>Config Status:</strong> <?php echo defined('SITE_NAME') ? 'Loaded' : 'Failed'; ?><br>
        <strong>SITE_NAME:</strong> <?php echo SITE_NAME; ?><br>
    </div>
    
    <div class="debug">
        <strong>Testing AJAX:</strong>
        <button onclick="testAjax()">Test AJAX</button>
        <div id="ajax-result"></div>
    </div>
    
    <div class="debug">
        <strong>Main Content Area:</strong>
        <div id="main-content" style="border: 1px solid #ccc; padding: 20px; min-height: 200px;">
            Content will load here...
        </div>
    </div>

    <script>
        function testAjax() {
            fetch('ajax/test.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('ajax-result').innerHTML = 'AJAX Response: ' + data;
                })
                .catch(error => {
                    document.getElementById('ajax-result').innerHTML = 'AJAX Error: ' + error;
                });
        }
        
        // Test AJAX navigation
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, testing AJAX navigation...');
            
            fetch('ajax/index.php')
                .then(response => {
                    console.log('AJAX response status:', response.status);
                    return response.text();
                })
                .then(html => {
                    console.log('AJAX response length:', html.length);
                    document.getElementById('main-content').innerHTML = html;
                })
                .catch(error => {
                    console.error('AJAX error:', error);
                    document.getElementById('main-content').innerHTML = 'Error: ' + error.message;
                });
        });
    </script>
</body>
</html>
