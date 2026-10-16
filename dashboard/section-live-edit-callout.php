<?php
// Live, in-place editor shared by Enrollment Form and Results Lookup (callout
// style sections: intro text, steps, photo, deadline, preview of the form).
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
if (!$section || !in_array($section['key_name'], ['enrollment_form','results_lookup'], true)) {
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
$isEnroll = $KEY === 'enrollment_form';
$config = get_section_variant_registry()[$KEY];
$variant = $section['layout_variant'] ?? 'simple';
$items = array_values(array_filter(array_map('trim', explode("\n", $content['list_items'] ?? ''))));
$photo = $content['photo'] ?? '';
$photoCount = $photo ? 1 : 0;
$introDefault = $isEnroll ? "Apply for your child's admission online." : "Check your child's term results using their admission number.";
$btnLabel = $isEnroll ? 'Apply Now' : 'Check Results';

function cl_intro(array $c, string $ph, string $tag = 'p', string $style = ''): string {
    return '<' . $tag . ($style ? ' style="' . $style . '"' : '') . ' data-field="intro_text"' . live_color_attr($c, 'intro_text') . ' contenteditable="true" data-placeholder="' . esc($ph) . '">' . esc($c['intro_text'] ?? '') . '</' . $tag . '>';
}
$btn = fn(string $label, string $extra = '') => '<span class="btn-primary demo-btn" title="On your live site this button opens the real page"' . ($extra ? ' style="' . $extra . '"' : '') . '>' . esc($label) . '</span>';
function cl_steps(array $c, array $items, string $add): void {
    echo '<div class="about-timeline" id="liveListItems" data-field="list_items" data-item-tag="div" data-item-class="about-timeline-item" style="text-align:left;margin:20px 0;">';
    foreach ($items as $it) echo '<div class="about-timeline-item live-list-item"><span contenteditable="true" data-placeholder="Describe this step"' . live_color_attr($c, 'items') . '>' . esc($it) . '</span><button type="button" class="live-list-remove" onclick="somahubRemoveListItem(this)">&times;</button></div>';
    echo '</div><button type="button" class="live-list-add" onclick="somahubAddListItem()">+ ' . $add . '</button>';
}

$extraCss = <<<CSS
  section#{$KEY}{background:var(--bg);padding:60px 0;min-height:60vh;}
  .callout-block{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:10px;padding:32px;text-align:center;max-width:520px;margin:0 auto;}
  .callout-block p{color:#5a5a52;margin-bottom:20px;font-size:.95rem;}
  .about-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:40px;align-items:start;}
  @media(max-width:820px){.about-grid{grid-template-columns:1fr;}}
  .about-grid p{margin-bottom:14px;color:#3a3a34;}
  .about-timeline{max-width:760px;position:relative;padding-left:28px;}
  .about-timeline::before{content:"";position:absolute;left:6px;top:6px;bottom:6px;width:2px;background:var(--accent);}
  .about-timeline-item{position:relative;padding:0 0 22px 20px;color:#3a3a34;}
  .about-timeline-item::before{content:"";position:absolute;left:-28px;top:4px;width:11px;height:11px;border-radius:50%;background:var(--primary);border:2px solid var(--accent);}
  .cta-banner{background:var(--primary);color:var(--bg);border-radius:10px;padding:44px 32px;text-align:center;}
  .cta-banner h2{font-size:1.2rem;margin-bottom:10px;}
  .cta-banner p{opacity:.85;max-width:50ch;margin:0 auto 22px;}
  .cta-banner .btn-primary{background:var(--accent);color:var(--primary);}
  .btn-primary{background:var(--primary);color:var(--bg);padding:13px 28px;border-radius:6px;font-weight:600;font-size:.9rem;display:inline-block;cursor:default;}
  .enroll-inline-form{max-width:none;margin:0;background:#fff;border-radius:10px;padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.05);}
  .enroll-inline-form > p{margin-bottom:18px;color:#5a5a52;}
  .enroll-form-fields{display:grid;grid-template-columns:repeat(6,1fr);gap:0 22px;align-items:start;}
  .enroll-form-fields .ef{display:flex;flex-direction:column;min-width:0;grid-column:span 6;}
  @media(min-width:760px){.enroll-form-fields .ef-2{grid-column:span 2;}.enroll-form-fields .ef-4{grid-column:span 4;}}
  .enroll-form-fields .demo-btn{grid-column:1/-1;justify-self:start;}
  .enroll-form-fields .lbl{font-size:.85rem;font-weight:600;margin-bottom:6px;}
  .enroll-form-fields .fake-input{padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:16px;color:#aaa;font-size:.88rem;background:#fafafa;}
  .live-list-item{position:relative;}
  .live-list-remove{position:absolute;top:0;right:0;width:22px;height:22px;border-radius:50%;border:none;background:#B3261E;color:#fff;cursor:pointer;opacity:0;line-height:1;padding:0;}
  .live-list-item:hover .live-list-remove{opacity:1;}
  .live-list-add{margin:0 auto 14px;background:none;border:2px dashed rgba(0,0,0,.3);border-radius:8px;padding:8px 16px;font-weight:700;cursor:pointer;color:#6b6b60;display:block;}
  .live-list-add:hover{border-color:var(--primary);color:var(--primary);}
  .i-note{font-size:.8rem;color:#6b6b60;margin:0 0 14px;display:flex;gap:6px;align-items:center;}
  .i-note .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;}
CSS;
$extraCss .= "\n" . ($appearance['custom_css'] ?? '');

// Notes for layouts whose live version falls back until content exists.
$note = '';
if ($variant === 'steps_preview' && !$items) $note = 'Add at least one step. Until then your live site shows the simple layout.';
elseif ($variant === 'photo_callout' && !$photo) $note = 'Add a photo. Until then your live site shows the simple layout.';
elseif ($variant === 'deadline_banner' && empty($content['deadline_text'])) $note = 'Add a deadline line. Until then your live site shows the simple layout.';
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
    <?php if ($note): ?><p class="i-note"><span class="material-symbols-outlined">info</span><?= esc($note) ?></p><?php endif; ?>

    <?php if ($variant === 'inline_form'): ?>
      <div class="enroll-inline-form">
        <?= cl_intro($content, $introDefault) ?>
        <div class="enroll-form-fields">
          <?php $spans = $isEnroll ? [4, 2, 2, 4, 2, 2, 2] : [2, 4];
          foreach ($isEnroll
              ? ["Child's Full Name", 'Date of Birth', 'Grade Applying For', 'Previous School (if transferring)', 'Parent / Guardian Name', 'Parent / Guardian Phone', 'Parent / Guardian Email (optional)']
              : ['Admission Number', "Student's Full Name or Date of Birth (YYYY-MM-DD)"] as $li => $l): ?>
            <div class="ef ef-<?= $spans[$li] ?>"><span class="lbl"><?= esc($l) ?></span><span class="fake-input">&nbsp;</span></div>
          <?php endforeach; ?>
          <?= $btn($isEnroll ? 'Submit Application' : 'Check Results') ?>
        </div>
        <p class="i-note" style="margin:12px 0 0;"><span class="material-symbols-outlined">info</span>Preview only. Visitors fill in the real form on your live site.</p>
      </div>

    <?php elseif ($variant === 'steps_preview'): ?>
      <div class="callout-block" style="max-width:640px;">
        <?= cl_intro($content, $introDefault) ?>
        <?php cl_steps($content, $items, $isEnroll ? 'Add step' : 'Add step'); ?>
        <?= $btn($btnLabel) ?>
      </div>

    <?php elseif ($variant === 'photo_callout'): ?>
      <div class="about-grid">
        <div><?= cl_intro($content, $introDefault) ?><?= $btn($btnLabel, 'margin-top:14px;') ?></div>
        <?php live_photo_block('photo', $photo ? img($photo) : ''); ?>
      </div>

    <?php elseif ($variant === 'deadline_banner'): ?>
      <div class="cta-banner">
        <h2 data-field="deadline_text"<?= live_color_attr($content, 'deadline_text') ?> contenteditable="true" data-placeholder="e.g. Applications close 30 November"><?= esc($content['deadline_text'] ?? '') ?></h2>
        <?= cl_intro($content, 'Add a supporting line (optional)') ?>
        <?= $btn($btnLabel) ?>
      </div>

    <?php else: /* simple / single_button */ ?>
      <div class="callout-block">
        <?= cl_intro($content, $introDefault) ?>
        <?= $btn($btnLabel) ?>
        <?php if (!$isEnroll && $variant === 'simple'): ?><?= $btn('View Full Report', 'margin-left:10px;background:transparent;border:2px solid var(--primary);color:var(--primary);') ?><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php render_live_edit_script((int)$section['id'], $variant); ?>
<?php render_live_edit_color_tools((int)$section['id'], $theme); ?>
</body>
</html>
