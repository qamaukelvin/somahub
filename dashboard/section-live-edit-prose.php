<?php
// Live, in-place editor shared by Admissions and Academics (same field model:
// body, list_items, photo, faq pairs, inline stats). Dispatches on key_name.
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
if (!$section || !in_array($section['key_name'], ['admissions','academics'], true)) {
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
$KEY = $section['key_name'];
$config = get_section_variant_registry()[$KEY];
$variant = $section['layout_variant'] ?? $config['default'];
$items = array_values(array_filter(array_map('trim', explode("\n", $content['list_items'] ?? ''))));
$photo = $content['photo'] ?? '';
$photoCount = $photo ? 1 : 0;
$isAdm = $KEY === 'admissions';

function pr_body(array $c, string $ph, string $cls = 'text-block'): string {
    return '<div class="' . $cls . '" data-field="body"' . live_color_attr($c, 'body') . ' contenteditable="true" data-placeholder="' . $ph . '">' . nl2p_live($c['body'] ?? '') . '</div>';
}
function pr_items(array $c, array $items, string $wrapTag, string $wrapCls, string $itemTag, string $itemCls, string $addLabel, string $ph): void {
    echo '<' . $wrapTag . ' class="' . $wrapCls . '" id="liveListItems" data-field="list_items" data-item-tag="' . $itemTag . '" data-item-class="' . $itemCls . '">';
    foreach ($items as $it) echo '<' . $itemTag . ' class="' . $itemCls . ' live-list-item"><span contenteditable="true" data-placeholder="' . $ph . '"' . live_color_attr($c, 'items') . '>' . esc($it) . '</span><button type="button" class="live-list-remove" onclick="somahubRemoveListItem(this)">&times;</button></' . $itemTag . '>';
    echo '</' . $wrapTag . '><button type="button" class="live-list-add" onclick="somahubAddListItem()">+ ' . $addLabel . '</button>';
}
$applyBtn = '<span class="btn-primary apply-prev" title="On your live site this opens your enrollment application">Apply for Admission</span>';

$extraCss = <<<CSS
  section#{$KEY}{background:var(--bg);padding:60px 0;min-height:60vh;}
  .text-block{max-width:760px;color:#3a3a34;}
  .text-block p{margin-bottom:14px;}
  .about-grid{display:grid;grid-template-columns:1.1fr 0.9fr;gap:40px;align-items:start;}
  @media(max-width:820px){.about-grid{grid-template-columns:1fr;}}
  .about-list{max-width:760px;list-style:none;padding:0;margin-top:20px;}
  .about-list li{position:relative;padding:12px 0 12px 30px;border-bottom:1px solid rgba(0,0,0,.08);color:#3a3a34;}
  .about-list li::before{content:"\\2713";position:absolute;left:0;top:12px;color:var(--accent);font-weight:800;}
  .about-timeline{max-width:760px;position:relative;padding-left:28px;margin-top:20px;}
  .about-timeline::before{content:"";position:absolute;left:6px;top:6px;bottom:6px;width:2px;background:var(--accent);}
  .about-timeline-item{position:relative;padding:0 0 22px 20px;color:#3a3a34;}
  .about-timeline-item::before{content:"";position:absolute;left:-28px;top:4px;width:11px;height:11px;border-radius:50%;background:var(--primary);border:2px solid var(--accent);}
  .key-dates-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-top:20px;}
  .key-date-card{background:#fff;border-radius:10px;padding:16px 18px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 4px rgba(0,0,0,.05);}
  .key-date-card .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:26px;color:var(--accent);flex-shrink:0;}
  .key-date-card span[contenteditable]{font-size:.88rem;color:#5c5c52;flex:1;min-width:0;overflow-wrap:anywhere;}
  .curriculum-grid{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px;}
  .curriculum-tag{background:#fff;border:1.5px solid var(--primary);color:var(--primary);padding:8px 18px;border-radius:20px;font-size:.85rem;font-weight:600;position:relative;}
  .grade-levels-list{margin-top:16px;display:flex;flex-direction:column;}
  .grade-level-row{padding:14px 0;border-bottom:1px solid rgba(0,0,0,.08);position:relative;color:#5c5c52;}
  .about-inline-stats{display:flex;gap:28px;margin-top:20px;flex-wrap:wrap;}
  .about-inline-stat{display:flex;flex-direction:column;}
  .about-inline-stat .num{font-size:1.6rem;font-weight:800;color:var(--primary);}
  .about-inline-stat .label{font-size:.78rem;color:#5c5c52;}
  .faq-item{background:#fff;border-radius:10px;padding:20px 22px;margin-bottom:14px;box-shadow:0 1px 4px rgba(0,0,0,.05);max-width:820px;}
  .faq-item.is-empty{background:rgba(255,255,255,.5);border:2px dashed rgba(0,0,0,.2);}
  .faq-item .q{font-weight:700;font-size:.98rem;margin-bottom:6px;color:var(--primary);display:block;}
  .faq-item .a{font-size:.9rem;color:#5a5a52;display:block;}
  .apply-prev{margin-top:20px;display:inline-block;cursor:default;}
  .live-list-item{position:relative;}
  .live-list-remove{position:absolute;top:2px;right:0;width:22px;height:22px;border-radius:50%;border:none;background:#B3261E;color:#fff;cursor:pointer;opacity:0;line-height:1;padding:0;}
  .live-list-item:hover .live-list-remove{opacity:1;}
  .curriculum-tag .live-list-remove{top:-8px;right:-8px;}
  .live-list-add{margin-top:14px;background:none;border:2px dashed rgba(0,0,0,.3);border-radius:8px;padding:8px 16px;font-weight:700;cursor:pointer;color:#6b6b60;display:block;}
  .live-list-add:hover{border-color:var(--primary);color:var(--primary);}
  .i-note{font-size:.8rem;color:#6b6b60;margin:0 0 14px;display:flex;gap:6px;align-items:center;}
  .i-note .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;}
CSS;
$extraCss .= "\n" . ($appearance['custom_css'] ?? '');
$hint = '';
if (in_array($variant, ['key_dates', 'grade_levels'], true)) $hint = 'Write each line as "Label: detail" (for example "Term 1 opens: 6 January"). Your live site shows the label in bold.';
elseif ($variant === 'steps') $hint = 'Each line is one step in the process.';
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

<section id="<?= $KEY ?>">
  <div class="wrap">
    <div class="section-head"><h2<?= live_color_attr($content, 'title') ?>><?= esc($section['label']) ?></h2></div>
    <?php if ($hint): ?><p class="i-note"><span class="material-symbols-outlined">info</span><?= esc($hint) ?></p><?php endif; ?>

    <?php if ($variant === 'steps'): ?>
      <?= pr_body($content, 'Introduce the process...') ?>
      <?php pr_items($content, $items, 'div', 'about-timeline', 'div', 'about-timeline-item', 'Add step', 'Describe this step'); ?>
      <?= $isAdm ? $applyBtn : '' ?>

    <?php elseif ($variant === 'checklist'): ?>
      <?= pr_body($content, 'Introduce the requirements...') ?>
      <?php pr_items($content, $items, 'ul', 'about-list', 'li', '', 'Add requirement', 'Add a requirement'); ?>
      <?= $isAdm ? $applyBtn : '' ?>

    <?php elseif ($variant === 'key_dates'): ?>
      <?= pr_body($content, 'Introduce the key dates...') ?>
      <div class="key-dates-grid" id="liveListItems" data-field="list_items" data-item-tag="div" data-item-class="key-date-card">
        <?php foreach ($items as $it): ?><div class="key-date-card live-list-item"><span class="material-symbols-outlined">event</span><span contenteditable="true" data-placeholder="Label: detail"<?= live_color_attr($content, 'items') ?>><?= esc($it) ?></span><button type="button" class="live-list-remove" onclick="somahubRemoveListItem(this)">&times;</button></div><?php endforeach; ?>
      </div>
      <button type="button" class="live-list-add" onclick="somahubAddListItem()">+ Add date</button>
      <?= $isAdm ? $applyBtn : '' ?>

    <?php elseif ($variant === 'faq_style'): ?>
      <?= pr_body($content, 'Introduce admissions...') ?>
      <div style="margin-top:20px;">
        <?php for ($i = 1; $i <= 3; $i++): $empty = empty($content["faq_q$i"]) && empty($content["faq_a$i"]); ?>
          <div class="faq-item<?= $empty ? ' is-empty' : '' ?>">
            <span class="q" data-field="faq_q<?= $i ?>"<?= live_color_attr($content, 'questions') ?> contenteditable="true" data-placeholder="Question <?= $i ?>"><?= esc($content["faq_q$i"] ?? '') ?></span>
            <span class="a" data-field="faq_a<?= $i ?>"<?= live_color_attr($content, 'answers') ?> contenteditable="true" data-placeholder="Answer"><?= esc($content["faq_a$i"] ?? '') ?></span>
          </div>
        <?php endfor; ?>
      </div>
      <?= $isAdm ? $applyBtn : '' ?>

    <?php elseif ($variant === 'text_photo'): ?>
      <div class="about-grid">
        <div><?= pr_body($content, 'Write something...') ?><?= $isAdm ? $applyBtn : '' ?></div>
        <?php live_photo_block('photo', $photo ? img($photo) : ''); ?>
      </div>

    <?php elseif ($variant === 'curriculum_grid'): ?>
      <?= pr_body($content, 'Introduce your curriculum...') ?>
      <div class="curriculum-grid" id="liveListItems" data-field="list_items" data-item-tag="div" data-item-class="curriculum-tag">
        <?php foreach ($items as $it): ?><div class="curriculum-tag live-list-item"><span contenteditable="true" data-placeholder="Subject"<?= live_color_attr($content, 'items') ?>><?= esc($it) ?></span><button type="button" class="live-list-remove" onclick="somahubRemoveListItem(this)">&times;</button></div><?php endforeach; ?>
      </div>
      <button type="button" class="live-list-add" onclick="somahubAddListItem()">+ Add subject</button>

    <?php elseif ($variant === 'grade_levels'): ?>
      <?= pr_body($content, 'Introduce your grade levels...') ?>
      <div class="grade-levels-list" id="liveListItems" data-field="list_items" data-item-tag="div" data-item-class="grade-level-row">
        <?php foreach ($items as $it): ?><div class="grade-level-row live-list-item"><span contenteditable="true" data-placeholder="Grade: description"<?= live_color_attr($content, 'items') ?>><?= esc($it) ?></span><button type="button" class="live-list-remove" onclick="somahubRemoveListItem(this)">&times;</button></div><?php endforeach; ?>
      </div>
      <button type="button" class="live-list-add" onclick="somahubAddListItem()">+ Add grade level</button>

    <?php elseif ($variant === 'stats_inline'): ?>
      <?= pr_body($content, 'Write something...') ?>
      <div class="about-inline-stats">
        <?php for ($i = 1; $i <= 2; $i++): ?>
        <div class="about-inline-stat"><span class="num" data-field="inline_stat_<?= $i ?>"<?= live_color_attr($content, 'numbers') ?> contenteditable="true" data-placeholder="<?= $i === 1 ? '15+' : '500+' ?>"><?= esc($content["inline_stat_$i"] ?? '') ?></span><span class="label" data-field="inline_stat_<?= $i ?>_label"<?= live_color_attr($content, 'labels') ?> contenteditable="true" data-placeholder="Label"><?= esc($content["inline_stat_{$i}_label"] ?? '') ?></span></div>
        <?php endfor; ?>
      </div>

    <?php else: /* text_cta / text_only */ ?>
      <?= pr_body($content, $isAdm ? 'Explain how families apply...' : 'Describe your academic programme...') ?>
      <?= $isAdm ? $applyBtn : '' ?>
    <?php endif; ?>
  </div>
</section>

<?php render_live_edit_script((int)$section['id'], $variant); ?>
<?php render_live_edit_color_tools((int)$section['id'], $theme); ?>
</body>
</html>
