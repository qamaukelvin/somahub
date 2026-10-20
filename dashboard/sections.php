<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/section_variants.php';
$user = require_school_login();
$db = get_db();
$schoolId = $user['school_id'];
// The section list is retired: everything is edited on the site itself.
$__sl = $db->prepare('SELECT slug FROM schools WHERE id = ?'); $__sl->execute([$schoolId]); $__slug = $__sl->fetchColumn();
if ($__slug && empty($_GET['error'])) { header('Location: https://' . $__slug . '.somahub.top/?edit=1'); exit; }

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
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
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
  .accordion-panel .remove-photo-label{font-size:0.78rem;color:#a33;display:flex;align-items:center;gap:5px;cursor:pointer;white-space:nowrap;}
  .accordion-panel .repeatable-item{border:1px solid #e2e2da;border-radius:8px;padding:14px;margin-bottom:14px;background:#fafaf6;}
  .accordion-panel .repeatable-item-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
  .accordion-panel .repeatable-item-header strong{font-size:0.88rem;color:#333;}
  .accordion-panel .remove-item-btn{background:none;border:1px solid #d99;color:#a33;font-size:0.76rem;padding:4px 10px;border-radius:6px;cursor:pointer;}
  .accordion-panel .add-item-btn{display:block;width:100%;background:#fff;border:1.5px dashed #0F5257;color:#0F5257;font-weight:700;font-size:0.85rem;padding:10px;border-radius:8px;cursor:pointer;margin-bottom:14px;}
  .accordion-panel .bulk-upload-field{border-bottom:1px solid #e2e2da;padding-bottom:14px;margin-bottom:14px;}
  .accordion-panel .field-hint{font-size:0.74rem;color:#888;margin-top:4px;}
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

  .page-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:4px;}
  .page-head h1{margin:0;}
  .add-section-btn{display:inline-flex;align-items:center;gap:6px;background:#0F5257;color:#fff;border:none;border-radius:999px;padding:11px 20px 11px 14px;font-size:.9rem;font-weight:700;cursor:pointer;box-shadow:0 2px 8px rgba(15,82,87,.3);transition:transform .12s,box-shadow .12s;}
  .add-section-btn:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(15,82,87,.4);}
  .add-section-btn .material-symbols-outlined,.as-card .material-symbols-outlined,.as-close .material-symbols-outlined{font-family:'Material Symbols Outlined';font-size:22px;}
  #addSectionModal{display:none;position:fixed;inset:0;background:rgba(20,20,16,.55);z-index:9000;align-items:flex-start;justify-content:center;padding:5vh 16px;overflow:auto;}
  #addSectionModal.open{display:flex;}
  .as-box{background:#fff;border-radius:16px;width:min(860px,100%);padding:22px 24px 26px;box-shadow:0 20px 60px rgba(0,0,0,.35);}
  .as-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;}
  .as-top h2{margin:0;font-size:1.25rem;}
  .as-close{background:none;border:none;cursor:pointer;color:#666;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;}
  .as-close:hover{background:#f0ede2;}
  .as-sub{color:#777;font-size:.85rem;margin:0 0 14px;}
  .as-cat{font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#8a8a7e;margin:18px 0 8px;}
  .as-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;}
  .as-card{display:flex;align-items:center;gap:12px;text-align:left;background:#faf8f1;border:1.5px solid #E2DCC6;border-radius:12px;padding:12px 14px;cursor:pointer;font-family:inherit;color:#222;transition:border-color .12s,background .12s,transform .12s;}
  .as-card:hover:not(:disabled){border-color:#0F5257;background:#fff;transform:translateY(-1px);}
  .as-card .ico{width:40px;height:40px;border-radius:10px;background:#0F5257;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
  .as-card strong{display:block;font-size:.9rem;}
  .as-card small{display:block;color:#888;font-size:.74rem;line-height:1.3;margin-top:2px;}
  .as-card:disabled{opacity:.55;cursor:not-allowed;}
  .as-card .paid{font-size:.64rem;font-weight:700;background:#FBF0D1;color:#8C6D1F;border-radius:10px;padding:1px 7px;margin-left:6px;vertical-align:middle;}
  select{padding:10px;border-radius:4px;border:1px solid #ccc;width:100%;max-width:320px;margin-bottom:10px;font-size:0.9rem;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <div class="page-head">
    <h1>Edit Your Website</h1>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <a class="add-section-btn" style="text-decoration:none;background:#F2A65A;color:#0A3A3E;" href="https://<?= htmlspecialchars($school['slug']) ?>.somahub.top/?edit=1"><span class="material-symbols-outlined">edit_square</span>Edit on your site</a>
      <button type="button" class="add-section-btn" onclick="openAddSection()"><span class="material-symbols-outlined">add</span>Add section</button>
    </div>
  </div>
  <div style="background:#FFF8EC;border:2px solid #F2A65A;border-radius:12px;padding:14px 18px;margin:0 0 16px;font-size:.9rem;display:flex;gap:14px;align-items:center;flex-wrap:wrap;">
    <span style="flex:1;min-width:220px;"><strong>Easiest way to edit:</strong> open your real site and click what you want to change. Everything on this page is also there, plus undo.</span>
    <a href="https://<?= htmlspecialchars($school['slug']) ?>.somahub.top/?edit=1" style="background:#0F5257;color:#fff;text-decoration:none;font-weight:800;border-radius:999px;padding:10px 18px;white-space:nowrap;">Edit on your site &rarr;</a>
  </div>
  <p class="sub">Advanced: drag <span class="drag-handle" style="display:inline;padding:0;">☰</span> to reorder. On desktop, click Edit. On mobile, tap ✎ to edit inline. The palette icon opens layout options for sections that support them.</p>

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

</main>

<?php
$typeIcons = ['hero'=>'flag','about'=>'info','gallery'=>'photo_library','staff'=>'groups','testimonials'=>'format_quote','faq'=>'quiz','blog'=>'article','stats'=>'bar_chart','contact'=>'call','admissions'=>'how_to_reg','academics'=>'school','cta_banner'=>'campaign','fees'=>'payments','enrollment_form'=>'assignment','results_lookup'=>'fact_check'];
$typeHints = ['hero'=>'The big welcome at the top','about'=>'Tell your school\'s story','gallery'=>'Photos of school life','staff'=>'Teachers and leadership','testimonials'=>'What parents say','faq'=>'Answers to common questions','blog'=>'News and updates','stats'=>'Key numbers at a glance','contact'=>'Address, phone, map','admissions'=>'How families join','academics'=>'Curriculum and grade levels','cta_banner'=>'A call-to-action banner','fees'=>'Published fee structure','enrollment_form'=>'Online applications','results_lookup'=>'Parents check results'];
?>
<div id="addSectionModal" onclick="if(event.target===this)closeAddSection()">
  <div class="as-box" role="dialog" aria-label="Add a section">
    <div class="as-top"><h2>Add a section</h2><button type="button" class="as-close" onclick="closeAddSection()" title="Close"><span class="material-symbols-outlined">close</span></button></div>
    <p class="as-sub">Pick one and it opens straight in the live editor, so you can fill it in right where it will appear.</p>
    <form method="POST" action="section-add.php">
      <?php foreach ($groupedTypes as $category => $types): ?>
        <div class="as-cat"><?= htmlspecialchars($category) ?></div>
        <div class="as-grid">
          <?php foreach ($types as $t): $locked = $t['is_premium'] && $school['plan'] === 'free'; ?>
            <button type="submit" name="section_type_id" value="<?= (int)$t['id'] ?>" class="as-card"<?= $locked ? ' disabled title="Part of the paid plan. Upgrade to add this section."' : '' ?>>
              <span class="ico"><span class="material-symbols-outlined"><?= htmlspecialchars($typeIcons[$t['key_name']] ?? 'widgets') ?></span></span>
              <span><strong><?= htmlspecialchars($t['label']) ?><?php if ($locked): ?><span class="paid">Paid</span><?php endif; ?></strong><small><?= htmlspecialchars($typeHints[$t['key_name']] ?? '') ?></small></span>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </form>
  </div>
</div>
<script>
function openAddSection(){ document.getElementById('addSectionModal').classList.add('open'); }
function closeAddSection(){ document.getElementById('addSectionModal').classList.remove('open'); }
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeAddSection(); });
</script>

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
let openDesignAccordionId = null;

function closeAccordion(id) {
    const panel = document.getElementById('accordion-' + id);
    if (panel) { panel.classList.remove('open'); panel.innerHTML = ''; }
    if (openAccordionId === id) openAccordionId = null;
}

function closeDesignAccordion(id) {
    const panel = document.getElementById('design-accordion-' + id);
    if (panel) { panel.classList.remove('open'); panel.innerHTML = ''; }
    if (openDesignAccordionId === id) openDesignAccordionId = null;
}

function toggleAccordion(id) {
    const panel = document.getElementById('accordion-' + id);

    // Only one accordion open at a time, period - close any other Edit
    // panel, and close this row's Design panel too if it's open.
    if (openAccordionId !== null && openAccordionId !== id) closeAccordion(openAccordionId);
    if (openDesignAccordionId !== null) closeDesignAccordion(openDesignAccordionId);

    if (panel.classList.contains('open')) {
        closeAccordion(id);
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
                submitInlineForm(form, id, 'section-save-ajax.php', 'Save Changes');
            });
        });
}

function toggleDesignAccordion(id) {
    const panel = document.getElementById('design-accordion-' + id);

    if (openDesignAccordionId !== null && openDesignAccordionId !== id) closeDesignAccordion(openDesignAccordionId);
    if (openAccordionId !== null) closeAccordion(openAccordionId);

    if (panel.classList.contains('open')) {
        closeDesignAccordion(id);
        return;
    }

    panel.innerHTML = '<div class="accordion-loading">Loading…</div>';
    panel.classList.add('open');
    openDesignAccordionId = id;

    fetch('section-design-fragment.php?id=' + id)
        .then(res => res.text())
        .then(html => {
            panel.innerHTML = html;
            const form = panel.querySelector('.inline-design-form');
            if (!form) return;
            form.addEventListener('submit', e => {
                e.preventDefault();
                submitInlineForm(form, id, 'section-design-save-ajax.php', 'Save Design');
            });
        });
}

// Shared by both Edit and Design forms - same save-button behavior either
// way, just posts to whichever endpoint the caller passed in.
// Builds one item's field HTML from the <template> stamped out in the
// fetched fragment - shared by the Add button for every repeatable
// section type (Staff, Testimonials, Gallery, FAQ, ...). Defined here
// rather than in the AJAX-fetched fragment itself: scripts injected via
// .innerHTML never execute, so defining these inside the fragment meant
// they never actually existed - this was the root cause of "Add Another"
// silently doing nothing anywhere it was used.
function somahubBuildRepeatableItemHtml(index, itemLabel, displayNum, templateId, namePattern) {
    var tpl = document.getElementById(templateId);
    var wrapper = document.createElement('div');
    wrapper.className = 'repeatable-item';
    wrapper.dataset.index = index;

    var header = document.createElement('div');
    header.className = 'repeatable-item-header';
    header.innerHTML = '<strong>' + itemLabel + ' ' + displayNum + '</strong> <button type="button" class="remove-item-btn" onclick="somahubRemoveRepeatableItem(this)">Remove</button>';
    wrapper.appendChild(header);

    var SOMAHUB_ICON_CHOICES = [
        ['', 'No icon'],
        ['school', 'Graduation cap'],
        ['groups', 'People'],
        ['calendar_month', 'Calendar'],
        ['menu_book', 'Book'],
        ['emoji_events', 'Trophy'],
        ['star', 'Star'],
        ['schedule', 'Clock'],
        ['bar_chart', 'Chart'],
        ['diversity_3', 'Community'],
        ['workspace_premium', 'Award'],
    ];

    tpl.content.querySelectorAll('[data-stem]').forEach(function(fieldTpl) {
        var stem = fieldTpl.dataset.stem, type = fieldTpl.dataset.type, label = fieldTpl.dataset.label;
        // Matches PHP's format_item_field_name() - same {stem}/{index}
        // placeholder pattern, so Stats' stat_{index}_{stem} naming works
        // here too, not just the standard {stem}_{index}.
        var name = (namePattern || '{stem}_{index}').replace('{stem}', stem).replace('{index}', index);
        var fieldDiv = document.createElement('div');
        fieldDiv.className = 'field';
        var inputHtml = '';
        if (name.endsWith('_icon')) {
            inputHtml = '<select name="' + name + '">' + SOMAHUB_ICON_CHOICES.map(function(pair) {
                return '<option value="' + pair[0] + '">' + pair[1] + '</option>';
            }).join('') + '</select>';
        } else if (type === 'textarea') {
            inputHtml = '<textarea name="' + name + '"></textarea>';
        } else if (type === 'image') {
            inputHtml = '<div class="image-field"><input type="file" name="' + name + '" accept="image/*"></div>';
        } else {
            inputHtml = '<input type="text" name="' + name + '">';
        }
        fieldDiv.innerHTML = '<label>' + label + '</label>' + inputHtml;
        wrapper.appendChild(fieldDiv);
    });

    return wrapper;
}

function somahubAddRepeatableItem(sectionId, index, maxItems, itemLabel, namePattern) {
    var container = document.getElementById('repeatable-' + sectionId);
    // Count only still-visible items - a removed item stays in the DOM
    // (display:none) so Save can still clear it, so querySelectorAll alone
    // would count it too and throw off the numbering of newly-added items.
    var visibleCount = Array.prototype.filter.call(
        container.querySelectorAll('.repeatable-item'),
        function(el) { return el.style.display !== 'none'; }
    ).length;
    var newItem = somahubBuildRepeatableItemHtml(index, itemLabel, visibleCount + 1, 'itemFieldTemplates-' + sectionId, namePattern);
    container.appendChild(newItem);


    if (index >= maxItems) {
        document.getElementById('addItemBtn-' + sectionId).remove();
    } else {
        var btn = document.getElementById('addItemBtn-' + sectionId);
        btn.setAttribute('onclick', "somahubAddRepeatableItem(" + sectionId + ", " + (index + 1) + ", " + maxItems + ", '" + itemLabel.replace(/'/g, "\\'") + "', '" + (namePattern || '{stem}_{index}').replace(/'/g, "\\'") + "')");
    }
}

// "Remove" blanks every field in the item (so Save actually clears it -
// fields not submitted are preserved, not wiped, so an item can't be
// cleared just by hiding it) then hides the card. Reuses the existing
// photo-remove checkbox mechanism for image fields.
function somahubRemoveRepeatableItem(button) {
    var item = button.closest('.repeatable-item');
    item.querySelectorAll('input[type=text], textarea').forEach(function(el) { el.value = ''; });
    item.querySelectorAll('input[type=file]').forEach(function(el) { el.value = ''; });
    item.querySelectorAll('input[type=checkbox][name^="remove_"]').forEach(function(el) { el.checked = true; });
    item.style.display = 'none';
}

function submitInlineForm(form, id, endpoint, savedButtonText) {
    const msg = form.querySelector('.inline-form-msg');
    const btn = form.querySelector('button[type=submit]');
    msg.textContent = '';
    msg.className = 'inline-form-msg';
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const formData = new FormData(form);
    formData.append('section_id', id);

    fetch(endpoint, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = savedButtonText;
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
            btn.textContent = savedButtonText;
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
<?php if (isset($_GET['welcome'])): ?>
<style>
.wm-back{position:fixed;inset:0;background:rgba(15,30,32,.55);display:flex;align-items:center;justify-content:center;z-index:9999;padding:16px;}
.wm-card{background:#fff;border-radius:18px;max-width:480px;width:100%;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.3);}
.wm-card h2{margin:0 0 6px;font-size:1.4rem;} .wm-card p{margin:0 0 14px;color:#4a5a5c;font-size:.92rem;line-height:1.5;}
.wm-steps{list-style:none;margin:0 0 20px;padding:0;display:grid;gap:12px;}
.wm-steps li{display:flex;gap:12px;align-items:flex-start;font-size:.9rem;line-height:1.4;}
.wm-steps .material-symbols-outlined{font-family:'Material Symbols Outlined';color:#0F5257;background:#e3f1f2;border-radius:10px;padding:7px;font-size:20px;flex:0 0 auto;}
.wm-btn{width:100%;background:#0F5257;color:#fff;border:none;border-radius:999px;padding:13px;font-weight:800;font-size:.95rem;cursor:pointer;}
</style>
<div class="wm-back" id="welcomeModal" role="dialog" aria-modal="true">
  <div class="wm-card">
    <h2>Welcome to Somahub, <?= htmlspecialchars(explode(' ', trim($user['name'] ?? ''))[0] ?: 'there') ?>!</h2>
    <p>Your school website is already live. Here is how to make it yours:</p>
    <ul class="wm-steps">
      <li><span class="material-symbols-outlined">edit</span><div><strong>Edit each section.</strong> Open any section below and click text or photos right on the page to change them.</div></li>
      <li><span class="material-symbols-outlined">palette</span><div><strong>Pick your colours.</strong> Open the Hero editor and use the Colours panel at the top.</div></li>
      <li><span class="material-symbols-outlined">add_circle</span><div><strong>Add or hide sections.</strong> Use "Add section" at the top. Fees stays hidden until you add a fee.</div></li>
      <li><span class="material-symbols-outlined">verified</span><div><strong>Verify your school</strong> when you are ready, and all features stay free for your 60-day trial.</div></li>
    </ul>
    <button type="button" class="wm-btn" onclick="document.getElementById('welcomeModal').remove();history.replaceState(null,'',location.pathname);">Got it, let's start</button>
  </div>
</div>
<?php endif; ?>
</body>
</html>
