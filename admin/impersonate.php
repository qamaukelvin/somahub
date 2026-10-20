<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
$db = get_db();

$schoolId = (int)($_POST['school_id'] ?? 0);

$stmt = $db->prepare("SELECT id, slug FROM schools WHERE id = ?");
$stmt->execute([$schoolId]);
$__sch = $stmt->fetch();
if (!$__sch) {
    die('School not found.');
}

$_SESSION['impersonating_school_id'] = $schoolId;
header('Location: ' . (!empty($__sch['slug']) ? 'https://' . $__sch['slug'] . '.somahub.top/?edit=1' : '../dashboard/index.php'));
exit;