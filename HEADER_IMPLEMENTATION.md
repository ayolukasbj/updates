# Header Menu Implementation Guide

## Overview
A responsive header navigation menu has been created for the Music Streaming Platform that works on all pages.

## File Location
- **Header Component**: `includes/header.php`

## Features
✅ Sticky header that stays at the top while scrolling
✅ Responsive design (mobile hamburger menu)
✅ User authentication status display
✅ Active page highlighting
✅ Navigation links to all major pages
✅ Mobile-friendly dropdown menu

## Navigation Links
- Home
- Browse
- Songs
- Artists
- Top 100
- News
- Playlists (logged-in users only)
- Favorites (logged-in users only)

## How to Add Header to Any Page

### Step 1: Include the header file
Add this line right after the `<body>` tag:

```php
<body>
    <?php include 'includes/header.php'; ?>
    
    <!-- Your page content here -->
</body>
```

### Step 2: Ensure Required Dependencies
Make sure your page has Font Awesome icons loaded in the `<head>`:

```html
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
```

## Example Implementation

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Page</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="content">
        <!-- Your page content -->
    </div>
</body>
</html>
```

## Responsive Behavior

### Desktop (> 768px)
- Horizontal navigation menu
- Full user information displayed
- All buttons visible

### Mobile (≤ 768px)
- Hamburger menu icon
- Slide-in navigation panel
- Compact user avatar
- Touch-friendly buttons

## Customization

### Colors
- Primary: `#1e4d72` (header background)
- Accent: `#64b5f6` (buttons and active states)
- Text: `#fff` (white)

### To change colors, edit in `includes/header.php`:
```css
.main-header {
    background: #1e4d72; /* Change this */
}

.btn-primary {
    background: #64b5f6; /* Change this */
}
```

## Pages Already Implemented
✅ song-details.php

## To Add Header to Other Pages
Simply copy this line to any PHP page after the `<body>` tag:
```php
<?php include 'includes/header.php'; ?>
```

## Notes
- Header automatically detects logged-in users via `$_SESSION['user_id']`
- Active page highlighting is automatic based on current URL
- Mobile menu closes automatically when clicking outside

