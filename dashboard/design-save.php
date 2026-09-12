<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/appearance.php';
require_once __DIR__ . '/../includes/plan.php';
$user = require_school_login();
$db = get_db();
$schoolId = $user['school_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: sections.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$schoolId]);
$school = $stmt->fetch();
$locked = is_premium_locked($school);

$templates = get_active_templates($db);
$templateId = (int)($_POST['template_id'] ?? 0);
$chosenTemplate = null;
foreach ($templates as $t) { if ($t['id'] == $templateId) { $chosenTemplate = $t; break; } }

// Never let a locked (free/expired) account switch to a premium template
// from here - fall back silently to whatever they already had rather than
// erroring, since this is a quiet background save, not a hard form gate.
if (!$chosenTemplate || ($chosenTemplate['is_premium'] && $locked)) {
    $templateId = $school['template_id'];
}

$colorMode = ($_POST['color_mode'] ?? 'preset') === 'custom' ? 'custom' : 'preset';
$paletteId = $colorMode === 'preset' ? ((int)($_POST['palette_id'] ?? 0) ?: null) : null;
$primaryOverride = $colorMode === 'custom' ? (trim($_POST['primary_override'] ?? '') ?: null) : null;
$secondaryOverride = $colorMode === 'custom' ? (trim($_POST['secondary_override'] ?? '') ?: null) : null;
$accentOverride = $colorMode === 'custom' ? (trim($_POST['accent_override'] ?? '') ?: null) : null;
$bgOverride = $colorMode === 'custom' ? (trim($_POST['bg_override'] ?? '') ?: null) : null;

$update = $db->prepare("
    UPDATE schools
    SET template_id = ?, palette_id = ?, color_mode = ?, primary_override = ?, secondary_override = ?, accent_override = ?, bg_override = ?
    WHERE id = ?
");
$update->execute([$templateId, $paletteId, $colorMode, $primaryOverride, $secondaryOverride, $accentOverride, $bgOverride, $schoolId]);

header('Location: sections.php?design_saved=1');
exit;
