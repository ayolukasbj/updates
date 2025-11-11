<?php
// test-zindex.php - Test z-index fixes
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Z-Index Test - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Fix for fixed navbar overlapping content */
        .navbar.fixed-top {
            z-index: 1030 !important;
        }
        
        .main-content {
            position: relative;
            z-index: 1;
            padding-top: 80px !important;
        }
        
        /* Fix for any forms or edit screens */
        .form-container,
        .edit-screen,
        form {
            position: relative;
            z-index: 10;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }
        
        /* Ensure all content is properly positioned */
        .container-fluid,
        .container {
            position: relative;
            z-index: 1;
        }
        
        .test-box {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            padding: 20px;
            margin: 10px 0;
            border-radius: 8px;
        }
        
        .z-index-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-music"></i> <?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Home</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <h1>Z-Index Test Page</h1>
            
            <div class="z-index-info">
                <h3>Z-Index Values Applied:</h3>
                <ul>
                    <li><strong>Navbar:</strong> z-index: 1030 (top layer)</li>
                    <li><strong>Main Content:</strong> z-index: 1 (base layer)</li>
                    <li><strong>Forms/Edit Screens:</strong> z-index: 10 (above content)</li>
                    <li><strong>Container:</strong> z-index: 1 (base layer)</li>
                </ul>
            </div>
            
            <div class="test-box">
                <h3>Test Form (Should be above content)</h3>
                <form class="form-container">
                    <div class="form-group">
                        <label for="test-input">Test Input:</label>
                        <input type="text" class="form-control" id="test-input" placeholder="This should be visible">
                    </div>
                    <button type="submit" class="btn btn-primary">Test Button</button>
                </form>
            </div>
            
            <div class="test-box">
                <h3>Edit Screen Test</h3>
                <div class="edit-screen">
                    <h4>Edit Form</h4>
                    <p>This edit screen should be properly positioned above other content.</p>
                    <div class="form-group">
                        <label for="edit-input">Edit Input:</label>
                        <input type="text" class="form-control" id="edit-input" placeholder="This should be visible">
                    </div>
                </div>
            </div>
            
            <div class="test-box">
                <h3>Regular Content</h3>
                <p>This is regular content that should be below the navbar but above the background.</p>
                <p>If you can see this text clearly without the navbar overlapping it, the fix is working!</p>
            </div>
            
            <div class="test-box">
                <h3>Debug Information</h3>
                <p><strong>Current Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                <p><strong>Page:</strong> Z-Index Test</p>
                <p><strong>Status:</strong> If you can see all content clearly, the z-index fixes are working!</p>
            </div>
        </div>
    </div>
</body>
</html>
