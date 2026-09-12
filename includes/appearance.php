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
 * Builds a small, self-contained HTML document showing what a template
 * looks like — same base structural CSS as the real site.php, with the
 * template's own custom_css layered on top, at fixed neutral placeholder
 * colors (color is a separate, independent choice from template now, so
 * previews use one consistent neutral palette regardless of what a school
 * might actually pick). Meant to be rendered in an isolated <iframe srcdoc>
 * so its CSS can never leak into or collide with the page showing it.
 */
function build_template_preview_html(string $customCss): string {
    $baseCss = "
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Nunito Sans',Arial,sans-serif;color:#1B1B18;background:var(--bg);line-height:1.4;}
        h1,h2,h3{font-family:'Sora','Nunito Sans',Arial,sans-serif;letter-spacing:-0.01em;}
        img{display:block;max-width:100%;background:#D9D3C4;}
        :root{ --primary:#0F5257; --accent:#F2A65A; --bg:#F7F2E7; }
        section{padding:16px;}
        section:nth-of-type(even){background:rgba(0,0,0,0.02);}
        .section-head{margin-bottom:10px;}
        .section-head h2{font-size:0.85rem;font-weight:700;color:var(--primary);}
        .hero{background:var(--primary);color:var(--bg);padding:22px 16px 18px;}
        .hero-inner{display:grid;grid-template-columns:1.1fr 0.9fr;gap:14px;align-items:center;}
        .hero h1{font-size:1.05rem;font-weight:700;line-height:1.15;}
        .hero p{margin-top:5px;font-size:0.62rem;opacity:0.85;}
        .hero-photo{border-radius:6px;overflow:hidden;}
        .hero-photo img{width:100%;height:50px;object-fit:cover;}
        .hero-cta{background:var(--accent);color:var(--primary);margin-top:7px;padding:4px 11px;border-radius:4px;font-weight:700;font-size:0.58rem;display:inline-block;}
        .staff-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;}
        .staff-card{background:#fff;border:1px solid rgba(0,0,0,0.08);border-radius:6px;padding:7px;text-align:center;}
        .staff-card img{width:28px;height:28px;border-radius:50%;margin:0 auto 3px;}
        .staff-card .name{font-size:0.56rem;font-weight:700;}
        .staff-card .role{font-size:0.48rem;color:#6b6b60;}
        .about-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:start;}
        .about-grid p{font-size:0.62rem;color:#3a3a34;margin-bottom:5px;}
        .about-photo{border-radius:6px;overflow:hidden;}
        .about-photo img{width:100%;height:56px;object-fit:cover;}
        .testimonial-grid{display:grid;grid-template-columns:1fr;gap:8px;}
        .testimonial-card{background:#fff;border:1px solid rgba(0,0,0,0.08);border-radius:8px;padding:10px;}
        .testimonial-card .quote{font-size:0.6rem;font-style:italic;color:#3a3a34;margin-bottom:5px;}
        .testimonial-card .author{font-size:0.55rem;font-weight:700;color:var(--primary);}
    ";
    $heroMarkup = '
        <section class="hero"><div class="hero-inner">
            <div><h1>Sample School</h1><p>A short line about the school goes here.</p><span class="hero-cta">Apply Now</span></div>
            <div class="hero-photo"><img src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect width=\'100\' height=\'100\' fill=\'%23D9D3C4\'/%3E%3C/svg%3E" alt=""></div>
        </div></section>
        <section><div class="section-head"><h2>About Us</h2></div>
            <div class="about-grid">
                <div>
                    <p>Founded to provide quality education in a supportive environment for every learner.</p>
                    <p>We believe every child deserves individual attention and a strong foundation.</p>
                </div>
                <div class="about-photo"><img src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect width=\'100\' height=\'100\' fill=\'%23D9D3C4\'/%3E%3C/svg%3E" alt=""></div>
            </div>
        </section>
        <section><div class="section-head"><h2>Our Staff</h2></div>
            <div class="staff-grid">
                <div class="staff-card"><img src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect width=\'100\' height=\'100\' fill=\'%23D9D3C4\'/%3E%3C/svg%3E"><div class="name">Jane Doe</div><div class="role">Head Teacher</div></div>
                <div class="staff-card"><img src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect width=\'100\' height=\'100\' fill=\'%23D9D3C4\'/%3E%3C/svg%3E"><div class="name">John Kamau</div><div class="role">Deputy</div></div>
                <div class="staff-card"><img src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect width=\'100\' height=\'100\' fill=\'%23D9D3C4\'/%3E%3C/svg%3E"><div class="name">Amina Yusuf</div><div class="role">Teacher</div></div>
            </div>
        </section>
        <section><div class="section-head"><h2>What Parents Say</h2></div>
            <div class="testimonial-grid">
                <div class="testimonial-card"><div class="quote">"A wonderful, caring school - my daughter has thrived here."</div><div class="author">— A Parent</div></div>
            </div>
        </section>
    ';
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $baseCss . $customCss . '</style></head><body>' . $heroMarkup . '</body></html>';
}

/**
 * Full appearance bundle for rendering a school's public pages: merged
 * color variables, the template's structural CSS, and which template is
 * in use (for the premium-lock check).
 *
 * Two color modes:
 *  - 'preset' (default): start from the picked palette, then apply any of
 *    the school's own override columns on top (unchanged from before).
 *  - 'custom': skip the palette entirely — every color comes straight from
 *    the override columns, since the school picked all 4 by hand.
 *
 * Only the TEMPLATE can be premium-gated — colors (preset or custom) are
 * always free to pick on any plan, on top of any template.
 */
function resolve_school_appearance(PDO $db, array $school): array {
    $template = resolve_school_template($db, $school);

    if (($school['color_mode'] ?? 'preset') === 'custom') {
        $theme = [
            'primary' => $school['primary_override'] ?: '#0F5257',
            'secondary' => $school['secondary_override'] ?: '#1C1C16',
            'accent' => $school['accent_override'] ?: '#F2A65A',
            'bg' => $school['bg_override'] ?: '#F7F2E7',
        ];
        return [
            'theme' => $theme,
            'custom_css' => $template['custom_css'] ?? '',
            'layout' => json_decode($template['layout_config'] ?? '{}', true) ?: [],
            'template' => $template,
            'palette' => null,
            'is_premium_template' => !empty($template['is_premium']),
        ];
    }

    $palette = resolve_school_palette($db, $school);

    $theme = json_decode($palette['css_variables_json'] ?? '{}', true) ?: [];
    if (!empty($school['accent_override'])) $theme['accent'] = $school['accent_override'];
    if (!empty($school['primary_override'])) $theme['primary'] = $school['primary_override'];
    if (!empty($school['secondary_override'])) $theme['secondary'] = $school['secondary_override'];
    if (!empty($school['bg_override'])) $theme['bg'] = $school['bg_override'];

    return [
        'theme' => $theme,
        'custom_css' => $template['custom_css'] ?? '',
        'layout' => json_decode($template['layout_config'] ?? '{}', true) ?: [],
        'template' => $template,
        'palette' => $palette,
        'is_premium_template' => !empty($template['is_premium']),
    ];
}
