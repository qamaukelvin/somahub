<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_school_login();
require_once __DIR__ . '/../includes/payments.php';
$db = get_db();
$schoolId = $user['school_id'];

$products = $db->query("SELECT * FROM products WHERE is_active = 1")->fetchAll();

$order = null;
$error = '';

// Resume an existing pending order for this school, if one exists
$existing = $db->prepare("SELECT * FROM orders WHERE school_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
$existing->execute([$schoolId]);
$order = $existing->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_order') {
        $selected = $_POST['products'] ?? [];
        if (!$selected) {
            $error = 'Please select at least one item.';
        } else {
            $orderId = create_order($db, $schoolId, $selected);
            header("Location: checkout.php");
            exit;
        }
    } elseif ($action === 'submit_code' && $order) {
        $method = $_POST['payment_method'] ?? '';
        $code = trim($_POST['mpesa_code'] ?? '');
        if (!$code) {
            $error = 'Please enter your M-Pesa confirmation code.';
        } else {
            submit_mpesa_code($db, $order['id'], $method, $code);
            header("Location: checkout.php?submitted=1");
            exit;
        }
    }
}

if ($order) {
    $items = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $items->execute([$order['id']]);
    $order['items'] = $items->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .box{background:#fff;border-radius:8px;padding:24px;max-width:520px;margin-bottom:20px;}
  .product-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #eee;}
  .product-row:last-child{border-bottom:none;}
  .total-row{display:flex;justify-content:space-between;font-weight:800;padding-top:14px;font-size:1.05rem;}
  .pay-option{border:1px solid #ddd;border-radius:8px;padding:14px 16px;margin-bottom:10px;}
  .pay-option strong{display:block;margin-bottom:4px;}
  .ref-code{font-family:monospace;font-size:1.3rem;font-weight:800;background:#F4F1E6;padding:10px 16px;border-radius:6px;display:inline-block;margin:10px 0;}
  .notice-success{background:#E4F5EA;color:#1B4D3E;padding:10px 14px;border-radius:6px;font-size:0.85rem;margin-bottom:16px;}
  .notice-error{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:6px;font-size:0.85rem;margin-bottom:16px;}
  input[type=text]{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:12px;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Upgrade / Checkout</h1>
  <?php if ($error): ?><div class="notice-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if (isset($_GET['submitted'])): ?><div class="notice-success">✓ Code submitted — we'll confirm your payment shortly and activate your plan.</div><?php endif; ?>

  <?php if (!$order): ?>
    <div class="box">
      <h3>Select what you'd like</h3>
      <form method="POST">
        <input type="hidden" name="action" value="create_order">
        <?php foreach ($products as $p): ?>
          <div class="product-row">
            <label style="display:flex;align-items:center;gap:10px;">
              <input type="checkbox" name="products[]" value="<?= htmlspecialchars($p['product_key']) ?>">
              <span><strong><?= htmlspecialchars($p['label']) ?></strong><br><small style="color:#888;"><?= htmlspecialchars($p['description']) ?></small></span>
            </label>
            <span>KSh <?= number_format($p['price'], 2) ?><?= $p['billing_cycle'] === 'yearly' ? '/yr' : '' ?></span>
          </div>
        <?php endforeach; ?>
        <button type="submit" class="btn" style="margin-top:16px;">Continue to Payment</button>
      </form>
    </div>

  <?php else: ?>
    <div class="box">
      <h3>Order Summary</h3>
      <?php foreach ($order['items'] as $item): ?>
        <div class="product-row">
          <span><?= htmlspecialchars($item['label']) ?></span>
          <span>KSh <?= number_format($item['amount'], 2) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="total-row"><span>Total</span><span>KSh <?= number_format($order['total_amount'], 2) ?></span></div>
    </div>

    <div class="box">
      <h3>How to Pay</h3>
      <p>Your reference: <span class="ref-code"><?= htmlspecialchars($order['reference_code']) ?></span></p>
      <p style="font-size:0.85rem;color:#666;">Add this reference in the M-Pesa transaction message/note if the option is available — it helps us match your payment faster.</p>

      <div class="pay-option">
        <strong>Option 1 — Till Number</strong>
        Till No: <strong><?= htmlspecialchars(PAYMENT_TILL_NUMBER) ?></strong><br>
        Amount: KSh <?= number_format($order['total_amount'], 2) ?><br>
        Name shown: <?= htmlspecialchars(PAYMENT_DISPLAY_NAME) ?>
      </div>
      <div class="pay-option">
        <strong>Option 2 — Pochi la Biashara</strong>
        Send to: <strong><?= htmlspecialchars(PAYMENT_POCHI_NUMBER) ?></strong><br>
        Amount: KSh <?= number_format($order['total_amount'], 2) ?><br>
        Name shown: <?= htmlspecialchars(PAYMENT_DISPLAY_NAME) ?>
      </div>
      <div class="pay-option">
        <strong>Option 3 — Send Money</strong>
        Send to: <strong><?= htmlspecialchars(PAYMENT_SEND_MONEY_NUMBER) ?></strong><br>
        Amount: KSh <?= number_format($order['total_amount'], 2) ?><br>
        Name shown: <?= htmlspecialchars(PAYMENT_DISPLAY_NAME) ?>
      </div>
      <div class="pay-option">
        <strong>Option 4 — Bank Deposit (Equity Paybill)</strong>
        Paybill No: <strong><?= htmlspecialchars(PAYMENT_EQUITY_PAYBILL) ?></strong><br>
        Account No: <strong><?= htmlspecialchars(PAYMENT_EQUITY_ACCOUNT_NUMBER) ?></strong><br>
        Amount: KSh <?= number_format($order['total_amount'], 2) ?><br>
        <small style="color:#888;">Deposits directly to the business bank account — works from M-Pesa (Lipa na M-Pesa → Pay Bill) or any bank's own paybill/bank transfer.</small>
      </div>

      <p style="font-size:0.8rem;color:#888;">Note: this number currently shows my personal name, not "Somahub" — this is temporary until the business is formally registered.</p>

      <h4 style="margin-top:20px;">Already paid? Submit your code</h4>
      <form method="POST">
        <input type="hidden" name="action" value="submit_code">
        <label>Which option did you use?</label>
        <select name="payment_method" required style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:12px;">
          <option value="till">Till Number</option>
          <option value="pochi">Pochi la Biashara</option>
          <option value="send_money">Send Money</option>
          <option value="equity_paybill">Bank Deposit (Equity Paybill)</option>
        </select>
        <label>Confirmation Code</label>
        <input type="text" name="mpesa_code" placeholder="e.g. QJH7XXXXXX (M-Pesa code, or bank transaction ref for direct bank deposits)" required <?= $order['mpesa_code'] ? 'value="'.htmlspecialchars($order['mpesa_code']).'"' : '' ?>>
        <button type="submit" class="btn">Submit Code</button>
      </form>

      <?php if ($order['mpesa_code_submitted_at']): ?>
        <p style="font-size:0.82rem;color:#1B4D3E;margin-top:10px;">✓ Code submitted <?= date('d M Y, H:i', strtotime($order['mpesa_code_submitted_at'])) ?> — awaiting confirmation.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <p><a href="invoices.php">View my invoices →</a></p>
</main>
</body>
</html>
