# Live Search Feature Documentation

## Overview
A real-time search feature has been added to the header that provides instant suggestions as users type.

## Features
✅ **Live search** - Results appear as you type
✅ **Debounced** - Waits 300ms after typing stops to search
✅ **Song search** - Searches by title, artist, or album
✅ **Artist search** - Shows artists with song counts
✅ **Image previews** - Shows album artwork for songs
✅ **Responsive** - Works on mobile and desktop
✅ **Loading indicator** - Shows spinner while searching
✅ **Click-to-navigate** - Click any result to visit that page

## Files Modified/Created

### 1. `includes/header.php`
- Added search bar HTML
- Added search results dropdown CSS
- Added JavaScript for live search
- Responsive design for mobile

### 2. `api/search.php` (NEW)
- Backend API endpoint
- Searches songs by title, artist, album
- Returns JSON with matched songs and artists
- Limits results: 5 songs, 3 artists

## How It Works

### User Experience:
1. User types in search box (minimum 2 characters)
2. Loading spinner appears
3. After 300ms, search executes
4. Results dropdown shows:
   - Songs with cover art, title, and artist
   - Artists with song count
5. Click any result to navigate to that page
6. Click outside dropdown to close

### Search Logic:
- **Songs**: Searches title, artist, and album fields
- **Artists**: Groups songs by artist name
- **Case-insensitive**: Matches regardless of capitalization
- **Partial matching**: "love" matches "Lovely", "Love Song", etc.

## Customization

### Change number of results:
Edit `api/search.php`:
```php
if (count($matchedSongs) < 5) { // Change 5 to desired number
if (count($matchedArtists) < 3) { // Change 3 to desired number
```

### Change search delay:
Edit `includes/header.php`:
```javascript
searchTimeout = setTimeout(() => {
    // search code
}, 300); // Change 300 to desired milliseconds
```

### Change minimum characters:
Edit `includes/header.php`:
```javascript
if (query.length < 2) { // Change 2 to desired minimum
```

## Search Result Types

### Song Results:
- Album artwork (50x50px)
- Song title (bold)
- Artist name (gray)
- "Song" badge (blue)
- Links to: `song-details.php?id={id}`

### Artist Results:
- Music icon placeholder
- Artist name (bold)
- Song count (e.g., "5 songs")
- "Artist" badge (blue)
- Links to: `artist-profile.php?name={name}`

## Mobile Behavior
- Search bar appears below logo on mobile
- Full width on mobile screens
- Same dropdown functionality
- Touch-friendly result items

## Performance
- **Debouncing**: Prevents excessive API calls while typing
- **Result limits**: Only returns top 5 songs and 3 artists
- **Lightweight**: Returns minimal data (id, title, artist, cover)

## Example Searches
- Type "love" → Shows songs with "love" in title
- Type artist name → Shows artist + their songs
- Type partial word → Matches anywhere in title/artist/album

## Future Enhancements
- [ ] Add album search
- [ ] Add playlist search
- [ ] Search history
- [ ] Keyboard navigation (arrow keys)
- [ ] Search analytics
- [ ] Recent searches

