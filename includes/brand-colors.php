<?php
/**
 * Brand Colors from Logo
 * Platform brand color palette:
 * - Red: Heart and microphone (#e74c3c / #c0392b)
 * - Blue: Book and text (#3498db / #2980b9)
 * - Yellow: Flame (#f39c12 / #e67e22)
 * - Dark Blue/Navy: Text (#1e4d72 / #2c5f8d)
 * - White: Background and accents
 */

// Brand color palette
$brand_colors = [
    'primary_red' => '#e74c3c',
    'primary_red_dark' => '#c0392b',
    'primary_blue' => '#3498db',
    'primary_blue_dark' => '#2980b9',
    'primary_yellow' => '#f39c12',
    'primary_yellow_dark' => '#e67e22',
    'primary_navy' => '#1e4d72',
    'primary_navy_dark' => '#2c5f8d',
    'white' => '#ffffff',
    'text_primary' => '#2c3e50',
    'text_secondary' => '#7f8c8d',
];

function getBrandColor($color_name, $default = '#333') {
    global $brand_colors;
    return $brand_colors[$color_name] ?? $default;
}

function renderBrandCSS() {
    global $brand_colors;
    echo '<style id="brand-colors">';
    echo ':root {';
    foreach ($brand_colors as $name => $value) {
        $css_var = '--brand-' . str_replace('_', '-', $name);
        echo "  {$css_var}: {$value};";
    }
    echo '}';
    
    // Apply brand colors globally
    echo '
    /* Brand color applications */
    .btn-primary, button[type="submit"].btn-primary {
        background: var(--brand-primary-blue, #3498db) !important;
        border-color: var(--brand-primary-blue, #3498db) !important;
    }
    .btn-primary:hover {
        background: var(--brand-primary-blue-dark, #2980b9) !important;
    }
    .btn-danger, .btn-delete {
        background: var(--brand-primary-red, #e74c3c) !important;
        border-color: var(--brand-primary-red, #e74c3c) !important;
    }
    .btn-danger:hover {
        background: var(--brand-primary-red-dark, #c0392b) !important;
    }
    .btn-warning {
        background: var(--brand-primary-yellow, #f39c12) !important;
        border-color: var(--brand-primary-yellow, #f39c12) !important;
    }
    .btn-warning:hover {
        background: var(--brand-primary-yellow-dark, #e67e22) !important;
    }
    a {
        color: var(--brand-primary-blue, #3498db);
    }
    a:hover {
        color: var(--brand-primary-blue-dark, #2980b9);
    }
    .section-title {
        color: var(--brand-primary-navy, #1e4d72);
    }
    ';
    echo '</style>';
}

