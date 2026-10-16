<?php
/** Sections that have a live (in-place) editor, keyed by section_types.key_name. */
function get_live_editor_files(): array {
    return [
        'about' => 'section-live-edit.php', 'hero' => 'section-live-edit-hero.php',
        'gallery' => 'section-live-edit-gallery.php', 'staff' => 'section-live-edit-staff.php',
        'testimonials' => 'section-live-edit-testimonials.php', 'faq' => 'section-live-edit-faq.php',
        'blog' => 'section-live-edit-blog.php', 'stats' => 'section-live-edit-stats.php',
        'contact' => 'section-live-edit-contact.php', 'admissions' => 'section-live-edit-prose.php',
        'academics' => 'section-live-edit-prose.php', 'cta_banner' => 'section-live-edit-cta-banner.php',
        'fees' => 'section-live-edit-fees.php', 'enrollment_form' => 'section-live-edit-callout.php',
        'results_lookup' => 'section-live-edit-callout.php',
    ];
}
