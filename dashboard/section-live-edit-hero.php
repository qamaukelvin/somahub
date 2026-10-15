<?php
// Live, in-place editor for Hero - see section-live-edit.php (About) for
// the pattern this follows; shared chrome lives in _live_edit_shared.php /
// _live_edit_script.php.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/section_variants.php';
require_once __DIR__ . '/../includes/appearance.php';
require_once __DIR__ . '/../includes/plan.php';
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
if (!$section || $section['key_name'] !== 'hero') {
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
    if (($theme['font_display'] ?? '') === $pair['display'] && ($theme['font_body'] ?? '') === $pair['body']) {
        $currentFontPair = $pairKey;
        break;
    }
}

$registry = get_section_variant_registry();
$config = $registry['hero'];
$content = json_decode($section['content_json'], true) ?: [];

$heroVariant = $section['layout_variant'] ?? 'split';
$heroSize = $section['layout_size'] ?? 'auto';
$heroPhotoFields = ['hero_photo', 'hero_photo_2', 'hero_photo_3'];
$heroPhotos = [
    'hero_photo' => $content['hero_photo'] ?? '',
    'hero_photo_2' => $content['hero_photo_2'] ?? '',
    'hero_photo_3' => $content['hero_photo_3'] ?? '',
];
$photoCount = count(array_filter($heroPhotos));
$sizeClass = $heroSize === 'full' ? ' hero-size-full' : ($heroSize === 'half' ? ' hero-size-half' : '');
$hColor = function($f){ global $content; $v = $content[$f] ?? ''; return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? ' style="color:'.$v.';"' : ''; };
$showCta = in_array($heroVariant, ['text_cta', 'split_cta', 'carousel', 'background_fixed'], true);

