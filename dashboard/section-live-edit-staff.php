<?php
// Live, in-place editor for Staff. Each person is a card you edit where it
// sits (name, role, bio, department, photo). A dashed blank card at the end
// adds the next person (max 10). A person appears on the live site once
// they have a name. Colours are per role (all names, all roles, all bios).
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
if (!$section || $section['key_name'] !== 'staff') {
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

$config = get_section_variant_registry()['staff'];
$content = json_decode($section['content_json'], true) ?: [];
$schema = json_decode($section['schema_json'], true) ?: [];
$hasIntro = array_key_exists('intro_text', $schema);
$variant = $section['layout_variant'] ?? 'grid';
$MAX = 10;

// Every slot that holds anything, in order; plus the first fully-empty slot
// as the "add a person" card.
$people = [];
$blank = null;
for ($i = 1; $i <= $MAX; $i++) {
    $has = false;
    foreach (['name', 'role', 'bio', 'department', 'photo'] as $f) { if (!empty($content["{$f}_$i"])) { $has = true; break; } }
    if ($has) $people[] = $i; elseif ($blank === null) $blank = $i;
}
$namedCount = count(array_filter($people, fn($i) => !empty($content["name_$i"])));

/** Editable text element for one person field. */
function staff_ef(array $c, int $i, string $f, string $tag, string $cls, string $ph, string $colorKey = ''): string {
    $val = esc($c["{$f}_$i"] ?? '');
    $color = $colorKey ? live_color_attr($c, $colorKey) : '';
    return "<$tag class=\"$cls\" data-field=\"{$f}_$i\"$color contenteditable=\"true\" data-placeholder=\"$ph\">$val</$tag>";
}
function staff_remove_btn(int $i): string {
    return '<button type="button" class="staff-remove" title="Remove this person" onclick="somahubRemovePerson(' . $i . ')"><span class="material-symbols-outlined">close</span></button>';
}

/** A person card for grid / carousel / org chart / grouped. */
function staff_card(array $c, int $i, bool $isNew, bool $withDept = false): void {
    $src = !empty($c["photo_$i"]) ? img($c["photo_$i"]) : '';
    $unnamed = !$isNew && empty($c["name_$i"]);
    echo '<div class="staff-card live-card' . ($isNew ? ' is-new' : '') . '">';
    if (!$isNew) echo staff_remove_btn($i);
    live_photo_block("photo_$i", $src, 'staff-photo');
    echo staff_ef($c, $i, 'name', 'div', 'name', $isNew ? 'Add a name' : 'Name', 'names');
    echo staff_ef($c, $i, 'role', 'div', 'role', 'Role', 'roles');
    if ($withDept) echo '<div class="staff-dept-edit"><span class="material-symbols-outlined">sell</span>' . staff_ef($c, $i, 'department', 'span', 'dept', 'Department') . '</div>';
    if ($unnamed) echo '<div class="staff-warn" title="Add a name and this person will show on your live site">Needs a name</div>';
    echo '</div>';
}

$extraCss = <<<CSS
  section#staff{background:var(--bg);padding:60px 0;min-height:70vh;}
  .staff-head-intro{color:#5a5a52;margin-top:6px;}
  .staff-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:22px;}
  .staff-card{text-align:center;position:relative;}
  .staff-card .staff-photo{width:100%;aspect-ratio:1;border-radius:50%;overflow:hidden;margin:0 auto 12px;background:#e9e5d6;}
  .staff-card .staff-photo img{width:100%;height:100%;object-fit:cover;display:block;}
  .staff-photo.live-photo-wrap.empty{min-height:0;aspect-ratio:1;}
  .staff-photo .photo-overlay-btn{font-size:0;gap:0;padding:7px;border-radius:50%;}
  .staff-photo .photo-delete-btn{top:6px;right:6px;width:22px;height:22px;}
  .staff-photo .photo-delete-btn .material-symbols-outlined{font-size:13px;}
  .staff-card .name{font-weight:700;font-size:.95rem;}
  .staff-card .role{font-size:.82rem;color:#6b6b60;}
  .staff-card.is-new{opacity:.7;border:2px dashed rgba(0,0,0,.25);border-radius:12px;padding:14px 8px;background:rgba(255,255,255,.4);}
  .staff-card.is-new:hover{opacity:1;border-color:var(--primary);}
  .staff-remove{position:absolute;top:-6px;right:-6px;z-index:6;width:24px;height:24px;border-radius:50%;border:2px solid #fff;background:#B3261E;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;opacity:0;transition:opacity .15s;}
  .staff-remove .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:14px;}
  .staff-card:hover .staff-remove,.staff-bio-row:hover .staff-remove,.staff-minimal-item:hover .staff-remove{opacity:1;}
  .staff-warn{display:inline-block;margin-top:6px;font-size:.68rem;font-weight:700;color:#8a5a00;background:rgba(244,163,64,.25);border-radius:10px;padding:2px 9px;}
  .staff-dept-edit{display:flex;align-items:center;justify-content:center;gap:4px;margin-top:6px;font-size:.78rem;color:#4A4A42;}
  .staff-dept-edit .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:14px;color:var(--accent);}
  .staff-carousel{display:flex;gap:20px;overflow-x:auto;padding:10px 6px 14px;}
  .staff-carousel .staff-card{flex:0 0 170px;}
  .staff-list-bio{display:flex;flex-direction:column;gap:22px;max-width:760px;}
  .staff-bio-row{display:flex;gap:16px;align-items:flex-start;position:relative;}
  .staff-bio-row .staff-photo{width:72px;flex:0 0 72px;margin:0;}
  .staff-bio-row .name{font-weight:700;}
  .staff-bio-row .role{font-size:.82rem;color:#6b6b60;margin-bottom:6px;}
  .staff-bio-row .bio{color:#3a3a34;font-size:.9rem;display:block;}
  .staff-bio-row.is-new{opacity:.7;border:2px dashed rgba(0,0,0,.25);border-radius:12px;padding:12px;background:rgba(255,255,255,.4);}
  .staff-org-chart{display:flex;flex-direction:column;align-items:center;gap:28px;}
  .org-tier{display:flex;gap:28px;justify-content:center;flex-wrap:wrap;}
  .org-tier-1 .staff-photo{width:120px;} .org-tier-2 .staff-photo{width:90px;} .org-tier-3 .staff-photo{width:70px;}
  .org-tier .staff-card{width:150px;}
  .staff-minimal-list{max-width:640px;margin:0 auto;}
  .staff-minimal-item{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid rgba(0,0,0,.08);position:relative;}
  .staff-minimal-item .name{font-weight:700;} .staff-minimal-item .role{color:#6b6b60;font-size:.88rem;}
  .staff-minimal-item.is-new{opacity:.7;border-bottom:2px dashed rgba(0,0,0,.25);}
  .staff-minimal-item .staff-remove{top:8px;right:-30px;}
  .staff-dept-heading{font-size:1.1rem;margin:28px 0 14px;color:var(--primary);}
  .staff-dept-heading:first-child{margin-top:0;}
  .s-note{font-size:.8rem;color:#6b6b60;margin:0 0 14px;display:flex;gap:6px;align-items:center;}
  .s-note .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;}
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
<?php render_variant_switcher($config, $variant, $namedCount); ?>

<div class="save-toast" id="saveToast"><span class="material-symbols-outlined">check_circle</span><span id="saveToastMsg">Saved</span></div>

<section id="staff">
  <div class="wrap">
    <div class="section-head">
      <h2<?= live_color_attr($content, 'title') ?>><?= esc($section['label']) ?></h2>
      <?php if ($hasIntro): ?><p class="staff-head-intro" data-field="intro_text"<?= live_color_attr($content, 'intro_text') ?> contenteditable="true" data-placeholder="Add a short intro (optional)"><?= esc($content['intro_text'] ?? '') ?></p><?php endif; ?>
    </div>

    <?php if ($namedCount === 0): ?>
      <p class="s-note"><span class="material-symbols-outlined">info</span>With no named people yet, this section stays hidden on your live site.</p>
    <?php endif; ?>

    <?php if ($variant === 'carousel'): ?>
      <div class="staff-carousel">
        <?php foreach ($people as $i) staff_card($content, $i, false); ?>
        <?php if ($blank) staff_card($content, $blank, true); ?>
      </div>

    <?php elseif ($variant === 'list_bio'): ?>
      <div class="staff-list-bio">
        <?php foreach (array_merge($people, $blank ? [-$blank] : []) as $k):
            $isNew = $k < 0; $i = abs($k);
            $src = !empty($content["photo_$i"]) ? img($content["photo_$i"]) : '';
        ?>
          <div class="staff-bio-row<?= $isNew ? ' is-new' : '' ?>">
            <?php if (!$isNew) echo staff_remove_btn($i); ?>
            <?php live_photo_block("photo_$i", $src, 'staff-photo'); ?>
            <div style="flex:1;">
              <?= staff_ef($content, $i, 'name', 'div', 'name', $isNew ? 'Add a name' : 'Name', 'names') ?>
              <?= staff_ef($content, $i, 'role', 'div', 'role', 'Role', 'roles') ?>
              <?= staff_ef($content, $i, 'bio', 'p', 'bio', 'Short bio (optional)', 'bios') ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    <?php elseif ($variant === 'org_chart'):
        $ordered = array_merge($people, $blank ? [-$blank] : []);
        $tiers = [array_slice($ordered, 0, 1), array_slice($ordered, 1, 2), array_slice($ordered, 3)];
    ?>
      <p class="s-note"><span class="material-symbols-outlined">account_tree</span>The first person is the top tier, the next two sit below, and everyone else forms the bottom tier.</p>
      <div class="staff-org-chart">
        <?php foreach ($tiers as $t => $tier): if (!$tier) continue; ?>
          <div class="org-tier org-tier-<?= $t + 1 ?>"><?php foreach ($tier as $k) staff_card($content, abs($k), $k < 0); ?></div>
        <?php endforeach; ?>
      </div>

    <?php elseif ($variant === 'minimal'): ?>
      <div class="staff-minimal-list">
        <?php foreach (array_merge($people, $blank ? [-$blank] : []) as $k): $isNew = $k < 0; $i = abs($k); ?>
          <div class="staff-minimal-item<?= $isNew ? ' is-new' : '' ?>">
            <?php if (!$isNew) echo staff_remove_btn($i); ?>
            <?= staff_ef($content, $i, 'name', 'span', 'name', $isNew ? 'Add a name' : 'Name', 'names') ?>
            <?= staff_ef($content, $i, 'role', 'span', 'role', 'Role', 'roles') ?>
          </div>
        <?php endforeach; ?>
      </div>

    <?php elseif ($variant === 'grouped'):
        $groups = [];
        foreach ($people as $i) { $dept = trim($content["department_$i"] ?? '') ?: 'Other'; $groups[$dept][] = $i; }
    ?>
      <p class="s-note"><span class="material-symbols-outlined">category</span>Type a department under each person - they're grouped under a heading for each one.</p>
      <?php foreach ($groups as $deptName => $idxs): ?>
        <h3 class="staff-dept-heading"<?= live_color_attr($content, 'depts') ?>><?= esc($deptName) ?></h3>
        <div class="staff-grid"><?php foreach ($idxs as $i) staff_card($content, $i, false, true); ?></div>
      <?php endforeach; ?>
      <?php if ($blank): ?><h3 class="staff-dept-heading">Add someone</h3><div class="staff-grid"><?php staff_card($content, $blank, true, true); ?></div><?php endif; ?>

    <?php else: /* grid */ ?>
      <div class="staff-grid">
        <?php foreach ($people as $i) staff_card($content, $i, false); ?>
        <?php if ($blank) staff_card($content, $blank, true); ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php render_live_edit_script((int)$section['id'], $variant); ?>
<?php render_live_edit_color_tools((int)$section['id'], $theme); ?>
<script>
(function(){
  var sectionId = <?= (int)$section['id'] ?>;
  // Remove a person: clear every field of that slot in one save, then reload.
  window.somahubRemovePerson = function(i) {
    if (!window.confirm('Remove this person?')) return;
    var fd = new FormData();
    fd.append('section_id', sectionId);
    ['name','role','bio','department'].forEach(function(f){ fd.append(f + '_' + i, ''); });
    fd.append('remove_photo_' + i, '1');
    fetch('section-save-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d.ok) { sessionStorage.setItem('somahubLiveEditNotice', 'Person removed'); window.location.reload(); }
        else if (window.somahubShowSaved) window.somahubShowSaved(false, 'Could not remove');
      });
  };
  // Typing a name into the blank card, or changing a department, changes
  // which cards/groups exist - reload once the save (fired on blur) is done.
  document.querySelectorAll('.is-new [data-field^="name_"], [data-field^="department_"]').forEach(function(el){
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
