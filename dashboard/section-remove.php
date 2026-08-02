<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
$db = get_db();

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

$stmt = $db->prepare("DELETE FROM site_sections WHERE id = ? AND school_id = ?");
$stmt->execute([$id, $user['school_id']]);

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
