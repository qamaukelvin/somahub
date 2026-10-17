<?php
// Navbar for school-owned standalone pages (results check, enrollment form,
// report card). Mirrors the school site: brand + links back to its sections.
// Expects $school (row) and $db in scope. Optional $portalActive = 'results'|'enroll'.
require_once __DIR__ . '/includes/plan.php';
$portalActive = $portalActive ?? '';
$__home = 'https://' . $school['slug'] . '.somahub.top/';
$__keys = [];
try {
    $q = $db->prepare("SELECT st.key_name, st.is_premium FROM site_sections ss JOIN section_types st ON st.id = ss.section_type_id WHERE ss.school_id = ? AND ss.is_visible = 1 ORDER BY ss.position");
    $q->execute([$school['id']]);
    $__locked = is_premium_locked($school);
    foreach ($q->fetchAll() as $r) { if (!($__locked && $r['is_premium'])) $__keys[$r['key_name']] = true; }
} catch (\Throwable $e) {}
$__links = [['Home', $__home, 'home', '']];
foreach ([['about','About','info'],['academics','Academics','menu_book'],['admissions','Admissions','school'],['gallery','Gallery','photo_library'],['staff','Staff','groups'],['fees','Fees','payments']] as $x) {
    if (isset($__keys[$x[0]])) $__links[] = [$x[1], $__home . '#' . $x[0], $x[2], ''];
}
if (isset($__keys['enrollment_form'])) $__links[] = ['Apply', 'https://' . $school['slug'] . '.somahub.top/enrollment-apply.php?school=' . urlencode($school['slug']), 'edit_note', 'enroll'];
if (isset($__keys['results_lookup'])) $__links[] = ['Results', 'https://' . $school['slug'] . '.somahub.top/results-check.php?school=' . urlencode($school['slug']), 'grading', 'results'];
if (isset($__keys['blog'])) $__links[] = ['Blog', $__home . '#blog', 'article', ''];
if (isset($__keys['contact'])) $__links[] = ['Contact', $__home . '#contact', 'mail', ''];
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
<header class="spn no-print">
  <div class="spn-in">
    <a class="spn-brand" href="<?= htmlspecialchars($__home) ?>"><?= htmlspecialchars($school['name']) ?></a>
    <nav><ul id="spnLinks">
      <?php foreach ($__links as $l): ?>
        <li><a href="<?= htmlspecialchars($l[1]) ?>" class="<?= $l[3] !== '' && $l[3] === $portalActive ? 'on' : '' ?>"><span class="material-symbols-outlined"><?= $l[2] ?></span><?= htmlspecialchars($l[0]) ?></a></li>
      <?php endforeach; ?>
    </ul></nav>
    <button type="button" class="spn-toggle" aria-label="Menu" onclick="document.getElementById('spnLinks').classList.toggle('open')"><span class="material-symbols-outlined">menu</span></button>
  </div>
</header>
<style>
  .spn{position:sticky;top:0;z-index:900;background:rgba(255,255,255,.96);backdrop-filter:blur(6px);border-bottom:1px solid #e7e3d6;font-family:inherit;}
  .spn-in{max-width:1100px;margin:0 auto;padding:0 20px;min-height:58px;display:flex;align-items:center;justify-content:space-between;gap:16px;}
  .spn-brand{font-weight:800;font-size:1.05rem;color:var(--primary,#0F5257);text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:60%;}
  .spn ul{list-style:none;margin:0;padding:0;display:flex;gap:2px;align-items:center;flex-wrap:wrap;}
  .spn li a{display:flex;align-items:center;gap:5px;padding:8px 11px;border-radius:9px;font-size:.84rem;font-weight:700;color:#1b1b18;text-decoration:none;}
  .spn li a:hover{background:rgba(0,0,0,.05);} .spn li a.on{background:var(--primary,#0F5257);color:#fff;}
  .spn .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:18px;line-height:1;}
  .spn-toggle{display:none;background:none;border:none;cursor:pointer;color:#1b1b18;padding:6px;}
  @media(max-width:900px){
    .spn-toggle{display:block;}
    .spn ul{display:none;position:absolute;top:100%;left:0;right:0;flex-direction:column;align-items:stretch;background:#fff;padding:10px 14px 14px;border-bottom:1px solid #e7e3d6;box-shadow:0 12px 24px rgba(0,0,0,.1);}
    .spn ul.open{display:flex;} .spn li a{padding:11px 12px;}
  }
</style>
