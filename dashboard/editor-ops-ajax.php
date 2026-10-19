<?php
// Structural actions for the on-page site editor: move, duplicate, remove,
// hide/show, change layout, add section, undo, redo. All answer with JSON,
// all are limited to the signed-in school, and all are undoable.
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/section_variants.php';
require_once __DIR__ . '/../includes/school-provision.php';
require_once __DIR__ . '/../includes/edit-history.php';
require_once __DIR__ . '/../includes/content-presets.php';
$user = require_school_login();
$db = get_db();
$sid = (int)$user['school_id'];
$action = $_POST['action'] ?? '';
$id = (int)($_POST['section_id'] ?? 0);

function out($a) { echo json_encode($a); exit; }

/** Fields a visitor can fill in place (plain text). Mirrors the editor's own filter. */
function fillable_field(string $f, string $type): bool {
    return $type !== 'image' && !preg_match('/(_color$|^cta_|destination|_url$|_link$|^rating|_icon$|phone|email|map|whatsapp|address|office_hours|date|amount|price|^layout)/i', $f);
}
function field_starter(string $f): string {
    $known = [
        'intro_text' => 'Add a short introduction here.', 'deadline_text' => 'Applications are open.',
        'summary_note' => 'Fees are reviewed each year. Contact the school office for details.',
        'body' => 'Write about this here.', 'headline' => 'Your headline here', 'subheading' => 'A short supporting line',
        'subtext' => 'A short supporting line', 'caption' => 'A short caption', 'author_name' => 'Name',
    ];
    return $known[$f] ?? ('Write your ' . strtolower(str_replace('_', ' ', $f)) . ' here.');
}
/** Starter content for a brand new section, so it shows up straight away instead of being an empty bar. */
function section_seed(string $key, array $schema, string $schoolName): array {
    $content = array_fill_keys(array_keys($schema), '');
    $preset = get_school_content_presets()['primary_day']['content'][$key] ?? null;
    if ($preset) {
        foreach ($preset as $f => $t) if (array_key_exists($f, $content)) $content[$f] = str_replace('{school}', $schoolName, $t);
        return $content;
    }
    $own = [
        'staff' => ['name_1' => 'New team member', 'role_1' => 'Role'],
        'testimonials' => ['quote_1' => 'Write what a parent or student said here.', 'author_1' => 'Name'],
        'blog' => ['title_1' => 'New post title', 'body_1' => 'Write your post here.'],
        'enrollment_form' => ['intro_text' => 'Apply online and we will get back to you.'],
        'results_lookup' => ['intro_text' => 'Enter your child\'s admission number to see their results.'],
    ][$key] ?? [];
    foreach ($own as $f => $t) if (array_key_exists($f, $content)) $content[$f] = $t;
    return $content;
}

if ($action === 'status') out(['ok' => true] + history_status($db, $sid));

if ($action === 'undo' || $action === 'redo') {
    $label = history_step($db, $sid, $action);
    if ($label === null) out(['ok' => false, 'error' => $action === 'undo' ? 'Nothing to undo.' : 'Nothing to redo.']);
    out(['ok' => true, 'label' => $label] + history_status($db, $sid));
}

$labels = ['move' => 'Moved a section', 'duplicate' => 'Duplicated a section', 'remove' => 'Removed a section',
           'toggle' => 'Showed or hid a section', 'variant' => 'Changed layout', 'add' => 'Added a section', 'reorder' => 'Reordered sections', 'item_add' => 'Added an item', 'item_remove' => 'Removed an item', 'field_fill' => 'Added a text field'];
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
    $nm = $db->prepare("SELECT name FROM schools WHERE id = ?"); $nm->execute([$sid]);
    $content = section_seed($type['key_name'], json_decode($type['schema_json'], true) ?: [], (string)$nm->fetchColumn());
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

if ($action === 'item_add' || $action === 'item_remove') {
    $cfg = get_section_variant_registry()[$sec['key_name']] ?? null;
    if (!$cfg || empty($cfg['repeatable']) || empty($cfg['item_fields'])) out(['ok' => false, 'error' => 'This section has no list items.']);
    $schema = json_decode($db->query("SELECT schema_json FROM section_types WHERE id = " . (int)$sec['section_type_id'])->fetchColumn(), true) ?: [];
    $content = json_decode($sec['content_json'], true) ?: [];
    $stems = $cfg['item_fields']; $max = (int)($cfg['max_items'] ?? 10);
    $fmt = $cfg['field_name_format'] ?? '{stem}_{index}';
    $fn = fn($st, $i) => str_replace(['{stem}', '{index}'], [$st, $i], $fmt);
    if ($action === 'item_remove') {
        $slot = (int)($_POST['slot'] ?? 0);
        if ($slot < 1 || $slot > $max) out(['ok' => false, 'error' => 'Item not found.']);
        foreach ($stems as $st) { $f = $fn($st, $slot); if (array_key_exists($f, $schema) || array_key_exists($f, $content)) $content[$f] = ''; }
    } else {
        $starter = [
            'staff' => ['name' => 'New team member', 'role' => 'Role'],
            'testimonials' => ['quote' => 'Write what a parent or student said here.', 'author' => 'Name'],
            'faq' => ['question' => 'New question', 'answer' => 'Write the answer here.'],
            'blog' => ['title' => 'New post title', 'body' => 'Write your post here.'],
            'stats' => ['number' => '0', 'label' => 'New statistic'],
        ][$sec['key_name']] ?? null;
        if (!$starter) out(['ok' => false, 'error' => 'Add photos to this section instead.']);
        $slot = 0;
        for ($i = 1; $i <= $max && !$slot; $i++) {
            $used = false;
            foreach ($stems as $st) { if (!empty($content[$fn($st, $i)])) { $used = true; break; } }
            if (!$used) $slot = $i;
        }
        if (!$slot) out(['ok' => false, 'error' => 'This section is full (' . $max . ' items).']);
        foreach ($starter as $st => $text) { $f = $fn($st, $slot); if (array_key_exists($f, $schema)) $content[$f] = $text; }
    }
    $db->prepare("UPDATE site_sections SET content_json = ? WHERE id = ? AND school_id = ?")->execute([json_encode($content), $id, $sid]);
    out(['ok' => true, 'slot' => $slot]);
}

if ($action === 'field_fill') {
    $field = (string)($_POST['field'] ?? '');
    $schema = json_decode($db->query("SELECT schema_json FROM section_types WHERE id = " . (int)$sec['section_type_id'])->fetchColumn(), true) ?: [];
    $content = json_decode($sec['content_json'], true) ?: [];
    if (!isset($schema[$field]) || !fillable_field($field, (string)$schema[$field])) out(['ok' => false, 'error' => 'That field cannot be added here.']);
    if (!empty($content[$field])) out(['ok' => true, 'field' => $field]);
    $content[$field] = field_starter($field);
    $db->prepare("UPDATE site_sections SET content_json = ? WHERE id = ? AND school_id = ?")->execute([json_encode($content), $id, $sid]);
    out(['ok' => true, 'field' => $field]);
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
