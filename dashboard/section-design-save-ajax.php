<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/app_log.php';
require_once __DIR__ . '/../includes/section_variants.php';
$user = require_school_login();
$db = get_db();
require_once __DIR__ . '/../includes/edit-history.php';
history_track_request($db, (int)$user['school_id'], 'Changed layout');

header('Content-Type: application/json');

$id = (int)($_POST['section_id'] ?? 0);
$stmt = $db->prepare("
    SELECT ss.id, ss.content_json, st.key_name
    FROM site_sections ss
    JOIN section_types st ON st.id = ss.section_type_id
    WHERE ss.id = ? AND ss.school_id = ?
");
$stmt->execute([$id, $user['school_id']]);
$section = $stmt->fetch();

if (!$section) {
    echo json_encode(['ok' => false, 'errors' => ['_' => 'Section not found.']]);
    exit;
}

$registry = get_section_variant_registry();
$config = $registry[$section['key_name']] ?? null;
if (!$config) {
    echo json_encode(['ok' => false, 'errors' => ['_' => 'No design options for this section.']]);
    exit;
}

$posted = trim($_POST['layout_variant'] ?? '');
if (!isset($config['options'][$posted])) {
    echo json_encode(['ok' => false, 'errors' => ['_' => 'Invalid layout choice.']]);
    exit;
}

$validSizes = ['auto', 'half', 'full'];
$postedSize = $config['has_size'] ? trim($_POST['layout_size'] ?? 'auto') : 'auto';
if (!in_array($postedSize, $validSizes, true)) $postedSize = 'auto';

// A layout can always be picked, even before its photos are uploaded - the
// editor shows the empty upload slots right where the photos go, and the
// public site gracefully falls back to the default layout (see site.php)
// until enough photos actually exist. So this is informational only, never
// a block: the school can set up the layout and the look first, then add
// photos whenever they're ready, in either order.
$needed = $config['options'][$posted]['photos_needed'];
$photosStillNeeded = 0;
if ($needed > 0) {
    $content = json_decode($section['content_json'], true) ?: [];
    $photoCount = count_section_photos($content, $config['photo_fields']);
    $photosStillNeeded = max(0, $needed - $photoCount);
}

try {
    $update = $db->prepare("UPDATE site_sections SET layout_variant = ?, layout_size = ? WHERE id = ? AND school_id = ?");
    $update->execute([$posted, $postedSize, $id, $user['school_id']]);
} catch (\Throwable $e) {
    app_log('section-design-save-ajax.php failed for section ' . $id . ': ' . $e->getMessage());
    echo json_encode(['ok' => false, 'errors' => ['_' => 'Could not save. Please try again or contact support.']]);
    exit;
}

$notice = $photosStillNeeded > 0
    ? "Saved. Add {$photosStillNeeded} more photo" . ($photosStillNeeded > 1 ? 's' : '') . ' to make this layout appear on your live site.'
    : null;
echo json_encode(['ok' => true, 'notice' => $notice]);
