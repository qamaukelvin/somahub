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
