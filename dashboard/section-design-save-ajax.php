<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/app_log.php';
$user = require_school_login();
$db = get_db();

header('Content-Type: application/json');

$id = (int)($_POST['section_id'] ?? 0);
$stmt = $db->prepare("
    SELECT ss.id, st.key_name
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

// Keep this allowlist in sync with section-design-fragment.php's options.
$validVariantsByType = [
    'hero' => ['default', 'background', 'carousel'],
];

$allowed = $validVariantsByType[$section['key_name']] ?? null;
if (!$allowed) {
    echo json_encode(['ok' => false, 'errors' => ['_' => 'No design options for this section.']]);
    exit;
}

$posted = trim($_POST['layout_variant'] ?? '');
if (!in_array($posted, $allowed, true)) {
    echo json_encode(['ok' => false, 'errors' => ['_' => 'Invalid layout choice.']]);
    exit;
}

try {
    $update = $db->prepare("UPDATE site_sections SET layout_variant = ? WHERE id = ? AND school_id = ?");
    $update->execute([$posted, $id, $user['school_id']]);
} catch (\Throwable $e) {
    app_log('section-design-save-ajax.php failed for section ' . $id . ': ' . $e->getMessage());
    echo json_encode(['ok' => false, 'errors' => ['_' => 'Could not save. Please try again or contact support.']]);
    exit;
}

echo json_encode(['ok' => true]);
