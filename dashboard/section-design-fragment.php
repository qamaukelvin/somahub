<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
$db = get_db();

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("
    SELECT ss.*, st.label, st.key_name
    FROM site_sections ss
    JOIN section_types st ON st.id = ss.section_type_id
    WHERE ss.id = ? AND ss.school_id = ?
");
$stmt->execute([$id, $user['school_id']]);
$section = $stmt->fetch();

if (!$section) {
    http_response_code(404);
    exit('Section not found.');
}

// Only section types with a real, built variant system get real options here.
// Keep this list in sync with dashboard/_section_row.php's design-button check.
$variantOptionsByType = [
    'hero' => [
        'default' => ['label' => 'Side-by-side photo', 'icon' => 'view_agenda'],
        'background' => ['label' => 'Full-bleed background photo', 'icon' => 'panorama'],
        'carousel' => ['label' => 'Rotating photo carousel', 'icon' => 'view_carousel'],
    ],
];

$options = $variantOptionsByType[$section['key_name']] ?? null;
$current = $section['layout_variant'] ?? 'default';
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
<style>
  .material-symbols-outlined{font-family:'Material Symbols Outlined';font-weight:normal;font-style:normal;font-size:26px;line-height:1;display:block;}
  .design-variant-grid{display:flex;gap:10px;flex-wrap:wrap;}
  .design-variant-option{display:flex;flex-direction:column;align-items:center;gap:6px;border:1.5px solid #ccc;border-radius:10px;padding:14px 16px;cursor:pointer;min-width:110px;text-align:center;transition:border-color .15s;}
  .design-variant-option input{position:absolute;opacity:0;pointer-events:none;}
  .design-variant-option.selected{border-color:#0F5257;box-shadow:0 0 0 2px rgba(15,82,87,0.15);background:#F4F8F6;}
  .design-variant-label{font-size:0.78rem;font-weight:600;}
</style>
<form class="inline-design-form" data-section-id="<?= $section['id'] ?>">
  <?php if ($options): ?>
    <label style="font-weight:700;display:block;margin-bottom:10px;">Layout</label>
    <div class="design-variant-grid">
      <?php foreach ($options as $value => $opt): ?>
        <label class="design-variant-option<?= $current === $value ? ' selected' : '' ?>">
          <input type="radio" name="layout_variant" value="<?= htmlspecialchars($value) ?>" <?= $current === $value ? 'checked' : '' ?> onchange="this.closest('.design-variant-grid').querySelectorAll('.design-variant-option').forEach(o=>o.classList.remove('selected'));this.closest('.design-variant-option').classList.add('selected');">
          <span class="material-symbols-outlined" aria-hidden="true"><?= htmlspecialchars($opt['icon']) ?></span>
          <span class="design-variant-label"><?= htmlspecialchars($opt['label']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="btn-primary" style="margin-top:14px;">Save Design</button>
    <span class="inline-form-msg"></span>
  <?php else: ?>
    <p style="color:#777;font-size:0.88rem;">No design options are available for this section yet.</p>
  <?php endif; ?>
</form>
