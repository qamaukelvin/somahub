<?php
require_once __DIR__ . '/../includes/auth.php';
require_platform_admin();
require_once __DIR__ . '/../includes/payments.php';
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $refundId = (int)($_POST['refund_id'] ?? 0);

    if ($action === 'mark_sent') {
        process_refund($db, $refundId, 'sent', $_POST['admin_note'] ?? '');
    } elseif ($action === 'decline') {
        process_refund($db, $refundId, 'declined', $_POST['admin_note'] ?? '');
    }
}

$pending = $db->query("
    SELECT r.*, o.reference_code, o.total_amount, s.name AS school_name
    FROM refunds r JOIN orders o ON o.id = r.order_id JOIN schools s ON s.id = o.school_id
    WHERE r.status = 'requested' ORDER BY r.requested_at ASC
")->fetchAll();

$history = $db->query("
    SELECT r.*, o.reference_code, s.name AS school_name
    FROM refunds r JOIN orders o ON o.id = r.order_id JOIN schools s ON s.id = o.school_id
    WHERE r.status != 'requested' ORDER BY r.processed_at DESC LIMIT 20
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Refunds</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .refund-card{background:#fff;border-radius:8px;padding:16px 20px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,0.08);}
  .refund-actions{display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;}
  .refund-actions button{padding:7px 16px;border-radius:5px;border:none;cursor:pointer;font-weight:700;font-size:0.82rem;}
  .btn-sent{background:#1B4D3E;color:#fff;}
  .btn-decline{background:#FBE8E4;color:#8C3B2E;}
  .refund-actions input[type=text]{padding:6px 10px;border:1px solid #ccc;border-radius:5px;font-size:0.82rem;flex:1;min-width:160px;}
  .status-pill{display:inline-block;padding:3px 10px;border-radius:10px;font-size:0.75rem;font-weight:700;color:#fff;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Refunds</h1>
  <p style="font-size:0.85rem;color:#666;">Refunds are tracked here but sent manually via M-Pesa — marking "Sent" doesn't move any money automatically.</p>

  <h3>Pending requests (<?= count($pending) ?>)</h3>
  <?php foreach ($pending as $r): ?>
    <div class="refund-card">
      <strong><?= htmlspecialchars($r['school_name']) ?></strong> — KSh <?= number_format($r['amount'], 2) ?><br>
      Order Ref: <?= htmlspecialchars($r['reference_code']) ?> (Total: KSh <?= number_format($r['total_amount'], 2) ?>)<br>
      Reason: <?= htmlspecialchars($r['reason'] ?: '—') ?><br>
      <small style="color:#888;">Requested <?= date('d M Y', strtotime($r['requested_at'])) ?></small>

      <form method="POST" class="refund-actions">
        <input type="hidden" name="refund_id" value="<?= $r['id'] ?>">
        <input type="text" name="admin_note" placeholder="Note (e.g. M-Pesa code you sent it with)">
        <button type="submit" name="action" value="mark_sent" class="btn-sent">✓ Mark Sent</button>
        <button type="submit" name="action" value="decline" class="btn-decline">Decline</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$pending): ?><p style="color:#888;">No pending refund requests.</p><?php endif; ?>

  <h3 style="margin-top:32px;">History</h3>
  <table>
    <tr><th>School</th><th>Order</th><th>Amount</th><th>Status</th><th>Note</th></tr>
    <?php foreach ($history as $r): ?>
    <tr>
      <td data-label="School"><?= htmlspecialchars($r['school_name']) ?></td>
      <td data-label="Order"><?= htmlspecialchars($r['reference_code']) ?></td>
      <td data-label="Amount">KSh <?= number_format($r['amount'], 2) ?></td>
      <td data-label="Status"><span class="status-pill" style="background:<?= $r['status']==='sent'?'#1B4D3E':'#8C3B2E' ?>;"><?= ucfirst($r['status']) ?></span></td>
      <td data-label="Note"><?= htmlspecialchars($r['admin_note'] ?: '—') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</main>
</body>
</html>
