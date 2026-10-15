<?php
// Live, in-place editor for Gallery - same pattern as About/Hero. Every
// photo is a tile you can replace/remove right where it sits; "Add photo"
// and "Add several" fill the next empty slots (max 10). Tabs shows each
// tile's category inline. Before/After and Slideshow use dedicated layouts.
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
if (!$section || $section['key_name'] !== 'gallery') {
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

$config = get_section_variant_registry()['gallery'];
$content = json_decode($section['content_json'], true) ?: [];
$schema = json_decode($section['schema_json'], true) ?: [];
$hasCaption = array_key_exists('caption', $schema);
$variant = $section['layout_variant'] ?? 'grid';
$MAX = 10;

$allFields = [];
$photos = [];       // field => resolved src ('' when empty)
$filled = [];       // fields with a photo, in order
$emptyFields = [];
for ($i = 1; $i <= $MAX; $i++) {
    $f = "photo_$i";
    $allFields[] = $f;
    $photos[$f] = !empty($content[$f]) ? img($content[$f]) : '';
    if ($photos[$f]) $filled[] = $f; else $emptyFields[] = $f;
}
$photoCount = count($filled);
$nextEmpty = $emptyFields[0] ?? null;

/** One gallery tile: photo with replace/delete, optional inline category. */
function gallery_tile(string $field, string $src, bool $withCategory, array $content, string $cls = 'g-tile'): void {
    live_photo_block($field, $src, $cls);
    if ($withCategory) {
        $n = (int)substr($field, 6);
        echo '<div class="g-cat"><span class="material-symbols-outlined">sell</span><span data-field="category_' . $n . '" contenteditable="true" data-placeholder="Category">' . esc($content["category_$n"] ?? '') . '</span></div>';
    }
}
function gallery_add_tile(?string $nextEmpty, string $cls = 'g-tile'): void {
    if (!$nextEmpty) return; ?>
  <div class="<?= esc($cls) ?> g-add" onclick="somahubTriggerPhotoUpload('<?= esc($nextEmpty) ?>', true)">
    <span class="material-symbols-outlined">add_photo_alternate</span><span>Add photo</span>
  </div>
<?php }

$extraCss = <<<CSS
  section#gallery{background:var(--bg);padding:60px 0;min-height:70vh;}
  .gallery-head-caption{color:#5a5a52;margin-top:6px;}
  .g-toolbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center;}
  .g-toolbar .g-btn{border:1.5px solid rgba(0,0,0,.2);background:#fff;color:#1C1C16;border-radius:8px;padding:7px 13px;font-size:.78rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;}
  .g-toolbar .g-btn:hover{background:#f3efe2;}
  .g-toolbar .g-btn.danger{color:#B3261E;border-color:rgba(179,38,30,.4);}
  .g-toolbar .g-btn .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:18px;}
  .g-toolbar .g-count{font-size:.75rem;color:#6b6b60;}
  .gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;}
  .gallery-grid .g-tile{aspect-ratio:4/3;border-radius:8px;overflow:hidden;}
  .g-tile img{width:100%;height:100%;object-fit:cover;display:block;}
  .g-tile.empty{min-height:0;}
  .g-add{border:2px dashed rgba(0,0,0,.25);color:#6b6b60;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;cursor:pointer;font-size:.8rem;font-weight:700;border-radius:8px;min-height:120px;background:rgba(255,255,255,.4);}
  .g-add:hover{background:rgba(255,255,255,.8);border-color:var(--primary);color:var(--primary);}
  .g-add .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:30px;}
  .gallery-masonry{columns:3 220px;column-gap:14px;}
  .gallery-masonry .g-tile,.gallery-masonry .g-add{break-inside:avoid;margin-bottom:14px;display:block;border-radius:8px;overflow:hidden;}
  .gallery-masonry .g-tile img{height:auto;}
  .gallery-masonry .g-add{display:flex;min-height:150px;}
  .g-cat{display:flex;align-items:center;gap:5px;margin-top:6px;font-size:.8rem;color:#4A4A42;}
  .g-cat .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:15px;color:var(--accent);}
  .g-cat span[contenteditable]{flex:1;}
  .g-cell{display:flex;flex-direction:column;}
  .g-note{font-size:.8rem;color:#6b6b60;margin:0 0 14px;display:flex;gap:6px;align-items:center;}
  .g-note .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;}
  .gallery-slideshow-box.live-photo-box{height:auto;aspect-ratio:16/9;max-height:520px;}
  .ba-live{display:grid;grid-template-columns:1fr 1fr;gap:14px;max-width:900px;margin:0 auto;}
  .ba-live .ba-slot{position:relative;}
  .ba-live .g-tile{aspect-ratio:4/3;border-radius:10px;overflow:hidden;}
  .ba-live .ba-label{position:absolute;top:10px;left:10px;z-index:3;background:rgba(0,0,0,.7);color:#fff;padding:4px 12px;border-radius:14px;font-size:.74rem;font-weight:700;pointer-events:none;}
  .g-tabs-preview{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;}
  .g-tabs-preview span{border:1.5px solid rgba(0,0,0,.15);background:#fff;padding:7px 16px;border-radius:20px;font-size:.82rem;font-weight:600;}
  .g-tabs-preview span:first-child{background:var(--primary);color:#fff;border-color:var(--primary);}
CSS;
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
<?php render_variant_switcher($config, $variant, $photoCount); ?>

<div class="save-toast" id="saveToast"><span class="material-symbols-outlined">check_circle</span><span id="saveToastMsg">Saved</span></div>

<section id="gallery">
  <div class="wrap">
    <div class="section-head">
      <h2 style="pointer-events:none;"><?= esc($section['label']) ?></h2>
      <?php if ($hasCaption): ?><p class="gallery-head-caption" data-field="caption" contenteditable="true" data-placeholder="Add a short caption (optional)"><?= esc($content['caption'] ?? '') ?></p><?php endif; ?>
    </div>

    <?php if ($photoCount === 0): ?>
      <div class="g-note"><span class="material-symbols-outlined">info</span>With no photos yet, this section stays hidden on your live site.</div>
    <?php endif; ?>

    <div class="bulk-box" data-fields="<?= esc(implode(',', $allFields)) ?>" data-empty-fields="<?= esc(implode(',', $emptyFields)) ?>">
      <?php if ($variant !== 'before_after' && $variant !== 'slideshow'): ?>
      <div class="g-toolbar">
        <?php if ($nextEmpty): ?><button type="button" class="g-btn" onclick="somahubTriggerBulkUpload(this)"><span class="material-symbols-outlined">upload</span>Add several photos</button><?php endif; ?>
        <?php if ($photoCount): ?><button type="button" class="g-btn danger" onclick="somahubRemoveAllPhotos(this)"><span class="material-symbols-outlined">delete_sweep</span>Remove all</button><?php endif; ?>
        <span class="g-count"><?= $photoCount ?> of <?= $MAX ?> photos</span>
      </div>
      <?php endif; ?>

      <?php if ($variant === 'masonry'): ?>
        <div class="gallery-masonry">
          <?php foreach ($filled as $f) gallery_tile($f, $photos[$f], false, $content); ?>
          <?php gallery_add_tile($nextEmpty); ?>
        </div>

      <?php elseif ($variant === 'lightbox'): ?>
        <p class="g-note"><span class="material-symbols-outlined">zoom_in</span>Visitors click a photo to open it full-screen.</p>
        <div class="gallery-grid">
          <?php foreach ($filled as $f) gallery_tile($f, $photos[$f], false, $content); ?>
          <?php gallery_add_tile($nextEmpty); ?>
        </div>

      <?php elseif ($variant === 'slideshow'): ?>
        <?php live_photo_box([
            'id' => 'gallerySlideBox',
            'fields' => $allFields,
            'photos' => $photos,
            'preview' => 'rotate',
            'emptyLabel' => 'Upload multiple photos',
        ]); ?>
        <script>document.getElementById('gallerySlideBox').classList.add('gallery-slideshow-box');</script>

      <?php elseif ($variant === 'before_after'):
          $baFields = array_slice($filled, 0, 2);
          foreach ($emptyFields as $ef) { if (count($baFields) >= 2) break; $baFields[] = $ef; }
      ?>
        <p class="g-note"><span class="material-symbols-outlined">compare</span>Visitors drag a slider to reveal the "After" photo over the "Before" photo.</p>
        <div class="ba-live">
          <?php foreach ($baFields as $k => $f): ?>
            <div class="ba-slot"><span class="ba-label"><?= $k === 0 ? 'Before' : 'After' ?></span><?php live_photo_block($f, $photos[$f], 'g-tile'); ?></div>
          <?php endforeach; ?>
        </div>

      <?php elseif ($variant === 'tabs'):
          $cats = [];
          foreach ($filled as $f) { $n = (int)substr($f, 6); $cat = trim($content["category_$n"] ?? '') ?: 'Other'; if (!in_array($cat, $cats, true)) $cats[] = $cat; }
      ?>
        <p class="g-note"><span class="material-symbols-outlined">sell</span>Type a category under each photo - visitors get a tab for each one.</p>
        <?php if ($cats): ?><div class="g-tabs-preview"><?php foreach ($cats as $cat): ?><span><?= esc($cat) ?></span><?php endforeach; ?></div><?php endif; ?>
        <div class="gallery-grid">
          <?php foreach ($filled as $f): ?><div class="g-cell"><?php gallery_tile($f, $photos[$f], true, $content); ?></div><?php endforeach; ?>
          <?php gallery_add_tile($nextEmpty); ?>
        </div>

      <?php else: /* grid */ ?>
        <div class="gallery-grid">
          <?php foreach ($filled as $f) gallery_tile($f, $photos[$f], false, $content); ?>
          <?php gallery_add_tile($nextEmpty); ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php render_live_edit_script((int)$section['id'], $variant); ?>
</body>
</html>
