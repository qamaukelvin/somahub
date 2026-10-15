<?php
// Inserts a new person at a given position in Staff's ordering, shifting
// everyone from that position down one slot (used by the org chart's
// per-level "+" buttons, since level = position in the list).
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
$db = get_db();
header('Content-Type: application/json');

$id = (int)($_POST['section_id'] ?? 0);
$pos = max(0, (int)($_POST['position'] ?? 0));
$stmt = $db->prepare("SELECT ss.content_json, st.key_name FROM site_sections ss JOIN section_types st ON st.id = ss.section_type_id WHERE ss.id = ? AND ss.school_id = ?");
$stmt->execute([$id, $user['school_id']]);
$row = $stmt->fetch();
if (!$row || $row['key_name'] !== 'staff') { http_response_code(404); echo json_encode(['ok' => false, 'errors' => ['_general' => 'Section not found.']]); exit; }

$content = json_decode($row['content_json'], true) ?: [];
$fields = ['name', 'role', 'photo', 'bio', 'department'];
$MAX = 10;
$people = [];
for ($i = 1; $i <= $MAX; $i++) {
    $p = []; $has = false;
    foreach ($fields as $f) { $p[$f] = $content["{$f}_$i"] ?? ''; if ($p[$f] !== '') $has = true; }
    if ($has) $people[] = $p;
}
if (count($people) >= $MAX) { echo json_encode(['ok' => false, 'errors' => ['_general' => 'You can have up to 10 people.']]); exit; }

$new = array_fill_keys($fields, ''); $new['name'] = 'New person';
array_splice($people, min($pos, count($people)), 0, [$new]);

for ($i = 1; $i <= $MAX; $i++) {
    $p = $people[$i - 1] ?? array_fill_keys($fields, '');
    foreach ($fields as $f) $content["{$f}_$i"] = $p[$f];
}
$db->prepare("UPDATE site_sections SET content_json = ? WHERE id = ? AND school_id = ?")->execute([json_encode($content), $id, $user['school_id']]);
echo json_encode(['ok' => true]);
