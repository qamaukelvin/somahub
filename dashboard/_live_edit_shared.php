<?php
// Shared machinery for every section's live, in-place editor (sections.php's
// "Edit live" button). One copy of the photo upload/delete/multi-photo-grid
// system, the variant/height switchers, the font picker, and the save toast
// - each section's own file (section-live-edit-<key>.php) only needs to
// supply its particular markup and call these.
//
// NOTE: this file is included by multiple live-edit pages in the same
// request lifecycle only once each (each page is its own HTTP request), so
// it's safe for it to declare top-level functions.

if (!function_exists('esc')) {
    function esc($text) { return htmlspecialchars($text ?? ''); }
}
if (!function_exists('img')) {
    function img($path) { return $path ? '../' . htmlspecialchars($path) : ''; }
}
if (!function_exists('nl2p_live')) {
    function nl2p_live($text) {
        // Plain text + <br> rather than separate <p> tags, so the whole
        // block stays one simple contenteditable region (a nested
        // contenteditable would freeze that paragraph and defeat the
        // point of editing in place).
        return nl2br(esc($text ?? ''));
    }
}

/**
 * One photo slot with its hover replace-overlay and corner delete button
 * baked in. Every variant that shows a single image uses this instead of a
 * bare <img>, so clicking the photo on the live canvas is how you change it.
 */
