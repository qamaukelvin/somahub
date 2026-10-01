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

// Hero and CTA Banner both link to real destinations (Admissions, Contact,
// Check Results, Fees) rather than free text - only offer ones this school
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
if (in_array($section['key_name'], ['hero', 'cta_banner'], true)) {
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
function render_edit_field(string $field, string $fieldType, string $displayLabel, array $content, array $ctaOptions = []): void {
    // Curated icon choices for any *_icon field (currently just Stats) -
    // a dropdown rather than free text, so a typo can never silently
    // produce a missing/broken icon on the live site.
    $iconChoices = [
        'school' => 'Graduation cap',
        'groups' => 'People',
        'calendar_month' => 'Calendar',
        'menu_book' => 'Book',
        'emoji_events' => 'Trophy',
        'star' => 'Star',
        'schedule' => 'Clock',
        'bar_chart' => 'Chart',
        'diversity_3' => 'Community',
        'workspace_premium' => 'Award',
    ];
    ?>
    <div class="field">
      <label><?= htmlspecialchars($displayLabel) ?></label>
      <?php if (str_starts_with($field, 'cta_destination') && $ctaOptions): ?>
        <select name="<?= htmlspecialchars($field) ?>">
          <option value="">Use default</option>
          <?php foreach ($ctaOptions as $ctaKey => $ctaLabel): ?>
            <option value="<?= htmlspecialchars($ctaKey) ?>" <?= ($content[$field] ?? '') === $ctaKey ? 'selected' : '' ?>><?= htmlspecialchars($ctaLabel) ?></option>
          <?php endforeach; ?>
        </select>
      <?php elseif (str_ends_with($field, '_icon')): ?>
        <select name="<?= htmlspecialchars($field) ?>">
          <option value="">No icon</option>
          <?php foreach ($iconChoices as $value => $iconLabel): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= ($content[$field] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($iconLabel) ?></option>
          <?php endforeach; ?>
        </select>
      <?php elseif ($fieldType === 'textarea'): ?>
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
    <p style="font-size:0.8rem;color:#888;margin:-4px 0 4px;">Buttons link to real pages on your site (like Admissions or Contact) - no web addresses to type.</p>
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
      $namePattern = $config['field_name_format'] ?? '{stem}_{index}';

      // Only show cards for items that actually have data - "the field that
      // makes this item exist" is its first field stem (name for staff,
      // quote for testimonials, photo for gallery, number for stats).
      $existenceField = $config['item_fields'][0];
      $existingIndexes = [];
      for ($i = 1; $i <= $maxItems; $i++) {
          if (!empty($content[format_item_field_name($existenceField, $i, $namePattern)])) $existingIndexes[] = $i;
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
          <?php foreach ($itemFieldStems as $stem): $fname = format_item_field_name($stem, 1, $namePattern); render_edit_field($fname, $schema[$fname] ?? 'text', ucwords($stem), $content); endforeach; ?>
        </div>
      <?php else: ?>
        <?php foreach ($existingIndexes as $n => $i): ?>
          <div class="repeatable-item" data-index="<?= $i ?>">
            <div class="repeatable-item-header">
              <strong><?= htmlspecialchars($itemLabel) ?> <?= $n + 1 ?></strong>
              <button type="button" class="remove-item-btn" onclick="somahubRemoveRepeatableItem(this)">Remove</button>
            </div>
            <?php foreach ($itemFieldStems as $stem): $fname = format_item_field_name($stem, $i, $namePattern); render_edit_field($fname, $schema[$fname] ?? 'text', ucwords($stem), $content); endforeach; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($nextIndex <= $maxItems): ?>
      <button type="button" class="add-item-btn" id="addItemBtn-<?= $section['id'] ?>" onclick="somahubAddRepeatableItem(<?= $section['id'] ?>, <?= $nextIndex ?>, <?= $maxItems ?>, '<?= htmlspecialchars($itemLabel, ENT_QUOTES) ?>', '<?= htmlspecialchars($namePattern, ENT_QUOTES) ?>')">+ Add Another <?= htmlspecialchars($itemLabel) ?></button>
    <?php endif; ?>

    <!-- Blank field templates for each field stem, used by the Add button - kept
         out of the visible form (a real hidden <template>, never submitted) -->
    <template id="itemFieldTemplates-<?= $section['id'] ?>">
      <?php foreach ($itemFieldStems as $stem): $sampleField = format_item_field_name($stem, 1, $namePattern); ?>
        <div data-stem="<?= htmlspecialchars($stem) ?>" data-type="<?= htmlspecialchars($schema[$sampleField] ?? 'text') ?>" data-label="<?= htmlspecialchars(ucwords($stem)) ?>"></div>
      <?php endforeach; ?>
    </template>

  <?php elseif ($config && isset($config['field_groups'][$currentVariant])): ?>
    <?php foreach ($config['field_groups'][$currentVariant] as $field):
        $niceLabel = preg_match('/^cta_destination_(\d)$/', $field, $m) ? "Button {$m[1]} Destination" : (($field === 'button_text_2') ? 'Button 2 Text' : ucwords(str_replace('_', ' ', $field)));
    ?>
      <?php render_edit_field($field, $schema[$field] ?? 'text', $niceLabel, $content, $availableCtaOptions); ?>
    <?php endforeach; ?>

  <?php else: ?>
    <?php foreach ($schema as $field => $fieldType): ?>
      <?php render_edit_field($field, $fieldType, ucwords(str_replace('_', ' ', $field)), $content, $availableCtaOptions); ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="inline-form-msg"></div>
  <button type="submit" class="btn">Save Changes</button>
</form>
