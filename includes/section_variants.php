<?php
/**
 * Central registry of per-section layout variants. Adding a new
 * variant-aware section type (Staff, Gallery, etc. later) means adding
 * one entry here plus the actual rendering branches in site.php -
 * nothing else needs a matching hand-edit anymore.
 *
 * Each section's config:
 *   'photo_fields' => which content_json keys count as "photos" when
 *                     checking a variant's photo requirement
 *   'options'      => variant_key => [label, icon (Material Symbols
 *                     ligature name), photos_needed]
 *   'default'      => variant to use when none has been explicitly set
 *   'has_size'     => whether this section type also gets the
 *                     full/half/auto height control
 */
function get_section_variant_registry(): array {
    return [
        'hero' => [
            'photo_fields' => ['hero_photo', 'hero_photo_2', 'hero_photo_3'],
            'default' => 'split',
            'has_size' => true,
            'options' => [
                'text_only' => ['label' => 'Text only', 'icon' => 'notes', 'photos_needed' => 0],
                'text_cta' => ['label' => 'Text with call to action', 'icon' => 'ads_click', 'photos_needed' => 0],
                'split' => ['label' => 'Text left, photo right', 'icon' => 'view_agenda', 'photos_needed' => 1],
                'split_cta' => ['label' => 'Text, call to action & photo', 'icon' => 'view_sidebar', 'photos_needed' => 1],
                'carousel' => ['label' => 'Text over rotating photos', 'icon' => 'view_carousel', 'photos_needed' => 2],
                'background_fixed' => ['label' => 'Fixed background photo', 'icon' => 'panorama', 'photos_needed' => 1],
            ],
        ],
        'about' => [
            'photo_fields' => ['photo', 'photo_2', 'photo_3'],
            'default' => 'photo_right',
            'has_size' => false,
            'options' => [
                'text_only' => ['label' => 'Text only', 'icon' => 'notes', 'photos_needed' => 0],
                'photo_right' => ['label' => 'Text left, photo right', 'icon' => 'view_agenda', 'photos_needed' => 1],
                'photo_left' => ['label' => 'Text right, photo left', 'icon' => 'flip', 'photos_needed' => 1],
                'carousel_left' => ['label' => 'Text right, rotating photos left', 'icon' => 'view_carousel', 'photos_needed' => 2],
                'list' => ['label' => 'Bullet list', 'icon' => 'checklist', 'photos_needed' => 0],
                'timeline' => ['label' => 'Timeline', 'icon' => 'timeline', 'photos_needed' => 0],
                'quote' => ['label' => 'Message from the Head Teacher', 'icon' => 'format_quote', 'photos_needed' => 1],
                'stats_inline' => ['label' => 'Text with inline stats', 'icon' => 'bar_chart', 'photos_needed' => 1],
            ],
        ],
        'staff' => [
            'photo_fields' => [], // staff photos aren't a variant *requirement* - people can be listed with or without photos regardless of layout
            'default' => 'grid',
            'has_size' => false,
            'options' => [
                'grid' => ['label' => 'Grid', 'icon' => 'grid_view', 'photos_needed' => 0],
                'carousel' => ['label' => 'Horizontal scroll', 'icon' => 'view_carousel', 'photos_needed' => 0],
                'list_bio' => ['label' => 'List with bio', 'icon' => 'view_list', 'photos_needed' => 0],
                'org_chart' => ['label' => 'Org chart', 'icon' => 'account_tree', 'photos_needed' => 0],
                'minimal' => ['label' => 'Minimal (text only)', 'icon' => 'format_list_bulleted', 'photos_needed' => 0],
                'grouped' => ['label' => 'Grouped by department', 'icon' => 'category', 'photos_needed' => 0],
            ],
        ],
        'testimonials' => [
            'photo_fields' => ['photo_1', 'photo_2', 'photo_3', 'photo_4', 'photo_5', 'photo_6', 'photo_7', 'photo_8', 'photo_9', 'photo_10'],
            'default' => 'cards',
            'has_size' => false,
            'options' => [
                'cards' => ['label' => 'Cards', 'icon' => 'grid_view', 'photos_needed' => 0],
                'slider' => ['label' => 'Auto-rotating slider', 'icon' => 'view_carousel', 'photos_needed' => 0],
                'single' => ['label' => 'Single large quote', 'icon' => 'format_quote', 'photos_needed' => 0],
                'wall' => ['label' => 'Quote wall', 'icon' => 'view_module', 'photos_needed' => 0],
                'rating' => ['label' => 'Review-style with rating', 'icon' => 'star', 'photos_needed' => 0],
                'photo_forward' => ['label' => 'Photo-forward', 'icon' => 'account_circle', 'photos_needed' => 1],
            ],
        ],
        'gallery' => [
            'photo_fields' => ['photo_1', 'photo_2', 'photo_3', 'photo_4', 'photo_5', 'photo_6', 'photo_7', 'photo_8', 'photo_9', 'photo_10'],
            'default' => 'grid',
            'has_size' => false,
            'options' => [
                'grid' => ['label' => 'Grid', 'icon' => 'grid_view', 'photos_needed' => 1],
                'masonry' => ['label' => 'Masonry', 'icon' => 'view_quilt', 'photos_needed' => 1],
                'lightbox' => ['label' => 'Lightbox carousel', 'icon' => 'view_carousel', 'photos_needed' => 1],
                'before_after' => ['label' => 'Before / After slider', 'icon' => 'compare', 'photos_needed' => 2],
                'slideshow' => ['label' => 'Full-bleed slideshow', 'icon' => 'slideshow', 'photos_needed' => 1],
                'tabs' => ['label' => 'Categorized tabs', 'icon' => 'tab', 'photos_needed' => 1],
            ],
        ],
    ];
}

/**
 * How many of a section's designated photo fields are actually filled in,
 * given its content_json (already json_decode'd to an array).
 */
function count_section_photos(array $content, array $photoFields): int {
    return count(array_filter(array_map(fn($f) => $content[$f] ?? '', $photoFields)));
}
