<?php
// Live, in-place editor for the CTA Banner: headline/subtext/button labels
// edited in place, destination picked from a hover menu (same as Hero).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/section_variants.php';
require_once __DIR__ . '/../includes/appearance.php';
require_once __DIR__ . '/_live_edit_shared.php';
require_once __DIR__ . '/_live_edit_script.php';
require_once __DIR__ . '/_live_edit_color.php';
$user = require_school_login();
$db = get_db();

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("
    SELECT ss.*, st.label, st.key_name, st.schema_json
    FROM site_sections ss
    JOIN section_types st ON st.id = ss.section_type_id
    WHERE ss.id = ? AND ss.school_id = ?
");
$stmt->execute([$id, $user['school_id']]);
$section = $stmt->fetch();
if (!$section || $section['key_name'] !== 'cta_banner') {
    http_response_code(404);
    exit('Live editor is not available for this section yet.');
}

$stmt2 = $db->prepare("SELECT * FROM schools WHERE id = ?");
$stmt2->execute([$user['school_id']]);
$school = $stmt2->fetch();
$appearance = resolve_school_appearance($db, $school);
$theme = $appearance['theme'];
$fontPairs = get_font_pairs();
$currentFontPair = 'default';
foreach ($fontPairs as $pairKey => $pair) {
    if (($theme['font_display'] ?? '') === $pair['display'] && ($theme['font_body'] ?? '') === $pair['body']) { $currentFontPair = $pairKey; break; }
}

$content = json_decode($section['content_json'], true) ?: [];
$config = get_section_variant_registry()['cta_banner'];
$variant = $section['layout_variant'] ?? 'simple';
$photo = $content['photo'] ?? '';
$photoCount = $photo ? 1 : 0;

// Same curated destinations + "unmet" disclaimers as the Hero editor.
require_once __DIR__ . '/../includes/plan.php';
$ctaOptions = ['contact' => 'Contact Us', 'enroll' => 'Request Admission', 'results' => 'Check Results', 'fees' => 'School Fees'];
$ctaSectionKeys = ['contact' => 'contact', 'enroll' => 'enrollment_form', 'results' => 'results_lookup', 'fees' => 'fees'];
$ctaSectionNames = ['contact' => 'Contact', 'enroll' => 'Enrollment Form', 'results' => 'Results Lookup', 'fees' => 'Fees'];
$locked = is_premium_locked($school);
$st = $db->prepare("SELECT st.key_name, ss.is_visible, st.is_premium FROM site_sections ss JOIN section_types st ON st.id = ss.section_type_id WHERE ss.school_id = ?");
$st->execute([$user['school_id']]);
$state = [];
foreach ($st->fetchAll() as $r) {
    $ok = (int)$r['is_visible'] === 1 && !($locked && (int)$r['is_premium'] === 1);
    $state[$r['key_name']] = ($state[$r['key_name']] ?? false) || $ok;
}
$unmet = [];
foreach ($ctaOptions as $k => $_) {
    if (!($state[$ctaSectionKeys[$k]] ?? false)) $unmet[$k] = "The " . $ctaSectionNames[$k] . " section isn't visible on your site, so this button won't show to visitors until you add or show it.";
}

function cb_slot(array $c, int $slot, array $options, array $unmet, string $btnClass): void {
    $dField = 'cta_destination_' . $slot;
    $lField = $slot === 1 ? 'button_text' : 'button_text_2';
    $val = $c[$dField] ?? '';
    if (!isset($options[$val])) $val = '';
    echo '<div class="cta-slot">';
    if ($val === '') {
        echo '<span class="' . $btnClass . ' placeholder"><span class="material-symbols-outlined" style="font-family:\'Material Symbols Outlined\';font-size:16px;vertical-align:-3px;">add</span> Add ' . ($slot === 1 ? 'a button' : 'second button') . '</span>';
    } else {
        echo '<span class="' . $btnClass . (isset($unmet[$val]) ? ' unmet' : '') . '"><span data-field="' . $lField . '" contenteditable="true" data-placeholder="' . esc($options[$val]) . '">' . esc($c[$lField] ?? '') . '</span></span>';
    }
    echo '<div class="cta-menu"><div class="cta-menu-panel"><div class="cta-menu-title">Button ' . $slot . ' goes to</div>';
    foreach ($options as $k => $label) {
        $warn = isset($unmet[$k]);
        echo '<button type="button" class="cta-opt' . ($val === $k ? ' on' : '') . '" onclick="somahubSaveCta(\'' . $dField . '\',\'' . $k . '\')"' . ($warn ? ' title="' . esc($unmet[$k]) . '"' : '') . '><span class="material-symbols-outlined">' . ($val === $k ? 'check' : 'chevron_right') . '</span>' . esc($label) . ($warn ? '<span class="material-symbols-outlined warn">warning</span>' : '') . '</button>';
    }
    if ($val !== '') echo '<button type="button" class="cta-opt remove" onclick="somahubSaveCta(\'' . $dField . '\',\'\')"><span class="material-symbols-outlined">close</span>Remove this button</button>';
    echo '</div></div></div>';
}
// Which slots to show: slot 1 always; slot 2 when set (the live site shows it
// in every layout) or when the layout is "two buttons".
$show2 = $variant === 'two_button' || !empty($content['cta_destination_2']);
function cb_buttons(array $c, bool $show2, array $o, array $u, string $cls1, string $cls2, string $wrapCls): void {
    echo '<div class="' . $wrapCls . '">';
    cb_slot($c, 1, $o, $u, $cls1);
    if ($show2) cb_slot($c, 2, $o, $u, $cls2);
    echo '</div>';
}
$warnings = [];
foreach ([1, 2] as $sl) { $v = $content["cta_destination_$sl"] ?? ''; if ($v !== '' && isset($unmet[$v])) $warnings[] = $ctaOptions[$v] . ': ' . $unmet[$v]; }
$photoMissing = $variant === 'background_photo' && !$photo;

$extraCss = <<<CSS
  section#cta_banner{background:var(--bg);padding:60px 0;min-height:60vh;}
  .cta-banner{background:var(--primary);color:var(--bg);border-radius:10px;padding:44px 32px;text-align:center;position:relative;}
  .cta-banner h2{font-size:clamp(1.4rem,3vw,1.9rem);margin-bottom:10px;}
  .cta-banner p{opacity:.85;max-width:50ch;margin:0 auto 22px;}
  .cta-banner-split{display:flex;justify-content:space-between;align-items:center;text-align:left;gap:24px;flex-wrap:wrap;}
  .cta-banner-split h2,.cta-banner-split p{text-align:left;margin:0;}
  .cta-banner-split p{margin-top:6px;}
  .cta-banner-photo{padding:0;min-height:280px;display:flex;align-items:center;justify-content:center;background-size:cover;background-position:center;}
  .cta-banner-photo.live-photo-wrap.empty{background:var(--primary);min-height:280px;display:flex;}
  .cta-banner-photo .photo-overlay,.cta-banner-photo.empty .photo-overlay{position:absolute !important;inset:0;background:none !important;align-items:flex-start;justify-content:flex-start;padding:12px;pointer-events:none;z-index:1;}
  .cta-banner-photo .photo-overlay-btn{pointer-events:auto;}
  .cta-banner-overlay{position:absolute;inset:0;background:rgba(0,0,0,.55);border-radius:inherit;pointer-events:none;}
  .cta-banner-content{position:relative;z-index:2;padding:40px 24px;}
  .cta-banner-content h2,.cta-banner-content p{color:#fff;}
  .cta-btns{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;}
  .cta-banner-split .cta-btns{justify-content:flex-end;}
  .btn-primary,.hero-cta{background:var(--accent);color:var(--primary);padding:12px 26px;border-radius:6px;font-weight:700;font-size:.9rem;display:inline-block;}
  .hero-cta.secondary{background:transparent;border:2px solid currentColor;color:inherit;padding:10px 24px;}
  .btn-primary.secondary{background:transparent;border:2px solid currentColor;color:inherit;}
  .btn-primary.placeholder,.hero-cta.placeholder{opacity:.75;border:2px dashed currentColor;background:transparent;color:inherit;cursor:pointer;}
  .cta-slot{position:relative;display:inline-block;}
  .cta-menu{display:none;position:absolute;top:100%;left:50%;transform:translateX(-50%);padding-top:8px;z-index:50;}
  .cta-slot:hover .cta-menu{display:block;}
  .cta-menu-panel{background:#1C1C16;border-radius:10px;padding:6px;min-width:230px;box-shadow:0 8px 24px rgba(0,0,0,.4);text-align:left;font-family:Arial,sans-serif;color:#fff;}
  .cta-menu-title{color:rgba(255,255,255,.55);font-size:.66rem;text-transform:uppercase;letter-spacing:.05em;padding:6px 8px 4px;}
  .cta-opt{display:flex;align-items:center;gap:6px;width:100%;background:none;border:none;color:#fff;font-size:.8rem;padding:7px 8px;border-radius:6px;cursor:pointer;text-align:left;}
  .cta-opt:hover{background:rgba(255,255,255,.12);} .cta-opt.on{background:rgba(255,255,255,.08);font-weight:700;}
  .cta-opt .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;}
  .cta-opt .warn{color:#F4A340;margin-left:auto;} .cta-opt.remove{color:#F4A0A0;}
  .btn-primary.unmet,.hero-cta.unmet{outline:2px dashed #F4A340;outline-offset:2px;}
  .cta-warning{display:flex;gap:6px;align-items:flex-start;margin-top:12px;max-width:560px;background:rgba(244,163,64,.18);border:1px solid rgba(244,163,64,.7);color:#5a3d00;border-radius:6px;padding:7px 10px;font-size:.78rem;line-height:1.35;}
  .cta-warning .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;color:#c77a00;}
  .i-note{font-size:.8rem;color:#6b6b60;margin:0 0 14px;display:flex;gap:6px;align-items:center;}
  .i-note .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;}
CSS;
function cb_head(array $c): string {
    return '<h2 data-field="headline"' . live_color_attr($c, 'headline') . ' contenteditable="true" data-placeholder="A short, strong call to action">' . esc($c['headline'] ?? '') . '</h2>'
         . '<p data-field="subtext"' . live_color_attr($c, 'subtext') . ' contenteditable="true" data-placeholder="Add a supporting line (optional)">' . esc($c['subtext'] ?? '') . '</p>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editing: <?= esc($section['label']) ?></title>
<?php render_live_edit_head($theme, $extraCss); ?>
</head>
<body>

<?php render_live_edit_topbar($section, $fontPairs, $currentFontPair); ?>
<?php render_variant_switcher($config, $variant, $photoCount); ?>

<div class="save-toast" id="saveToast"><span class="material-symbols-outlined">check_circle</span><span id="saveToastMsg">Saved</span></div>

<section id="cta_banner">
  <div class="wrap">
    <?php if ($photoMissing): ?><p class="i-note"><span class="material-symbols-outlined">info</span>Add a background photo below. Until then your live site shows the simple layout.</p><?php endif; ?>
    <?php if (empty($content['cta_destination_1'])): ?><p class="i-note"><span class="material-symbols-outlined">info</span>Hover the dashed button to choose where it goes. With no button chosen, the banner shows no button on your live site.</p><?php endif; ?>

    <?php if ($variant === 'background_photo'): $bg = $photo ? img($photo) : ''; ?>
      <div class="cta-banner cta-banner-photo live-photo-wrap<?= $bg ? '' : ' empty' ?>" data-photo-field="photo" style="<?= $bg ? "background-image:url('" . esc($bg) . "');" : '' ?>">
        <?php if ($bg): ?><button type="button" class="photo-delete-btn" title="Remove photo" onclick="event.stopPropagation();somahubDeletePhoto('photo')"><span class="material-symbols-outlined">close</span></button><?php endif; ?>
        <div class="photo-overlay"><span class="photo-overlay-btn" onclick="event.stopPropagation();somahubTriggerPhotoUpload('photo')"><span class="material-symbols-outlined" style="font-family:'Material Symbols Outlined';font-size:16px;">photo_camera</span><?= $bg ? 'Replace photo' : 'Upload photo' ?></span></div>
        <div class="cta-banner-overlay"></div>
        <div class="cta-banner-content"><?= cb_head($content) ?><?php cb_buttons($content, $show2, $ctaOptions, $unmet, 'btn-primary', 'btn-primary secondary', 'cta-btns'); ?></div>
      </div>

    <?php elseif ($variant === 'split'): ?>
      <div class="cta-banner cta-banner-split"><div><?= cb_head($content) ?></div><?php cb_buttons($content, $show2, $ctaOptions, $unmet, 'btn-primary', 'btn-primary secondary', 'cta-btns'); ?></div>

    <?php elseif ($variant === 'two_button'): ?>
      <div class="cta-banner"><?= cb_head($content) ?><?php cb_buttons($content, true, $ctaOptions, $unmet, 'hero-cta', 'hero-cta secondary', 'cta-btns'); ?></div>

    <?php else: ?>
      <div class="cta-banner"><?= cb_head($content) ?><?php cb_buttons($content, $show2, $ctaOptions, $unmet, 'btn-primary', 'btn-primary secondary', 'cta-btns'); ?></div>
    <?php endif; ?>

    <?php foreach ($warnings as $w): ?><div class="cta-warning"><span class="material-symbols-outlined">warning</span><span><?= esc($w) ?></span></div><?php endforeach; ?>
  </div>
</section>

<?php render_live_edit_script((int)$section['id'], $variant); ?>
<?php render_live_edit_color_tools((int)$section['id'], $theme); ?>
<script>
window.somahubSaveCta = function(field, value) {
  var fd = new FormData();
  fd.append('section_id', <?= (int)$section['id'] ?>);
  fd.append(field, value);
  fetch('section-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data.ok) { sessionStorage.setItem('somahubLiveEditNotice', 'Button updated'); window.location.reload(); }
    });
};
</script>
</body>
</html>
