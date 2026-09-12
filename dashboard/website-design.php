<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/app_log.php';
require_once __DIR__ . '/../includes/appearance.php';
require_once __DIR__ . '/../includes/plan.php';
require_once __DIR__ . '/../includes/content-presets.php';
$user = require_school_login();
$db = get_db();

$schoolId = $user['school_id'];
$stmt = $db->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$schoolId]);
$school = $stmt->fetch();

$locked = is_premium_locked($school);
$templates = get_active_templates($db);
$palettes = get_active_palettes($db);
$contentPresets = get_school_content_presets();

// Every section type available, plus whether this school already has it
// (and if so, whether it's currently shown and what variant it uses).
$allTypes = $db->query("SELECT id, key_name, label, is_premium, schema_json FROM section_types ORDER BY id ASC")->fetchAll();
$existingStmt = $db->prepare("SELECT section_type_id, is_visible, layout_variant, content_json FROM site_sections WHERE school_id = ?");
$existingStmt->execute([$schoolId]);
$existingBySectionType = [];
foreach ($existingStmt->fetchAll() as $row) {
    $existingBySectionType[$row['section_type_id']] = $row;
}

// Only section types with a real, built variant system get a variant picker
// shown - everything else just gets the include/exclude checkbox. Honest
// about what's actually built rather than showing fake choices.
$variantOptions = [
    'hero' => [
        'default' => 'Side-by-side photo',
        'background' => 'Full-bleed background photo',
        'carousel' => 'Rotating photo carousel',
    ],
];

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Quick Start preset (optional - "Start from scratch" skips this)
        $chosenTemplateId = (int)($_POST['template_id'] ?? 0);
        $chosenPaletteId = (int)($_POST['palette_id'] ?? 0);
        if ($chosenTemplateId) {
            $chosenTemplate = null;
            foreach ($templates as $t) { if ($t['id'] == $chosenTemplateId) { $chosenTemplate = $t; break; } }
            if ($chosenTemplate && (!$chosenTemplate['is_premium'] || !$locked)) {
                $db->prepare("UPDATE schools SET template_id = ?, palette_id = ?, color_mode = 'preset' WHERE id = ?")
                   ->execute([$chosenTemplateId, $chosenPaletteId ?: null, $schoolId]);
            }
        }

        // Section include/exclude + variant
        $insertSection = $db->prepare("
            INSERT INTO site_sections (school_id, section_type_id, position, is_visible, layout_variant, content_json)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $updateSection = $db->prepare("
            UPDATE site_sections SET is_visible = ?, layout_variant = ? WHERE school_id = ? AND section_type_id = ?
        ");
        $maxPosStmt = $db->prepare("SELECT COALESCE(MAX(position), 0) FROM site_sections WHERE school_id = ?");
        $maxPosStmt->execute([$schoolId]);
        $nextPosition = (int)$maxPosStmt->fetchColumn() + 1;

        foreach ($allTypes as $type) {
            if ($type['is_premium'] && $locked) continue; // never let a locked account enable a premium section here
            $wanted = $type['key_name'] === 'hero' ? true : isset($_POST['section_' . $type['key_name']]);
            $variant = trim($_POST['variant_' . $type['key_name']] ?? 'default');
            if (!isset($variantOptions[$type['key_name']][$variant])) $variant = 'default';

            $existing = $existingBySectionType[$type['id']] ?? null;
            if ($existing) {
                $updateSection->execute([$wanted ? 1 : 0, $variant, $schoolId, $type['id']]);
            } elseif ($wanted) {
                $schema = json_decode($type['schema_json'], true) ?: [];
                $emptyContent = array_fill_keys(array_keys($schema), '');
                $insertSection->execute([$schoolId, $type['id'], $nextPosition++, 1, $variant, json_encode($emptyContent)]);
            }
        }

        $db->commit();
        header('Location: index.php?welcome=1');
        exit;
    } catch (\Throwable $e) {
        $db->rollBack();
        app_log('website-design.php failed for school ' . $schoolId . ': ' . $e->getMessage());
        $error = 'Something went wrong saving your choices. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Design Your Website - Somahub</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .wizard-wrap{max-width:760px;margin:0 auto;padding:32px 20px 80px;}
  .wizard-head{margin-bottom:24px;}
  .wizard-head h1{font-size:1.5rem;margin-bottom:6px;}
  .wizard-head p{color:var(--muted);font-size:0.92rem;}
  .customize-toggle{display:block;margin:24px 0 16px;font-weight:700;color:var(--teal);cursor:pointer;}
  #customizePanel{display:none;border-top:1px solid var(--line);padding-top:20px;}
  #customizePanel.open{display:block;}
  .section-row{display:flex;align-items:flex-start;gap:12px;padding:14px 0;border-bottom:1px solid var(--line);}
  .section-row input[type=checkbox]{margin-top:4px;width:18px;height:18px;}
  .section-row .label{font-weight:700;font-size:0.95rem;}
  .section-row .premium-tag{background:#F2A65A;color:#0A3A3E;font-size:0.6rem;font-weight:800;padding:2px 6px;border-radius:8px;margin-left:6px;}
  .variant-options{display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;}
  .variant-options label{border:1.5px solid var(--line);border-radius:8px;padding:6px 12px;font-size:0.82rem;cursor:pointer;}
  .variant-options input{margin-right:5px;}
  .variant-options input:checked + span, .variant-options label:has(input:checked){border-color:var(--teal);background:#F4F8F6;}
  .wizard-actions{margin-top:28px;display:flex;gap:12px;align-items:center;}
  .btn-skip{color:var(--muted);font-size:0.88rem;}
</style>
</head>
<body>
<div class="wizard-wrap">
  <div class="wizard-head">
    <h1>Design Your Website</h1>
    <p>Pick a starting look, or skip straight to customizing sections yourself. You can change any of this later from your dashboard.</p>
  </div>

  <?php if ($error): ?><p style="background:#FBE8E4;color:#8C3B2E;padding:10px 16px;border-radius:8px;margin-bottom:16px;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

  <form method="POST">
    <label style="font-weight:700;display:block;margin-bottom:10px;">Quick Start</label>
    <?php $selectedTemplateId = $school['template_id'] ?: 0; include __DIR__ . '/../admin/_template_picker.php'; ?>
    <label style="font-weight:700;display:block;margin:20px 0 10px;">Colors</label>
    <?php
      $selectedPaletteId = $school['palette_id'] ?: 0;
      $selectedColorMode = $school['color_mode'] ?? 'preset';
      $customColors = [
          'primary' => $school['primary_override'] ?: '#0F5257',
          'secondary' => $school['secondary_override'] ?: '#1C1C16',
          'accent' => $school['accent_override'] ?: '#F2A65A',
          'bg' => $school['bg_override'] ?: '#F7F2E7',
      ];
      include __DIR__ . '/../admin/_palette_picker.php';
    ?>

    <span class="customize-toggle" onclick="document.getElementById('customizePanel').classList.toggle('open');this.textContent=this.textContent.includes('▾')?'Customize your sections ▸':'Customize your sections ▾';">Customize your sections ▸</span>

    <div id="customizePanel">
      <?php foreach ($allTypes as $type):
          if ($type['is_premium'] && $locked) continue;
          $existing = $existingBySectionType[$type['id']] ?? null;
          $isChecked = $existing ? (bool)$existing['is_visible'] : in_array($type['key_name'], ['hero','about','academics','admissions','gallery','contact']);
          $currentVariant = $existing['layout_variant'] ?? 'default';
      ?>
        <div class="section-row">
          <input type="checkbox" name="section_<?= htmlspecialchars($type['key_name']) ?>" id="sec_<?= htmlspecialchars($type['key_name']) ?>" <?= $type['key_name'] === 'hero' ? 'checked disabled' : ($isChecked ? 'checked' : '') ?>>
          <div style="flex:1;">
            <label for="sec_<?= htmlspecialchars($type['key_name']) ?>" class="label"><?= htmlspecialchars($type['label']) ?><?php if ($type['is_premium']): ?><span class="premium-tag">Premium</span><?php endif; ?></label>
            <?php if (isset($variantOptions[$type['key_name']])): ?>
              <div class="variant-options">
                <?php foreach ($variantOptions[$type['key_name']] as $value => $vlabel): ?>
                  <label><input type="radio" name="variant_<?= htmlspecialchars($type['key_name']) ?>" value="<?= htmlspecialchars($value) ?>" <?= $currentVariant === $value ? 'checked' : '' ?>><span><?= htmlspecialchars($vlabel) ?></span></label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="wizard-actions">
      <button type="submit" class="btn-primary">Continue to Dashboard</button>
      <a href="index.php" class="btn-skip">Skip for now</a>
    </div>
  </form>
</div>
</body>
</html>
