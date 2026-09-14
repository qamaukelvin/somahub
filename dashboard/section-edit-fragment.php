<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/section_variants.php';
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

$registry = get_section_variant_registry();
$config = $registry[$section['key_name']] ?? null;
$currentVariant = $section['layout_variant'] ?? ($config['default'] ?? null);

// Hero's CTA buttons link to real destinations (Admissions, Contact, Check
// Results, Fees) rather than free text - only offer ones this school
// actually has (section exists, visible, and not premium-locked), so a
// choice here can never end up pointing at a dead anchor.
$ctaDestinations = [
    'enroll' => 'Request Admission',
    'contact' => 'Contact Us',
    'results' => 'Check Results',
    'fees' => 'School Fees',
];
$ctaSectionKeys = ['enroll' => 'enrollment_form', 'contact' => 'contact', 'results' => 'results_lookup', 'fees' => 'fees'];
$availableCtaOptions = [];
if ($section['key_name'] === 'hero') {
    require_once __DIR__ . '/../includes/plan.php';
    $stmt2 = $db->prepare("SELECT * FROM schools WHERE id = ?");
    $stmt2->execute([$user['school_id']]);
    $schoolRow = $stmt2->fetch();
    $locked = is_premium_locked($schoolRow);

    $availStmt = $db->prepare("
        SELECT st.key_name FROM site_sections ss
        JOIN section_types st ON st.id = ss.section_type_id
        WHERE ss.school_id = ? AND ss.is_visible = 1 AND (? = 0 OR st.is_premium = 0)
    ");
    $availStmt->execute([$user['school_id'], $locked ? 1 : 0]);
    $availableKeys = array_column($availStmt->fetchAll(), 'key_name');

    foreach ($ctaDestinations as $ctaKey => $ctaLabel) {
        if (in_array($ctaSectionKeys[$ctaKey], $availableKeys, true)) {
            $availableCtaOptions[$ctaKey] = $ctaLabel;
        }
    }
}

// Renders one field's input (text/textarea/image), reused by both the flat
// field list and the repeatable-item cards below so markup stays identical.
function render_edit_field(string $field, string $fieldType, string $displayLabel, array $content): void {
    ?>
    <div class="field">
      <label><?= htmlspecialchars($displayLabel) ?></label>
      <?php if ($fieldType === 'textarea'): ?>
        <textarea name="<?= htmlspecialchars($field) ?>"><?= htmlspecialchars($content[$field] ?? '') ?></textarea>
      <?php elseif ($fieldType === 'image'): ?>
        <div class="image-field">
          <?php if (!empty($content[$field])): ?>
            <img class="current-img" src="../<?= htmlspecialchars($content[$field]) ?>" alt="">
            <label class="remove-photo-label"><input type="checkbox" name="remove_<?= htmlspecialchars($field) ?>" value="1"> Remove this photo</label>
          <?php endif; ?>
          <input type="file" name="<?= htmlspecialchars($field) ?>" accept="image/*">
        </div>
      <?php else: ?>
        <input type="text" name="<?= htmlspecialchars($field) ?>" value="<?= htmlspecialchars($content[$field] ?? '') ?>">
      <?php endif; ?>
    </div>
    <?php
}
?>
<form class="inline-edit-form" data-section-id="<?= $section['id'] ?>">
  <?php if ($section['key_name'] === 'hero' && $availableCtaOptions): ?>
    <div class="field">
      <label>Button 1</label>
      <select name="cta_1">
        <option value="">Use default</option>
        <?php foreach ($availableCtaOptions as $ctaKey => $ctaLabel): ?>
          <option value="<?= htmlspecialchars($ctaKey) ?>" <?= ($content['cta_1'] ?? '') === $ctaKey ? 'selected' : '' ?>><?= htmlspecialchars($ctaLabel) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Button 2 (optional)</label>
      <select name="cta_2">
        <option value="">None</option>
        <?php foreach ($availableCtaOptions as $ctaKey => $ctaLabel): ?>
          <option value="<?= htmlspecialchars($ctaKey) ?>" <?= ($content['cta_2'] ?? '') === $ctaKey ? 'selected' : '' ?>><?= htmlspecialchars($ctaLabel) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <?php if ($config && !empty($config['repeatable'])):
      $itemFieldStems = $config['item_field_groups'][$currentVariant] ?? $config['item_fields'];
      $maxItems = $config['max_items'];
      $itemLabel = $config['item_label'];

      // Only show cards for items that actually have data - "the field that
      // makes this item exist" is its first field stem (name for staff,
      // quote for testimonials, photo for gallery).
      $existenceField = $config['item_fields'][0];
      $existingIndexes = [];
      for ($i = 1; $i <= $maxItems; $i++) {
          if (!empty($content["{$existenceField}_{$i}"])) $existingIndexes[] = $i;
      }
      $nextIndex = empty($existingIndexes) ? 1 : max($existingIndexes) + 1;
  ?>
    <?php if (!empty($config['bulk_photo_upload'])): ?>
      <div class="field bulk-upload-field">
        <label>Upload several photos at once (optional)</label>
        <input type="file" name="bulk_photos[]" accept="image/*" multiple>
        <p class="field-hint">Fills the next empty slots below in order, in addition to adding photos one at a time.</p>
      </div>
    <?php endif; ?>

    <div class="repeatable-items" id="repeatable-<?= $section['id'] ?>">
      <?php if (empty($existingIndexes)): ?>
        <div class="repeatable-item" data-index="1">
          <div class="repeatable-item-header"><strong><?= htmlspecialchars($itemLabel) ?> 1</strong></div>
          <?php foreach ($itemFieldStems as $stem): render_edit_field("{$stem}_1", $schema["{$stem}_1"] ?? 'text', ucwords($stem), $content); endforeach; ?>
        </div>
      <?php else: ?>
        <?php foreach ($existingIndexes as $n => $i): ?>
          <div class="repeatable-item" data-index="<?= $i ?>">
            <div class="repeatable-item-header">
              <strong><?= htmlspecialchars($itemLabel) ?> <?= $n + 1 ?></strong>
              <button type="button" class="remove-item-btn" onclick="somahubRemoveRepeatableItem(this)">Remove</button>
            </div>
            <?php foreach ($itemFieldStems as $stem): render_edit_field("{$stem}_{$i}", $schema["{$stem}_{$i}"] ?? 'text', ucwords($stem), $content); endforeach; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($nextIndex <= $maxItems): ?>
      <button type="button" class="add-item-btn" id="addItemBtn-<?= $section['id'] ?>" onclick="somahubAddRepeatableItem(<?= $section['id'] ?>, <?= $nextIndex ?>, <?= $maxItems ?>, '<?= htmlspecialchars($itemLabel, ENT_QUOTES) ?>')">+ Add Another <?= htmlspecialchars($itemLabel) ?></button>
    <?php endif; ?>

    <!-- Blank field templates for each field stem, used by the Add button - kept
         out of the visible form (a real hidden <template>, never submitted) -->
    <template id="itemFieldTemplates-<?= $section['id'] ?>">
      <?php foreach ($itemFieldStems as $stem): ?>
        <div data-stem="<?= htmlspecialchars($stem) ?>" data-type="<?= htmlspecialchars($schema["{$stem}_1"] ?? 'text') ?>" data-label="<?= htmlspecialchars(ucwords($stem)) ?>"></div>
      <?php endforeach; ?>
    </template>

  <?php elseif ($config && isset($config['field_groups'][$currentVariant])): ?>
    <?php foreach ($config['field_groups'][$currentVariant] as $field): ?>
      <?php render_edit_field($field, $schema[$field] ?? 'text', ucwords(str_replace('_', ' ', $field)), $content); ?>
    <?php endforeach; ?>

  <?php else: ?>
    <?php foreach ($schema as $field => $fieldType): ?>
      <?php render_edit_field($field, $fieldType, ucwords(str_replace('_', ' ', $field)), $content); ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="inline-form-msg"></div>
  <button type="submit" class="btn">Save Changes</button>
</form>

<script>
  // Builds one item's field HTML from the <template> stamped out above -
  // shared by the Add button for every repeatable section type.
  function somahubBuildRepeatableItemHtml(index, itemLabel, displayNum, templateId) {
    var tpl = document.getElementById(templateId);
    var wrapper = document.createElement('div');
    wrapper.className = 'repeatable-item';
    wrapper.dataset.index = index;

    var header = document.createElement('div');
    header.className = 'repeatable-item-header';
    header.innerHTML = '<strong>' + itemLabel + ' ' + displayNum + '</strong> <button type="button" class="remove-item-btn" onclick="somahubRemoveRepeatableItem(this)">Remove</button>';
    wrapper.appendChild(header);

    tpl.content.querySelectorAll('[data-stem]').forEach(function(fieldTpl) {
      var stem = fieldTpl.dataset.stem, type = fieldTpl.dataset.type, label = fieldTpl.dataset.label;
      var name = stem + '_' + index;
      var fieldDiv = document.createElement('div');
      fieldDiv.className = 'field';
      var inputHtml = '';
      if (type === 'textarea') {
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

  function somahubAddRepeatableItem(sectionId, index, maxItems, itemLabel) {
    var container = document.getElementById('repeatable-' + sectionId);
    var displayNum = container.querySelectorAll('.repeatable-item').length + 1;
    var newItem = somahubBuildRepeatableItemHtml(index, itemLabel, displayNum, 'itemFieldTemplates-' + sectionId);
    container.appendChild(newItem);

    if (index >= maxItems) {
      document.getElementById('addItemBtn-' + sectionId).remove();
    } else {
      var btn = document.getElementById('addItemBtn-' + sectionId);
      btn.setAttribute('onclick', "somahubAddRepeatableItem(" + sectionId + ", " + (index + 1) + ", " + maxItems + ", '" + itemLabel.replace(/'/g, "\\'") + "')");
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
</script>
