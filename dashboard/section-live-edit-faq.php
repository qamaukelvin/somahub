<?php
// Live, in-place editor for FAQ - same pattern as Staff/Testimonials: each
// entry is edited where it sits, a dashed blank card adds the next one (max
// 10), a red x on hover removes one. An entry shows on the live site once it
// has a question. This section has a single layout, so there's no layout picker.
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
if (!$section || $section['key_name'] !== 'faq') {
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
$MAX = 10;
$FIELDS = ['question', 'answer'];
$items = [];
$blank = null;
for ($i = 1; $i <= $MAX; $i++) {
    $has = false;
    foreach ($FIELDS as $f) { if (!empty($content["{$f}_$i"])) { $has = true; break; } }
    if ($has) $items[] = $i; elseif ($blank === null) $blank = $i;
}
$named = count(array_filter($items, fn($i) => !empty($content["question_$i"])));

function ed(array $c, int $i, string $f, string $tag, string $ph, string $cls = '', string $colorKey = ''): string {
    return "<$tag class=\"$cls\" data-field=\"{$f}_$i\"" . ($colorKey ? live_color_attr($c, $colorKey) : '') . " contenteditable=\"true\" data-placeholder=\"$ph\">" . nl2br(esc($c["{$f}_$i"] ?? '')) . "</$tag>";
}
function rm_btn(int $i): string {
    return '<button type="button" class="it-remove" title="Remove this entry" onclick="somahubRemoveEntry(' . $i . ')"><span class="material-symbols-outlined">close</span></button>';
}

$extraCss = <<<CSS
  section#faq{background:var(--bg);padding:60px 0;min-height:70vh;}
  .faq-item{background:#fff;border-radius:10px;padding:20px 22px;margin-bottom:14px;box-shadow:0 1px 4px rgba(0,0,0,.05);}
  .faq-item .q{font-weight:700;font-size:.98rem;margin-bottom:6px;color:var(--primary);}
  .faq-item .a{font-size:.9rem;color:#5a5a52;}
  .faq-wrap{max-width:820px;}
  .it-item{position:relative;}
  .it-item.is-new{opacity:.7;border:2px dashed rgba(0,0,0,.25);background:rgba(255,255,255,.4);border-radius:10px;}
  .it-item.is-new:hover{opacity:1;border-color:var(--primary);}
  .it-remove{position:absolute;top:-8px;right:-8px;z-index:6;width:24px;height:24px;border-radius:50%;border:2px solid #fff;background:#B3261E;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;opacity:0;transition:opacity .15s;}
  .it-remove .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:14px;}
  .it-item:hover .it-remove{opacity:1;}
  .it-warn{display:inline-block;margin-top:8px;font-size:.68rem;font-weight:700;color:#8a5a00;background:rgba(244,163,64,.25);border-radius:10px;padding:2px 9px;}
  .i-note{font-size:.8rem;color:#6b6b60;margin:0 0 14px;display:flex;gap:6px;align-items:center;}
  .i-note .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;}
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

<div class="save-toast" id="saveToast"><span class="material-symbols-outlined">check_circle</span><span id="saveToastMsg">Saved</span></div>

<section id="faq">
  <div class="wrap">
    <div class="section-head"><h2<?= live_color_attr($content, 'title') ?>><?= esc($section['label']) ?></h2></div>
    <?php if ($named === 0): ?><p class="i-note"><span class="material-symbols-outlined">info</span>With no questions yet, this section stays hidden on your live site.</p><?php endif; ?>
    <div class="faq-wrap">
      <?php foreach (array_merge($items, $blank ? [-$blank] : []) as $k): $isNew = $k < 0; $i = abs($k); ?>
        <div class="faq-item it-item<?= $isNew ? ' is-new' : '' ?>">
          <?php if (!$isNew) echo rm_btn($i); ?>
          <?= ed($content, $i, 'question', 'div', $isNew ? 'Add a question' : 'Question', 'q', 'questions') ?>
          <?= ed($content, $i, 'answer', 'div', 'Answer', 'a', 'answers') ?>
          <?php if (!$isNew && empty($content["question_$i"])): ?><div class="it-warn">Needs a question</div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php render_live_edit_script((int)$section['id'], ''); ?>
<?php render_live_edit_color_tools((int)$section['id'], $theme); ?>
<script>
(function(){
  var sectionId = <?= (int)$section['id'] ?>;
  window.somahubRemoveEntry = function(i) {
    if (!window.confirm('Remove this entry?')) return;
    var fd = new FormData();
    fd.append('section_id', sectionId);
    ['question','answer'].forEach(function(f){ fd.append(f + '_' + i, ''); });
    fetch('section-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d.ok) { sessionStorage.setItem('somahubLiveEditNotice', 'Entry removed'); window.location.reload(); }
        else if (window.somahubShowSaved) window.somahubShowSaved(false, 'Could not remove');
      });
  };
  // Typing into the blank card creates a new entry - reload so a fresh blank card appears.
  document.querySelectorAll('.is-new [data-field^="question_"]').forEach(function(el){
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
