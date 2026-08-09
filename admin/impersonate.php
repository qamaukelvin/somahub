<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

$schoolId = (int)($_POST['school_id'] ?? 0);

$stmt = $db->prepare("SELECT id FROM schools WHERE id = ?");
$stmt->execute([$schoolId]);
if (!$stmt->fetch()) {
    die('School not found.');
}

$_SESSION['impersonating_school_id'] = $schoolId;
header('Location: ../dashboard/sections.php');
exit;