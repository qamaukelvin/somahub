<?php
// Live, in-place editor for About: renders the section the way it actually
// looks on the live site (same markup/CSS, real school colors) and lets
// the school edit text/photos/layout directly on top of it. Shared chrome
// (topbar, font picker, variant switcher, photo upload/delete/multi-photo
// box, save toast) lives in _live_edit_shared.php / _live_edit_script.php
// so every other section's live editor reuses the same system.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/section_variants.php';
require_once __DIR__ . '/../includes/appearance.php';
require_once __DIR__ . '/_live_edit_shared.php';
require_once __DIR__ . '/_live_edit_script.php';
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
if (!$section || $section['key_name'] !== 'about') {
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
$config = $registry['about'];
$content = json_decode($section['content_json'], true) ?: [];

$aboutVariant = $section['layout_variant'] ?? 'photo_right';
$aboutPhotos = [
    'photo' => $content['photo'] ?? '',
    'photo_2' => $content['photo_2'] ?? '',
    'photo_3' => $content['photo_3'] ?? '',
];
$photoCount = count(array_filter($aboutPhotos));
$listItems = array_values(array_filter(array_map('trim', explode("\n", $content['list_items'] ?? ''))));

$extraCss = <<<CSS
  .about-grid{display:grid;grid-template-columns:1.1fr 0.9fr;gap:40px;align-items:start;}
  @media(max-width:820px){.about-grid{grid-template-columns:1fr;}}
  .about-grid p{margin-bottom:14px;color:#3a3a34;}
  .about-grid > div[data-field="body"],
  .about-text-only[data-field="body"],
  .about-list-intro[data-field="body"],
  .about-quote-text div[data-field="body"]{color:#3a3a34;line-height:1.8;}
  .about-photo{border-radius:10px;overflow:hidden;}
  .about-photo img{width:100%;max-height:420px;object-fit:cover;display:block;}
  .about-photo-left{grid-template-columns:0.9fr 1.1fr;}
  @media(max-width:820px){.about-photo-left{grid-template-columns:1fr;}}
  .about-photo-left .about-photo, .about-photo-left .live-photo-box{order:-1;}
  @media(max-width:820px){.about-photo-left .about-photo, .about-photo-left .live-photo-box{order:0;}}
  .about-text-only{max-width:760px;color:#3a3a34;}
  .about-text-only p{margin-bottom:14px;}
  .about-list-intro{max-width:760px;color:#3a3a34;margin-bottom:20px;}
  .about-list{max-width:760px;list-style:none;padding:0;}
  .about-list li{position:relative;padding:12px 0 12px 30px;border-bottom:1px solid rgba(0,0,0,0.08);color:#3a3a34;}
  .about-list li::before{content:"\\2713";position:absolute;left:0;top:12px;color:var(--accent);font-weight:800;}
  .about-timeline{max-width:760px;position:relative;padding-left:28px;}
  .about-timeline::before{content:"";position:absolute;left:6px;top:6px;bottom:6px;width:2px;background:var(--accent);}
  .about-timeline-item{position:relative;padding:0 0 22px 20px;color:#3a3a34;}
  .about-timeline-item::before{content:"";position:absolute;left:-28px;top:4px;width:11px;height:11px;border-radius:50%;background:var(--primary);border:2px solid var(--accent);}
  .about-quote-grid{display:grid;grid-template-columns:0.8fr 1.2fr;gap:40px;align-items:center;}
  @media(max-width:820px){.about-quote-grid{grid-template-columns:1fr;}}
  .about-quote-photo{border-radius:50%;overflow:hidden;width:220px;height:220px;margin:0 auto;}
  .about-quote-photo img{width:100%;height:100%;object-fit:cover;display:block;}
  .about-quote-mark{font-family:Georgia,serif;font-size:3.5rem;color:var(--accent);line-height:1;margin-bottom:-10px;}
  .about-quote-text{color:#3a3a34;font-size:1.1rem;font-style:italic;}
  .about-quote-author{margin-top:14px;font-weight:700;font-style:normal;color:var(--primary);}
  .about-inline-stats{display:flex;gap:28px;margin-top:20px;flex-wrap:wrap;}
  .about-inline-stat{display:flex;flex-direction:column;}
  .about-inline-stat .num{font-size:1.6rem;font-weight:800;color:var(--primary);}
  .about-inline-stat .label{font-size:0.78rem;color:#5c5c52;}
  section#about{background:var(--bg);padding:60px 0;min-height:100vh;}
CSS;
// Same template CSS the live site injects, so the editor matches it.
$extraCss .= "\n" . ($appearance['custom_css'] ?? '');
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
<?php render_variant_switcher($config, $aboutVariant, $photoCount); ?>

<div class="save-toast" id="saveToast"><span class="material-symbols-outlined">check_circle</span><span id="saveToastMsg">Saved</span></div>

<section id="about">
  <div class="wrap">
    <div class="section-head"><h2 contenteditable="true" data-field="_label_readonly" style="pointer-events:none;"><?= esc($section['label']) ?></h2></div>

    <?php if ($aboutVariant === 'text_only'): ?>
      <div class="about-text-only" data-field="body" contenteditable="true" data-placeholder="Tell families about your school..."><?= nl2p_live($content['body'] ?? '') ?></div>

    <?php elseif ($aboutVariant === 'photo_left'): ?>
      <div class="about-grid about-photo-left">
        <?php live_photo_block('photo', $aboutPhotos['photo'] ? img($aboutPhotos['photo']) : ''); ?>
        <div data-field="body" contenteditable="true" data-placeholder="Tell families about your school..."><?= nl2p_live($content['body'] ?? '') ?></div>
      </div>

    <?php elseif ($aboutVariant === 'carousel_left'): ?>
      <div class="about-grid about-photo-left">
        <?php live_photo_box([
            'id' => 'aboutCarouselBox',
            'fields' => ['photo', 'photo_2', 'photo_3'],
            'photos' => array_map(fn($p) => $p ? img($p) : '', $aboutPhotos),
            'preview' => 'rotate',
            'emptyLabel' => 'Upload multiple photos',
        ]); ?>
        <div data-field="body" contenteditable="true" data-placeholder="Tell families about your school..."><?= nl2p_live($content['body'] ?? '') ?></div>
      </div>

    <?php elseif ($aboutVariant === 'list'): ?>
      <?php if (!empty($content['body'])): ?><div class="about-list-intro" data-field="body" contenteditable="true"><?= nl2p_live($content['body']) ?></div><?php else: ?><div class="about-list-intro" data-field="body" contenteditable="true" data-placeholder="Optional intro line..."></div><?php endif; ?>
      <ul class="about-list" id="liveListItems" data-field="list_items">
        <?php foreach ($listItems as $item): ?><li class="live-list-item"><span contenteditable="true"><?= esc($item) ?></span><button type="button" class="live-list-remove" onclick="somahubRemoveListItem(this)">&times;</button></li><?php endforeach; ?>
      </ul>
      <button type="button" class="live-list-add" onclick="somahubAddListItem()">+ Add point</button>

    <?php elseif ($aboutVariant === 'timeline'): ?>
      <?php if (!empty($content['body'])): ?><div class="about-list-intro" data-field="body" contenteditable="true"><?= nl2p_live($content['body']) ?></div><?php else: ?><div class="about-list-intro" data-field="body" contenteditable="true" data-placeholder="Optional intro line..."></div><?php endif; ?>
      <div class="about-timeline" id="liveListItems" data-field="list_items" data-item-tag="div" data-item-class="about-timeline-item">
        <?php foreach ($listItems as $item): ?><div class="about-timeline-item live-list-item"><span contenteditable="true"><?= esc($item) ?></span><button type="button" class="live-list-remove" onclick="somahubRemoveListItem(this)">&times;</button></div><?php endforeach; ?>
      </div>
      <button type="button" class="live-list-add" onclick="somahubAddListItem()">+ Add milestone</button>

    <?php elseif ($aboutVariant === 'quote'): ?>
      <div class="about-quote-grid">
        <?php live_photo_block('photo', $aboutPhotos['photo'] ? img($aboutPhotos['photo']) : '', 'about-quote-photo'); ?>
        <div class="about-quote-text">
          <div class="about-quote-mark">&ldquo;</div>
          <div data-field="body" contenteditable="true" data-placeholder="A short, warm quote about your school..."><?= nl2p_live($content['body'] ?? '') ?></div>
          <div class="about-quote-author">— <span data-field="author_name" contenteditable="true" data-placeholder="Author name"><?= esc($content['author_name'] ?? '') ?></span></div>
        </div>
      </div>

    <?php elseif ($aboutVariant === 'stats_inline'): ?>
      <div class="about-grid">
        <div>
          <div data-field="body" contenteditable="true" data-placeholder="Tell families about your school..."><?= nl2p_live($content['body'] ?? '') ?></div>
          <div class="about-inline-stats">
            <div class="about-inline-stat"><span class="num" data-field="inline_stat_1" contenteditable="true" data-placeholder="15+"><?= esc($content['inline_stat_1'] ?? '') ?></span><span class="label" data-field="inline_stat_1_label" contenteditable="true" data-placeholder="Years running"><?= esc($content['inline_stat_1_label'] ?? '') ?></span></div>
            <div class="about-inline-stat"><span class="num" data-field="inline_stat_2" contenteditable="true" data-placeholder="500+"><?= esc($content['inline_stat_2'] ?? '') ?></span><span class="label" data-field="inline_stat_2_label" contenteditable="true" data-placeholder="Students"><?= esc($content['inline_stat_2_label'] ?? '') ?></span></div>
          </div>
        </div>
        <?php live_photo_block('photo', $aboutPhotos['photo'] ? img($aboutPhotos['photo']) : ''); ?>
      </div>

    <?php else: /* photo_right - default */ ?>
      <div class="about-grid">
        <div data-field="body" contenteditable="true" data-placeholder="Tell families about your school..."><?= nl2p_live($content['body'] ?? '') ?></div>
        <?php live_photo_block('photo', $aboutPhotos['photo'] ? img($aboutPhotos['photo']) : ''); ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php render_live_edit_script((int)$section['id'], $aboutVariant); ?>
</body>
</html>
