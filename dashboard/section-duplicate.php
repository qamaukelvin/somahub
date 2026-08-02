<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
$db = get_db();

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

// Fetch original — MUST belong to this school
$stmt = $db->prepare("SELECT * FROM site_sections WHERE id = ? AND school_id = ?");
$stmt->execute([$id, $user['school_id']]);
$original = $stmt->fetch();

header('Content-Type: application/json');

if (!$original) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Section not found']);
    exit;
}

// New position = end of list
$maxPos = $db->prepare("SELECT COALESCE(MAX(position), 0) m FROM site_sections WHERE school_id = ?");
$maxPos->execute([$user['school_id']]);
$newPosition = $maxPos->fetch()['m'] + 1;

$insert = $db->prepare("
    INSERT INTO site_sections (school_id, section_type_id, position, is_visible, content_json)
    VALUES (?, ?, ?, ?, ?)
");
$insert->execute([
    $user['school_id'],
    $original['section_type_id'],
    $newPosition,
    $original['is_visible'],
    $original['content_json'],
]);

echo json_encode(['ok' => true, 'new_id' => $db->lastInsertId()]);
