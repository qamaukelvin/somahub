<?php
// Saves a per-text colour (e.g. Hero headline/subheading) into content_json.
// Only whitelisted *_color keys, only #rrggbb or '' (= automatic).
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
$db = get_db();
header('Content-Type: application/json');

$id = (int)($_POST['section_id'] ?? 0);
$field = $_POST['field'] ?? '';
$value = trim($_POST['value'] ?? '');
$allowed = ['headline_color', 'subheading_color'];

if (!in_array($field, $allowed, true) || ($value !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $value))) {
    echo json_encode(['ok' => false, 'errors' => ['_general' => 'Invalid colour.']]);
    exit;
}
$stmt = $db->prepare("SELECT content_json FROM site_sections WHERE id = ? AND school_id = ?");
$stmt->execute([$id, $user['school_id']]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); echo json_encode(['ok' => false, 'errors' => ['_general' => 'Section not found.']]); exit; }

$content = json_decode($row['content_json'], true) ?: [];
if ($value === '') unset($content[$field]); else $content[$field] = $value;
$db->prepare("UPDATE site_sections SET content_json = ? WHERE id = ? AND school_id = ?")
   ->execute([json_encode($content), $id, $user['school_id']]);
echo json_encode(['ok' => true]);