function live_photo_block(string $field, string $src, string $wrapClass = 'about-photo'): void { ?>
  <div class="<?= esc($wrapClass) ?> live-photo-wrap<?= $src ? '' : ' empty' ?>" data-photo-field="<?= esc($field) ?>">
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

/**
 * A box that holds 2-3 photo slots as one editable unit: a live preview
 * (either a rotating carousel or a static mosaic, matching however the
 * live site actually renders that variant) by default, and on hover a
 * balanced grid of every photo with its own replace/delete plus
 * Replace-all/Remove-all for the whole set. Used by About's "carousel"
 * variant and Hero's "carousel"/mosaic (split, split_cta) variants.
 *
 * $opts:
 *   id        - unique id for the box element
 *   fields    - ordered field names for the slots, e.g. ['hero_photo','hero_photo_2','hero_photo_3']
 *   photos    - assoc field => already-img()-resolved src (or '' if empty)
 *   preview   - 'rotate' (About carousel, Hero carousel) or 'mosaic' (Hero split/split_cta)
 *   mosaicSingleClass - wrap class for the lone-photo case in mosaic mode (e.g. 'hero-photo')
 *   mosaicClass        - wrap class for the 2+ photo mosaic grid (e.g. 'hero-mosaic')
 *   emptyLabel - CTA text for the empty state
 */
function live_photo_box(array $opts): void {
    $id = $opts['id'];
    $fields = $opts['fields'];
    $photos = $opts['photos'];
    $preview = $opts['preview'] ?? 'rotate';
    $mosaicSingleClass = $opts['mosaicSingleClass'] ?? 'about-photo';
    $mosaicClass = $opts['mosaicClass'] ?? 'hero-mosaic';
    $emptyLabel = $opts['emptyLabel'] ?? 'Upload multiple photos';

    $filled = array_values(array_filter($fields, fn($f) => !empty($photos[$f])));
    $nextEmpty = null;
    foreach ($fields as $f) { if (empty($photos[$f])) { $nextEmpty = $f; break; } }
    $gridCellCount = count($filled) + ($nextEmpty ? 1 : 0);
    $lastSpansFull = $gridCellCount % 2 === 1;
    $fieldsCsv = implode(',', $fields);
    ?>
    <div class="live-photo-box" id="<?= esc($id) ?>" data-fields="<?= esc($fieldsCsv) ?>">
      <?php if (empty($filled)): ?>
        <div class="carousel-empty-cta" onclick="somahubTriggerBulkUpload(this)">
          <span class="material-symbols-outlined">add_photo_alternate</span>
          <span class="cta-btn"><?= esc($emptyLabel) ?></span>
        </div>
      <?php else: ?>
        <?php if ($preview === 'rotate'): ?>
          <div class="carousel-rotator">
            <?php foreach ($filled as $i => $f): ?>
              <div class="carousel-slide<?= $i === 0 ? ' active' : '' ?>"><img src="<?= esc($photos[$f]) ?>" alt=""></div>
            <?php endforeach; ?>
          </div>
        <?php elseif (count($filled) === 1): ?>
          <div class="<?= esc($mosaicSingleClass) ?>"><img src="<?= esc($photos[$filled[0]]) ?>" alt=""></div>
        <?php else: ?>
          <div class="<?= esc($mosaicClass) ?><?= count($filled) === 2 ? ' two-photos' : '' ?>">
            <img class="m-main" src="<?= esc($photos[$filled[0]]) ?>" alt="">
            <?php foreach (array_slice($filled, 1) as $f): ?><img src="<?= esc($photos[$f]) ?>" alt=""><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="carousel-hover-grid">
          <div class="carousel-grid-toolbar">
            <button type="button" class="mini-btn" onclick="somahubTriggerBulkUpload(this)">Replace all</button>
            <button type="button" class="mini-btn danger" onclick="somahubRemoveAllPhotos(this)">Remove all</button>
          </div>
          <div class="carousel-grid-items">
            <?php foreach ($filled as $i => $f):
                $isLast = ($i === count($filled) - 1) && !$nextEmpty;
            ?>
              <?php live_photo_block($f, $photos[$f], 'carousel-grid-slot' . ($isLast && $lastSpansFull ? ' span-full' : '')); ?>
            <?php endforeach; ?>
            <?php if ($nextEmpty): ?>
              <div class="add-tile<?= $lastSpansFull ? ' span-full' : '' ?>" onclick="somahubTriggerPhotoUpload('<?= esc($nextEmpty) ?>', true)">
                <span class="material-symbols-outlined">add</span>
                <span style="font-size:0.72rem;font-weight:600;">Add photo</span>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
<?php }

/**
 * The <head> styles shared by every live-edit page: theme variables, the
 * editor chrome (topbar, font picker, save toast, variant/size switchers),
 * and the generic photo-editing CSS (single slot + multi-photo box). Pass
 * $extraCss for whatever's unique to that section (About's list/quote
 * styles, Hero's mosaic/bgfixed/carousel treatments, etc.) - it's injected
 * right after the shared rules so section CSS can still override by being
 * more specific if it ever needs to.
 */
function render_live_edit_head(array $theme, string $extraCss = ''): void { ?>
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

  .size-switcher{display:flex;gap:6px;align-items:center;padding:8px 20px;background:#EFEADA;border-bottom:1px solid #E2DCC6;font-size:0.78rem;}
  .size-switcher .size-label{color:#777;font-weight:600;margin-right:4px;}
  .size-chip{border:1.5px solid #ccc;border-radius:16px;padding:5px 12px;font-size:0.76rem;font-weight:600;cursor:pointer;background:#fff;color:#333;}
  .size-chip.active{border-color:var(--primary);background:var(--primary);color:#fff;}

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

  /* Multi-photo box (About's carousel, Hero's carousel/mosaic): a live
     preview by default; hover replaces it outright (opacity:0, not just
     covered, so the old photo never shows through) with a solid-background
     grid of every photo, each individually editable, plus
     Replace-all/Remove-all for the whole set. */
  .live-photo-box{position:relative;border-radius:10px;overflow:hidden;height:340px;background:#e9e5d6;}
  .live-photo-box > .carousel-rotator,
  .live-photo-box > .hero-mosaic,
  .live-photo-box > .hero-photo,
  .live-photo-box > .about-photo{position:absolute;inset:0;height:100%;opacity:1;transition:opacity .15s;}
  .live-photo-box:hover > .carousel-rotator,
  .live-photo-box:hover > .hero-mosaic,
  .live-photo-box:hover > .hero-photo,
  .live-photo-box:hover > .about-photo{opacity:0;}
  .live-photo-box > .hero-photo img,
  .live-photo-box > .about-photo img{width:100%;height:100%;object-fit:cover;display:block;}
  .carousel-empty-cta{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;cursor:pointer;color:#555;}
  .carousel-empty-cta .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:40px;color:#999;}
  .carousel-empty-cta span.cta-btn{background:var(--primary);color:#fff;border-radius:6px;padding:9px 18px;font-size:0.82rem;font-weight:700;}
  .carousel-rotator{position:absolute;inset:0;}
  .carousel-slide{position:absolute;inset:0;opacity:0;transition:opacity 1s ease;}
  .carousel-slide.active{opacity:1;}
  .carousel-slide img{width:100%;height:100%;object-fit:cover;display:block;}

  .carousel-hover-grid{position:absolute;inset:0;background:#20201a;opacity:0;pointer-events:none;transition:opacity .15s;display:flex;flex-direction:column;padding:14px;}
  .live-photo-box:hover .carousel-hover-grid{opacity:1;pointer-events:auto;}
  .carousel-grid-toolbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
  .carousel-grid-toolbar .mini-btn{border:1.5px solid rgba(255,255,255,0.5);background:rgba(255,255,255,0.08);color:#fff;border-radius:6px;padding:6px 11px;font-size:0.74rem;font-weight:600;cursor:pointer;}
  .carousel-grid-toolbar .mini-btn:hover{background:rgba(255,255,255,0.2);}
  .carousel-grid-toolbar .mini-btn.danger{border-color:rgba(244,160,160,0.6);color:#F4A0A0;}

  .carousel-grid-items{flex:1;min-height:0;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);grid-auto-rows:minmax(0,1fr);gap:10px;margin-top:12px;}
  .carousel-grid-items .live-photo-wrap{overflow:hidden;}
  .carousel-grid-items .live-photo-wrap > img{position:absolute;inset:0;width:100%;height:100%;max-height:none;object-fit:cover;display:block;}
  .carousel-grid-items .live-photo-wrap{border-radius:6px;min-height:0;height:100%;}
  .carousel-grid-items .live-photo-wrap.empty{background:rgba(255,255,255,0.1);min-height:0;}
  .carousel-grid-items .live-photo-wrap.span-full{grid-column:1 / -1;}
  .carousel-grid-items .add-tile{border:1.5px dashed rgba(255,255,255,0.4);border-radius:6px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;cursor:pointer;color:#D8D4C4;min-height:0;}
  .carousel-grid-items .add-tile:hover{background:rgba(255,255,255,0.08);border-color:rgba(255,255,255,0.7);}
  .carousel-grid-items .add-tile .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:26px;}
  .carousel-grid-items .add-tile.span-full{grid-column:1 / -1;}
  .carousel-grid-items .live-photo-wrap .photo-overlay-btn{padding:5px 8px;font-size:0.68rem;}
  .carousel-grid-items .live-photo-wrap .photo-delete-btn{width:22px;height:22px;top:4px;right:4px;}
  .carousel-grid-items .live-photo-wrap .photo-delete-btn .material-symbols-outlined{font-size:13px;}

  .live-list-item{display:flex;align-items:flex-start;gap:8px;}
  .live-list-item [contenteditable]{flex:1;}
  .live-list-remove{border:none;background:none;color:#b33;cursor:pointer;font-size:1rem;line-height:1;padding:10px 4px;}
  .live-list-add{border:1.5px dashed #999;background:none;border-radius:6px;padding:8px 14px;font-size:0.8rem;font-weight:600;color:#555;cursor:pointer;margin-top:6px;}

  <?= $extraCss ?>
</style>
<?php }

/**
 * Topbar: back link, section label, autosave note, font picker. Identical
 * on every live-edit page.
 */
function render_live_edit_topbar(array $section, array $fontPairs, string $currentFontPair): void { ?>
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
<?php }

/**
 * The variant-switcher chip row, generic off the section_variants registry
 * entry. $photoCount is however the caller wants "needs N more photos"
 * computed (usually count_section_photos() against the registry's
 * photo_fields).
 */
function render_variant_switcher(array $config, string $currentVariant, int $photoCount): void { ?>
<div class="variant-switcher" id="variantSwitcher">
  <?php foreach ($config['options'] as $value => $opt):
      $short = max(0, $opt['photos_needed'] - $photoCount);
  ?>
    <button type="button" class="variant-chip<?= $currentVariant === $value ? ' active' : '' ?>"
            data-variant="<?= esc($value) ?>"
            onclick="somahubSwitchVariant('<?= esc($value) ?>')">
      <span class="material-symbols-outlined"><?= esc($opt['icon']) ?></span>
      <?= esc($opt['label']) ?>
      <?php if ($short > 0): ?><span class="needs-photo-badge">+<?= $short ?> photo<?= $short > 1 ? 's' : '' ?></span><?php endif; ?>
    </button>
  <?php endforeach; ?>
</div>
<?php }

/**
 * Height row (Hero's full/half/auto) - only rendered when the registry
 * entry has has_size.
 */
function render_size_switcher(string $currentSize): void {
    $sizeOptions = ['auto' => 'Fits content', 'half' => 'Half page', 'full' => 'Full page'];
    ?>
<div class="size-switcher">
  <span class="size-label">Height:</span>
  <?php foreach ($sizeOptions as $value => $label): ?>
    <button type="button" class="size-chip<?= $currentSize === $value ? ' active' : '' ?>" onclick="somahubSetSize('<?= esc($value) ?>')"><?= esc($label) ?></button>
  <?php endforeach; ?>
</div>
<?php }
