<?php
// Live, in-place editor for Testimonials - same pattern as Staff. Each
// testimonial is a card edited where it sits (quote, author, photo, star
// rating). A dashed blank card at the end adds the next one (max 10). An
// entry shows on the live site once it has a quote.
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
if (!$section || $section['key_name'] !== 'testimonials') {
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

$config = get_section_variant_registry()['testimonials'];
$content = json_decode($section['content_json'], true) ?: [];
$variant = $section['layout_variant'] ?? 'cards';
$MAX = 10;

$items = [];
$blank = null;
for ($i = 1; $i <= $MAX; $i++) {
    $has = false;
    foreach (['quote', 'author', 'photo', 'rating'] as $f) { if (!empty($content["{$f}_$i"])) { $has = true; break; } }
    if ($has) $items[] = $i; elseif ($blank === null) $blank = $i;
}
$withQuote = count(array_filter($items, fn($i) => !empty($content["quote_$i"])));
$photoCount = count(array_filter($items, fn($i) => !empty($content["photo_$i"])));

function tm_text(array $c, int $i, string $f, string $tag, string $ph): string {
    return "<$tag data-field=\"{$f}_$i\" contenteditable=\"true\" data-placeholder=\"$ph\">" . esc($c["{$f}_$i"] ?? '') . "</$tag>";
}
function tm_remove(int $i): string {
    return '<button type="button" class="tm-remove" title="Remove this testimonial" onclick="somahubRemoveTestimonial(' . $i . ')"><span class="material-symbols-outlined">close</span></button>';
}
function tm_stars(array $c, int $i): string {
    $r = (int)($c["rating_$i"] ?? 0);
    $o = '<div class="stars tm-stars" data-i="' . $i . '" data-rating="' . $r . '" title="Click a star to set the rating">';
    for ($n = 1; $n <= 5; $n++) $o .= '<button type="button" class="tm-star' . ($n <= $r ? ' on' : '') . '" onclick="somahubSetRating(' . $i . ',' . $n . ')">' . ($n <= $r ? '★' : '☆') . '</button>';
    return $o . '</div>';
}
/** One testimonial. $o: class, stars, photo, remove(bool) */
function tm_card(array $c, int $i, bool $isNew, array $o): void {
    $unq = !$isNew && empty($c["quote_$i"]);
    echo '<div class="' . esc($o['class']) . ' tm-item' . ($isNew ? ' is-new' : '') . '">';
    if (!$isNew) echo tm_remove($i);
    if (!empty($o['stars'])) echo tm_stars($c, $i);
    if (!empty($o['photo'])) {
        $src = !empty($c["photo_$i"]) ? img($c["photo_$i"]) : '';
        live_photo_block("photo_$i", $src, 'tm-photo');
    }
    echo '<div class="quote q-wrap"' . live_color_attr($c, 'quotes') . '>' . tm_text($c, $i, 'quote', 'span', $isNew ? 'Add a testimonial' : 'Quote') . '</div>';
    echo '<div class="author"' . live_color_attr($c, 'authors') . '>' . tm_text($c, $i, 'author', 'span', 'Author') . '</div>';
    if ($unq) echo '<div class="tm-warn" title="Add a quote and this will show on your live site">Needs a quote</div>';
    echo '</div>';
}

$extraCss = <<<CSS
  section#testimonials{background:var(--bg);padding:60px 0;min-height:70vh;}
  .testimonial-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px;align-items:start;}
  .testimonial-card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:10px;padding:24px;}
  .testimonial-card .quote{font-size:.95rem;font-style:italic;color:#3a3a34;margin-bottom:14px;}
  .testimonial-card .author{font-size:.82rem;font-weight:700;color:var(--primary);}
  .q-wrap::before{content:"\\201C";} .q-wrap::after{content:"\\201D";}
  .tm-item{position:relative;}
  .tm-item.is-new{opacity:.7;border:2px dashed rgba(0,0,0,.25);background:rgba(255,255,255,.4);border-radius:10px;}
  .tm-item.is-new:hover{opacity:1;border-color:var(--primary);}
  .tm-remove{position:absolute;top:-8px;right:-8px;z-index:6;width:24px;height:24px;border-radius:50%;border:2px solid #fff;background:#B3261E;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;opacity:0;transition:opacity .15s;}
  .tm-remove .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:14px;}
  .tm-item:hover .tm-remove{opacity:1;}
  .tm-warn{display:inline-block;margin-top:8px;font-size:.68rem;font-weight:700;color:#8a5a00;background:rgba(244,163,64,.25);border-radius:10px;padding:2px 9px;}
  .tm-stars{margin-bottom:8px;letter-spacing:2px;}
  .tm-star{background:none;border:none;padding:0 1px;font-size:1.15rem;color:var(--accent);cursor:pointer;line-height:1;}
  .tm-star:hover{transform:scale(1.2);}
  .tm-photo{width:88px;height:88px;border-radius:50%;overflow:hidden;margin:0 auto 14px;background:#e9e5d6;}
  .tm-photo img{width:100%;height:100%;object-fit:cover;display:block;}
  .tm-photo.live-photo-wrap.empty{min-height:0;}
  .tm-photo .photo-overlay-btn{font-size:0;gap:0;padding:7px;border-radius:50%;}
  .tm-photo .photo-delete-btn{top:2px;right:2px;width:20px;height:20px;}
  .testimonial-photo-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:24px;align-items:start;}
  .testimonial-photo-card{text-align:center;padding:12px;}
  .testimonial-photo-card .quote{color:#3a3a34;} .testimonial-photo-card .author{margin-top:10px;font-weight:700;color:var(--primary);}
  .testimonial-wall{columns:3 220px;column-gap:16px;}
  .testimonial-wall-item{break-inside:avoid;background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:8px;padding:16px;margin-bottom:16px;font-size:.9rem;}
  .testimonial-wall-item .author{margin-top:8px;font-weight:700;font-size:.82rem;color:var(--primary);}
  .tm-slides{max-width:720px;margin:0 auto;display:flex;flex-direction:column;gap:16px;}
  .tm-slide{text-align:center;background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:10px;padding:22px;}
  .tm-slide .quote{font-size:1.15rem;font-style:italic;color:#3a3a34;}
  .tm-slide .author{margin-top:12px;font-weight:700;color:var(--primary);}
  .tm-single{max-width:760px;margin:0 auto;text-align:center;}
  .tm-single .quote{font-family:Georgia,serif;font-size:1.6rem;font-style:italic;color:#2a2a24;line-height:1.4;}
  .tm-single .author{margin-top:18px;font-weight:700;color:var(--primary);}
  .tm-single.tm-item{padding:18px;}
  .tm-unused{opacity:.45;}
  .tm-unused:hover{opacity:1;}
  .t-note{font-size:.8rem;color:#6b6b60;margin:0 0 14px;display:flex;gap:6px;align-items:center;}
  .t-note .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;}
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

<section id="testimonials">
  <div class="wrap">
    <div class="section-head"><h2<?= live_color_attr($content, 'title') ?>><?= esc($section['label']) ?></h2></div>

    <?php if ($withQuote === 0): ?>
      <p class="t-note"><span class="material-symbols-outlined">info</span>With no quotes yet, this section stays hidden on your live site.</p>
    <?php endif; ?>

    <?php if ($variant === 'slider'): ?>
      <p class="t-note"><span class="material-symbols-outlined">view_carousel</span>Visitors see these one at a time, rotating automatically.</p>
      <div class="tm-slides">
        <?php foreach ($items as $i) tm_card($content, $i, false, ['class' => 'tm-slide']); ?>
        <?php if ($blank) tm_card($content, $blank, true, ['class' => 'tm-slide']); ?>
      </div>

    <?php elseif ($variant === 'single'): ?>
      <p class="t-note"><span class="material-symbols-outlined">format_quote</span>Only the first quote is shown in this layout. The others are kept for when you switch.</p>
      <?php
        $first = $items[0] ?? $blank;
        if ($first) tm_card($content, $first, empty($items), ['class' => 'tm-single']);
        $rest = array_slice($items, 1);
      ?>
      <?php if ($rest || ($blank && $items)): ?>
        <div class="testimonial-grid tm-unused" style="margin-top:30px;">
          <?php foreach ($rest as $i) tm_card($content, $i, false, ['class' => 'testimonial-card']); ?>
          <?php if ($blank && $items) tm_card($content, $blank, true, ['class' => 'testimonial-card']); ?>
        </div>
      <?php endif; ?>

    <?php elseif ($variant === 'wall'): ?>
      <div class="testimonial-wall">
        <?php foreach ($items as $i) tm_card($content, $i, false, ['class' => 'testimonial-wall-item']); ?>
        <?php if ($blank) tm_card($content, $blank, true, ['class' => 'testimonial-wall-item']); ?>
      </div>

    <?php elseif ($variant === 'rating'): ?>
      <div class="testimonial-grid">
        <?php foreach ($items as $i) tm_card($content, $i, false, ['class' => 'testimonial-card', 'stars' => true]); ?>
        <?php if ($blank) tm_card($content, $blank, true, ['class' => 'testimonial-card', 'stars' => true]); ?>
      </div>

    <?php elseif ($variant === 'photo_forward'): ?>
      <div class="testimonial-photo-grid">
        <?php foreach ($items as $i) tm_card($content, $i, false, ['class' => 'testimonial-photo-card', 'photo' => true]); ?>
        <?php if ($blank) tm_card($content, $blank, true, ['class' => 'testimonial-photo-card', 'photo' => true]); ?>
      </div>

    <?php else: /* cards */ ?>
      <div class="testimonial-grid">
        <?php foreach ($items as $i) tm_card($content, $i, false, ['class' => 'testimonial-card']); ?>
        <?php if ($blank) tm_card($content, $blank, true, ['class' => 'testimonial-card']); ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php render_live_edit_script((int)$section['id'], $variant); ?>
<?php render_live_edit_color_tools((int)$section['id'], $theme); ?>
<script>
(function(){
  var sectionId = <?= (int)$section['id'] ?>;
  window.somahubRemoveTestimonial = function(i) {
    if (!window.confirm('Remove this testimonial?')) return;
    var fd = new FormData();
    fd.append('section_id', sectionId);
    ['quote','author','rating'].forEach(function(f){ fd.append(f + '_' + i, ''); });
    fd.append('remove_photo_' + i, '1');
    fetch('section-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d.ok) { sessionStorage.setItem('somahubLiveEditNotice', 'Testimonial removed'); window.location.reload(); }
        else if (window.somahubShowSaved) window.somahubShowSaved(false, 'Could not remove');
      });
  };
  // Click a star to set the rating; click the current rating again to clear it.
  window.somahubSetRating = function(i, n) {
    var box = document.querySelector('.tm-stars[data-i="' + i + '"]');
    var next = (parseInt(box.getAttribute('data-rating'), 10) === n) ? 0 : n;
    box.setAttribute('data-rating', next);
    box.querySelectorAll('.tm-star').forEach(function(b, idx){
      var on = idx < next; b.classList.toggle('on', on); b.textContent = on ? '★' : '☆';
    });
    window.somahubSaveField('rating_' + i, next ? String(next) : '');
  };
  // Typing into the blank card creates a new entry - reload so a fresh blank card appears.
  document.querySelectorAll('.is-new [data-field^="quote_"]').forEach(function(el){
    var before = el.textContent;
    el.addEventListener('blur', function(){
      if (el.textContent.trim() === before.trim()) return;
      setTimeout(function(){ window.location.reload(); }, 700);
    });
  });
})();
</script>
</body>
</html>
