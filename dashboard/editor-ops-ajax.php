<?php
// Structural actions for the on-page site editor: move, duplicate, remove,
// hide/show, change layout, add section, undo, redo. All answer with JSON,
// all are limited to the signed-in school, and all are undoable.
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/section_variants.php';
require_once __DIR__ . '/../includes/school-provision.php';
require_once __DIR__ . '/../includes/edit-history.php';
$user = require_school_login();
$db = get_db();
$sid = (int)$user['school_id'];
$action = $_POST['action'] ?? '';
$id = (int)($_POST['section_id'] ?? 0);

function out($a) { echo json_encode($a); exit; }

if ($action === 'status') out(['ok' => true] + history_status($db, $sid));

if ($action === 'undo' || $action === 'redo') {
    $label = history_step($db, $sid, $action);
    if ($label === null) out(['ok' => false, 'error' => $action === 'undo' ? 'Nothing to undo.' : 'Nothing to redo.']);
    out(['ok' => true, 'label' => $label] + history_status($db, $sid));
}

$labels = ['move' => 'Moved a section', 'duplicate' => 'Duplicated a section', 'remove' => 'Removed a section',
           'toggle' => 'Showed or hid a section', 'variant' => 'Changed layout', 'add' => 'Added a section', 'reorder' => 'Reordered sections'];
if (!isset($labels[$action])) out(['ok' => false, 'error' => 'Unknown action.']);
history_track_request($db, $sid, $labels[$action]);

function ordered_ids(PDO $db, int $sid): array {
    $q = $db->prepare("SELECT id FROM site_sections WHERE school_id = ? ORDER BY position, id");
    $q->execute([$sid]);
    return array_map('intval', array_column($q->fetchAll(), 'id'));
}
function save_order(PDO $db, array $ids): void {
    $u = $db->prepare("UPDATE site_sections SET position = ? WHERE id = ?");
    foreach (array_values($ids) as $pos => $i) $u->execute([$pos, $i]);
}

if ($action === 'reorder') {
    $given = array_values(array_unique(array_map('intval', json_decode($_POST['ids'] ?? '[]', true) ?: [])));
    $have = ordered_ids($db, $sid);
    $given = array_values(array_intersect($given, $have));           // only this school's sections
    $rest = array_values(array_diff($have, $given));                  // anything not mentioned keeps its place at the end
    save_order($db, array_merge($given, $rest));
    out(['ok' => true]);
}

if ($action === 'add') {
    $typeId = (int)($_POST['section_type_id'] ?? 0);
    $t = $db->prepare("SELECT * FROM section_types WHERE id = ?"); $t->execute([$typeId]);
    $type = $t->fetch();
    if (!$type) out(['ok' => false, 'error' => 'Unknown section type.']);
    $sc = $db->prepare("SELECT plan FROM schools WHERE id = ?"); $sc->execute([$sid]);
    if ($type['is_premium'] && ($sc->fetchColumn() === 'free')) out(['ok' => false, 'error' => 'This section needs the paid plan.']);
    $content = array_fill_keys(array_keys(json_decode($type['schema_json'], true) ?: []), '');
    $cfg = get_section_variant_registry()[$type['key_name']] ?? [];
    $variant = $cfg['default'] ?? null;
    $max = $db->prepare("SELECT COALESCE(MAX(position),0)+1 FROM site_sections WHERE school_id = ?"); $max->execute([$sid]);
    $pos = (int)$max->fetchColumn();
    if (db_has_column($db, 'site_sections', 'layout_variant')) {
        $db->prepare("INSERT INTO site_sections (school_id, section_type_id, position, is_visible, layout_variant, content_json) VALUES (?,?,?,1,?,?)")
           ->execute([$sid, $typeId, $pos, $variant, json_encode($content)]);
    } else {
        $db->prepare("INSERT INTO site_sections (school_id, section_type_id, position, is_visible, content_json) VALUES (?,?,?,1,?)")
           ->execute([$sid, $typeId, $pos, json_encode($content)]);
    }
    $newId = (int)$db->lastInsertId();
    // Optional position: after_id = a section id, or 'first' for the very top. Default is the bottom.
    $after = $_POST['after_id'] ?? '';
    if ($after !== '') {
        $ids = array_values(array_filter(ordered_ids($db, $sid), fn($x) => $x !== $newId));
        $at = $after === 'first' ? 0 : (array_search((int)$after, $ids, true));
        if ($at !== false) {
            if ($after !== 'first') $at++;
            array_splice($ids, $at, 0, [$newId]);
            save_order($db, $ids);
        }
    }
    out(['ok' => true, 'new_id' => $newId, 'key' => $type['key_name']]);
}

$q = $db->prepare("SELECT ss.*, st.key_name FROM site_sections ss JOIN section_types st ON st.id = ss.section_type_id WHERE ss.id = ? AND ss.school_id = ?");
$q->execute([$id, $sid]);
$sec = $q->fetch(PDO::FETCH_ASSOC);
if (!$sec) out(['ok' => false, 'error' => 'Section not found.']);

if ($action === 'move') {
    $dir = ($_POST['dir'] ?? '') === 'up' ? -1 : 1;
    $ids = ordered_ids($db, $sid);
    $i = array_search($id, $ids, true);
    $j = $i + $dir;
    if ($i === false || !isset($ids[$j])) out(['ok' => true, 'unchanged' => true]);
    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    save_order($db, $ids);
    out(['ok' => true]);
}

if ($action === 'toggle') {
    $db->prepare("UPDATE site_sections SET is_visible = NOT is_visible WHERE id = ? AND school_id = ?")->execute([$id, $sid]);
    out(['ok' => true]);
}

if ($action === 'remove') {
    $db->prepare("DELETE FROM site_sections WHERE id = ? AND school_id = ?")->execute([$id, $sid]);
    out(['ok' => true]);
}

if ($action === 'duplicate') {
    $copy = $sec; unset($copy['id'], $copy['key_name']);
    $cols = array_keys($copy);
    $db->prepare("INSERT INTO site_sections (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")
       ->execute(array_values($copy));
    $newId = (int)$db->lastInsertId();
    $ids = ordered_ids($db, $sid);
    $ids = array_values(array_filter($ids, fn($x) => $x !== $newId));
    array_splice($ids, array_search($id, $ids, true) + 1, 0, [$newId]); // right after the original
    save_order($db, $ids);
    out(['ok' => true, 'new_id' => $newId]);
}

if ($action === 'variant') {
    $variant = trim($_POST['variant'] ?? '');
    $cfg = get_section_variant_registry()[$sec['key_name']] ?? null;
    if (!$cfg || empty($cfg['options'][$variant])) out(['ok' => false, 'error' => 'That layout is not available.']);
    $db->prepare("UPDATE site_sections SET layout_variant = ? WHERE id = ? AND school_id = ?")->execute([$variant, $id, $sid]);
    out(['ok' => true]);
}
