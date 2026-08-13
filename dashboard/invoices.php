<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
$db = get_db();
$schoolId = $user['school_id'];

$orders = $db->prepare("SELECT * FROM orders WHERE school_id = ? ORDER BY created_at DESC");
$orders->execute([$schoolId]);
$orders = $orders->fetchAll();

$statusLabels = ['pending' => 'Pending', 'paid' => 'Paid', 'refunded' => 'Refunded', 'cancelled' => 'Cancelled'];
$statusColors = ['pending' => '#8C6D1F', 'paid' => '#1B4D3E', 'refunded' => '#8C3B2E', 'cancelled' => '#888'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoices</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .status-pill{display:inline-block;padding:3px 10px;border-radius:10px;font-size:0.75rem;font-weight:700;color:#fff;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Invoices</h1>
  <table>
    <tr><th>Reference</th><th>Date</th><th>Amount</th><th>Status</th><th></th></tr>
    <?php foreach ($orders as $o): ?>
    <tr>
      <td data-label="Reference"><?= htmlspecialchars($o['reference_code']) ?></td>
      <td data-label="Date"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
      <td data-label="Amount">KSh <?= number_format($o['total_amount'], 2) ?></td>
      <td data-label="Status"><span class="status-pill" style="background:<?= $statusColors[$o['status']] ?>;"><?= $statusLabels[$o['status']] ?></span></td>
      <td data-label=""><a href="invoice.php?id=<?= $o['id'] ?>">View</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?>
    <tr><td colspan="5" style="text-align:center;color:#888;">No orders yet. <a href="checkout.php">Upgrade your plan →</a></td></tr>
    <?php endif; ?>
  </table>
</main>
</body>
</html>
