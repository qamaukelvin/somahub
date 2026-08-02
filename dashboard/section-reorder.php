<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
$db = get_db();

$input = json_decode(file_get_contents('php://input'), true);
$order = $input['order'] ?? [];

$stmt = $db->prepare("UPDATE site_sections SET position = ? WHERE id = ? AND school_id = ?");
foreach ($order as $position => $sectionId) {
    $stmt->execute([$position, (int)$sectionId, $user['school_id']]);
}

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
