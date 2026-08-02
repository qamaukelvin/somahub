<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
$db = get_db();

$id = (int)($_POST['id'] ?? 0);
$direction = $_POST['direction'] ?? '';

// Confirm this section belongs to the logged-in school
$stmt = $db->prepare("SELECT * FROM site_sections WHERE id = ? AND school_id = ?");
$stmt->execute([$id, $user['school_id']]);
$current = $stmt->fetch();

header('Content-Type: application/json');

if (!$current) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Section not found']);
    exit;
}

// Find the immediate neighbor in the requested direction, among ALL this school's sections
// (position spans visible and hidden sections together)
$op = $direction === 'up' ? '<' : '>';
$order = $direction === 'up' ? 'DESC' : 'ASC';

$neighborStmt = $db->prepare("
    SELECT * FROM site_sections
    WHERE school_id = ? AND position $op ?
    ORDER BY position $order
    LIMIT 1
");
$neighborStmt->execute([$user['school_id'], $current['position']]);
$neighbor = $neighborStmt->fetch();

if (!$neighbor) {
    // Already at the top/bottom — nothing to do
    echo json_encode(['ok' => true, 'moved' => false]);
    exit;
}

// Swap positions
$swap = $db->prepare("UPDATE site_sections SET position = ? WHERE id = ? AND school_id = ?");
$swap->execute([$neighbor['position'], $current['id'], $user['school_id']]);
$swap->execute([$current['position'], $neighbor['id'], $user['school_id']]);

echo json_encode(['ok' => true, 'moved' => true]);
