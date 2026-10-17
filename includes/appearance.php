<?php
/**
 * Appearance = colours + fonts only. Sites share one set of layouts; schools
 * pick a colour palette (or their own colours) and a font pair.
 *
 * Every school-site-rendering page (site.php, enrollment-apply.php,
 * results-check.php, report-card.php, the live editors) uses
 * resolve_school_appearance().
 */

function get_active_palettes(PDO $db): array {
    return $db->query("SELECT * FROM color_palettes WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
}

/**
 * The school's palette row: its palette_id, else the first active palette.
 */
function resolve_school_palette(PDO $db, array $school): array {
    if (!empty($school['palette_id'])) {
        $stmt = $db->prepare("SELECT * FROM color_palettes WHERE id = ?");
        $stmt->execute([$school['palette_id']]);
        $p = $stmt->fetch();
        if ($p) return $p;
    }

    return $db->query("SELECT * FROM color_palettes WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetch() ?: [];
}

/**
 * Full appearance bundle for a school's public pages: merged colour and font
 * variables (`theme`) plus the palette row (null in custom mode).
 *
 * Two color modes:
 *  - 'preset' (default): start from the picked palette, then apply any of
 *    the school's own override columns on top (unchanged from before).
 *  - 'custom': skip the palette entirely — every color comes straight from
 *    the override columns, since the school picked all 4 by hand.
 *
 * Colours (preset or custom) are free on every plan.
 */
function resolve_school_appearance(PDO $db, array $school): array {
    if (($school['color_mode'] ?? 'preset') === 'custom') {
        $theme = [
            'primary' => $school['primary_override'] ?: '#0F5257',
            'secondary' => $school['secondary_override'] ?: '#1C1C16',
            'accent' => $school['accent_override'] ?: '#F2A65A',
            'bg' => $school['bg_override'] ?: '#F7F2E7',
            'font_display' => $school['font_display_override'] ?: 'Sora',
            'font_body' => $school['font_body_override'] ?: 'Nunito Sans',
        ];
        return [
            'theme' => $theme,
            'palette' => null,
        ];
    }

    $palette = resolve_school_palette($db, $school);

    $theme = json_decode($palette['css_variables_json'] ?? '{}', true) ?: [];
    if (!empty($school['accent_override'])) $theme['accent'] = $school['accent_override'];
    if (!empty($school['primary_override'])) $theme['primary'] = $school['primary_override'];
    if (!empty($school['secondary_override'])) $theme['secondary'] = $school['secondary_override'];
    if (!empty($school['bg_override'])) $theme['bg'] = $school['bg_override'];
    // Font pair is its own override, independent of color mode - a school on
    // a preset color palette can still pick a different font pairing.
    if (!empty($school['font_display_override'])) $theme['font_display'] = $school['font_display_override'];
    if (!empty($school['font_body_override'])) $theme['font_body'] = $school['font_body_override'];

    return [
        'theme' => $theme,
        'palette' => $palette,
    ];
}

/**
 * Curated display/body font pairings - a picker, not free text, so a school
 * can never end up with an unreadable or unloaded combination. Key is what
 * gets saved to font_display_override/font_body_override.
 */
function get_font_pairs(): array {
    return [
        'sora_nunito'     => ['label' => 'Sora + Nunito Sans (default)', 'display' => 'Sora', 'body' => 'Nunito Sans'],
        'playfair_lato'   => ['label' => 'Playfair Display + Lato',      'display' => 'Playfair Display', 'body' => 'Lato'],
        'poppins_inter'   => ['label' => 'Poppins + Inter',              'display' => 'Poppins', 'body' => 'Inter'],
        'merriweather_open' => ['label' => 'Merriweather + Open Sans',   'display' => 'Merriweather', 'body' => 'Open Sans'],
        'montserrat_source' => ['label' => 'Montserrat + Source Sans 3', 'display' => 'Montserrat', 'body' => 'Source Sans 3'],
        'dmserif_karla'   => ['label' => 'DM Serif Display + Karla',     'display' => 'DM Serif Display', 'body' => 'Karla'],
    ];
}
