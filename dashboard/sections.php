<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
$db = get_db();
$schoolId = $user['school_id'];

$sections = $db->prepare("
    SELECT ss.id, ss.position, ss.is_visible, ss.content_json, st.key_name, st.label, st.is_premium
    FROM site_sections ss
    JOIN section_types st ON st.id = ss.section_type_id
    WHERE ss.school_id = ?
    ORDER BY ss.position ASC
");
$sections->execute([$schoolId]);
$sections = $sections->fetchAll();

$visibleSections = array_filter($sections, fn($s) => $s['is_visible']);
$hiddenSections = array_filter($sections, fn($s) => !$s['is_visible']);

$stmt = $db->prepare("SELECT * FROM schools WHERE id=?");
$stmt->execute([$schoolId]);
$school = $stmt->fetch();

$availableTypes = $db->query("SELECT * FROM section_types ORDER BY category, label")->fetchAll();
$groupedTypes = [];
foreach ($availableTypes as $t) {
    $groupedTypes[$t['category'] ?? 'Other'][] = $t;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Website — Sections</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .section-row{background:#fff;border:1px solid #E2DCC6;border-radius:8px;padding:14px 16px;margin-bottom:10px;}
  .section-row.dragging{opacity:0.4;}
  .row-top{display:flex;align-items:center;gap:14px;}
  .drag-handle{color:#999;font-size:1.2rem;cursor:grab;touch-action:none;user-select:none;padding:4px;flex-shrink:0;}
  .drag-handle:active{cursor:grabbing;}
  .section-info{flex:1;min-width:0;}
  .section-info strong{display:block;font-size:0.95rem;}
  .section-info span{font-size:0.78rem;color:#888;}
  .badge-hidden{background:#eee;color:#888;font-size:0.7rem;padding:2px 8px;border-radius:10px;margin-left:8px;}
  .badge-premium{background:#FBF0D1;color:#8C6D1F;font-size:0.7rem;padding:2px 8px;border-radius:10px;margin-left:8px;}

  /* DESKTOP — original inline button row */
  .desktop-actions{display:flex;gap:6px;flex-shrink:0;}
  .desktop-actions a, .desktop-actions button{font-size:0.8rem;padding:6px 10px;border-radius:4px;border:1px solid #ddd;background:#fff;cursor:pointer;text-decoration:none;color:#333;}
  .desktop-actions .danger{color:#8C3B2E;border-color:#e0c4bc;}
  .mobile-actions{display:none;}

  /* MOBILE — pencil + accordion + ... menu */
  @media(max-width:760px){
    .desktop-actions{display:none;}
    .mobile-actions{display:flex;align-items:center;gap:8px;flex-shrink:0;}
    .icon-btn{width:36px;height:36px;border:1px solid #ddd;border-radius:6px;background:#fafafa;font-size:1.05rem;cursor:pointer;}
    .row-menu{position:relative;}
    .row-menu summary{list-style:none;width:36px;height:36px;display:flex;align-items:center;justify-content:center;border:1px solid #ddd;border-radius:6px;cursor:pointer;font-size:1.1rem;color:#666;background:#fafafa;}
    .row-menu summary::-webkit-details-marker{display:none;}
    .menu-items{position:absolute;right:0;top:40px;background:#fff;border:1px solid #ddd;border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,0.12);min-width:150px;z-index:20;overflow:hidden;}
    .menu-items button{display:block;width:100%;text-align:left;padding:11px 14px;border:none;background:none;font-size:0.85rem;cursor:pointer;color:#333;}
    .menu-items button:hover{background:#f4f1e6;}
    .menu-items .danger{color:#8C3B2E;}
  }

  /* Inline accordion editor (mobile only, but styled here regardless) */
  .accordion-panel{display:none;border-top:1px solid #eee;margin-top:12px;padding-top:14px;}
  .accordion-panel.open{display:block;}
  .accordion-panel .field{margin-bottom:16px;}
  .accordion-panel label{display:block;font-size:0.8rem;font-weight:600;margin-bottom:5px;color:#333;}
  .accordion-panel input[type=text], .accordion-panel textarea{width:100%;padding:9px;border:1px solid #ccc;border-radius:4px;font-family:inherit;box-sizing:border-box;}
  .accordion-panel textarea{min-height:90px;}
  .accordion-panel .image-field{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
  .accordion-panel .current-img{width:64px;height:64px;object-fit:cover;border-radius:6px;flex-shrink:0;}
  .accordion-panel input[type=file]{font-size:0.8rem;flex:1;min-width:160px;}
  .accordion-panel .inline-form-msg{font-size:0.85rem;margin:10px 0;}
  .accordion-panel .inline-form-msg.success{color:#1B4D3E;}
  .accordion-panel .inline-form-msg.error{color:#8C3B2E;}
  .accordion-loading{padding:20px;text-align:center;color:#888;font-size:0.85rem;}

  .hidden-toggle{margin-top:28px;}
  .hidden-toggle summary{cursor:pointer;font-size:0.9rem;font-weight:600;color:#666;padding:10px 0;list-style:none;}
  .hidden-toggle summary::-webkit-details-marker{display:none;}
  .hidden-toggle summary::before{content:"▸ ";}
  .hidden-toggle[open] summary::before{content:"▾ ";}
  .hidden-toggle .section-row{opacity:0.7;}

  .add-section{margin-top:28px;padding:18px;background:#F4F1E6;border-radius:8px;}
  select{padding:10px;border-radius:4px;border:1px solid #ccc;width:100%;max-width:320px;margin-bottom:10px;font-size:0.9rem;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Edit Your Website</h1>
  <p class="sub">Drag <span class="drag-handle" style="display:inline;padding:0;">☰</span> to reorder. On desktop, click Edit. On mobile, tap ✎ to edit inline.</p>

  <?php if (isset($_GET['error']) && $_GET['error'] === 'upgrade_required'): ?>
    <div style="background:#FBE8E4;color:#8C3B2E;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:0.88rem;">
      That section is part of the paid plan. <a href="checkout.php" style="color:#8C3B2E;font-weight:600;">Upgrade now</a>.
    </div>
  <?php endif; ?>

  <div id="section-list">
    <?php foreach ($visibleSections as $s): ?>
      <?php include __DIR__ . '/_section_row.php'; ?>
    <?php endforeach; ?>
    <?php if (!$visibleSections): ?>
      <p style="color:#888;font-size:0.9rem;">No sections yet — add your first one below.</p>
    <?php endif; ?>
  </div>

  <?php if ($hiddenSections): ?>
    <details class="hidden-toggle">
      <summary>Hidden Sections (<?= count($hiddenSections) ?>)</summary>
      <div id="hidden-section-list">
        <?php foreach ($hiddenSections as $s): ?>
          <?php include __DIR__ . '/_section_row.php'; ?>
        <?php endforeach; ?>
      </div>
    </details>
  <?php endif; ?>

  <div class="add-section">
    <form method="POST" action="section-add.php">
      <label style="display:block;margin-bottom:8px;"><strong>Add a section</strong></label>
      <select name="section_type_id" required>
        <option value="">Choose a section type…</option>
        <?php foreach ($groupedTypes as $category => $types): ?>
          <optgroup label="<?= htmlspecialchars($category) ?>">
            <?php foreach ($types as $t): ?>
              <option value="<?= $t['id'] ?>">
                <?= htmlspecialchars($t['label']) ?><?= $t['is_premium'] && $school['plan'] === 'free' ? ' (Paid plan)' : '' ?>
              </option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select><br>
      <button type="submit" class="btn">Add Section</button>
    </form>
  </div>
</main>

<script>
/* ---------- Drag to reorder — Pointer Events, works on mouse AND touch ---------- */
let dragEl = null;

document.querySelectorAll('.drag-handle').forEach(handle => {
    handle.addEventListener('pointerdown', e => {
        e.preventDefault();
        dragEl = handle.closest('.section-row');
        handle.setPointerCapture(e.pointerId);
        dragEl.classList.add('dragging');
    });

    handle.addEventListener('pointermove', e => {
        if (!dragEl) return;
        const list = dragEl.closest('#section-list, #hidden-section-list');
        if (!list) return;
        const rows = [...list.querySelectorAll('.section-row:not(.dragging)')];
        const after = rows.find(row => {
            const box = row.getBoundingClientRect();
            return e.clientY < box.top + box.height / 2;
        });
        if (after) list.insertBefore(dragEl, after);
        else list.appendChild(dragEl);
    });

    handle.addEventListener('pointerup', () => {
        if (!dragEl) return;
        dragEl.classList.remove('dragging');
        saveOrder();
        dragEl = null;
    });
});

function saveOrder() {
    // Combine visible + hidden lists — position spans both
    const visibleIds = [...document.querySelectorAll('#section-list .section-row')].map(r => r.dataset.id);
    const hiddenList = document.getElementById('hidden-section-list');
    const hiddenIds = hiddenList ? [...hiddenList.querySelectorAll('.section-row')].map(r => r.dataset.id) : [];
    const order = [...visibleIds, ...hiddenIds];

    fetch('section-reorder.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ order })
    });
}

/* ---------- Mobile inline accordion editor ---------- */
let openAccordionId = null;

function toggleAccordion(id) {
    const panel = document.getElementById('accordion-' + id);

    // Close whichever accordion is currently open, if it's a different one
    if (openAccordionId !== null && openAccordionId !== id) {
        const prev = document.getElementById('accordion-' + openAccordionId);
        if (prev) { prev.classList.remove('open'); prev.innerHTML = ''; }
    }

    if (panel.classList.contains('open')) {
        panel.classList.remove('open');
        panel.innerHTML = '';
        openAccordionId = null;
        return;
    }

    panel.innerHTML = '<div class="accordion-loading">Loading…</div>';
    panel.classList.add('open');
    openAccordionId = id;

    fetch('section-edit-fragment.php?id=' + id)
        .then(res => res.text())
        .then(html => {
            panel.innerHTML = html;
            const form = panel.querySelector('.inline-edit-form');
            form.addEventListener('submit', e => {
                e.preventDefault();
                submitInlineForm(form, id);
            });
        });
}

function submitInlineForm(form, id) {
    const msg = form.querySelector('.inline-form-msg');
    const btn = form.querySelector('button[type=submit]');
    msg.textContent = '';
    msg.className = 'inline-form-msg';
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const formData = new FormData(form);
    formData.append('section_id', id);

    fetch('section-save-ajax.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = 'Save Changes';
            if (data.ok) {
                msg.textContent = 'Saved.';
                msg.classList.add('success');
            } else {
                const firstError = Object.values(data.errors)[0] || 'Something went wrong.';
                msg.textContent = firstError;
                msg.classList.add('error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = 'Save Changes';
            msg.textContent = 'Network error — please try again.';
            msg.classList.add('error');
        });
}

/* ---------- Shared actions (both desktop buttons and mobile menu use these) ---------- */
function duplicateSection(id) {
    fetch('section-duplicate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id })
    }).then(() => location.reload());
}

function toggleVisibility(id) {
    fetch('section-toggle.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id })
    }).then(() => location.reload());
}

function removeSection(id) {
    if (!confirm('Remove this section? You can re-add it later, but its content will be lost.')) return;
    fetch('section-remove.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id })
    }).then(() => location.reload());
}
</script>
</body>
</html>
