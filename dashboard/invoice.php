<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
require_once __DIR__ . '/../includes/payments.php';
$db = get_db();
$schoolId = $user['school_id'];

$orderId = (int)($_GET['id'] ?? 0);
$order = $db->prepare("SELECT o.*, s.name AS school_name FROM orders o JOIN schools s ON s.id = o.school_id WHERE o.id = ? AND o.school_id = ?");
$order->execute([$orderId, $schoolId]);
$order = $order->fetch();

if (!$order) { http_response_code(404); echo "Invoice not found."; exit; }

$items = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items->execute([$orderId]);
$items = $items->fetchAll();

$isPaid = $order['status'] === 'paid' || $order['status'] === 'refunded';
$docLabel = $isPaid ? 'Receipt' : 'Invoice';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $docLabel ?> <?= htmlspecialchars($order['reference_code']) ?></title>
<style>
  body{font-family:Arial,sans-serif;background:#F7F2E7;margin:0;color:#1C1C16;}
  .wrap{max-width:600px;margin:0 auto;padding:32px 20px;}
  .doc{background:#fff;border-radius:10px;padding:32px;}
  .doc-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;border-bottom:2px solid #0F5257;padding-bottom:16px;}
  .brand{font-weight:800;color:#0F5257;font-size:20px;}
  .doc-title{text-align:right;}
  .doc-title h2{margin:0;color:#0F5257;}
  table{width:100%;border-collapse:collapse;margin:20px 0;}
  th{text-align:left;border-bottom:1px solid #ddd;padding:8px 0;font-size:0.8rem;color:#888;}
  td{padding:10px 0;border-bottom:1px solid #f0f0f0;}
  .total-row td{font-weight:800;border-top:2px solid #0F5257;border-bottom:none;padding-top:14px;}
  .status-badge{display:inline-block;padding:4px 12px;border-radius:12px;font-size:0.8rem;font-weight:700;color:#fff;}
  .meta{font-size:0.85rem;color:#666;margin-bottom:4px;}
  .print-btn{background:#0F5257;color:#fff;border:none;padding:10px 20px;border-radius:6px;font-weight:700;cursor:pointer;margin-top:20px;}
  @media print { .print-btn, header, .no-print { display:none; } body{background:#fff;} }
</style>
</head>
<body>
<div class="wrap">
  <div class="doc">
    <div class="doc-header">
      <div>
        <div class="brand">● somahub</div>
        <div class="meta">somahub.top</div>
      </div>
      <div class="doc-title">
        <h2><?= $docLabel ?></h2>
        <div class="meta">Ref: <?= htmlspecialchars($order['reference_code']) ?></div>
        <div class="meta">Date: <?= date('d M Y', strtotime($order['created_at'])) ?></div>
      </div>
    </div>

    <p><strong>Billed to:</strong> <?= htmlspecialchars($order['school_name']) ?></p>
    <p>
      Status:
      <span class="status-badge" style="background:<?= $order['status']==='paid'?'#1B4D3E':($order['status']==='refunded'?'#8C3B2E':'#8C6D1F') ?>;">
        <?= ucfirst($order['status']) ?>
      </span>
    </p>

    <table>
      <tr><th>Item</th><th style="text-align:right;">Amount</th></tr>
      <?php foreach ($items as $item): ?>
      <tr><td><?= htmlspecialchars($item['label']) ?></td><td style="text-align:right;">KSh <?= number_format($item['amount'], 2) ?></td></tr>
      <?php endforeach; ?>
      <tr class="total-row"><td>Total</td><td style="text-align:right;">KSh <?= number_format($order['total_amount'], 2) ?></td></tr>
    </table>

    <?php if ($isPaid): ?>
      <p class="meta">Paid on <?= date('d M Y', strtotime($order['paid_at'])) ?> via <?= htmlspecialchars(ucfirst(str_replace('_',' ',$order['payment_method']))) ?><?= $order['mpesa_code'] ? ' — Code: '.htmlspecialchars($order['mpesa_code']) : '' ?></p>
    <?php endif; ?>

    <button class="print-btn no-print" onclick="window.print()">Print / Save as PDF</button>

    <?php if ($order['status'] === 'paid'): ?>
      <div class="no-print" style="margin-top:24px;border-top:1px solid #eee;padding-top:20px;">
        <?php
        $refundCheck = $db->prepare("SELECT status FROM refunds WHERE order_id = ? ORDER BY requested_at DESC LIMIT 1");
        $refundCheck->execute([$orderId]);
        $existingRefund = $refundCheck->fetchColumn();
        ?>
        <?php if ($existingRefund): ?>
          <p style="font-size:0.85rem;color:#666;">Refund status: <strong><?= ucfirst($existingRefund) ?></strong></p>
        <?php elseif (isset($_GET['refund_requested'])): ?>
          <p style="font-size:0.85rem;color:#1B4D3E;">✓ Refund request submitted — we'll be in touch.</p>
        <?php else: ?>
          <details>
            <summary style="cursor:pointer;color:#0F5257;font-size:0.85rem;">Request a refund</summary>
            <form method="POST" action="refund-request.php" style="margin-top:10px;">
              <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
              <textarea name="reason" rows="3" placeholder="Reason for refund" style="width:100%;box-sizing:border-box;padding:8px;border:1px solid #ccc;border-radius:6px;margin-bottom:10px;" required></textarea>
              <button type="submit" style="background:#8C3B2E;color:#fff;border:none;padding:8px 18px;border-radius:6px;cursor:pointer;">Submit Refund Request</button>
            </form>
          </details>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
