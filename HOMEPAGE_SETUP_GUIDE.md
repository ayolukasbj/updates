# Homepage Setup Guide

## Overview
The homepage has been restructured to match **jnews.io/default/** layout. This guide will help you understand how to manage and edit the homepage sections.

## How to Add Sample News

First, create sample news articles by running:
```
http://localhost/music/create-sample-news.php
```

This will create 16 sample news articles in various categories (Politics, Business, Tech, Entertainment, Lifestyle, etc.) to make your homepage look complete and professional.

## Homepage Layout Structure (jnews.io/default style)

The homepage now follows this structure:

### 1. **NEWSFLASH Carousel** (Top Section)
- Displays the 5 most recent news articles
- Auto-rotates every 5 seconds
- Manual navigation with Previous/Next buttons
- Clickable indicators at the bottom
- **Location in code**: Lines 614-655 in `index.php`

### 2. **Main Content Grid** (2 columns + sidebar)
- **Left Column (2/3 width)**:
  - **Politics Section**: 3-column grid of politics news
  - **Business Section**: With tabs (All, News, Tech, Startup, World)
  - **Tech Section**: With tabs (All, Apps, Gadget, Mobile, Startup)
  
- **Right Sidebar (1/3 width)**:
  - **Featured Stories**: 4 featured articles
  - **Popular Stories**: Top 5 most viewed articles

### 3. **Entertainment Section**
- 3-column grid displaying entertainment, movie, music, and fashion news
- Shows up to 6 articles

### 4. **Lifestyle Section**
- 4-column grid displaying lifestyle, travel, food, and health news
- Shows up to 4 articles

### 5. **Latest Post Section**
- 3-column grid showing the 6 most recent articles from all categories

### 6. **Trending Songs Section**
- Numbered list of top 8 trending songs
- Displays plays and downloads count

### 7. **Music Chart & Songs Newly Added**
- Side-by-side layout
- Left: Top 4 chart songs with trend indicators
- Right: 5 newest songs with stats

### 8. **Artistes Section**
- 6 columns on desktop (12 artists = 2 rows)
- 2 columns on mobile (12 artists = 6 rows)
- Shows rank, total songs, and total plays

## How to Edit Homepage Sections

### To Add/Edit News Articles:
1. Go to Admin Panel → News Management
2. Add new articles or edit existing ones
3. Set category (Politics, Business, Tech, Entertainment, etc.)
4. Mark as "Featured" to appear in Featured Stories sidebar
5. News will automatically appear in relevant sections based on category

### To Control Which News Appears:
The homepage automatically pulls news based on these queries:

- **NEWSFLASH Carousel**: Most recent 5 articles
- **Politics Section**: `WHERE category = 'Politics' LIMIT 5`
- **Business Section**: `WHERE category = 'Business' LIMIT 6`
- **Tech Section**: `WHERE category = 'Tech' LIMIT 6`
- **Entertainment Section**: `WHERE category IN ('Entertainment', 'Movie', 'Music', 'Fashion') LIMIT 6`
- **Lifestyle Section**: `WHERE category IN ('Lifestyle', 'Travel', 'Food', 'Health') LIMIT 6`
- **Featured Stories**: `WHERE featured = 1 LIMIT 4`
- **Popular Stories**: `ORDER BY views DESC LIMIT 10`

### To Customize Sections:

#### Change Number of Articles Displayed:
Edit the `LIMIT` values in the PHP queries (around lines 54-64 in `index.php`):

```php
$politics_news = $conn->query("SELECT * FROM news WHERE is_published = 1 AND category = 'Politics' ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
// Change LIMIT 5 to your desired number
```

#### Change Grid Columns:
Edit the `grid-template-columns` CSS:

```php
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
// Change repeat(3, 1fr) to repeat(4, 1fr) for 4 columns, etc.
```

#### Add New Section:
1. Query news in the PHP section (around line 54):
```php
$your_section_news = $conn->query("SELECT * FROM news WHERE is_published = 1 AND category = 'YourCategory' ORDER BY created_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
```

2. Add HTML section in the body (around line 820+):
```php
<!-- Your Section -->
<?php if (!empty($your_section_news)): ?>
<div style="margin: 40px 0;">
    <h2>Your Section Title</h2>
    <!-- Display news here -->
</div>
<?php endif; ?>
```

### To Hide/Show Sections:

Simply comment out or add conditional checks:

```php
<?php if (!empty($entertainment_news) && false): // Set to true to show ?>
    <!-- Entertainment Section -->
<?php endif; ?>
```

## Responsive Breakpoints

- **Desktop (>1024px)**: Full layout with all columns
- **Tablet (768px-1024px)**: Reduced columns, sidebar stacks
- **Mobile (≤768px)**: Single column layout

## File Locations

- **Homepage**: `index.php`
- **Sample News Creator**: `create-sample-news.php`
- **News Management**: Admin Panel → News

## Quick Start

1. Run `create-sample-news.php` to populate sample news
2. Visit `index.php` to see the new layout
3. Edit news articles in Admin Panel
4. Customize sections as needed using the guide above

## Tips

- **Featured Articles**: Mark articles as "Featured" to appear in the Featured Stories sidebar
- **Views Count**: Articles with more views appear in "Popular Stories"
- **Categories**: Use consistent category names (Politics, Business, Tech, Entertainment, Lifestyle, etc.)
- **Images**: Add images to news articles for better visual appeal (currently shows gradient placeholders if no image)

## Need Help?

- All sections are clearly commented in `index.php`
- Each section has conditional checks `<?php if (!empty($news)): ?>`
- Sections gracefully hide if no news is available
- Responsive design automatically adjusts for mobile/tablet

