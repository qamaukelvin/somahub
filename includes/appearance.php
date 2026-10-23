<?php
/**
 * Appearance = colours + fonts only. Sites share one set of layouts; schools
 * pick a colour palette (or their own colours) and a font pair.
 *
 * Every school-site-rendering page (site.php, enrollment-apply.php,
 * results-check.php, report-card.php, the live editors) uses
 * resolve_school_appearance().
 */

/** Plain-English name for a colour, from its hue/lightness (e.g. "Forest", "Gold", "Navy"). */
function palette_color_name(string $hex, bool $accent = false): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) return $accent ? 'Gold' : 'Teal';
    [$r, $g, $b] = [hexdec(substr($hex,0,2))/255, hexdec(substr($hex,2,2))/255, hexdec(substr($hex,4,2))/255];
    $mx = max($r,$g,$b); $mn = min($r,$g,$b); $l = ($mx+$mn)/2; $d = $mx-$mn;
    $s = $d == 0 ? 0 : $d / (1 - abs(2*$l - 1));
    if ($d == 0) $h = 0; elseif ($mx == $r) $h = fmod(($g-$b)/$d, 6); elseif ($mx == $g) $h = ($b-$r)/$d + 2; else $h = ($r-$g)/$d + 4;
    $h = fmod($h*60 + 360, 360);
    if ($s < 0.12) return $l < 0.2 ? 'Charcoal' : ($l < 0.6 ? 'Slate' : 'Silver');
    if ($s < 0.30 && $l < 0.35) return 'Slate';
    $dark = $l < 0.30; $light = $l > 0.68;
    if ($h < 15 || $h >= 345) return $dark ? 'Maroon' : ($light ? 'Rose' : 'Crimson');
    if ($h < 28)  return $dark ? 'Brown' : ($light ? 'Peach' : 'Coral');
    if ($h < 52)  return $dark ? 'Bronze' : ($light ? 'Sand' : 'Amber');
    if ($h < 68)  return $dark ? 'Olive' : ($light ? 'Cream' : 'Gold');
    if ($h < 168) return $dark ? 'Forest' : ($light ? 'Mint' : ($accent ? 'Lime' : 'Green'));
    if ($h < 188) return $dark ? 'Deep Teal' : ($light ? 'Aqua' : 'Teal');
    if ($h < 215) return $dark ? 'Ocean' : ($light ? 'Sky' : 'Cyan');
    if ($h < 250) return $dark ? 'Navy' : ($light ? 'Sky' : 'Royal Blue');
    if ($h < 285) return $dark ? 'Indigo' : ($light ? 'Lavender' : 'Violet');
    return $dark ? 'Plum' : ($light ? 'Blush' : 'Magenta');
}
/** [palette id => display name], unique, named after the actual colours: "Forest & Gold". */
function palette_display_names(array $palettes): array {
    $out = []; $seen = [];
    foreach ($palettes as $p) {
        $v = json_decode($p['css_variables_json'] ?? '{}', true) ?: [];
        $a = palette_color_name($v['primary'] ?? '#0F5257');
        $b = palette_color_name($v['accent'] ?? '#F2A65A', true);
        $n = $a === $b ? $a . ' Tones' : "$a & $b";
        $seen[$n] = ($seen[$n] ?? 0) + 1;
        if ($seen[$n] > 1) $n .= ' ' . $seen[$n];
        $out[(int)$p['id']] = $n;
    }
    return $out;
}

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
