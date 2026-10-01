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
        'faq' => [
            // No 'options' here - FAQ doesn't get a Design/layout picker,
            // just the same repeatable add/remove editing UX as the others.
            // _section_row.php's Design-button check looks for 'options'
            // specifically, so this correctly gets no Design button.
            'repeatable' => true,
            'item_label' => 'Question',
            'max_items' => 10,
            'item_fields' => ['question', 'answer'],
        ],
        'blog' => [
            // Minimal step for now - numbered post teasers, same pattern as
            // FAQ. Not the real per-school blog subsystem (own archive
            // page, real post management) - that's deliberately deferred.
            'repeatable' => true,
            'item_label' => 'Post',
            'max_items' => 10,
            'item_fields' => ['title', 'body', 'photo'],
        ],
        'stats' => [
            'photo_fields' => [], // stats never need photos, regardless of variant
            'default' => 'strip',
            'has_size' => false,
            'options' => [
                'strip' => ['label' => 'Strip', 'icon' => 'view_column', 'photos_needed' => 0],
                'countup' => ['label' => 'Animated count-up', 'icon' => 'trending_up', 'photos_needed' => 0],
                'icon_paired' => ['label' => 'Icon-paired', 'icon' => 'stars', 'photos_needed' => 0],
                'rings' => ['label' => 'Circular progress rings', 'icon' => 'donut_large', 'photos_needed' => 0],
                'ticker' => ['label' => 'Horizontal ticker', 'icon' => 'view_carousel', 'photos_needed' => 0],
                'bars' => ['label' => 'Comparison bars', 'icon' => 'bar_chart', 'photos_needed' => 0],
            ],
            'repeatable' => true,
            'item_label' => 'Stat',
            'max_items' => 8,
            'item_fields' => ['number', 'label', 'icon'],
            'item_field_groups' => [
                'strip' => ['number', 'label'],
                'countup' => ['number', 'label'],
                'icon_paired' => ['number', 'label', 'icon'],
                'rings' => ['number', 'label'],
                'ticker' => ['number', 'label'],
                'bars' => ['number', 'label'],
            ],
            // Stats predates the repeatable-item system and already uses
            // stat_{index}_{stem} (e.g. stat_1_number) rather than the
            // {stem}_{index} pattern every other repeatable section uses -
            // kept as-is rather than forcing a rename migration on existing
            // schools' already-saved content.
            'field_name_format' => 'stat_{index}_{stem}',
        ],
        'contact' => [
            // Not repeatable - one set of contact details per school, just
            // different presentations of the same fields (plus a couple
            // variant-specific extras).
            'default' => 'stacked',
            'has_size' => false,
            'options' => [
                'stacked' => ['label' => 'Details + map (stacked)', 'icon' => 'view_agenda', 'photos_needed' => 0],
                'side_by_side' => ['label' => 'Details + map (side by side)', 'icon' => 'view_sidebar', 'photos_needed' => 0],
                'map_only' => ['label' => 'Map-only, details overlay', 'icon' => 'map', 'photos_needed' => 0],
                'details_only' => ['label' => 'Details only, no map', 'icon' => 'contact_page', 'photos_needed' => 0],
                'with_form' => ['label' => 'With quick-message form', 'icon' => 'mail', 'photos_needed' => 0],
                'whatsapp_first' => ['label' => 'WhatsApp-first', 'icon' => 'chat', 'photos_needed' => 0],
            ],
            'photo_fields' => [],
            'field_groups' => [
                'stacked' => ['address', 'phone', 'email', 'office_hours', 'map_location'],
                'side_by_side' => ['address', 'phone', 'email', 'office_hours', 'map_location'],
                'map_only' => ['address', 'phone', 'map_location'],
                'details_only' => ['address', 'phone', 'email', 'office_hours'],
                'with_form' => ['address', 'phone', 'email', 'office_hours', 'form_intro'],
                'whatsapp_first' => ['whatsapp_number', 'address', 'phone', 'email'],
            ],
        ],
        'admissions' => [
            'default' => 'text_cta',
            'has_size' => false,
            'photo_fields' => ['photo'],
            'options' => [
                'text_cta' => ['label' => 'Text + Apply button', 'icon' => 'notes', 'photos_needed' => 0],
                'steps' => ['label' => 'Steps / process timeline', 'icon' => 'timeline', 'photos_needed' => 0],
                'checklist' => ['label' => 'Requirements checklist', 'icon' => 'checklist', 'photos_needed' => 0],
                'text_photo' => ['label' => 'Text + photo', 'icon' => 'view_agenda', 'photos_needed' => 1],
                'key_dates' => ['label' => 'Key dates / deadlines', 'icon' => 'event', 'photos_needed' => 0],
                'faq_style' => ['label' => 'Admissions FAQ', 'icon' => 'quiz', 'photos_needed' => 0],
            ],
            'field_groups' => [
                'text_cta' => ['body'],
                'steps' => ['body', 'list_items'],
                'checklist' => ['body', 'list_items'],
                'text_photo' => ['body', 'photo'],
                'key_dates' => ['body', 'list_items'],
                'faq_style' => ['body', 'faq_q1', 'faq_a1', 'faq_q2', 'faq_a2', 'faq_q3', 'faq_a3'],
            ],
        ],
        'academics' => [
            'default' => 'text_only',
            'has_size' => false,
            'photo_fields' => ['photo'],
            'options' => [
                'text_only' => ['label' => 'Text only', 'icon' => 'notes', 'photos_needed' => 0],
                'text_photo' => ['label' => 'Text + photo', 'icon' => 'view_agenda', 'photos_needed' => 1],
                'curriculum_grid' => ['label' => 'Curriculum grid', 'icon' => 'grid_view', 'photos_needed' => 0],
                'grade_levels' => ['label' => 'Grade levels breakdown', 'icon' => 'stairs', 'photos_needed' => 0],
                'stats_inline' => ['label' => 'Text with inline stats', 'icon' => 'bar_chart', 'photos_needed' => 0],
            ],
            'field_groups' => [
                'text_only' => ['body'],
                'text_photo' => ['body', 'photo'],
                'curriculum_grid' => ['body', 'list_items'],
                'grade_levels' => ['body', 'list_items'],
                'stats_inline' => ['body', 'inline_stat_1', 'inline_stat_1_label', 'inline_stat_2', 'inline_stat_2_label'],
            ],
        ],
        'cta_banner' => [
            'default' => 'simple',
            'has_size' => false,
            'photo_fields' => ['photo'],
            'options' => [
                'simple' => ['label' => 'Simple, centered', 'icon' => 'crop_landscape', 'photos_needed' => 0],
                'split' => ['label' => 'Text + button side by side', 'icon' => 'view_sidebar', 'photos_needed' => 0],
                'background_photo' => ['label' => 'Background photo', 'icon' => 'panorama', 'photos_needed' => 1],
                'two_button' => ['label' => 'Two buttons', 'icon' => 'view_agenda', 'photos_needed' => 0],
            ],
            'field_groups' => [
                'simple' => ['headline', 'subtext', 'button_text', 'cta_destination_1'],
                'split' => ['headline', 'subtext', 'button_text', 'cta_destination_1'],
                'background_photo' => ['headline', 'subtext', 'button_text', 'cta_destination_1', 'photo'],
                'two_button' => ['headline', 'subtext', 'button_text', 'cta_destination_1', 'button_text_2', 'cta_destination_2'],
            ],
        ],
        'fees' => [
            // Not repeatable - fee rows live in the real fee_structures
            // table (managed on its own dashboard page), not numbered
            // content_json fields. These variants only change how the
            // same underlying rows are grouped/displayed.
            'default' => 'table',
            'has_size' => false,
            'photo_fields' => [],
            'options' => [
                'table' => ['label' => 'Table', 'icon' => 'table_chart', 'photos_needed' => 0],
                'by_grade' => ['label' => 'Grouped by grade', 'icon' => 'grid_view', 'photos_needed' => 0],
                'by_term' => ['label' => 'Grouped by term', 'icon' => 'calendar_view_month', 'photos_needed' => 0],
                'compact_list' => ['label' => 'Compact list', 'icon' => 'format_list_bulleted', 'photos_needed' => 0],
                'summary_only' => ['label' => 'Summary only', 'icon' => 'summarize', 'photos_needed' => 0],
            ],
            'field_groups' => [
                'table' => ['intro_text'],
                'by_grade' => ['intro_text'],
                'by_term' => ['intro_text'],
                'compact_list' => ['intro_text'],
                'summary_only' => ['intro_text', 'summary_note'],
            ],
        ],
        'enrollment_form' => [
            'default' => 'simple',
            'has_size' => false,
            'photo_fields' => ['photo'],
            'options' => [
                'simple' => ['label' => 'Simple callout', 'icon' => 'campaign', 'photos_needed' => 0],
                'inline_form' => ['label' => 'Inline embedded form', 'icon' => 'assignment', 'photos_needed' => 0],
                'steps_preview' => ['label' => '"How it works" steps', 'icon' => 'timeline', 'photos_needed' => 0],
                'photo_callout' => ['label' => 'Photo + callout', 'icon' => 'view_agenda', 'photos_needed' => 1],
                'deadline_banner' => ['label' => 'Deadline / urgency banner', 'icon' => 'event', 'photos_needed' => 0],
            ],
            'field_groups' => [
                'simple' => ['intro_text'],
                'inline_form' => ['intro_text'],
                'steps_preview' => ['intro_text', 'list_items'],
                'photo_callout' => ['intro_text', 'photo'],
                'deadline_banner' => ['intro_text', 'deadline_text'],
            ],
        ],
        'results_lookup' => [
            'default' => 'simple',
            'has_size' => false,
            'photo_fields' => ['photo'],
            'options' => [
                'simple' => ['label' => 'Simple callout (both buttons)', 'icon' => 'campaign', 'photos_needed' => 0],
                'inline_form' => ['label' => 'Inline lookup form', 'icon' => 'search', 'photos_needed' => 0],
                'photo_callout' => ['label' => 'Photo + callout', 'icon' => 'view_agenda', 'photos_needed' => 1],
                'steps_preview' => ['label' => '"How to check" steps', 'icon' => 'timeline', 'photos_needed' => 0],
                'single_button' => ['label' => 'Single focused button', 'icon' => 'touch_app', 'photos_needed' => 0],
            ],
            'field_groups' => [
                'simple' => ['intro_text'],
                'inline_form' => ['intro_text'],
                'photo_callout' => ['intro_text', 'photo'],
                'steps_preview' => ['intro_text', 'list_items'],
                'single_button' => ['intro_text'],
            ],
        ],
    ];
}

/**
 * Builds the actual content_json field name for one item's field, given a
 * section's naming pattern (defaults to the standard {stem}_{index} used
 * by every repeatable section except Stats).
 */
function format_item_field_name(string $stem, int $index, ?string $pattern = null): string {
    $pattern = $pattern ?? '{stem}_{index}';
    return str_replace(['{stem}', '{index}'], [$stem, (string)$index], $pattern);
}/**
 * How many of a section's designated photo fields are actually filled in,
 * given its content_json (already json_decode'd to an array).
 */
function count_section_photos(array $content, array $photoFields): int {
    return count(array_filter(array_map(fn($f) => $content[$f] ?? '', $photoFields)));
}
