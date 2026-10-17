<?php
// Live, in-place editor for Fees. Fee rows live in fee_structures (not
// content_json), so cells save through fee-save-ajax.php; the intro and
// summary text save through the normal section save.
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
if (!$section || $section['key_name'] !== 'fees') {
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

require_once __DIR__ . '/../includes/plan.php';
if (is_premium_locked($school)) {
    exit('Fee publishing is a paid-plan feature, or your paid term has ended. <a href="checkout.php">Upgrade now</a> to reactivate.');
}
$content = json_decode($section['content_json'], true) ?: [];
$config = get_section_variant_registry()['fees'];
$variant = $section['layout_variant'] ?? 'table';
$fq = $db->prepare("SELECT * FROM fee_structures WHERE school_id = ? ORDER BY grade, term_label");
$fq->execute([$user['school_id']]);
$fees = $fq->fetchAll();

function fe_cell(string $kind, string $val, string $ph, string $extra = ''): string {
    return '<span class="fe-cell fe-' . $kind . '" data-cell="' . $kind . '" contenteditable="true" data-placeholder="' . $ph . '"' . $extra . '>' . esc($val) . '</span>';
}
function fe_amt($f): string { return fe_cell('amount', number_format((float)$f['amount'], 2), '0.00', live_color_attr($GLOBALS['content'], 'amounts')); }
function fe_del(int $id): string {
    return '<button type="button" class="fe-del" title="Remove this fee" onclick="somahubDeleteFee(' . $id . ')"><span class="material-symbols-outlined">close</span></button>';
}
function fe_row_attrs(array $f): string {
    return ' data-id="' . (int)$f['id'] . '" data-grade="' . esc($f['grade']) . '" data-term="' . esc($f['term_label']) . '" data-amount="' . esc($f['amount']) . '"';
}
function fe_head(string $by, string $val): string {
    return '<h3 class="fe-cell" contenteditable="true" data-group="' . $by . '" data-from="' . esc($val) . '"' . live_color_attr($GLOBALS['content'], 'titles') . '>' . esc($val) . '</h3>';
}

$extraCss = <<<CSS
  section#fees{background:var(--bg);padding:60px 0;min-height:70vh;}
  .text-block{max-width:760px;color:#3a3a34;margin-bottom:20px;}
  table.fee-table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;}
  table.fee-table th{background:var(--primary);color:var(--bg);text-align:left;padding:12px 16px;font-size:.78rem;text-transform:uppercase;}
  table.fee-table td{padding:12px 16px;border-bottom:1px solid rgba(0,0,0,.08);font-size:.9rem;position:relative;}
  .fee-compact-list{max-width:620px;}
  .fee-compact-row{display:flex;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid rgba(0,0,0,.08);font-size:.9rem;position:relative;align-items:baseline;}
  .fee-compact-row .lhs{display:flex;gap:6px;align-items:baseline;flex-wrap:wrap;}
  .fee-compact-row strong{color:var(--primary);}
  .fee-group-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:18px;align-items:start;}
  .fee-group-card{background:#fff;border-radius:10px;padding:20px 22px;box-shadow:0 1px 4px rgba(0,0,0,.05);min-width:0;}
  .fee-group-card h3{font-size:1.05rem;color:var(--primary);margin-bottom:12px;}
  .fee-group-row{display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid rgba(0,0,0,.06);font-size:.88rem;position:relative;align-items:baseline;}
  .fee-group-row strong{color:var(--accent);white-space:nowrap;}
  .callout-block{background:#fff;border-radius:10px;padding:22px 26px;max-width:640px;box-shadow:0 1px 4px rgba(0,0,0,.05);}
  .fe-ksh{font-weight:700;margin-right:4px;}
  .fe-cell{min-width:2ch;display:inline-block;}
  .fe-amount{font-weight:700;}
  .fe-del{position:absolute;top:50%;right:-10px;transform:translateY(-50%);width:22px;height:22px;border-radius:50%;border:2px solid #fff;background:#B3261E;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;opacity:0;transition:opacity .15s;z-index:5;}
  .fe-del .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:14px;}
  .fe-row:hover .fe-del{opacity:1;}
  table.fee-table td.fe-last{padding-right:30px;}
  .fe-new{opacity:.75;} .fe-new:hover,.fe-new:focus-within{opacity:1;}
  .fee-group-card .fe-new{border:2px dashed rgba(0,0,0,.2);border-radius:8px;padding:8px;margin-top:8px;border-bottom:2px dashed rgba(0,0,0,.2);}
  .fe-addwrap{margin-top:20px;max-width:720px;}
  .fe-addwrap .fe-new{display:flex;gap:10px;flex-wrap:wrap;border:2px dashed rgba(0,0,0,.25);border-radius:10px;padding:12px 14px;background:rgba(255,255,255,.5);align-items:baseline;}
  .fe-addwrap .fe-new .fe-cell{border-bottom:1px dashed rgba(0,0,0,.3);min-width:90px;padding:0 4px;}
  .fe-addlabel{font-size:.72rem;font-weight:700;color:#6b6b60;text-transform:uppercase;letter-spacing:.04em;width:100%;}
  .i-note{font-size:.8rem;color:#6b6b60;margin:0 0 14px;display:flex;gap:6px;align-items:center;}
  .i-note .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:16px;}
  .fe-err{color:#B3261E;font-size:.78rem;margin-top:6px;min-height:1em;}
CSS;
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

<section id="fees">
  <div class="wrap">
    <div class="section-head"><h2<?= live_color_attr($content, 'title') ?>><?= esc($section['label']) ?></h2></div>
    <div class="text-block" data-field="intro_text"<?= live_color_attr($content, 'intro_text') ?> contenteditable="true" data-placeholder="Add a short intro, e.g. how and when fees are paid (optional)"><?= nl2p_live($content['intro_text'] ?? '') ?></div>

    <?php if (empty($fees)): ?><p class="i-note"><span class="material-symbols-outlined">info</span>No fees yet. Add your first one below. Until then your live site says fee details are coming soon.</p><?php endif; ?>

    <?php if ($variant === 'summary_only'): ?>
      <p class="i-note"><span class="material-symbols-outlined">info</span>This layout hides the individual fee amounts and shows only the note below. Switch layouts to edit your <?= count($fees) ?> fee<?= count($fees) === 1 ? '' : 's' ?>.</p>
      <div class="callout-block"><div data-field="summary_note"<?= live_color_attr($content, 'summary_note') ?> contenteditable="true" data-placeholder="Fee structures vary by grade and term - contact us for full details."><?= nl2p_live($content['summary_note'] ?? '') ?></div></div>

    <?php elseif ($variant === 'compact_list'): ?>
      <div class="fee-compact-list">
        <?php foreach ($fees as $f): ?>
          <div class="fee-compact-row fe-row"<?= fe_row_attrs($f) ?>>
            <span class="lhs"><?= fe_cell('grade', $f['grade'], 'Grade') ?><span>&mdash;</span><?= fe_cell('term', $f['term_label'], 'Term') ?></span>
            <strong><span class="fe-ksh">KSh</span><?= fe_amt($f) ?></strong>
            <?= fe_del((int)$f['id']) ?>
          </div>
        <?php endforeach; ?>
      </div>

    <?php elseif ($variant === 'by_grade' || $variant === 'by_term'):
        $by = $variant === 'by_grade' ? 'grade' : 'term';
        $groups = [];
        foreach ($fees as $f) $groups[$by === 'grade' ? $f['grade'] : $f['term_label']][] = $f;
    ?>
      <div class="fee-group-grid">
        <?php foreach ($groups as $gName => $rows): ?>
          <div class="fee-group-card">
            <?= fe_head($by, (string)$gName) ?>
            <?php foreach ($rows as $f): ?>
              <div class="fee-group-row fe-row"<?= fe_row_attrs($f) ?>>
                <?= $by === 'grade' ? fe_cell('term', $f['term_label'], 'Term') : fe_cell('grade', $f['grade'], 'Grade') ?>
                <strong><span class="fe-ksh">KSh</span><?= fe_amt($f) ?></strong>
                <?= fe_del((int)$f['id']) ?>
              </div>
            <?php endforeach; ?>
            <div class="fee-group-row fe-new" data-grade="<?= $by === 'grade' ? esc($gName) : '' ?>" data-term="<?= $by === 'term' ? esc($gName) : '' ?>">
              <?= $by === 'grade' ? fe_cell('term', '', 'Add term') : fe_cell('grade', '', 'Add grade') ?>
              <strong><span class="fe-ksh">KSh</span><?= fe_cell('amount', '', '0.00') ?></strong>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    <?php else: /* table */ ?>
      <table class="fee-table">
        <tr><th>Grade</th><th>Term</th><th>Amount</th></tr>
        <?php foreach ($fees as $f): ?>
          <tr class="fe-row"<?= fe_row_attrs($f) ?>><td><?= fe_cell('grade', $f['grade'], 'Grade') ?></td><td><?= fe_cell('term', $f['term_label'], 'Term') ?></td><td class="fe-last"><span class="fe-ksh">KSh</span><?= fe_amt($f) ?><?= fe_del((int)$f['id']) ?></td></tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>

    <?php if ($variant !== 'summary_only'): ?>
    <div class="fe-addwrap">
      <div class="fe-new" data-grade="" data-term="">
        <span class="fe-addlabel">Add a fee</span>
        <?= fe_cell('grade', '', 'Grade, e.g. Grade 4') ?>
        <?= fe_cell('term', '', 'Term, e.g. Term 1') ?>
        <span><span class="fe-ksh">KSh</span><?= fe_cell('amount', '', '0.00') ?></span>
      </div>
      <div class="fe-err" id="feErr"></div>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php render_live_edit_script((int)$section['id'], $variant); ?>
<?php render_live_edit_color_tools((int)$section['id'], $theme); ?>
<script>
(function(){
  function post(data){
    var fd = new FormData(); Object.keys(data).forEach(function(k){ fd.append(k, data[k]); });
    return fetch('fee-save-ajax.php', {method:'POST', body:fd, credentials:'same-origin'}).then(function(r){ return r.json(); });
  }
  function fail(msg){
    var e = document.getElementById('feErr'); if (e) e.textContent = msg;
    if (window.somahubShowSaved) window.somahubShowSaved(false, msg);
  }
  function reload(msg){ sessionStorage.setItem('somahubLiveEditNotice', msg); location.reload(); }
  function txt(el){ return el.innerText.replace(/\s+/g,' ').trim(); }
  function vals(row){
    var v = {grade: row.dataset.grade || '', term: row.dataset.term || '', amount: row.dataset.amount || ''};
    row.querySelectorAll('[data-cell]').forEach(function(c){ v[c.dataset.cell] = txt(c); });
    return v;
  }
  // Existing fee rows: save the row when a cell loses focus and something changed.
  document.querySelectorAll('.fe-row').forEach(function(row){
    row.addEventListener('focusout', function(e){
      if (!e.target.matches('[data-cell]')) return;
      var v = vals(row);
      var oldAmt = parseFloat(row.dataset.amount) || 0, newAmt = parseFloat(String(v.amount).replace(/[^0-9.]/g,''));
      var changedKey = v.grade !== row.dataset.grade || v.term !== row.dataset.term;
      if (!changedKey && newAmt === oldAmt) return;
      post({action:'save', id: row.dataset.id, grade: v.grade, term_label: v.term, amount: v.amount}).then(function(d){
        if (!d.ok) { fail(d.error || 'Could not save'); return; }
        if (changedKey) reload('Fee updated');
        else { row.dataset.amount = newAmt; if (window.somahubShowSaved) window.somahubShowSaved(true, 'Saved'); }
      });
    });
  });
  // New fee rows: create once grade, term and amount are all filled.
  document.querySelectorAll('.fe-new').forEach(function(row){
    var busy = false;
    row.addEventListener('focusout', function(e){
      if (busy || !e.target.matches('[data-cell]')) return;
      var v = vals(row);
      if (!v.grade || !v.term || !v.amount) return;
      busy = true;
      post({action:'save', grade: v.grade, term_label: v.term, amount: v.amount}).then(function(d){
        busy = false;
        if (!d.ok) { fail(d.error || 'Could not save'); return; }
        reload('Fee added');
      });
    });
  });
  // Group headings (grade or term) rename every row in the group.
  document.querySelectorAll('[data-group]').forEach(function(h){
    h.addEventListener('blur', function(){
      var to = txt(h); if (to === h.dataset.from) return;
      if (!to) { h.textContent = h.dataset.from; return; }
      post({action:'rename_group', by: h.dataset.group, from: h.dataset.from, to: to}).then(function(d){
        if (!d.ok) { h.textContent = h.dataset.from; fail(d.error || 'Could not rename'); return; }
        reload('Renamed');
      });
    });
  });
  window.somahubDeleteFee = function(id){
    if (!confirm('Remove this fee?')) return;
    post({action:'delete', id:id}).then(function(d){ if (d.ok) reload('Fee removed'); else fail(d.error || 'Could not remove'); });
  };
})();
</script>
</body>
</html>
