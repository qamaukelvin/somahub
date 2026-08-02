<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
$db = get_db();

$sectionTypeId = (int)($_POST['section_type_id'] ?? 0);

// Check plan restrictions — block premium sections on free plan
$typeStmt = $db->prepare("SELECT * FROM section_types WHERE id = ?");
$typeStmt->execute([$sectionTypeId]);
$type = $typeStmt->fetch();

$schoolStmt = $db->prepare("SELECT plan FROM schools WHERE id = ?");
$schoolStmt->execute([$user['school_id']]);
$school = $schoolStmt->fetch();

if (!$type) {
    die('Invalid section type.');
}
if ($type['is_premium'] && $school['plan'] === 'free') {
    header('Location: sections.php?error=upgrade_required');
    exit;
}

$maxPos = $db->prepare("SELECT COALESCE(MAX(position), 0) m FROM site_sections WHERE school_id = ?");
$maxPos->execute([$user['school_id']]);
$newPosition = $maxPos->fetch()['m'] + 1;

// Seed with empty content matching the section's schema
$defaults = json_decode($type['schema_json'], true);
$emptyContent = [];
foreach (array_keys($defaults) as $field) {
    $emptyContent[$field] = '';
}

$insert = $db->prepare("
    INSERT INTO site_sections (school_id, section_type_id, position, is_visible, content_json)
    VALUES (?, ?, ?, 1, ?)
");
$insert->execute([
    $user['school_id'],
    $sectionTypeId,
    $newPosition,
    json_encode($emptyContent),
]);

$newId = $db->lastInsertId();
header('Location: section-edit.php?id=' . $newId);
exit;
