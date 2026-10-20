<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
$user = require_school_login();
require_once __DIR__ . '/../includes/payments.php';
require_once __DIR__ . '/../includes/mailer.php';
$db = get_db();
$schoolId = $user['school_id'];

ensure_cart_schema($db);
$products = $db->query("SELECT * FROM products WHERE is_active = 1 ORDER BY price DESC")->fetchAll();
$schoolRow = $db->prepare("SELECT * FROM schools WHERE id = ?"); $schoolRow->execute([$schoolId]); $schoolRow = $schoolRow->fetch();

// Unpaid orders with no payment code after 14 days are tidied away so the school can start fresh.
$db->prepare("UPDATE orders SET status = 'cancelled' WHERE school_id = ? AND status = 'pending' AND mpesa_code IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY)")->execute([$schoolId]);

$error = '';
$notice = '';

$existing = $db->prepare("SELECT * FROM orders WHERE school_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
$existing->execute([$schoolId]);
$order = $existing->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'cart_set') {
        $key = (string)($_POST['product'] ?? '');
        cart_set($db, $schoolId, $key, (int)($_POST['qty'] ?? 1));
        header('Location: checkout.php'); exit;
    } elseif ($action === 'cart_clear') {
        cart_clear($db, $schoolId);
        header('Location: checkout.php'); exit;
    } elseif ($action === 'place_order') {
        if ($order) { $error = 'You already have an unpaid order. Pay for it or cancel it first.'; }
        else {
            try { create_order_from_cart($db, $schoolId); header('Location: checkout.php'); exit; }
            catch (Exception $e) { $error = $e->getMessage(); }
        }
    } elseif ($action === 'cancel_order' && $order) {
        cancel_pending_order($db, (int)$order['id'], (int)$schoolId);
        header('Location: checkout.php?cancelled=1'); exit;
    } elseif ($action === 'submit_code' && $order) {
        $method = in_array($_POST['payment_method'] ?? '', ['till', 'pochi', 'send_money', 'equity_paybill'], true) ? $_POST['payment_method'] : 'till';
        $code = trim($_POST['mpesa_code'] ?? '');
        if (!$code) {
            $error = 'Please enter your M-Pesa confirmation code.';
        } elseif ($err = submit_mpesa_code($db, (int)$order['id'], $method, $code)) {
            $error = $err;
        } else {
            $schoolName = $schoolRow['name'];
            $adminBody = "
                <h2 style='color:#0F5257;margin-top:0;'>Payment Code Submitted</h2>
                <table style='width:100%;font-size:14px;margin:16px 0;'>
                    <tr><td style='color:#6E6A5C;padding:4px 0;'>School</td><td><strong>" . htmlspecialchars($schoolName) . "</strong></td></tr>
                    <tr><td style='color:#6E6A5C;padding:4px 0;'>Order</td><td>#" . htmlspecialchars($order['reference_code']) . "</td></tr>
                    <tr><td style='color:#6E6A5C;padding:4px 0;'>Amount</td><td>KSh " . number_format($order['total_amount'], 2) . "</td></tr>
                    <tr><td style='color:#6E6A5C;padding:4px 0;'>Method</td><td>" . htmlspecialchars($method) . "</td></tr>
                    <tr><td style='color:#6E6A5C;padding:4px 0;'>Code</td><td><strong>" . htmlspecialchars(strtoupper($code)) . "</strong></td></tr>
                </table>
                <p><a href='https://somahub.top/admin/payments.php' style='color:#0F5257;font-weight:700;'>Verify in Admin &rarr;</a></p>
            ";
            send_somahub_email(get_setting($db, 'admin_notify_email', 'admin@somahub.top'), "Payment code submitted: {$schoolName}", $adminBody);
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
$cart = cart_get($db, $schoolId);
$cartMap = []; foreach ($cart as $c) $cartMap[$c['product_key']] = $c['qty'];
$cartTotal = cart_total($cart);
$owned = ['custom_domain' => !empty($schoolRow['custom_domain_enabled']), 'custom_templates' => !empty($schoolRow['custom_templates_enabled'])];
$planLine = '';
if (in_array($schoolRow['plan'], ['paid', 'promo_paid'], true) && !empty($schoolRow['promo_ends_at'])) {
    $planLine = ($schoolRow['plan'] === 'paid' ? 'Premium until ' : 'Trial until ') . date('d M Y', strtotime($schoolRow['promo_ends_at']));
} elseif ($schoolRow['plan'] === 'paid') { $planLine = 'Premium (no end date)'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout</title>
<?php include __DIR__ . '/_styles.php'; ?>
<style>
  .box{background:#fff;border-radius:12px;padding:24px;max-width:560px;margin-bottom:20px;}
  .product-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #eee;}
  .product-row:last-child{border-bottom:none;}
  .total-row{display:flex;justify-content:space-between;font-weight:800;padding-top:14px;font-size:1.05rem;}
  .pay-option{border:1px solid #ddd;border-radius:8px;padding:14px 16px;margin-bottom:10px;}
  .pay-option strong{display:block;margin-bottom:4px;}
  .ref-code{font-family:monospace;font-size:1.3rem;font-weight:800;background:#F4F1E6;padding:10px 16px;border-radius:6px;display:inline-block;margin:10px 0;}
  .notice-success{background:#E4F5EA;color:#1B4D3E;padding:10px 14px;border-radius:6px;font-size:0.85rem;margin-bottom:16px;}
  .notice-error{background:#FBE8E4;color:#8C3B2E;padding:10px 14px;border-radius:6px;font-size:0.85rem;margin-bottom:16px;}
  .shop{display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;} @media(max-width:820px){.shop{grid-template-columns:1fr;}}
  .pcard{display:flex;justify-content:space-between;gap:16px;align-items:center;background:#fff;border:1.5px solid var(--line);border-radius:14px;padding:16px 18px;margin-bottom:12px;} .pcard.in{border-color:var(--teal);background:#F4FAFA;}
  .pinfo strong{display:block;color:var(--teal-deep);} .pinfo small{color:#777;display:block;margin-top:4px;line-height:1.45;}
  .pbuy{text-align:right;flex:0 0 auto;} .pbuy b{display:block;font-size:1.05rem;margin-bottom:8px;color:var(--teal);} .pbuy b span{font-size:.75rem;color:#888;font-weight:600;}
  .pbuy select{padding:8px 10px;border:1.5px solid var(--line);border-radius:10px;font:inherit;} .btn.sm{padding:8px 16px;font-size:.82rem;}
  .owned{background:#DCEFE1;color:#1B4D3E;border-radius:99px;padding:5px 12px;font-size:.78rem;font-weight:800;}
  .cart{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px;position:sticky;top:76px;box-shadow:0 2px 10px rgba(0,0,0,.05);} .cart h3{margin:0 0 12px;}
  .crow{display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid #eee;font-size:.92rem;}
  input[type=text]{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:12px;}
</style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<main class="wrap">
  <h1>Plans &amp; services</h1>
  <?php if ($error): ?><div class="notice-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if (isset($_GET['cancelled'])): ?><div class="notice-success">Order cancelled. Your cart is ready for a new one.</div><?php endif; ?>
  <?php if (isset($_GET['submitted'])): ?><div class="notice-success">✓ Code submitted — we'll confirm your payment shortly and activate your plan.</div><?php endif; ?>

  <?php if (!$order): ?>
    <?php if ($planLine): ?><p class="sub" style="margin-bottom:16px;">Your plan: <strong><?= htmlspecialchars($planLine) ?></strong>. Renewing adds time on top of what you have left.</p><?php endif; ?>
    <div class="shop">
      <div>
        <?php foreach ($products as $p):
          $k = $p['product_key']; $inCart = $cartMap[$k] ?? 0; $max = product_max_qty($k); $ql = product_qty_label($k); $isOwned = $owned[$k] ?? false; ?>
          <div class="pcard<?= $inCart ? ' in' : '' ?>">
            <div class="pinfo"><strong><?= htmlspecialchars($p['label']) ?></strong><small><?= htmlspecialchars($p['description']) ?></small></div>
            <div class="pbuy">
              <b>KSh <?= number_format($p['price'], 0) ?><?= $p['billing_cycle'] === 'yearly' ? '<span>/yr</span>' : '' ?></b>
              <?php if ($isOwned): ?><span class="owned">Active</span>
              <?php else: ?>
              <form method="POST" class="no-loader">
                <input type="hidden" name="action" value="cart_set"><input type="hidden" name="product" value="<?= htmlspecialchars($k) ?>">
                <?php if ($max > 1): ?>
                  <select name="qty" onchange="this.form.submit()" aria-label="Quantity">
                    <option value="0">Not added</option>
                    <?php for ($i = 1; $i <= $max; $i++): ?><option value="<?= $i ?>" <?= $inCart === $i ? 'selected' : '' ?>><?= $i ?> <?= $ql ?><?= $i > 1 ? 's' : '' ?></option><?php endfor; ?>
                  </select>
                <?php elseif ($inCart): ?>
                  <input type="hidden" name="qty" value="0"><button type="submit" class="btn secondary sm">Remove</button>
                <?php else: ?>
                  <input type="hidden" name="qty" value="1"><button type="submit" class="btn sm">Add to cart</button>
                <?php endif; ?>
              </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <aside class="cart">
        <h3>Your cart <?= $cart ? '(' . array_sum(array_column($cart, 'qty')) . ')' : '' ?></h3>
        <?php if (!$cart): ?>
          <p style="color:#888;font-size:.9rem;">Nothing here yet. Add the plan or a service from the list.</p>
        <?php else: ?>
          <?php foreach ($cart as $c): ?>
            <div class="crow"><span><?= htmlspecialchars($c['label']) ?><?= $c['qty'] > 1 ? ' <small>× ' . $c['qty'] . '</small>' : '' ?></span><span>KSh <?= number_format($c['line'], 0) ?></span></div>
          <?php endforeach; ?>
          <div class="total-row"><span>Total</span><span>KSh <?= number_format($cartTotal, 0) ?></span></div>
          <form method="POST" class="loader-on-submit" style="margin-top:14px;"><input type="hidden" name="action" value="place_order"><button type="submit" class="btn" style="width:100%;">Continue to payment</button></form>
          <form method="POST" class="no-loader" style="margin-top:8px;text-align:center;"><input type="hidden" name="action" value="cart_clear"><button type="submit" style="background:none;border:none;color:#8C3B2E;cursor:pointer;font-size:.82rem;">Empty cart</button></form>
        <?php endif; ?>
      </aside>
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
      <form method="POST" class="no-loader" onsubmit="return confirm('Cancel this order and go back to your cart?')" style="margin-top:14px;"><input type="hidden" name="action" value="cancel_order"><button type="submit" style="background:none;border:none;color:#8C3B2E;cursor:pointer;font-size:.85rem;text-decoration:underline;padding:0;">Cancel this order</button></form>
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
      <?php if (PAYMENT_EQUITY_ACCOUNT_NUMBER !== ''): ?>
      <div class="pay-option">
        <strong>Option 4 — Bank Deposit (Equity Paybill)</strong>
        Paybill No: <strong><?= htmlspecialchars(PAYMENT_EQUITY_PAYBILL) ?></strong><br>
        Account No: <strong><?= htmlspecialchars(PAYMENT_EQUITY_ACCOUNT_NUMBER) ?></strong><br>
        Amount: KSh <?= number_format($order['total_amount'], 2) ?><br>
        <small style="color:#888;">Deposits directly to the business bank account — works from M-Pesa (Lipa na M-Pesa → Pay Bill) or any bank's own paybill/bank transfer.</small>
      </div>
      <?php endif; ?>

      <p style="font-size:0.8rem;color:#888;">Note: this number currently shows my personal name, not "Somahub" — this is temporary until the business is formally registered.</p>

      <h4 style="margin-top:20px;">Already paid? Submit your code</h4>
      <form method="POST" class="loader-on-submit">
        <input type="hidden" name="action" value="submit_code">
        <label>Which option did you use?</label>
        <select name="payment_method" required style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:12px;">
          <option value="till">Till Number</option>
          <option value="pochi">Pochi la Biashara</option>
          <option value="send_money">Send Money</option>
          <?php if (PAYMENT_EQUITY_ACCOUNT_NUMBER !== ''): ?>
          <option value="equity_paybill">Bank Deposit (Equity Paybill)</option>
          <?php endif; ?>
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
<?php include __DIR__ . '/../_loader.php'; ?>
</body>
</html>
