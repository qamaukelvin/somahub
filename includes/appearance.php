<?php
/**
 * Template (structural CSS) and color palette (colors/fonts) are now
 * separate, independently-choosable things — see template_color_split.sql
 * for the schema and rationale.
 *
 * Every school-site-rendering page (site.php, enrollment-apply.php,
 * results-check.php, report-card.php) should use resolve_school_appearance()
 * instead of joining `themes` directly.
 */

function get_active_templates(PDO $db): array {
    return $db->query("SELECT * FROM templates WHERE is_active = 1 ORDER BY is_premium ASC, name ASC")->fetchAll();
}

function get_active_palettes(PDO $db): array {
    return $db->query("SELECT * FROM color_palettes WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
}

/**
 * Resolves which template row applies to a school. Prefers the new
 * template_id; if that's not set yet (school hasn't been manually
 * reassigned since the template/color split), falls back to the legacy
 * theme_id matched by name, so the site keeps rendering exactly as before.
 * Last resort is the first active template, so a page never has nothing
 * to render.
 */
function resolve_school_template(PDO $db, array $school): array {
    if (!empty($school['template_id'])) {
        $stmt = $db->prepare("SELECT * FROM templates WHERE id = ?");
        $stmt->execute([$school['template_id']]);
        $tpl = $stmt->fetch();
        if ($tpl) return $tpl;
    }

    if (!empty($school['theme_id'])) {
        $stmt = $db->prepare("
            SELECT tpl.* FROM templates tpl
            JOIN themes th ON th.name = tpl.name
            WHERE th.id = ?
        ");
        $stmt->execute([$school['theme_id']]);
        $tpl = $stmt->fetch();
        if ($tpl) return $tpl;
    }

    return $db->query("SELECT * FROM templates WHERE is_active = 1 ORDER BY is_premium ASC, id ASC LIMIT 1")->fetch() ?: [];
}

/**
 * Same fallback pattern as resolve_school_template(), for the color side.
 */
function resolve_school_palette(PDO $db, array $school): array {
    if (!empty($school['palette_id'])) {
        $stmt = $db->prepare("SELECT * FROM color_palettes WHERE id = ?");
        $stmt->execute([$school['palette_id']]);
        $p = $stmt->fetch();
        if ($p) return $p;
    }

    if (!empty($school['theme_id'])) {
        $stmt = $db->prepare("
            SELECT cp.* FROM color_palettes cp
            JOIN themes th ON th.name = cp.name
            WHERE th.id = ?
        ");
        $stmt->execute([$school['theme_id']]);
        $p = $stmt->fetch();
        if ($p) return $p;
    }

    return $db->query("SELECT * FROM color_palettes WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetch() ?: [];
}

/**
 * Full appearance bundle for rendering a school's public pages: merged
 * color variables (with the school's own accent/primary/bg overrides
 * applied on top, same as before), the template's structural CSS, and
 * which template is in use (for the premium-lock check).
 *
 * Only the TEMPLATE can be premium-gated — palettes are always free to
 * pick on any plan, on top of any template.
 */
function resolve_school_appearance(PDO $db, array $school): array {
    $template = resolve_school_template($db, $school);
    $palette = resolve_school_palette($db, $school);

    $theme = json_decode($palette['css_variables_json'] ?? '{}', true) ?: [];
    if (!empty($school['accent_override'])) $theme['accent'] = $school['accent_override'];
    if (!empty($school['primary_override'])) $theme['primary'] = $school['primary_override'];
    if (!empty($school['bg_override'])) $theme['bg'] = $school['bg_override'];

    return [
        'theme' => $theme,
        'custom_css' => $template['custom_css'] ?? '',
        'template' => $template,
        'palette' => $palette,
        'is_premium_template' => !empty($template['is_premium']),
    ];
}
