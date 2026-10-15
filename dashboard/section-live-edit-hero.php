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
    'enroll' => 'Request Admission',
    'contact' => 'Contact Us',
    'results' => 'Check Results',
    'fees' => 'School Fees',
];
$ctaSectionKeys = ['enroll' => 'enrollment_form', 'contact' => 'contact', 'results' => 'results_lookup', 'fees' => 'fees'];
$locked = is_premium_locked($school);
$availStmt = $db->prepare("
    SELECT st.key_name FROM site_sections ss
    JOIN section_types st ON st.id = ss.section_type_id
    WHERE ss.school_id = ? AND ss.is_visible = 1 AND (? = 0 OR st.is_premium = 0)
");
$availStmt->execute([$user['school_id'], $locked ? 1 : 0]);
$availableSectionKeys = array_column($availStmt->fetchAll(), 'key_name');
$availableCtaOptions = [];
foreach ($ctaDestinations as $ctaKey => $ctaLabel) {
    if (in_array($ctaSectionKeys[$ctaKey], $availableSectionKeys, true)) $availableCtaOptions[$ctaKey] = $ctaLabel;
}
$defaultCta = isset($availableCtaOptions['enroll']) ? 'enroll' : (isset($availableCtaOptions['contact']) ? 'contact' : null);

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
  .hero-bgfixed-variant .hero-bg-content .cta-edit-row{pointer-events:auto;}
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
  .hero-themebg .hero-inner .cta-edit-row{pointer-events:auto;}
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
// The CTA destination pickers - shared by every variant that shows
// buttons. Rendered once, reused wherever $showCta is true.
function render_hero_cta_pickers(array $content, array $availableCtaOptions): void { ?>
  <div class="cta-edit-row">
    <span class="cta-edit-label">Button 1</span>
    <select onchange="somahubSaveCta('cta_1', this.value)">
      <option value="">Use default</option>
      <?php foreach ($availableCtaOptions as $ctaKey => $ctaLabel): ?>
        <option value="<?= esc($ctaKey) ?>" <?= ($content['cta_1'] ?? '') === $ctaKey ? 'selected' : '' ?>><?= esc($ctaLabel) ?></option>
      <?php endforeach; ?>
    </select>
    <span class="cta-edit-label">Button 2</span>
    <select onchange="somahubSaveCta('cta_2', this.value)">
      <option value="">None</option>
      <?php foreach ($availableCtaOptions as $ctaKey => $ctaLabel): ?>
        <option value="<?= esc($ctaKey) ?>" <?= ($content['cta_2'] ?? '') === $ctaKey ? 'selected' : '' ?>><?= esc($ctaLabel) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
<?php }

// Resolve the same real button labels the live site would show, so the
// preview reads correctly even before any dropdown is touched.
$heroCtaLabels = [];
if ($showCta) {
    $chosen1 = $content['cta_1'] ?? '';
    $chosen2 = $content['cta_2'] ?? '';
    if (empty($chosen1) && $defaultCta) $chosen1 = $defaultCta;
    foreach ([$chosen1, $chosen2] as $choice) {
        if ($choice && isset($availableCtaOptions[$choice]) && !isset($heroCtaLabels[$choice])) {
            $heroCtaLabels[$choice] = $availableCtaOptions[$choice];
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
      <div class="hero-cta-row">
        <?php foreach ($heroCtaLabels as $label): ?><span class="hero-cta"><?= esc($label) ?></span><?php endforeach; ?>
      </div>
      <?php render_hero_cta_pickers($content, $availableCtaOptions); ?>
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
        <div class="hero-cta-row">
          <?php foreach ($heroCtaLabels as $label): ?><span class="hero-cta"><?= esc($label) ?></span><?php endforeach; ?>
        </div>
        <?php render_hero_cta_pickers($content, $availableCtaOptions); ?>
      </div>
    </div>
  </section>

<?php elseif (in_array($heroVariant, ['text_only', 'text_cta'], true)): ?>
  <section class="hero hero-own-layout hero-text-variant<?= $sizeClass ?>" id="hero">
    <div class="hero-inner-text">
      <h1 data-field="headline" data-color-field="headline_color"<?= $hColor('headline_color') ?> contenteditable="true" data-placeholder="<?= esc($school['name']) ?>"><?= esc($content['headline'] ?? '') ?></h1>
      <p data-field="subheading" data-color-field="subheading_color"<?= $hColor('subheading_color') ?> contenteditable="true" data-placeholder="A short line about your school"><?= esc($content['subheading'] ?? '') ?></p>
      <?php if ($showCta): ?>
        <div class="hero-cta-row">
          <?php foreach ($heroCtaLabels as $label): ?><span class="hero-cta"><?= esc($label) ?></span><?php endforeach; ?>
        </div>
        <?php render_hero_cta_pickers($content, $availableCtaOptions); ?>
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
          <div class="hero-cta-row">
            <?php foreach ($heroCtaLabels as $label): ?><span class="hero-cta"><?= esc($label) ?></span><?php endforeach; ?>
          </div>
          <?php render_hero_cta_pickers($content, $availableCtaOptions); ?>
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
