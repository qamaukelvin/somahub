<?php
/**
 * Central registry of per-section layout variants. Adding a new
 * variant-aware section type means adding one entry here plus the
 * actual rendering branches in site.php - nothing else needs a
 * matching hand-edit anymore.
 *
 * Each section's config:
 *   'photo_fields'  => which content_json keys count as "photos" when
 *                      checking a variant's photo requirement
 *   'options'       => variant_key => [label, icon (Material Symbols
 *                      ligature name), photos_needed]
 *   'default'       => variant to use when none has been explicitly set
 *   'has_size'      => whether this section type also gets the
 *                      full/half/auto height control
 *   'field_groups'  => (non-repeatable sections only) variant_key =>
 *                      list of schema field names that variant actually
 *                      uses - the edit form only shows these, instead of
 *                      every field regardless of relevance. A variant
 *                      missing from this map shows all fields (safe
 *                      fallback for anything not explicitly mapped).
 *   'repeatable'    => true for sections built from a numbered list of
 *                      near-identical items (staff members, quotes,
 *                      photos). Drives the one-at-a-time add/remove
 *                      edit UI instead of the flat field list.
 *   'item_label'    => singular display name for one item, e.g. "Staff
 *                      Member" - used in "+ Add Another X" / "Remove
 *                      this X" button text.
 *   'max_items'     => how many numbered slots exist (matches the
 *                      schema migration that added name_1..name_10 etc).
 *   'item_fields'   => the full set of per-item field name *stems*
 *                      (without the numeric suffix) that exist in the
 *                      schema, e.g. 'name' covers name_1..name_10.
 *   'item_field_groups' => variant_key => which of item_fields that
 *                      variant actually uses, e.g. 'minimal' only needs
 *                      name+role, not photo/bio/department.
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
            'field_groups' => [
                'text_only' => ['headline', 'subheading'],
                'text_cta' => ['headline', 'subheading'],
                'split' => ['headline', 'subheading', 'hero_photo', 'hero_photo_2', 'hero_photo_3'],
                'split_cta' => ['headline', 'subheading', 'hero_photo', 'hero_photo_2', 'hero_photo_3'],
                'carousel' => ['headline', 'subheading', 'hero_photo', 'hero_photo_2', 'hero_photo_3'],
                'background_fixed' => ['headline', 'subheading', 'hero_photo'],
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
            'field_groups' => [
                'text_only' => ['body'],
                'photo_right' => ['body', 'photo'],
                'photo_left' => ['body', 'photo'],
                'carousel_left' => ['body', 'photo', 'photo_2', 'photo_3'],
                'list' => ['body', 'list_items'],
                'timeline' => ['body', 'list_items'],
                'quote' => ['body', 'photo', 'author_name'],
                'stats_inline' => ['body', 'photo', 'inline_stat_1', 'inline_stat_1_label', 'inline_stat_2', 'inline_stat_2_label'],
            ],
        ],
        'staff' => [
            'photo_fields' => [],
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
            'repeatable' => true,
            'item_label' => 'Staff Member',
            'max_items' => 10,
            'item_fields' => ['name', 'role', 'photo', 'bio', 'department'],
            'item_field_groups' => [
                'grid' => ['name', 'role', 'photo'],
                'carousel' => ['name', 'role', 'photo'],
                'list_bio' => ['name', 'role', 'photo', 'bio'],
                'org_chart' => ['name', 'role', 'photo'],
                'minimal' => ['name', 'role'],
                'grouped' => ['name', 'role', 'photo', 'department'],
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
            'repeatable' => true,
            'item_label' => 'Testimonial',
            'max_items' => 10,
            'item_fields' => ['quote', 'author', 'photo', 'rating'],
            'item_field_groups' => [
                'cards' => ['quote', 'author'],
                'slider' => ['quote', 'author'],
                'single' => ['quote', 'author'],
                'wall' => ['quote', 'author'],
                'rating' => ['quote', 'author', 'rating'],
                'photo_forward' => ['quote', 'author', 'photo'],
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
            'repeatable' => true,
            'item_label' => 'Photo',
            'max_items' => 10,
            'item_fields' => ['photo', 'category'],
            'item_field_groups' => [
                'grid' => ['photo'],
                'masonry' => ['photo'],
                'lightbox' => ['photo'],
                'before_after' => ['photo'],
                'slideshow' => ['photo'],
                'tabs' => ['photo', 'category'],
            ],
            'bulk_photo_upload' => true,
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
