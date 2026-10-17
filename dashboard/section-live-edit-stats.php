<?php
// Live, in-place editor for Stats - same pattern as Staff/Testimonials. Each
// stat is edited where it sits (number, label, icon); a dashed blank card adds
// the next one (max 8). A stat shows on the live site once it has a number.
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
if (!$section || $section['key_name'] !== 'stats') {
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

$config = get_section_variant_registry()['stats'];
$content = json_decode($section['content_json'], true) ?: [];
$variant = $section['layout_variant'] ?? 'strip';
$MAX = 8;

$items = [];
$blank = null;
for ($i = 1; $i <= $MAX; $i++) {
    $has = false;
    foreach (['number', 'label', 'icon'] as $f) { if (!empty($content["stat_{$i}_$f"])) { $has = true; break; } }
    if ($has) $items[] = $i; elseif ($blank === null) $blank = $i;
}
$withNumber = count(array_filter($items, fn($i) => !empty($content["stat_{$i}_number"])));

// Curated icon choices (Material Symbols names) - a picker, never free text.
$ICONS = ['school','groups','person','menu_book','emoji_events','workspace_premium','star','favorite','sports_soccer','science','computer','restaurant','directions_bus','local_library','calendar_month','verified'];

function st_text(array $c, int $i, string $f, string $tag, string $ph, string $cls, string $colorKey): string {
    return "<$tag class=\"$cls\" data-field=\"stat_{$i}_$f\"" . live_color_attr($c, $colorKey) . " contenteditable=\"true\" data-placeholder=\"$ph\">" . esc($c["stat_{$i}_$f"] ?? '') . "</$tag>";
}
function st_remove(int $i): string {
    return '<button type="button" class="it-remove" title="Remove this stat" onclick="somahubRemoveStat(' . $i . ')"><span class="material-symbols-outlined">close</span></button>';
}
function st_icon(array $c, int $i, array $icons): string {
    $cur = $c["stat_{$i}_icon"] ?? '';
    $o = '<div class="st-icon-slot">';
    $o .= $cur ? '<span class="material-symbols-outlined stat-icon">' . esc($cur) . '</span>' : '<span class="st-icon-add"><span class="material-symbols-outlined">add_reaction</span>Add icon</span>';
    $o .= '<div class="st-icon-menu"><div class="st-icon-panel">';
    foreach ($icons as $ic) $o .= '<button type="button" class="st-ic' . ($cur === $ic ? ' on' : '') . '" title="' . esc($ic) . '" onclick="somahubSetIcon(' . $i . ',\'' . esc($ic) . '\')"><span class="material-symbols-outlined">' . esc($ic) . '</span></button>';
    if ($cur) $o .= '<button type="button" class="st-ic none" title="No icon" onclick="somahubSetIcon(' . $i . ',\'\')"><span class="material-symbols-outlined">block</span></button>';
    return $o . '</div></div></div>';
}
/** one stat card; $o: icon(bool), kind(strip|ring|bar) */
function st_card(array $c, int $i, bool $isNew, array $o, array $icons): void {
    $unn = !$isNew && empty($c["stat_{$i}_number"]);
    echo '<div class="stat-item it-item' . ($isNew ? ' is-new' : '') . '" data-i="' . $i . '">';
    if (!$isNew) echo st_remove($i);
    if (!empty($o['icon'])) echo st_icon($c, $i, $icons);
    if (($o['kind'] ?? '') === 'ring') {
        $v = min(100, max(0, (int)preg_replace('/[^0-9]/', '', $c["stat_{$i}_number"] ?? '')));
        echo '<div class="ring" style="background:conic-gradient(var(--accent) ' . ($v * 3.6) . 'deg, rgba(0,0,0,0.08) 0deg);"><div class="ring-inner">' . st_text($c, $i, 'number', 'span', '75', 'num-edit', 'numbers') . '</div></div>';
    } else {
        echo st_text($c, $i, 'number', 'div', $isNew ? 'Add a number' : '15+', 'number', 'numbers');
    }
    echo st_text($c, $i, 'label', 'div', 'Label', 'label', 'labels');
    if ($unn) echo '<div class="it-warn" title="Add a number and this will show on your live site">Needs a number</div>';
    echo '</div>';
}

$extraCss = <<<CSS
  section#stats{background:var(--bg);padding:60px 0;min-height:60vh;}
  .stats-strip{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:16px;align-items:start;}
  .stat-item{background:#fff;border-radius:12px;padding:24px 16px;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.05);}
  .stat-item .number{font-weight:700;font-size:2rem;color:var(--accent);}
  .stat-item .label{font-size:.8rem;color:#6b6b60;text-transform:uppercase;letter-spacing:.04em;margin-top:4px;}
  .stat-item .stat-icon{font-family:'Material Symbols Outlined';font-size:32px;color:var(--primary);display:block;margin:0 auto 8px;}
  .stat-item.is-new{background:rgba(255,255,255,.4);}
  .stats-rings{display:flex;gap:28px;flex-wrap:wrap;justify-content:center;}
  .stats-rings .stat-item{background:none;box-shadow:none;padding:12px;}
  .stats-rings .stat-item.is-new{border:2px dashed rgba(0,0,0,.25);}
  .stats-rings .ring{width:120px;height:120px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;}
  .stats-rings .ring-inner{width:96px;height:96px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.3rem;color:var(--primary);}
  .stats-bars{max-width:700px;margin:0 auto;display:flex;flex-direction:column;gap:16px;}
  .stats-bar-row{display:flex;align-items:center;gap:14px;position:relative;}
  .stats-bar-label{flex:0 0 140px;font-size:.85rem;color:#5c5c52;text-align:right;}
  .stats-bar-track{flex:1;background:rgba(0,0,0,.06);border-radius:6px;overflow:hidden;height:34px;}
  .stats-bar-fill{background:var(--accent);color:var(--primary);height:100%;display:flex;align-items:center;padding:0 12px;font-weight:700;font-size:.85rem;white-space:nowrap;border-radius:6px;min-width:56px;}
  .stats-bar-row.is-new{opacity:.7;border:2px dashed rgba(0,0,0,.25);border-radius:8px;padding:6px;}
  .stats-bar-row .it-remove{right:-30px;top:4px;}
  .st-icon-slot{position:relative;display:inline-block;margin-bottom:6px;}
  .st-icon-add{display:inline-flex;align-items:center;gap:4px;font-size:.72rem;font-weight:700;color:#8a8a7e;border:1.5px dashed rgba(0,0,0,.3);border-radius:16px;padding:4px 10px;cursor:pointer;}
  .st-icon-add .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;}
  .st-icon-menu{display:none;position:absolute;top:100%;left:50%;transform:translateX(-50%);padding-top:6px;z-index:40;}
  .st-icon-slot:hover .st-icon-menu{display:block;}
  .st-icon-panel{background:#1C1C16;border-radius:10px;padding:8px;display:grid;grid-template-columns:repeat(4,36px);gap:4px;box-shadow:0 8px 24px rgba(0,0,0,.4);}
  .st-ic{width:36px;height:36px;border-radius:8px;border:none;background:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;}
  .st-ic:hover{background:rgba(255,255,255,.15);} .st-ic.on{background:rgba(255,255,255,.25);} .st-ic.none{color:#F4A0A0;}
  .st-ic .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:22px;}
  .stats-ticker-note{font-size:.8rem;color:#6b6b60;}
  .it-item{position:relative;}
  .it-item.is-new{opacity:.7;border:2px dashed rgba(0,0,0,.25);border-radius:12px;}
  .it-item.is-new:hover{opacity:1;border-color:var(--primary);}
  .it-remove{position:absolute;top:-8px;right:-8px;z-index:6;width:24px;height:24px;border-radius:50%;border:2px solid #fff;background:#B3261E;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;opacity:0;transition:opacity .15s;}
  .it-remove .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:14px;}
  .it-item:hover .it-remove{opacity:1;}
  .it-warn{display:inline-block;margin-top:8px;font-size:.68rem;font-weight:700;color:#8a5a00;background:rgba(244,163,64,.25);border-radius:10px;padding:2px 9px;}
  .i-note{font-size:.8rem;color:#6b6b60;margin:0 0 14px;display:flex;gap:6px;align-items:center;}
  .i-note .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;}
CSS;
$all = array_merge($items, $blank ? [-$blank] : []);
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
<?php render_variant_switcher($config, $variant, 0); ?>

<div class="save-toast" id="saveToast"><span class="material-symbols-outlined">check_circle</span><span id="saveToastMsg">Saved</span></div>

<section id="stats">
  <div class="wrap">
    <?php if ($withNumber === 0): ?><p class="i-note"><span class="material-symbols-outlined">info</span>With no stats yet, this section stays hidden on your live site.</p><?php endif; ?>

    <?php if ($variant === 'countup'): ?>
      <p class="i-note"><span class="material-symbols-outlined">trending_up</span>On your live site these numbers count up when visitors scroll to them. Add a suffix like + or % after the digits.</p>
    <?php elseif ($variant === 'ticker'): ?>
      <p class="i-note"><span class="material-symbols-outlined">view_carousel</span>On your live site these scroll across in a loop.</p>
    <?php elseif ($variant === 'rings'): ?>
      <p class="i-note"><span class="material-symbols-outlined">donut_large</span>The ring fills to the number (0-100), so use percentages for this layout.</p>
    <?php elseif ($variant === 'bars'): ?>
      <p class="i-note"><span class="material-symbols-outlined">bar_chart</span>Bars are sized against the biggest number.</p>
    <?php endif; ?>

    <?php if ($variant === 'rings'): ?>
      <div class="stats-strip stats-rings">
        <?php foreach ($all as $k) st_card($content, abs($k), $k < 0, ['kind' => 'ring'], $ICONS); ?>
      </div>

    <?php elseif ($variant === 'bars'):
        $vals = array_map(fn($i) => (float)preg_replace('/[^0-9.]/', '', $content["stat_{$i}_number"] ?? '') ?: 0, $items);
        $maxVal = $vals ? max($vals) : 0; if ($maxVal <= 0) $maxVal = 1;
    ?>
      <div class="stats-bars">
        <?php foreach ($all as $k): $i = abs($k); $isNew = $k < 0;
            $val = (float)preg_replace('/[^0-9.]/', '', $content["stat_{$i}_number"] ?? '') ?: 0;
            $pct = $isNew ? 0 : round($val / $maxVal * 100); ?>
          <div class="stats-bar-row it-item<?= $isNew ? ' is-new' : '' ?>" data-i="<?= $i ?>">
            <?php if (!$isNew) echo st_remove($i); ?>
            <?= st_text($content, $i, 'label', 'div', 'Label', 'stats-bar-label', 'labels') ?>
            <div class="stats-bar-track"><div class="stats-bar-fill" style="width:<?= $pct ?>%;"><?= st_text($content, $i, 'number', 'span', $isNew ? 'Number' : '75', 'bar-num', 'numbers') ?></div></div>
          </div>
        <?php endforeach; ?>
      </div>

    <?php else: ?>
      <div class="stats-strip">
        <?php foreach ($all as $k) st_card($content, abs($k), $k < 0, ['icon' => $variant === 'icon_paired'], $ICONS); ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php render_live_edit_script((int)$section['id'], $variant); ?>
<?php render_live_edit_color_tools((int)$section['id'], $theme); ?>
<script>
(function(){
  var sectionId = <?= (int)$section['id'] ?>;
  window.somahubRemoveStat = function(i) {
    if (!window.confirm('Remove this stat?')) return;
    var fd = new FormData();
    fd.append('section_id', sectionId);
    ['number','label','icon'].forEach(function(f){ fd.append('stat_' + i + '_' + f, ''); });
    fetch('section-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d.ok) { sessionStorage.setItem('somahubLiveEditNotice', 'Stat removed'); window.location.reload(); }
        else if (window.somahubShowSaved) window.somahubShowSaved(false, 'Could not remove');
      });
  };
  window.somahubSetIcon = function(i, name) {
    var fd = new FormData();
    fd.append('section_id', sectionId);
    fd.append('stat_' + i + '_icon', name);
    fetch('section-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d.ok) { sessionStorage.setItem('somahubLiveEditNotice', 'Icon updated'); window.location.reload(); }
        else if (window.somahubShowSaved) window.somahubShowSaved(false, 'Could not save icon');
      });
  };
  function digits(t){ return parseFloat(String(t).replace(/[^0-9.]/g,'')) || 0; }
  // Rings and bars follow the numbers as they're typed.
  document.querySelectorAll('.ring').forEach(function(ring){
    var n = ring.querySelector('[data-field$="_number"]');
    n.addEventListener('input', function(){
      var v = Math.min(100, Math.max(0, parseInt(digits(n.textContent), 10) || 0));
      ring.style.background = 'conic-gradient(var(--accent) ' + (v * 3.6) + 'deg, rgba(0,0,0,0.08) 0deg)';
    });
  });
  var rows = document.querySelectorAll('.stats-bar-row:not(.is-new)');
  function resizeBars(){
    var vals = []; rows.forEach(function(r){ vals.push(digits(r.querySelector('.bar-num').textContent)); });
    var max = Math.max.apply(null, vals.concat([1]));
    rows.forEach(function(r, k){ r.querySelector('.stats-bar-fill').style.width = Math.round(vals[k] / max * 100) + '%'; });
  }
  rows.forEach(function(r){ r.querySelector('.bar-num').addEventListener('input', resizeBars); });
  // Typing a number into the blank card creates a new stat - reload so a fresh blank appears.
  document.querySelectorAll('.is-new [data-field$="_number"]').forEach(function(el){
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
