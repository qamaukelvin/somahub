<?php
// Live, in-place editor: renders the section the way it actually looks on
// the live site (same markup/CSS, real school colors) and lets the school
// edit text/photos/layout directly on top of it, instead of switching
// between a plain-text Edit form and a separate Design picker.
//
// Proof of concept for the About section only - if this lands well, the
// same pattern (render real markup + contenteditable/upload overlays +
// floating variant switcher) rolls out to the other 14 section types.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/section_variants.php';
require_once __DIR__ . '/../includes/appearance.php';
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

function img($path) { return $path ? '../' . htmlspecialchars($path) : ''; }
function esc($text) { return htmlspecialchars($text ?? ''); }
function nl2p_live($text) {
    // Plain text + <br> rather than separate <p> tags, so the whole block
    // stays one simple contenteditable region (a nested contenteditable
    // would freeze that paragraph and defeat the point of editing in place).
    return nl2br(esc($text ?? ''));
}

$aboutVariant = $section['layout_variant'] ?? 'photo_right';
$aboutPhotos = [
    'photo' => $content['photo'] ?? '',
    'photo_2' => $content['photo_2'] ?? '',
    'photo_3' => $content['photo_3'] ?? '',
];
$photoCount = count(array_filter($aboutPhotos));
$listItems = array_values(array_filter(array_map('trim', explode("\n", $content['list_items'] ?? ''))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editing: <?= esc($section['label']) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?= urlencode($theme['font_display'] ?? 'Sora') ?>:wght@400;700;800&family=<?= urlencode($theme['font_body'] ?? 'Nunito Sans') ?>:wght@400;600;700&display=swap">
<style>
  :root{
    --primary: <?= esc($theme['primary'] ?? '#1B4D3E') ?>;
    --accent: <?= esc($theme['accent'] ?? '#F2B705') ?>;
    --bg: <?= esc($theme['bg'] ?? '#FBF8F2') ?>;
  }
  *{box-sizing:border-box;}
  body{font-family:'<?= esc($theme['font_body'] ?? 'Nunito Sans') ?>',sans-serif;color:#1B1B18;background:#DCD8C8;line-height:1.65;margin:0;}
  h1,h2,h3{font-family:'<?= esc($theme['font_display'] ?? 'Sora') ?>',sans-serif;letter-spacing:-0.01em;}
  .wrap{max-width:1080px;margin:0 auto;padding:0 24px;}
  .section-head{margin-bottom:32px;max-width:60ch;}
  .section-head h2{font-size:clamp(1.4rem,3.2vw,2rem);font-weight:700;color:var(--primary);}
  .btn-primary{background:var(--primary);color:var(--bg);padding:13px 28px;border-radius:6px;font-weight:600;font-size:0.9rem;display:inline-block;border:none;cursor:pointer;}

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
  .about-photo-left .about-photo{order:-1;}
  @media(max-width:820px){.about-photo-left .about-photo{order:0;}}
  .about-text-only{max-width:760px;color:#3a3a34;}
  .about-text-only p{margin-bottom:14px;}
  .about-list-intro{max-width:760px;color:#3a3a34;margin-bottom:20px;}
  .about-list{max-width:760px;list-style:none;padding:0;}
  .about-list li{position:relative;padding:12px 0 12px 30px;border-bottom:1px solid rgba(0,0,0,0.08);color:#3a3a34;}
  .about-list li::before{content:"✓";position:absolute;left:0;top:12px;color:var(--accent);font-weight:800;}
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

  .about-carousel{position:relative;border-radius:10px;overflow:hidden;height:340px;display:flex;gap:6px;background:#e9e5d6;}
  .about-carousel .live-photo-wrap{flex:1;height:100%;border-radius:0;}
  .about-carousel .live-photo-wrap img{height:100%;}
  .about-carousel .live-photo-wrap.empty{min-height:0;}

  section#about{background:var(--bg);padding:60px 0;min-height:100vh;}

  /* --- editor chrome - none of this exists on the live site --- */
  .live-editor-topbar{position:sticky;top:0;z-index:50;background:#1C1C16;color:#fff;padding:10px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
  .live-editor-topbar a.back-link{color:#fff;text-decoration:none;font-size:0.85rem;opacity:0.85;display:flex;align-items:center;gap:4px;}
  .live-editor-topbar a.back-link:hover{opacity:1;}
  .live-editor-topbar .title{font-weight:700;font-size:0.9rem;margin-right:auto;}
  .autosave-note{font-size:0.72rem;color:#C9C4B0;display:flex;align-items:center;gap:4px;}
  .autosave-note .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:15px;}

  .font-picker{position:relative;}
  .font-picker-btn{background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.25);color:#fff;border-radius:6px;padding:6px 12px;font-size:0.8rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px;}
  .font-picker-panel{display:none;position:absolute;top:calc(100% + 8px);right:0;background:#fff;color:#1C1C16;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,0.25);padding:8px;min-width:230px;z-index:60;}
  .font-picker-panel.open{display:block;}
  .font-pair-option{display:block;width:100%;text-align:left;padding:9px 10px;border-radius:6px;border:none;background:none;cursor:pointer;font-size:0.82rem;}
  .font-pair-option:hover{background:#F4F1E6;}
  .font-pair-option.selected{background:#F4F8F6;color:var(--primary);font-weight:700;}

  /* Toast-style save confirmation - fixed position so it's visible
     regardless of scroll, since there's no explicit Save button to click. */
  .save-toast{position:fixed;left:50%;bottom:24px;transform:translate(-50%,16px);background:#1C1C16;color:#fff;padding:11px 20px;border-radius:30px;font-size:0.83rem;font-weight:600;display:flex;align-items:center;gap:8px;box-shadow:0 6px 20px rgba(0,0,0,0.25);opacity:0;pointer-events:none;transition:opacity .2s,transform .2s;z-index:100;}
  .save-toast.show{opacity:1;transform:translate(-50%,0);}
  .save-toast .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:18px;color:#9FE3B0;}
  .save-toast.err .material-symbols-outlined{color:#F4A0A0;}

  .variant-switcher{display:flex;gap:6px;overflow-x:auto;padding:10px 20px;background:#F4F1E6;border-bottom:1px solid #E2DCC6;}
  .variant-chip{display:flex;align-items:center;gap:6px;border:1.5px solid #ccc;border-radius:20px;padding:7px 14px;font-size:0.78rem;font-weight:600;white-space:nowrap;cursor:pointer;background:#fff;color:#333;}
  .variant-chip .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:17px;}
  .variant-chip.active{border-color:var(--primary);background:var(--primary);color:#fff;}
  .variant-chip .needs-photo-badge{background:rgba(0,0,0,0.08);border-radius:10px;padding:1px 7px;font-size:0.68rem;font-weight:700;}
  .variant-chip.active .needs-photo-badge{background:rgba(255,255,255,0.25);}

  .bulk-upload-row{display:flex;align-items:center;gap:8px;padding:6px 20px 12px;background:#F4F1E6;}
  .bulk-upload-btn{border:1.5px dashed #999;background:#fff;border-radius:6px;padding:7px 12px;font-size:0.78rem;font-weight:600;color:#555;cursor:pointer;}

  [contenteditable="true"]{outline:2px dashed transparent;border-radius:3px;transition:outline-color .15s;cursor:text;min-width:20px;}
  [contenteditable="true"]:hover{outline-color:rgba(15,82,87,0.35);}
  [contenteditable="true"]:focus{outline:2px dashed var(--primary);background:rgba(255,255,255,0.5);}
  [contenteditable="true"]:empty::before{content:attr(data-placeholder);color:#999;font-style:italic;}

  .live-photo-wrap{position:relative;}
  .live-photo-wrap .photo-overlay{position:absolute;inset:0;background:rgba(0,0,0,0.0);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .15s,background .15s;cursor:pointer;}
  .live-photo-wrap:hover .photo-overlay{opacity:1;background:rgba(0,0,0,0.35);}
  .live-photo-wrap .photo-overlay-btn{background:#fff;color:#1C1C16;border-radius:6px;padding:8px 14px;font-size:0.78rem;font-weight:700;display:flex;align-items:center;gap:6px;}
  .live-photo-wrap.empty{background:#e9e5d6;min-height:220px;display:flex;align-items:center;justify-content:center;}
  .live-photo-wrap.empty .photo-overlay{opacity:1;position:static;background:none;}
  .live-photo-wrap.empty .photo-overlay-btn{background:var(--primary);color:#fff;}

  .photo-delete-btn{position:absolute;top:8px;right:8px;width:28px;height:28px;border-radius:50%;background:rgba(20,20,16,0.7);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .15s,background .15s;z-index:5;}
  .photo-delete-btn:hover{background:#B3382C;}
  .photo-delete-btn .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;}
  .live-photo-wrap:hover .photo-delete-btn{opacity:1;}
  .live-photo-wrap.empty .photo-delete-btn{display:none;}

  .photo-select-check{position:absolute;top:8px;left:8px;width:22px;height:22px;z-index:6;display:none;accent-color:var(--primary);}
  #carouselSlots.select-mode .photo-select-check{display:block;}
  #carouselSlots.select-mode .photo-overlay,
  #carouselSlots.select-mode .photo-delete-btn{display:none;}
  #carouselSlots.select-mode .live-photo-wrap{cursor:pointer;}
  #carouselSlots.select-mode .live-photo-wrap.picked{outline:3px solid var(--primary);outline-offset:-3px;}

  .bulk-select-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
  .bulk-select-btn{border:1.5px solid #ccc;background:#fff;border-radius:6px;padding:7px 12px;font-size:0.78rem;font-weight:600;color:#555;cursor:pointer;}
  .bulk-select-btn.primary{background:var(--primary);border-color:var(--primary);color:#fff;}
  .bulk-select-btn.danger{border-color:#e0c4bc;color:#8C3B2E;}
  .bulk-select-btn:disabled{opacity:0.4;cursor:not-allowed;}

  .live-list-item{display:flex;align-items:flex-start;gap:8px;}
  .live-list-item [contenteditable]{flex:1;}
  .live-list-remove{border:none;background:none;color:#b33;cursor:pointer;font-size:1rem;line-height:1;padding:10px 4px;}
  .live-list-add{border:1.5px dashed #999;background:none;border-radius:6px;padding:8px 14px;font-size:0.8rem;font-weight:600;color:#555;cursor:pointer;margin-top:6px;}
</style>
</head>
<body>

<div class="live-editor-topbar">
  <a href="sections.php" class="back-link">&larr; Back to sections</a>
  <span class="title"><?= esc($section['label']) ?> — live editor</span>
  <span class="autosave-note"><span class="material-symbols-outlined">cloud_done</span> Changes save automatically</span>

  <div class="font-picker">
    <button type="button" class="font-picker-btn" onclick="document.getElementById('fontPickerPanel').classList.toggle('open')">
      <span style="font-size:1rem;">Aa</span> Font
    </button>
    <div class="font-picker-panel" id="fontPickerPanel">
      <?php foreach ($fontPairs as $pairKey => $pair): ?>
        <button type="button" class="font-pair-option<?= $currentFontPair === $pairKey ? ' selected' : '' ?>"
                style="font-family:'<?= esc($pair['display']) ?>',sans-serif;"
                onclick="somahubSetFontPair('<?= esc($pairKey) ?>')"><?= esc($pair['label']) ?></button>
      <?php endforeach; ?>
      <p style="font-size:0.7rem;color:#888;padding:6px 10px 2px;margin:0;">Applies across your whole site, not just this section.</p>
    </div>
  </div>
</div>

<div class="variant-switcher" id="variantSwitcher">
  <?php foreach ($config['options'] as $value => $opt):
      $needed = $opt['photos_needed'];
      $short = max(0, $needed - $photoCount);
  ?>
    <button type="button" class="variant-chip<?= $aboutVariant === $value ? ' active' : '' ?>"
            data-variant="<?= esc($value) ?>"
            onclick="somahubSwitchVariant('<?= esc($value) ?>')">
      <span class="material-symbols-outlined"><?= esc($opt['icon']) ?></span>
      <?= esc($opt['label']) ?>
      <?php if ($short > 0): ?><span class="needs-photo-badge">+<?= $short ?> photo<?= $short > 1 ? 's' : '' ?></span><?php endif; ?>
    </button>
  <?php endforeach; ?>
</div>

<?php if ($aboutVariant === 'carousel_left'): ?>
  <div class="bulk-upload-row">
    <button type="button" class="bulk-upload-btn" id="bulkUploadBtn" onclick="document.getElementById('bulkPhotoInput').click()">+ Upload several photos at once</button>
    <span style="font-size:0.75rem;color:#888;" id="bulkUploadHint">Fills the next empty slots below, in order.</span>
  </div>
  <div class="bulk-select-row" style="padding:0 20px 14px;background:#F4F1E6;">
    <button type="button" class="bulk-select-btn" id="selectModeBtn" onclick="somahubToggleSelectMode()">Select photos</button>
    <span id="selectModeActions" style="display:none;gap:8px;align-items:center;">
      <button type="button" class="bulk-select-btn primary" id="replaceSelectedBtn" disabled onclick="document.getElementById('replaceSelectedInput').click()">Replace selected</button>
      <button type="button" class="bulk-select-btn danger" id="deleteSelectedBtn" disabled onclick="somahubDeleteSelected()">Delete selected</button>
      <button type="button" class="bulk-select-btn" onclick="somahubToggleSelectMode()">Cancel</button>
      <span id="selectedCountNote" style="font-size:0.75rem;color:#888;"></span>
    </span>
  </div>
<?php endif; ?>

<div class="save-toast" id="saveToast"><span class="material-symbols-outlined">check_circle</span><span id="saveToastMsg">Saved</span></div>

<?php
// A tiny helper that renders one photo slot with its upload/replace overlay
// baked in - every variant below that shows an image uses this instead of
// a bare <img>, so clicking the photo on the live canvas is how you change it.
function live_photo_block(string $field, string $src, string $wrapClass = 'about-photo', bool $selectable = false): void { ?>
  <div class="<?= esc($wrapClass) ?> live-photo-wrap<?= $src ? '' : ' empty' ?>" data-photo-field="<?= esc($field) ?>" <?= $selectable ? 'onclick="somahubToggleSelect(event, this)"' : '' ?>>
    <?php if ($selectable): ?><input type="checkbox" class="photo-select-check" onclick="event.stopPropagation();somahubToggleSelect(event, this.closest('.live-photo-wrap'))"><?php endif; ?>
    <?php if ($src): ?><img src="<?= esc($src) ?>" alt=""><?php endif; ?>
    <?php if ($src): ?>
      <button type="button" class="photo-delete-btn" title="Remove photo" onclick="event.stopPropagation();somahubDeletePhoto('<?= esc($field) ?>')">
        <span class="material-symbols-outlined">close</span>
      </button>
    <?php endif; ?>
    <div class="photo-overlay" onclick="event.stopPropagation();somahubTriggerPhotoUpload('<?= esc($field) ?>')">
      <span class="photo-overlay-btn">
        <span class="material-symbols-outlined" style="font-family:'Material Symbols Outlined';font-size:16px;">photo_camera</span>
        <?= $src ? 'Replace photo' : 'Upload photo' ?>
      </span>
    </div>
  </div>
<?php }
?>

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
        <div class="about-carousel" id="carouselSlots">
          <?php foreach (['photo', 'photo_2', 'photo_3'] as $slot): ?>
            <?php live_photo_block($slot, $aboutPhotos[$slot] ? img($aboutPhotos[$slot]) : '', 'about-carousel-slot', true); ?>
          <?php endforeach; ?>
        </div>
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

<input type="file" id="livePhotoInput" accept="image/*" style="display:none;">
<input type="file" id="bulkPhotoInput" accept="image/*" multiple style="display:none;">
<input type="file" id="replaceSelectedInput" accept="image/*" multiple style="display:none;">

<script>
(function(){
  var sectionId = <?= (int)$section['id'] ?>;
  var pendingField = null;
  var toast = document.getElementById('saveToast');
  var toastMsg = document.getElementById('saveToastMsg');

  function showSaved(ok, msg) {
    toastMsg.textContent = msg || (ok ? 'Saved' : 'Could not save');
    toast.classList.toggle('err', !ok);
    toast.querySelector('.material-symbols-outlined').textContent = ok ? 'check_circle' : 'error';
    toast.classList.add('show');
    clearTimeout(showSaved._t);
    showSaved._t = setTimeout(function(){ toast.classList.remove('show'); }, 2600);
  }

  // A notice can be queued just before a reload (e.g. after switching
  // layout) so it still shows up once the fresh page has loaded.
  var queuedNotice = sessionStorage.getItem('somahubLiveEditNotice');
  if (queuedNotice) {
    sessionStorage.removeItem('somahubLiveEditNotice');
    setTimeout(function(){ showSaved(true, queuedNotice); }, 300);
  }

  function saveField(field, value) {
    var fd = new FormData();
    fd.append('section_id', sectionId);
    fd.append(field, value);
    fetch('section-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){ showSaved(!!data.ok, data.ok ? 'Saved' : (Object.values(data.errors||{})[0] || 'Could not save')); })
      .catch(function(){ showSaved(false); });
  }

  // Plain contenteditable fields (body, author_name, stat numbers/labels) -
  // save on blur, not on every keystroke, same as a normal form field would.
  document.querySelectorAll('[contenteditable="true"][data-field]').forEach(function(el){
    if (el.dataset.field === '_label_readonly') return;
    el.addEventListener('blur', function(){
      var value = el.innerText.replace(/\n{3,}/g, '\n\n').trim();
      saveField(el.dataset.field, value);
    });
  });

  // List-based fields (About's "list" and "timeline" variants) - every item
  // is one line of the underlying list_items textarea, newline-joined.
  var listWrap = document.getElementById('liveListItems');
  function serializeList() {
    if (!listWrap) return;
    var lines = Array.prototype.map.call(listWrap.querySelectorAll('.live-list-item span'), function(span){
      return span.innerText.trim();
    }).filter(function(line){ return line.length > 0; });
    saveField('list_items', lines.join('\n'));
  }
  if (listWrap) {
    listWrap.addEventListener('focusout', function(e){
      if (e.target.matches('span[contenteditable]')) serializeList();
    });
  }
  window.somahubRemoveListItem = function(btn) {
    btn.closest('.live-list-item').remove();
    serializeList();
  };
  window.somahubAddListItem = function() {
    if (!listWrap) return;
    var tag = listWrap.dataset.itemTag || 'li';
    var extraClass = listWrap.dataset.itemClass || '';
    var item = document.createElement(tag);
    item.className = ('live-list-item ' + extraClass).trim();
    var span = document.createElement('span');
    span.setAttribute('contenteditable', 'true');
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'live-list-remove';
    btn.innerHTML = '&times;';
    btn.onclick = function(){ somahubRemoveListItem(btn); };
    item.appendChild(span);
    item.appendChild(btn);
    listWrap.appendChild(item);
    span.addEventListener('focusout', serializeList);
    span.focus();
  };

  // Single photo upload (used by every photo slot, including each carousel
  // slot) - returns a Promise so the bulk-upload flow below can chain calls.
  function uploadPhoto(field, file) {
    var fd = new FormData();
    fd.append('section_id', sectionId);
    fd.append(field, file);
    var wrap = document.querySelector('.live-photo-wrap[data-photo-field="' + field + '"]');
    return fetch('section-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok && data.content && data.content[field]) {
          var newSrc = '../' + data.content[field] + '?t=' + Date.now();
          var img = wrap.querySelector('img');
          if (!img) { img = document.createElement('img'); wrap.insertBefore(img, wrap.firstChild); }
          img.src = newSrc;
          wrap.classList.remove('empty');
          return true;
        }
        showSaved(false, (data.errors && Object.values(data.errors)[0]) || 'Upload failed');
        return false;
      })
      .catch(function(){ showSaved(false); return false; });
  }

  // Click-to-replace on a single photo slot.
  var fileInput = document.getElementById('livePhotoInput');
  window.somahubTriggerPhotoUpload = function(field) {
    pendingField = field;
    fileInput.click();
  };
  fileInput.addEventListener('change', function(){
    if (!fileInput.files.length || !pendingField) return;
    uploadPhoto(pendingField, fileInput.files[0]).then(function(ok){ showSaved(ok, ok ? 'Photo updated' : undefined); });
    fileInput.value = '';
  });

  // Bulk upload (currently the carousel layout) - pick several photos at
  // once, they fill the empty photo/photo_2/photo_3 slots in order.
  var bulkInput = document.getElementById('bulkPhotoInput');
  bulkInput.addEventListener('change', function(){
    if (!bulkInput.files.length) return;
    var emptySlots = Array.prototype.filter.call(
      document.querySelectorAll('#carouselSlots .live-photo-wrap'),
      function(w){ return w.classList.contains('empty'); }
    ).map(function(w){ return w.dataset.photoField; });

    var files = Array.prototype.slice.call(bulkInput.files);
    var chain = Promise.resolve();
    var filled = 0;
    files.forEach(function(file, i){
      if (i >= emptySlots.length) return;
      chain = chain.then(function(){ return uploadPhoto(emptySlots[i], file).then(function(ok){ if (ok) filled++; }); });
    });
    chain.then(function(){ showSaved(filled > 0, filled > 0 ? (filled + ' photo' + (filled > 1 ? 's' : '') + ' added') : 'Could not add photos'); });
    bulkInput.value = '';
  });

  // Delete a single photo - the little x in the corner of any filled slot.
  // Reuses section-save-ajax.php's existing remove_<field> flag.
  window.somahubDeletePhoto = function(field) {
    if (!window.confirm('Remove this photo?')) return;
    var fd = new FormData();
    fd.append('section_id', sectionId);
    fd.append('remove_' + field, '1');
    var wrap = document.querySelector('.live-photo-wrap[data-photo-field="' + field + '"]');
    fetch('section-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok) {
          var img = wrap.querySelector('img');
          if (img) img.remove();
          wrap.classList.add('empty');
          wrap.classList.remove('picked');
          var check = wrap.querySelector('.photo-select-check');
          if (check) check.checked = false;
          showSaved(true, 'Photo removed');
        } else {
          showSaved(false, (data.errors && Object.values(data.errors)[0]) || 'Could not remove photo');
        }
      })
      .catch(function(){ showSaved(false); });
  };

  // Select mode for the carousel - pick several existing photos at once,
  // then either replace all of them or delete all of them in one go.
  var selectedFields = [];
  function updateSelectActionState() {
    var n = selectedFields.length;
    document.getElementById('replaceSelectedBtn').disabled = n === 0;
    document.getElementById('deleteSelectedBtn').disabled = n === 0;
    document.getElementById('selectedCountNote').textContent = n > 0 ? (n + ' selected') : '';
  }
  window.somahubToggleSelect = function(evt, wrap) {
    if (!document.getElementById('carouselSlots').classList.contains('select-mode')) return;
    var field = wrap.dataset.photoField;
    var check = wrap.querySelector('.photo-select-check');
    var picked = wrap.classList.toggle('picked');
    if (check) check.checked = picked;
    if (picked) {
      if (selectedFields.indexOf(field) === -1) selectedFields.push(field);
    } else {
      selectedFields = selectedFields.filter(function(f){ return f !== field; });
    }
    updateSelectActionState();
  };
  window.somahubToggleSelectMode = function() {
    var slots = document.getElementById('carouselSlots');
    var active = slots.classList.toggle('select-mode');
    document.getElementById('selectModeBtn').style.display = active ? 'none' : '';
    document.getElementById('selectModeActions').style.display = active ? 'inline-flex' : 'none';
    document.getElementById('bulkUploadBtn').style.display = active ? 'none' : '';
    document.getElementById('bulkUploadHint').style.display = active ? 'none' : '';
    if (!active) {
      selectedFields = [];
      slots.querySelectorAll('.live-photo-wrap.picked').forEach(function(w){
        w.classList.remove('picked');
        var check = w.querySelector('.photo-select-check');
        if (check) check.checked = false;
      });
      updateSelectActionState();
    }
  };
  window.somahubDeleteSelected = function() {
    if (!selectedFields.length) return;
    if (!window.confirm('Remove ' + selectedFields.length + ' selected photo' + (selectedFields.length > 1 ? 's' : '') + '?')) return;
    var chain = Promise.resolve();
    var removed = 0;
    selectedFields.slice().forEach(function(field){
      chain = chain.then(function(){
        var fd = new FormData();
        fd.append('section_id', sectionId);
        fd.append('remove_' + field, '1');
        return fetch('section-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(data){
            if (data.ok) {
              var wrap = document.querySelector('.live-photo-wrap[data-photo-field="' + field + '"]');
              var img = wrap.querySelector('img');
              if (img) img.remove();
              wrap.classList.add('empty');
              removed++;
            }
          });
      });
    });
    chain.then(function(){
      showSaved(removed > 0, removed + ' photo' + (removed > 1 ? 's' : '') + ' removed');
      somahubToggleSelectMode();
    });
  };
  var replaceSelectedInput = document.getElementById('replaceSelectedInput');
  replaceSelectedInput.addEventListener('change', function(){
    if (!replaceSelectedInput.files.length || !selectedFields.length) return;
    var fields = selectedFields.slice();
    var files = Array.prototype.slice.call(replaceSelectedInput.files);
    var chain = Promise.resolve();
    var updated = 0;
    files.forEach(function(file, i){
      if (i >= fields.length) return;
      chain = chain.then(function(){ return uploadPhoto(fields[i], file).then(function(ok){ if (ok) updated++; }); });
    });
    chain.then(function(){
      showSaved(updated > 0, updated > 0 ? (updated + ' photo' + (updated > 1 ? 's' : '') + ' replaced') : 'Could not replace photos');
      somahubToggleSelectMode();
    });
    replaceSelectedInput.value = '';
  });

  // Variant switching - same save endpoint the old Design tab used. Always
  // allowed, even without the needed photos yet: the editor shows the empty
  // upload slots right where the photos go, and the public site falls back
  // to the default layout until they're filled in. We reload after a
  // successful switch since a different variant can need UI that isn't on
  // screen yet (e.g. switching into "list" needs the list-items markup,
  // which only this page's PHP render knows how to build).
  window.somahubSwitchVariant = function(variant) {
    var fd = new FormData();
    fd.append('section_id', sectionId);
    fd.append('layout_variant', variant);
    fetch('section-design-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok) {
          sessionStorage.setItem('somahubLiveEditNotice', data.notice || 'Layout updated');
          window.location.reload();
        } else {
          showSaved(false, (data.errors && Object.values(data.errors)[0]) || 'Could not switch layout');
        }
      })
      .catch(function(){ showSaved(false); });
  };

  // Font pairing - site-wide, so this just saves and reloads to pick up the
  // new Google Fonts link and CSS variables.
  window.somahubSetFontPair = function(pairKey) {
    var fd = new FormData();
    fd.append('font_pair', pairKey);
    fetch('font-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok) {
          sessionStorage.setItem('somahubLiveEditNotice', 'Font updated');
          window.location.reload();
        } else {
          showSaved(false, (data.errors && Object.values(data.errors)[0]) || 'Could not change font');
        }
      })
      .catch(function(){ showSaved(false); });
  };

  // Close the font picker panel when clicking outside it.
  document.addEventListener('click', function(e){
    var panel = document.getElementById('fontPickerPanel');
    if (!panel.classList.contains('open')) return;
    if (!e.target.closest('.font-picker')) panel.classList.remove('open');
  });
})();
</script>
</body>
</html>
