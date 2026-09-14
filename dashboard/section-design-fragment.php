<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/section_variants.php';
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

$registry = get_section_variant_registry();
$config = $registry[$section['key_name']] ?? null;
$current = $section['layout_variant'] ?? ($config['default'] ?? null);
$currentSize = $section['layout_size'] ?? 'auto';

$photoCount = 0;
if ($config) {
    $content = json_decode($section['content_json'], true) ?: [];
    $photoCount = count_section_photos($content, $config['photo_fields']);
}

$sizeOptions = ['auto' => 'Fits content', 'half' => 'Half page', 'full' => 'Full page'];
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
<style>
  .material-symbols-outlined{font-family:'Material Symbols Outlined';font-weight:normal;font-style:normal;font-size:26px;line-height:1;display:block;}
  .design-variant-grid{display:flex;gap:10px;flex-wrap:wrap;}
  .design-variant-option{display:flex;flex-direction:column;align-items:center;gap:6px;border:1.5px solid #ccc;border-radius:10px;padding:14px 16px;cursor:pointer;min-width:110px;text-align:center;transition:border-color .15s;}
  .design-variant-option input{position:absolute;opacity:0;pointer-events:none;}
  .design-variant-option.selected{border-color:#0F5257;box-shadow:0 0 0 2px rgba(15,82,87,0.15);background:#F4F8F6;}
  .design-variant-option.disabled{opacity:0.45;cursor:not-allowed;}
  .design-variant-label{font-size:0.78rem;font-weight:600;}
  .design-size-row{display:flex;gap:8px;margin-top:8px;}
  .design-size-option{border:1.5px solid #ccc;border-radius:8px;padding:7px 14px;font-size:0.82rem;cursor:pointer;}
  .design-size-option input{margin-right:5px;}
  .design-size-option:has(input:checked){border-color:#0F5257;background:#F4F8F6;}
</style>
<form class="inline-design-form" data-section-id="<?= $section['id'] ?>">
  <?php if ($config): ?>
    <label style="font-weight:700;display:block;margin-bottom:10px;">Layout</label>
    <div class="design-variant-grid">
      <?php foreach ($config['options'] as $value => $opt):
          $needed = $opt['photos_needed'];
          $isDisabled = $needed > 0 && $photoCount < $needed;
          $disabledReason = $isDisabled ? "Needs {$needed} photo" . ($needed > 1 ? 's' : '') . ' uploaded (Edit tab)' : '';
      ?>
        <label class="design-variant-option<?= $current === $value ? ' selected' : '' ?><?= $isDisabled ? ' disabled' : '' ?>" <?= $isDisabled ? 'title="' . htmlspecialchars($disabledReason) . '"' : '' ?>>
          <input type="radio" name="layout_variant" value="<?= htmlspecialchars($value) ?>" <?= $current === $value ? 'checked' : '' ?> <?= $isDisabled ? 'disabled' : '' ?> onchange="this.closest('.design-variant-grid').querySelectorAll('.design-variant-option').forEach(o=>o.classList.remove('selected'));this.closest('.design-variant-option').classList.add('selected');">
          <span class="material-symbols-outlined" aria-hidden="true"><?= htmlspecialchars($opt['icon']) ?></span>
          <span class="design-variant-label"><?= htmlspecialchars($opt['label']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <?php if ($config['has_size']): ?>
      <label style="font-weight:700;display:block;margin:18px 0 8px;">Height</label>
      <div class="design-size-row">
        <?php foreach ($sizeOptions as $value => $slabel): ?>
          <label class="design-size-option">
            <input type="radio" name="layout_size" value="<?= htmlspecialchars($value) ?>" <?= $currentSize === $value ? 'checked' : '' ?>><?= htmlspecialchars($slabel) ?>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <button type="submit" class="btn-primary" style="margin-top:14px;">Save Design</button>
    <span class="inline-form-msg"></span>
  <?php else: ?>
    <p style="color:#777;font-size:0.88rem;">No design options are available for this section yet.</p>
  <?php endif; ?>
</form>
