<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
require_once __DIR__ . '/../includes/payments.php';
$db = get_db();
$schoolId = $user['school_id'];

$orderId = (int)($_POST['order_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

$order = $db->prepare("SELECT * FROM orders WHERE id = ? AND school_id = ? AND status = 'paid'");
$order->execute([$orderId, $schoolId]);
$order = $order->fetch();

if ($order && $reason) {
    request_refund($db, $orderId, $order['total_amount'], $reason);
}

header("Location: invoice.php?id=$orderId&refund_requested=1");
exit;
