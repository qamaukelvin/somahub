<?php
// Moves one numbered item (staff member, testimonial, FAQ entry, blog post)
// up or down by swapping its numbered fields with the neighbouring item.
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/section_variants.php';
$user = require_school_login();
$db = get_db();

$id = (int)($_POST['section_id'] ?? 0);
$slot = (int)($_POST['slot'] ?? 0);
$dir = ($_POST['dir'] ?? '') === 'up' ? 'up' : 'down';

$stmt = $db->prepare("SELECT ss.*, st.key_name FROM site_sections ss JOIN section_types st ON st.id = ss.section_type_id WHERE ss.id = ? AND ss.school_id = ?");
$stmt->execute([$id, $user['school_id']]);
$section = $stmt->fetch();
$cfg = $section ? (get_section_variant_registry()[$section['key_name']] ?? null) : null;
if (!$section || !$cfg || empty($cfg['repeatable']) || empty($cfg['item_fields']) || $section['key_name'] === 'stats') {
    echo json_encode(['ok' => false, 'error' => 'This section cannot be reordered.']); exit;
}

$content = json_decode($section['content_json'], true) ?: [];
$stems = $cfg['item_fields'];
$max = (int)($cfg['max_items'] ?? 10);

$occupied = [];
for ($i = 1; $i <= $max; $i++) {
    foreach ($stems as $st) { if (!empty($content["{$st}_$i"])) { $occupied[] = $i; break; } }
}
$pos = array_search($slot, $occupied, true);
if ($pos === false) { echo json_encode(['ok' => false, 'error' => 'Item not found.']); exit; }
$swapPos = $dir === 'up' ? $pos - 1 : $pos + 1;
if (!isset($occupied[$swapPos])) { echo json_encode(['ok' => true, 'unchanged' => true]); exit; }
$other = $occupied[$swapPos];

foreach ($stems as $st) {
    $a = $content["{$st}_$slot"] ?? '';
    $b = $content["{$st}_$other"] ?? '';
    $content["{$st}_$slot"] = $b;
    $content["{$st}_$other"] = $a;
}
$db->prepare("UPDATE site_sections SET content_json = ? WHERE id = ? AND school_id = ?")
   ->execute([json_encode($content), $id, $user['school_id']]);
echo json_encode(['ok' => true]);
