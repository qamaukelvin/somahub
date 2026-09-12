<?php
require_once __DIR__ . '/../includes/auth.php';
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

if (!$section) {
    http_response_code(404);
    exit('Section not found.');
}

$schema = json_decode($section['schema_json'], true);
$content = json_decode($section['content_json'], true);

// Only section types with a real, built layout-variant system get this
// control - everything else just has its normal content fields.
$heroVariantOptions = [
    'default' => 'Side-by-side photo',
    'background' => 'Full-bleed background photo',
    'carousel' => 'Rotating photo carousel',
];
?>
<form class="inline-edit-form" data-section-id="<?= $section['id'] ?>">
  <?php if ($section['key_name'] === 'hero'): ?>
    <div class="field">
      <label>Layout</label>
      <div class="variant-options">
        <?php foreach ($heroVariantOptions as $value => $vlabel): ?>
          <label style="display:inline-flex;align-items:center;gap:6px;border:1.5px solid #ccc;border-radius:8px;padding:6px 12px;margin:0 6px 6px 0;font-weight:400;font-size:0.85rem;cursor:pointer;">
            <input type="radio" name="layout_variant" value="<?= $value ?>" <?= ($section['layout_variant'] ?? 'default') === $value ? 'checked' : '' ?>>
            <?= htmlspecialchars($vlabel) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
  <?php foreach ($schema as $field => $fieldType): ?>
    <div class="field">
      <label><?= htmlspecialchars(ucwords(str_replace('_', ' ', $field))) ?></label>

      <?php if ($fieldType === 'textarea'): ?>
        <textarea name="<?= $field ?>"><?= htmlspecialchars($content[$field] ?? '') ?></textarea>

      <?php elseif ($fieldType === 'image'): ?>
        <div class="image-field">
          <?php if (!empty($content[$field])): ?>
            <img class="current-img" src="../<?= htmlspecialchars($content[$field]) ?>" alt="">
          <?php endif; ?>
          <input type="file" name="<?= $field ?>" accept="image/*">
        </div>

      <?php else: ?>
        <input type="text" name="<?= $field ?>" value="<?= htmlspecialchars($content[$field] ?? '') ?>">
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="inline-form-msg"></div>
  <button type="submit" class="btn">Save Changes</button>
</form>
