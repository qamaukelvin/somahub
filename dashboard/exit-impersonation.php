<?php
require_once __DIR__ . '/../includes/auth.php';

$schoolId = $_SESSION['impersonating_school_id'] ?? null;
unset($_SESSION['impersonating_school_id']);

if ($schoolId) {
    header('Location: ../admin/school-edit.php?id=' . (int)$schoolId);
} else {
    header('Location: ../admin/index.php');
}
exit;