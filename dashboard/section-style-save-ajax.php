<?php
// Saves a per-text colour into content_json (any editable text field of the
// section, or the section 'title').
// Only <field>_color keys, only #rrggbb or '' (= automatic).
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
$db = get_db();
header('Content-Type: application/json');

$id = (int)($_POST['section_id'] ?? 0);
$field = $_POST['field'] ?? '';
$value = trim($_POST['value'] ?? '');
if (!preg_match('/^([a-z0-9_]+)_color$/', $field, $m) || ($value !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $value))) {
    echo json_encode(['ok' => false, 'errors' => ['_general' => 'Invalid colour.']]);
    exit;
}
$stmt = $db->prepare("SELECT ss.content_json, st.schema_json FROM site_sections ss JOIN section_types st ON st.id = ss.section_type_id WHERE ss.id = ? AND ss.school_id = ?");
$stmt->execute([$id, $user['school_id']]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); echo json_encode(['ok' => false, 'errors' => ['_general' => 'Section not found.']]); exit; }

// Only 'title' (the section heading) or a real, non-image field of this
// section's schema can carry a colour.
$schema = json_decode($row['schema_json'], true) ?: [];
$base = $m[1];
$virtual = ['title', 'names', 'roles', 'bios', 'depts']; // roles that apply to every matching text in a section
if (!in_array($base, $virtual, true) && (!isset($schema[$base]) || $schema[$base] === 'image')) {
    echo json_encode(['ok' => false, 'errors' => ['_general' => 'Invalid colour target.']]);
    exit;
}
$content = json_decode($row['content_json'], true) ?: [];
if ($value === '') unset($content[$field]); else $content[$field] = $value;
$db->prepare("UPDATE site_sections SET content_json = ? WHERE id = ? AND school_id = ?")
   ->execute([json_encode($content), $id, $user['school_id']]);
echo json_encode(['ok' => true]);