// Hero's CTA buttons are a curated destination picker, never free text, so
// a button can never point at a page the school doesn't actually have.
// Mirrors site.php's own $ctaDestinations/$availableCtaKeys computation,
// scoped here to just this school.
$ctaDestinations = [
    'contact' => 'Contact Us',
    'enroll' => 'Request Admission',
    'results' => 'Check Results',
    'fees' => 'School Fees',
];
$ctaSectionKeys = ['contact' => 'contact', 'enroll' => 'enrollment_form', 'results' => 'results_lookup', 'fees' => 'fees'];
$ctaSectionNames = ['contact' => 'Contact', 'enroll' => 'Enrollment Form', 'results' => 'Results Lookup', 'fees' => 'Fees'];
$locked = is_premium_locked($school);
$availStmt = $db->prepare("
    SELECT st.key_name, ss.is_visible, st.is_premium FROM site_sections ss
    JOIN section_types st ON st.id = ss.section_type_id
    WHERE ss.school_id = ?
");
$availStmt->execute([$user['school_id']]);
$ctaSectionState = [];
foreach ($availStmt->fetchAll() as $r) {
    $ok = (int)$r['is_visible'] === 1 && !($locked && (int)$r['is_premium'] === 1);
    $ctaSectionState[$r['key_name']] = ($ctaSectionState[$r['key_name']] ?? false) || $ok;
}
// Every destination is always selectable. If its section isn't live, the
// reason is shown as a disclaimer (and the button is hidden on the real
// site until the requirement is met).
$ctaUnmet = [];
foreach ($ctaDestinations as $ctaKey => $ctaLabel) {
    if (!($ctaSectionState[$ctaSectionKeys[$ctaKey]] ?? false)) {
        $ctaUnmet[$ctaKey] = "The " . $ctaSectionNames[$ctaKey] . " section isn't visible on your site, so this button won't show to visitors until you add or show it.";
    }
}
$availableCtaOptions = $ctaDestinations;
$defaultCta = 'contact';
foreach (array_keys($ctaDestinations) as $k) { if (!isset($ctaUnmet[$k])) { $defaultCta = $k; break; } }

$extraCss = <<<CSS
  .hero{background:var(--primary);color:var(--bg);padding:80px 24px 60px;}
  .hero-inner{max-width:1080px;margin:0 auto;display:grid;grid-template-columns:1.1fr 0.9fr;gap:40px;align-items:center;}
  .hero h1{font-size:clamp(2rem,4.8vw,3.2rem);font-weight:700;line-height:1.1;}
  .hero p{margin-top:18px;font-size:1.02rem;opacity:0.85;max-width:50ch;}
  .hero-photo{border-radius:10px;overflow:hidden;}
  .hero-photo img{width:100%;max-height:420px;object-fit:cover;}
  .hero-cta{background:var(--accent);color:var(--primary);padding:13px 28px;border-radius:6px;font-weight:700;font-size:0.9rem;display:inline-block;border:none;cursor:default;font-family:inherit;}
  .hero-cta-row{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px;align-items:center;}
  .hero-cta-row .hero-cta:nth-child(2){background:transparent;border:2px solid currentColor;}
  .hero-mosaic{display:grid;grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr;gap:10px;height:420px;}
  .hero-mosaic img{width:100%;height:100%;object-fit:cover;}
  .hero-mosaic .m-main{grid-row:1/3;}
  .hero-mosaic.two-photos{grid-template-rows:1fr;}
  .hero-mosaic.two-photos .m-main{grid-row:1/2;}
  @media(max-width:820px){
    .hero-inner{grid-template-columns:1fr;}
    .hero{padding:48px 20px 36px;}
    .hero-photo img{max-height:220px;aspect-ratio:16/9;}
    .hero-mosaic{height:220px;}
  }
  .hero-bgfixed-variant{position:relative;padding:0;overflow:hidden;min-height:420px;display:flex;align-items:flex-end;background-size:cover;background-position:center;}
  .hero-bg-overlay{position:absolute;inset:0;background:linear-gradient(180deg, rgba(0,0,0,0.05), rgba(0,0,0,0.7));pointer-events:none;}
  .hero-bg-content{position:relative;z-index:2;padding:60px 6% 50px;color:#fff;max-width:720px;}
  .hero-bg-content h1{color:#fff;}
  .hero-bg-content p{color:rgba(255,255,255,0.88);}
  .hero-text-variant{display:flex;align-items:center;justify-content:center;text-align:center;padding:70px 6%;}
  .hero-inner-text{max-width:680px;}
  .hero-inner-text .hero-cta-row{justify-content:center;}
  /* Editor-only: Hero's live-photo-box needs real height here since it
     isn't nested inside .hero-inner's grid like the live site's plain
     <img>/mosaic are - the box supplies its own 420px/220px box above. */
  .hero-inner .live-photo-box{border-radius:10px;}
  .cta-edit-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;}
  .cta-edit-row select{background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.3);border-radius:6px;padding:7px 10px;font-size:0.78rem;}
  .cta-edit-row select option{color:#1C1C16;}
  .hero-cta.secondary{background:transparent;color:inherit;border:2px solid currentColor;padding:11px 26px;}
  .hero-cta.placeholder{opacity:.75;border-style:dashed;cursor:pointer;}
  .cta-slot{position:relative;display:inline-block;}
  .cta-menu{display:none;position:absolute;top:100%;left:0;padding-top:8px;z-index:50;}
  .cta-slot:hover .cta-menu{display:block;}
  .cta-menu-panel{background:#1C1C16;border-radius:10px;padding:6px;min-width:230px;box-shadow:0 8px 24px rgba(0,0,0,.4);text-align:left;font-family:Arial,sans-serif;}
  .cta-menu-title{color:rgba(255,255,255,.55);font-size:.66rem;text-transform:uppercase;letter-spacing:.05em;padding:6px 8px 4px;}
  .cta-opt{display:flex;align-items:center;gap:6px;width:100%;background:none;border:none;color:#fff;font-size:.8rem;padding:7px 8px;border-radius:6px;cursor:pointer;text-align:left;}
  .cta-opt:hover{background:rgba(255,255,255,.12);}
  .cta-opt.on{background:rgba(255,255,255,.08);font-weight:700;}
  .cta-opt .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;}
  .cta-opt .warn{color:#F4A340;margin-left:auto;}
  .cta-opt.remove{color:#F4A0A0;}
  .hero-cta.unmet{outline:2px dashed #F4A340;outline-offset:2px;}
  .cta-warning{display:flex;gap:6px;align-items:flex-start;margin-top:8px;max-width:520px;background:rgba(244,163,64,.18);border:1px solid rgba(244,163,64,.6);color:#fff;border-radius:6px;padding:6px 9px;font-size:.74rem;line-height:1.35;}
  .cta-warning .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;color:#F4A340;}
  .cta-edit-label{font-size:0.7rem;color:rgba(255,255,255,0.65);align-self:center;}
  /* The CTA buttons themselves are a curated picker, not free text (see
     the dropdowns below), so they aren't contenteditable - this just
     keeps the editor's cursor from implying they are. */
  .hero-photo-layer{position:absolute;inset:0;z-index:0;}
  .hero-photo-layer .live-photo-box{height:100%;border-radius:0;}
  /* Text sits above the photo layer, but the layer's hover grid still
     needs to be reachable - the content only blocks the area it covers. */
  .hero-bgfixed-variant .hero-bg-content{pointer-events:none;}
  .hero-bgfixed-variant .hero-bg-content [contenteditable],
  .hero-bgfixed-variant .hero-bg-content select,
  .hero-bgfixed-variant .hero-bg-content .cta-edit-row,
  .hero-bgfixed-variant .hero-bg-content .cta-slot{pointer-events:auto;}
  .hero-size-half{min-height:50vh !important;}
  .hero-size-full{min-height:100vh !important;}
  .hero-size-half.hero, .hero-size-full.hero{display:flex;align-items:center;}
  .hero-size-half .hero-inner, .hero-size-full .hero-inner{width:100%;}
  .hero-bgfixed-variant.hero-size-half, .hero-bgfixed-variant.hero-size-full{align-items:flex-end;}
CSS;

// The school's template can restyle the hero (e.g. photos as a full-bleed
// background with an overlay). The live site injects that CSS, so the
// editor must too or the two never match.
$themeCss = $appearance['custom_css'] ?? '';
$themePhotoBg = false; // variant layout wins over template hero layout (see get_hero_variant_override_css)
$unusedThemeProbe = (bool)preg_match('/\.hero-(?:mosaic|photo)[^{}]*\{[^}]*position\s*:\s*absolute/i', $themeCss);
$extraCss .= "\n" . $themeCss . <<<CSS2

  .hero::before{pointer-events:none;}
  .hero-themebg{position:relative;overflow:hidden;}
  .hero-themebg .hero-inner{pointer-events:none;}
  .hero-themebg .hero-inner [contenteditable],
  .hero-themebg .hero-inner select,
  .hero-themebg .hero-inner .cta-edit-row,
  .hero-themebg .hero-inner .cta-slot{pointer-events:auto;}
CSS2;
$extraCss .= "\n" . get_hero_variant_override_css() . "\n  .hero.hero-split .live-photo-box{height:420px;}\n  .hero.hero-split .live-photo-box > .hero-mosaic,.hero.hero-split .live-photo-box > .hero-photo{position:absolute;inset:0;height:100%;}\n  @media(max-width:820px){.hero.hero-split .live-photo-box{height:220px;}}\n";
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
<?php render_variant_switcher($config, $heroVariant, $photoCount); ?>
<?php render_size_switcher($heroSize); ?>

<div class="save-toast" id="saveToast"><span class="material-symbols-outlined">check_circle</span><span id="saveToastMsg">Saved</span></div>

<?php
// Hero buttons: shown as real buttons; hovering one opens a small menu to
// choose where it goes. Slot 2 shows an editor-only "+ Add second button"
// placeholder until chosen. Options whose section isn't live carry a warning.
function render_hero_cta_row(array $content, array $options, array $unmet, string $defaultCta): void {
    $c1 = $content['cta_1'] ?? ''; if ($c1 === '' || !isset($options[$c1])) $c1 = $defaultCta;
    $c2 = $content['cta_2'] ?? ''; if (!isset($options[$c2]) || $c2 === $c1) $c2 = '';
    echo '<div class="hero-cta-row">';
    foreach ([1 => $c1, 2 => $c2] as $slot => $val) {
        $field = 'cta_' . $slot;
        echo '<div class="cta-slot">';
        if ($val === '') {
            echo '<span class="hero-cta secondary placeholder"><span class="material-symbols-outlined" style="font-family:\'Material Symbols Outlined\';font-size:16px;vertical-align:-3px;">add</span> Add second button</span>';
        } else {
            echo '<span class="hero-cta' . ($slot === 2 ? ' secondary' : '') . (isset($unmet[$val]) ? ' unmet' : '') . '">' . esc($options[$val]) . '</span>';
        }
        echo '<div class="cta-menu"><div class="cta-menu-panel"><div class="cta-menu-title">Button ' . $slot . ' goes to</div>';
        foreach ($options as $k => $label) {
            $warn = isset($unmet[$k]);
            echo '<button type="button" class="cta-opt' . ($val === $k ? ' on' : '') . '" onclick="somahubSaveCta(\'' . $field . '\',\'' . esc($k) . '\')"' . ($warn ? ' title="' . esc($unmet[$k]) . '"' : '') . '>'
               . '<span class="material-symbols-outlined">' . ($val === $k ? 'check' : 'chevron_right') . '</span>' . esc($label)
               . ($warn ? '<span class="material-symbols-outlined warn">warning</span>' : '') . '</button>';
        }
        if ($slot === 2 && $val !== '') echo '<button type="button" class="cta-opt remove" onclick="somahubSaveCta(\'cta_2\',\'\')"><span class="material-symbols-outlined">close</span>Remove this button</button>';
        echo '</div></div></div>';
    }
    echo '</div>';
    foreach ([$c1, $c2] as $v) {
        if ($v !== '' && isset($unmet[$v])) {
            echo '<div class="cta-warning"><span class="material-symbols-outlined">warning</span><span>' . esc($options[$v]) . ': ' . esc($unmet[$v]) . '</span></div>';
        }
    }
}

// Resolve the same real button labels the live site would show, so the
// preview reads correctly even before any dropdown is touched.
$heroCtaLabels = [];
$heroCtaWarnings = [];
if ($showCta) {
    $chosen1 = $content['cta_1'] ?? '';
    $chosen2 = $content['cta_2'] ?? '';
    if (empty($chosen1)) $chosen1 = $defaultCta;
    foreach ([$chosen1, $chosen2] as $choice) {
        if ($choice && isset($availableCtaOptions[$choice]) && !isset($heroCtaLabels[$choice])) {
            $heroCtaLabels[$choice] = $availableCtaOptions[$choice];
            if (isset($ctaUnmet[$choice])) $heroCtaWarnings[] = $availableCtaOptions[$choice] . ': ' . $ctaUnmet[$choice];
        }
    }
}
?>

<?php if ($heroVariant === 'background_fixed'):
    $bgSrc = $heroPhotos['hero_photo'] ? img($heroPhotos['hero_photo']) : '';
?>
  <section class="hero hero-own-layout hero-bgfixed-variant<?= $sizeClass ?> live-photo-wrap<?= $bgSrc ? '' : ' empty' ?>" id="hero" data-photo-field="hero_photo" style="<?= $bgSrc ? "background-image:url('" . esc($bgSrc) . "');" : '' ?>">
    <?php if ($bgSrc): ?>
      <button type="button" class="photo-delete-btn" title="Remove photo" onclick="event.stopPropagation();somahubDeletePhoto('hero_photo')">
        <span class="material-symbols-outlined">close</span>
      </button>
    <?php endif; ?>
    <div class="photo-overlay" onclick="event.stopPropagation();somahubTriggerPhotoUpload('hero_photo')">
      <span class="photo-overlay-btn">
        <span class="material-symbols-outlined" style="font-family:'Material Symbols Outlined';font-size:16px;">photo_camera</span>
        <?= $bgSrc ? 'Replace photo' : 'Upload photo' ?>
      </span>
    </div>
    <div class="hero-bg-overlay"></div>
    <div class="hero-bg-content">
      <h1 data-field="headline" data-color-field="headline_color"<?= $hColor('headline_color') ?> contenteditable="true" data-placeholder="<?= esc($school['name']) ?>"><?= esc($content['headline'] ?? '') ?></h1>
      <p data-field="subheading" data-color-field="subheading_color"<?= $hColor('subheading_color') ?> contenteditable="true" data-placeholder="A short line about your school"><?= esc($content['subheading'] ?? '') ?></p>
      <?php render_hero_cta_row($content, $availableCtaOptions, $ctaUnmet, $defaultCta); ?>
    </div>
  </section>

<?php elseif ($heroVariant === 'carousel'): ?>
  <section class="hero hero-own-layout" id="hero" style="padding:0;">
    <div class="hero-bgfixed-variant<?= $sizeClass ?>" style="position:relative;">
      <?php /* The photo box sits in its own absolutely-positioned layer behind
               the text (like the live site's carousel track), sized to fill
               the whole hero rather than the box's default 340px. */ ?>
      <div class="hero-photo-layer">
        <?php live_photo_box([
            'id' => 'heroCarouselBox',
            'fields' => $heroPhotoFields,
            'photos' => array_map(fn($p) => $p ? img($p) : '', $heroPhotos),
            'preview' => 'rotate',
            'emptyLabel' => 'Upload multiple photos',
        ]); ?>
      </div>
      <div class="hero-bg-overlay" style="pointer-events:none;z-index:1;"></div>
      <div class="hero-bg-content" style="z-index:2;position:relative;">
        <h1 data-field="headline" data-color-field="headline_color"<?= $hColor('headline_color') ?> contenteditable="true" data-placeholder="<?= esc($school['name']) ?>"><?= esc($content['headline'] ?? '') ?></h1>
        <p data-field="subheading" data-color-field="subheading_color"<?= $hColor('subheading_color') ?> contenteditable="true" data-placeholder="A short line about your school"><?= esc($content['subheading'] ?? '') ?></p>
        <?php render_hero_cta_row($content, $availableCtaOptions, $ctaUnmet, $defaultCta); ?>
      </div>
    </div>
  </section>

<?php elseif (in_array($heroVariant, ['text_only', 'text_cta'], true)): ?>
  <section class="hero hero-own-layout hero-text-variant<?= $sizeClass ?>" id="hero">
    <div class="hero-inner-text">
      <h1 data-field="headline" data-color-field="headline_color"<?= $hColor('headline_color') ?> contenteditable="true" data-placeholder="<?= esc($school['name']) ?>"><?= esc($content['headline'] ?? '') ?></h1>
      <p data-field="subheading" data-color-field="subheading_color"<?= $hColor('subheading_color') ?> contenteditable="true" data-placeholder="A short line about your school"><?= esc($content['subheading'] ?? '') ?></p>
      <?php if ($showCta): ?>
        <?php render_hero_cta_row($content, $availableCtaOptions, $ctaUnmet, $defaultCta); ?>
      <?php endif; ?>
    </div>
  </section>

<?php else: /* split / split_cta - text left, photo(s) right */ ?>
  <section class="hero hero-own-layout hero-split<?= $sizeClass ?><?= $themePhotoBg ? ' hero-themebg' : '' ?>" id="hero">
    <?php if ($themePhotoBg): ?>
      <div class="hero-photo-layer">
        <?php live_photo_box([
            'id' => 'heroMosaicBox',
            'fields' => $heroPhotoFields,
            'photos' => array_map(fn($p) => $p ? img($p) : '', $heroPhotos),
            'preview' => 'mosaic',
            'mosaicSingleClass' => 'hero-photo',
            'mosaicClass' => 'hero-mosaic',
            'emptyLabel' => 'Upload photo(s)',
        ]); ?>
      </div>
    <?php endif; ?>
    <div class="hero-inner">
      <div>
        <h1 data-field="headline" data-color-field="headline_color"<?= $hColor('headline_color') ?> contenteditable="true" data-placeholder="<?= esc($school['name']) ?>"><?= esc($content['headline'] ?? '') ?></h1>
        <p data-field="subheading" data-color-field="subheading_color"<?= $hColor('subheading_color') ?> contenteditable="true" data-placeholder="A short line about your school"><?= esc($content['subheading'] ?? '') ?></p>
        <?php if ($showCta): ?>
          <?php render_hero_cta_row($content, $availableCtaOptions, $ctaUnmet, $defaultCta); ?>
        <?php endif; ?>
      </div>
      <?php if (!$themePhotoBg) live_photo_box([
          'id' => 'heroMosaicBox',
          'fields' => $heroPhotoFields,
          'photos' => array_map(fn($p) => $p ? img($p) : '', $heroPhotos),
          'preview' => 'mosaic',
          'mosaicSingleClass' => 'hero-photo',
          'mosaicClass' => 'hero-mosaic',
          'emptyLabel' => 'Upload photo(s)',
      ]); ?>
    </div>
  </section>
<?php endif; ?>

<?php render_live_edit_script((int)$section['id'], $heroVariant, $heroSize); ?>
<?php render_live_edit_color_tools((int)$section['id'], $theme); ?>
<script>
// A CTA choice changes the button label shown in the preview, so save then
// reload to re-render it (same approach as layout/height changes).
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
