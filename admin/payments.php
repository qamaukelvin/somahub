<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
require_once __DIR__ . '/../includes/payments.php';
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);

    if ($action === 'mark_paid') {
        $admin = current_user(); $done = mark_order_paid($db, $orderId, $admin['name'] ?? 'admin');
        header('Location: payments.php?' . ($done ? 'ok=1' : 'already=1')); exit;
    } elseif ($action === 'reject_code') {
        // Wrong or fake code: clear it so the school can submit the right one.
        $db->prepare("UPDATE orders SET mpesa_code = NULL, mpesa_code_submitted_at = NULL, payment_method = NULL WHERE id = ? AND status = 'pending'")->execute([$orderId]);
        header('Location: payments.php?rejected=1'); exit;
    } elseif ($action === 'cancel') {
        $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
    }
}

ensure_cart_schema($db);
$itemsFor = function (int $oid) use ($db) { $q = $db->prepare("SELECT label FROM order_items WHERE order_id = ?"); $q->execute([$oid]); return implode(', ', array_column($q->fetchAll(), 'label')); };
$dupCount = function (string $code, int $oid) use ($db) { $q = $db->prepare("SELECT COUNT(*) FROM orders WHERE mpesa_code = ? AND id <> ?"); $q->execute([$code, $oid]); return (int)$q->fetchColumn(); };
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
  <?php if (isset($_GET['ok'])): ?><div style="background:#E4F5EA;color:#1B4D3E;padding:10px 14px;border-radius:8px;margin-bottom:14px;">Payment confirmed and applied.</div><?php endif; ?>
  <?php if (isset($_GET['already'])): ?><div style="background:#FFF1DC;color:#8A5A12;padding:10px 14px;border-radius:8px;margin-bottom:14px;">That order was already handled, so nothing changed.</div><?php endif; ?>
  <?php if (isset($_GET['rejected'])): ?><div style="background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:8px;margin-bottom:14px;">Code rejected. The school can submit another.</div><?php endif; ?>

  <h3>Awaiting your verification (<?= count($awaitingVerification) ?>)</h3>
  <p style="font-size:0.85rem;color:#666;">Check your M-Pesa messages for a matching code and amount before confirming.</p>
  <?php foreach ($awaitingVerification as $o): ?>
    <div class="pay-card">
      <strong><?= htmlspecialchars($o['school_name']) ?></strong> — KSh <?= number_format($o['total_amount'], 2) ?><br>
      Reference: <?= htmlspecialchars($o['reference_code']) ?><br>
      Items: <?= htmlspecialchars($itemsFor((int)$o['id'])) ?><br>
      Method: <?= htmlspecialchars(ucfirst(str_replace('_',' ',$o['payment_method']))) ?><br>
      Code submitted: <span class="code"><?= htmlspecialchars($o['mpesa_code']) ?></span><br>
      <small style="color:#888;">Submitted <?= date('d M Y, H:i', strtotime($o['mpesa_code_submitted_at'])) ?></small>
      <?php if ($dupCount($o['mpesa_code'], (int)$o['id']) > 0): ?><div style="margin-top:8px;background:#FBE8E4;color:#8C3B2E;padding:8px 12px;border-radius:8px;font-size:.84rem;font-weight:700;">Warning: this code was already used on another order. Check before confirming.</div><?php endif; ?>
      <div class="pay-actions">
        <form method="POST" onsubmit="return confirm('Confirm this payment as verified in your M-Pesa messages?')">
          <input type="hidden" name="action" value="mark_paid"><input type="hidden" name="order_id" value="<?= $o['id'] ?>">
          <button type="submit" class="btn-confirm">✓ Confirm Paid</button>
        </form>
        <form method="POST" onsubmit="return confirm('Reject this code? The school will be asked to submit it again.')">
          <input type="hidden" name="action" value="reject_code"><input type="hidden" name="order_id" value="<?= $o['id'] ?>">
          <button type="submit" class="btn-cancel" style="background:#FFF1DC;color:#8A5A12;">Reject code</button>
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
    <thead><tr><th>School</th><th>Reference</th><th>Amount</th><th>Paid</th><th>Code</th></tr></thead>
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
