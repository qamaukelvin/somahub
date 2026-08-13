<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
require_once __DIR__ . '/../includes/payments.php';
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);

    if ($action === 'mark_paid') {
        $admin = current_user(); mark_order_paid($db, $orderId, $admin['name'] ?? 'admin');
    } elseif ($action === 'cancel') {
        $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
    }
}

$awaitingVerification = $db->query("
    SELECT o.*, s.name AS school_name FROM orders o JOIN schools s ON s.id = o.school_id
    WHERE o.status = 'pending' AND o.mpesa_code IS NOT NULL
    ORDER BY o.mpesa_code_submitted_at ASC
")->fetchAll();

$stillWaitingOnSchool = $db->query("
    SELECT o.*, s.name AS school_name FROM orders o JOIN schools s ON s.id = o.school_id
    WHERE o.status = 'pending' AND o.mpesa_code IS NULL
    ORDER BY o.created_at DESC
")->fetchAll();

$recentPaid = $db->query("
    SELECT o.*, s.name AS school_name FROM orders o JOIN schools s ON s.id = o.school_id
    WHERE o.status = 'paid' ORDER BY o.paid_at DESC LIMIT 20
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .pay-card{background:#fff;border-radius:8px;padding:16px 20px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,0.08);}
  .pay-card .code{font-family:monospace;font-weight:800;font-size:1.1rem;color:#0F5257;}
  .pay-actions{display:flex;gap:10px;margin-top:12px;}
  .pay-actions button{padding:7px 16px;border-radius:5px;border:none;cursor:pointer;font-weight:700;font-size:0.82rem;}
  .btn-confirm{background:#1B4D3E;color:#fff;}
  .btn-cancel{background:#FBE8E4;color:#8C3B2E;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Payments</h1>

  <h3>Awaiting your verification (<?= count($awaitingVerification) ?>)</h3>
  <p style="font-size:0.85rem;color:#666;">Check your M-Pesa messages for a matching code and amount before confirming.</p>
  <?php foreach ($awaitingVerification as $o): ?>
    <div class="pay-card">
      <strong><?= htmlspecialchars($o['school_name']) ?></strong> — KSh <?= number_format($o['total_amount'], 2) ?><br>
      Reference: <?= htmlspecialchars($o['reference_code']) ?><br>
      Method: <?= htmlspecialchars(ucfirst(str_replace('_',' ',$o['payment_method']))) ?><br>
      Code submitted: <span class="code"><?= htmlspecialchars($o['mpesa_code']) ?></span><br>
      <small style="color:#888;">Submitted <?= date('d M Y, H:i', strtotime($o['mpesa_code_submitted_at'])) ?></small>
      <div class="pay-actions">
        <form method="POST" onsubmit="return confirm('Confirm this payment as verified in your M-Pesa messages?')">
          <input type="hidden" name="action" value="mark_paid"><input type="hidden" name="order_id" value="<?= $o['id'] ?>">
          <button type="submit" class="btn-confirm">✓ Confirm Paid</button>
        </form>
        <form method="POST" onsubmit="return confirm('Cancel this order?')">
          <input type="hidden" name="action" value="cancel"><input type="hidden" name="order_id" value="<?= $o['id'] ?>">
          <button type="submit" class="btn-cancel">Cancel Order</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$awaitingVerification): ?><p style="color:#888;">Nothing awaiting verification.</p><?php endif; ?>

  <h3 style="margin-top:32px;">Pending — school hasn't submitted a code yet (<?= count($stillWaitingOnSchool) ?>)</h3>
  <?php foreach ($stillWaitingOnSchool as $o): ?>
    <div class="pay-card">
      <strong><?= htmlspecialchars($o['school_name']) ?></strong> — KSh <?= number_format($o['total_amount'], 2) ?> · Ref: <?= htmlspecialchars($o['reference_code']) ?>
      <br><small style="color:#888;">Created <?= date('d M Y', strtotime($o['created_at'])) ?></small>
    </div>
  <?php endforeach; ?>

  <h3 style="margin-top:32px;">Recently confirmed paid</h3>
  <table>
    <tr><th>School</th><th>Reference</th><th>Amount</th><th>Paid</th><th>Code</th></tr>
    <?php foreach ($recentPaid as $o): ?>
    <tr>
      <td data-label="School"><?= htmlspecialchars($o['school_name']) ?></td>
      <td data-label="Reference"><?= htmlspecialchars($o['reference_code']) ?></td>
      <td data-label="Amount">KSh <?= number_format($o['total_amount'], 2) ?></td>
      <td data-label="Paid"><?= date('d M Y', strtotime($o['paid_at'])) ?></td>
      <td data-label="Code"><?= htmlspecialchars($o['mpesa_code']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</main>
</body>
</html>
